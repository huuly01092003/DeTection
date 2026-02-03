<?php
/**
 * ✅ NHÂN VIÊN KPI - MAIN ENTRY POINT
 * File: nhanvien_kpi.php (root folder)
 */

session_start();

// ✅ Load controller
require_once 'controllers/NhanVienKPIController.php';

// ✅ Khởi tạo controller (đã có authentication bên trong)
$controller = new NhanVienKPIController();

// ✅ Xử lý action
$action = $_GET['action'] ?? 'report';

switch ($action) {
    case 'get_customers':
        // Lấy chi tiết khách hàng nhân viên (AJAX)
        $controller->getEmployeeCustomers();
        break;

    case 'get_order_products':
        // Lấy chi tiết sản phẩm của đơn hàng (AJAX)
        $controller->getOrderProducts();
        break;

    case 'getTinhByKhuVuc':
        // ✅ Lấy danh sách Tỉnh theo Khu vực (cascading dropdown)
        $controller->getTinhByKhuVuc();
        break;

    case 'getNhanVienByFilters':
        // ✅ Lấy danh sách Nhân viên theo các filter (cascading dropdown)
        $controller->getNhanVienByFilters();
        break;

    case 'get_products_by_group':
        // ✅ Lấy danh sách sản phẩm theo nhóm (AJAX)
        $controller->getProductsByGroup();
        break;

    case 'report':
    default:
        // Hiển thị báo cáo KPI
        $controller->showKPIReport();
        break;
}
?>