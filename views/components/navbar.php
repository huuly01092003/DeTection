<?php
/**
 * Enhanced Global Navigation Bar
 * File: views/components/navbar.php
 * Usage: Include at the top of every page
 */

function renderEnhancedNavbar($currentPage = '', $additionalInfo = []) {
    // Get current path for active state detection
    $currentPath = $_SERVER['PHP_SELF'];
    $baseName = basename($currentPath, '.php');
    
    // Load auth middleware
    require_once __DIR__ . '/../../middleware/AuthMiddleware.php';
    $currentUser = AuthMiddleware::getCurrentUser();
    $isAdmin = AuthMiddleware::getCurrentRole() === 'admin' && !AuthMiddleware::isSwitchedRole();
    $isSwitched = AuthMiddleware::isSwitchedRole();
?>
<nav class="navbar navbar-expand-lg navbar-custom navbar-dark sticky-top shadow-lg">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center" href="index.php">
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
            <!-- Main Navigation Links -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <!-- Import Section -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($baseName, ['import', 'dskh', 'gkhl']) ? 'active' : '' ?>" 
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
                            <a class="dropdown-item <?= $baseName === 'dskh' && !isset($_GET['action']) ? 'active' : '' ?>" 
                               href="dskh.php">
                                <i class="fas fa-users me-2"></i>Danh Sách Khách Hàng
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= $baseName === 'gkhl' && !isset($_GET['action']) ? 'active' : '' ?>" 
                               href="gkhl.php">
                                <i class="fas fa-handshake me-2"></i>Gắn Kết Hoa Linh
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Reports Section -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($baseName, ['report', 'nhanvien_report']) ? 'active' : '' ?>" 
                       href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-chart-bar me-1"></i>Báo Cáo
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li>
                            <a class="dropdown-item <?= $baseName === 'report' ? 'active' : '' ?>" href="report.php">
                                <i class="fas fa-users me-2"></i>Báo Cáo Khách Hàng
                            </a>
                        </li>
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

                <!-- Detection Section -->
                <li class="nav-item">
                    <a class="nav-link <?= $baseName === 'anomaly' ? 'active' : '' ?>" href="anomaly.php">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <span class="badge bg-warning text-dark ms-1">Phát Hiện BT</span>
                    </a>
                </li>

                <!-- Data Management -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-database me-1"></i>Quản Lý DL
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
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
                        <li><hr class="dropdown-divider"></li>
                        
                    </ul>
                </li>
            </ul>

            <!-- Right Side Info & Actions -->
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

                <!-- Quick Stats (if provided) -->
                <?php if (!empty($additionalInfo['stats'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-chart-pie me-1"></i>
                        <span class="badge bg-info">Thống Kê</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" style="min-width: 250px;">
                        <?php foreach ($additionalInfo['stats'] as $label => $value): ?>
                        <li class="dropdown-item-text">
                            <div class="d-flex justify-content-between">
                                <span><?= htmlspecialchars($label) ?>:</span>
                                <strong><?= htmlspecialchars($value) ?></strong>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Help & Settings -->
                <li class="nav-item dropdown">
                    <a class="nav-link" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cog"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
                        <li>
                            <a class="dropdown-item" href="#" onclick="showHelp(); return false;">
                                <i class="fas fa-question-circle me-2"></i>Trợ Giúp
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="showShortcuts(); return false;">
                                <i class="fas fa-keyboard me-2"></i>Phím Tắt
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="index.php">
                                <i class="fas fa-home me-2"></i>Trang Chủ
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Breadcrumb (optional, shows current location) -->
<?php if (!empty($additionalInfo['breadcrumb'])): ?>
<div class="container-fluid mt-2">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-white rounded shadow-sm px-3 py-2">
            <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i></a></li>
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

.navbar-brand strong {
    letter-spacing: 0.5px;
}

.navbar-text.badge {
    font-size: 0.9rem;
    border-radius: 20px;
}

.breadcrumb {
    background: white;
    margin-bottom: 0;
}

.breadcrumb-item + .breadcrumb-item::before {
    content: "›";
    font-size: 1.2rem;
}
</style>

<script>
function showHelp() {
    alert('📚 HƯỚNG DẪN SỬ DỤNG:\n\n' +
          '1. Import Dữ Liệu: Upload file CSV\n' +
          '2. Báo Cáo: Xem phân tích khách hàng & nhân viên\n' +
          '3. Phát Hiện BT: Tìm hành vi bất thường\n' +
          '4. Quản Lý DL: Xem & export dữ liệu\n\n' +
          'Liên hệ hỗ trợ: support@example.com');
}

function showShortcuts() {
    alert('⌨️ PHÍM TẮT:\n\n' +
          'Ctrl + H: Trang chủ\n' +
          'Ctrl + R: Báo cáo\n' +
          'Ctrl + D: Phát hiện bất thường\n' +
          'Ctrl + /: Trợ giúp');
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey) {
        switch(e.key) {
            case 'h': e.preventDefault(); window.location.href = 'index.php'; break;
            case 'r': e.preventDefault(); window.location.href = 'report.php'; break;
            case 'd': e.preventDefault(); window.location.href = 'anomaly.php'; break;
            case 'e': e.preventDefault(); window.location.href = 'export.php'; break;
            case '/': e.preventDefault(); showHelp(); break;
        }
    }
});
</script>

<?php
}

// Helper function to get current page title
function getCurrentPageTitle($currentPage) {
    $titles = [
        'index' => 'Import Order Detail',
        'dskh' => 'Danh Sách Khách Hàng',
        'gkhl' => 'Gắn Kết Hoa Linh',
        'report' => 'Báo Cáo Khách Hàng',
        'nhanvien_report' => 'Doanh Số Nhân Viên',
        'nhanvien_kpi' => 'KPI Nhân Viên',
        'anomaly' => 'Phát Hiện Bất Thường',
        'export' => 'Export Dữ Liệu'
    ];
    return $titles[$currentPage] ?? 'Hệ Thống Báo Cáo';
}

/**
 * BACKWARD COMPATIBILITY WRAPPER
 * Old function name for existing code
 */
function renderNavbar($currentPage = '', $thangNam = '') {
    // Convert old parameters to new format
    $additionalInfo = [];
    
    if (!empty($thangNam)) {
        $additionalInfo['period'] = $thangNam;
    }
    
    // Call the new enhanced function
    renderEnhancedNavbar($currentPage, $additionalInfo);
}


?>