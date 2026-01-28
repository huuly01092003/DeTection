<?php
/**
 * ============================================
 * DANH SÁCH NHÂN VIÊN - FULL AUTH
 * ============================================
 * Updated: Tích hợp đầy đủ phân quyền
 */

// Start session ONCE
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define constants - list.php is in views/dsnv/, need to go up 2 levels
define('PROJECT_ROOT', dirname(dirname(__DIR__)));

// Load dependencies
require_once PROJECT_ROOT . '/middleware/AuthMiddleware.php';
require_once PROJECT_ROOT . '/models/DsnvModel.php';

// Load permission helpers if exists
$permissionHelpersPath = PROJECT_ROOT . '/helpers/permission_helpers.php';
if (file_exists($permissionHelpersPath)) {
    require_once $permissionHelpersPath;
}

// Require login
AuthMiddleware::requireLogin();

// Get current user info
$currentUser = AuthMiddleware::getCurrentUser();
$currentRole = AuthMiddleware::getCurrentRole();

// ============================================
// HANDLE ACTIONS
// ============================================
$action = $_GET['action'] ?? 'list';
$model = new DsnvModel();

if ($action === 'list') {
    // Get filter parameters
    $filters = [
        'bo_phan' => $_GET['bo_phan'] ?? '',
        'chuc_vu' => $_GET['chuc_vu'] ?? '',
        'base_tinh' => $_GET['base_tinh'] ?? '',
        'trang_thai' => $_GET['trang_thai'] ?? '',
        'search' => $_GET['search'] ?? ''
    ];
    
    // Get pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    
    // Get data using existing model methods
    $startTime = microtime(true);
    $data = $model->getAll($filters, $page);
    $totalCount = $model->getFilteredCount($filters);
    $totalCountAll = $model->getTotalCount();
    $activeCount = $model->getActiveCount();
    $perPage = 100; // From model PAGE_SIZE
    $totalPages = ceil($totalCount / $perPage);
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    
    // Show performance notification
    if ($duration < 100) {
        $_SESSION['info'] = "✅ Tải dữ liệu nhanh ({$duration}ms)";
    }
    
    // Get filter options
    $departments = $model->getDepartments();
    $positions = $model->getPositions();
    $provinces = $model->getProvinces();
    $statuses = $model->getStatuses();
    
    // Build filter display
    $filterDisplay = [];
    if (!empty($filters['bo_phan'])) $filterDisplay[] = '🏢 ' . $filters['bo_phan'];
    if (!empty($filters['chuc_vu'])) $filterDisplay[] = '👔 ' . $filters['chuc_vu'];
    if (!empty($filters['base_tinh'])) $filterDisplay[] = '📍 ' . $filters['base_tinh'];
    if (!empty($filters['trang_thai'])) $filterDisplay[] = '📊 ' . $filters['trang_thai'];
    if (!empty($filters['search'])) $filterDisplay[] = '🔍 ' . $filters['search'];
    $filterDisplayText = !empty($filterDisplay) ? implode(' | ', $filterDisplay) : 'Tất cả nhân viên';
    
    // Load navbar
    require_once PROJECT_ROOT . '/views/components/navbar_loader.php';
    
    // Check if admin (for import button)
    $isAdminUser = false;
    if (function_exists('isAdmin')) {
        $isAdminUser = isAdmin();
    } else {
        $isAdminUser = ($currentRole === 'admin' && !AuthMiddleware::isSwitchedRole());
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Danh sách Nhân Viên - DSNV</title>
        
        <!-- CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
        
        <style>
            body { 
                background: #f5f7fa; 
            }
            
            .filter-card, .data-card {
                background: white;
                border-radius: 15px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.05);
                padding: 25px;
                margin-bottom: 25px;
            }
            
            .stat-box {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
                border-radius: 10px;
                text-align: center;
                transition: transform 0.3s;
            }
            
            .stat-box:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            }
            
            .stat-box h2 {
                margin: 0;
                font-size: 2rem;
                font-weight: 700;
            }
            
            .stat-box p {
                margin: 5px 0 0 0;
                opacity: 0.9;
            }
            
            .table thead {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            
            .table thead th {
                border: none;
                font-weight: 600;
                text-transform: uppercase;
                font-size: 0.85rem;
                letter-spacing: 0.5px;
            }
            
            .pagination-info {
                text-align: center;
                margin: 15px 0;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 8px;
                color: #666;
                font-weight: 500;
            }
            
            .badge-active {
                background: #28a745;
                color: white;
                padding: 6px 12px;
                border-radius: 12px;
                font-size: 0.8rem;
                font-weight: 600;
            }
            
            .badge-inactive {
                background: #dc3545;
                color: white;
                padding: 6px 12px;
                border-radius: 12px;
                font-size: 0.8rem;
                font-weight: 600;
            }
            
            .filter-display {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 15px 20px;
                border-radius: 10px;
                margin-bottom: 20px;
                text-align: center;
            }
            
            .action-bar {
                background: white;
                padding: 15px 20px;
                border-radius: 10px;
                margin-bottom: 20px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            }
            
            .btn-action {
                border-radius: 20px;
                padding: 8px 20px;
                font-weight: 600;
                transition: all 0.3s;
            }
            
            .btn-action:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            }
            
            .pagination .page-link {
                color: #667eea;
                border-radius: 8px;
                margin: 0 3px;
                font-weight: 500;
            }
            
            .pagination .page-item.active .page-link {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-color: #667eea;
            }
            
            .info-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
                border-radius: 15px;
                margin-bottom: 25px;
            }
        </style>
    </head>
    <body <?php if (function_exists('getBodyClass')): ?>class="<?= getBodyClass() ?>"<?php endif; ?>>
        
        <?php 
        // Render navbar with breadcrumb
        renderSmartNavbar('dsnv', [
            'breadcrumb' => [
                ['label' => 'Quản Lý DL', 'url' => ''],
                ['label' => 'Danh Sách Nhân Viên', 'url' => '']
            ]
        ]); 
        ?>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="container-fluid mt-3">
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['info'])): ?>
            <div class="container-fluid mt-3">
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i><?= $_SESSION['info'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            <?php unset($_SESSION['info']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="container-fluid mt-3">
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="container-fluid mt-4">
            
            <!-- Action Bar (Admin Only) -->
            <?php if ($isAdminUser): ?>
            <div class="action-bar">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>Quản lý Danh Sách Nhân Viên
                        </h5>
                        <small class="text-muted">
                            <i class="fas fa-database me-1"></i>
                            Tổng: <strong><?= number_format($totalCountAll) ?></strong> nhân viên
                            | <i class="fas fa-check-circle ms-2 me-1"></i>
                            Đang làm: <strong><?= number_format($activeCount) ?></strong>
                        </small>
                    </div>
                    <div>
                        <a href="dsnv.php" class="btn btn-primary btn-action">
                            <i class="fas fa-file-import me-2"></i>Import CSV
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Info Card for All Users -->
            <div class="info-card">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-2">
                            <i class="fas fa-users me-2"></i>Danh Sách Nhân Viên (DSNV)
                        </h5>
                        <p class="mb-0 opacity-90">
                            Danh sách toàn bộ nhân viên trong công ty. 
                            <?php if (!$isAdminUser): ?>
                                Chức năng chỉ xem (view only).
                            <?php else: ?>
                                Sử dụng bộ lọc để tìm kiếm nhanh.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if (function_exists('getRoleBadge')): ?>
                            <div class="fs-4"><?= getRoleBadge() ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Filter Display -->
            <?php if (!empty($filterDisplay)): ?>
            <div class="filter-display">
                <h6 class="mb-0">
                    <i class="fas fa-filter me-2"></i>
                    Đang lọc: <strong><?= htmlspecialchars($filterDisplayText) ?></strong>
                </h6>
            </div>
            <?php endif; ?>

            <!-- Filter Card -->
            <div class="filter-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">
                        <i class="fas fa-filter me-2"></i>Bộ lọc dữ liệu
                    </h5>
                </div>
                
                <form method="GET" action="dsnv.php" id="filterForm">
                    <input type="hidden" name="action" value="list">
                    <input type="hidden" name="page" value="1">
                    
                    <div class="row g-3">
                        <!-- Bộ phận -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-building me-1"></i>Bộ phận
                            </label>
                            <select name="bo_phan" class="form-select">
                                <option value="">-- Tất cả bộ phận --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>" 
                                        <?= ($filters['bo_phan'] === $dept) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Chức vụ -->
                        <div class="col-md-2">
                            <label class="form-label fw-bold">
                                <i class="fas fa-user-tie me-1"></i>Chức vụ
                            </label>
                            <select name="chuc_vu" class="form-select">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($positions as $pos): ?>
                                    <option value="<?= htmlspecialchars($pos) ?>" 
                                        <?= ($filters['chuc_vu'] === $pos) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pos) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Base tỉnh -->
                        <div class="col-md-2">
                            <label class="form-label fw-bold">
                                <i class="fas fa-map-marker-alt me-1"></i>Tỉnh
                            </label>
                            <select name="base_tinh" class="form-select">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($provinces as $prov): ?>
                                    <option value="<?= htmlspecialchars($prov) ?>" 
                                        <?= ($filters['base_tinh'] === $prov) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prov) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Trạng thái -->
                        <div class="col-md-2">
                            <label class="form-label fw-bold">
                                <i class="fas fa-info-circle me-1"></i>Trạng thái
                            </label>
                            <select name="trang_thai" class="form-select">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= htmlspecialchars($status) ?>" 
                                        <?= ($filters['trang_thai'] === $status) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($status) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Tìm kiếm -->
                        <div class="col-md-2">
                            <label class="form-label fw-bold">
                                <i class="fas fa-search me-1"></i>Tìm kiếm
                            </label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Mã NV hoặc tên..." 
                                   value="<?= htmlspecialchars($filters['search']) ?>">
                        </div>

                        <!-- Buttons -->
                        <div class="col-md-1">
                            <label class="form-label fw-bold">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-action">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-12">
                            <a href="dsnv.php?action=list" class="btn btn-secondary btn-action">
                                <i class="fas fa-sync me-2"></i>Làm mới
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Stats Boxes -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-box">
                        <h2><?= number_format($totalCount) ?></h2>
                        <p class="mb-0">
                            <i class="fas fa-filter me-2"></i>Nhân viên (theo bộ lọc)
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box">
                        <h2><?= number_format($totalCountAll) ?></h2>
                        <p class="mb-0">
                            <i class="fas fa-users me-2"></i>Tổng số nhân viên
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box">
                        <h2><?= number_format($activeCount) ?></h2>
                        <p class="mb-0">
                            <i class="fas fa-check-circle me-2"></i>Đang làm việc
                        </p>
                    </div>
                </div>
            </div>

            <!-- Pagination Info -->
            <?php if ($totalCount > 0): ?>
                <div class="pagination-info">
                    <i class="fas fa-list me-2"></i>
                    Trang <strong><?= $page ?></strong> / <strong><?= $totalPages ?></strong> 
                    | Hiển thị <strong><?= count($data) ?></strong> / <strong><?= $totalCount ?></strong> bản ghi
                    | Thời gian tải: <strong><?= $duration ?>ms</strong>
                </div>
            <?php endif; ?>

            <!-- Data Table -->
            <div class="data-card">
                <h5 class="mb-4">
                    <i class="fas fa-list me-2"></i>Danh sách nhân viên
                    <span class="badge bg-primary"><?= number_format(count($data)) ?> nhân viên</span>
                </h5>
                
                <div class="table-responsive">
                    <table id="dsnvTable" class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 50px;">STT</th>
                                <th style="width: 100px;">Mã NV</th>
                                <th style="width: 180px;">Họ tên</th>
                                <th class="text-center" style="width: 80px;">Giới tính</th>
                                <th style="width: 120px;">SĐT</th>
                                <th style="width: 150px;">Bộ phận</th>
                                <th style="width: 120px;">Chức vụ</th>
                                <th style="width: 100px;">Tỉnh</th>
                                <th class="text-center" style="width: 100px;">Trạng thái</th>
                                <th style="width: 120px;">NV Quản lý</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data)): ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                        <h5>Không tìm thấy dữ liệu</h5>
                                        <p>Vui lòng thử lại với bộ lọc khác</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $startNum = ($page - 1) * $perPage + 1;
                                foreach ($data as $i => $row): 
                                ?>
                                    <tr>
                                        <td class="text-center"><?= $startNum + $i ?></td>
                                        <td><strong><?= htmlspecialchars($row['ma_nv']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['ho_ten']) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($row['gioi_tinh']) ?></td>
                                        <td><?= htmlspecialchars($row['sdt_ca_nhan']) ?></td>
                                        <td><?= htmlspecialchars($row['bo_phan']) ?></td>
                                        <td><?= htmlspecialchars($row['chuc_vu']) ?></td>
                                        <td><?= htmlspecialchars($row['base_tinh']) ?></td>
                                        <td class="text-center">
    <?php 
    $status = $row['trang_thai'];
    // Xác định màu sắc dựa trên giá trị thực tế trong DB
    if ($status === 'Chính thức' || $status === 'Thử việc') {
        echo '<span class="badge badge-active"><i class="fas fa-check"></i> ' . htmlspecialchars($status) . '</span>';
    } elseif ($status === 'Nghỉ thai sản') {
        echo '<span class="badge bg-warning text-dark"><i class="fas fa-baby"></i> Thai sản</span>';
    } else {
        // Mặc định cho 'Nghỉ việc' hoặc các trạng thái khác
        echo '<span class="badge badge-inactive"><i class="fas fa-times"></i> ' . htmlspecialchars($status) . '</span>';
    }
    ?>
</td>
                                        <td>
                                            <?php if (!empty($row['ten_nv_ql'])): ?>
                                                <small><?= htmlspecialchars($row['ten_nv_ql']) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">--</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?action=list&page=1<?= buildFilterQuery($filters) ?>">
                                    <i class="fas fa-step-backward"></i> Đầu
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?action=list&page=<?= $page - 1 ?><?= buildFilterQuery($filters) ?>">
                                    <i class="fas fa-chevron-left"></i> Trước
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        
                        if ($start > 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>

                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?action=list&page=<?= $i ?><?= buildFilterQuery($filters) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end < $totalPages): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?action=list&page=<?= $page + 1 ?><?= buildFilterQuery($filters) ?>">
                                    Tiếp <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?action=list&page=<?= $totalPages ?><?= buildFilterQuery($filters) ?>">
                                    Cuối <i class="fas fa-step-forward"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>

        <!-- Scripts -->
        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Build filter query string for pagination
 */
function buildFilterQuery($filters) {
    $params = [];
    foreach ($filters as $key => $value) {
        if ($value !== '') {
            $params[] = $key . '=' . urlencode($value);
        }
    }
    return $params ? '&' . implode('&', $params) : '';
}

// Default redirect to list
header('Location: dsnv.php?action=list');
exit;