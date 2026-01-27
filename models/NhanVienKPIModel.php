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
        $sql1 = "SELECT DISTINCT 
                    o.DSRCode,
                    o.DSRTypeProvince,
                    MAX(nv.TenNVBH) as TenNVBH,
                    MAX(nv.MaGSBH) as MaGSBH
                FROM orderdetail o
                LEFT JOIN dskh nv ON o.DSRCode = nv.MaNVBH
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
                'MaGSBH' => $emp['MaGSBH'],
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
            
            // Phân tích risk
            $row['risk_analysis'] = $this->analyzeRiskByThreshold($daily_customers, $threshold_n, $daily_dates);
            $row['risk_score'] = $row['risk_analysis']['risk_score'];
            $row['risk_level'] = $row['risk_analysis']['risk_level'];
            $row['violation_days'] = $row['risk_analysis']['violation_days'];
            $row['violation_count'] = $row['risk_analysis']['violation_count'];
            $row['ten_nv'] = !empty($row['TenNVBH']) ? $row['TenNVBH'] : 'NV_' . $row['DSRCode'];
            
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

    private function analyzeRiskByThreshold($daily_customers, $threshold_n, $daily_dates = []) {
        $violation_days = [];
        $violation_count = 0;
        $max_violation = 0;
        
        foreach ($daily_customers as $idx => $customers_per_day) {
            if ($customers_per_day > $threshold_n) {
                $violation_count++;
                $violation_amount = $customers_per_day - $threshold_n;
                $max_violation = max($max_violation, $violation_amount);
                
                $violation_days[] = [
                    'date' => $daily_dates[$idx] ?? "Ngày $idx",
                    'customers' => $customers_per_day,
                    'threshold' => $threshold_n,
                    'violation' => $violation_amount,
                    'ratio' => round(($customers_per_day / $threshold_n) * 100, 1)
                ];
            }
        }
        
        $total_days = count($daily_customers);
        $violation_rate = $total_days > 0 ? ($violation_count / $total_days) * 100 : 0;
        
        $risk_score = 0;
        
        if ($violation_rate >= 80) {
            $risk_score += 40;
        } elseif ($violation_rate >= 60) {
            $risk_score += 30;
        } elseif ($violation_rate >= 40) {
            $risk_score += 20;
        } elseif ($violation_rate >= 20) {
            $risk_score += 10;
        }
        
        if ($max_violation >= $threshold_n * 3) {
            $risk_score += 40;
        } elseif ($max_violation >= $threshold_n * 2) {
            $risk_score += 30;
        } elseif ($max_violation >= $threshold_n * 1.5) {
            $risk_score += 20;
        } elseif ($max_violation >= $threshold_n) {
            $risk_score += 10;
        }
        
        $consecutive = $this->countConsecutiveViolations($daily_customers, $threshold_n);
        if ($consecutive >= 3) {
            $risk_score += 20;
        } elseif ($consecutive >= 1) {
            $risk_score += 10;
        }
        
        if ($risk_score >= 75) {
            $risk_level = 'critical';
        } elseif ($risk_score >= 50) {
            $risk_level = 'warning';
        } else {
            $risk_level = 'normal';
        }
        
        return [
            'risk_score' => min(100, $risk_score),
            'risk_level' => $risk_level,
            'violation_count' => $violation_count,
            'total_days' => $total_days,
            'violation_rate' => round($violation_rate, 1),
            'max_violation' => $max_violation,
            'consecutive_violations' => $consecutive,
            'violation_days' => $violation_days
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
                ORDER BY RptYear DESC, RptMonth DESC LIMIT 24";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
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