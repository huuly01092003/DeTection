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
    public function getAllEmployeesWithKPI($tu_ngay, $den_ngay, $product_filter = '', $threshold_n = 5, $khu_vuc = '', $tinh = '', $bo_phan = '', $chuc_vu = '', $nhan_vien = '') {
        $cacheKey = $this->generateCacheKey($tu_ngay, $den_ngay, $product_filter, $threshold_n, $khu_vuc, $tinh, $bo_phan, $chuc_vu, $nhan_vien);
        
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
        
        // ✅ LỌC NHÂN VIÊN THEO CÁC BỘ LỌC NÂNG CAO
        if (!empty($khu_vuc) || !empty($tinh) || !empty($bo_phan) || !empty($chuc_vu)) {
            $filtered_emp_base = [];
            foreach ($emp_base as $emp) {
                $dsrCode = $emp['DSRCode'];
                $nv = $nv_data[$dsrCode] ?? null;
                
                // Bỏ qua nếu không có thông tin NV
                if (!$nv) continue;
                
                // Check từng filter
                if (!empty($khu_vuc) && ($nv['khu_vuc'] ?? '') !== $khu_vuc) continue;
                if (!empty($tinh) && ($nv['base_tinh'] ?? '') !== $tinh) continue;
                if (!empty($bo_phan) && ($nv['bo_phan'] ?? '') !== $bo_phan) continue;
                if (!empty($chuc_vu) && ($nv['chuc_vu'] ?? '') !== $chuc_vu) continue;
                
                $filtered_emp_base[] = $emp;
            }
            $emp_base = $filtered_emp_base;
            
            if (empty($emp_base)) return [];
        }
        
        // ✅ LỌC THEO NHÂN VIÊN CỤ THỂ
        if (!empty($nhan_vien)) {
            $emp_base = array_filter($emp_base, function($emp) use ($nhan_vien) {
                return $emp['DSRCode'] === $nhan_vien;
            });
            $emp_base = array_values($emp_base);
            if (empty($emp_base)) return [];
        }
        
        // ✅ Lấy thống kê cho từng nhân viên (loop đơn giản)
        $results = [];
        
        // ✅ 3. Lấy TOÀN BỘ thống kê hàng ngày cho TOÀN BỘ nhân viên (sử dụng index hints)
        // PHÒNG CHỐNG LỖI "MySQL server has gone away": Tăng timeout và đơn giản hóa truy vấn
        try {
            $this->conn->exec("SET SESSION wait_timeout=600");
            $this->conn->exec("SET SESSION interactive_timeout=600");
            $this->conn->exec("SET SESSION max_allowed_packet=67108864");
        } catch (Exception $e) { /* Ignore error if session set fails */ }

        // Truy vấn 1: Lấy các số liệu tổng hợp cơ bản (nhẹ hơn)
        $sqlBasic = "SELECT 
                        o.DSRCode,
                        o.OrderDate as order_date,
                        COUNT(DISTINCT o.OrderNumber) as daily_orders,
                        COUNT(DISTINCT o.CustCode) as daily_customers,
                        SUM(o.TotalGrossAmount) as daily_gross,
                        SUM(o.TotalSchemeAmount) as daily_scheme,
                        SUM(o.TotalNetAmount) as daily_amount
                    FROM orderdetail o
                    WHERE o.OrderDate >= ? AND o.OrderDate <= ?
                    " . $rpt_where . "
                    " . (!empty($product_filter) ? "AND o.ProductCode LIKE ?" : "") . "
                    GROUP BY o.DSRCode, o.OrderDate";
        
        $paramsBasic = array_merge([$tu_ngay, $den_ngay], $rpt_params);
        if (!empty($product_filter)) { $paramsBasic[] = $product_filter . '%'; }
        
        $stmt1 = $this->conn->prepare($sqlBasic);
        $stmt1->execute($paramsBasic);
        $basicData = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        // Truy vấn 2: Lấy thông tin chẻ đơn (đếm khách hàng có nhiều đơn/ngày) - Truy vấn này thường nặng, xử lý riêng
        $sqlMulti = "SELECT DSRCode, OrderDate, COUNT(*) as multi_order_customers
                     FROM (
                        SELECT o.DSRCode, o.OrderDate, o.CustCode
                        FROM orderdetail o
                        WHERE o.OrderDate >= ? AND o.OrderDate <= ?
                        " . $rpt_where . "
                        " . (!empty($product_filter) ? "AND o.ProductCode LIKE ?" : "") . "
                        GROUP BY o.DSRCode, o.OrderDate, o.CustCode
                        HAVING COUNT(DISTINCT o.OrderNumber) > 1
                     ) t
                     GROUP BY DSRCode, OrderDate";
        
        $stmt2 = $this->conn->prepare($sqlMulti);
        $stmt2->execute($paramsBasic);
        $multiData = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // Gộp dữ liệu trong PHP (tiết kiệm tài nguyên server MySQL)
        $multiOrderMap = [];
        foreach ($multiData as $md) {
            $multiOrderMap[$md['DSRCode']][$md['OrderDate']] = $md['multi_order_customers'];
        }

        $allDailyData = [];
        foreach ($basicData as $bd) {
            $bd['multi_order_customers'] = $multiOrderMap[$bd['DSRCode']][$bd['order_date']] ?? 0;
            $allDailyData[] = $bd;
        }


        // --- MỚI: TÍNH TOÁN GKHL CHÍNH XÁC (MTD context) ---
        // Lấy ngày đầu tháng của ngày bắt đầu báo cáo
        $first_of_month = date('Y-m-01', strtotime($tu_ngay));
        $day_before_start = date('Y-m-d', strtotime($tu_ngay . ' -1 day'));
        
        $customerRunningNet = []; // Lũy kế doanh số cho mỗi khách hàng THEO TẪNg DSRCode

        // Bước 1: Lấy doanh số 'nền' từ đầu tháng đến trước ngày bắt đầu báo cáo
        // ✅ SỬa: Phân biệt theo DSRCode để đếm chính xác KH đạt GKHL cho từng NV
        if ($day_before_start >= $first_of_month) {
            $sqlBaseNet = "SELECT o.DSRCode, o.CustCode, SUM(o.TotalNetAmount) as base_net 
                           FROM orderdetail o
                           JOIN gkhl g ON o.CustCode = g.MaKHDMS
                           WHERE o.OrderDate >= ? AND o.OrderDate <= ? 
                           " . $rpt_where . "
                           GROUP BY o.DSRCode, o.CustCode";
            
            $baseParams = array_merge([$first_of_month, $day_before_start], $rpt_params);
            
            $stmtBase = $this->conn->prepare($sqlBaseNet);
            $stmtBase->execute($baseParams);
            while ($row = $stmtBase->fetch(PDO::FETCH_ASSOC)) {
                $customerRunningNet[$row['DSRCode']][$row['CustCode']] = floatval($row['base_net']);
            }
        }

        // Truy vấn 3: Lấy dữ liệu chốt số GKHL (Tính toán thông minh qua PHP để tránh timeout)
        $sqlGKHL = "SELECT o.DSRCode, o.OrderDate, o.CustCode, SUM(o.TotalNetAmount) as day_net, MAX(g.DangKyMucDoanhSo) as gk_limit
                    FROM orderdetail o
                    JOIN gkhl g ON o.CustCode = g.MaKHDMS
                    WHERE o.OrderDate >= ? AND o.OrderDate <= ?
                    " . $rpt_where . "
                    " . (!empty($product_filter) ? "AND o.ProductCode LIKE ?" : "") . "
                    GROUP BY o.DSRCode, o.OrderDate, o.CustCode";
        
        $stmt3 = $this->conn->prepare($sqlGKHL);
        $stmt3->execute($paramsBasic);
        $gkhlData = $stmt3->fetchAll(PDO::FETCH_ASSOC);

        $gkhlAchieverMap = []; // [DSRCode][OrderDate] = số KH đạt chỉ tiêu
        $gkhlAchievedCustomers = []; // [DSRCode][CustCode] = ngày đạt chỉ tiêu
        $gkhlAchieverDetails = []; // [DSRCode][OrderDate] = [{CustCode, limit, achieved_net}]
        
        foreach ($gkhlData as $gd) {
            $cust = $gd['CustCode'];
            $dsrCode = $gd['DSRCode'];
            $orderDate = $gd['OrderDate'];
            $limitStr = $gd['gk_limit'];
            
            // XỬ LÝ ĐỊNH MỨC: Loại bỏ dấu phẩy
            $cleanLimit = preg_replace('/[^0-9]/', '', $limitStr);
            $limit = floatval($cleanLimit);
            
            if ($limit <= 0) continue;

            // ✅ SỬa: Lấy prevNet THEO DSRCode
            $prevNet = $customerRunningNet[$dsrCode][$cust] ?? 0;
            $currNet = $prevNet + floatval($gd['day_net']);
            $customerRunningNet[$dsrCode][$cust] = $currNet;

            // ✅ CHỈ ĐẼM KH ĐẠT CHỈ TIÊU (chuyển từ chưa đạt -> đạt)
            if ($prevNet < $limit && $currNet >= $limit) {
                if (!isset($gkhlAchieverMap[$dsrCode][$orderDate])) {
                    $gkhlAchieverMap[$dsrCode][$orderDate] = 0;
                }
                $gkhlAchieverMap[$dsrCode][$orderDate]++;
                
                // Lưu ngày đạt chỉ tiêu cho từng khách hàng
                if (!isset($gkhlAchievedCustomers[$dsrCode])) {
                    $gkhlAchievedCustomers[$dsrCode] = [];
                }
                $gkhlAchievedCustomers[$dsrCode][$cust] = $orderDate;
                
                // ✅ MỚI: Lưu chi tiết KH đạt GKHL theo ngày
                if (!isset($gkhlAchieverDetails[$dsrCode][$orderDate])) {
                    $gkhlAchieverDetails[$dsrCode][$orderDate] = [];
                }
                $gkhlAchieverDetails[$dsrCode][$orderDate][] = [
                    'CustCode' => $cust,
                    'limit' => $limit,
                    'limit_formatted' => number_format($limit, 0, ',', '.'),
                    'achieved_net' => $currNet,
                    'achieved_net_formatted' => number_format($currNet, 0, ',', '.')
                ];
            }
        }

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
            $daily_grosses = [];
            $daily_schemes = [];
            $daily_multi_cust = [];
            $daily_gkhl_achievers = [];
            
            $total_orders = 0;
            $total_customers = 0;
            $total_amount = 0;
            $total_gross = 0;
            $total_scheme = 0;
            $max_customers = 0;
            
            foreach ($dailyData as $day) {
                $daily_dates[] = $day['order_date'];
                $daily_orders[] = intval($day['daily_orders']);
                $daily_customers[] = intval($day['daily_customers']);
                $daily_amounts[] = floatval($day['daily_amount']);
                $daily_grosses[] = floatval($day['daily_gross'] ?? 0);
                $daily_schemes[] = floatval($day['daily_scheme']);
                $daily_multi_cust[] = intval($day['multi_order_customers']);
                $daily_gkhl_achievers[] = $gkhlAchieverMap[$dsrCode][$day['order_date']] ?? 0;
                
                $total_orders += intval($day['daily_orders']);
                $total_customers += intval($day['daily_customers']);
                $total_amount += floatval($day['daily_amount']);
                $total_gross += floatval($day['daily_gross'] ?? 0);
                $total_scheme += floatval($day['daily_scheme']);
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
                'total_gross' => $total_gross,
                'total_scheme' => $total_scheme,
                'working_days' => $working_days,
                'max_day_customers' => $max_customers,
                'max_day_orders' => max($daily_orders ?: [0]),
                'max_day_amount' => max($daily_amounts ?: [0]),
                'daily_dates' => $daily_dates,
                'daily_orders' => $daily_orders,
                'daily_customers' => $daily_customers,
                'daily_amounts' => $daily_amounts,
                'daily_schemes' => $daily_schemes,
                'daily_multi_cust' => $daily_multi_cust,
                'avg_daily_orders' => $working_days > 0 ? round($total_orders / $working_days, 2) : 0,
                'avg_daily_amount' => $working_days > 0 ? round($total_amount / $working_days, 0) : 0,
                'avg_daily_customers' => $working_days > 0 ? round($total_customers / $working_days, 2) : 0,
                'scheme_rate' => $total_amount > 0 ? round(($total_scheme / $total_amount) * 100, 1) : 0,
            ];
            
            // Phân tích risk nâng cao
            $row['risk_analysis'] = $this->analyzeRiskByThreshold(
                $daily_customers, 
                $threshold_n, 
                $daily_dates, 
                $daily_amounts,   // Tiền thực thu (Net) -> index 3
                $daily_orders,    // Số lượng đơn -> index 4
                $daily_schemes,   // Tiền KM -> index 5
                $daily_multi_cust,// Chẻ đơn -> index 6
                $daily_grosses,   // Tiền hàng -> index 7
                $daily_gkhl_achievers, // Khách chốt GKHL -> index 8
                $dsrCode,         // Mã nhân viên -> index 9
                $gkhlAchievedCustomers, // Danh sách KH đạt GKHL -> index 10
                $gkhlAchieverDetails // Chi tiết KH đạt GKHL theo ngày -> index 11
            );
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
            
            // Tối ưu dữ liệu lưu vào DB (bỏ các chi tiết quá nặng không cần thiết cho việc hiển thị danh sách)
            $dbData = array_map(function($item) {
                // Giữ lại các trường summary quan trọng, bỏ violation_days quá chi tiết
                unset($item['orders']); // Bỏ danh sách đơn hàng
                // Giữ lại cấu trúc risk_analysis nhưng có thể rút gọn nội dung text dài nếu cần
                return $item;
            }, $data);

            $sql = "INSERT INTO summary_nhanvien_kpi_cache 
                    (cache_key, tu_ngay, den_ngay, product_filter, threshold_n, data, employee_count, critical_count, warning_count)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    data = VALUES(data),
                    employee_count = VALUES(employee_count),
                    critical_count = VALUES(critical_count),
                    warning_count = VALUES(warning_count),
                    calculated_at = CURRENT_TIMESTAMP";
            
            // Tăng giới hạn gói tin cho việc save cache
            $this->conn->exec("SET SESSION max_allowed_packet=67108864");
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $cacheKey,
                $tu_ngay,
                $den_ngay,
                $product_filter ?: null,
                $threshold_n,
                json_encode($dbData, JSON_UNESCAPED_UNICODE),
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

    private function analyzeRiskByThreshold($daily_customers, $threshold_n, $daily_dates = [], $daily_amounts = [], $daily_orders = [], $daily_schemes = [], $daily_multi_cust = [], $daily_grosses = [], $daily_gkhl_achievers = [], $dsrCode = '', $gkhlAchievedCustomers = [], $gkhlAchieverDetails = []) {
        $total_days = count($daily_customers);
        if ($total_days < 3) return $this->emptyRiskResult();

        // 1. TÍNH TOÁN THỐNG KÊ ROBUST
        $sorted_cust = $daily_customers;
        sort($sorted_cust);
        $median = $this->calculateMedian($sorted_cust);
        $p80 = $this->getPercentile($sorted_cust, 80);
        
        // Theo yêu cầu người dùng: Ngưỡng N (Input) là ưu tiên số 1 để rà soát
        $adaptive_n = $threshold_n; 
        
        // Vẫn tính statistical_n để tham khảo nếu muốn đánh giá độ lệch (nhưng không dùng làm ngưỡng chính)
        $statistical_n = max($threshold_n, ceil($median * 1.5), ceil($p80));

        // Baseline AOV trung vị
        $temp_aovs = [];
        foreach ($daily_customers as $idx => $count) {
            if ($count > 0) $temp_aovs[] = ($daily_amounts[$idx] ?? 0) / $count;
        }
        $baseline_aov = $this->calculateMedian($temp_aovs);

        // Baseline Scheme Rate trung vị
        $temp_scheme_rates = [];
        foreach ($daily_amounts as $idx => $amt) {
            if ($amt > 0) $temp_scheme_rates[] = ($daily_schemes[$idx] ?? 0) / $amt;
        }
        $baseline_scheme_rate = $this->calculateMedian($temp_scheme_rates);

        $violation_days = [];
        $suspicious_indices = [];
        $violation_count = 0;
        $max_excess_ratio = 0;
        $total_multi_cust = array_sum($daily_multi_cust);

        foreach ($daily_customers as $idx => $count) {
            $day_amount = $daily_amounts[$idx] ?? 0;
            $day_scheme = $daily_schemes[$idx] ?? 0;
            $day_aov = $count > 0 ? ($day_amount / $count) : 0;
            $day_scheme_rate = $day_amount > 0 ? ($day_scheme / $day_amount) : 0;
            $day_multi_cust = $daily_multi_cust[$idx] ?? 0;
            
            // --- NHẬN DIỆN CÁC BIỂU HIỆN BẤT THƯỜNG ---
            
            // 1. Vượt ngưỡng (KPI Pressure)
            $is_threshold_violation = ($count > $adaptive_n);
            
            // 2. Chẻ đơn (Bằng chứng: 1 khách nhiều đơn/ngày HOẶC AOV quá thấp)
            $is_splitting = ($day_multi_cust > 0) || ($count > $median * 1.5 && $day_aov < $baseline_aov * 0.4);
            
            // 3. Gộp đơn (Bằng chứng: AOV cao đột biến + số khách rất ít)
            // Tinh chỉnh: Giảm xuống 2x baseline AOV và nới lỏng số lượng khách
            $is_consolidation = ($day_aov > $baseline_aov * 2.0 && $count < max(3, $median * 0.7) && $count > 0);
            
            // 4. Thao túng khuyến mãi (Bằng chứng: Tỷ lệ KM cao bất thường)
            $is_scheme_abusing = ($day_scheme_rate > $baseline_scheme_rate * 1.8 && $day_scheme_rate > 0.08);

            // 5. Biến động cực đại (Alternating behavior - "Răng cưa")
            $is_alternating = false;
            if ($idx > 0) {
                $prev_count = $daily_customers[$idx-1];
                if (abs($count - $prev_count) > max(3, $median * 1.2)) $is_alternating = true;
            }

            // 6. KH đạt mức doanh số GKHL (CHỈ tính từ ngày 25 đến cuối tháng)
            $is_gkhl_achieving = false;
            $achievers_this_day = $daily_gkhl_achievers[$idx] ?? 0;
            
            if ($achievers_this_day > 0) {
                // Kiểm tra ngày dương lịch
                $current_date = $daily_dates[$idx]; // Format Y-m-d
                $day_parts = explode('-', $current_date);
                $day_num = isset($day_parts[2]) ? (int)$day_parts[2] : 0;
                
                // Chu kỳ rà soát: CHỈ từ ngày 25 đến cuối tháng
                $is_review_window = ($day_num >= 25);
                
                // ✅ CHỈ TÍN LÀ VI PHẠM NẾU:
                // 1. Có KH ĐẠT mức doanh số đăng ký
                // 2. Ngày đạt mức nằm trong khoảng 25- cuối tháng
                if ($is_review_window && $achievers_this_day > 0) {
                    $is_gkhl_achieving = true;
                }
            }

            if ($is_threshold_violation || $is_splitting || $is_consolidation || $is_scheme_abusing || $is_alternating) {
                $reasons = [];
                if ($is_threshold_violation) {
                    $ratio = round($count / $adaptive_n, 1);
                    if ($ratio >= 2) {
                        $reasons[] = "🎁 Vượt ngưỡng đột xuất (Gấp " . $ratio . ")";
                    } else {
                        $reasons[] = "📈 Vượt ngưỡng (" . $count . "/" . $adaptive_n . ")";
                    }
                }
                
                if ($day_multi_cust > 0) {
                    $reasons[] = "✂️ Chẻ đơn: " . $day_multi_cust . " khách có >1 đơn/ngày";
                } elseif ($is_splitting) {
                    $reasons[] = "✂️ Nghi vấn chẻ đơn (AOV thấp)";
                }

                if ($is_consolidation) $reasons[] = "📦 Nghi vấn gộp đơn (AOV cực cao)";
                if ($is_scheme_abusing) $reasons[] = "💰 Lạm dụng khuyến mãi (" . round($day_scheme_rate * 100, 1) . "%)";
                if ($is_alternating) $reasons[] = "⚖️ Biến động khách hàng (Ngày trước/sau lệch lớn)";

                if ($is_threshold_violation) $violation_count++;
                $suspicious_indices[] = $idx;
                $max_excess_ratio = max($max_excess_ratio, $count / max(1, $adaptive_n));

                $violation_days[] = [
                    'date' => $daily_dates[$idx] ?? "Ngày $idx",
                    'customers' => $count,
                    'orders' => $daily_orders[$idx] ?? 0,
                    'multi_cust' => $day_multi_cust,
                    'threshold' => $adaptive_n,
                    'day_aov' => $day_aov,
                    'day_scheme_rate' => $day_scheme_rate,
                    'day_gross' => $daily_grosses[$idx] ?? 0,
                    'day_scheme' => $day_scheme,
                    'total_amount' => $day_amount,
                    'reasons' => $reasons,
                    'is_critical' => ($is_threshold_violation && $count > $adaptive_n * 1.8) || ($day_multi_cust > 2) || ($is_splitting && $is_scheme_abusing),
                    // ✅ MỚI: Chi tiết các KH đạt mức GKHL trong ngày này
                    'gkhl_achiever_details' => $gkhlAchieverDetails[$dsrCode][$daily_dates[$idx]] ?? []
                ];
            }
        }

        // 2. TÍNH ĐIỂM RISK (0-100)
        $risk_scores = ['threshold' => 0, 'splitting' => 0, 'scheme' => 0, 'consecutive' => 0];

        // A. Điểm vượt ngưỡng (Max 50đ, Min 20đ nếu vi phạm)
        // Yêu cầu: Min 20, Max 50.
        if ($violation_count > 0) {
            // Cơ chế: Bắt đầu từ 20đ, cộng thêm 10đ cho mỗi hệ số vượt (max 50)
            $threshold_score = 20 + round(($max_excess_ratio - 1) * 10);
            $risk_scores['threshold'] = max(20, min(50, $threshold_score));
        }

        // B. Điểm chẻ đơn/gộp đơn (Max 20đ)
        $splitting_score = 0;
        foreach ($violation_days as $vd) {
            foreach ($vd['reasons'] as $r) {
                if (strpos($r, "✂️") !== false) $splitting_score += 10;
                if (strpos($r, "📦") !== false) $splitting_score += 10;
            }
        }
        $risk_scores['splitting'] = min(20, $splitting_score);

        // C. Điểm lạm dụng khuyến mãi (Max 20đ)
        $scheme_score = 0;
        foreach ($violation_days as $vd) {
            foreach ($vd['reasons'] as $r) {
                if (strpos($r, "💰") !== false) $scheme_score += 10;
                // ✅ Đã bỏ GKHL khỏi tính điểm
            }
        }
        $risk_scores['scheme'] = min(20, $scheme_score);

        // E. Điểm liên tiếp (Max 10đ)
        $streak = 0;
        if (!empty($suspicious_indices)) {
            $current_streak = 1; $max_streak = 1;
            for ($i = 1; $i < count($suspicious_indices); $i++) {
                if ($suspicious_indices[$i] == $suspicious_indices[$i-1] + 1) {
                    $current_streak++;
                    $max_streak = max($max_streak, $current_streak);
                } else $current_streak = 1;
            }
            $streak = $max_streak;
        }
        $risk_scores['consecutive'] = ($streak >= 4) ? 10 : (($streak >= 2) ? 5 : 0);

        $total_score = min(100, array_sum($risk_scores));

        return [
            'risk_score' => $total_score,
            'risk_level' => $total_score >= 75 ? 'critical' : ($total_score >= 35 ? 'warning' : 'normal'),
            'risk_breakdown' => [
                'threshold' => $risk_scores['threshold'],
                'splitting' => $risk_scores['splitting'],
                'scheme' => $risk_scores['scheme'],
                'consecutive' => $risk_scores['consecutive']
            ],
            'violation_count' => $violation_count,
            'total_days' => $total_days,
            'violation_rate' => round(($violation_count / max(1, $total_days)) * 100, 1),
            'max_violation' => max(0, ceil($max_excess_ratio * $adaptive_n) - $adaptive_n),
            'consecutive_violations' => $streak,
            'multi_order_customers_total' => $total_multi_cust,
            'violation_days' => $violation_days,
            'stats' => [
                'median_cust' => $median,
                'p80' => $p80,
                'adaptive_n' => $adaptive_n,
                'baseline_aov' => $baseline_aov,
                'baseline_scheme_rate' => $baseline_scheme_rate
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
                'splitting' => 0,
                'scheme' => 0,
                'consecutive' => 0
            ],
            'violation_count' => 0,
            'total_days' => 0,
            'violation_rate' => 0,
            'max_violation' => 0,
            'consecutive_violations' => 0,
            'multi_order_customers_total' => 0,
            'violation_days' => [],
            'stats' => [
                'median_cust' => 0,
                'p80' => 0,
                'adaptive_n' => 5,
                'baseline_aov' => 0,
                'baseline_scheme_rate' => 0
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
        $first_of_month = date('Y-m-01', strtotime($den_ngay));
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
                    gk_limit,
                    COUNT(OrderNumber) as order_count,
                    SUM(OrderGross) as total_gross,
                    SUM(OrderScheme) as total_scheme,
                    SUM(OrderAmount) as total_amount,
                    GROUP_CONCAT(order_str SEPARATOR '||') as orders_raw,
                    (SELECT SUM(TotalGrossAmount) FROM orderdetail WHERE CustCode = sub.CustCode AND OrderDate >= ? AND OrderDate <= ?) as mtd_gross,
                    (SELECT SUM(TotalSchemeAmount) FROM orderdetail WHERE CustCode = sub.CustCode AND OrderDate >= ? AND OrderDate <= ?) as mtd_scheme,
                    (SELECT SUM(TotalNetAmount) FROM orderdetail WHERE CustCode = sub.CustCode AND OrderDate >= ? AND OrderDate <= ?) as mtd_net
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
                        MAX(g.DangKyMucDoanhSo) as gk_limit,
                        o.OrderNumber,
                        o.OrderDate,
                        SUM(COALESCE(o.TotalGrossAmount, 0)) as OrderGross,
                        SUM(COALESCE(o.TotalSchemeAmount, 0)) as OrderScheme,
                        SUM(COALESCE(o.TotalNetAmount, 0)) as OrderAmount,
                        SUM(COALESCE(o.Qty, 0)) as OrderQty,
                        CONCAT(COALESCE(o.OrderDate, ''), '|', COALESCE(o.OrderNumber, ''), '|', COALESCE(SUM(o.TotalNetAmount), 0), '|', COALESCE(SUM(o.Qty), 0), '|', COALESCE(SUM(o.TotalGrossAmount), 0), '|', COALESCE(SUM(o.TotalSchemeAmount), 0)) as order_str
                    FROM orderdetail o
                    LEFT JOIN dskh d ON o.CustCode = d.MaKH
                    LEFT JOIN gkhl g ON o.CustCode = g.MaKHDMS
                    WHERE o.DSRCode = ?
                    AND o.OrderDate >= ?
                    AND o.OrderDate <= ?
                    " . (!empty($product_filter) ? "AND o.ProductCode LIKE ?" : "") . "
                    GROUP BY o.CustCode, o.OrderNumber, o.OrderDate
                ) sub
                GROUP BY CustCode, customer_name, customer_address, customer_province, tax_code, customer_type, customer_group, is_gkhl, gkhl_types, gk_limit
                ORDER BY total_amount DESC";
        
        $params = [
            $first_of_month, $den_ngay,  // MTD gross
            $first_of_month, $den_ngay,  // MTD scheme
            $first_of_month, $den_ngay,  // MTD net
            $dsr_code, $tu_ngay, $den_ngay  // Main query: lấy KH trong ngày được chọn
        ];
        if (!empty($product_filter)) {
            $params[] = $product_filter . '%';
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- TÍNH NGÀY ĐẠT CHỈ TIÊU GKHL CHI TIẾT ---
        
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
                            'qty' => intval($parts[3]),
                            'gross' => floatval($parts[4] ?? 0),
                            'scheme' => floatval($parts[5] ?? 0)
                        ];
                    }
                }
            }
            unset($row['orders_raw']);
            
            // ✅ TÍNH NGÀY ĐẠT GKHL (nếu có đăng ký) - dựa trên orders của DSRCode này
            $row['gkhl_achieved_date'] = null;
            $row['gkhl_progress'] = 0;
            
            if ($row['is_gkhl'] == 1 && !empty($row['gk_limit'])) {
                // Parse limit
                $cleanLimit = preg_replace('/[^0-9]/', '', $row['gk_limit']);
                $limit = floatval($cleanLimit);
                
                if ($limit > 0 && !empty($row['mtd_net'])) {
                    $mtd_net = floatval($row['mtd_net']);
                    $row['gkhl_progress'] = round(($mtd_net / $limit) * 100, 1);
                    
                    // Query riêng: lấy orders của DSRCode này từ ĐẦU THÁNG để tính ngày đạt
                    $sqlGKHLOrders = "SELECT OrderDate, SUM(TotalNetAmount) as day_net 
                                      FROM orderdetail 
                                      WHERE CustCode = ? AND DSRCode = ? 
                                        AND OrderDate >= ? AND OrderDate <= ?
                                      GROUP BY OrderDate 
                                      ORDER BY OrderDate ASC";
                    $stmtGKHL = $this->conn->prepare($sqlGKHLOrders);
                    $stmtGKHL->execute([$row['CustCode'], $dsr_code, $first_of_month, $den_ngay]);
                    $gkhlOrders = $stmtGKHL->fetchAll(PDO::FETCH_ASSOC);
                    
                    $runningTotal = 0;
                    foreach ($gkhlOrders as $order) {
                        $runningTotal += floatval($order['day_net']);
                        if ($runningTotal >= $limit) {
                            $row['gkhl_achieved_date'] = $order['OrderDate'];
                            break;
                        }
                    }
                }
            }
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
                    COALESCE(SUM(o.TotalGrossAmount), 0) as total_gross,
                    COALESCE(SUM(o.TotalSchemeAmount), 0) as total_scheme,
                    COALESCE(SUM(o.TotalNetAmount), 0) as total_net,
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

    private function generateCacheKey($tu_ngay, $den_ngay, $product_filter, $threshold_n, $khu_vuc = '', $tinh = '', $bo_phan = '', $chuc_vu = '', $nhan_vien = '') {
        $productHash = !empty($product_filter) ? md5($product_filter) : 'all';
        $filterHash = md5($khu_vuc . '|' . $tinh . '|' . $bo_phan . '|' . $chuc_vu . '|' . $nhan_vien);
        return "nhanvien:kpi:N{$threshold_n}:{$tu_ngay}:{$den_ngay}:{$productHash}:{$filterHash}";
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
     * ✅ LẤY DANH SÁCH KHU VỰC
     */
    public function getAvailableKhuVuc() {
        $sql = "SELECT DISTINCT khu_vuc FROM dsnv WHERE khu_vuc IS NOT NULL AND khu_vuc != '' ORDER BY khu_vuc";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * ✅ LẤY DANH SÁCH TỈNH
     */
    public function getAvailableTinh() {
        $sql = "SELECT DISTINCT base_tinh FROM dsnv WHERE base_tinh IS NOT NULL AND base_tinh != '' ORDER BY base_tinh";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * ✅ LẤY DANH SÁCH BỘ PHẬN
     */
    public function getAvailableBoPhan() {
        $sql = "SELECT DISTINCT bo_phan FROM dsnv WHERE bo_phan IS NOT NULL AND bo_phan != '' ORDER BY bo_phan";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * ✅ LẤY DANH SÁCH CHỨC VỤ
     */
    public function getAvailableChucVu() {
        $sql = "SELECT DISTINCT chuc_vu FROM dsnv WHERE chuc_vu IS NOT NULL AND chuc_vu != '' ORDER BY chuc_vu";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * ✅ LẤY DANH SÁCH TỈNH THEO KHU VỰC (cho cascading dropdown)
     */
    public function getTinhByKhuVuc($khu_vuc = '') {
        if (empty($khu_vuc)) {
            return $this->getAvailableTinh();
        }
        $sql = "SELECT DISTINCT base_tinh FROM dsnv WHERE khu_vuc = ? AND base_tinh IS NOT NULL AND base_tinh != '' ORDER BY base_tinh";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$khu_vuc]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * ✅ LẤY DANH SÁCH NHÂN VIÊN THEO CÁC FILTER (cho cascading dropdown)
     */
    public function getNhanVienByFilters($khu_vuc = '', $tinh = '', $bo_phan = '', $chuc_vu = '') {
        $sql = "SELECT ma_nv, ho_ten FROM dsnv WHERE 1=1";
        $params = [];
        
        if (!empty($khu_vuc)) {
            $sql .= " AND khu_vuc = ?";
            $params[] = $khu_vuc;
        }
        if (!empty($tinh)) {
            $sql .= " AND base_tinh = ?";
            $params[] = $tinh;
        }
        if (!empty($bo_phan)) {
            $sql .= " AND bo_phan = ?";
            $params[] = $bo_phan;
        }
        if (!empty($chuc_vu)) {
            $sql .= " AND chuc_vu = ?";
            $params[] = $chuc_vu;
        }
        
        $sql .= " AND ho_ten IS NOT NULL AND ho_ten != '' ORDER BY ho_ten";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * ✅ LẤY TẤT CẢ NHÂN VIÊN
     */
    public function getAvailableNhanVien() {
        $sql = "SELECT ma_nv, ho_ten FROM dsnv WHERE ho_ten IS NOT NULL AND ho_ten != '' ORDER BY ho_ten";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * ✅ Lấy chi tiết sản phẩm của một đơn hàng (Lọc theo điều kiện sản phẩm nếu có)
     */
    public function getOrderProductDetails($orderNumber, $product_filter = '') {
        $sql = "SELECT 
                    ProductCode,
                    ProductSaleType as SaleType,
                    Qty as Quantity,
                    TotalGrossAmount,
                    TotalSchemeAmount,
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