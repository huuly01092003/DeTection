<?php
/**
 * Enhanced Navbar with Authentication, User Info & Role-Based Features
 * FIXED: Viewer/User can see Reports, Anomaly, Data Management (view only)
 */

function renderAuthNavbar($currentPage = '', $additionalInfo = []) {
    require_once __DIR__ . '/../../middleware/AuthMiddleware.php';
    
    $currentUser = AuthMiddleware::getCurrentUser();
    $currentRole = AuthMiddleware::getCurrentRole();
    $isAdmin = $currentRole === 'admin' && !AuthMiddleware::isSwitchedRole();
    $isSwitched = AuthMiddleware::isSwitchedRole();
    $isViewer = $currentRole === 'viewer';
    
    $currentPath = $_SERVER['PHP_SELF'];
    $baseName = basename($currentPath, '.php');
?>
<nav class="navbar navbar-expand-lg navbar-custom navbar-dark sticky-top shadow-lg">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <i class="fas fa-chart-line me-2"></i>
            <div>
                <strong style="font-size: 1.2rem;">HỆ THỐNG BÁO CÁO</strong>
                <div style="font-size: 0.7rem; opacity: 0.9;">Phân tích doanh số & phát hiện bất thường</div>
            </div>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="mainNavbar">
            <!-- Main Navigation -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                
                <!-- Reports Section (ALWAYS VISIBLE FOR ALL ROLES) -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($baseName, ['report', 'nhanvien_report', 'nhanvien_kpi']) ? 'active' : '' ?>" 
                       href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-chart-bar me-1"></i>Báo Cáo
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        
                        <li>
                            <a class="dropdown-item <?= $baseName === 'nhanvien_report' ? 'active' : '' ?>" 
                               href="nhanvien_report.php">
                                <i class="fas fa-user-tie me-2"></i>Doanh Số Nhân Viên
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= $baseName === 'nhanvien_kpi' ? 'active' : '' ?>" 
                               href="nhanvien_kpi.php">
                                <i class="fas fa-chart-line me-2"></i>KPI Nhân Viên
                            </a>
                        </li>
                    </ul>
                </li>

                

                <!-- Import Data (ADMIN ONLY) -->
                <?php if ($isAdmin): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($baseName, ['index', 'dskh', 'gkhl']) ? 'active' : '' ?>" 
                       href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-upload me-1"></i>Import Dữ Liệu
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li>
                            <a class="dropdown-item <?= $baseName === 'import' ? 'active' : '' ?>" href="import.php">
                                <i class="fas fa-file-csv me-2"></i>Order Detail
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item <?= $baseName === 'dskh' ? 'active' : '' ?>" href="dskh.php">
                                <i class="fas fa-users me-2"></i>Danh Sách Khách Hàng
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= $baseName === 'gkhl' ? 'active' : '' ?>" href="gkhl.php">
                                <i class="fas fa-handshake me-2"></i>Gắn Kết Hoa Linh
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= $baseName === 'dsnv' ? 'active' : '' ?>" href="dsnv.php">
                                <i class="fas fa-file-csv me-2"></i>Danh sách nhân viên
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Data Management (VISIBLE FOR ALL, but content differs) -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-database me-1"></i>Quản Lý DL
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <!-- Admin: User Management -->
                        <?php if ($isAdmin): ?>
                        <li>
                            <a class="dropdown-item <?= $baseName === 'users' ? 'active' : '' ?>" href="users.php">
                                <i class="fas fa-users-cog me-2"></i>Người Dùng
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        
                        <!-- All roles: View data lists -->
                        <li>
                            <a class="dropdown-item" href="dskh.php?action=list">
                                <i class="fas fa-list me-2"></i>Xem DSKH
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="gkhl.php?action=list">
                                <i class="fas fa-list me-2"></i>Xem GKHL
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="dsnv.php?action=list">
                                <i class="fas fa-list me-2"></i>Xem dsnv
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>

            <!-- Right Side: User Info & Actions -->
            <ul class="navbar-nav">
                <!-- Period Display (if provided) -->
                <?php if (!empty($additionalInfo['period'])): ?>
                <li class="nav-item">
                    <span class="navbar-text me-3 badge bg-light text-dark px-3 py-2">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <strong><?= htmlspecialchars($additionalInfo['period']) ?></strong>
                    </span>
                </li>
                <?php endif; ?>

                <!-- Role Switcher Badge (Admin only) -->
                <?php if ($isAdmin || $isSwitched): ?>
                <li class="nav-item dropdown me-2">
                    <a class="nav-link dropdown-toggle px-3" href="#" role="button" data-bs-toggle="dropdown" 
                       style="background: rgba(255,255,255,0.1); border-radius: 20px;">
                        <i class="fas fa-user-shield me-1"></i>
                        <?php if ($isSwitched): ?>
                            <span class="badge bg-warning text-dark">
                                Xem: <?= ucfirst($currentRole) ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger">Admin</span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
                        <?php if ($isSwitched): ?>
                            <li class="dropdown-item-text">
                                <small class="text-warning">
                                    <i class="fas fa-eye me-1"></i>
                                    Đang xem giao diện: <strong><?= ucfirst($currentRole) ?></strong>
                                </small>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="#" onclick="switchBackToAdmin(); return false;">
                                    <i class="fas fa-undo me-2"></i>Quay Lại Admin Mode
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="dropdown-item-text">
                                <small><strong>Chuyển sang xem giao diện:</strong></small>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="#" onclick="switchRole('user'); return false;">
                                    <i class="fas fa-user me-2"></i>User View
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" onclick="switchRole('viewer'); return false;">
                                    <i class="fas fa-eye me-2"></i>Viewer View
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- User Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center px-3" href="#" role="button" data-bs-toggle="dropdown"
                       style="background: rgba(255,255,255,0.15); border-radius: 20px;">
                        <div class="user-avatar me-2">
                            <i class="fas fa-user-circle fa-lg"></i>
                        </div>
                        <div class="user-info d-none d-lg-block">
                            <div style="font-size: 0.9rem; font-weight: 600; line-height: 1.2;">
                                <?= htmlspecialchars($currentUser['full_name']) ?>
                            </div>
                            <div style="font-size: 0.7rem; opacity: 0.8;">
                                @<?= htmlspecialchars($currentUser['username']) ?>
                            </div>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" style="min-width: 280px;">
                        <!-- User Info Header -->
                        <li class="dropdown-item-text px-3 py-2" style="background: rgba(255,255,255,0.1);">
                            <div class="d-flex align-items-center mb-2">
                                <div class="user-avatar-large me-3">
                                    <i class="fas fa-user-circle fa-3x" style="color: #667eea;"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 600; font-size: 1rem;">
                                        <?= htmlspecialchars($currentUser['full_name']) ?>
                                    </div>
                                    <div style="font-size: 0.85rem; opacity: 0.8;">
                                        @<?= htmlspecialchars($currentUser['username']) ?>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <span class="badge <?= $currentRole === 'admin' ? 'bg-danger' : ($currentRole === 'user' ? 'bg-success' : 'bg-secondary') ?>">
                                    <i class="fas fa-<?= $currentRole === 'admin' ? 'crown' : ($currentRole === 'user' ? 'user' : 'eye') ?> me-1"></i>
                                    <?= $currentRole === 'admin' ? 'Quản trị viên' : ($currentRole === 'user' ? 'Người dùng' : 'Người xem') ?>
                                </span>
                                <?php if ($isSwitched): ?>
                                    <span class="badge bg-warning text-dark ms-1">
                                        <i class="fas fa-exchange-alt me-1"></i>Switched
                                    </span>
                                <?php endif; ?>
                            </div>
                        </li>
                        
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Profile & Settings -->
                        <li>
                            <a class="dropdown-item" href="users.php">
                                <i class="fas fa-user-edit me-2 text-info"></i>Thông Tin Cá Nhân
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="users.php">
                                <i class="fas fa-key me-2 text-warning"></i>Đổi Mật Khẩu
                            </a>
                        </li>
                        
                        <!-- Admin Panel (Admin only) -->
                        <?php if ($isAdmin): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="users.php">
                                <i class="fas fa-users-cog me-2 text-primary"></i>Quản Lý Người Dùng
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="system.php">
                                <i class="fas fa-database me-2 text-success"></i>Quản Lý Dữ Liệu
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Help -->
                        <li>
                            <a class="dropdown-item" href="#" onclick="showHelp(); return false;">
                                <i class="fas fa-question-circle me-2 text-info"></i>Trợ Giúp
                            </a>
                        </li>
                        
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Logout -->
                        <li>
                            <a class="dropdown-item text-danger fw-bold" href="logout.php" 
                               onclick="return confirm('Bạn có chắc muốn đăng xuất?');">
                                <i class="fas fa-sign-out-alt me-2"></i>Đăng Xuất
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Breadcrumb (optional) -->
<?php if (!empty($additionalInfo['breadcrumb'])): ?>
<div class="container-fluid mt-2">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-white rounded shadow-sm px-3 py-2">
            <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home"></i></a></li>
            <?php foreach ($additionalInfo['breadcrumb'] as $crumb): ?>
                <?php if (!empty($crumb['url'])): ?>
                    <li class="breadcrumb-item">
                        <a href="<?= htmlspecialchars($crumb['url']) ?>">
                            <?= htmlspecialchars($crumb['label']) ?>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="breadcrumb-item active">
                        <?= htmlspecialchars($crumb['label']) ?>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ol>
    </nav>
</div>
<?php endif; ?>

<style>
.navbar-custom {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 0.75rem 1rem;
}

.navbar-custom .nav-link {
    color: rgba(255,255,255,0.9);
    font-weight: 500;
    transition: all 0.3s ease;
    border-radius: 8px;
    margin: 0 3px;
    padding: 8px 15px;
}

.navbar-custom .nav-link:hover {
    background: rgba(255,255,255,0.15);
    color: white;
    transform: translateY(-1px);
}

.navbar-custom .nav-link.active {
    background: rgba(255,255,255,0.25);
    color: white;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.navbar-custom .dropdown-menu {
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    border: none;
    margin-top: 8px;
}

.navbar-custom .dropdown-item {
    padding: 10px 20px;
    transition: all 0.2s;
    border-radius: 8px;
    margin: 2px 8px;
}

.navbar-custom .dropdown-item:hover {
    background: rgba(102, 126, 234, 0.2);
    padding-left: 25px;
    transform: translateX(3px);
}

.navbar-custom .dropdown-item.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
}

.user-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
}

.navbar-brand strong {
    letter-spacing: 0.5px;
}

.breadcrumb {
    background: white;
    margin-bottom: 0;
}

.breadcrumb-item + .breadcrumb-item::before {
    content: "›";
    font-size: 1.2rem;
}

/* Export button visibility for viewers */
.export-action {
    <?php if ($isViewer): ?>
    display: none !important;
    <?php endif; ?>
}
</style>

<script>
// Role switching functions
function switchRole(role) {
    if (!confirm(`Bạn muốn xem giao diện dưới quyền "${role}"?`)) return;
    
    fetch('ajax/role_switch.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=switch&role=${role}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Lỗi: ' + data.error);
        }
    });
}

function switchBackToAdmin() {
    if (!confirm('Quay lại chế độ Admin?')) return;
    
    fetch('ajax/role_switch.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=back'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Lỗi: ' + data.error);
        }
    });
}

function showHelp() {
    const helpContent = `
╔═══════════════════════════════════════════╗
║     📚 HƯỚNG DẪN SỬ DỤNG HỆ THỐNG        ║
╚═══════════════════════════════════════════╝

📊 BÁO CÁO:
   • Báo Cáo Khách Hàng: Xem chi tiết doanh số
   • Doanh Số Nhân Viên: Theo dõi hiệu suất
   • KPI Nhân Viên: Phân tích chỉ tiêu

⚠️ PHÁT HIỆN BẤT THƯỜNG:
   • Tìm các giao dịch khả nghi
   • Phân tích hành vi bất thường

<?php if ($isAdmin): ?>
🔧 QUẢN TRỊ (Admin):
   • Import dữ liệu CSV
   • Quản lý người dùng
   • Phân quyền hệ thống
<?php endif; ?>

🗄️ QUẢN LÝ DỮ LIỆU:
   • Xem danh sách khách hàng
   • Xem danh sách GKHL
   <?php if (!$isViewer): ?>• Export dữ liệu (User/Admin)<?php endif; ?>

📞 HỖ TRỢ:
   Email: support@example.com
   Hotline: 1900-xxxx
    `;
    
    alert(helpContent);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey) {
        switch(e.key) {
            case 'h': e.preventDefault(); window.location.href = 'dashboard.php'; break;
            case 'r': e.preventDefault(); window.location.href = 'report.php'; break;
            case 'd': e.preventDefault(); window.location.href = 'anomaly.php'; break;
            case '/': e.preventDefault(); showHelp(); break;
            <?php if ($isAdmin): ?>
            case 'u': e.preventDefault(); window.location.href = 'users.php'; break;
            <?php endif; ?>
        }
    }
});

// Add viewer class to body for global CSS control
<?php if ($isViewer): ?>
document.body.classList.add('viewer-mode');
<?php endif; ?>
</script>

<?php
}
?>