<?php
/**
 * Enhanced System Management View
 * File: views/system/management.php
 * Features:
 * - Removed date-based deletion from table management
 * - Added Redis key tables grouped by cache type
 * - Individual key deletion buttons
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
    <title>Quản Lý Hệ Thống - <?= htmlspecialchars($currentUser['full_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
        }
        
        .data-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .table-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .danger-zone {
            border: 2px solid #dc3545;
            border-radius: 10px;
            padding: 20px;
            background: #fff5f5;
            margin-top: 20px;
        }
        
        .btn-danger-action {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            color: white;
            font-weight: 600;
        }
        
        .btn-danger-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }
        
        .redis-key-group {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .redis-key-item {
            background: white;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 8px;
            border-left: 4px solid #667eea;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }
        
        .redis-key-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateX(3px);
        }
        
        .redis-key-name {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: #333;
            flex: 1;
            word-break: break-all;
        }
        
        .redis-key-meta {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-right: 10px;
        }
        
        .key-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
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
        
        .cache-summary-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .cache-summary-table table {
            margin-bottom: 0;
        }
        
        .cache-summary-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .loading-spinner {
            text-align: center;
            padding: 30px;
        }

        .redis-key-group {
    background: #f8f9fa;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.redis-key-item {
    background: white;
    padding: 12px 15px;
    border-radius: 5px;
    margin-bottom: 8px;
    border-left: 4px solid #667eea;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s ease;
}

.redis-key-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateX(5px);
    border-left-color: #764ba2;
}

.redis-key-name {
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    color: #333;
    flex: 1;
    word-break: break-all;
    margin-right: 15px;
}

.redis-key-meta {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-right: 10px;
    flex-shrink: 0;
}

.key-badge {
    font-size: 0.75rem;
    padding: 4px 8px;
    white-space: nowrap;
}

/* Cache Summary Table */
.cache-summary-table {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.cache-summary-table table {
    margin-bottom: 0;
}

.cache-summary-table thead {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.cache-summary-table thead th {
    border: none;
    font-weight: 600;
    padding: 15px;
}

.cache-summary-table tbody tr:hover {
    background: rgba(102, 126, 234, 0.05);
}

/* Loading States */
.loading-spinner {
    text-align: center;
    padding: 30px;
    color: #667eea;
}

.loading-spinner i {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Table Info Cards */
.table-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 15px;
    border: 1px solid #e0e0e0;
    transition: all 0.2s ease;
}

.table-info:hover {
    border-color: #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
}

/* Stat Cards */
.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border-left: 4px solid #667eea;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.2);
}

.stat-card h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #667eea;
    margin-bottom: 5px;
}

/* Danger Zone */
.danger-zone {
    border: 2px solid #dc3545;
    border-radius: 10px;
    padding: 20px;
    background: linear-gradient(135deg, #fff5f5 0%, #ffe5e5 100%);
    margin-top: 20px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { border-color: #dc3545; }
    50% { border-color: #ff6b6b; }
}

.danger-zone h6 {
    color: #dc3545;
    font-weight: 700;
}

/* Buttons */
.btn-danger-action {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    border: none;
    color: white;
    font-weight: 600;
    padding: 12px 24px;
    transition: all 0.3s ease;
}

.btn-danger-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
    background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
}

/* Action buttons in key items */
.redis-key-item .btn-sm {
    padding: 4px 8px;
    font-size: 0.8rem;
    transition: all 0.2s ease;
}

.redis-key-item .btn-sm:hover {
    transform: scale(1.1);
}

/* Responsive Design */
@media (max-width: 768px) {
    .redis-key-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .redis-key-meta {
        width: 100%;
        justify-content: space-between;
    }
    
    .stat-card {
        margin-bottom: 15px;
    }
}

/* Tabs */
.nav-tabs .nav-link {
    color: #667eea;
    font-weight: 500;
    padding: 12px 24px;
    border: none;
    border-radius: 0;
    transition: all 0.2s ease;
}

.nav-tabs .nav-link:hover {
    background: rgba(102, 126, 234, 0.1);
    border: none;
}

.nav-tabs .nav-link.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
}

/* Alert Animations */
.alert {
    animation: slideInDown 0.5s ease-out;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Success/Error States */
.text-success-glow {
    color: #28a745;
    text-shadow: 0 0 10px rgba(40, 167, 69, 0.5);
}

.text-danger-glow {
    color: #dc3545;
    text-shadow: 0 0 10px rgba(220, 53, 69, 0.5);
}

/* Badge Variations */
.badge-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.badge-gradient-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.badge-gradient-warning {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
}

.badge-gradient-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
}

/* Scrollbar Styling */
.redis-key-group::-webkit-scrollbar {
    width: 8px;
}

.redis-key-group::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.redis-key-group::-webkit-scrollbar-thumb {
    background: #667eea;
    border-radius: 4px;
}

.redis-key-group::-webkit-scrollbar-thumb:hover {
    background: #764ba2;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.3;
}

.empty-state h5 {
    font-weight: 600;
    margin-bottom: 10px;
}

/* Collapsible Sections */
.collapsible-header {
    cursor: pointer;
    user-select: none;
    transition: all 0.2s ease;
}

.collapsible-header:hover {
    background: rgba(102, 126, 234, 0.05);
}

.collapsible-header i.fa-chevron-down {
    transition: transform 0.3s ease;
}

.collapsible-header.collapsed i.fa-chevron-down {
    transform: rotate(-90deg);
}
    </style>
</head>
<body>

<?php
// Load navbar
require_once __DIR__ . '/../components/navbar_loader.php';

$breadcrumb = [
    ['label' => 'Quản Lý Hệ Thống', 'url' => '']
];

renderSmartNavbar('system', ['breadcrumb' => $breadcrumb]);
?>

<div class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="h3 mb-1"><i class="fas fa-cogs me-2"></i>Quản Lý Hệ Thống</h2>
                <p class="mb-0 small opacity-75">Quản lý dữ liệu, cache và bảng summary</p>
            </div>
            <div>
                <span class="badge bg-light text-dark px-3 py-2 shadow-sm">
                    <i class="fas fa-user-shield me-2"></i>
                    <?= htmlspecialchars($currentUser['full_name']) ?>
                </span>
            </div>
        </div>
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

    <!-- TAB NAVIGATION -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link" href="users.php?tab=users">
                <i class="fas fa-users me-2"></i>Người Dùng
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="users.php?tab=system">
                <i class="fas fa-cogs me-2"></i>Quản Lý Data & Cache
            </a>
        </li>
    </ul>

    <!-- System Statistics -->
    <!-- System Statistics -->
    <div class="row mb-4 g-3">
        <div class="col-6 col-md-3">
            <div class="stat-card h-100">
                <h3 class="mb-1"><?= number_format(array_sum(array_column($stats['tables'], 'count'))) ?></h3>
                <p class="mb-0 text-muted small">
                    <i class="fas fa-database me-1"></i>Tổng Bản Ghi
                </p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card h-100">
                <h3 class="mb-1"><?= count($stats['tables']) ?></h3>
                <p class="mb-0 text-muted small">
                    <i class="fas fa-table me-1"></i>Số Bảng
                </p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card h-100">
                <h3 class="mb-1">
                    <?= $stats['redis']['connected'] ? 
                        '<span class="text-success">OK</span>' : 
                        '<span class="text-danger">Err</span>' ?>
                </h3>
                <p class="mb-0 text-muted small">
                    <i class="fas fa-server me-1"></i>Redis
                </p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card h-100">
                <h3 class="mb-1"><?= number_format($stats['redis']['total_keys']) ?></h3>
                <p class="mb-0 text-muted small">
                    <i class="fas fa-key me-1"></i>Redis Keys
                </p>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tables">
                <i class="fas fa-table me-2"></i>Quản Lý Bảng
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#redis">
                <i class="fas fa-server me-2"></i>Redis Cache
            </a>
        </li>
        
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- ========================================
             TAB 1: QUẢN LÝ BẢNG (NO DATE DELETE)
             ======================================== -->
        <div id="tables" class="tab-pane fade show active">
            <div class="data-card">
                <h5 class="mb-4">
                    <i class="fas fa-database me-2"></i>Quản Lý Dữ Liệu Bảng
                </h5>

                <?php foreach ($stats['tables'] as $table => $info): ?>
                    <div class="table-info">
                        <div class="row align-items-center g-2">
                            <div class="col-12 col-md-5">
                                <h6 class="mb-1 text-truncate">
                                    <i class="fas fa-table me-2 text-primary"></i>
                                    <?= htmlspecialchars($info['name']) ?>
                                </h6>
                                <small class="text-muted text-truncate d-block">Table: <code><?= $table ?></code></small>
                            </div>
                            <div class="col-6 col-md-3 text-start text-md-center">
                                <div>
                                    <strong style="font-size: 1.1rem;"><?= number_format($info['count']) ?></strong>
                                    <small class="d-none d-md-block text-muted">bản ghi</small>
                                    <span class="d-md-none text-muted small"> dòng</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-2 text-end text-md-center">
                                <div>
                                    <strong style="font-size: 1.1rem; color: #667eea;"><?= $info['size'] ?> MB</strong>
                                    <small class="d-none d-md-block text-muted">dung lượng</small>
                                </div>
                            </div>
                            <div class="col-12 col-md-2 text-end mt-2 mt-md-0">
                                <button class="btn btn-outline-danger btn-sm w-100" 
                                        onclick="confirmClearTable('<?= $table ?>', '<?= $info['name'] ?>')">
                                    <i class="fas fa-trash me-1"></i>Xóa
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="danger-zone">
                    <h6 class="text-danger mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Khu Vực Nguy Hiểm
                    </h6>
                    <p class="text-muted mb-3">
                        Các thao tác dưới đây sẽ <strong>XÓA VĨNH VIỄN</strong> dữ liệu và 
                        <strong>KHÔNG THỂ KHÔI PHỤC</strong>. Vui lòng thực hiện cẩn thận!
                    </p>
                    <button class="btn btn-danger-action" onclick="confirmClearAllData()">
                        <i class="fas fa-bomb me-2"></i>Xóa Toàn Bộ Dữ Liệu Hệ Thống
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================
             TAB 2: REDIS CACHE WITH KEY TABLES
             ======================================== -->
        <div id="redis" class="tab-pane fade">
            <div class="data-card">
                <h5 class="mb-4">
                    <i class="fas fa-server me-2"></i>Quản Lý Redis Cache
                </h5>

                <?php if ($stats['redis']['connected']): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Trạng thái:</strong> Connected | 
                        <strong>Keys:</strong> <?= number_format($stats['redis']['total_keys']) ?> | 
                        <strong>Memory:</strong> <?= $stats['redis']['memory_used'] ?>
                    </div>

                    <!-- Cache Summary Table -->
                    <div class="cache-summary-table mb-4">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 text-nowrap">
                                <thead>
                                    <tr>
                                        <th style="width: 40%;">Cache Type</th>
                                        <th style="width: 15%; text-align: center;">Keys</th>
                                        <th style="width: 15%; text-align: center;">Total Size</th>
                                        <th style="width: 30%; text-align: center;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="cacheSummaryBody">
                                    <tr>
                                        <td colspan="4" class="text-center">
                                            <button class="btn btn-primary" onclick="loadCacheSummary()">
                                                <i class="fas fa-sync me-2"></i>Tải Thống Kê Cache
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Detailed Keys Section -->
                    <div id="detailedKeysSection" style="display: none;">
                        <h6 class="mb-3">
                            <i class="fas fa-list me-2"></i>Chi Tiết Cache Keys
                        </h6>
                        <div id="redisKeyGroups"></div>
                    </div>

                    <div class="danger-zone">
                        <h6 class="text-danger mb-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Xóa Toàn Bộ Cache
                        </h6>
                        <p class="text-muted mb-3">
                            Xóa toàn bộ cache Redis. Hệ thống sẽ tính toán lại dữ liệu khi cần.
                        </p>
                        <button class="btn btn-danger-action" onclick="confirmClearRedisAll()">
                            <i class="fas fa-trash-alt me-2"></i>Xóa Toàn Bộ Redis Cache
                        </button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Lỗi:</strong> Không thể kết nối đến Redis server.
                        Vui lòng kiểm tra cấu hình và khởi động Redis.
                    </div>
                <?php endif; ?>
            </div>
        </div>
                <div class="alert alert-info mt-4">
                    <h6><i class="fas fa-info-circle me-2"></i>Về Summary Tables:</h6>
                    <ul class="mb-0">
                        <li>Lưu kết quả tính toán trước để tăng tốc độ truy vấn</li>
                        <li>Tự động đồng bộ với Redis cache</li>
                        <li>Xóa khi dữ liệu cũ hoặc cần tính toán lại</li>
                        <li>Hệ thống tự động tạo lại khi cần thiết</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ========================================
// TABLE MANAGEMENT FUNCTIONS
// ========================================

function confirmClearTable(table, name) {
    if (!confirm(`⚠️ XÓA TOÀN BỘ DỮ LIỆU\n\nBảng: ${name}\nTable: ${table}\n\nHành động này KHÔNG THỂ HOÀN TÁC!\n\nBạn có chắc chắn muốn tiếp tục?`)) {
        return;
    }
    
    $.post('system.php?action=clear_table_all', {
        table: table
    }, function(response) {
        if (response.success) {
            alert('✅ ' + response.message);
            location.reload();
        } else {
            alert('❌ ' + response.error);
        }
    }, 'json').fail(function() {
        alert('❌ Lỗi kết nối server');
    });
}

function confirmClearAllData() {
    const confirmation = prompt('⚠️⚠️⚠️ CẢNH BÁO CỰC KỲ NGUY HIỂM ⚠️⚠️⚠️\n\nĐIỀU NÀY SẼ XÓA TOÀN BỘ DỮ LIỆU HỆ THỐNG!\n\nGõ "XOA TAT CA" để xác nhận:');
    
    if (confirmation !== 'XOA TAT CA') {
        alert('Đã hủy thao tác');
        return;
    }
    
    alert('Chức năng này chưa được kích hoạt để tránh xóa nhầm.\nVui lòng xóa từng bảng riêng lẻ.');
}

// ========================================
// REDIS MANAGEMENT FUNCTIONS
// ========================================

function loadCacheSummary() {
    $('#cacheSummaryBody').html(`
        <tr>
            <td colspan="4" class="text-center">
                <i class="fas fa-spinner fa-spin me-2"></i>Đang tải thống kê...
            </td>
        </tr>
    `);
    
    $.get('system.php?action=get_cache_summary', function(response) {
        if (response.success) {
            displayCacheSummary(response.data);
        } else {
            $('#cacheSummaryBody').html(`
                <tr>
                    <td colspan="4" class="text-center text-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>Lỗi: ${response.error}
                    </td>
                </tr>
            `);
        }
    }, 'json').fail(function() {
        $('#cacheSummaryBody').html(`
            <tr>
                <td colspan="4" class="text-center text-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>Lỗi kết nối server
                </td>
            </tr>
        `);
    });
}

function displayCacheSummary(data) {
    let html = '';
    
    const cacheTypes = {
        'anomaly:report:*': { name: 'Anomaly Reports', icon: 'exclamation-triangle', color: 'warning' },
        'report:cache:*': { name: 'Report Cache', icon: 'chart-bar', color: 'info' },
        'nhanvien:report:*': { name: 'Nhân Viên Reports', icon: 'user-tie', color: 'success' },
        'nhanvien:kpi:*': { name: 'KPI Cache', icon: 'chart-line', color: 'primary' }
    };
    
    for (const [pattern, info] of Object.entries(cacheTypes)) {
        const stats = data[pattern] || { count: 0, size: 0 };
        html += `
            <tr>
                <td>
                    <i class="fas fa-${info.icon} me-2 text-${info.color}"></i>
                    <strong>${info.name}</strong>
                    <br><small class="text-muted">${pattern}</small>
                </td>
                <td class="text-center">
                    <span class="badge bg-${info.color}">${stats.count}</span>
                </td>
                <td class="text-center">
                    <strong>${(stats.size / 1024).toFixed(2)} KB</strong>
                </td>
                <td class="text-center">
                    <button class="btn btn-sm btn-primary me-1" onclick="loadDetailedKeys('${pattern}')">
                        <i class="fas fa-eye me-1"></i>Xem Chi Tiết
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="confirmClearPattern('${pattern}')">
                        <i class="fas fa-trash me-1"></i>Xóa Pattern
                    </button>
                </td>
            </tr>
        `;
    }
    
    if (html === '') {
        html = `
            <tr>
                <td colspan="4" class="text-center text-muted">
                    <i class="fas fa-inbox me-2"></i>Không có cache keys
                </td>
            </tr>
        `;
    }
    
    $('#cacheSummaryBody').html(html);
}

function loadDetailedKeys(pattern) {
    $('#detailedKeysSection').show();
    $('#redisKeyGroups').html(`
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p class="mt-2">Đang tải keys...</p>
        </div>
    `);
    
    $.get('system.php?action=get_redis_keys', { pattern: pattern }, function(response) {
        if (response.success) {
            displayDetailedKeys(response, pattern);
        } else {
            $('#redisKeyGroups').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>${response.error}
                </div>
            `);
        }
    }, 'json');
}

function displayDetailedKeys(data, pattern) {
    let html = `
        <div class="redis-key-group">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">
                    <i class="fas fa-key me-2"></i>Pattern: <code>${pattern}</code>
                    <span class="badge bg-primary ms-2">${data.total} keys</span>
                </h6>
                <button class="btn btn-sm btn-secondary" onclick="$('#detailedKeysSection').hide()">
                    <i class="fas fa-times me-1"></i>Đóng
                </button>
            </div>
    `;
    
    if (data.keys.length === 0) {
        html += `<p class="text-muted text-center">Không tìm thấy key nào</p>`;
    } else {
        data.keys.forEach(key => {
            const ttlText = key.ttl > 0 ? `${key.ttl}s` : 'No expire';
            html += `
                <div class="redis-key-item">
                    <div class="redis-key-name">${escapeHtml(key.key)}</div>
                    <div class="redis-key-meta">
                        <span class="badge bg-info key-badge">${key.type}</span>
                        <span class="badge bg-secondary key-badge">${ttlText}</span>
                        <span class="badge bg-primary key-badge">${(key.size / 1024).toFixed(2)} KB</span>
                    </div>
                    <button class="btn btn-sm btn-danger" onclick="deleteRedisKey('${escapeHtml(key.key)}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
        });
    }
    
    html += `</div>`;
    $('#redisKeyGroups').html(html);
}

function loadTableKeys(table, pattern) {
    const container = $(`#keys-${table}`);
    const listContainer = $(`#list-${table}`);
    
    if (container.is(':visible')) {
        container.slideUp();
        return;
    }
    
    container.slideDown();
    
    $.get('system.php?action=get_redis_keys', { pattern: pattern }, function(response) {
        if (response.success) {
            $(`#count-${table}`).text(response.total);
            
            let html = '';
            if (response.keys.length === 0) {
                html = `<p class="text-muted text-center">Không có cache keys</p>`;
            } else {
                response.keys.forEach(key => {
                    const ttlText = key.ttl > 0 ? `${key.ttl}s` : 'No expire';
                    html += `
                        <div class="redis-key-item">
                            <div class="redis-key-name">${escapeHtml(key.key)}</div>
                            <div class="redis-key-meta">
                                <span class="badge bg-info key-badge">${key.type}</span>
                                <span class="badge bg-secondary key-badge">${ttlText}</span>
                                <span class="badge bg-primary key-badge">${(key.size / 1024).toFixed(2)} KB</span>
                            </div>
                            <button class="btn btn-sm btn-danger" onclick="deleteRedisKey('${escapeHtml(key.key)}', '${table}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                });
            }
            listContainer.html(html);
        } else {
            listContainer.html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>${response.error}
                </div>
            `);
        }
    }, 'json').fail(function() {
        listContainer.html(`
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>Lỗi kết nối server
            </div>
        `);
    });
}

function deleteRedisKey(key, tableContext = null) {
    if (!confirm(`⚠️ Xóa cache key?\n\nKey: ${key}\n\nBạn có chắc chắn?`)) {
        return;
    }
    
    $.post('system.php?action=delete_redis_key', { key: key }, function(response) {
        if (response.success) {
            alert('✅ ' + response.message);
            
            // Reload the specific section
            if (tableContext) {
                // Reload table keys
                const pattern = $(`#keys-${tableContext}`).data('pattern');
                if (pattern) {
                    loadTableKeys(tableContext, pattern);
                }
            } else {
                // Reload detailed view
                location.reload();
            }
        } else {
            alert('❌ ' + response.error);
        }
    }, 'json').fail(function() {
        alert('❌ Lỗi kết nối server');
    });
}

function confirmClearPattern(pattern) {
    if (!confirm(`⚠️ XÓA TẤT CẢ KEYS\n\nPattern: ${pattern}\n\nBạn có chắc chắn?`)) {
        return;
    }
    
    $.post('system.php?action=clear_redis_pattern', { pattern: pattern }, function(response) {
        if (response.success) {
            alert('✅ ' + response.message);
            location.reload();
        } else {
            alert('❌ ' + response.error);
        }
    }, 'json').fail(function() {
        alert('❌ Lỗi kết nối server');
    });
}

function confirmClearRedisAll() {
    if (!confirm('⚠️ XÓA TOÀN BỘ REDIS CACHE\n\nHành động này sẽ xóa tất cả cache.\nHệ thống sẽ tính toán lại khi cần.\n\nBạn có chắc chắn?')) {
        return;
    }
    
    $.post('system.php?action=clear_redis_all', {}, function(response) {
        if (response.success) {
            alert('✅ ' + response.message);
            location.reload();
        } else {
            alert('❌ ' + response.error);
        }
    }, 'json').fail(function() {
        alert('❌ Lỗi kết nối server');
    });
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Auto-hide alerts
setTimeout(function() {
    $('.alert').fadeOut('slow');
}, 5000);
</script>
</body>
</html>