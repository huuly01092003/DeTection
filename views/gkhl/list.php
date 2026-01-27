<?php
/**
 * ============================================
 * DANH SÁCH GẮN KẾT HOA LINH - FULL AUTH
 * ============================================
 * Updated: Tích hợp đầy đủ phân quyền
 */

// Start session ONCE
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define constants - list.php is in views/gkhl/, need to go up 2 levels
define('PROJECT_ROOT', dirname(dirname(__DIR__)));

// Load dependencies
require_once PROJECT_ROOT . '/middleware/AuthMiddleware.php';
require_once PROJECT_ROOT . '/models/GkhlModel.php';

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
$model = new GkhlModel();
if ($action === 'list') {
    // Get filter parameters
    $filters = [
        'ma_nvbh' => $_GET['ma_nvbh'] ?? '',
        'ma_kh_dms' => $_GET['ma_kh_dms'] ?? '',
        'khop_sdt' => $_GET['khop_sdt'] ?? '',
        'nam_sinh' => $_GET['nam_sinh'] ?? ''
    ];
    
    // Get pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    
    // Get data using existing model methods
    $startTime = microtime(true);
    $data = $model->getAll($filters, $page);
    $totalCount = $model->getFilteredCount($filters);
    $totalCountAll = $model->getTotalCount();
    $phoneMatchCount = $model->getPhoneMatchCount();
    $perPage = 100; // From model PAGE_SIZE
    $totalPages = ceil($totalCount / $perPage);
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    
    // Show performance notification
    if ($duration < 100) {
        $_SESSION['info'] = "✅ Tải dữ liệu nhanh ({$duration}ms)";
    }
    
    // Get filter options
    $saleStaff = $model->getSaleStaff();
    $birthYears = $model->getBirthYears();
    
    // Build filter display
    $filterDisplay = [];
    if (!empty($filters['ma_nvbh'])) {
        $staffName = '';
        foreach ($saleStaff as $staff) {
            if ($staff['MaNVBH'] === $filters['ma_nvbh']) {
                $staffName = $staff['TenNVBH'];
                break;
            }
        }
        $filterDisplay[] = '👤 ' . $filters['ma_nvbh'] . ($staffName ? ' - ' . $staffName : '');
    }
    if (!empty($filters['ma_kh_dms'])) $filterDisplay[] = '🔍 Mã: ' . $filters['ma_kh_dms'];
    if ($filters['khop_sdt'] === '1') $filterDisplay[] = '✅ Đã khớp SDT';
    if ($filters['khop_sdt'] === '0') $filterDisplay[] = '❌ Chưa khớp SDT';
    if (!empty($filters['nam_sinh'])) $filterDisplay[] = '🎂 Năm sinh: ' . $filters['nam_sinh'];
    $filterDisplayText = !empty($filterDisplay) ? implode(' | ', $filterDisplay) : 'Tất cả khách hàng GKHL';
    
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
        <title>Danh sách Gắn kết Hoa Linh - GKHL</title>
        
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
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                color: white;
                padding: 20px;
                border-radius: 10px;
                text-align: center;
                transition: transform 0.3s;
            }
            
            .stat-box:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 25px rgba(240, 147, 251, 0.3);
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
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            
            .badge-yes {
                background: #28a745;
                color: white;
                padding: 6px 12px;
                border-radius: 12px;
                font-size: 0.8rem;
                font-weight: 600;
            }
            
            .badge-no {
                background: #dc3545;
                color: white;
                padding: 6px 12px;
                border-radius: 12px;
                font-size: 0.8rem;
                font-weight: 600;
            }
            
            .badge-null {
                background: #6c757d;
                color: white;
                padding: 6px 12px;
                border-radius: 12px;
                font-size: 0.8rem;
                font-weight: 600;
            }
            
            .filter-display {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
                color: #f093fb;
                border-radius: 8px;
                margin: 0 3px;
                font-weight: 500;
            }
            
            .pagination .page-item.active .page-link {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                border-color: #f093fb;
            }
            
            .info-card {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
        renderSmartNavbar('gkhl', [
            'breadcrumb' => [
                ['label' => 'Quản Lý DL', 'url' => ''],
                ['label' => 'Gắn Kết Hoa Linh', 'url' => '']
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
                            <i class="fas fa-handshake me-2"></i>Quản lý Gắn kết Hoa Linh
                        </h5>
                        <small class="text-muted">
                            <i class="fas fa-database me-1"></i>
                            Tổng: <strong><?= number_format($totalCountAll) ?></strong> khách hàng GKHL
                            | <i class="fas fa-check-circle ms-2 me-1"></i>
                            Đã khớp SDT: <strong><?= number_format($phoneMatchCount) ?></strong>
                        </small>
                    </div>
                    
                </div>
            </div>
            <?php endif; ?>

            <!-- Info Card for All Users -->
            <div class="info-card">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-2">
                            <i class="fas fa-handshake me-2"></i>Gắn kết Hoa Linh (GKHL)
                        </h5>
                        <p class="mb-0 opacity-90">
                            Danh sách khách hàng tham gia chương trình Gắn kết Hoa Linh. 
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
                
                <form method="GET" action="gkhl.php" id="filterForm">
                    <input type="hidden" name="action" value="list">
                    <input type="hidden" name="page" value="1">
                    
                    <div class="row g-3">
                        <!-- Mã NVBH -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-user-tie me-1"></i>Nhân viên bán hàng
                            </label>
                            <select name="ma_nvbh" class="form-select">
                                <option value="">-- Tất cả nhân viên --</option>
                                <?php foreach ($saleStaff as $staff): ?>
                                    <option value="<?= htmlspecialchars($staff['MaNVBH']) ?>" 
                                        <?= ($filters['ma_nvbh'] === $staff['MaNVBH']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($staff['MaNVBH']) ?> - <?= htmlspecialchars($staff['TenNVBH']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Năm sinh -->
                        <div class="col-md-2">
                            <label class="form-label fw-bold">
                                <i class="fas fa-birthday-cake me-1"></i>Năm sinh
                            </label>
                            <select name="nam_sinh" class="form-select">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($birthYears as $year): ?>
                                    <option value="<?= $year ?>" 
                                        <?= ($filters['nam_sinh'] == $year) ? 'selected' : '' ?>>
                                        <?= $year ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Khớp SDT -->
                        <div class="col-md-2">
                            <label class="form-label fw-bold">
                                <i class="fas fa-phone me-1"></i>Khớp SDT
                            </label>
                            <select name="khop_sdt" class="form-select">
                                <option value="">-- Tất cả --</option>
                                <option value="1" <?= ($filters['khop_sdt'] === '1') ? 'selected' : '' ?>>
                                    ✅ Đã khớp (Y)
                                </option>
                                <option value="0" <?= ($filters['khop_sdt'] === '0') ? 'selected' : '' ?>>
                                    ❌ Chưa khớp (N)
                                </option>
                            </select>
                        </div>

                        <!-- Mã KH DMS -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-search me-1"></i>Mã KH DMS
                            </label>
                            <input type="text" name="ma_kh_dms" class="form-control" 
                                   placeholder="Nhập mã khách hàng DMS..." 
                                   value="<?= htmlspecialchars($filters['ma_kh_dms']) ?>">
                        </div>

                        <!-- Buttons -->
                        <div class="col-md-2">
                            <label class="form-label fw-bold">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-action">
                                    <i class="fas fa-search me-2"></i>Tìm kiếm
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-12">
                            <a href="gkhl.php?action=list" class="btn btn-secondary btn-action">
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
                            <i class="fas fa-filter me-2"></i>KH GKHL (theo bộ lọc)
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box">
                        <h2><?= number_format($totalCountAll) ?></h2>
                        <p class="mb-0">
                            <i class="fas fa-handshake me-2"></i>Tổng số KH GKHL
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box">
                        <h2><?= number_format($phoneMatchCount) ?></h2>
                        <p class="mb-0">
                            <i class="fas fa-check-circle me-2"></i>Đã khớp SDT
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
                    <i class="fas fa-list me-2"></i>Danh sách gắn kết
                    <span class="badge bg-primary"><?= number_format(count($data)) ?> khách hàng</span>
                </h5>
                
                <div class="table-responsive">
                    <table id="gkhlTable" class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 50px;">STT</th>
                                <th style="width: 120px;">Mã KH DMS</th>
                                <th style="width: 200px;">Tên quầy</th>
                                <th style="width: 180px;">Tên chủ cửa hàng</th>
                                <th class="text-center" style="width: 100px;">Ngày sinh</th>
                                <th style="width: 120px;">SDT Zalo</th>
                                <th style="width: 120px;">SDT định danh</th>
                                <th class="text-center" style="width: 100px;">Khớp SDT</th>
                                <th style="width: 100px;">Mã NVBH</th>
                                <th style="width: 150px;">Tên NVBH</th>
                                <th style="width: 150px;">ĐK Chương trình</th>
                                <th style="width: 120px;">ĐK Mục DS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data)): ?>
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-5">
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
                                        <td><strong><?= htmlspecialchars($row['MaKHDMS']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['TenQuay']) ?></td>
                                        <td><?= htmlspecialchars($row['TenChuCuaHang']) ?></td>
                                        <td class="text-center">
                                            <?php if ($row['NgaySinh'] && $row['ThangSinh'] && $row['NamSinh']): ?>
                                                <?= sprintf('%02d/%02d/%04d', $row['NgaySinh'], $row['ThangSinh'], $row['NamSinh']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">--</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['SDTZalo']) ?></td>
                                        <td><?= htmlspecialchars($row['SDTDaDinhDanh']) ?></td>
                                        <td class="text-center">
                                            <?php 
                                            $khopSdt = $row['KhopSDT'];
                                            if ($khopSdt == 'Y'): ?>
                                                <span class="badge badge-yes">
                                                    <i class="fas fa-check"></i> Đã khớp
                                                </span>
                                            <?php elseif ($khopSdt == 'N'): ?>
                                                <span class="badge badge-no">
                                                    <i class="fas fa-times"></i> Chưa khớp
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-null">
                                                    <i class="fas fa-question"></i> Chưa rõ
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['MaNVBH']) ?></td>
                                        <td><?= htmlspecialchars($row['TenNVBH']) ?></td>
                                        <td>
                                            <?php if (!empty($row['DangKyChuongTrinh'])): ?>
                                                <span class="badge bg-info"><?= htmlspecialchars($row['DangKyChuongTrinh']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">--</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['DangKyMucDoanhSo'])): ?>
                                                <span class="badge bg-warning text-dark"><?= htmlspecialchars($row['DangKyMucDoanhSo']) ?></span>
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
        
        <script>
            $(document).ready(function() {
                // Clear district selection when province changes
                $('#tinhSelect').on('change', function() {
                    const selectedTinh = $(this).val();
                    if (selectedTinh) {
                        $('#quanHuyenSelect').val('');
                    }
                });
            });
        </script>
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
header('Location: dskh.php?action=list');
exit;