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
        
        // Tăng timeout cho session này để tránh "server gone away" khi xử lý dữ liệu lớn
        try {
            $this->conn->exec("SET SESSION wait_timeout = 600");
            $this->conn->exec("SET SESSION interactive_timeout = 600");
            $this->conn->exec("SET SESSION max_allowed_packet = 104857600"); // 100MB
        } catch (Exception $e) {}

        // TRÍCH XUẤT NĂM/THÁNG để tối ưu index hints
        $start_year = date('Y', strtotime($tu_ngay));
        $start_month = date('n', strtotime($tu_ngay));
        $end_year = date('Y', strtotime($den_ngay));
        $end_month = date('n', strtotime($den_ngay));
        
        $rpt_where = "";
        $rpt_params = [];
        if ($start_year == $end_year && $start_month == $end_month) {
            $rpt_where = " AND o.RptYear = ? AND o.RptMonth = ? ";
            $rpt_params = [$start_year, $start_month];
        }

        // ✅ 1. Lấy danh sách DSRCode từ orderdetail (không join dsnv ở đây để tránh quét bảng lớn)
        $sql1 = "SELECT 
                    o.DSRCode,
                    o.DSRTypeProvince
                FROM orderdetail o
                WHERE o.OrderDate >= ? AND o.OrderDate <= ?
                " . $rpt_where . "
                " . (!empty($product_filter) ? "AND o.ProductCode LIKE ?" : "") . "
                GROUP BY o.DSRCode, o.DSRTypeProvince";
        
        $params1 = array_merge([$tu_ngay, $den_ngay], $rpt_params);
        if (!empty($product_filter)) {
            $params1[] = $product_filter . '%';
        }
        
        $stmt1 = $this->conn->prepare($sql1);
        $stmt1->execute($params1);
        $emp_base = $stmt1->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($emp_base)) return [];

        // ✅ 2. Lấy thông tin NV từ dsnv (truy vấn riêng biệt, cực nhanh)
        $dsrCodes = array_unique(array_filter(array_column($emp_base, 'DSRCode')));
        $nv_data = [];
        if (!empty($dsrCodes)) {
            $in_clause = implode(',', array_fill(0, count($dsrCodes), '?'));
            $sqlNV = "SELECT ma_nv, ho_ten, bo_phan, chuc_vu, base_tinh, khu_vuc, kenh_ban_hang, trang_thai, ma_nv_ql, ten_nv_ql, ngay_vao_cty FROM dsnv WHERE ma_nv IN ($in_clause)";
            $stmtNV = $this->conn->prepare($sqlNV);
            $stmtNV->execute(array_values($dsrCodes));
            while ($row = $stmtNV->fetch(PDO::FETCH_ASSOC)) {
                $nv_data[$row['ma_nv']] = $row;
            }
        }
        
        // ✅ Lấy thống kê cho từng nhân viên (loop đơn giản)
        $results = [];
        
        // ✅ 3. Lấy TOÀN BỘ thống kê hàng ngày cho TOÀN BỘ nhân viên (sử dụng index hints)
        $sql2 = "SELECT 
                    o.DSRCode,
                    o.OrderDate as order_date,
                    COUNT(DISTINCT o.OrderNumber) as daily_orders,
                    COUNT(DISTINCT o.CustCode) as daily_customers,
                    SUM(o.TotalNetAmount) as daily_amount
                FROM orderdetail o
                WHERE o.OrderDate >= ? AND o.OrderDate <= ?
                " . $rpt_where . "
                " . (!empty($product_filter) ? "AND o.ProductCode LIKE ?" : "") . "
                GROUP BY o.DSRCode, o.OrderDate
                ORDER BY o.DSRCode, o.OrderDate";
        
        $params2 = array_merge([$tu_ngay, $den_ngay], $rpt_params);
        if (!empty($product_filter)) {
            $params2[] = $product_filter . '%';
        }
        
        $stmt2 = $this->conn->prepare($sql2);
        $stmt2->execute($params2);
        $allDailyData = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // Gom nhóm dữ liệu theo nhân viên trong memory
        $groupedDailyData = [];
        foreach ($allDailyData as $dayRow) {
            $groupedDailyData[$dayRow['DSRCode']][] = $dayRow;
        }
        
        // ✅ 4. Xử lý logic tính toán trong memory
        $results = [];
        foreach ($emp_base as $emp) {
            $dsrCode = $emp['DSRCode'];
            $nvInfo = $nv_data[$dsrCode] ?? [];
            $dailyData = $groupedDailyData[$dsrCode] ?? [];
            
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
                'DSRCode' => $dsrCode,
                'DSRTypeProvince' => $emp['DSRTypeProvince'],
                'TenNVBH' => $nvInfo['ho_ten'] ?? 'NV_' . $dsrCode,
                'ma_nv_ql' => $nvInfo['ma_nv_ql'] ?? '-',
                'ten_nv_ql' => $nvInfo['ten_nv_ql'] ?? '-',
                'bo_phan' => $nvInfo['bo_phan'] ?? '-',
                'chuc_vu' => $nvInfo['chuc_vu'] ?? '-',
                'base_tinh' => $nvInfo['base_tinh'] ?? ($emp['DSRTypeProvince'] ?? '-'),
                'khu_vuc' => $nvInfo['khu_vuc'] ?? '-',
                'kenh_ban_hang' => $nvInfo['kenh_ban_hang'] ?? '-',
                'trang_thai' => $nvInfo['trang_thai'] ?? '-',
                'ngay_vao_cty' => $nvInfo['ngay_vao_cty'] ?? '',
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
            $row['risk_analysis'] = $this->analyzeRiskByThreshold($daily_customers, $threshold_n, $daily_dates, $daily_amounts, $daily_orders);
            $row['risk_score'] = $row['risk_analysis']['risk_score'];
            $row['risk_level'] = $row['risk_analysis']['risk_level'];
            $row['violation_days'] = $row['risk_analysis']['violation_days'];
            $row['violation_count'] = $row['risk_analysis']['violation_count'];
            $row['ten_nv'] = $row['TenNVBH']; // Alias cho view
            
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
    private function analyzeRiskByThreshold($daily_customers, $threshold_n, $daily_dates = [], $daily_amounts = [], $daily_orders = []) {
        $total_days = count($daily_customers);
        if ($total_days < 3) return $this->emptyRiskResult();

        // 1. TÍNH TOÁN THỐNG KÊ ROBUST (Median, MAD, Percentile) - Dùng bản sao để không hỏng index
        $sorted_cust = $daily_customers;
        sort($sorted_cust);
        $median = $this->calculateMedian($sorted_cust);
        $mad = $this->calculateMAD($sorted_cust, $median);
        $p80 = $this->getPercentile($sorted_cust, 80);
        
        $adaptive_n = max($threshold_n, ceil($median * 1.5), ceil($p80));

        // Baseline AOV trung vị (Dùng bản sao để tính)
        $temp_aovs = [];
        foreach ($daily_customers as $idx => $count) {
            if ($count > 0) $temp_aovs[] = ($daily_amounts[$idx] ?? 0) / $count;
        }
        $baseline_aov = $this->calculateMedian($temp_aovs);

        $violation_days = [];
        $suspicious_indices = []; // Track any day with suspicious behavior for streak
        $violation_count = 0;
        $max_excess_ratio = 0;

        foreach ($daily_customers as $idx => $count) {
            $day_amount = $daily_amounts[$idx] ?? 0;
            $day_aov = $count > 0 ? ($day_amount / $count) : 0;
            
            // 1. Phân loại hành vi
            $is_threshold_violation = ($count > $adaptive_n);
            $is_splitting = ($count > $median * 1.3 && $day_aov < $baseline_aov * 0.4);
            $is_consolidation = ($day_aov > $baseline_aov * 2.5 && $count < $median * 0.7 && $count > 0);
            
            // 2. Phát hiện xen kẽ (So với ngày hôm trước) - Biến động cực lớn
            $is_alternating = false;
            if ($idx > 0) {
                $prev_count = $daily_customers[$idx-1];
                if (abs($count - $prev_count) > ($median * 1.2)) {
                    $is_alternating = true;
                }
            }

            if ($is_threshold_violation || $is_splitting || $is_consolidation || $is_alternating) {
                $reasons = [];
                if ($count > $adaptive_n * 2) $reasons[] = "Vượt ngưỡng đột xuất (Gấp 2)";
                elseif ($is_threshold_violation) $reasons[] = "Vượt ngưỡng thích ứng (N=" . $adaptive_n . ")";
                
                if ($is_splitting) $reasons[] = "Nghi vấn chẻ đơn (AOV thấp)";
                if ($is_consolidation) $reasons[] = "Nghi vấn gộp đơn (AOV cao)";
                if ($is_alternating) $reasons[] = "Dấu hiệu xen kẽ (Biến động)";

                if ($is_threshold_violation) $violation_count++;
                $suspicious_indices[] = $idx;
                
                $max_excess_ratio = max($max_excess_ratio, $count / max(1, $adaptive_n));

                $violation_days[] = [
                    'date' => $daily_dates[$idx] ?? "Ngày $idx",
                    'customers' => $count,
                    'orders' => $daily_orders[$idx] ?? 0,
                    'threshold' => $adaptive_n,
                    'z_score' => 0, 
                    'day_aov' => $day_aov,
                    'baseline_aov' => $baseline_aov,
                    'total_amount' => $day_amount,
                    'reasons' => $reasons,
                    'is_critical' => $is_threshold_violation && ($count > $adaptive_n * 1.5)
                ];
            }
        }

        // 2. TÍNH ĐIỂM RISK (0-100)
        $risk_scores = ['threshold' => 0, 'behavioral' => 0, 'consecutive' => 0];

        // A. Điểm vượt ngưỡng (Max 50đ)
        if ($violation_count > 0) {
            if ($max_excess_ratio >= 3) $risk_scores['threshold'] = 50;
            elseif ($max_excess_ratio >= 2) $risk_scores['threshold'] = 40;
            elseif ($max_excess_ratio >= 1.5) $risk_scores['threshold'] = 30;
            else $risk_scores['threshold'] = 20;
        }

        // B. Điểm hành vi thao túng (Max 40đ)
        $behavioral_days = array_filter($violation_days, fn($v) => array_intersect(["Nghi vấn chẻ đơn (AOV thấp)", "Nghi vấn gộp đơn (AOV cao)", "Dấu hiệu xen kẽ (Biến động)"], $v['reasons']));
        if (count($behavioral_days) > 0) {
            $risk_scores['behavioral'] = min(40, count($behavioral_days) * 15);
        }

        // C. Điểm liên tiếp (Max 10đ) - Tính streak dựa trên suspicious_indices
        $streak = 0;
        if (!empty($suspicious_indices)) {
            $current_streak = 1;
            $max_streak = 1;
            for ($i = 1; $i < count($suspicious_indices); $i++) {
                if ($suspicious_indices[$i] == $suspicious_indices[$i-1] + 1) {
                    $current_streak++;
                    $max_streak = max($max_streak, $current_streak);
                } else {
                    $current_streak = 1;
                }
            }
            $streak = $max_streak;
        }

        if ($streak >= 4) $risk_scores['consecutive'] = 10;
        elseif ($streak >= 3) $risk_scores['consecutive'] = 7;
        elseif ($streak >= 2) $risk_scores['consecutive'] = 3;

        $total_score = min(100, array_sum($risk_scores));

        return [
            'risk_score' => $total_score,
            'risk_level' => $total_score >= 80 ? 'critical' : ($total_score >= 40 ? 'warning' : 'normal'),
            'risk_breakdown' => [
                'threshold' => $risk_scores['threshold'],
                'efficiency' => $risk_scores['behavioral'], // Ánh xạ lại vào view hiện tại
                'consecutive' => $risk_scores['consecutive']
            ],
            'violation_count' => $violation_count,
            'total_days' => $total_days,
            'violation_rate' => round(($violation_count / max(1, $total_days)) * 100, 1),
            'max_violation' => max(0, ceil($max_excess_ratio * $adaptive_n) - $adaptive_n),
            'consecutive_violations' => $streak,
            'violation_days' => $violation_days,
            'stats' => [
                'median_cust' => $median,
                'mad' => round($mad, 1),
                'p80' => $p80,
                'adaptive_n' => $adaptive_n,
                'baseline_aov' => $baseline_aov
            ]
        ];
    }

    private function calculateMedian($arr) {
        if (empty($arr)) return 0;
        sort($arr);
        $count = count($arr);
        $middle = floor(($count - 1) / 2);
        if ($count % 2) return $arr[$middle];
        return ($arr[$middle] + $arr[$middle + 1]) / 2;
    }

    private function calculateMAD($arr, $median) {
        if (empty($arr)) return 0;
        $diffs = [];
        foreach ($arr as $val) $diffs[] = abs($val - $median);
        return $this->calculateMedian($diffs);
    }

    private function getPercentile($arr, $percentile) {
        if (empty($arr)) return 0;
        sort($arr);
        $index = ($percentile / 100) * (count($arr) - 1);
        $lower = floor($index);
        $upper = ceil($index);
        $weight = $index - $lower;
        return $arr[$lower] * (1 - $weight) + $arr[$upper] * $weight;
    }

    private function emptyRiskResult() {
        return [
            'risk_score' => 0,
            'risk_level' => 'normal',
            'risk_breakdown' => [
                'threshold' => 0,
                'statistical' => 0,
                'efficiency' => 0,
                'consecutive' => 0
            ],
            'violation_count' => 0,
            'total_days' => 0,
            'violation_rate' => 0,
            'max_violation' => 0,
            'consecutive_violations' => 0,
            'violation_days' => [],
            'stats' => [
                'median_cust' => 0,
                'mad' => 0,
                'p80' => 0,
                'adaptive_n' => 5,
                'baseline_aov' => 0
            ]
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
                    CustCode,
                    customer_name,
                    customer_address,
                    customer_province,
                    tax_code,
                    customer_type,
                    customer_group,
                    is_gkhl,
                    gkhl_types,
                    COUNT(OrderNumber) as order_count,
                    SUM(OrderAmount) as total_amount,
                    GROUP_CONCAT(order_str SEPARATOR '||') as orders_raw
                FROM (
                    SELECT 
                        o.CustCode,
                        MAX(d.TenKH) as customer_name,
                        MAX(d.DiaChi) as customer_address,
                        MAX(d.Tinh) as customer_province,
                        MAX(d.MaSoThue) as tax_code,
                        MAX(d.LoaiKH) as customer_type,
                        MAX(d.PhanLoaiNhomKH) as customer_group,
                        MAX(CASE WHEN g.MaKHDMS IS NOT NULL THEN 1 ELSE 0 END) as is_gkhl,
                        MAX(CONCAT_WS(', ', g.DangKyChuongTrinh, g.DangKyMucDoanhSo, g.DangKyTrungBay)) as gkhl_types,
                        o.OrderNumber,
                        o.OrderDate,
                        SUM(o.TotalNetAmount) as OrderAmount,
                        SUM(o.Qty) as OrderQty,
                        CONCAT(o.OrderDate, '|', o.OrderNumber, '|', SUM(o.TotalNetAmount), '|', SUM(o.Qty)) as order_str
                    FROM orderdetail o
                    LEFT JOIN dskh d ON o.CustCode = d.MaKH
                    LEFT JOIN gkhl g ON o.CustCode = g.MaKHDMS
                    WHERE o.DSRCode = ?
                    AND o.OrderDate >= ?
                    AND o.OrderDate <= ?
                    " . (!empty($product_filter) ? "AND o.ProductCode LIKE ?" : "") . "
                    GROUP BY o.CustCode, o.OrderNumber, o.OrderDate
                ) sub
                GROUP BY CustCode, customer_name, customer_address, customer_province, tax_code, customer_type, customer_group, is_gkhl, gkhl_types
                ORDER BY total_amount DESC";
        
        $params = [$dsr_code, $tu_ngay, $den_ngay];
        if (!empty($product_filter)) {
            $params[] = $product_filter . '%';
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['orders'] = [];
            if (!empty($row['orders_raw'])) {
                $order_items = explode('||', $row['orders_raw']);
                foreach ($order_items as $item) {
                    $parts = explode('|', $item);
                    if (count($parts) >= 4) {
                        $row['orders'][] = [
                            'date' => $parts[0],
                            'order_number' => $parts[1],
                            'amount' => floatval($parts[2]),
                            'qty' => intval($parts[3])
                        ];
                    }
                }
            }
            unset($row['orders_raw']);
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
                AND o.OrderDate >= ?
                AND o.OrderDate <= ?
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

    /**
     * ✅ Lấy chi tiết sản phẩm của một đơn hàng (Lọc theo điều kiện sản phẩm nếu có)
     */
    public function getOrderProductDetails($orderNumber, $product_filter = '') {
        $sql = "SELECT 
                    ProductCode,
                    ProductSaleType as SaleType,
                    Qty as Quantity,
                    TotalNetAmount
                FROM orderdetail
                WHERE OrderNumber = ?
                " . (!empty($product_filter) ? "AND ProductCode LIKE ?" : "") . "
                ORDER BY ProductCode ASC";
        
        $params = [$orderNumber];
        if (!empty($product_filter)) {
            $params[] = $product_filter . '%';
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>