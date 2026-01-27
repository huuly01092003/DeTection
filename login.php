<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Nếu đã đăng nhập, chuyển về dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/models/UserModel.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    echo "<!-- DEBUG: Login attempt for username: " . htmlspecialchars($username) . " -->";
    
    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin';
    } else {
        try {
            $userModel = new UserModel();
            $user = $userModel->authenticate($username, $password);
            
            if ($user) {
                // Lưu thông tin vào session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                
                // Debug
                echo "<!-- DEBUG: Login successful! User ID: " . $user['id'] . " -->";
                
                // Redirect về trang được yêu cầu hoặc dashboard
                $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
                unset($_SESSION['redirect_after_login']);
                
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = 'Tên đăng nhập hoặc mật khẩu không đúng';
                echo "<!-- DEBUG: Authentication failed -->";
            }
        } catch (Exception $e) {
            $error = 'Lỗi hệ thống: ' . $e->getMessage();
            echo "<!-- DEBUG ERROR: " . $e->getMessage() . " -->";
        }
    }
}

// Lấy message từ session
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - Hệ Thống Báo Cáo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            display: flex;
        }
        
        .login-left {
            flex: 1;
            padding: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-left h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .login-left p {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .login-left .feature-list {
            margin-top: 30px;
        }
        
        .login-left .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .login-left .feature-item i {
            font-size: 1.5rem;
            margin-right: 15px;
        }
        
        .login-right {
            flex: 1;
            padding: 50px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-header h3 {
            color: #333;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
        }
        
        .form-floating {
            margin-bottom: 20px;
        }
        
        .form-floating input {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-floating input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 15px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .demo-accounts {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        
        .demo-accounts h6 {
            color: #667eea;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .demo-account {
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .demo-account:last-child {
            margin-bottom: 0;
        }
        
        .demo-account strong {
            color: #667eea;
        }
        
        .demo-account code {
            background: #e7f3ff;
            padding: 4px 8px;
            border-radius: 5px;
            color: #333;
        }
        
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
            }
            
            .login-left {
                padding: 30px;
            }
            
            .login-right {
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Side - Branding -->
        <div class="login-left">
            <div>
                <i class="fas fa-chart-line fa-3x mb-4"></i>
                <h2>Hệ Thống Báo Cáo</h2>
                <p>Quản lý và phân tích dữ liệu kinh doanh một cách hiệu quả</p>
                
                <div class="feature-list">
                    <div class="feature-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Báo cáo chi tiết và trực quan</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Bảo mật cao với phân quyền</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-bolt"></i>
                        <span>Hiệu suất xử lý nhanh chóng</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-mobile-alt"></i>
                        <span>Tương thích mọi thiết bị</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Side - Login Form -->
        <div class="login-right">
            <div class="login-header">
                <h3>Đăng Nhập</h3>
                <p>Nhập thông tin tài khoản của bạn</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-floating">
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="Tên đăng nhập" required autofocus value="admin">
                    <label for="username">
                        <i class="fas fa-user me-2"></i>Tên đăng nhập
                    </label>
                </div>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Mật khẩu" required value="admin123">
                    <label for="password">
                        <i class="fas fa-lock me-2"></i>Mật khẩu
                    </label>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Đăng Nhập
                </button>
            </form>
            
            <!-- Demo Accounts -->
            <div class="demo-accounts">
                <h6><i class="fas fa-info-circle me-2"></i>Tài Khoản Demo</h6>
                <div class="demo-account">
                    <div>
                        <strong>Admin:</strong> admin
                    </div>
                    <code>admin123</code>
                </div>
            </div>
            
            <!-- Debug Info -->
            <div class="mt-3">
                <small class="text-muted">
                    Nếu không đăng nhập được, vui lòng chạy file <code>create_admin.php</code> để tạo tài khoản admin.
                </small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>