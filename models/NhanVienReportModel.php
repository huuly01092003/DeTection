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
        
        // ✅ Enable emulation for multiple uses of the same named parameter
        if ($this->conn) {
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        }
        
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
        // 1️⃣ Tạo cache key (v2 to force refresh)
        $cacheKey = $this->generateCacheKey('employees_v2', $thang, $tu_ngay, $den_ngay);
        
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
                    
                    MAX(nv_info.ho_ten) as ten_nhan_vien,
                    MAX(nv_info.bo_phan) as bo_phan,
                    MAX(nv_info.chuc_vu) as chuc_vu,
                    MAX(nv_info.base_tinh) as base_tinh,
                    MAX(nv_info.khu_vuc) as khu_vuc,
                    MAX(nv_info.kenh_ban_hang) as kenh_ban_hang,
                    MAX(nv_info.trang_thai) as trang_thai,
                    MAX(nv_info.ma_nv_ql) as ma_nv_ql,
                    MAX(nv_info.ten_nv_ql) as ten_nv_ql,
                    MAX(nv_info.ngay_vao_cty) as ngay_vao_cty,
                    
                    SUM(CASE WHEN DATE(o.OrderDate) >= :tu_ngay AND DATE(o.OrderDate) <= :den_ngay 
                        THEN o.TotalNetAmount ELSE 0 END) as ds_tien_do,
                    COUNT(DISTINCT CASE WHEN DATE(o.OrderDate) >= :tu_ngay AND DATE(o.OrderDate) <= :den_ngay 
                        THEN o.OrderNumber END) as so_don_khoang,
                    COUNT(DISTINCT CASE WHEN DATE(o.OrderDate) >= :tu_ngay AND DATE(o.OrderDate) <= :den_ngay 
                        THEN o.CustCode END) as so_kh_khoang,
                    COUNT(DISTINCT CASE WHEN DATE(o.OrderDate) >= :tu_ngay AND DATE(o.OrderDate) <= :den_ngay 
                        THEN DATE(o.OrderDate) END) as so_ngay_co_doanh_so_khoang,
                    COALESCE(MAX(ds_khoang.max_daily), 0) as ds_ngay_cao_nhat_nv_khoang,
                    
                    SUM(CASE WHEN o.RptYear = :year AND o.RptMonth = :month 
                        THEN o.TotalNetAmount ELSE 0 END) as ds_tong_thang_nv,
                    COUNT(DISTINCT CASE WHEN o.RptYear = :year AND o.RptMonth = :month 
                        THEN o.OrderNumber END) as so_don_thang,
                    COUNT(DISTINCT CASE WHEN o.RptYear = :year AND o.RptMonth = :month 
                        THEN o.CustCode END) as so_kh_thang,
                    COUNT(DISTINCT CASE WHEN o.RptYear = :year AND o.RptMonth = :month 
                        THEN DATE(o.OrderDate) END) as so_ngay_co_doanh_so_thang,
                    COALESCE(MAX(ds_thang.max_daily), 0) as ds_ngay_cao_nhat_nv_thang,

                    -- MỚI: Doanh số khách hàng lớn nhất trong khoảng
                    COALESCE(MAX(ds_kh_khoang.max_cust_amount), 0) as ds_kh_lon_nhat_khoang,
                    
                    -- MỚI: Doanh số từ khách hàng GKHL (Gắn kết Hoa Linh)
                    SUM(CASE WHEN DATE(o.OrderDate) >= :tu_ngay AND DATE(o.OrderDate) <= :den_ngay AND g.MaKHDMS IS NOT NULL 
                        THEN o.TotalNetAmount ELSE 0 END) as ds_gkhl_khoang,
                        
                    -- MỚI: Số khách hàng GKHL phát sinh đơn trong khoảng
                    COUNT(DISTINCT CASE WHEN DATE(o.OrderDate) >= :tu_ngay AND DATE(o.OrderDate) <= :den_ngay AND g.MaKHDMS IS NOT NULL 
                        THEN o.CustCode END) as so_kh_gkhl_khoang
                    
                FROM orderdetail o
                
                LEFT JOIN dsnv nv_info ON o.DSRCode = nv_info.ma_nv
                LEFT JOIN gkhl g ON o.CustCode = g.MaKHDMS
                
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
                        AND DATE(OrderDate) >= :tu_ngay
                        AND DATE(OrderDate) <= :den_ngay
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
                        AND RptYear = :year
                        AND RptMonth = :month
                        GROUP BY DSRCode, DATE(OrderDate)
                    ) daily_thang
                    GROUP BY DSRCode
                ) ds_thang ON o.DSRCode = ds_thang.DSRCode

                -- Subquery lấy doanh số khách hàng lớn nhất
                LEFT JOIN (
                    SELECT 
                        DSRCode,
                        MAX(cust_amount) as max_cust_amount
                    FROM (
                        SELECT 
                            DSRCode,
                            CustCode,
                            SUM(TotalNetAmount) as cust_amount
                        FROM orderdetail
                        WHERE DATE(OrderDate) >= :tu_ngay AND DATE(OrderDate) <= :den_ngay
                        GROUP BY DSRCode, CustCode
                    ) cust_khoang
                    GROUP BY DSRCode
                ) ds_kh_khoang ON o.DSRCode = ds_kh_khoang.DSRCode
                
                WHERE o.DSRCode IS NOT NULL 
                AND o.DSRCode != ''
                AND (
                    (DATE(o.OrderDate) >= :tu_ngay AND DATE(o.OrderDate) <= :den_ngay)
                    OR (o.RptYear = :year AND o.RptMonth = :month)
                )
                GROUP BY o.DSRCode, o.DSRTypeProvince
                HAVING ds_tien_do > 0 OR ds_tong_thang_nv > 0
                ORDER BY o.DSRCode";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'tu_ngay' => $tu_ngay,
            'den_ngay' => $den_ngay,
            'year' => $year,
            'month' => $month
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
                    DATEDIFF(MAX(DATE(o.OrderDate)), MIN(DATE(o.OrderDate))) + 1 as so_ngay,
                    
                    COUNT(DISTINCT o.OrderNumber) as order_count,
                    COUNT(DISTINCT o.CustCode) as cust_count,

                    -- Doanh số TB ngày / NV
                    COALESCE(SUM(o.TotalNetAmount), 0) / 
                    NULLIF(COUNT(DISTINCT o.DSRCode) * (DATEDIFF(MAX(DATE(o.OrderDate)), MIN(DATE(o.OrderDate))) + 1), 0) as ds_tb_chung_thang,
                    
                    -- AOV TB
                    COALESCE(SUM(o.TotalNetAmount), 0) / NULLIF(COUNT(DISTINCT o.OrderNumber), 0) as aov_thang,

                    -- Số đơn TB ngày / NV
                    COUNT(DISTINCT o.OrderNumber) / 
                    NULLIF(COUNT(DISTINCT o.DSRCode) * (DATEDIFF(MAX(DATE(o.OrderDate)), MIN(DATE(o.OrderDate))) + 1), 0) as orders_per_day_thang,

                    -- Số khách TB ngày / NV
                    COUNT(DISTINCT o.CustCode) / 
                    NULLIF(COUNT(DISTINCT o.DSRCode) * (DATEDIFF(MAX(DATE(o.OrderDate)), MIN(DATE(o.OrderDate))) + 1), 0) as cust_per_day_thang,
                    
                    -- Tỷ lệ khách hàng GKHL của hệ thống
                    COUNT(DISTINCT CASE WHEN g.MaKHDMS IS NOT NULL THEN o.CustCode END) / 
                    NULLIF(COUNT(DISTINCT o.CustCode), 0) as gkhl_rate_thang,

                    AVG(emp_max.max_daily) as ds_ngay_cao_nhat_tb_thang
                    
                FROM orderdetail o
                LEFT JOIN gkhl g ON o.CustCode = g.MaKHDMS
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
                        WHERE RptYear = :year AND RptMonth = :month
                        AND DSRCode IS NOT NULL AND DSRCode != ''
                        GROUP BY DSRCode, DATE(OrderDate)
                    ) daily
                    GROUP BY DSRCode
                ) emp_max ON o.DSRCode = emp_max.DSRCode
                WHERE o.RptYear = :year AND o.RptMonth = :month
                AND o.DSRCode IS NOT NULL AND o.DSRCode != ''";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['year' => $year, 'month' => $month]);
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
                    DATEDIFF(:den_ngay, :tu_ngay) + 1 as so_ngay,
                    
                    COUNT(DISTINCT o.OrderNumber) as order_count,
                    COUNT(DISTINCT o.CustCode) as cust_count,

                    -- Doanh số TB ngày / NV
                    COALESCE(SUM(o.TotalNetAmount), 0) / 
                    NULLIF(COUNT(DISTINCT o.DSRCode) * (DATEDIFF(:den_ngay, :tu_ngay) + 1), 0) as ds_tb_chung_khoang,
                    
                    -- AOV TB
                    COALESCE(SUM(o.TotalNetAmount), 0) / NULLIF(COUNT(DISTINCT o.OrderNumber), 0) as aov_khoang,

                    -- Số đơn TB ngày / NV
                    COUNT(DISTINCT o.OrderNumber) / 
                    NULLIF(COUNT(DISTINCT o.DSRCode) * (DATEDIFF(:den_ngay, :tu_ngay) + 1), 0) as orders_per_day_khoang,

                    -- Số khách TB ngày / NV
                    COUNT(DISTINCT o.CustCode) / 
                    NULLIF(COUNT(DISTINCT o.DSRCode) * (DATEDIFF(:den_ngay, :tu_ngay) + 1), 0) as cust_per_day_khoang,

                    -- Tỷ lệ khách hàng GKHL của hệ thống
                    COUNT(DISTINCT CASE WHEN g.MaKHDMS IS NOT NULL THEN o.CustCode END) / 
                    NULLIF(COUNT(DISTINCT o.CustCode), 0) as gkhl_rate_khoang,
                    
                    AVG(emp_max.max_daily) as ds_ngay_cao_nhat_tb_khoang
                    
                FROM orderdetail o
                LEFT JOIN gkhl g ON o.CustCode = g.MaKHDMS
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
                        WHERE DATE(OrderDate) >= :tu_ngay AND DATE(OrderDate) <= :den_ngay
                        AND DSRCode IS NOT NULL AND DSRCode != ''
                        GROUP BY DSRCode, DATE(OrderDate)
                    ) daily
                    GROUP BY DSRCode
                ) emp_max ON o.DSRCode = emp_max.DSRCode
                WHERE DATE(o.OrderDate) >= :tu_ngay AND DATE(o.OrderDate) <= :den_ngay
                AND o.DSRCode IS NOT NULL AND o.DSRCode != ''";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'tu_ngay' => $tu_ngay,
            'den_ngay' => $den_ngay
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
                foreach ($data as $emp) {
                    $ds_tien_do = $emp['ds_tien_do'] ?? 0;
                    $ds_tong_thang = $emp['ds_tong_thang_nv'] ?? 1;
                    $ty_le_tien_do = ($ds_tong_thang > 0) ? ($ds_tien_do / $ds_tong_thang) : 0;
                    
                    // Chỉ đếm là nghi vấn nếu tiến độ > 50% tháng (để lưu stats tổng quát)
                    if ($ty_le_tien_do > 0.5) {
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
     * ✅ CHI TIẾT ĐƠN HÀNG NHÂN VIÊN - BẢN CẢI TIẾN
     * Bao gồm: thông tin KH từ dskh, trạng thái GKHL, ProductCode, ProductSaleType
     */
    public function getEmployeeOrderDetails($dsr_code, $tu_ngay, $den_ngay) {
        $sql = "SELECT 
                    o.OrderNumber as ma_don,
                    o.OrderDate as ngay_dat,
                    o.CustCode as ma_kh,
                    o.ProductCode as ma_san_pham,
                    o.ProductSaleType as loai_ban,
                    o.TotalNetAmount as so_tien,
                    o.Qty as so_luong,
                    
                    -- Thông tin khách hàng từ dskh
                    COALESCE(d.TenKH, 'N/A') as ten_kh,
                    COALESCE(d.MaSoThue, '') as ma_so_thue,
                    COALESCE(d.PhanLoaiNhomKH, '') as phan_loai_nhom_kh,
                    COALESCE(d.LoaiKH, '') as loai_kh,
                    COALESCE(d.DiaChi, '') as dia_chi_kh,
                    COALESCE(d.Tinh, '') as tinh_kh,
                    
                    -- Trạng thái gắn kết Hoa Linh từ gkhl
                    CASE WHEN g.MaKHDMS IS NOT NULL THEN 'Y' ELSE 'N' END as gkhl_status,
                    COALESCE(g.DangKyChuongTrinh, '') as dang_ky_chuong_trinh,
                    COALESCE(g.DangKyMucDoanhSo, '') as dang_ky_muc_doanh_so,
                    COALESCE(g.DangKyTrungBay, '') as dang_ky_trung_bay
                    
                FROM orderdetail o
                LEFT JOIN dskh d ON o.CustCode = d.MaKH
                LEFT JOIN gkhl g ON o.CustCode = g.MaKHDMS
                WHERE o.DSRCode = ?
                AND DATE(o.OrderDate) >= ?
                AND DATE(o.OrderDate) <= ?
                ORDER BY o.OrderDate DESC, o.OrderNumber DESC, o.ProductCode";
        
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

    /**
     * ✅ LẤY KHOẢNG NGÀY THỰC TẾ CHO MỖI KỲ BÁO CÁO
     * Thay vì dùng ngày theo lịch (1-31), lấy MIN/MAX OrderDate từ database
     * @return array ['2025-12' => ['min_date' => '2025-11-28', 'max_date' => '2025-12-28'], ...]
     */
    public function getActualDateRanges() {
        $sql = "SELECT 
                    CONCAT(RptYear, '-', LPAD(RptMonth, 2, '0')) as thang,
                    MIN(DATE(OrderDate)) as min_date,
                    MAX(DATE(OrderDate)) as max_date
                FROM orderdetail
                WHERE RptYear IS NOT NULL AND RptMonth IS NOT NULL
                AND RptYear >= 2020
                GROUP BY RptYear, RptMonth
                ORDER BY RptYear DESC, RptMonth DESC
                LIMIT 24";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $results = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[$row['thang']] = [
                'min_date' => $row['min_date'],
                'max_date' => $row['max_date']
            ];
        }
        return $results;
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

    /**
     * ✅ LẤY DOANH SỐ THEO NGÀY CHO BIỂU ĐỒ PHÂN TÍCH
     * Trả về dữ liệu: [{ dsr_code, ten_nv, ngay, doanh_so, so_don }]
     */
    public function getDailySalesForChart($tu_ngay, $den_ngay, $thang = null) {
        // Thử lấy từ Redis cache trước
        $cacheKey = "nhanvien:daily_sales:{$tu_ngay}:{$den_ngay}";
        
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

        $sql = "SELECT 
                    o.DSRCode as dsr_code,
                    COALESCE(nv.ho_ten, CONCAT('NV_', o.DSRCode)) as ten_nv,
                    DATE(o.OrderDate) as ngay,
                    SUM(o.TotalNetAmount) as doanh_so,
                    COUNT(DISTINCT o.OrderNumber) as so_don,
                    COUNT(DISTINCT o.CustCode) as so_kh
                FROM orderdetail o
                LEFT JOIN dsnv nv ON o.DSRCode = nv.ma_nv
                WHERE DATE(o.OrderDate) >= :tu_ngay 
                AND DATE(o.OrderDate) <= :den_ngay
                AND o.DSRCode IS NOT NULL 
                AND o.DSRCode != ''
                GROUP BY o.DSRCode, nv.ho_ten, DATE(o.OrderDate)
                ORDER BY o.DSRCode, DATE(o.OrderDate)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'tu_ngay' => $tu_ngay,
            'den_ngay' => $den_ngay
        ]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Lưu vào Redis cache (TTL 30 phút)
        if ($this->redis && !empty($results)) {
            try {
                $this->redis->setex($cacheKey, 1800, json_encode($results, JSON_UNESCAPED_UNICODE));
            } catch (Exception $e) {
                error_log("Redis set error: " . $e->getMessage());
            }
        }
        
        return $results;
    }

    /**
     * ✅ LẤY DANH SÁCH TOP NHÂN VIÊN NGHI VẤN (dựa trên tỷ lệ tiến độ cao)
     * Dùng cho biểu đồ tập trung vào NV bất thường
     */
    public function getTopSuspectEmployees($tu_ngay, $den_ngay, $thang, $limit = 20) {
        list($year, $month) = explode('-', $thang);
        
        $sql = "SELECT 
                    o.DSRCode as dsr_code,
                    COALESCE(nv.ho_ten, CONCAT('NV_', o.DSRCode)) as ten_nv,
                    
                    SUM(CASE WHEN DATE(o.OrderDate) >= :tu_ngay AND DATE(o.OrderDate) <= :den_ngay 
                        THEN o.TotalNetAmount ELSE 0 END) as ds_khoang,
                    
                    SUM(CASE WHEN o.RptYear = :year AND o.RptMonth = :month 
                        THEN o.TotalNetAmount ELSE 0 END) as ds_thang,
                        
                    COUNT(DISTINCT CASE WHEN DATE(o.OrderDate) >= :tu_ngay AND DATE(o.OrderDate) <= :den_ngay 
                        THEN DATE(o.OrderDate) END) as so_ngay_hoat_dong
                        
                FROM orderdetail o
                LEFT JOIN dsnv nv ON o.DSRCode = nv.ma_nv
                WHERE o.DSRCode IS NOT NULL AND o.DSRCode != ''
                AND (
                    (DATE(o.OrderDate) >= :tu_ngay AND DATE(o.OrderDate) <= :den_ngay)
                    OR (o.RptYear = :year AND o.RptMonth = :month)
                )
                GROUP BY o.DSRCode, nv.ho_ten
                HAVING ds_khoang > 0
                ORDER BY (ds_khoang / NULLIF(ds_thang, 0)) DESC, ds_khoang DESC
                LIMIT :limit";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tu_ngay', $tu_ngay, PDO::PARAM_STR);
        $stmt->bindValue(':den_ngay', $den_ngay, PDO::PARAM_STR);
        $stmt->bindValue(':year', $year, PDO::PARAM_STR);
        $stmt->bindValue(':month', $month, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>