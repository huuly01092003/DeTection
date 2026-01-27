<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh Sách Nhân Viên - DSNV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }
        
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .page-header h1 {
            color: #10b981;
            margin: 0;
            font-weight: 700;
        }
        
        .stats-row {
            margin-top: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }
        
        .stat-card.secondary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
        }
        
        .stat-card.danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }
        
        .stat-card.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            box-shadow: 0 5px 15px rgba(245, 158, 11, 0.3);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .filter-card h5 {
            color: #10b981;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 15px;
        }
        
        .table tbody tr {
            transition: all 0.3s;
        }
        
        .table tbody tr:hover {
            background: #f0fdf4;
            transform: scale(1.01);
        }
        
        .badge {
            padding: 6px 12px;
            font-weight: 600;
            font-size: 11px;
            border-radius: 20px;
        }
        
        .badge.bg-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
        }
        
        .badge.bg-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
        }
        
        .btn-group-actions .btn {
            margin: 0 2px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }
        
        .pagination {
            margin-top: 20px;
            justify-content: center;
        }
        
        .pagination .page-link {
            color: #10b981;
            border: 1px solid #10b981;
            margin: 0 3px;
            border-radius: 8px;
        }
        
        .pagination .page-link:hover {
            background: #10b981;
            color: white;
        }
        
        .pagination .active .page-link {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-color: #10b981;
        }
        
        .form-select, .form-control {
            border-radius: 8px;
            border: 2px solid #e5e7eb;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.25);
        }
    </style>
</head>
<body>
    <div class="container main-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-users"></i> Danh Sách Nhân Viên</h1>
                    <p class="text-muted mb-0">Quản lý thông tin nhân viên công ty</p>
                </div>
                <div class="btn-group-actions">
                    <?php if ($role === 'admin'): ?>
                        <a href="dsnv.php" class="btn btn-primary">
                            <i class="fas fa-file-import"></i> Import CSV
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($role === 'admin' || $role === 'user'): ?>
                        <a href="dsnv.php?action=export" class="btn btn-success">
                            <i class="fas fa-file-export"></i> Export CSV
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Stats Row -->
            <div class="row stats-row g-3">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= number_format($stats['total']) ?></div>
                        <div class="stat-label">Tổng Số NV</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card secondary">
                        <div class="stat-number"><?= number_format($stats['active']) ?></div>
                        <div class="stat-label">Đang Làm Việc</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card danger">
                        <div class="stat-number"><?= number_format($stats['inactive']) ?></div>
                        <div class="stat-label">Đã Nghỉ Việc</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card warning">
                        <div class="stat-number"><?= number_format($stats['departments']) ?></div>
                        <div class="stat-label">Bộ Phận</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-card">
            <h5><i class="fas fa-filter"></i> Bộ Lọc</h5>
            <form method="GET" action="dsnv.php">
                <input type="hidden" name="action" value="list">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Bộ Phận</label>
                        <select name="bo_phan" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($filterOptions['bo_phan'] as $bp): ?>
                                <option value="<?= htmlspecialchars($bp) ?>" 
                                    <?= ($filters['bo_phan'] === $bp) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($bp) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Chức Vụ</label>
                        <select name="chuc_vu" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($filterOptions['chuc_vu'] as $cv): ?>
                                <option value="<?= htmlspecialchars($cv) ?>" 
                                    <?= ($filters['chuc_vu'] === $cv) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cv) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Tỉnh</label>
                        <select name="base_tinh" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($filterOptions['base_tinh'] as $tinh): ?>
                                <option value="<?= htmlspecialchars($tinh) ?>" 
                                    <?= ($filters['base_tinh'] === $tinh) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tinh) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Trạng Thái</label>
                        <select name="trang_thai" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($filterOptions['trang_thai'] as $tt): ?>
                                <option value="<?= htmlspecialchars($tt) ?>" 
                                    <?= ($filters['trang_thai'] === $tt) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Tìm Kiếm</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Tên hoặc Mã NV..." 
                               value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                    
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Mã NV</th>
                            <th>Họ Tên</th>
                            <th>Giới Tính</th>
                            <th>Bộ Phận</th>
                            <th>Chức Vụ</th>
                            <th>Tỉnh</th>
                            <th>SĐT</th>
                            <th>Trạng Thái</th>
                            <th>Người QL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($result['data'])): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Không có dữ liệu</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($result['data'] as $row): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['ma_nv']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['ho_ten']) ?></td>
                                    <td><?= htmlspecialchars($row['gioi_tinh']) ?></td>
                                    <td><?= htmlspecialchars($row['bo_phan']) ?></td>
                                    <td><?= htmlspecialchars($row['chuc_vu']) ?></td>
                                    <td><?= htmlspecialchars($row['base_tinh']) ?></td>
                                    <td><?= htmlspecialchars($row['sdt_ca_nhan']) ?></td>
                                    <td>
                                        <?php if ($row['trang_thai'] === 'Đang làm'): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle"></i> Đang làm
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times-circle"></i> Nghỉ việc
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['ten_nv_ql'])): ?>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($row['ten_nv_ql']) ?>
                                                <br>(<?= htmlspecialchars($row['ma_nv_ql']) ?>)
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($result['total_pages'] > 1): ?>
                <nav>
                    <ul class="pagination">
                        <?php
                        $currentPage = $result['page'];
                        $totalPages = $result['total_pages'];
                        $queryParams = $_GET;
                        $queryParams['action'] = 'list';
                        
                        // Previous button
                        if ($currentPage > 1):
                            $queryParams['page'] = $currentPage - 1;
                            $prevUrl = 'dsnv.php?' . http_build_query($queryParams);
                        ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= $prevUrl ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        // Page numbers
                        $start = max(1, $currentPage - 2);
                        $end = min($totalPages, $currentPage + 2);
                        
                        for ($i = $start; $i <= $end; $i++):
                            $queryParams['page'] = $i;
                            $pageUrl = 'dsnv.php?' . http_build_query($queryParams);
                        ?>
                            <li class="page-item <?= ($i === $currentPage) ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $pageUrl ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php
                        // Next button
                        if ($currentPage < $totalPages):
                            $queryParams['page'] = $currentPage + 1;
                            $nextUrl = 'dsnv.php?' . http_build_query($queryParams);
                        ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= $nextUrl ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                
                <div class="text-center text-muted mt-3">
                    <small>
                        Hiển thị <?= number_format(($currentPage - 1) * PAGE_SIZE + 1) ?> 
                        - <?= number_format(min($currentPage * PAGE_SIZE, $result['total'])) ?> 
                        / <?= number_format($result['total']) ?> nhân viên
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>