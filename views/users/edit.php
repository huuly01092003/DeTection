<?php
$currentPage = 'users';
require_once __DIR__ . '/../components/navbar.php';
renderNavbar($currentPage);

$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);

$permissions = $user['permissions_array'] ?? [];

// Define available permissions
$availablePermissions = [
    'view_reports' => 'Xem báo cáo',
    'export_data' => 'Export dữ liệu',
    'advanced_filters' => 'Sử dụng bộ lọc nâng cao',
    'view_anomaly' => 'Xem phát hiện bất thường',
    'import_data' => 'Import dữ liệu (Admin only)',
    'manage_users' => 'Quản lý người dùng (Admin only)'
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa Người Dùng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; }
        .form-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            padding: 30px;
            margin-top: 30px;
        }
        .form-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .permissions-card {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .permission-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
        }
        .permission-item:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="form-card">
            <div class="form-header">
                <h3><i class="fas fa-user-edit me-2"></i>Sửa Thông Tin Người Dùng</h3>
                <p class="mb-0">Username: <strong><?= htmlspecialchars($user['username']) ?></strong></p>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form method="POST" action="users.php?action=update">
                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                Họ và Tên <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="full_name" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" 
                                   value="<?= htmlspecialchars($user['full_name']) ?>" required>
                            <?php if (isset($errors['full_name'])): ?>
                                <div class="invalid-feedback"><?= $errors['full_name'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?= $errors['email'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                Vai Trò <span class="text-danger">*</span>
                            </label>
                            <select name="role" class="form-select" required>
                                <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                <option value="viewer" <?= $user['role'] === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Trạng Thái</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" 
                                       <?= $user['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    Kích hoạt tài khoản
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                Đổi Mật Khẩu
                                <small class="text-muted">(Để trống nếu không muốn đổi)</small>
                            </label>
                            <input type="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>">
                            <small class="text-muted">Tối thiểu 6 ký tự nếu muốn đổi</small>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?= $errors['password'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Permissions Section -->
                <?php if ($user['role'] !== 'admin'): ?>
                    <div class="permissions-card">
                        <h5 class="mb-3">
                            <i class="fas fa-shield-alt me-2"></i>Phân Quyền Chi Tiết
                        </h5>
                        
                        <?php foreach ($availablePermissions as $permKey => $permLabel): ?>
                            <?php 
                            // Skip admin-only permissions for non-admin roles
                            if (in_array($permKey, ['import_data', 'manage_users'])) continue;
                            ?>
                            <div class="permission-item">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" 
                                           name="permissions[<?= $permKey ?>]" 
                                           id="perm_<?= $permKey ?>"
                                           value="1"
                                           <?= ($permissions[$permKey] ?? false) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="perm_<?= $permKey ?>">
                                        <?= $permLabel ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Admin</strong> có tất cả quyền hạn trong hệ thống
                    </div>
                <?php endif; ?>

                <hr class="my-4">

                <!-- Action Buttons -->
                <div class="row">
                    <div class="col-md-6">
                        <a href="users.php" class="btn btn-secondary btn-lg w-100">
                            <i class="fas fa-arrow-left me-2"></i>Quay Lại
                        </a>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-save me-2"></i>Lưu Thay Đổi
                        </button>
                    </div>
                </div>
            </form>

            <!-- Additional Actions -->
            <div class="mt-4 pt-4 border-top">
                <h5 class="mb-3">Thao Tác Khác</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <button type="button" class="btn btn-warning w-100" onclick="showResetPasswordModal()">
                            <i class="fas fa-key me-2"></i>Đặt Lại Mật Khẩu
                        </button>
                    </div>
                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <div class="col-md-6">
                            <button type="button" class="btn btn-danger w-100" 
                                    onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                <i class="fas fa-trash me-2"></i>Xóa Tài Khoản
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Đặt Lại Mật Khẩu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Nhập mật khẩu mới cho <strong><?= htmlspecialchars($user['username']) ?></strong></p>
                    <input type="password" id="new_password" class="form-control" placeholder="Mật khẩu mới (tối thiểu 6 ký tự)">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-warning" onclick="resetPassword()">Đặt Lại</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showResetPasswordModal() {
            new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
        }

        function resetPassword() {
            const newPassword = document.getElementById('new_password').value;
            
            if (!newPassword || newPassword.length < 6) {
                alert('Mật khẩu phải có ít nhất 6 ký tự');
                return;
            }

            $.post('users.php?action=reset_password', {
                user_id: <?= $user['id'] ?>,
                new_password: newPassword
            }, function(response) {
                if (response.success) {
                    alert(response.message);
                    bootstrap.Modal.getInstance(document.getElementById('resetPasswordModal')).hide();
                    document.getElementById('new_password').value = '';
                } else {
                    alert('Lỗi: ' + response.error);
                }
            }, 'json');
        }

        function confirmDelete(userId, username) {
            if (!confirm(`Bạn có chắc muốn xóa tài khoản "${username}"?\n\nHành động này không thể hoàn tác!`)) return;

            $.post('users.php?action=delete', {
                user_id: userId
            }, function(response) {
                if (response.success) {
                    alert(response.message);
                    window.location.href = 'users.php';
                } else {
                    alert('Lỗi: ' + response.error);
                }
            }, 'json');
        }
    </script>
</body>
</html>