<?php
/**
 * ============================================
 * ENHANCED SYSTEM MANAGEMENT ROUTER
 * ============================================
 * Handles all system management operations including new Redis features
 */

session_start();

require_once 'middleware/AuthMiddleware.php';
require_once 'controllers/SystemManagementController.php';

// Require admin access
try {
    AuthMiddleware::requireAdmin();
} catch (Exception $e) {
    if (isset($_GET['action']) || isset($_POST['action'])) {
        // AJAX request
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized access'
        ]);
    } else {
        // Normal request
        $_SESSION['error'] = 'Bạn không có quyền truy cập chức năng này';
        header('Location: dashboard.php');
    }
    exit;
}

$controller = new SystemManagementController();
$action = $_GET['action'] ?? $_POST['action'] ?? 'index';

switch ($action) {
    // Table operations
    case 'clear_table_all':
        $controller->clearTableAll();
        break;
    
    // Redis operations - Enhanced
    case 'clear_redis_all':
        $controller->clearRedisAll();
        break;
        
    case 'clear_redis_pattern':
        $controller->clearRedisByPattern();
        break;
        
    case 'get_redis_keys':
        $controller->getRedisKeys();
        break;
    
    // NEW: Get cache summary
    case 'get_cache_summary':
        $controller->getCacheSummary();
        break;
    
    // NEW: Delete single Redis key
    case 'delete_redis_key':
        $controller->deleteRedisKey();
        break;
    
    // NEW: Get cache statistics
    case 'get_cache_stats':
        $controller->getCacheStats();
        break;
    
    // Default: show management interface
    default:
        $controller->index();
        break;
}
?>