<?php
/**
 * ✅ CONTROLLER BÁO CÁO NHÂN VIÊN - WITH AUTHENTICATION
 * File: controllers/NhanVienReportController.php
 * Tương tự DskhController (KHÔNG CÓ EXPORT)
 */

require_once 'models/NhanVienReportModel.php';
require_once 'middleware/AuthMiddleware.php';
require_once 'helpers/permission_helpers.php';

class NhanVienReportController {
    private $model;

    public function __construct() {
        // ✅ REQUIRE LOGIN
        AuthMiddleware::requireLogin();
        
        $this->model = new NhanVienReportModel();
    }

    /**
     * ✅ CHI TIẾT ĐƠN HÀNG NHÂN VIÊN (AJAX)
     * Permission: Tất cả role có thể xem
     */
    public function getEmployeeOrders() {
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $dsr_code = $_GET['dsr_code'] ?? '';
            $tu_ngay = $_GET['tu_ngay'] ?? '';
            $den_ngay = $_GET['den_ngay'] ?? '';
            
            if (empty($dsr_code) || empty($tu_ngay) || empty($den_ngay)) {
                echo json_encode(['error' => 'Thiếu tham số'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $orders = $this->model->getEmployeeOrderDetails($dsr_code, $tu_ngay, $den_ngay);
            echo json_encode($orders, JSON_UNESCAPED_UNICODE);
            exit;
            
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * ✅ LẤY DỮ LIỆU DOANH SỐ THEO NGÀY CHO BIỂU ĐỒ (AJAX)
     * Trả về dữ liệu cho heatmap/chart phân tích bất thường
     */
    public function getDailySalesChart() {
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $tu_ngay = $_GET['tu_ngay'] ?? '';
            $den_ngay = $_GET['den_ngay'] ?? '';
            $thang = $_GET['thang'] ?? '';
            $type = $_GET['type'] ?? 'all'; // 'all' hoặc 'suspect'
            
            if (empty($tu_ngay) || empty($den_ngay)) {
                echo json_encode(['error' => 'Thiếu tham số ngày'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Lấy dữ liệu doanh số theo ngày
            $dailySales = $this->model->getDailySalesForChart($tu_ngay, $den_ngay, $thang);
            
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
            if ($limit <= 0) $limit = 20;

            // Nếu chỉ lấy nhân viên nghi vấn
            $suspectList = [];
            if ($type === 'suspect' && !empty($thang)) {
                $suspects = $this->model->getTopSuspectEmployees($tu_ngay, $den_ngay, $thang, $limit);
                $suspectList = array_column($suspects, 'dsr_code');
                
                // Lọc dữ liệu chỉ lấy nhân viên nghi vấn
                $dailySales = array_filter($dailySales, function($row) use ($suspectList) {
                    return in_array($row['dsr_code'], $suspectList);
                });
                $dailySales = array_values($dailySales);
            }
            
            echo json_encode([
                'success' => true,
                'data' => $dailySales,
                'tu_ngay' => $tu_ngay,
                'den_ngay' => $den_ngay,
                'suspect_list' => $suspectList
            ], JSON_UNESCAPED_UNICODE);
            exit;
            
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * ✅ HIỂN THỊ BÁO CÁO CHÍNH
     * Permission: Tất cả role có thể xem
     */
    public function showReport() {
        $startTime = microtime(true);
        
        // ✅ LẤY THÔNG TIN USER
        $currentUser = AuthMiddleware::getCurrentUser();
        $currentRole = AuthMiddleware::getCurrentRole();
        
        $message = '';
        $type = '';
        $report = [];
        $so_ngay = 0;
        $ket_qua_chung = 0;
        $ty_le_nghi_van = 0;
        $tu_ngay = date('Y-m-d');
        $den_ngay = date('Y-m-d');
        $tong_tien_ky = 0;
        $tong_tien_khoang = 0;
        $tong_tien_ky_detailed = [];
        $debug_info = '';
        $available_months = [];
        $top_threshold = 0;
        $tong_nghi_van = 0;
        $thang = '';
        $has_filtered = false;
        $he_so = 1.5; // ✅ Hệ số nghi vấn (mặc định 1.5, có thể tùy chỉnh)
        $so_thang_lich_su = 0; // Initialize default
        $max_history_months = 0; // Initialize default
        
        try {
            $available_months = $this->model->getAvailableMonths();
            $date_ranges = $this->model->getActualDateRanges(); // ✅ Khoảng ngày thực tế
            
            if (empty($available_months)) {
                $message = "⚠️ Chưa có dữ liệu. Vui lòng import OrderDetail trước.";
                $type = 'warning';
                require_once 'views/nhanvien_report/report.php';
                return;
            }
            
            $has_filtered = !empty($_GET['tu_ngay']) && !empty($_GET['den_ngay']);
            
            
            $thang = !empty($_GET['thang']) ? $_GET['thang'] : $available_months[0];
            if (!in_array($thang, $available_months)) {
                $thang = $available_months[0];
            }

            // ✅ Tính toán số tháng lịch sử tối đa dựa trên tháng đang chọn
            // Trong available_months, các tháng được sắp xếp DESC (mới nhất lên đầu)
            // Ví dụ: [12, 11, 10, 09]. Nếu chọn 11 (index 1), history tối đa là count-1-1 = 2 (10, 09)
            $currentIndex = array_search($thang, $available_months);
            if ($currentIndex !== false) {
                 $max_history_months = count($available_months) - 1 - $currentIndex;
                 // Giới hạn hiển thị tối đa 5 tháng (theo UI cũ)
                 $max_history_months = min(5, $max_history_months);
            }

            if (!$has_filtered) {
                require_once 'views/nhanvien_report/report.php';
                return;
            }
            $tu_ngay = trim($_GET['tu_ngay']);
            $den_ngay = trim($_GET['den_ngay']);
            
            if (strtotime($tu_ngay) > strtotime($den_ngay)) {
                list($tu_ngay, $den_ngay) = [$den_ngay, $tu_ngay];
            }
            
            // ✅ SỬ DỤNG KHOẢNG NGÀY THỰC TẾ TỪ DATABASE (thay vì tháng lịch)
            $thang_start = isset($date_ranges[$thang]) ? $date_ranges[$thang]['min_date'] : $thang . '-01';
            $thang_end = isset($date_ranges[$thang]) ? $date_ranges[$thang]['max_date'] : date('Y-m-t', strtotime($thang . '-01'));
            
            if (strtotime($tu_ngay) < strtotime($thang_start)) $tu_ngay = $thang_start;
            if (strtotime($den_ngay) > strtotime($thang_end)) $den_ngay = $thang_end;

            $ngay_diff = intval((strtotime($den_ngay) - strtotime($tu_ngay)) / 86400);
            $so_ngay = max(1, $ngay_diff + 1);

            // ✅ LẤY THỐNG KÊ (sẽ dùng cache nếu có)
            $stats_thang = $this->model->getSystemStatsForMonth($thang);
            $stats_khoang = $this->model->getSystemStatsForRange($tu_ngay, $den_ngay);
            
            // ✅ BỔ SUNG DỮ LIỆU BENCHMARK THÁNG VÀO STATS_KHOANG ĐỂ MODAL LẤY ĐƯỢC
            $stats_khoang['so_ngay_trong_thang'] = $stats_thang['so_ngay'] ?? 1;
            $stats_khoang['ds_tb_chung_thang'] = $stats_thang['ds_tb_chung_thang'] ?? 0;
            $stats_khoang['ds_ngay_cao_nhat_tb_thang'] = $stats_thang['ds_ngay_cao_nhat_tb_thang'] ?? 0;

            $tong_tien_ky = $stats_thang['total'] ?? 0;
            $tong_tien_khoang = $stats_khoang['total'] ?? 0;
            
            $ket_qua_chung = ($tong_tien_ky > 0) ? ($tong_tien_khoang / $tong_tien_ky) : 0;
            
            // ✅ LẤY HỆ SỐ TỪ FORM (mặc định 1.5)
            $he_so = isset($_GET['he_so']) && is_numeric($_GET['he_so']) ? floatval($_GET['he_so']) : 1.5;
            $he_so = max(1, min(5, $he_so)); // Giới hạn từ 1 đến 5
            
            $ty_le_nghi_van = $ket_qua_chung * $he_so;

            // ✅ XỬ LÝ LỊCH SỬ (SO SÁNH CÁC THÁNG TRƯỚC)
            $so_thang_lich_su = isset($_GET['so_thang_lich_su']) ? intval($_GET['so_thang_lich_su']) : 0;
            // Clamp so_thang_lich_su based on available data
            $so_thang_lich_su = min($so_thang_lich_su, $max_history_months);
            
            $history_data = []; // [ 'month' => [ 'tu_ngay', 'den_ngay', 'stats', 'employees' ] ]
            
            if ($so_thang_lich_su > 0 && $currentIndex !== false) {
                // B1: Xác định vị trí tương đối của khoảng ngày hiện tại trong tháng hiện tại
                $curr_min = $thang_start;
                $curr_max = $thang_end;
                
                $tu_ngay_ts = strtotime($tu_ngay);
                $den_ngay_ts = strtotime($den_ngay);
                $curr_min_ts = strtotime($curr_min);
                $curr_max_ts = strtotime($curr_max);
                
                // Khoảng cách từ đầu tháng và từ cuối tháng (để quyết định logic map)
                $offset_from_start = round(($tu_ngay_ts - $curr_min_ts) / 86400);
                $offset_from_end = round(($curr_max_ts - $den_ngay_ts) / 86400);
                $duration_days = round(($den_ngay_ts - $tu_ngay_ts) / 86400);
                
                // Nếu chọn toàn bộ tháng (hoặc gần như toàn bộ), ta sẽ lấy toàn bộ tháng lịch sử
                $is_full_month = ($tu_ngay == $curr_min && $den_ngay == $curr_max);

                for ($i = 1; $i <= $so_thang_lich_su; $i++) {
                    if (isset($available_months[$currentIndex + $i])) {
                        $prevMonth = $available_months[$currentIndex + $i];
                        $prev_range_min = isset($date_ranges[$prevMonth]) ? $date_ranges[$prevMonth]['min_date'] : $prevMonth . '-01';
                        $prev_range_max = isset($date_ranges[$prevMonth]) ? $date_ranges[$prevMonth]['max_date'] : date('Y-m-t', strtotime($prevMonth . '-01'));
                        
                        $prev_min_ts = strtotime($prev_range_min);
                        $prev_max_ts = strtotime($prev_range_max);

                        if ($is_full_month) {
                            $tu_ngay_hist = $prev_range_min;
                            $den_ngay_hist = $prev_range_max;
                        } else {
                            // Nếu chọn ngày gần cuối tháng hơn, ta map theo cuối tháng
                            if ($offset_from_end < $offset_from_start) {
                                // Map từ cuối tháng lùi lại
                                $den_ngay_hist_ts = $prev_max_ts - ($offset_from_end * 86400);
                                $tu_ngay_hist_ts = $den_ngay_hist_ts - ($duration_days * 86400);
                            } else {
                                // Map từ đầu tháng tiến tới
                                $tu_ngay_hist_ts = $prev_min_ts + ($offset_from_start * 86400);
                                $den_ngay_hist_ts = $tu_ngay_hist_ts + ($duration_days * 86400);
                            }
                            
                            // Clamp kết quả trong range thực tế của tháng đó
                            $tu_ngay_hist_ts = max($prev_min_ts, min($prev_max_ts, $tu_ngay_hist_ts));
                            $den_ngay_hist_ts = max($prev_min_ts, min($prev_max_ts, $den_ngay_hist_ts));
                            
                            $tu_ngay_hist = date('Y-m-d', $tu_ngay_hist_ts);
                            $den_ngay_hist = date('Y-m-d', $den_ngay_hist_ts);
                        }
                        
                        $stats_thang_hist = $this->model->getSystemStatsForMonth($prevMonth);
                        $stats_khoang_hist = $this->model->getSystemStatsForRange($tu_ngay_hist, $den_ngay_hist);
                        
                        $tong_tien_ky_hist = $stats_thang_hist['total'] ?? 0;
                        $tong_tien_khoang_hist = $stats_khoang_hist['total'] ?? 0;
                        $ket_qua_chung_hist = ($tong_tien_ky_hist > 0) ? ($tong_tien_khoang_hist / $tong_tien_ky_hist) : 0;
                        $ty_le_nghi_van_hist = $ket_qua_chung_hist * $he_so;
                        
                        $stats_detailed_hist = [
                            'ds_tb_chung_khoang' => $stats_khoang_hist['ds_tb_chung_khoang'] ?? 0,
                            'ds_ngay_cao_nhat_tb_khoang' => $stats_khoang_hist['ds_ngay_cao_nhat_tb_khoang'] ?? 0,
                            'aov_khoang' => $stats_khoang_hist['aov_khoang'] ?? 0,
                            'orders_per_day_khoang' => $stats_khoang_hist['orders_per_day_khoang'] ?? 0,
                            'cust_per_day_khoang' => $stats_khoang_hist['cust_per_day_khoang'] ?? 0,
                            'gkhl_rate_khoang' => $stats_khoang_hist['gkhl_rate_khoang'] ?? 0,

                            'ds_tong_thang_nv' => 0,
                            'so_nhan_vien_thang' => $stats_thang_hist['emp_count'] ?? 0,
                            'ds_tb_chung_thang' => $stats_thang_hist['ds_tb_chung_thang'] ?? 0,
                            'ds_ngay_cao_nhat_tb_thang' => $stats_thang_hist['ds_ngay_cao_nhat_tb_thang'] ?? 0,
                            'aov_thang' => $stats_thang_hist['aov_thang'] ?? 0,
                            'orders_per_day_thang' => $stats_thang_hist['orders_per_day_thang'] ?? 0,
                            'cust_per_day_thang' => $stats_thang_hist['cust_per_day_thang'] ?? 0,

                            'so_ngay' => $stats_khoang_hist['so_ngay'] ?? 0,
                            'so_ngay_trong_thang' => $stats_thang_hist['so_ngay'] ?? 0
                        ];

                        $employees_hist = $this->model->getAllEmployeesWithStats($tu_ngay_hist, $den_ngay_hist, $prevMonth);
                        $report_hist = [];
                        
                        foreach ($employees_hist as $emp) {
                            $ds_tim_kiem = $emp['ds_tong_thang_nv'] ?? 0;
                            $ds_tien_do = $emp['ds_tien_do'] ?? 0;
                            $ty_le = ($ds_tim_kiem > 0) ? ($ds_tien_do / $ds_tim_kiem) : 0;
                            
                            $aov_khoang_hist = ($emp['so_don_khoang'] ?? 0) > 0 ? ($ds_tien_do / $emp['so_don_khoang']) : 0;
                            $aov_thang_hist = ($emp['so_don_thang'] ?? 0) > 0 ? ($ds_tim_kiem / $emp['so_don_thang']) : 0;

                            $is_nghi_van = ($ty_le >= $ty_le_nghi_van_hist);
                            
                            $report_hist[$emp['DSRCode']] = array_merge($emp, [
                                'ma_nv' => $emp['DSRCode'],
                                'ten_nv' => $emp['ten_nhan_vien'],
                                'ds_tim_kiem' => $ds_tim_kiem,
                                'ds_tien_do' => $ds_tien_do,
                                'ty_le' => $ty_le,
                                'is_nghi_van' => $is_nghi_van,
                                'suspect_score' => 0, // Bỏ điểm
                                'aov_khoang' => $aov_khoang_hist,
                                'aov_thang' => $aov_thang_hist,
                                'highlight_type' => $is_nghi_van ? 'red' : 'none',
                                'rpt_month' => $prevMonth,
                                'tu_ngay' => $tu_ngay_hist,
                                'den_ngay' => $den_ngay_hist,
                                'so_don_khoang' => $emp['so_don_khoang'] ?? 0,
                                'so_kh_khoang' => $emp['so_kh_khoang'] ?? 0,
                                'so_don_thang' => $emp['so_don_thang'] ?? 0,
                                'so_kh_thang' => $emp['so_kh_thang'] ?? 0,
                                'so_ngay_co_doanh_so_khoang' => $emp['so_ngay_co_doanh_so_khoang'] ?? 0,
                                'so_ngay_co_doanh_so_thang' => $emp['so_ngay_co_doanh_so_thang'] ?? 0,
                                'ds_kh_lon_nhat_khoang' => $emp['ds_kh_lon_nhat_khoang'] ?? 0,
                                'ds_gkhl_khoang' => $emp['ds_gkhl_khoang'] ?? 0,
                                'so_kh_gkhl_khoang' => $emp['so_kh_gkhl_khoang'] ?? 0,
                                'ds_ngay_cao_nhat_nv_khoang' => $emp['ds_ngay_cao_nhat_nv_khoang'] ?? 0
                            ]);
                        }

                        // Tối ưu: Chỉ giữ lại Top 100 nhân viên trong report lịch sử để giảm dung lượng data
                        uasort($report_hist, function($a, $b) {
                            return $b['ds_tim_kiem'] <=> $a['ds_tim_kiem'];
                        });
                        $report_hist = array_slice($report_hist, 0, 100, true);

                        $history_data[$prevMonth] = [
                            'tu_ngay' => $tu_ngay_hist,
                            'den_ngay' => $den_ngay_hist,
                            'ket_qua_chung' => $ket_qua_chung_hist,
                            'ty_le_nghi_van' => $ty_le_nghi_van_hist,
                            'stats_detailed' => $stats_detailed_hist,
                            'report' => $report_hist
                        ];
                    }
                }
            }

            $tong_tien_ky_detailed = [
                'ds_tb_chung_khoang' => $stats_khoang['ds_tb_chung_khoang'] ?? 0,
                'ds_ngay_cao_nhat_tb_khoang' => $stats_khoang['ds_ngay_cao_nhat_tb_khoang'] ?? 0,
                'aov_khoang' => $stats_khoang['aov_khoang'] ?? 0,
                'orders_per_day_khoang' => $stats_khoang['orders_per_day_khoang'] ?? 0,
                'cust_per_day_khoang' => $stats_khoang['cust_per_day_khoang'] ?? 0,
                'gkhl_rate_khoang' => $stats_khoang['gkhl_rate_khoang'] ?? 0,

                'so_nhan_vien_khoang' => $stats_khoang['emp_count'] ?? 0,
                'tong_tien_khoang' => $tong_tien_khoang,
                'so_ngay' => $so_ngay,
                
                'ds_tb_chung_thang' => $stats_thang['ds_tb_chung_thang'] ?? 0,
                'ds_ngay_cao_nhat_tb_thang' => $stats_thang['ds_ngay_cao_nhat_tb_thang'] ?? 0,
                'aov_thang' => $stats_thang['aov_thang'] ?? 0,
                'orders_per_day_thang' => $stats_thang['orders_per_day_thang'] ?? 0,
                'cust_per_day_thang' => $stats_thang['cust_per_day_thang'] ?? 0,

                'so_nhan_vien_thang' => $stats_thang['emp_count'] ?? 0,
                'tong_tien_ky' => $tong_tien_ky,
                'so_ngay_trong_thang' => $stats_thang['so_ngay'] ?? 30
            ];

            // ✅ LẤY NHÂN VIÊN (sẽ dùng cache nếu có)
            $employees = $this->model->getAllEmployeesWithStats($tu_ngay, $den_ngay, $thang);

            if (empty($employees)) {
                $message = "⚠️ Không có dữ liệu nhân viên trong khoảng thời gian này.";
                $type = 'warning';
                require_once 'views/nhanvien_report/report.php';
                return;
            }

            $report_nghi_van = [];
            $report_ok = [];
            
            foreach ($employees as $emp) {
                $ds_tim_kiem = $emp['ds_tong_thang_nv'] ?? 0;
                $ds_tien_do = $emp['ds_tien_do'] ?? 0;

                if ($ds_tien_do > 0 || $ds_tim_kiem > 0) {
                    $ty_le = ($ds_tim_kiem > 0) ? ($ds_tien_do / $ds_tim_kiem) : 0;
                    
                    $row = [
                        'ma_nv' => $emp['DSRCode'],
                        'ten_nv' => !empty($emp['ten_nhan_vien']) ? $emp['ten_nhan_vien'] : ('NV_' . $emp['DSRCode']),
                        'bo_phan' => $emp['bo_phan'] ?? 'N/A',
                        'chuc_vu' => $emp['chuc_vu'] ?? 'N/A',
                        'base_tinh' => $emp['base_tinh'] ?? ($emp['DSRTypeProvince'] ?? ''),
                        'khu_vuc' => $emp['khu_vuc'] ?? '',
                        'kenh_ban_hang' => $emp['kenh_ban_hang'] ?? '',
                        'trang_thai' => $emp['trang_thai'] ?? '',
                        'ma_nv_ql' => $emp['ma_nv_ql'] ?? '',
                        'ten_nv_ql' => $emp['ten_nv_ql'] ?? '',
                        'ngay_vao_cty' => $emp['ngay_vao_cty'] ?? '',
                        'ds_tim_kiem' => $ds_tim_kiem,
                        'ds_tien_do' => $ds_tien_do,
                        'ty_le' => $ty_le,
                        
                        'ds_ngay_cao_nhat_nv_khoang' => $emp['ds_ngay_cao_nhat_nv_khoang'] ?? 0,
                        'so_don_khoang' => $emp['so_don_khoang'] ?? 0,
                        'so_kh_khoang' => $emp['so_kh_khoang'] ?? 0,
                        'so_ngay_co_doanh_so_khoang' => $emp['so_ngay_co_doanh_so_khoang'] ?? 0,
                        
                        'ds_tong_thang_nv' => $ds_tim_kiem,
                        'so_don_thang' => $emp['so_don_thang'] ?? 0,
                        'so_kh_thang' => $emp['so_kh_thang'] ?? 0,
                        'ds_ngay_cao_nhat_nv_thang' => $emp['ds_ngay_cao_nhat_nv_thang'] ?? 0,
                        'so_ngay_co_doanh_so_thang' => $emp['so_ngay_co_doanh_so_thang'] ?? 0,

                        // MỚI: Các trường phân tích chuyên sâu
                        'ds_kh_lon_nhat_khoang' => $emp['ds_kh_lon_nhat_khoang'] ?? 0,
                        'ds_gkhl_khoang' => $emp['ds_gkhl_khoang'] ?? 0,
                        'so_kh_gkhl_khoang' => $emp['so_kh_gkhl_khoang'] ?? 0
                    ];
                    
                    if ($ty_le >= $ty_le_nghi_van) {
                        $row['is_suspect'] = true;
                        $row['suspect_score'] = 0; // Bỏ điểm
                        $report_nghi_van[] = $row;
                    } else {
                        $row['is_suspect'] = false;
                        $row['suspect_score'] = 0; // Bỏ điểm
                        $report_ok[] = $row;
                    }
                }
            }

            usort($report_nghi_van, function($a, $b) {
                // Ưu tiên điểm số nghi vấn trước, sau đó đến tỷ lệ
                if ($b['suspect_score'] !== $a['suspect_score']) {
                    return $b['suspect_score'] <=> $a['suspect_score'];
                }
                return $b['ty_le'] <=> $a['ty_le'];
            });
            
            $tong_nghi_van = count($report_nghi_van);
            
            if ($tong_nghi_van >= 20) $top_threshold = 20;
            elseif ($tong_nghi_van >= 15) $top_threshold = 15;
            elseif ($tong_nghi_van >= 10) $top_threshold = 10;
            elseif ($tong_nghi_van >= 5) $top_threshold = 5;
            else $top_threshold = $tong_nghi_van;
            
            foreach ($report_nghi_van as $key => &$item) {
                $item['rank'] = $key + 1;
                $item['highlight_type'] = ($item['rank'] <= $top_threshold) ? 'red' : 'orange';
            }
            unset($item);
            
            foreach ($report_ok as &$item) {
                $item['rank'] = 0;
                $item['highlight_type'] = 'none';
            }
            unset($item);
            
            $report = array_merge($report_nghi_van, $report_ok);
            
            // ✅ HIỂN THỊ THÔNG BÁO CACHE
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($duration < 200) {
                $message = "✅ Dữ liệu từ Cache Redis ({$duration}ms) - Phân tích " . count($report) . " nhân viên!";
                $type = 'success';
            } else {
                $message = "✅ Dữ liệu từ Database ({$duration}ms) - Phân tích " . count($report) . " nhân viên! Lần sau sẽ nhanh hơn.";
                $type = 'info';
            }
            
            $debug_info = "Tháng: $thang | Nhân viên: " . count($employees) . " | Nghi vấn: $tong_nghi_van | Top: $top_threshold | Thời gian: {$duration}ms | User: {$currentUser['username']} ({$currentRole})";
            
            if (empty($report)) {
                $message = "⚠️ Không có dữ liệu cho khoảng thời gian này.";
                $type = 'warning';
            }
            
        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
            $type = 'danger';
            error_log("NhanVienReportController Error: " . $e->getMessage());
        }
        
        require_once 'views/nhanvien_report/report.php';
    }
}
?>