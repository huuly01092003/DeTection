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
    case 'get_orders':
        // Lấy chi tiết đơn hàng nhân viên (AJAX)
        $controller->getEmployeeOrders();
        break;
        
    case 'report':
    default:
        // Hiển thị báo cáo chính
        $controller->showReport();
        break;
}
?>