<?php
/**
 * ============================================
 * USER & SYSTEM MANAGEMENT ROUTER
 * ============================================
 */

session_start();

require_once 'middleware/AuthMiddleware.php';

// Require admin access
AuthMiddleware::requireAdmin();

// Get tab parameter
$tab = $_GET['tab'] ?? 'users';

// Route to appropriate controller
if ($tab === 'system') {
    // System Management
    require_once 'controllers/SystemManagementController.php';
    $controller = new SystemManagementController();
    $action = $_GET['action'] ?? 'index';
    
    switch ($action) {
        case 'clear_table_all':
            $controller->clearTableAll();
            break;
        case 'clear_table_by_date':
            $controller->clearTableByDate();
            break;
        case 'clear_redis_all':
            $controller->clearRedisAll();
            break;
        case 'clear_redis_pattern':
            $controller->clearRedisByPattern();
            break;
        case 'get_redis_keys':
            $controller->getRedisKeys();
            break;
        default:
            $controller->index();
            break;
    }
} else {
    // User Management
    require_once 'controllers/UserController.php';
    $controller = new UserController();
    $action = $_GET['action'] ?? 'index';
    
    switch ($action) {
        case 'create':
            $controller->create();
            break;
        case 'store':
            $controller->store();
            break;
        case 'edit':
            $controller->edit();
            break;
        case 'update':
            $controller->update();
            break;
        case 'delete':
            $controller->delete();
            break;
        case 'toggle_status':
            $controller->toggleStatus();
            break;
        case 'reset_password':
            $controller->resetPassword();
            break;
        default:
            $controller->index();
            break;
    }
}
?>