<?php
/**
 * ✅ MODEL TỐI ƯU - Báo Cáo Doanh Số Nhân Viên + REDIS CACHE + DB BACKUP
 * Compatible với bảng summary_nhanvien_report_cache có sẵn
 */

require_once 'config/database.php';

class NhanVienReportModel {
    private $conn;
    private $redis;
    
    private const REDIS_HOST = '127.0.0.1';
    private const REDIS_PORT = 6379;
    private const REDIS_TTL = 3600; // 1 giờ

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->connectRedis();
    }
    
    /**
     * ✅ KẾT NỐI REDIS
     */
    private function connectRedis() {
        try {
            $this->redis = new Redis();
            $this->redis->connect(self::REDIS_HOST, self::REDIS_PORT, 2.5);
            $this->redis->ping();
        } catch (Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
            $this->redis = null;
        }
    }

    /**
     * ✅ LẤY TẤT CẢ NHÂN VIÊN - WITH REDIS CACHE + DB BACKUP
     */
    public function getAllEmployeesWithStats($tu_ngay, $den_ngay, $thang) {
        // 1️⃣ Tạo cache key
        $cacheKey = $this->generateCacheKey('employees', $thang, $tu_ngay, $den_ngay);
        
        // 2️⃣ Thử lấy từ Redis
        if ($this->redis) {
            try {
                $cached = $this->redis->get($cacheKey);
                if ($cached) {
                    return json_decode($cached, true);
                }
            } catch (Exception $e) {
                error_log("Redis get error: " . $e->getMessage());
            }
        }
        
        // 3️⃣ Thử lấy từ Database backup
        $dbResults = $this->getFromSummaryTable($cacheKey, 'employees');
        if (!empty($dbResults)) {
            $this->populateRedisFromDB($cacheKey, $dbResults);
            return $dbResults;
        }
        
        // 4️⃣ Query từ database chính
        list($year, $month) = explode('-', $thang);
        
        $sql = "SELECT 
                    o.DSRCode,
                    o.DSRTypeProvince,
                    
                    MAX(nv_info.TenNVBH) as ten_nhan_vien,
                    MAX(nv_info.MaGSBH) as ma_gsbh,
                    
                    SUM(CASE WHEN DATE(o.OrderDate) >= ? AND DATE(o.OrderDate) <= ? 
                        THEN o.TotalNetAmount ELSE 0 END) as ds_tien_do,
                    COUNT(DISTINCT CASE WHEN DATE(o.OrderDate) >= ? AND DATE(o.OrderDate) <= ? 
                        THEN DATE(o.OrderDate) END) as so_ngay_co_doanh_so_khoang,
                    COALESCE(MAX(ds_khoang.max_daily), 0) as ds_ngay_cao_nhat_nv_khoang,
                    
                    SUM(CASE WHEN o.RptYear = ? AND o.RptMonth = ? 
                        THEN o.TotalNetAmount ELSE 0 END) as ds_tong_thang_nv,
                    COUNT(DISTINCT CASE WHEN o.RptYear = ? AND o.RptMonth = ? 
                        THEN DATE(o.OrderDate) END) as so_ngay_co_doanh_so_thang,
                    COALESCE(MAX(ds_thang.max_daily), 0) as ds_ngay_cao_nhat_nv_thang
                    
                FROM orderdetail o
                
                LEFT JOIN (
                    SELECT DISTINCT MaNVBH, TenNVBH, MaGSBH
                    FROM dskh
                    WHERE MaNVBH IS NOT NULL AND MaNVBH != ''
                ) nv_info ON o.DSRCode = nv_info.MaNVBH
                
                LEFT JOIN (
                    SELECT 
                        DSRCode,
                        MAX(daily_amount) as max_daily
                    FROM (
                        SELECT 
                            DSRCode,
                            DATE(OrderDate) as order_date,
                            SUM(TotalNetAmount) as daily_amount
                        FROM orderdetail
                        WHERE DSRCode IS NOT NULL 
                        AND DSRCode != ''
                        AND DATE(OrderDate) >= ?
                        AND DATE(OrderDate) <= ?
                        GROUP BY DSRCode, DATE(OrderDate)
                    ) daily_khoang
                    GROUP BY DSRCode
                ) ds_khoang ON o.DSRCode = ds_khoang.DSRCode
                
                LEFT JOIN (
                    SELECT 
                        DSRCode,
                        MAX(daily_amount) as max_daily
                    FROM (
                        SELECT 
                            DSRCode,
                            DATE(OrderDate) as order_date,
                            SUM(TotalNetAmount) as daily_amount
                        FROM orderdetail
                        WHERE DSRCode IS NOT NULL 
                        AND DSRCode != ''
                        AND RptYear = ?
                        AND RptMonth = ?
                        GROUP BY DSRCode, DATE(OrderDate)
                    ) daily_thang
                    GROUP BY DSRCode
                ) ds_thang ON o.DSRCode = ds_thang.DSRCode
                
                WHERE o.DSRCode IS NOT NULL 
                AND o.DSRCode != ''
                AND (
                    (DATE(o.OrderDate) >= ? AND DATE(o.OrderDate) <= ?)
                    OR (o.RptYear = ? AND o.RptMonth = ?)
                )
                GROUP BY o.DSRCode, o.DSRTypeProvince
                HAVING ds_tien_do > 0 OR ds_tong_thang_nv > 0
                ORDER BY o.DSRCode";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $tu_ngay, $den_ngay,
            $tu_ngay, $den_ngay,
            $year, $month,
            $year, $month,
            $tu_ngay, $den_ngay,
            $year, $month,
            $tu_ngay, $den_ngay,
            $year, $month
        ]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 5️⃣ Lưu vào cache (Redis + Database)
        if (!empty($results)) {
            $this->saveCache($cacheKey, $results, $tu_ngay, $den_ngay, $thang, 'employees');
        }
        
        return $results;
    }

    /**
     * ✅ TỔNG THEO THÁNG - WITH REDIS CACHE + DB BACKUP
     */
    public function getSystemStatsForMonth($thang) {
        $cacheKey = "nhanvien:stats:month:{$thang}";
        
        // Thử lấy từ Redis
        if ($this->redis) {
            try {
                $cached = $this->redis->get($cacheKey);
                if ($cached) {
                    return json_decode($cached, true);
                }
            } catch (Exception $e) {
                error_log("Redis get error: " . $e->getMessage());
            }
        }
        
        // Thử lấy từ Database backup
        list($year, $month) = explode('-', $thang);
        $firstDay = "$thang-01";
        $lastDay = date('Y-m-t', strtotime($firstDay));
        
        $dbResults = $this->getFromSummaryTable($cacheKey, 'stats_month', $firstDay, $lastDay, $thang);
        if (!empty($dbResults)) {
            $this->populateRedisFromDB($cacheKey, $dbResults);
            return $dbResults;
        }
        
        // Query từ database chính
        $sql = "SELECT 
                    COALESCE(SUM(o.TotalNetAmount), 0) as total,
                    COUNT(DISTINCT o.DSRCode) as emp_count,
                    DAY(LAST_DAY(CONCAT(?, '-', LPAD(?, 2, '0'), '-01'))) as so_ngay,
                    
                    COALESCE(SUM(o.TotalNetAmount), 0) / 
                    NULLIF(COUNT(DISTINCT o.DSRCode) * DAY(LAST_DAY(CONCAT(?, '-', LPAD(?, 2, '0'), '-01'))), 0) as ds_tb_chung_thang,
                    
                    AVG(emp_max.max_daily) as ds_ngay_cao_nhat_tb_thang
                    
                FROM orderdetail o
                LEFT JOIN (
                    SELECT 
                        DSRCode, 
                        MAX(daily_total) as max_daily
                    FROM (
                        SELECT 
                            DSRCode, 
                            DATE(OrderDate) as order_date,
                            SUM(TotalNetAmount) as daily_total
                        FROM orderdetail
                        WHERE RptYear = ? AND RptMonth = ?
                        AND DSRCode IS NOT NULL AND DSRCode != ''
                        GROUP BY DSRCode, DATE(OrderDate)
                    ) daily
                    GROUP BY DSRCode
                ) emp_max ON o.DSRCode = emp_max.DSRCode
                WHERE o.RptYear = ? AND o.RptMonth = ?
                AND o.DSRCode IS NOT NULL AND o.DSRCode != ''";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$year, $month, $year, $month, $year, $month, $year, $month]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Lưu vào cache
        if (!empty($result)) {
            $this->saveCache($cacheKey, $result, $firstDay, $lastDay, $thang, 'stats_month');
        }
        
        return $result;
    }

    /**
     * ✅ TỔNG THEO KHOẢNG - WITH REDIS CACHE + DB BACKUP
     */
    public function getSystemStatsForRange($tu_ngay, $den_ngay) {
        $cacheKey = "nhanvien:stats:range:{$tu_ngay}:{$den_ngay}";
        
        // Thử lấy từ Redis
        if ($this->redis) {
            try {
                $cached = $this->redis->get($cacheKey);
                if ($cached) {
                    return json_decode($cached, true);
                }
            } catch (Exception $e) {
                error_log("Redis get error: " . $e->getMessage());
            }
        }
        
        // Thử lấy từ Database backup
        $dbResults = $this->getFromSummaryTable($cacheKey, 'stats_range', $tu_ngay, $den_ngay);
        if (!empty($dbResults)) {
            $this->populateRedisFromDB($cacheKey, $dbResults);
            return $dbResults;
        }
        
        // Query từ database chính
        $sql = "SELECT 
                    COALESCE(SUM(o.TotalNetAmount), 0) as total,
                    COUNT(DISTINCT o.DSRCode) as emp_count,
                    DATEDIFF(?, ?) + 1 as so_ngay,
                    
                    COALESCE(SUM(o.TotalNetAmount), 0) / 
                    NULLIF(COUNT(DISTINCT o.DSRCode) * (DATEDIFF(?, ?) + 1), 0) as ds_tb_chung_khoang,
                    
                    AVG(emp_max.max_daily) as ds_ngay_cao_nhat_tb_khoang
                    
                FROM orderdetail o
                LEFT JOIN (
                    SELECT 
                        DSRCode, 
                        MAX(daily_total) as max_daily
                    FROM (
                        SELECT 
                            DSRCode, 
                            DATE(OrderDate) as order_date,
                            SUM(TotalNetAmount) as daily_total
                        FROM orderdetail
                        WHERE DATE(OrderDate) >= ? AND DATE(OrderDate) <= ?
                        AND DSRCode IS NOT NULL AND DSRCode != ''
                        GROUP BY DSRCode, DATE(OrderDate)
                    ) daily
                    GROUP BY DSRCode
                ) emp_max ON o.DSRCode = emp_max.DSRCode
                WHERE DATE(o.OrderDate) >= ? AND DATE(o.OrderDate) <= ?
                AND o.DSRCode IS NOT NULL AND o.DSRCode != ''";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $den_ngay, $tu_ngay,
            $den_ngay, $tu_ngay,
            $tu_ngay, $den_ngay,
            $tu_ngay, $den_ngay
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Lưu vào cache
        if (!empty($result)) {
            $this->saveCache($cacheKey, $result, $tu_ngay, $den_ngay, '', 'stats_range');
        }
        
        return $result;
    }

    /**
     * ✅ LẤY TỪ DATABASE BACKUP TABLE
     * Compatible với cấu trúc: cache_key, data_type, thang, tu_ngay, den_ngay
     */
    private function getFromSummaryTable($cacheKey, $dataType, $tu_ngay = null, $den_ngay = null, $thang = null) {
        try {
            $sql = "SELECT data FROM summary_nhanvien_report_cache 
                    WHERE cache_key = ? AND data_type = ? LIMIT 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$cacheKey, $dataType]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row && !empty($row['data'])) {
                return json_decode($row['data'], true);
            }
        } catch (Exception $e) {
            error_log("Report database backup fetch error: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * ✅ LƯU CACHE (REDIS + DATABASE)
     * Compatible với cấu trúc bảng có sẵn
     */
    private function saveCache($cacheKey, $data, $tu_ngay, $den_ngay, $thang, $dataType) {
        try {
            // 1️⃣ Lưu Redis
            if ($this->redis) {
                $this->redis->setex(
                    $cacheKey, 
                    self::REDIS_TTL, 
                    json_encode($data, JSON_UNESCAPED_UNICODE)
                );
            }
            
            // 2️⃣ Tính toán metrics
            $employeeCount = 0;
            $suspectCount = 0;
            
            if ($dataType === 'employees' && is_array($data)) {
                $employeeCount = count($data);
                // Suspect: nhân viên có doanh số cao bất thường
                foreach ($data as $emp) {
                    $avgDaily = isset($emp['so_ngay_co_doanh_so_khoang']) && $emp['so_ngay_co_doanh_so_khoang'] > 0
                        ? $emp['ds_tien_do'] / $emp['so_ngay_co_doanh_so_khoang']
                        : 0;
                    $maxDaily = $emp['ds_ngay_cao_nhat_nv_khoang'] ?? 0;
                    
                    // Đánh dấu suspect nếu doanh số ngày cao nhất > 3x trung bình
                    if ($maxDaily > $avgDaily * 3 && $avgDaily > 0) {
                        $suspectCount++;
                    }
                }
            }
            
            // 3️⃣ Lưu Database
            $sql = "INSERT INTO summary_nhanvien_report_cache 
                    (cache_key, thang, tu_ngay, den_ngay, data_type, data, employee_count, suspect_count)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    data = VALUES(data),
                    employee_count = VALUES(employee_count),
                    suspect_count = VALUES(suspect_count),
                    calculated_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $cacheKey,
                $thang ?: date('Y-m', strtotime($tu_ngay)),
                $tu_ngay,
                $den_ngay,
                $dataType,
                json_encode($data, JSON_UNESCAPED_UNICODE),
                $employeeCount,
                $suspectCount
            ]);
            
        } catch (Exception $e) {
            error_log("Save report cache error: " . $e->getMessage());
        }
    }

    /**
     * ✅ POPULATE REDIS FROM DATABASE
     */
    private function populateRedisFromDB($cacheKey, $data) {
        if (!$this->redis) return;
        
        try {
            $this->redis->setex(
                $cacheKey, 
                self::REDIS_TTL, 
                json_encode($data, JSON_UNESCAPED_UNICODE)
            );
        } catch (Exception $e) {
            error_log("Report Redis populate error: " . $e->getMessage());
        }
    }

    /**
     * ✅ CHI TIẾT ĐƠN HÀNG NHÂN VIÊN
     */
    public function getEmployeeOrderDetails($dsr_code, $tu_ngay, $den_ngay) {
        $sql = "SELECT 
                    o.OrderNumber as ma_don,
                    o.OrderDate as ngay_dat,
                    o.CustCode as ma_kh,
                    COALESCE(d.TenKH, 'N/A') as ten_kh,
                    COALESCE(d.DiaChi, '') as dia_chi_kh,
                    COALESCE(d.Tinh, '') as tinh_kh,
                    o.TotalNetAmount as so_tien,
                    o.Qty as so_luong
                FROM orderdetail o
                LEFT JOIN dskh d ON o.CustCode = d.MaKH
                WHERE o.DSRCode = ?
                AND DATE(o.OrderDate) >= ?
                AND DATE(o.OrderDate) <= ?
                ORDER BY o.OrderDate DESC, o.OrderNumber DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$dsr_code, $tu_ngay, $den_ngay]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ✅ GENERATE CACHE KEY
     */
    private function generateCacheKey($type, $thang, $tu_ngay, $den_ngay) {
        return "nhanvien:{$type}:{$thang}:{$tu_ngay}:{$den_ngay}";
    }

    /**
     * ✅ XÓA CACHE (Redis + Database)
     */
    public function clearCache($pattern = 'nhanvien:*') {
        $deletedCount = 0;
        
        // 1️⃣ Xóa Redis
        if ($this->redis) {
            try {
                $keys = $this->redis->keys($pattern);
                if (!empty($keys)) {
                    $this->redis->del($keys);
                    $deletedCount = count($keys);
                }
            } catch (Exception $e) {
                error_log("Redis clear cache error: " . $e->getMessage());
            }
        }
        
        // 2️⃣ Xóa Database cache cũ (giữ lại 24 giờ gần nhất)
        try {
            $sql = "DELETE FROM summary_nhanvien_report_cache 
                    WHERE calculated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Database cache cleanup error: " . $e->getMessage());
        }
        
        return $deletedCount;
    }

    /**
     * ✅ XEM TỔNG QUAN CACHE
     */
    public function getCacheStatistics() {
        try {
            $sql = "SELECT 
                        data_type,
                        COUNT(*) as total_records,
                        SUM(employee_count) as total_employees,
                        SUM(suspect_count) as total_suspects,
                        MIN(calculated_at) as oldest_cache,
                        MAX(calculated_at) as newest_cache,
                        ROUND(SUM(LENGTH(data)) / 1024 / 1024, 2) as data_size_mb
                    FROM summary_nhanvien_report_cache
                    GROUP BY data_type
                    ORDER BY data_type";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get cache statistics error: " . $e->getMessage());
            return [];
        }
    }

    // ============================================
    // CÁC HÀM CŨ GIỮ NGUYÊN
    // ============================================

    public function getAvailableMonths() {
        $sql = "SELECT DISTINCT CONCAT(RptYear, '-', LPAD(RptMonth, 2, '0')) as thang
                FROM orderdetail
                WHERE RptYear IS NOT NULL AND RptMonth IS NOT NULL
                AND RptYear >= 2020
                ORDER BY RptYear DESC, RptMonth DESC
                LIMIT 24";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function getTotalByMonth($thang) {
        list($year, $month) = explode('-', $thang);
        $sql = "SELECT COALESCE(SUM(TotalNetAmount), 0) as total
                FROM orderdetail WHERE RptYear = ? AND RptMonth = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$year, $month]);
        return floatval($stmt->fetch()['total'] ?? 0);
    }

    public function getTotalByDateRange($tu_ngay, $den_ngay) {
        $sql = "SELECT COALESCE(SUM(TotalNetAmount), 0) as total
                FROM orderdetail WHERE DATE(OrderDate) >= ? AND DATE(OrderDate) <= ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$tu_ngay, $den_ngay]);
        return floatval($stmt->fetch()['total'] ?? 0);
    }
}
?>