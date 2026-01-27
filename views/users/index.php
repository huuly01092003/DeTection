<?php
/**
 * User Management Index View - Enhanced with Statistics & Table Details
 * File: views/users/index.php
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/middleware/AuthMiddleware.php';
$currentUser = AuthMiddleware::getCurrentUser();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Người Dùng - <?= htmlspecialchars($currentUser['full_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .filter-card, .data-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .badge-admin { background: #dc3545; }
        .badge-user { background: #28a745; }
        .badge-viewer { background: #6c757d; }
        
        .btn-action {
            padding: 5px 10px;
            font-size: 0.85rem;
            margin: 2px;
        }
        
        .nav-tabs .nav-link {
            color: #667eea;
            font-weight: 500;
            padding: 12px 24px;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .nav-tabs .nav-link:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border-left: 5px solid #667eea;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
        }

        .stats-label {
            color: #6c757d;
            font-size: 0.95rem;
            margin-top: 10px;
        }

        .stats-icon {
            font-size: 2rem;
            color: #667eea;
            opacity: 0.3;
        }

        .nav-pills .nav-link {
            color: #667eea;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .nav-pills .nav-link:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        .tab-pane {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .table-section {
            margin-bottom: 40px;
        }

        .table-section h6 {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
    </style>
</head>
<body>

<?php
// Load navbar
require_once __DIR__ . '/../components/navbar_loader.php';

$breadcrumb = [
    ['label' => 'Quản Lý Hệ Thống', 'url' => ''],
    ['label' => 'Người Dùng', 'url' => '']
];

renderSmartNavbar('users', ['breadcrumb' => $breadcrumb]);
?>

<div class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fas fa-users-cog me-2"></i>Quản Lý Hệ Thống</h2>
                <p class="mb-0">Quản lý người dùng, phân quyền và dữ liệu hệ thống</p>
            </div>
            <div>
                <a href="users.php?action=create" class="btn btn-light btn-lg me-2">
                    <i class="fas fa-plus me-2"></i>Thêm Người Dùng
                </a>
                <span class="badge bg-light text-dark px-3 py-2">
                    <i class="fas fa-user-shield me-1"></i>
                    <?= htmlspecialchars($currentUser['full_name']) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ✅ TAB NAVIGATION -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" href="users.php?tab=users">
                <i class="fas fa-users me-2"></i>Người Dùng
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="users.php?tab=system">
                <i class="fas fa-cogs me-2"></i>Quản Lý Data & Cache
            </a>
        </li>
    </ul>

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

    <!-- Sub Tabs inside Users Tab -->
    <ul class="nav nav-pills mb-4" role="tablist" id="userSubTabs">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="pill" href="#usersManagement" role="tab">
                <i class="fas fa-list me-2"></i>Danh Sách Người Dùng
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="pill" href="#usersStatistics" role="tab">
                <i class="fas fa-chart-bar me-2"></i>Thống Kê & Chi Tiết
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Users Management Tab -->
        <div class="tab-pane fade show active" id="usersManagement" role="tabpanel">
            <!-- Filters -->
            <div class="filter-card">
                <h5 class="mb-4"><i class="fas fa-filter me-2"></i>Bộ Lọc</h5>
                <form method="GET" action="users.php">
                    <input type="hidden" name="tab" value="users">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Vai Trò</label>
                            <select name="role" class="form-select">
                                <option value="">-- Tất cả --</option>
                                <option value="admin" <?= ($filters['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                                <option value="user" <?= ($filters['role'] === 'user') ? 'selected' : '' ?>>User</option>
                                <option value="viewer" <?= ($filters['role'] === 'viewer') ? 'selected' : '' ?>>Viewer</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Trạng Thái</label>
                            <select name="is_active" class="form-select">
                                <option value="">-- Tất cả --</option>
                                <option value="1" <?= ($filters['is_active'] === 1) ? 'selected' : '' ?>>Đang hoạt động</option>
                                <option value="0" <?= ($filters['is_active'] === 0) ? 'selected' : '' ?>>Đã khóa</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Tìm Kiếm</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Tên đăng nhập, họ tên, email..." 
                                   value="<?= htmlspecialchars($filters['search']) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Lọc
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- User Table -->
            <div class="data-card">
                <h5 class="mb-4">
                    <i class="fas fa-list me-2"></i>Danh Sách Người Dùng
                    <span class="badge bg-secondary"><?= count($users) ?></span>
                </h5>
                
                <div class="table-responsive">
                    <table id="usersTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th style="width: 150px;">Tên Đăng Nhập</th>
                                <th>Họ Tên</th>
                                <th>Email</th>
                                <th style="width: 100px; text-align: center;">Vai Trò</th>
                                <th style="width: 120px; text-align: center;">Trạng Thái</th>
                                <th style="width: 150px;">Đăng Nhập Lần Cuối</th>
                                <th style="width: 200px; text-align: center;">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                                    <td class="text-center">
                                        <?php 
                                        $roleClass = 'badge-' . $user['role'];
                                        $roleText = [
                                            'admin' => 'Admin',
                                            'user' => 'User',
                                            'viewer' => 'Viewer'
                                        ][$user['role']] ?? $user['role'];
                                        ?>
                                        <span class="badge <?= $roleClass ?>"><?= $roleText ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($user['is_active']): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle"></i> Hoạt động
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-lock"></i> Đã khóa
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['last_login']): ?>
                                            <small><?= date('d/m/Y H:i', strtotime($user['last_login'])) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Chưa đăng nhập</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="users.php?tab=users&action=edit&id=<?= $user['id'] ?>" 
                                           class="btn btn-sm btn-primary btn-action">
                                            <i class="fas fa-edit"></i> Sửa
                                        </a>
                                        
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button onclick="toggleStatus(<?= $user['id'] ?>, <?= $user['is_active'] ? 0 : 1 ?>)" 
                                                    class="btn btn-sm btn-<?= $user['is_active'] ? 'warning' : 'success' ?> btn-action">
                                                <i class="fas fa-<?= $user['is_active'] ? 'lock' : 'unlock' ?>"></i>
                                                <?= $user['is_active'] ? 'Khóa' : 'Mở' ?>
                                            </button>
                                            
                                            <button onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" 
                                                    class="btn btn-sm btn-danger btn-action">
                                                <i class="fas fa-trash"></i> Xóa
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Statistics Tab -->
        <div class="tab-pane fade" id="usersStatistics" role="tabpanel">
            <!-- Statistics Cards Row 1 -->
            <div class="row mb-4">
                <div class="col-md-6 col-lg-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stats-number"><?= count($users) ?></div>
                                <div class="stats-label"><i class="fas fa-users me-2"></i>Tổng Số Người Dùng</div>
                            </div>
                            <i class="fas fa-users stats-icon"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stats-number" style="color: #dc3545;">
                                    <?php 
                                    $adminCount = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
                                    echo $adminCount;
                                    ?>
                                </div>
                                <div class="stats-label"><i class="fas fa-crown me-2"></i>Admin</div>
                            </div>
                            <i class="fas fa-crown stats-icon" style="color: #dc3545;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stats-number" style="color: #28a745;">
                                    <?php 
                                    $userCount = count(array_filter($users, fn($u) => $u['role'] === 'user'));
                                    echo $userCount;
                                    ?>
                                </div>
                                <div class="stats-label"><i class="fas fa-user me-2"></i>User</div>
                            </div>
                            <i class="fas fa-user stats-icon" style="color: #28a745;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stats-number" style="color: #6c757d;">
                                    <?php 
                                    $viewerCount = count(array_filter($users, fn($u) => $u['role'] === 'viewer'));
                                    echo $viewerCount;
                                    ?>
                                </div>
                                <div class="stats-label"><i class="fas fa-eye me-2"></i>Viewer</div>
                            </div>
                            <i class="fas fa-eye stats-icon" style="color: #6c757d;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards Row 2 -->
            <div class="row mb-4">
                <div class="col-md-6 col-lg-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stats-number" style="color: #28a745;">
                                    <?php 
                                    $activeCount = count(array_filter($users, fn($u) => $u['is_active'] === 1));
                                    echo $activeCount;
                                    ?>
                                </div>
                                <div class="stats-label"><i class="fas fa-check-circle me-2"></i>Đang Hoạt Động</div>
                            </div>
                            <i class="fas fa-check-circle stats-icon" style="color: #28a745;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stats-number" style="color: #dc3545;">
                                    <?php 
                                    $inactiveCount = count(array_filter($users, fn($u) => $u['is_active'] === 0));
                                    echo $inactiveCount;
                                    ?>
                                </div>
                                <div class="stats-label"><i class="fas fa-lock me-2"></i>Đã Khóa</div>
                            </div>
                            <i class="fas fa-lock stats-icon" style="color: #dc3545;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stats-number" style="color: #667eea;">7</div>
                                <div class="stats-label"><i class="fas fa-database me-2"></i>Số Bảng Quản Lý</div>
                            </div>
                            <i class="fas fa-database stats-icon" style="color: #667eea;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stats-number" style="color: #17a2b8;">Connected</div>
                                <div class="stats-label"><i class="fas fa-link me-2"></i>Redis Status</div>
                            </div>
                            <i class="fas fa-link stats-icon" style="color: #17a2b8;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Login History Quick View Table Section -->
            <div class="table-section">
                <div class="data-card">
                    <h6><i class="fas fa-history me-2"></i>Lịch Sử Đăng Nhập (Tóm Tắt)</h6>
                    <div class="table-responsive">
                        <table id="loginHistoryQuickViewTable" class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th style="width: 70px;">User ID</th>
                                    <th style="width: 120px;">IP Address</th>
                                    <th>User Agent</th>
                                    <th style="width: 160px;">Login Time</th>
                                    <th style="width: 160px;">Logout Time</th>
                                    <th style="width: 100px;">Duration</th>
                                    <th style="width: 150px;">Session Token</th>
                                    <th style="width: 80px; text-align: center;">Thao Tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if (isset($loginHistory) && is_array($loginHistory)) {
                                    foreach ($loginHistory as $log): 
                                ?>
                                    <tr>
                                        <td><?= $log['id'] ?></td>
                                        <td><?= $log['user_id'] ?></td>
                                        <td><small><?= htmlspecialchars($log['ip_address'] ?? '-') ?></small></td>
                                        <td>
                                            <small class="text-muted" title="<?= htmlspecialchars($log['user_agent'] ?? '-') ?>">
                                                <?php 
                                                    $ua = $log['user_agent'] ?? '-';
                                                    if (strlen($ua) > 50) {
                                                        echo htmlspecialchars(substr($ua, 0, 50)) . '...';
                                                    } else {
                                                        echo htmlspecialchars($ua);
                                                    }
                                                ?>
                                            </small>
                                        </td>
                                        <td><small><?= $log['login_time'] ? date('d/m/Y H:i:s', strtotime($log['login_time'])) : '-' ?></small></td>
                                        <td><small><?= $log['logout_time'] ? date('d/m/Y H:i:s', strtotime($log['logout_time'])) : '-' ?></small></td>
                                        <td>
                                            <?php 
                                            if ($log['login_time'] && $log['logout_time']) {
                                                $interval = strtotime($log['logout_time']) - strtotime($log['login_time']);
                                                $hours = floor($interval / 3600);
                                                $minutes = floor(($interval % 3600) / 60);
                                                echo "<small>{$hours}h {$minutes}m</small>";
                                            } else {
                                                echo '<span class="badge bg-success">Active</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><small class="text-monospace"><?= htmlspecialchars(substr($log['session_token'] ?? '-', 0, 15)) ?>...</small></td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-info" onclick="viewLoginDetailFull(<?= $log['id'] ?>, '<?= htmlspecialchars($log['ip_address'] ?? '-') ?>', '<?= htmlspecialchars(addslashes($log['user_agent'] ?? '-')) ?>', '<?= htmlspecialchars($log['session_token'] ?? '-') ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php 
                                    endforeach;
                                } else {
                                ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">Không có dữ liệu lịch sử đăng nhập</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Login History Table Section -->
            <div class="table-section">
                <div class="data-card">
                    <h6><i class="fas fa-history me-2"></i>Login History</h6>
                    <div class="table-responsive">
                        <table id="loginHistoryTable" class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User ID</th>
                                    <th>IP Address</th>
                                    <th>Login Time</th>
                                    <th>Logout Time</th>
                                    <th>Duration</th>
                                    <th class="text-center">Thao Tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if (isset($roleSwitchLog) && is_array($roleSwitchLog)) {
                                    foreach ($roleSwitchLog as $log): 
                                ?>
                                    <tr>
                                        <td><?= $log['id'] ?></td>
                                        <td><?= $log['admin_user_id'] ?></td>
                                        <td><span class="badge badge-<?= $log['switched_to_role'] ?>"><?= ucfirst($log['switched_to_role']) ?></span></td>
                                        <td><?= date('d/m/Y H:i:s', strtotime($log['switched_at'])) ?></td>
                                        <td><?= $log['switched_back_at'] ? date('d/m/Y H:i:s', strtotime($log['switched_back_at'])) : 'Active' ?></td>
                                        <td>
                                            <?php 
                                            if ($log['switched_at'] && $log['switched_back_at']) {
                                                $interval = strtotime($log['switched_back_at']) - strtotime($log['switched_at']);
                                                $hours = floor($interval / 3600);
                                                $minutes = floor(($interval % 3600) / 60);
                                                echo "{$hours}h {$minutes}m";
                                            } else {
                                                echo 'Active';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-info" onclick="viewRoleSwitchDetail(<?= $log['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php 
                                    endforeach;
                                } else {
                                ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No role switch data</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- User Detail Modal -->
<div class="modal fade" id="userDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title"><i class="fas fa-user-circle me-2"></i>Chi Tiết Người Dùng</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="fw-bold text-muted small">ID</label>
                        <p id="detail_id" class="fs-6">-</p>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold text-muted small">Username</label>
                        <p id="detail_username" class="fs-6">-</p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="fw-bold text-muted small">Họ Tên</label>
                        <p id="detail_fullname" class="fs-6">-</p>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold text-muted small">Email</label>
                        <p id="detail_email" class="fs-6">-</p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="fw-bold text-muted small">Role</label>
                        <p id="detail_role" class="fs-6">-</p>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold text-muted small">Status</label>
                        <p id="detail_status" class="fs-6">-</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- Login History Detail Modal -->
<div class="modal fade" id="loginDetailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title"><i class="fas fa-history me-2"></i>Chi Tiết Login</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>User Agent:</strong></p>
                <p id="login_useragent" class="small text-muted">-</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        $('#usersTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json'
            },
            pageLength: 25,
            order: [[0, 'desc']]
        });

        $('#loginHistoryQuickViewTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json'
            },
            pageLength: 25,
            order: [[0, 'desc']],
            columnDefs: [
                { orderable: false, targets: -1 }
            ]
        });

        $('#loginHistoryQuickTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json'
            },
            pageLength: 10,
            order: [[0, 'desc']],
            paging: false,
            searching: false
        });

        $('#loginHistoryFullTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json'
            },
            pageLength: 25,
            order: [[0, 'desc']]
        });

        $('#permissionsTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json'
            },
            pageLength: 10,
            order: [[0, 'desc']]
        });

        $('#roleSwitchTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json'
            },
            pageLength: 10,
            order: [[0, 'desc']]
        });
        
        // Auto-hide alerts
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    });

    function viewUserDetail(id, username) {
        document.getElementById('detail_id').textContent = id;
        document.getElementById('detail_username').textContent = username;
        var modal = new bootstrap.Modal(document.getElementById('userDetailModal'));
        modal.show();
    }

    function viewLoginDetail(id) {
        document.getElementById('login_useragent').textContent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)...';
        var modal = new bootstrap.Modal(document.getElementById('loginDetailModal'));
        modal.show();
    }

    function viewLoginDetailFull(id, ip, userAgent, sessionToken) {
        var modalContent = `
            <div class="modal fade" id="loginDetailFullModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <h5 class="modal-title"><i class="fas fa-history me-2"></i>Chi Tiết Lịch Sử Đăng Nhập</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="fw-bold text-muted small">ID</label>
                                    <p class="fs-6">${id}</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="fw-bold text-muted small">IP Address</label>
                                    <p class="fs-6">${ip}</p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="fw-bold text-muted small">User Agent</label>
                                    <p class="fs-6 text-break"><small>${userAgent}</small></p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="fw-bold text-muted small">Session Token</label>
                                    <p class="fs-6 text-break"><code>${sessionToken}</code></p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove old modal if exists
        var oldModal = document.getElementById('loginDetailFullModal');
        if (oldModal) oldModal.remove();
        
        // Add new modal
        document.body.insertAdjacentHTML('beforeend', modalContent);
        var modal = new bootstrap.Modal(document.getElementById('loginDetailFullModal'));
        modal.show();
        
        // Clean up when modal is hidden
        document.getElementById('loginDetailFullModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    function viewPermissionDetail(id) {
        alert('Chi tiết quyền: ' + id);
    }

    function viewRoleSwitchDetail(id) {
        alert('Chi tiết chuyển đổi vai trò: ' + id);
    }

    function toggleStatus(userId, status) {
        const action = status ? 'kích hoạt' : 'khóa';
        if (!confirm(`Bạn có chắc muốn ${action} tài khoản này?`)) return;

        $.post('users.php?tab=users&action=toggle_status', {
            user_id: userId,
            status: status
        }, function(response) {
            if (response.success) {
                alert(response.message);
                location.reload();
            } else {
                alert('Lỗi: ' + response.error);
            }
        }, 'json');
    }

    function confirmDelete(userId, username) {
        if (!confirm(`Bạn có chắc muốn xóa tài khoản "${username}"?\n\nHành động này không thể hoàn tác!`)) return;

        $.post('users.php?tab=users&action=delete', {
            user_id: userId
        }, function(response) {
            if (response.success) {
                alert(response.message);
                location.reload();
            } else {
                alert('Lỗi: ' + response.error);
            }
        }, 'json');
    }
</script>
</body>
</html>