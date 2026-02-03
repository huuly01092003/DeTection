<?php
/**
 * ✅ CONTROLLER KPI Nhân Viên + AUTHENTICATION
 */

require_once 'models/NhanVienKPIModel.php';
require_once 'middleware/AuthMiddleware.php';
require_once 'helpers/permission_helpers.php';

class NhanVienKPIController {
    private $model;

    public function __construct() {
        // ✅ REQUIRE LOGIN
        AuthMiddleware::requireLogin();
        
        $this->model = new NhanVienKPIModel();
    }

    public function showKPIReport() {
        $startTime = microtime(true);
        
        // ✅ LẤY THÔNG TIN USER
        $currentUser = AuthMiddleware::getCurrentUser();
        $currentRole = AuthMiddleware::getCurrentRole();
        
        $message = '';
        $type = '';
        $kpi_data = [];
        $statistics = [];
        $filters = [];
        $available_months = [];
        $available_products = [];
        $available_khuvuc = [];
        $available_tinh = [];
        $available_bophan = [];
        $available_chucvu = [];
        $has_filtered = false;
        
        $threshold_n = isset($_GET['threshold_n']) ? intval($_GET['threshold_n']) : 5;
        
        try {
            $available_months = $this->model->getAvailableMonths();
            $date_ranges = $this->model->getActualDateRanges(); // ✅ Khoảng ngày thực tế
            
            if (empty($available_months)) {
                $message = "⚠️ Chưa có dữ liệu. Vui lòng import OrderDetail trước.";
                $type = 'warning';
                require_once 'views/nhanvien_kpi/report.php';
                return;
            }
            
            $available_products = $this->model->getAvailableProducts();
            $available_khuvuc = $this->model->getAvailableKhuVuc();
            $available_tinh = $this->model->getAvailableTinh();
            $available_bophan = $this->model->getAvailableBoPhan();
            $available_chucvu = $this->model->getAvailableChucVu();
            
            $has_filtered = !empty($_GET['tu_ngay']) && !empty($_GET['den_ngay']);
            
            $thang = !empty($_GET['thang']) ? $_GET['thang'] : $available_months[0];
            if (!in_array($thang, $available_months)) $thang = $available_months[0];

            // ✅ Lấy khoảng ngày thực tế cho tháng đã chọn
            $range = $date_ranges[$thang] ?? null;
            if ($range) {
                $default_tu_ngay = $range['min_date'];
                $default_den_ngay = $range['max_date'];
            } else {
                $default_tu_ngay = $thang . '-01';
                $default_den_ngay = date('Y-m-t', strtotime($default_tu_ngay));
            }
            
            if (!$has_filtered) {
                $filters = [
                    'thang' => $thang,
                    'tu_ngay' => $default_tu_ngay,
                    'den_ngay' => $default_den_ngay,
                    'product_filter' => '',
                    'threshold_n' => $threshold_n
                ];
                
                $statistics = [
                    'total_employees' => 0,
                    'employees_with_orders' => 0,
                    'total_orders' => 0,
                    'total_customers' => 0,
                    'total_amount' => 0,
                    'avg_orders_per_emp' => 0,
                    'avg_customers_per_emp' => 0,
                    'warning_count' => 0,
                    'danger_count' => 0,
                    'normal_count' => 0
                ];
                
                require_once 'views/nhanvien_kpi/report.php';
                return;
            }
            
            $tu_ngay = trim($_GET['tu_ngay']);
            $den_ngay = trim($_GET['den_ngay']);
            
            if (strtotime($tu_ngay) > strtotime($den_ngay)) {
                list($tu_ngay, $den_ngay) = [$den_ngay, $tu_ngay];
            }
            
            $product_filter = !empty($_GET['product_filter']) ? trim($_GET['product_filter']) : '';
            if ($product_filter === '--all--') $product_filter = '';
            if (!empty($product_filter)) $product_filter = substr($product_filter, 0, 2);
            
            // ✅ LẤY FILTER NÂNG CAO
            $khu_vuc = !empty($_GET['khu_vuc']) ? trim($_GET['khu_vuc']) : '';
            $tinh = !empty($_GET['tinh']) ? trim($_GET['tinh']) : '';
            $bo_phan = !empty($_GET['bo_phan']) ? trim($_GET['bo_phan']) : '';
            $chuc_vu = !empty($_GET['chuc_vu']) ? trim($_GET['chuc_vu']) : '';
            $nhan_vien = !empty($_GET['nhan_vien']) ? trim($_GET['nhan_vien']) : '';
            $specific_product_code = !empty($_GET['specific_product']) ? trim($_GET['specific_product']) : '';
            
            $filters = [
                'thang' => $thang,
                'tu_ngay' => $tu_ngay,
                'den_ngay' => $den_ngay,
                'product_filter' => $product_filter,
                'specific_product' => $specific_product_code,
                'threshold_n' => $threshold_n,
                'khu_vuc' => $khu_vuc,
                'tinh' => $tinh,
                'bo_phan' => $bo_phan,
                'chuc_vu' => $chuc_vu,
                'nhan_vien' => $nhan_vien
            ];
            
            // ✅ LẤY DỮ LIỆU
            $employees = $this->model->getAllEmployeesWithKPI($tu_ngay, $den_ngay, $product_filter, $threshold_n, $khu_vuc, $tinh, $bo_phan, $chuc_vu, $nhan_vien, $specific_product_code);
            
            if (empty($employees)) {
                $message = "⚠️ Không có dữ liệu nhân viên.";
                $type = 'warning';
                
                $statistics = [
                    'total_employees' => 0,
                    'employees_with_orders' => 0,
                    'total_orders' => 0,
                    'total_customers' => 0,
                    'total_gross' => 0,
                    'total_scheme' => 0,
                    'total_net' => 0,
                    'total_amount' => 0,
                    'avg_orders_per_emp' => 0,
                    'avg_customers_per_emp' => 0,
                    'warning_count' => 0,
                    'danger_count' => 0,
                    'normal_count' => 0
                ];
                
                require_once 'views/nhanvien_kpi/report.php';
                return;
            }
            
            $system_metrics = $this->model->getSystemMetrics($tu_ngay, $den_ngay, $product_filter, $specific_product_code);
            
            $emp_count = $system_metrics['emp_count'];
            $total_orders = $system_metrics['total_orders'];
            $total_customers = $system_metrics['total_customers'];
            $total_amount = $system_metrics['total_amount'];
            
            $avg_orders_per_emp = $emp_count > 0 ? $total_orders / $emp_count : 0;
            $avg_customers_per_emp = $emp_count > 0 ? $total_customers / $emp_count : 0;
            
            $suspicious_employees = [];
            $warning_employees = [];
            $normal_employees = [];
            
            foreach ($employees as &$emp_kpi) {
                $reasons = [];
                $analysis = $emp_kpi['risk_analysis'];
                
                if ($emp_kpi['violation_count'] > 0) {
                    $reasons[] = "Vi phạm ngưỡng {$emp_kpi['violation_count']} ngày";
                }
                
                if ($analysis['risk_breakdown']['scheme'] > 0) {
                    $reasons[] = "💰 Lạm dụng KM (" . $emp_kpi['scheme_rate'] . "%)";
                }
                
                if ($analysis['consecutive_violations'] >= 3) {
                    $reasons[] = "Vi phạm liên tục {$analysis['consecutive_violations']} ngày";
                }
                
                if (empty($reasons)) {
                    $reasons[] = "Hoạt động bình thường";
                }
                
                $emp_kpi['risk_reasons'] = $reasons;
                
                if ($emp_kpi['risk_level'] === 'critical') {
                    $suspicious_employees[] = $emp_kpi;
                } elseif ($emp_kpi['risk_level'] === 'warning') {
                    $warning_employees[] = $emp_kpi;
                } else {
                    $normal_employees[] = $emp_kpi;
                }
            }
            unset($emp_kpi);
            
            usort($suspicious_employees, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);
            usort($warning_employees, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);
            usort($normal_employees, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);
            
            $statistics = [
                'total_employees' => count($employees),
                'employees_with_orders' => $emp_count,
                'total_orders' => $total_orders,
                'total_customers' => $total_customers,
                'total_gross' => $system_metrics['total_gross'] ?? 0,
                'total_scheme' => $system_metrics['total_scheme'] ?? 0,
                'total_net' => $system_metrics['total_net'] ?? 0,
                'total_amount' => $total_amount,
                'avg_orders_per_emp' => round($avg_orders_per_emp, 2),
                'avg_customers_per_emp' => round($avg_customers_per_emp, 2),
                'warning_count' => count($warning_employees),
                'danger_count' => count($suspicious_employees),
                'normal_count' => count($normal_employees),
                'threshold_n' => $threshold_n
            ];
            
            $kpi_data = array_merge($suspicious_employees, $warning_employees, $normal_employees);
            
            // ✅ HIỂN THỊ THÔNG BÁO CACHE
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            if (empty($kpi_data)) {
                $message = "⚠️ Không có dữ liệu cho khoảng thời gian này.";
                $type = 'warning';
            } else {
                if ($duration < 200) {
                    $message = "✅ Dữ liệu từ Cache Redis ({$duration}ms) - Phân tích " . count($kpi_data) . " nhân viên với ngưỡng N={$threshold_n}! User: {$currentUser['username']} ({$currentRole})";
                    $type = 'success';
                } else {
                    $message = "✅ Dữ liệu từ Database ({$duration}ms) - Phân tích " . count($kpi_data) . " nhân viên với ngưỡng N={$threshold_n}! Lần sau sẽ nhanh hơn. User: {$currentUser['username']} ({$currentRole})";
                    $type = 'info';
                }
            }
            
        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
            $type = 'danger';
            error_log("NhanVienKPIController Error: " . $e->getMessage());
            
            $statistics = [
                'total_employees' => 0,
                'employees_with_orders' => 0,
                'total_orders' => 0,
                'total_customers' => 0,
                'total_gross' => 0,
                'total_scheme' => 0,
                'total_net' => 0,
                'total_amount' => 0,
                'avg_orders_per_emp' => 0,
                'avg_customers_per_emp' => 0,
                'warning_count' => 0,
                'danger_count' => 0,
                'normal_count' => 0
            ];
        }
        
        require_once 'views/nhanvien_kpi/report.php';
    }
    
    /**
     * ✅ AJAX: Lấy chi tiết khách hàng
     */
    public function getEmployeeCustomers() {
        header('Content-Type: application/json');
        
        $dsr_code = $_GET['dsr_code'] ?? '';
        $tu_ngay = $_GET['tu_ngay'] ?? '';
        $den_ngay = $_GET['den_ngay'] ?? '';
        $product_filter = $_GET['product_filter'] ?? '';
        $specific_product = $_GET['specific_product'] ?? '';
        
        if (empty($dsr_code) || empty($tu_ngay) || empty($den_ngay)) {
            echo json_encode(['success' => false, 'error' => 'Missing parameters']);
            return;
        }
        
        try {
            $customers = $this->model->getEmployeeCustomerDetails($dsr_code, $tu_ngay, $den_ngay, $product_filter, $specific_product);
            echo json_encode(['success' => true, 'data' => $customers]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * ✅ AJAX: Lấy chi tiết sản phẩm của đơn hàng
     */
    public function getOrderProducts() {
        header('Content-Type: application/json');
        
        $order_number = $_GET['order_number'] ?? '';
        $product_filter = $_GET['product_filter'] ?? '';
        
        if (empty($order_number)) {
            echo json_encode(['success' => false, 'error' => 'Missing order number']);
            return;
        }
        
        try {
            $products = $this->model->getOrderProductDetails($order_number, $product_filter);
            echo json_encode(['success' => true, 'data' => $products]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * ✅ AJAX: Lấy danh sách Tỉnh theo Khu vực (cascading dropdown)
     */
    public function getTinhByKhuVuc() {
        header('Content-Type: application/json');
        
        $khu_vuc = $_GET['khu_vuc'] ?? '';
        
        try {
            $tinh_list = $this->model->getTinhByKhuVuc($khu_vuc);
            echo json_encode(['success' => true, 'data' => $tinh_list]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * ✅ AJAX: Lấy danh sách Nhân viên theo các filter (cascading dropdown)
     */
    public function getNhanVienByFilters() {
        header('Content-Type: application/json');
        
        $khu_vuc = $_GET['khu_vuc'] ?? '';
        $tinh = $_GET['tinh'] ?? '';
        $bo_phan = $_GET['bo_phan'] ?? '';
        $chuc_vu = $_GET['chuc_vu'] ?? '';
        
        try {
            $nhanvien_list = $this->model->getNhanVienByFilters($khu_vuc, $tinh, $bo_phan, $chuc_vu);
            echo json_encode(['success' => true, 'data' => $nhanvien_list]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * ✅ AJAX: Lấy danh sách sản phẩm theo nhóm
     */
    public function getProductsByGroup() {
        header('Content-Type: application/json');
        
        $group_code = $_GET['group_code'] ?? '';
        
        if (empty($group_code)) {
            echo json_encode(['success' => false, 'data' => []]);
            return;
        }
        
        try {
            $products = $this->model->getProductsByGroup($group_code);
            echo json_encode(['success' => true, 'data' => $products]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
?>