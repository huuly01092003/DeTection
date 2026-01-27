<?php
/**
 * ============================================
 * USER MANAGEMENT CONTROLLER
 * ============================================
 */

require_once 'models/UserModel.php';
require_once 'middleware/AuthMiddleware.php';

class UserController {
    private $model;
    
    public function __construct() {
        $this->model = new UserModel();
    }
    
    /**
     * Hiển thị danh sách users
     */
    public function index() {
        AuthMiddleware::requireAdmin();
        
        $filters = [
            'role' => $_GET['role'] ?? '',
            'is_active' => isset($_GET['is_active']) ? (int)$_GET['is_active'] : null,
            'search' => $_GET['search'] ?? ''
        ];
        
        $users = $this->model->getAllUsers($filters);
        
        // Lấy dữ liệu cho tab thống kê
        $loginHistory = $this->model->getLoginHistory(50);
        $userPermissions = $this->model->getAllUserPermissions();
        $roleSwitchLog = $this->model->getRoleSwitchLog();
        
        require_once 'views/users/index.php';
    }
    
    /**
     * Hiển thị form tạo user
     */
    public function create() {
        AuthMiddleware::requireAdmin();
        
        require_once 'views/users/create.php';
    }
    
    /**
     * Xử lý tạo user
     */
    public function store() {
        AuthMiddleware::requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: users.php');
            exit;
        }
        
        $data = [
            'username' => trim($_POST['username'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'full_name' => trim($_POST['full_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'role' => $_POST['role'] ?? 'user',
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Validate
        $errors = $this->validateUser($data);
        
        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $data;
            header('Location: users.php?action=create');
            exit;
        }
        
        // Check username exists
        if ($this->model->usernameExists($data['username'])) {
            $_SESSION['error'] = 'Tên đăng nhập đã tồn tại';
            $_SESSION['old'] = $data;
            header('Location: users.php?action=create');
            exit;
        }
        
        $userId = $this->model->createUser($data);
        
        if ($userId) {
            $_SESSION['success'] = "Tạo tài khoản thành công: {$data['username']}";
            header('Location: users.php');
        } else {
            $_SESSION['error'] = 'Có lỗi xảy ra khi tạo tài khoản';
            $_SESSION['old'] = $data;
            header('Location: users.php?action=create');
        }
        exit;
    }
    
    /**
     * Hiển thị form sửa user
     */
    public function edit() {
        AuthMiddleware::requireAdmin();
        
        $userId = (int)($_GET['id'] ?? 0);
        if ($userId <= 0) {
            $_SESSION['error'] = 'ID không hợp lệ';
            header('Location: users.php');
            exit;
        }
        
        $user = $this->model->getUserById($userId);
        if (!$user) {
            $_SESSION['error'] = 'Không tìm thấy người dùng';
            header('Location: users.php');
            exit;
        }
        
        // Parse permissions
        $permissions = [];
        if (!empty($user['permissions'])) {
            foreach (explode(',', $user['permissions']) as $perm) {
                list($key, $value) = explode(':', $perm);
                $permissions[$key] = (bool)$value;
            }
        }
        $user['permissions_array'] = $permissions;
        
        require_once 'views/users/edit.php';
    }
    
    /**
     * Xử lý cập nhật user
     */
    public function update() {
        AuthMiddleware::requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: users.php');
            exit;
        }
        
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $_SESSION['error'] = 'ID không hợp lệ';
            header('Location: users.php');
            exit;
        }
        
        $data = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'role' => $_POST['role'] ?? 'user',
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Đổi mật khẩu nếu có
        if (!empty($_POST['password'])) {
            $data['password'] = $_POST['password'];
        }
        
        // Validate
        $errors = $this->validateUser($data, $userId);
        
        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            header("Location: users.php?action=edit&id={$userId}");
            exit;
        }
        
        // Update user
        if ($this->model->updateUser($userId, $data)) {
            // Update permissions
            if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                foreach ($_POST['permissions'] as $permKey => $permValue) {
                    $this->model->updatePermission($userId, $permKey, $permValue ? 1 : 0);
                }
            }
            
            $_SESSION['success'] = 'Cập nhật thông tin thành công';
        } else {
            $_SESSION['error'] = 'Có lỗi xảy ra khi cập nhật';
        }
        
        header("Location: users.php?action=edit&id={$userId}");
        exit;
    }
    
    /**
     * Xóa user
     */
    public function delete() {
        AuthMiddleware::requireAdmin();
        
        $userId = (int)($_POST['user_id'] ?? 0);
        
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID không hợp lệ']);
            exit;
        }
        
        // Không cho xóa chính mình
        if ($userId == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'error' => 'Không thể xóa tài khoản của chính mình']);
            exit;
        }
        
        if ($this->model->deleteUser($userId)) {
            echo json_encode(['success' => true, 'message' => 'Xóa tài khoản thành công']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Có lỗi xảy ra']);
        }
        exit;
    }
    
    /**
     * Toggle user active status
     */
    public function toggleStatus() {
        AuthMiddleware::requireAdmin();
        
        $userId = (int)($_POST['user_id'] ?? 0);
        $status = (int)($_POST['status'] ?? 1);
        
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID không hợp lệ']);
            exit;
        }
        
        // Không cho khóa chính mình
        if ($userId == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'error' => 'Không thể khóa tài khoản của chính mình']);
            exit;
        }
        
        if ($this->model->updateUser($userId, ['is_active' => $status])) {
            $message = $status ? 'Kích hoạt tài khoản thành công' : 'Khóa tài khoản thành công';
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Có lỗi xảy ra']);
        }
        exit;
    }
    
    /**
     * Reset password
     */
    public function resetPassword() {
        AuthMiddleware::requireAdmin();
        
        $userId = (int)($_POST['user_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';
        
        if ($userId <= 0 || empty($newPassword)) {
            echo json_encode(['success' => false, 'error' => 'Dữ liệu không hợp lệ']);
            exit;
        }
        
        if (strlen($newPassword) < 6) {
            echo json_encode(['success' => false, 'error' => 'Mật khẩu phải có ít nhất 6 ký tự']);
            exit;
        }
        
        if ($this->model->changePassword($userId, $newPassword)) {
            echo json_encode(['success' => true, 'message' => 'Đặt lại mật khẩu thành công']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Có lỗi xảy ra']);
        }
        exit;
    }
    
    /**
     * Validate user data
     */
    private function validateUser($data, $userId = null) {
        $errors = [];
        
        if (isset($data['username']) && empty($data['username'])) {
            $errors['username'] = 'Tên đăng nhập không được để trống';
        }
        
        if (isset($data['password']) && !$userId && empty($data['password'])) {
            $errors['password'] = 'Mật khẩu không được để trống';
        }
        
        if (isset($data['password']) && !empty($data['password']) && strlen($data['password']) < 6) {
            $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự';
        }
        
        if (empty($data['full_name'])) {
            $errors['full_name'] = 'Họ tên không được để trống';
        }
        
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email không hợp lệ';
        }
        
        if (!in_array($data['role'] ?? '', ['admin', 'user', 'viewer'])) {
            $errors['role'] = 'Vai trò không hợp lệ';
        }
        
        return $errors;
    }
}
?>