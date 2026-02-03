<?php
/**
 * ✅ NHÂN VIÊN REPORT - MAIN ENTRY POINT
 * File: nhanvien_report.php (root folder)
 * Giống cấu trúc dskh.php (KHÔNG CÓ EXPORT)
 */

session_start();

// ✅ Load controller
require_once 'controllers/NhanVienReportController.php';

// ✅ Khởi tạo controller (đã có authentication bên trong)
$controller = new NhanVienReportController();

// ✅ Xử lý action
$action = $_GET['action'] ?? 'report';

switch ($action) {
    case 'getTinhByKhuVuc':
        // Lấy danh sách Tỉnh theo Khu vực (cascading)
        $controller->getTinhByKhuVuc();
        break;

    case 'getNhanVienByFilters':
        // Lấy danh sách Nhân viên theo filters (cascading)
        $controller->getNhanVienByFilters();
        break;

    case 'get_orders':
        // Lấy chi tiết đơn hàng nhân viên (AJAX)
        $controller->getEmployeeOrders();
        break;
    
    case 'get_daily_sales':
        // Lấy dữ liệu doanh số theo ngày cho biểu đồ (AJAX)
        $controller->getDailySalesChart();
        break;

    case 'export_excel':
        // Xuất báo cáo ra Excel
        $controller->exportExcel();
        break;
        
    case 'report':
    default:
        // Hiển thị báo cáo chính
        $controller->showReport();
        break;
}
?>