<?php
/**
 * ============================================
 * AUTHENTICATION MIDDLEWARE - FIXED VERSION
 * ============================================
 */

class AuthMiddleware {
    
    /**
     * Kiểm tra user đã đăng nhập chưa
     */
    public static function requireLogin() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: login.php');
            exit;
        }
        
        // Kiểm tra session timeout (30 phút không hoạt động)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            self::logout();
            $_SESSION['error'] = 'Phiên làm việc đã hết hạn. Vui lòng đăng nhập lại.';
            header('Location: login.php');
            exit;
        }
        
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Kiểm tra quyền Admin
     */
    public static function requireAdmin() {
        self::requireLogin();
        
        $currentRole = self::getCurrentRole();
        
        // Nếu đang switch role, kiểm tra original role
        if (isset($_SESSION['switched_from_role'])) {
            if ($_SESSION['switched_from_role'] !== 'admin') {
                self::accessDenied();
            }
        } else {
            if ($currentRole !== 'admin') {
                self::accessDenied();
            }
        }
        
        return true;
    }
    
    /**
     * Kiểm tra permission cụ thể
     */
    public static function checkPermission($permissionKey) {
        self::requireLogin();
        
        // Admin có tất cả quyền
        if (self::getCurrentRole() === 'admin' && !isset($_SESSION['switched_to_role'])) {
            return true;
        }
        
        require_once __DIR__ . '/../models/UserModel.php';
        $userModel = new UserModel();
        
        $userId = $_SESSION['user_id'];
        return $userModel->hasPermission($userId, $permissionKey);
    }
    
    /**
     * Lấy role hiện tại (có thể là switched role)
     */
    public static function getCurrentRole() {
        if (isset($_SESSION['switched_to_role'])) {
            return $_SESSION['switched_to_role'];
        }
        
        return $_SESSION['user_role'] ?? 'viewer';
    }
    
    /**
     * Kiểm tra có đang switch role không
     */
    public static function isSwitchedRole() {
        return isset($_SESSION['switched_to_role']);
    }
    
    /**
     * Lấy thông tin user hiện tại
     */
    public static function getCurrentUser() {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'role' => self::getCurrentRole(),
            'is_switched' => self::isSwitchedRole(),
            'original_role' => $_SESSION['switched_from_role'] ?? null
        ];
    }
    
    /**
     * Đăng xuất
     */
    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Log logout time
        if (isset($_SESSION['user_id'])) {
            require_once __DIR__ . '/../models/UserModel.php';
            $userModel = new UserModel();
            $userModel->logLogout($_SESSION['user_id']);
        }
        
        session_unset();
        session_destroy();
    }
    
    /**
     * Access Denied Handler
     */
    private static function accessDenied() {
        http_response_code(403);
        $_SESSION['error'] = 'Bạn không có quyền truy cập trang này.';
        header('Location: dashboard.php');
        exit;
    }
    
    /**
     * Switch Role (chỉ cho Admin)
     */
    public static function switchRole($targetRole) {
        self::requireAdmin();
        
        $allowedRoles = ['admin', 'user', 'viewer'];
        if (!in_array($targetRole, $allowedRoles)) {
            return false;
        }
        
        // Lưu role gốc nếu chưa switch
        if (!isset($_SESSION['switched_from_role'])) {
            $_SESSION['switched_from_role'] = $_SESSION['user_role'];
        }
        
        $_SESSION['switched_to_role'] = $targetRole;
        
        // Log switch event
        require_once __DIR__ . '/../models/UserModel.php';
        $userModel = new UserModel();
        $userModel->logRoleSwitch($_SESSION['user_id'], $targetRole);
        
        return true;
    }
    
    /**
     * Switch back to Admin
     */
    public static function switchBackToAdmin() {
        if (!isset($_SESSION['switched_from_role'])) {
            return false;
        }
        
        if ($_SESSION['switched_from_role'] !== 'admin') {
            return false;
        }
        
        // Log switch back
        require_once __DIR__ . '/../models/UserModel.php';
        $userModel = new UserModel();
        $userModel->logRoleSwitchBack($_SESSION['user_id']);
        
        unset($_SESSION['switched_to_role']);
        unset($_SESSION['switched_from_role']);
        
        return true;
    }
    
    /**
     * Generate CSRF Token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF Token
     */
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

/**
 * ============================================
 * HELPER FUNCTIONS
 * ============================================
 */

/**
 * Quick check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Quick check if user is admin
 */
function isAdmin() {
    return AuthMiddleware::getCurrentRole() === 'admin' && !AuthMiddleware::isSwitchedRole();
}

/**
 * Quick check if user is a regular user
 */
function isUser() {
    return AuthMiddleware::getCurrentRole() === 'user';
}

/**
 * Quick check if user is viewer
 */
function isViewer() {
    return AuthMiddleware::getCurrentRole() === 'viewer';
}

/**
 * Check permission
 */
function can($permission) {
    return AuthMiddleware::checkPermission($permission);
}

/**
 * Get current user
 */
function currentUser() {
    return AuthMiddleware::getCurrentUser();
}

/**
 * Redirect if not logged in
 */
function requireAuth() {
    AuthMiddleware::requireLogin();
}

/**
 * Redirect if not admin
 */
function requireAdmin() {
    AuthMiddleware::requireAdmin();
}