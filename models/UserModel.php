<?php
/**
 * ============================================
 * USER MODEL
 * ============================================
 */

require_once __DIR__ . '/../config/database.php';

class UserModel {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    /**
     * Xác thực đăng nhập
     */
    public function authenticate($username, $password) {
        $sql = "SELECT id, username, password, full_name, email, role, is_active 
                FROM users 
                WHERE username = ? AND is_active = 1 
                LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            return false;
        }
        
        // Update last login
        $this->updateLastLogin($user['id']);
        
        // Log login
        $this->logLogin($user['id']);
        
        // Return user without password
        unset($user['password']);
        return $user;
    }
    
    /**
     * Tạo user mới
     */
    public function createUser($data) {
        $sql = "INSERT INTO users (username, password, full_name, email, role, is_active) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $data['username'],
                $hashedPassword,
                $data['full_name'],
                $data['email'] ?? null,
                $data['role'] ?? 'user',
                $data['is_active'] ?? 1
            ]);
            
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("Create user error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cập nhật user
     */
    public function updateUser($userId, $data) {
        $fields = [];
        $params = [];
        
        if (isset($data['full_name'])) {
            $fields[] = "full_name = ?";
            $params[] = $data['full_name'];
        }
        
        if (isset($data['email'])) {
            $fields[] = "email = ?";
            $params[] = $data['email'];
        }
        
        if (isset($data['role'])) {
            $fields[] = "role = ?";
            $params[] = $data['role'];
        }
        
        if (isset($data['is_active'])) {
            $fields[] = "is_active = ?";
            $params[] = $data['is_active'];
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            $fields[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        
        try {
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Update user error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Xóa user
     */
    public function deleteUser($userId) {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$userId]);
    }
    
    /**
     * Lấy danh sách users
     */
    public function getAllUsers($filters = []) {
        $sql = "SELECT u.id, u.username, u.full_name, u.email, u.role, 
                       u.is_active, u.last_login, u.created_at,
                       GROUP_CONCAT(CONCAT(p.permission_key, ':', p.permission_value)) as permissions
                FROM users u
                LEFT JOIN user_permissions p ON u.id = p.user_id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['role'])) {
            $sql .= " AND u.role = ?";
            $params[] = $filters['role'];
        }
        
        if (isset($filters['is_active'])) {
            $sql .= " AND u.is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " GROUP BY u.id ORDER BY u.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Lấy thông tin user theo ID
     */
    public function getUserById($userId) {
        $sql = "SELECT u.*, 
                       GROUP_CONCAT(CONCAT(p.permission_key, ':', p.permission_value)) as permissions
                FROM users u
                LEFT JOIN user_permissions p ON u.id = p.user_id
                WHERE u.id = ?
                GROUP BY u.id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Kiểm tra permission
     */
    public function hasPermission($userId, $permissionKey) {
        $sql = "SELECT permission_value 
                FROM user_permissions 
                WHERE user_id = ? AND permission_key = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId, $permissionKey]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['permission_value'] == 1;
    }
    
    /**
     * Cập nhật permission
     */
    public function updatePermission($userId, $permissionKey, $value) {
        $sql = "INSERT INTO user_permissions (user_id, permission_key, permission_value) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE permission_value = ?";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$userId, $permissionKey, $value, $value]);
    }
    
    /**
     * Lấy tất cả permissions của user
     */
    public function getUserPermissions($userId) {
        $sql = "SELECT permission_key, permission_value 
                FROM user_permissions 
                WHERE user_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);
        
        $permissions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions[$row['permission_key']] = (bool)$row['permission_value'];
        }
        
        return $permissions;
    }
    
    /**
     * Lấy lịch sử đăng nhập
     */
    public function getLoginHistory($limit = 50) {
        $sql = "SELECT id, user_id, ip_address, user_agent, login_time, logout_time, session_token 
                FROM login_history 
                ORDER BY login_time DESC 
                LIMIT ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Lấy tất cả user permissions
     */
    public function getAllUserPermissions() {
        $sql = "SELECT id, user_id, permission_key, permission_value, created_at 
                FROM user_permissions 
                ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Lấy lịch sử chuyển đổi vai trò
     */
    public function getRoleSwitchLog() {
        $sql = "SELECT id, admin_user_id, switched_to_role, switched_at, switched_back_at 
                FROM role_switch_log 
                ORDER BY switched_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update last login
     */
    private function updateLastLogin($userId) {
        $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);
    }
    
    /**
     * Log login
     */
    public function logLogin($userId) {
        $sql = "INSERT INTO login_history (user_id, ip_address, user_agent, session_token) 
                VALUES (?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            session_id()
        ]);
        
        return $this->conn->lastInsertId();
    }
    
    /**
     * Log logout
     */
    public function logLogout($userId) {
        $sql = "UPDATE login_history 
                SET logout_time = NOW() 
                WHERE user_id = ? AND session_token = ? AND logout_time IS NULL";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId, session_id()]);
    }
    
    /**
     * Log role switch
     */
    public function logRoleSwitch($userId, $targetRole) {
        $sql = "INSERT INTO role_switch_log (admin_user_id, switched_to_role) 
                VALUES (?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId, $targetRole]);
    }
    
    /**
     * Log role switch back
     */
    public function logRoleSwitchBack($userId) {
        $sql = "UPDATE role_switch_log 
                SET switched_back_at = NOW() 
                WHERE admin_user_id = ? AND switched_back_at IS NULL 
                ORDER BY switched_at DESC LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);
    }
    
    /**
     * Đổi mật khẩu
     */
    public function changePassword($userId, $newPassword) {
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$hashedPassword, $userId]);
    }
    
    /**
     * Kiểm tra username đã tồn tại
     */
    public function usernameExists($username, $excludeUserId = null) {
        $sql = "SELECT id FROM users WHERE username = ?";
        $params = [$username];
        
        if ($excludeUserId) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }
}
?>