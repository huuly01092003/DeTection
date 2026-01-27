<?php
/**
 * ============================================
 * PERMISSION HELPER FUNCTIONS
 * ============================================
 * File: helpers/permission_helpers.php
 * 
 * Các hàm tiện ích để kiểm tra quyền trong views
 */

// Tránh load lại nếu đã được include
if (!defined('PERMISSION_HELPERS_LOADED')) {
    define('PERMISSION_HELPERS_LOADED', true);

require_once __DIR__ . '/../middleware/AuthMiddleware.php';

/**
 * Note: isAdmin(), isUser(), isViewer() đã được định nghĩa trong AuthMiddleware.php
 * Không cần khai báo lại ở đây
 */

/**
 * Kiểm tra có quyền export không
 */
function canExport() {
    $role = AuthMiddleware::getCurrentRole();
    return $role !== 'viewer';
}

/**
 * Kiểm tra có quyền import không
 */
function canImport() {
    return isAdmin();
}

/**
 * Kiểm tra có quyền xem anomaly không
 */
function canViewAnomaly() {
    return isAdmin() || AuthMiddleware::checkPermission('view_anomaly');
}

/**
 * Kiểm tra có quyền sử dụng advanced filters không
 */
function canUseAdvancedFilters() {
    return isAdmin() || AuthMiddleware::checkPermission('advanced_filters');
}

/**
 * Lấy thông tin user hiện tại
 */
function getCurrentUser() {
    return AuthMiddleware::getCurrentUser();
}

/**
 * Lấy role hiện tại
 */
function getCurrentRole() {
    return AuthMiddleware::getCurrentRole();
}

/**
 * Render export button (chỉ hiển thị nếu có quyền)
 */
function renderExportButton($text = 'Export', $onclick = '', $class = 'btn-success', $icon = 'fa-file-excel') {
    if (!canExport()) {
        return '';
    }
    
    $html = '<button type="button" class="btn ' . $class . ' export-action" onclick="' . $onclick . '">';
    $html .= '<i class="fas ' . $icon . ' me-2"></i>' . $text;
    $html .= '</button>';
    
    return $html;
}

/**
 * Render import button (chỉ admin)
 */
function renderImportButton($text = 'Import', $onclick = '', $class = 'btn-primary', $icon = 'fa-upload') {
    if (!canImport()) {
        return '';
    }
    
    $html = '<button type="button" class="btn ' . $class . ' import-action" onclick="' . $onclick . '">';
    $html .= '<i class="fas ' . $icon . ' me-2"></i>' . $text;
    $html .= '</button>';
    
    return $html;
}

/**
 * Get CSS class cho body dựa trên role
 */
function getBodyClass() {
    $role = getCurrentRole();
    $classes = [];
    
    if ($role === 'admin' && !AuthMiddleware::isSwitchedRole()) {
        $classes[] = 'admin-mode';
    }
    
    if ($role === 'viewer') {
        $classes[] = 'viewer-mode';
    }
    
    if ($role === 'user') {
        $classes[] = 'user-mode';
    }
    
    if (AuthMiddleware::isSwitchedRole()) {
        $classes[] = 'switched-mode';
    }
    
    return implode(' ', $classes);
}

/**
 * Hiển thị thông báo nếu không có quyền
 */
function showNoPermissionAlert() {
    return '
    <div class="alert alert-warning" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Không có quyền!</strong> Bạn không có quyền sử dụng chức năng này.
        Vui lòng liên hệ quản trị viên để được hỗ trợ.
    </div>
    ';
}

/**
 * Check và redirect nếu không có quyền
 */
function requirePermission($permission) {
    if (!AuthMiddleware::checkPermission($permission)) {
        $_SESSION['error'] = 'Bạn không có quyền truy cập chức năng này';
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Get role badge HTML
 */
function getRoleBadge($role = null) {
    if ($role === null) {
        $role = getCurrentRole();
    }
    
    $badges = [
        'admin' => '<span class="badge bg-danger"><i class="fas fa-crown me-1"></i>Admin</span>',
        'user' => '<span class="badge bg-success"><i class="fas fa-user me-1"></i>User</span>',
        'viewer' => '<span class="badge bg-secondary"><i class="fas fa-eye me-1"></i>Viewer</span>'
    ];
    
    return $badges[$role] ?? '<span class="badge bg-secondary">' . $role . '</span>';
}

/**
 * Check if feature is available for current role
 */
function isFeatureAvailable($feature) {
    $features = [
        'export' => ['admin', 'user'],
        'import' => ['admin'],
        'delete' => ['admin'],
        'edit' => ['admin', 'user'],
        'view_anomaly' => ['admin', 'user'], // có thể override bằng permission
        'advanced_filters' => ['admin', 'user']
    ];
    
    if (!isset($features[$feature])) {
        return false;
    }
    
    $role = getCurrentRole();
    
    // Check role-based access
    if (in_array($role, $features[$feature])) {
        return true;
    }
    
    // Check permission-based access
    return AuthMiddleware::checkPermission($feature);
}

/**
 * Render action buttons based on permissions
 */
function renderActionButtons($config = []) {
    $html = '<div class="action-buttons">';
    
    // Export button
    if (isset($config['export']) && canExport()) {
        $html .= renderExportButton(
            $config['export']['text'] ?? 'Export Excel',
            $config['export']['onclick'] ?? '',
            $config['export']['class'] ?? 'btn-success'
        );
    }
    
    // Import button
    if (isset($config['import']) && canImport()) {
        $html .= ' ' . renderImportButton(
            $config['import']['text'] ?? 'Import',
            $config['import']['onclick'] ?? '',
            $config['import']['class'] ?? 'btn-primary'
        );
    }
    
    // Custom buttons
    if (isset($config['custom'])) {
        foreach ($config['custom'] as $button) {
            if (!isset($button['permission']) || isFeatureAvailable($button['permission'])) {
                $html .= ' <button type="button" class="btn ' . ($button['class'] ?? 'btn-secondary') . '" ';
                $html .= 'onclick="' . ($button['onclick'] ?? '') . '">';
                $html .= '<i class="fas ' . ($button['icon'] ?? 'fa-cog') . ' me-2"></i>';
                $html .= $button['text'] ?? 'Action';
                $html .= '</button>';
            }
        }
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Initialize page with proper classes and restrictions
 */
function initPage() {
    // Add body class
    echo '<script>';
    echo 'document.body.className += " ' . getBodyClass() . '";';
    echo '</script>';
    
    // Add viewer restrictions CSS
    if (isViewer()) {
        echo '<style>';
        if (file_exists(__DIR__ . '/../assets/css/viewer_restrictions.css')) {
            echo file_get_contents(__DIR__ . '/../assets/css/viewer_restrictions.css');
        }
        echo '</style>';
    }
}

} // End of PERMISSION_HELPERS_LOADED check
?>