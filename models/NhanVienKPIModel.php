<?php
/**
 * ✅ MODEL TỐI ƯU V3 - Query đơn giản hơn, tránh MySQL timeout
 */

require_once 'config/database.php';

class NhanVienKPIModel {
    private $conn;
    private $redis;
    
    private const REDIS_HOST = '127.0.0.1';
    private const REDIS_PORT = 6379;
    private const REDIS_TTL = 3600;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        // Tăng timeout cho MySQL
        $this->conn->setAttribute(PDO::ATTR_TIMEOUT, 300);
        $this->conn->exec("SET SESSION wait_timeout=300");
        $this->conn->exec("SET SESSION interactive_timeout=300");
        
        $this->connectRedis();
    }
    
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
     * ✅ LẤY NHÂN VIÊN - QUERY ĐƠN GIẢN HƠN
     */
    public function getAllEmployeesWithKPI($tu_ngay, $den_ngay, $product_filter = '', $threshold_n = 5) {
        $cacheKey = $this->generateCacheKey($tu_ngay, $den_ngay, $product_filter, $threshold_n);
        
        // Thử Redis
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
        
        // Thử Database cache
        $dbResults = $this->getFromSummaryTable($cacheKey);
        if (!empty($dbResults)) {
            $this->populateRedisFromDB($cacheKey, $dbResults);
            return $dbResults;
        }
        
        // ✅ QUERY ĐƠN GIẢN - Lấy danh sách nhân viên trước
        // Sử dụng dsnv để lấy nhiều thông tin hơn giống NhanVienReport
        $sql1 = "SELECT 
                    o.DSRCode,
                    o.DSRTypeProvince,
                    MAX(nv.ho_ten) as TenNVBH,
                    MAX(nv.bo_phan) as bo_phan,
                    MAX(nv.chuc_vu) as chuc_vu,
                    MAX(nv.base_tinh) as base_tinh,
                    MAX(nv.khu_vuc) as khu_vuc,
                    MAX(nv.kenh_ban_hang) as kenh_ban_hang,
                    MAX(nv.trang_thai) as trang_thai,
                    MAX(nv.ma_nv_ql) as ma_nv_ql,
                    MAX(nv.ten_nv_ql) as ten_nv_ql,
                    MAX(nv.ngay_vao_cty) as ngay_vao_cty
                FROM orderdetail o
                LEFT JOIN dsnv nv ON o.DSRCode = nv.ma_nv
                WHERE o.DSRCode IS NOT NULL 
                AND o.DSRCode != ''
                AND DATE(o.OrderDate) >= ?
                AND DATE(o.OrderDate) <= ?
                " . (!empty($product_filter) ? "AND o.ProductCode LIKE ?" : "") . "
                GROUP BY o.DSRCode, o.DSRTypeProvince";
        
        $params1 = [$tu_ngay, $den_ngay];
        if (!empty($product_filter)) {
            $params1[] = $product_filter . '%';
        }
        
        $stmt1 = $this->conn->prepare($sql1);
        $stmt1->execute($params1);
        $employees = $stmt1->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($employees)) {
            return [];
        }
        
        // ✅ Lấy thống kê cho từng nhân viên (loop đơn giản)
        $results = [];
        
        foreach ($employees as $emp) {
            $dsrCode = $emp['DSRCode'];
            
            // Query đơn giản cho mỗi nhân viên
            $sql2 = "SELECT 
                        DATE(OrderDate) as order_date,
                        COUNT(DISTINCT OrderNumber) as daily_orders,
                        COUNT(DISTINCT CustCode) as daily_customers,
                        SUM(TotalNetAmount) as daily_amount
                    FROM orderdetail
                    WHERE DSRCode = ?
                    AND DATE(OrderDate) >= ?
                    AND DATE(OrderDate) <= ?
                    " . (!empty($product_filter) ? "AND ProductCode LIKE ?" : "") . "
                    GROUP BY DATE(OrderDate)
                    ORDER BY order_date";
            
            $params2 = [$dsrCode, $tu_ngay, $den_ngay];
            if (!empty($product_filter)) {
                $params2[] = $product_filter . '%';
            }
            
            $stmt2 = $this->conn->prepare($sql2);
            $stmt2->execute($params2);
            $dailyData = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            
            // Tính toán
            $daily_dates = [];
            $daily_orders = [];
            $daily_customers = [];
            $daily_amounts = [];
            $total_orders = 0;
            $total_customers = 0;
            $total_amount = 0;
            $max_customers = 0;
            
            foreach ($dailyData as $day) {
                $daily_dates[] = $day['order_date'];
                $daily_orders[] = intval($day['daily_orders']);
                $daily_customers[] = intval($day['daily_customers']);
                $daily_amounts[] = floatval($day['daily_amount']);
                
                $total_orders += intval($day['daily_orders']);
                $total_customers += intval($day['daily_customers']);
                $total_amount += floatval($day['daily_amount']);
                $max_customers = max($max_customers, intval($day['daily_customers']));
            }
            
            $working_days = count($dailyData);
            
            $row = [
                'DSRCode' => $emp['DSRCode'],
                'DSRTypeProvince' => $emp['DSRTypeProvince'],
                'TenNVBH' => $emp['TenNVBH'],
                'ma_nv_ql' => $emp['ma_nv_ql'],
                'ten_nv_ql' => $emp['ten_nv_ql'],
                'total_orders' => $total_orders,
                'total_customers' => $total_customers,
                'total_amount' => $total_amount,
                'working_days' => $working_days,
                'max_day_customers' => $max_customers,
                'max_day_orders' => max($daily_orders ?: [0]),
                'max_day_amount' => max($daily_amounts ?: [0]),
                'daily_dates' => $daily_dates,
                'daily_orders' => $daily_orders,
                'daily_customers' => $daily_customers,
                'daily_amounts' => $daily_amounts,
                'avg_daily_orders' => $working_days > 0 ? round($total_orders / $working_days, 2) : 0,
                'avg_daily_amount' => $working_days > 0 ? round($total_amount / $working_days, 0) : 0,
                'avg_daily_customers' => $working_days > 0 ? round($total_customers / $working_days, 2) : 0,
            ];
            
            // Phân tích risk nâng cao
            $row['risk_analysis'] = $this->analyzeRiskByThreshold($daily_customers, $threshold_n, $daily_dates, $daily_amounts);
            $row['risk_score'] = $row['risk_analysis']['risk_score'];
            $row['risk_level'] = $row['risk_analysis']['risk_level'];
            $row['violation_days'] = $row['risk_analysis']['violation_days'];
            $row['violation_count'] = $row['risk_analysis']['violation_count'];
            
            // Thông tin chi tiết nhân viên
            $row['ten_nv'] = !empty($emp['TenNVBH']) ? $emp['TenNVBH'] : 'NV_' . $emp['DSRCode'];
            $row['bo_phan'] = $emp['bo_phan'] ?? 'N/A';
            $row['chuc_vu'] = $emp['chuc_vu'] ?? 'N/A';
            $row['base_tinh'] = $emp['base_tinh'] ?? ($emp['DSRTypeProvince'] ?? '');
            $row['khu_vuc'] = $emp['khu_vuc'] ?? '';
            $row['kenh_ban_hang'] = $emp['kenh_ban_hang'] ?? '';
            $row['trang_thai'] = $emp['trang_thai'] ?? '';
            $row['ma_nv_ql'] = $emp['ma_nv_ql'] ?? '';
            $row['ten_nv_ql'] = $emp['ten_nv_ql'] ?? '';
            $row['ngay_vao_cty'] = $emp['ngay_vao_cty'] ?? '';
            
            $results[] = $row;
        }
        
        // Lưu cache
        if (!empty($results)) {
            $this->saveKPICache($cacheKey, $results, $tu_ngay, $den_ngay, $product_filter, $threshold_n);
        }
        
        return $results;
    }

    private function getFromSummaryTable($cacheKey) {
        try {
            $sql = "SELECT data FROM summary_nhanvien_kpi_cache 
                    WHERE cache_key = ? LIMIT 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$cacheKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row && !empty($row['data'])) {
                return json_decode($row['data'], true);
            }
        } catch (Exception $e) {
            error_log("KPI database backup fetch error: " . $e->getMessage());
        }
        
        return null;
    }

    private function saveKPICache($cacheKey, $data, $tu_ngay, $den_ngay, $product_filter, $threshold_n) {
        try {
            if ($this->redis) {
                $this->redis->setex(
                    $cacheKey, 
                    self::REDIS_TTL, 
                    json_encode($data, JSON_UNESCAPED_UNICODE)
                );
            }
            
            $criticalCount = 0;
            $warningCount = 0;
            foreach ($data as $item) {
                if ($item['risk_level'] === 'critical') $criticalCount++;
                elseif ($item['risk_level'] === 'warning') $warningCount++;
            }
            
            $sql = "INSERT INTO summary_nhanvien_kpi_cache 
                    (cache_key, tu_ngay, den_ngay, product_filter, threshold_n, data, employee_count, critical_count, warning_count)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    data = VALUES(data),
                    employee_count = VALUES(employee_count),
                    critical_count = VALUES(critical_count),
                    warning_count = VALUES(warning_count),
                    calculated_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $cacheKey,
                $tu_ngay,
                $den_ngay,
                $product_filter ?: null,
                $threshold_n,
                json_encode($data, JSON_UNESCAPED_UNICODE),
                count($data),
                $criticalCount,
                $warningCount
            ]);
            
        } catch (Exception $e) {
            error_log("Save KPI cache error: " . $e->getMessage());
        }
    }

    private function populateRedisFromDB($cacheKey, $data) {
        if (!$this->redis) return;
        
        try {
            $this->redis->setex(
                $cacheKey, 
                self::REDIS_TTL, 
                json_encode($data, JSON_UNESCAPED_UNICODE)
            );
        } catch (Exception $e) {
            error_log("KPI Redis populate error: " . $e->getMessage());
        }
    }

    /**
     * ✅ PHÂN TÍCH RỦI RO NÂNG CAO (Advanced KPI Logic)
     * 1. Threshold Violation: Vi phạm ngưỡng tuyệt đối.
     * 2. Statistical Anomaly (Z-Score): Bất thường so với chính mình.
     * 3. Efficiency Decline: Doanh số TB/khách thấp bất thường vào ngày có nhiều khách (Filler orders).
     * 4. Consecutive Violations: Vi phạm liên tiếp.
     */
    private function analyzeRiskByThreshold($daily_customers, $threshold_n, $daily_dates = [], $daily_amounts = []) {
        $total_days = count($daily_customers);
        if ($total_days === 0) return $this->emptyRiskResult();

        $violation_days = [];
        $violation_count = 0;
        $max_absolute_customers = 0;
        
        // 1. TÍNH TOÁN CƠ BẢN VÀ baseline (Trung bình, Độ lệch chuẩn cho Z-Score)
        $avg_cust = array_sum($daily_customers) / $total_days;
        $sum_sq_diff = 0;
        foreach ($daily_customers as $c) $sum_sq_diff += pow($c - $avg_cust, 2);
        $std_dev = sqrt($sum_sq_diff / $total_days);
        $std_dev = max(1, $std_dev); // Tránh chia cho 0

        // Baseline AOV (Doanh số TB/khách) của nhân viên này
        $total_amount = array_sum($daily_amounts);
        $total_cust = array_sum($daily_customers);
        $baseline_aov = $total_cust > 0 ? ($total_amount / $total_cust) : 0;

        $risk_scores = [
            'threshold' => 0,
            'statistical' => 0,
            'efficiency' => 0,
            'consecutive' => 0
        ];

        foreach ($daily_customers as $idx => $count) {
            $is_threshold_violation = ($count > $threshold_n);
            $z_score = ($count - $avg_cust) / $std_dev;
            $is_statistical_anomaly = ($z_score > 2); // Trên 2 standard deviations là bất thường
            
            $day_amount = $daily_amounts[$idx] ?? 0;
            $day_aov = $count > 0 ? ($day_amount / $count) : 0;
            
            // Efficiency drop: Nếu số khách cao nhưng AOV thấp hơn 50% baseline
            $has_efficiency_drop = ($count >= $threshold_n && $day_aov < ($baseline_aov * 0.5));

            if ($is_threshold_violation || $is_statistical_anomaly || $has_efficiency_drop) {
                if ($is_threshold_violation) $violation_count++;
                $max_absolute_customers = max($max_absolute_customers, $count);

                $reasons = [];
                if ($is_threshold_violation) $reasons[] = "Vượt ngưỡng N=" . $threshold_n;
                if ($is_statistical_anomaly) $reasons[] = "Bất thường thống kê (Z=" . round($z_score, 1) . ")";
                if ($has_efficiency_drop) $reasons[] = "Hiệu suất thấp (Gom đơn)";

                $violation_days[] = [
                    'date' => $daily_dates[$idx] ?? "Ngày $idx",
                    'customers' => $count,
                    'threshold' => $threshold_n,
                    'z_score' => round($z_score, 1),
                    'day_aov' => $day_aov,
                    'baseline_aov' => $baseline_aov,
                    'reasons' => $reasons,
                    'is_critical' => ($is_threshold_violation && $is_statistical_anomaly)
                ];
            }
        }

        // 2. TÍNH ĐIỂM RISK (0-100)
        
        // A. Điểm vi phạm ngưỡng (Max 40đ)
        $violation_rate = ($violation_count / $total_days) * 100;
        $risk_scores['threshold'] = min(40, ($violation_rate / 100) * 40);
        
        // Bonus penalty cho mức độ vượt (N * X)
        $excess_ratio = $max_absolute_customers / max(1, $threshold_n);
        if ($excess_ratio >= 3) $risk_scores['threshold'] += 10;
        elseif ($excess_ratio >= 2) $risk_scores['threshold'] += 5;

        // B. Điểm bất thường thống kê (Max 30đ)
        $anomalies = array_filter($violation_days, fn($v) => $v['z_score'] > 2);
        if (count($anomalies) > 0) {
            $risk_scores['statistical'] = min(30, count($anomalies) * 10);
        }

        // C. Điểm hiệu suất/gom đơn (Max 20đ)
        $efficiency_drops = array_filter($violation_days, fn($v) => in_array("Hiệu suất thấp (Gom đơn)", $v['reasons']));
        if (count($efficiency_drops) > 0) {
            $risk_scores['efficiency'] = min(20, count($efficiency_drops) * 7);
        }

        // D. Điểm vi phạm liên tiếp (Max 10đ)
        $consecutive = $this->countConsecutiveViolations($daily_customers, $threshold_n);
        if ($consecutive >= 5) $risk_scores['consecutive'] = 10;
        elseif ($consecutive >= 3) $risk_scores['consecutive'] = 7;
        elseif ($consecutive >= 2) $risk_scores['consecutive'] = 3;

        $total_risk_score = min(100, array_sum($risk_scores));

        // Phân loại Level
        if ($total_risk_score >= 80) $risk_level = 'critical';
        elseif ($total_risk_score >= 50) $risk_level = 'warning';
        else $risk_level = 'normal';

        return [
            'risk_score' => round($total_risk_score, 0),
            'risk_level' => $risk_level,
            'risk_breakdown' => $risk_scores,
            'violation_count' => $violation_count,
            'total_days' => $total_days,
            'violation_rate' => round($violation_rate, 1),
            'max_violation' => max(0, $max_absolute_customers - $threshold_n),
            'consecutive_violations' => $consecutive,
            'violation_days' => $violation_days,
            'stats' => [
                'avg_cust' => round($avg_cust, 1),
                'std_dev' => round($std_dev, 1),
                'baseline_aov' => $baseline_aov
            ]
        ];
    }

    private function emptyRiskResult() {
        return [
            'risk_score' => 0,
            'risk_level' => 'normal',
            'violation_count' => 0,
            'violation_days' => []
        ];
    }

    private function countConsecutiveViolations($daily_customers, $threshold_n) {
        $max_consecutive = 0;
        $current_consecutive = 0;
        
        foreach ($daily_customers as $customers) {
            if ($customers > $threshold_n) {
                $current_consecutive++;
                $max_consecutive = max($max_consecutive, $current_consecutive);
            } else {
                $current_consecutive = 0;
            }
        }
        
        return $max_consecutive;
    }

    public function getEmployeeCustomerDetails($dsr_code, $tu_ngay, $den_ngay, $product_filter = '') {
        $sql = "SELECT 
                    o.CustCode,
                    d.TenKH as customer_name,
                    d.DiaChi as customer_address,
                    d.Tinh as customer_province,
                    COUNT(DISTINCT o.OrderNumber) as order_count,
                    SUM(o.TotalNetAmount) as total_amount,
                    GROUP_CONCAT(DISTINCT DATE(o.OrderDate) ORDER BY o.OrderDate) as order_dates,
                    GROUP_CONCAT(DISTINCT o.OrderNumber ORDER BY o.OrderDate) as order_numbers,
                    GROUP_CONCAT(o.TotalNetAmount ORDER BY o.OrderDate) as order_amounts
                FROM orderdetail o
                LEFT JOIN dskh d ON o.CustCode = d.MaKH
                WHERE o.DSRCode = ?
                AND DATE(o.OrderDate) >= ?
                AND DATE(o.OrderDate) <= ?
                " . (!empty($product_filter) ? "AND o.ProductCode LIKE ?" : "") . "
                GROUP BY o.CustCode, d.TenKH, d.DiaChi, d.Tinh
                ORDER BY total_amount DESC";
        
        $params = [$dsr_code, $tu_ngay, $den_ngay];
        if (!empty($product_filter)) {
            $params[] = $product_filter . '%';
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $dates = explode(',', $row['order_dates']);
            $numbers = explode(',', $row['order_numbers']);
            $amounts = explode(',', $row['order_amounts']);
            
            $row['orders'] = [];
            for ($i = 0; $i < count($dates); $i++) {
                $row['orders'][] = [
                    'date' => $dates[$i],
                    'order_number' => $numbers[$i],
                    'amount' => floatval($amounts[$i])
                ];
            }
            
            unset($row['order_dates'], $row['order_numbers'], $row['order_amounts']);
        }
        
        return $results;
    }

    public function getSystemMetrics($tu_ngay, $den_ngay, $product_filter = '') {
        $cacheKey = "nhanvien:kpi:metrics:{$tu_ngay}:{$den_ngay}:" . md5($product_filter);
        
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
                    COUNT(DISTINCT o.DSRCode) as emp_count,
                    COUNT(DISTINCT o.OrderNumber) as total_orders,
                    COUNT(DISTINCT o.CustCode) as total_customers,
                    COALESCE(SUM(o.TotalNetAmount), 0) as total_amount
                FROM orderdetail o
                WHERE o.DSRCode IS NOT NULL 
                AND o.DSRCode != ''
                AND DATE(o.OrderDate) >= ?
                AND DATE(o.OrderDate) <= ?
                " . (!empty($product_filter) ? "AND o.ProductCode LIKE ?" : "");
        
        $params = [$tu_ngay, $den_ngay];
        if (!empty($product_filter)) {
            $params[] = $product_filter . '%';
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($this->redis && !empty($result)) {
            try {
                $this->redis->setex(
                    $cacheKey, 
                    self::REDIS_TTL, 
                    json_encode($result, JSON_UNESCAPED_UNICODE)
                );
            } catch (Exception $e) {
                error_log("Redis set error: " . $e->getMessage());
            }
        }
        
        return $result;
    }

    private function generateCacheKey($tu_ngay, $den_ngay, $product_filter, $threshold_n) {
        $productHash = !empty($product_filter) ? md5($product_filter) : 'all';
        return "nhanvien:kpi:N{$threshold_n}:{$tu_ngay}:{$den_ngay}:{$productHash}";
    }

    public function clearCache($pattern = 'nhanvien:kpi:*') {
        if (!$this->redis) return false;
        
        try {
            $keys = $this->redis->keys($pattern);
            if (!empty($keys)) {
                $this->redis->del($keys);
                return count($keys);
            }
            return 0;
        } catch (Exception $e) {
            error_log("Redis clear cache error: " . $e->getMessage());
            return false;
        }
    }

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

    public function getAvailableProducts() {
        $sql = "SELECT DISTINCT SUBSTRING(ProductCode, 1, 2) as product_prefix
                FROM orderdetail 
                WHERE ProductCode IS NOT NULL AND ProductCode != ''
                ORDER BY product_prefix
                LIMIT 50";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}
?>