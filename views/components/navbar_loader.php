<?php
/**
 * ============================================
 * CENTRALIZED NAVBAR LOADER
 * ============================================
 * File: views/components/navbar_loader.php
 * 
 * Quản lý việc load navbar tập trung, tránh xung đột
 */

// Đảm bảo chỉ load 1 lần
if (defined('NAVBAR_LOADED')) {
    return;
}
define('NAVBAR_LOADED', true);

// Load AuthMiddleware trước
if (!class_exists('AuthMiddleware')) {
    require_once __DIR__ . '/../../middleware/AuthMiddleware.php';
}

/**
 * Load navbar phù hợp dựa trên context
 * CHỈ LOAD 1 FILE DUY NHẤT để tránh xung đột
 */
function loadNavbar() {
    $navbarAuthPath = __DIR__ . '/navbar_auth.php';
    $navbarBasicPath = __DIR__ . '/navbar.php';
    
    // Ưu tiên navbar_auth nếu user đã đăng nhập
    if (isset($_SESSION['user_id']) && file_exists($navbarAuthPath)) {
        // Load navbar_auth.php (có authentication)
        require_once $navbarAuthPath;
        return 'auth';
    }
    
    // Fallback về navbar cơ bản (KHÔNG load nếu đã có auth)
    if (!function_exists('renderEnhancedNavbar') && file_exists($navbarBasicPath)) {
        require_once $navbarBasicPath;
        return 'basic';
    }
    
    if (function_exists('renderAuthNavbar')) {
        return 'auth';
    }
    
    if (function_exists('renderEnhancedNavbar')) {
        return 'basic';
    }
    
    throw new Exception('No navbar component found!');
}

/**
 * Smart render function - tự động chọn navbar phù hợp
 */
function renderSmartNavbar($currentPage = '', $additionalInfo = []) {
    $navbarType = loadNavbar();
    
    // Convert string to array nếu cần
    if (is_string($additionalInfo)) {
        $additionalInfo = ['period' => $additionalInfo];
    }
    
    // Gọi hàm render tương ứng với priority
    if ($navbarType === 'auth' && function_exists('renderAuthNavbar')) {
        renderAuthNavbar($currentPage, $additionalInfo);
    } elseif (function_exists('renderEnhancedNavbar')) {
        renderEnhancedNavbar($currentPage, $additionalInfo);
    } else {
        throw new Exception('No navbar render function available!');
    }
}

// Load navbar ngay khi file này được include
loadNavbar();