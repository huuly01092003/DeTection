<?php
$currentPage = 'users';
require_once __DIR__ . '/../components/navbar.php';
renderNavbar($currentPage);

$old = $_SESSION['old'] ?? [];
$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['old'], $_SESSION['errors']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Người Dùng</title>
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
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="form-card">
            <div class="form-header">
                <h3><i class="fas fa-user-plus me-2"></i>Thêm Người Dùng Mới</h3>
            </div>

            <form method="POST" action="users.php?action=store">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                Tên Đăng Nhập <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="username" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" 
                                   value="<?= htmlspecialchars($old['username'] ?? '') ?>" required>
                            <?php if (isset($errors['username'])): ?>
                                <div class="invalid-feedback"><?= $errors['username'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                Mật Khẩu <span class="text-danger">*</span>
                            </label>
                            <input type="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" required>
                            <small class="text-muted">Tối thiểu 6 ký tự</small>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?= $errors['password'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                Họ và Tên <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="full_name" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" 
                                   value="<?= htmlspecialchars($old['full_name'] ?? '') ?>" required>
                            <?php if (isset($errors['full_name'])): ?>
                                <div class="invalid-feedback"><?= $errors['full_name'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                                   value="<?= htmlspecialchars($old['email'] ?? '') ?>">
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
                                <option value="user" <?= ($old['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>User</option>
                                <option value="viewer" <?= ($old['role'] ?? '') === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                                <option value="admin" <?= ($old['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Trạng Thái</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" 
                                       <?= ($old['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    Kích hoạt tài khoản
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                    <a href="users.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-arrow-left me-2"></i>Quay Lại
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Tạo Tài Khoản
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>