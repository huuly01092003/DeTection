<?php
/**
 * ✅ VIEW KPI NHÂN VIÊN - WITH SHARED NAVBAR
 * File: views/nhanvien_kpi/report.php
 */

// ✅ Load navbar loader (tự động chọn navbar phù hợp)
require_once __DIR__ . '/../components/navbar_loader.php';

// ✅ Render navbar với thông tin bổ sung
$additionalInfo = [
    'period' => !empty($filters['thang']) ? 'Tháng ' . date('m/Y', strtotime($filters['thang'] . '-01')) : '',
    'breadcrumb' => [
        ['label' => 'Dashboard', 'url' => 'dashboard.php'],
        ['label' => 'Báo Cáo', 'url' => '#'],
        ['label' => 'KPI Nhân Viên', 'url' => '']
    ]
];

renderSmartNavbar('nhanvien_kpi', $additionalInfo);

// ✅ Load permission helpers
if (!function_exists('isViewer')) {
    require_once __DIR__ . '/../../helpers/permission_helpers.php';
}

$isViewer = isViewer();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo Cáo KPI - Logic Ngưỡng N</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <?php if ($isViewer): ?>
    <link href="assets/css/viewer_restrictions.css" rel="stylesheet">
    <?php endif; ?>
    
    <style>
        /* ✅ PREMIUM UI UPGRADE 2026 */
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --glass-bg: rgba(255, 255, 255, 0.95);
            --card-radius: 16px;
            --shadow-soft: 0 10px 40px rgba(0,0,0,0.08);
            --shadow-hover: 0 20px 50px rgba(0,0,0,0.15);
        }
        
        body { 
            background: #f3f4f6; 
            min-height: 100vh; 
            padding: 20px; 
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }
        
        .card { 
            background: var(--glass-bg); 
            border-radius: var(--card-radius); 
            box-shadow: var(--shadow-soft); 
            border: none;
            margin-bottom: 25px; 
            backdrop-filter: blur(10px);
        }
        
        .card-header { 
            background: var(--primary-gradient); 
            color: white; 
            padding: 25px 30px; 
            border-radius: var(--card-radius) var(--card-radius) 0 0; 
            border: none;
        }
        
        /* Premium KPI Cards */
        .kpi-card { 
            background: white; 
            padding: 20px; 
            border-radius: 16px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); 
            text-align: center; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0,0,0,0.03);
            position: relative;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .kpi-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
            border-color: rgba(102, 126, 234, 0.2);
        }
        
        .kpi-card-icon {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 3rem;
            opacity: 0.1;
            transform: rotate(-15deg);
            transition: all 0.3s;
        }
        
        .kpi-card:hover .kpi-card-icon {
            transform: rotate(0deg) scale(1.1);
            opacity: 0.15;
        }
        
        .kpi-value { 
            font-size: 1.8rem; 
            font-weight: 800; 
            color: #2d3748;
            margin-bottom: 5px;
            letter-spacing: -0.5px;
        }
        
        .kpi-label { 
            font-size: 0.85rem; 
            color: #718096; 
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filter Section Polish */
        .filter-box {
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 25px !important;
        }
        
        .form-label {
            font-weight: 600;
            color: #4a5568;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-select, .form-control {
            border-radius: 8px;
            border: 1px solid #cbd5e0;
            padding: 10px 15px;
            font-size: 0.95rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            transition: all 0.2s;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
        }

        /* Table Polish */
        .table thead th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 15px 10px;
        }
        
        .table tbody td {
            vertical-align: middle;
            padding: 15px 10px;
            font-size: 0.9rem;
        }
        
        .violation-badge { 
            background: #fff5f5;
            color: #e53e3e; 
            padding: 6px 12px; 
            border-radius: 20px; 
            font-size: 0.8rem; 
            font-weight: 700;
            border: 1px solid #fed7d7;
            display: inline-block;
        }
        
        .customer-row { border-bottom: 1px solid #eee; padding: 10px 0; }
        .customer-row:hover { background: #f8f9fa; }
        .order-chip { background: #e3f2fd; padding: 3px 8px; border-radius: 5px; font-size: 0.75rem; margin-right: 5px; border: 1px solid #bbdefb; cursor: pointer; transition: all 0.2s; }
        .order-chip:hover { background: #2196f3; color: white; }
        .clickable-row { cursor: pointer; transition: background 0.2s; }
        .clickable-row:hover { background-color: rgba(102, 126, 234, 0.1) !important; }

        /* Modal Resizable/Draggable handles */
        .modal-content { resize: both; overflow: hidden; min-width: 500px; min-height: 300px; display: flex; flex-direction: column; }
        .modal-body { overflow-y: auto; flex: 1; }
        .modal-header { cursor: move; flex-shrink: 0; }
        
        /* Unified Investigation Hub Styles */
        .investigation-level { transition: all 0.3s ease-in-out; }
        .breadcrumb-item a:hover { color: white !important; text-decoration: underline !important; }
        .breadcrumb-item.active { color: white !important; font-weight: bold; }
        .investigation-modal .modal-content { border-radius: 15px; }
        .detail-sub-row { background-color: #f8f9fa; }
        .detail-container { padding: 15px; border-left: 4px solid #3b82f6; margin: 10px; background: white; border-radius: 0 8px 8px 0; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); }
        .order-products-container { margin-top: 10px; padding: 10px; background: #e9ecef; border-radius: 6px; display: none; }
        .expand-icon { transition: transform 0.3s; }
        .expanded .expand-icon { transform: rotate(90deg); }
        .gkhl-badge { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px; }
        .mst-badge { background: #e9ecef; color: #495057; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; display: inline-block; }

        .empty-state { 
            text-align: center; 
            padding: 60px 20px; 
            color: #999; 
        }
        .empty-state i { 
            background: -webkit-linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 5rem;
            margin-bottom: 25px;
        }
        
        /* ✅ MOBILE & TABLET OPTIMIZATION */
        @media (max-width: 768px) {
            body { padding: 10px; background: #fff; }
            .card { border-radius: 0; box-shadow: none; background: transparent; }
            .card-header { border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2); }
            .card-header h2 { font-size: 1.5rem; }
            
            .kpi-card { 
                padding: 15px; 
                min-height: 110px;
                border-radius: 12px;
                background: white;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }
            
            .kpi-value { font-size: 1.5rem; }
            .kpi-card-icon { font-size: 2rem; }
            
            .filter-box { padding: 15px !important; background: white; border: none; }
            
            /* Form inputs */
            .form-label { font-size: 0.85rem; margin-bottom: 2px; }
            .btn { width: 100%; margin-top: 10px; }
            
            /* Modal adjustments */
            .modal-dialog { margin: 0.5rem; }
            .modal-content { height: 90vh !important; }
            
            .mobile-user-card {
                background: white;
                border-radius: 16px;
                box-shadow: 0 4px 25px rgba(0,0,0,0.05);
                margin-bottom: 20px;
                border: 1px solid #f0f0f0;
                transition: transform 0.2s;
            }
             .mobile-card-header {
                padding: 12px;
                border-bottom: 1px solid #f0f0f0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: #fcfcfc;
            }
            .mobile-card-body { padding: 12px; }
            .mobile-stat-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 8px;
                font-size: 0.9rem;
            }
            .mobile-stat-label { color: #666; }
            .mobile-stat-value { font-weight: 600; color: #333; }
            
            .mobile-user-card:active { transform: scale(0.98); }
        }
    </style>
</head>
<body class="<?= getBodyClass() ?>">

<div class="container-fluid">
    <div class="card mt-4">
        <div class="card-header">
            <h2><i class="fas fa-chart-line"></i> PHÂN TÍCH KPI - LOGIC NGƯỠNG N</h2>
            <p class="mb-0">Hệ thống quét từng ngày để phát hiện vi phạm ngưỡng khách/ngày</p>
        </div>
        
        <div class="card-body">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= htmlspecialchars($type ?? 'info') ?> alert-dismissible fade show">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- FORM FILTER -->
            <form method="get" class="p-4 filter-box">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-calendar-alt"></i> Tháng</label>
                        <select name="thang" id="selectThang" class="form-select" required>
                            <?php foreach ($available_months as $m): ?>
                                <option value="<?= htmlspecialchars($m) ?>" <?= ($m === ($filters['thang'] ?? '')) ? 'selected' : '' ?>>
                                    <?= date('m/Y', strtotime($m . '-01')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Từ Ngày</label>
                        <input type="date" name="tu_ngay" id="tuNgay" class="form-control" 
                               value="<?= htmlspecialchars($filters['tu_ngay'] ?? '') ?>" required>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Đến Ngày</label>
                        <input type="date" name="den_ngay" id="denNgay" class="form-control" 
                               value="<?= htmlspecialchars($filters['den_ngay'] ?? '') ?>" required>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-box"></i> Nhóm SP</label>
                        <select name="product_filter" class="form-select">
                            <option value="">-- Tất Cả --</option>
                            <?php if (!empty($available_products)): foreach ($available_products as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>" <?= ($p === ($filters['product_filter'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label fw-bold">
                            <i class="fas fa-users"></i> Ngưỡng N <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="threshold_n" class="form-control" 
                               value="<?= intval($filters['threshold_n'] ?? 5) ?>" min="1" max="100" required>
                        <small class="text-muted">khách/ngày</small>
                    </div>
                    
                    <div class="col-md-1" style="padding-top: 30px;">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Phân Tích
                        </button>
                    </div>
                    <div class="col-md-1" style="padding-top: 30px;">
                        <a href="nhanvien_kpi.php" class="btn btn-secondary w-100">
                            <i class="fas fa-sync"></i> Reset
                        </a>
                    </div>
                </div>
                
                <!-- ✅ BỘ LỌC NÂNG CAO -->
                <div class="row g-3 mt-2">
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><i class="fas fa-map-marker-alt"></i> Khu vực</label>
                        <select name="khu_vuc" id="filterKhuVuc" class="form-select">
                            <option value="">-- Tất cả --</option>
                            <?php if (!empty($available_khuvuc)): foreach ($available_khuvuc as $kv): ?>
                                <option value="<?= htmlspecialchars($kv) ?>" <?= ($kv === ($filters['khu_vuc'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($kv) ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><i class="fas fa-city"></i> Tỉnh/TP</label>
                        <select name="tinh" id="filterTinh" class="form-select">
                            <option value="">-- Tất cả --</option>
                            <?php if (!empty($available_tinh)): foreach ($available_tinh as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>" <?= ($t === ($filters['tinh'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><i class="fas fa-building"></i> Bộ phận</label>
                        <select name="bo_phan" id="filterBoPhan" class="form-select">
                            <option value="">-- Tất cả --</option>
                            <?php if (!empty($available_bophan)): foreach ($available_bophan as $bp): ?>
                                <option value="<?= htmlspecialchars($bp) ?>" <?= ($bp === ($filters['bo_phan'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($bp) ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><i class="fas fa-user-tie"></i> Chức vụ</label>
                        <select name="chuc_vu" id="filterChucVu" class="form-select">
                            <option value="">-- Tất cả --</option>
                            <?php if (!empty($available_chucvu)): foreach ($available_chucvu as $cv): ?>
                                <option value="<?= htmlspecialchars($cv) ?>" <?= ($cv === ($filters['chuc_vu'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($cv) ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                </div>
                
                <!-- ✅ BỘ LỌC NHÂN VIÊN (Row riêng) -->
                <div class="row g-3 mt-2">
                    <div class="col-md-6">
                        <label class="form-label fw-bold"><i class="fas fa-user"></i> Nhân viên</label>
                        <select name="nhan_vien" id="filterNhanVien" class="form-select">
                            <option value="">-- Tất cả nhân viên --</option>
                            <!-- Sẽ được cập nhật bởi JavaScript -->
                        </select>
                        <small class="text-muted">Chọn Khu vực/Tỉnh để lọc danh sách nhân viên</small>
                    </div>
                </div>
                
                <div class="threshold-box mt-3">
                    <strong><i class="fas fa-info-circle"></i> Logic:</strong> 
                    Hệ thống sẽ đánh dấu mỗi ngày có <strong>số khách > <?= intval($filters['threshold_n'] ?? 5) ?></strong> là vi phạm.
                    Risk Score = f(tỷ lệ ngày vi phạm, mức độ vượt, số ngày liên tục).
                </div>
            </form>

            <?php if (!$has_filtered): ?>
                <div class="empty-state">
                    <i class="fas fa-filter"></i>
                    <h4>Nhập ngưỡng N và chọn khoảng thời gian</h4>
                    <p class="text-muted">Hệ thống sẽ phân tích khi bạn nhấn "Phân Tích"</p>
                </div>
            <?php else: ?>
                <!-- KPI CARDS WITH ICONS -->
                <div class="row g-3 mt-3">
                    <div class="col-6 col-md-2">
                        <div class="kpi-card">
                            <i class="fas fa-users kpi-card-icon text-primary"></i>
                            <div class="kpi-value text-primary"><?= intval($statistics['employees_with_orders']) ?></div>
                            <div class="kpi-label">Nhân Viên</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="kpi-card">
                            <i class="fas fa-coins kpi-card-icon text-info"></i>
                            <div class="kpi-value text-info" style="font-size: 1.2rem;"><?= number_format($statistics['total_gross']) ?></div>
                            <div class="kpi-label">Tổng Gross</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="kpi-card">
                            <i class="fas fa-tags kpi-card-icon text-success"></i>
                            <div class="kpi-value text-success" style="font-size: 1.2rem;"><?= number_format($statistics['total_scheme']) ?></div>
                            <div class="kpi-label">Tổng KM</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="kpi-card">
                            <i class="fas fa-wallet kpi-card-icon text-primary"></i>
                            <div class="kpi-value text-primary" style="font-size: 1.2rem;"><?= number_format($statistics['total_net']) ?></div>
                            <div class="kpi-label">Tổng Net</div>
                        </div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="kpi-card">
                            <i class="fas fa-exclamation-triangle kpi-card-icon text-warning"></i>
                            <div class="kpi-value text-warning"><?= intval($statistics['warning_count']) ?></div>
                            <div class="kpi-label">Cảnh Báo</div>
                        </div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="kpi-card">
                            <i class="fas fa-fire kpi-card-icon text-danger"></i>
                            <div class="kpi-value text-danger"><?= intval($statistics['danger_count']) ?></div>
                            <div class="kpi-label">Nghiêm Trọng</div>
                        </div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="kpi-card">
                            <i class="fas fa-check-circle kpi-card-icon text-secondary"></i>
                            <div class="kpi-value"><?= intval($statistics['normal_count']) ?></div>
                            <div class="kpi-label">BT</div>
                        </div>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive mt-4" style="max-height: 600px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px;">
                    <table class="table table-hover" style="margin-bottom: 0;">
                        <thead style="position: sticky; top: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; z-index: 10;">
                            <tr>
                                <th class="text-center" style="width: 80px;">Mức Độ</th>
                                <th style="width: 100px;">Mã NV</th>
                                <th style="width: 200px;">Tên NV</th>
                                <th style="width: 120px;">Bộ Phận</th>
                                <th style="width: 120px;">Chức Vụ</th>
                                <th style="width: 100px;">Tỉnh</th>
                                <th style="width: 100px;">Mã QL</th>
                                <th style="width: 150px;">Tên QL</th>
                                <th class="text-end" style="width: 100px;">TB Khách/Ngày</th>
                                <th class="text-end" style="width: 100px;">Max/Ngày</th>
                                <th class="text-center" style="width: 100px;">Vi Phạm</th>
                                <th class="text-end" style="width: 80px;">Risk</th>
                                <th class="text-center" style="width: 150px;">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($kpi_data)): foreach ($kpi_data as $item): ?>
                            <?php 
                                $badge = ($item['risk_level'] === 'critical') ? 'bg-danger' : (($item['risk_level'] === 'warning') ? 'bg-warning text-dark' : 'bg-success');
                                $icon = ($item['risk_level'] === 'critical') ? '🚨' : (($item['risk_level'] === 'warning') ? '⚠️' : '✅');
                            ?>
                            <tr>
                                <td class="text-center"><span class="badge <?= $badge ?>"><?= $icon ?></span></td>
                                <td><strong><?= htmlspecialchars($item['DSRCode']) ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($item['ten_nv']) ?>
                                    <?php if (!empty($item['khu_vuc'])): ?>
                                        <div class="text-muted small"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($item['khu_vuc']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= htmlspecialchars($item['bo_phan'] ?? '-') ?></small></td>
                                <td><small><?= htmlspecialchars($item['chuc_vu'] ?? '-') ?></small></td>
                                <td><small><?= htmlspecialchars($item['base_tinh'] ?? '-') ?></small></td>
                                <td><small><?= htmlspecialchars($item['ma_nv_ql'] ?? '-') ?></small></td>
                                <td><small><?= htmlspecialchars($item['ten_nv_ql'] ?? '-') ?></small></td>
                                <td class="text-end"><?= number_format($item['avg_daily_customers'], 1) ?></td>
                                <td class="text-end text-danger"><strong><?= intval($item['max_day_customers']) ?></strong></td>
                                <td class="text-center">
                                    <?php if ($item['violation_count'] > 0): ?>
                                        <span class="violation-badge"><?= intval($item['violation_count']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success">OK</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <span style="padding: 5px 10px; border-radius: 5px; color: white; font-weight: bold; background: <?= ($item['risk_level'] === 'critical') ? '#dc3545' : (($item['risk_level'] === 'warning') ? '#ffc107' : '#28a745') ?>;">
                                        <?= intval($item['risk_score']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick='showDetail(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)'>
                                        <i class="fas fa-search"></i> Điều Tra (<?= intval($item['risk_score']) ?>đ)
                                    </button>
                                    <div class="mt-1">
                                        <?php foreach (array_slice($item['risk_reasons'], 0, 2) as $reason): ?>
                                            <span class="badge border text-dark" style="font-size: 0.65rem; background: #f8f9fa;"><?= htmlspecialchars($reason) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="9" class="text-center py-5 text-muted">Không có dữ liệu</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Trung Tâm Điều Tra (Tất cả trong 1) -->
<div class="modal fade investigation-modal" id="violationModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content overflow-hidden border-0 shadow-lg" style="height: 85vh;">
            <div class="modal-header border-0 p-3" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);">
                <h5 class="modal-title fw-bold text-white mb-0">
                    <i class="fas fa-shield-alt me-2"></i>Chi Tiết Điều Tra KPI: <span id="violationStaffName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-0 bg-light overflow-auto">
                <div id="violationContent" class="p-4"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Hiển thị chi tiết vi phạm (Level 1)
function showDetail(data) {

    if (!data) {
        alert('Không tìm thấy dữ liệu chi tiết cho ' + dsrCode + '! Vui lòng kiểm tra console (F12) để xem lỗi.');
        console.error('Data missing for code:', dsrCode);
        console.log('Available codes:', Object.keys(window.reportData));
        return;
    }

    document.getElementById('violationStaffName').textContent = data.ten_nv;
    
    const rb = data.risk_analysis.risk_breakdown || {};
    
    let html = `
        <!-- Thông tin nhân viên -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px; border-left: 5px solid #1e3a8a !important;">
            <div class="card-body p-3">
                <div class="row align-items-center">
                    <div class="col-md-auto text-center border-end pe-4">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2" style="width: 50px; height: 50px;">
                            <i class="fas fa-user-tie fa-lg"></i>
                        </div>
                        <div class="fw-bold text-dark">${data.DSRCode}</div>
                    </div>
                    <div class="col-md ps-4">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="small text-muted mb-1"><i class="fas fa-sitemap me-1"></i>Bộ phận / Chức vụ</div>
                                <div class="fw-bold text-dark">${data.bo_phan} - ${data.chuc_vu}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted mb-1"><i class="fas fa-user-shield me-1"></i>Quản lý trực tiếp</div>
                                <div class="fw-bold text-dark">${data.ten_nv_ql} <small class="text-muted">[${data.ma_nv_ql}]</small></div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted mb-1"><i class="fas fa-calendar-check me-1"></i>Ngày vào làm</div>
                                <div class="fw-bold text-dark">${data.ngay_vao_cty || 'N/A'}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="p-3 border rounded-3 bg-white shadow-sm text-center h-100 d-flex flex-column justify-content-center">
                    <div class="small text-muted fw-bold mb-1">TỔNG ĐIỂM</div>
                    <div class="h3 mb-0 fw-bold ${data.risk_level === 'critical' ? 'text-danger' : 'text-warning'}">${data.risk_score}đ</div>
                    <span class="badge ${data.risk_level === 'critical' ? 'bg-danger' : 'bg-warning text-dark'} mt-1 align-self-center">${data.risk_level.toUpperCase()}</span>
                </div>
            </div>
            <div class="col-md-9">
                <div class="row g-2">
                    <div class="col-3">
                        <div class="p-2 border rounded bg-white text-center shadow-sm">
                            <div class="small text-muted">Vượt Ngưỡng</div>
                            <div class="fw-bold text-danger">${Math.round(rb.threshold)}đ</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded bg-white text-center shadow-sm">
                            <div class="small text-muted">Chẻ/Gộp Đơn</div>
                            <div class="fw-bold text-info">${Math.round(rb.splitting)}đ</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded bg-white text-center shadow-sm">
                            <div class="small text-muted">Lạm Dụng KM</div>
                            <div class="fw-bold text-warning">${Math.round(rb.scheme)}đ</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded bg-white text-center shadow-sm">
                            <div class="small text-muted">Liên Tiếp</div>
                            <div class="fw-bold text-secondary">${Math.round(rb.consecutive)}đ</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm" style="border-radius: 15px;">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="fw-bold mb-0 text-dark">LịCH SỬ PHÁT HIỆN BẤT THƯỜNG</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0" id="violationTable">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 40px;"></th>
                            <th>Ngày Phân Tích</th>
                            <th class="text-end">Khách hàng</th>
                            <th class="text-end">Đơn hàng</th>
                            <th class="text-end">Tiền Gross</th>
                            <th class="text-end">Tiền KM</th>
                            <th class="text-end">Thực Thu (Net)</th>
                            <th class="text-end">AOV</th>
                            <th class="text-end">Tỷ lệ KM</th>
                            <th>Lý do rủi ro</th>
                        </tr>
                    </thead>
                    <tbody>
    `;
    
    if (data.risk_analysis.violation_days && data.risk_analysis.violation_days.length > 0) {
        data.risk_analysis.violation_days.forEach((v, index) => {
            const zClass = v.z_score > 3 ? 'text-danger' : (v.z_score > 2 ? 'text-warning' : '');
            
            html += `
                <tr class="clickable-row" id="row-${index}" onclick="toggleDayDetails('${index}', '${data.DSRCode}', '${v.date}')">
                    <td class="text-center"><i class="fas fa-chevron-right expand-icon" id="icon-${index}"></i></td>
                    <td><strong class="text-muted">${v.date}</strong></td>
                    <td class="text-end"><strong>${v.customers}</strong> KH</td>
                    <td class="text-end"><strong>${v.orders}</strong> Đơn</td>
                    <td class="text-end text-muted">${formatMoney(v.day_gross)}</td>
                    <td class="text-end text-success">${formatMoney(v.day_scheme)}</td>
                    <td class="text-end fw-bold text-dark">${formatMoney(v.total_amount)}</td>
                    <td class="text-end fw-bold text-primary">${formatMoney(v.day_aov)}</td>
                    <td class="text-end fw-bold text-warning">${(v.day_scheme_rate * 100).toFixed(1)}%</td>
                    <td>
                        <div class="small">
                            ${v.reasons.map(r => `<span class="badge ${r.includes('✂️') ? 'bg-info' : (r.includes('💰') ? 'bg-warning text-dark' : (r.includes('🎯') ? 'bg-success' : 'bg-light text-dark'))} border me-1">${r}</span>`).join('')}
                        </div>
                    </td>
                </tr>
                <tr class="detail-sub-row d-none" id="subrow-${index}">
                    <td colspan="8">
                        <div class="detail-container" id="container-${index}">
                            <div class="text-center py-3">
                                <div class="spinner-border spinner-border-sm text-primary"></div>
                                <span class="ms-2">Đang tải chi tiết giao dịch...</span>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        });
    } else {
        html += '<tr><td colspan="6" class="text-center py-4 text-success">Không có vi phạm nghiêm trọng</td></tr>';
    }
    
    html += `</tbody></table></div></div>`;
    
    document.getElementById('violationContent').innerHTML = html;
    new bootstrap.Modal(document.getElementById('violationModal')).show();
}

function toggleDayDetails(index, dsrCode, date) {
    const row = document.getElementById(`row-${index}`);
    const subrow = document.getElementById(`subrow-${index}`);
    const container = document.getElementById(`container-${index}`);
    const icon = document.getElementById(`icon-${index}`);

    if (!subrow.classList.contains('d-none')) {
        subrow.classList.add('d-none');
        row.classList.remove('expanded');
        return;
    }

    // Close others
    document.querySelectorAll('.detail-sub-row').forEach(el => el.classList.add('d-none'));
    document.querySelectorAll('.clickable-row').forEach(el => el.classList.remove('expanded'));

    subrow.classList.remove('d-none');
    row.classList.add('expanded');

    // Load if empty
    if (container.getAttribute('data-loaded') !== 'true') {
        const params = new URLSearchParams({
            action: 'get_customers',
            dsr_code: dsrCode,
            tu_ngay: date,
            den_ngay: date,
            product_filter: '<?= $filters['product_filter'] ?? '' ?>'
        });

        fetch(`nhanvien_kpi.php?${params}`)
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    renderInlineCustomers(container, result.data);
                    container.setAttribute('data-loaded', 'true');
                } else {
                    container.innerHTML = `<div class="alert alert-danger">${result.error}</div>`;
                }
            });
    }
}

function renderInlineCustomers(container, customers) {
    if (!customers || customers.length === 0) {
        container.innerHTML = '<div class="text-muted">Không có dữ liệu khách hàng</div>';
        return;
    }

    let html = `<h6><i class="fas fa-users me-2 text-primary"></i>Danh sách khách hàng giao dịch (${customers.length})</h6>`;
    
    customers.forEach((c, cIdx) => {
        let gkhlHtml = '';
        if (c.is_gkhl == 1) {
            const cleanLimit = (c.gk_limit || '').replace(/[^0-9]/g, '');
            const limit = parseFloat(cleanLimit);
            const progress = c.gkhl_progress || 0;
            const achievedDate = c.gkhl_achieved_date;
            
            let statusHtml = '';
            if (achievedDate) {
                statusHtml = `<div class="badge bg-success text-white mt-1"><i class="fas fa-check-circle me-1"></i>Đạt ngày ${achievedDate}</div>`;
            } else if (progress >= 100) {
                // KH đạt mức qua giao dịch với nhiều NV, không có ngày cụ thể với NV này
                statusHtml = `<div class="badge bg-success text-white mt-1"><i class="fas fa-check-circle me-1"></i>Đã đạt GKHL (${progress.toFixed(1)}%)</div>`;
            } else if (progress > 0) {
                statusHtml = `
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar ${progress >= 80 ? 'bg-warning' : 'bg-info'}" style="width: ${Math.min(progress, 100)}%"></div>
                    </div>
                    <div class="small text-muted mt-1">Tiến độ: ${progress.toFixed(1)}%</div>
                `;
            }
            
            gkhlHtml = `
                <div class="mt-2 p-2 rounded bg-warning-subtle border border-warning" style="font-size: 0.75rem;">
                    <div class="fw-bold text-dark"><i class="fas fa-handshake me-1"></i>Đăng ký GKHL:</div>
                    <div class="text-muted">${escapeHtml(c.gkhl_types)}</div>
                    ${statusHtml}
                </div>
            `;
        }
        
        html += `
            <div class="border-bottom py-3">
                <div class="row">
                    <div class="col-md-7">
                        <div class="d-flex align-items-center mb-1">
                            <div class="fw-bold text-dark h6 mb-0">${escapeHtml(c.customer_name)}</div>
                            <small class="text-muted ms-2">[${c.CustCode}]</small>
                            ${c.is_gkhl == 1 ? '<span class="badge bg-warning text-dark ms-2" style="font-size:0.6rem">GKHL</span>' : ''}
                        </div>
                        <div class="small text-muted mb-2"><i class="fas fa-map-marker-alt me-1"></i>${escapeHtml(c.customer_address)}</div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-light text-dark border"><i class="fas fa-id-card me-1"></i>MST: ${escapeHtml(c.tax_code || 'N/A')}</span>
                            <span class="badge bg-light text-dark border"><i class="fas fa-tag me-1"></i>Loại: ${escapeHtml(c.customer_type || 'N/A')}</span>
                            <span class="badge bg-light text-dark border"><i class="fas fa-users-cog me-1"></i>Nhóm: ${escapeHtml(c.customer_group || 'N/A')}</span>
                        </div>
                        ${gkhlHtml}
                    </div>
                    <div class="col-md-5 text-end">
                        <div class="fw-bold text-dark h5 mb-1">${formatMoney(c.total_amount)}</div>
                        <div class="small text-muted mb-2">Đơn quét: Gross ${formatMoney(c.total_gross)} | KM ${formatMoney(c.total_scheme)}</div>
                        
                        <div class="p-2 rounded border bg-light d-inline-block text-start" style="font-size: 0.7rem; min-width: 200px;">
                            <div class="text-muted fw-bold border-bottom mb-1 pb-1">THỐNG KÊ LŨY KẾ THÁNG (MTD)</div>
                            <div class="d-flex justify-content-between">
                                <span>Thực thu (Net):</span>
                                <span class="fw-bold text-primary">${formatMoney(c.mtd_net)}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Tiền hàng (Gross):</span>
                                <span class="text-dark">${formatMoney(c.mtd_gross)}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Khuyến mãi:</span>
                                <span class="text-success">${formatMoney(c.mtd_scheme)}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-2 text-nowrap overflow-auto pb-1" style="max-width: 100%;">
        `;

        if (c.orders) {
            c.orders.forEach((o, oIdx) => {
                const orderId = `order-${cIdx}-${oIdx}-${Math.random().toString(36).substr(2, 5)}`;
                html += `
                    <div class="d-inline-block me-2">
                        <span class="order-chip pointer mb-1" onclick="toggleOrderProducts('${orderId}', '${o.order_number}')" title="Gross: ${formatMoney(o.gross)} | KM: ${formatMoney(o.scheme)}">
                            <i class="fas fa-file-invoice me-1"></i>${o.order_number} (${formatMoney(o.amount)})
                        </span>
                        <div id="${orderId}" class="order-products-container border border-secondary shadow-sm">
                            <div class="text-center py-2"><i class="fas fa-spinner fa-spin"></i></div>
                        </div>
                    </div>
                `;
            });
        }
        html += `</div></div>`;
    });
    
    container.innerHTML = html;
}

function toggleOrderProducts(elementId, orderNumber) {
    const container = document.getElementById(elementId);
    if (container.style.display === 'block') {
        container.style.display = 'none';
        return;
    }

    // Hide other containers in the same customer row? No, maybe keep it simple.
    container.style.display = 'block';

    if (container.getAttribute('data-loaded') !== 'true') {
        const params = new URLSearchParams({
            action: 'get_order_products',
            order_number: orderNumber,
            product_filter: '<?= $filters['product_filter'] ?? '' ?>'
        });

        fetch(`nhanvien_kpi.php?${params}`)
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    let phtml = '<table class="table table-sm table-borderless mb-0" style="font-size:0.75rem">';
                    phtml += '<tr class="border-bottom"><th>Mã SP</th><th>Loại</th><th class="text-center">SL</th><th class="text-end">Gross</th><th class="text-end">KM</th><th class="text-end">Net</th></tr>';
                    result.data.forEach(p => {
                        let amount = parseFloat(p.TotalNetAmount);
                        let gross = parseFloat(p.TotalGrossAmount || 0);
                        let scheme = parseFloat(p.TotalSchemeAmount || 0);
                        phtml += `<tr>
                            <td>${escapeHtml(p.ProductCode)}</td>
                            <td class="text-center badge ${p.SaleType === 'S' ? 'bg-primary' : 'bg-warning text-dark'} p-0 px-1" style="font-size:0.6rem">${p.SaleType}</td>
                            <td class="text-center">${p.Quantity}</td>
                            <td class="text-end">${formatMoney(gross)}</td>
                            <td class="text-end text-success">${formatMoney(scheme)}</td>
                            <td class="text-end fw-bold">${formatMoney(amount)}</td>
                        </tr>`;
                    });
                    phtml += '</table>';
                    container.innerHTML = phtml;
                    container.setAttribute('data-loaded', 'true');
                } else {
                    container.innerHTML = `<small class="text-danger">${result.error}</small>`;
                }
            });
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Draggable Modal Logic (Vanilla JS)
document.addEventListener('mousedown', function (e) {
    const header = e.target.closest('.modal-header');
    if (!header) return;
    
    const modalContent = header.closest('.modal-content');
    if (!modalContent) return;
    
    // Check if we are clicking on Close button
    if (e.target.closest('.btn-close')) return;

    let initialX = e.clientX;
    let initialY = e.clientY;
    
    const rect = modalContent.getBoundingClientRect();
    let offsetX = initialX - rect.left;
    let offsetY = initialY - rect.top;

    function onMouseMove(e) {
        modalContent.style.position = 'absolute';
        modalContent.style.margin = '0';
        modalContent.style.left = (e.clientX - offsetX) + 'px';
        modalContent.style.top = (e.clientY - offsetY) + 'px';
    }

    function onMouseUp() {
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
    }

    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
});

function formatMoney(val) {
    const n = parseFloat(val);
    if (isNaN(n)) return '0đ';
    return n.toLocaleString('vi-VN') + 'đ';
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectThang = document.getElementById('selectThang');
    const tuNgayInput = document.getElementById('tuNgay');
    const denNgayInput = document.getElementById('denNgay');

    // Dữ liệu khoảng ngày thực tế từ Controller
    const actualDateRanges = <?= json_encode($date_ranges ?? []) ?>;

    // 1. Xử lý khi thay đổi Tháng/Năm
    selectThang.addEventListener('change', function() {
        const monthVal = this.value; // Định dạng YYYY-MM
        if (!monthVal) return;

        let firstDate, lastDate;
        
        if (actualDateRanges[monthVal]) {
            firstDate = actualDateRanges[monthVal].min_date;
            lastDate = actualDateRanges[monthVal].max_date;
        } else {
            const [year, month] = monthVal.split('-').map(Number);
            const daysInMonth = new Date(year, month, 0).getDate();
            firstDate = `${monthVal}-01`;
            lastDate = `${monthVal}-${daysInMonth}`;
        }

        // Cập nhật giá trị
        tuNgayInput.value = firstDate;
        denNgayInput.value = lastDate;

        // Cập nhật min/max
        tuNgayInput.min = firstDate;
        tuNgayInput.max = lastDate;
        denNgayInput.min = firstDate;
        denNgayInput.max = lastDate;
    });

    // 2. Ràng buộc Từ Ngày <= Đến Ngày
    tuNgayInput.addEventListener('change', function() {
        if (this.value > denNgayInput.value) {
            denNgayInput.value = this.value;
        }
        // Ngày kết thúc không thể nhỏ hơn ngày bắt đầu
        denNgayInput.min = this.value;
    });

    denNgayInput.addEventListener('change', function() {
        if (this.value < tuNgayInput.value) {
            tuNgayInput.value = this.value;
        }
    });

    // Kích hoạt giới hạn ngay khi load trang
    if (selectThang.value) {
        const monthVal = selectThang.value;
        let firstDate, lastDate;
        
        if (actualDateRanges[monthVal]) {
            firstDate = actualDateRanges[monthVal].min_date;
            lastDate = actualDateRanges[monthVal].max_date;
        } else {
            const [year, month] = monthVal.split('-').map(Number);
            const daysInMonth = new Date(year, month, 0).getDate();
            firstDate = `${monthVal}-01`;
            lastDate = `${monthVal}-${daysInMonth}`;
        }
        
        tuNgayInput.min = firstDate;
        tuNgayInput.max = lastDate;
        denNgayInput.min = firstDate;
        denNgayInput.max = lastDate;
    }
});

// ✅ CASCADING DROPDOWNS: Khu vực → Tỉnh → Nhân viên
document.addEventListener('DOMContentLoaded', function() {
    const filterKhuVuc = document.getElementById('filterKhuVuc');
    const filterTinh = document.getElementById('filterTinh');
    const filterBoPhan = document.getElementById('filterBoPhan');
    const filterChucVu = document.getElementById('filterChucVu');
    const filterNhanVien = document.getElementById('filterNhanVien');
    
    // Lưu giá trị selected ban đầu
    const selectedNhanVien = '<?= htmlspecialchars($filters['nhan_vien'] ?? '') ?>';
    
    // Hàm cập nhật dropdown Tỉnh theo Khu vực
    function updateTinhDropdown() {
        const khuVuc = filterKhuVuc?.value || '';
        
        fetch(`nhanvien_kpi.php?action=getTinhByKhuVuc&khu_vuc=${encodeURIComponent(khuVuc)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && filterTinh) {
                    const currentVal = filterTinh.value;
                    filterTinh.innerHTML = '<option value="">-- Tất cả --</option>';
                    data.data.forEach(tinh => {
                        const opt = document.createElement('option');
                        opt.value = tinh;
                        opt.textContent = tinh;
                        if (tinh === currentVal) opt.selected = true;
                        filterTinh.appendChild(opt);
                    });
                }
            })
            .catch(console.error);
    }
    
    // Hàm cập nhật dropdown Nhân viên theo các filter
    function updateNhanVienDropdown() {
        const khuVuc = filterKhuVuc?.value || '';
        const tinh = filterTinh?.value || '';
        const boPhan = filterBoPhan?.value || '';
        const chucVu = filterChucVu?.value || '';
        
        const params = new URLSearchParams({
            action: 'getNhanVienByFilters',
            khu_vuc: khuVuc,
            tinh: tinh,
            bo_phan: boPhan,
            chuc_vu: chucVu
        });
        
        fetch(`nhanvien_kpi.php?${params}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && filterNhanVien) {
                    filterNhanVien.innerHTML = '<option value="">-- Tất cả nhân viên --</option>';
                    data.data.forEach(nv => {
                        const opt = document.createElement('option');
                        opt.value = nv.ma_nv;
                        opt.textContent = `${nv.ho_ten} (${nv.ma_nv})`;
                        if (nv.ma_nv === selectedNhanVien) opt.selected = true;
                        filterNhanVien.appendChild(opt);
                    });
                }
            })
            .catch(console.error);
    }
    
    // Event listeners
    if (filterKhuVuc) {
        filterKhuVuc.addEventListener('change', () => {
            updateTinhDropdown();
            updateNhanVienDropdown();
        });
    }
    
    if (filterTinh) {
        filterTinh.addEventListener('change', updateNhanVienDropdown);
    }
    
    if (filterBoPhan) {
        filterBoPhan.addEventListener('change', updateNhanVienDropdown);
    }
    
    if (filterChucVu) {
        filterChucVu.addEventListener('change', updateNhanVienDropdown);
    }
    
    // Load danh sách nhân viên ban đầu
    updateNhanVienDropdown();
});
</script>
</body>
</html>