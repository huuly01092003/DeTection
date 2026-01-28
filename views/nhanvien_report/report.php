<?php
/**
 * ✅ VIEW BÁO CÁO NHÂN VIÊN - WITH SHARED NAVBAR
 * File: views/nhanvien_report/report.php
 * Giống cấu trúc views/dskh/list.php
 */

// ✅ Load navbar loader (tự động chọn navbar phù hợp)
require_once __DIR__ . '/../components/navbar_loader.php';

// ✅ Render navbar với thông tin bổ sung
$additionalInfo = [
    'period' => !empty($thang) ? 'Tháng ' . date('m/Y', strtotime($thang . '-01')) : '',
    'breadcrumb' => [
        ['label' => 'Dashboard', 'url' => 'dashboard.php'],
        ['label' => 'Báo Cáo', 'url' => '#'],
        ['label' => 'Kiểm Soát Nhân Viên', 'url' => '']
    ]
];

renderSmartNavbar('nhanvien_report', $additionalInfo);

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
    <title>Báo Cáo Kiểm Soát - Doanh Số Nhân Viên</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- ✅ Load viewer restrictions nếu cần -->
    <?php if ($isViewer): ?>
    <link href="assets/css/viewer_restrictions.css" rel="stylesheet">
    <?php endif; ?>

    <!-- ✅ Load Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            padding: 20px; 
        }
        .card { 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.3); 
            margin-bottom: 25px; 
        }
        .card-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 30px; 
            border-radius: 20px 20px 0 0; 
        }
        .filter-section { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
        }
        .info-box { 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            text-align: center; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.08); 
        }
        .info-box h5 { 
            margin-bottom: 5px; 
            font-weight: 700; 
            color: #667eea; 
        }
        .info-box small { color: #666; }
        
        .kpi-table thead th { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white !important; 
            font-weight: 700; 
            border: none; 
            padding: 15px; 
            text-align: center; 
            position: sticky; 
            top: 0; 
            z-index: 10; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
        }
        .kpi-table tbody tr { 
            border-bottom: 1px solid #e0e0e0; 
            transition: background 0.2s; 
        }
        .kpi-table tbody tr:hover { 
            background: rgba(102, 126, 234, 0.05); 
        }
        
        .bg-red-highlight { 
            background: #fff5f5 !important; 
            border-left: 5px solid #dc3545 !important; 
        }
        .bg-orange-highlight { 
            background: #fffaf0 !important; 
            border-left: 5px solid #ff9800 !important; 
        }
        .bg-none-highlight { 
            background: #f0f7ff !important; 
            border-left: 5px solid #667eea !important;
        }
        
        /* 📜 Dòng lịch sử - Mờ hơn để phân biệt với tháng chính */
        .history-row {
            background-color: #fcfcfc !important;
            opacity: 0.85;
            border-left: 2px solid #dee2e6 !important;
        }
        .history-row.bg-danger-subtle {
            background-color: #fffafb !important;
            border-left: 2px solid #ffccd5 !important;
        }
        
        .legend { 
            display: flex; 
            gap: 20px; 
            margin-bottom: 20px; 
            padding: 15px; 
            background: #f8f9fa; 
            border-radius: 10px; 
            flex-wrap: wrap; 
        }
        .legend-item { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .legend-color { 
            width: 40px; 
            height: 30px; 
            border-radius: 5px; 
            border-left: 4px solid; 
        }
        
        .btn-group-custom { 
            margin-top: 20px; 
            display: flex; 
            gap: 10px; 
            flex-wrap: wrap; 
        }
        
        .debug-info { 
            background: #f8f9fa; 
            border-left: 4px solid #667eea; 
            padding: 10px 15px; 
            margin-top: 20px; 
            border-radius: 4px; 
            font-size: 0.9rem; 
            color: #555; 
        }
        
        .empty-state { 
            text-align: center; 
            padding: 60px 20px; 
            color: #999; 
        }
        .empty-state i { 
            font-size: 4rem; 
            color: #ddd; 
            margin-bottom: 20px; 
        }
    </style>
</head>
<body class="<?= getBodyClass() ?>">

<div class="container-fluid">
    <div class="card mt-4 mb-4">
        <div class="card-header">
            <h2><i class="fas fa-chart-bar"></i> KIỂM SOÁT DOANH SỐ NHÂN VIÊN</h2>
            <p class="mb-0 mt-2" style="opacity: 0.9;">Phân tích và phát hiện bất thường trong hoạt động bán hàng</p>
        </div>
        
        <div class="card-body">
            <!-- ✅ Message Alert -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= htmlspecialchars($type ?? 'info') ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- ✅ Form Filter -->
            <form id="filterForm" method="get" class="filter-section">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-calendar-alt"></i> Tháng</label>
                        <select name="thang" id="selectThang" class="form-select" required>
                            <?php foreach ($available_months as $m): ?>
                                <option value="<?= htmlspecialchars($m) ?>" <?= ($m === $thang) ? 'selected' : '' ?>>
                                    Tháng <?= date('m/Y', strtotime($m . '-01')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php
                        // Xác định min/max cho input date dựa trên tháng đã chọn
                        $current_range = $date_ranges[$thang] ?? null;
                        $min_date = $current_range ? $current_range['min_date'] : ($thang . '-01');
                        $max_date = $current_range ? $current_range['max_date'] : date('Y-m-t', strtotime($thang . '-01'));
                    ?>
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Từ Ngày</label>
                        <input type="date" name="tu_ngay" id="tuNgay" class="form-control" 
                               value="<?= htmlspecialchars($tu_ngay) ?>" 
                               min="<?= $min_date ?>" max="<?= $max_date ?>" required>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Đến Ngày</label>
                        <input type="date" name="den_ngay" id="denNgay" class="form-control" 
                               value="<?= htmlspecialchars($den_ngay) ?>" 
                               min="<?= $min_date ?>" max="<?= $max_date ?>" required>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-percentage"></i> Hệ Số NV</label>
                        <input type="number" name="he_so" id="heSo" class="form-control" 
                               value="<?= htmlspecialchars($he_so ?? 1.5) ?>" 
                               step="0.1" min="1" max="5" placeholder="1.5">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-history"></i> Tháng Trước</label>
                        <select name="so_thang_lich_su" class="form-select">
                            <option value="0" <?= (($so_thang_lich_su ?? 0) == 0) ? 'selected' : '' ?>>Không</option>
                            <?php for ($i = 1; $i <= ($max_history_months ?? 0); $i++): ?>
                                <option value="<?= $i ?>" <?= (($so_thang_lich_su ?? 0) == $i) ? 'selected' : '' ?>>
                                    <?= $i ?> Tháng
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100" title="Rà Soát">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div class="col-md-1">
                        <a href="nhanvien_report.php" class="btn btn-secondary w-100" title="Reset">
                            <i class="fas fa-sync"></i>
                        </a>
                    </div>
                </div>
            </form>

            <!-- ✅ EMPTY STATE - Khi chưa filter -->
            <?php if (!$has_filtered): ?>
                <div class="empty-state">
                    <i class="fas fa-filter"></i>
                    <h4>Vui lòng chọn khoảng ngày để bắt đầu</h4>
                    <p class="text-muted">Hệ thống sẽ tính toán dữ liệu khi bạn nhấn "Rà Soát"</p>
                </div>
            <?php else: ?>
                <!-- ✅ Nav Tabs -->
                <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="table-tab" data-bs-toggle="tab" data-bs-target="#table-pane" type="button" role="tab" aria-controls="table-pane" aria-selected="true">
                            <i class="fas fa-table me-2"></i>Bảng Dữ Liệu
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="chart-tab" data-bs-toggle="tab" data-bs-target="#chart-pane" type="button" role="tab" aria-controls="chart-pane" aria-selected="false" onclick="renderRevenueCharts()">
                            <i class="fas fa-chart-line me-2"></i>Biểu Đồ So Sánh
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="reportTabsContent">
                    <!-- ✅ Tab 1: Bảng Dữ Liệu -->
                    <div class="tab-pane fade show active" id="table-pane" role="tabpanel" aria-labelledby="table-tab">
                        <!-- ✅ Tổng Quan -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="info-box">
                            <small><i class="fas fa-calendar-days"></i> Số Ngày</small>
                            <h5><?= intval($so_ngay) ?> ngày</h5>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <small><i class="fas fa-money-bill-wave"></i> Tổng Tiền Kỳ (Tháng)</small>
                            <h5><?= number_format($tong_tien_ky, 0) ?>đ</h5>
                            <small class="text-muted">Chỉ tính tháng: <?= date('m/Y', strtotime($thang . '-01')) ?></small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <small><i class="fas fa-hourglass-half"></i> Tổng Tiền Khoảng</small>
                            <h5><?= number_format($tong_tien_khoang, 0) ?>đ</h5>
                            <small class="text-muted"><?= $tu_ngay ?> ~ <?= $den_ngay ?></small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <small><i class="fas fa-exclamation-triangle"></i> Kết Quả Chung</small>
                            <h5><span class="badge bg-warning text-dark"><?= number_format($ket_qua_chung * 100, 2) ?>%</span></h5>
                            <small class="text-muted">Khoảng/Kỳ</small>
                        </div>
                    </div>
                </div>

                <!-- ✅ Tỉ lệ Nghi Vấn -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="info-box">
                            <small><i class="fas fa-eye"></i> Tỉ Lệ Hoàn Thành Nghi Vấn (Kết quả chung × <?= number_format($he_so ?? 1.5, 1) ?>)</small>
                            <h5><span class="badge bg-danger"><?= number_format($ty_le_nghi_van * 100, 2) ?>%</span></h5>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-box">
                            <small><i class="fas fa-user-secret"></i> Số Người Nghi Vấn Gian Lận</small>
                            <h5><span class="badge bg-danger" style="font-size: 18px;"><?= $tong_nghi_van ?> người</span></h5>
                        </div>
                    </div>
                </div>

                <!-- ✅ Legend -->
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background: linear-gradient(90deg, #fee 0%, #fdd 100%); border-left-color: #dc3545;"></div>
                        <span><strong>Đỏ:</strong> Top <?= $top_threshold ?> Gian Lận Nghiêm Trọng</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: linear-gradient(90deg, #fff5e6 0%, #ffe6cc 100%); border-left-color: #ff9800;"></div>
                        <span><strong>Cam:</strong> Nghi Vấn Gian Lận Còn Lại (<?= max(0, $tong_nghi_van - $top_threshold) ?> người)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #f0f7ff; border-left-color: #667eea;"></div>
                        <span><strong>Xanh nhạt:</strong> Tháng Hiện Tại (OK)</span>
                    </div>
                </div>

                <!-- ✅ Bảng Báo Cáo -->
                <div class="table-responsive" style="max-height: 600px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px;">
                    <table class="table table-hover kpi-table" style="margin-bottom: 0;">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 60px;">#</th>
                                <th style="width: 100px;">Mã NV</th>
                                <th style="width: 150px;">Tên Nhân Viên</th>
                                <th style="width: 120px;">Bộ Phận</th>
                                <th style="width: 100px;">Chức Vụ</th>
                                <th style="width: 80px;">Base Tỉnh</th>
                                <th style="width: 80px;">Mã NVQL</th>
                                <th style="width: 120px;">Tên NVQL</th>
                                <th style="width: 100px;">Ngày Vào Làm</th>
                                <th class="text-end">DS Tháng Tìm Kiếm</th>
                                <th class="text-end">DS Tiến Độ Tìm Kiếm</th>
                                <th class="text-end">% Tiến Độ</th>
                                <th class="text-center">Chi Tiết</th>
                                <th class="text-end">Trạng Thái</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($report)): ?>
                            <?php foreach ($report as $r): ?>
                            <?php
                                if (isset($r['highlight_type'])) {
                                    if ($r['highlight_type'] === 'red') {
                                        $row_class = 'bg-red-highlight';
                                    } elseif ($r['highlight_type'] === 'orange') {
                                        $row_class = 'bg-orange-highlight';
                                    } else {
                                        $row_class = 'bg-none-highlight';
                                    }
                                } else {
                                    $row_class = ''; // Fallback
                                }
                                
                                $ma_nv = $r['ma_nv'];
                                $rowId = 'row_' . md5($ma_nv);
                                $has_history = !empty($history_data);
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td class="text-center fw-bold">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <?php if ($has_history): ?>
                                            <button class="btn btn-sm btn-link text-decoration-none p-0 me-2" 
                                                    onclick="toggleHistory('<?= $rowId ?>', this)"
                                                    title="Xem tháng trước">
                                                <i class="fas fa-plus-square text-primary"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex flex-column">
                                            <?php if ($r['rank'] > 0): ?>
                                                <span class="badge <?= (isset($r['highlight_type']) && $r['highlight_type'] === 'red') ? 'bg-danger' : 'bg-warning text-dark' ?>">#<?= $r['rank'] ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark">-</span>
                                            <?php endif; ?>
                                            <small class="text-muted" style="font-size: 0.7em;"><?= date('m/y', strtotime($thang)) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><strong><?= htmlspecialchars($r['ma_nv']) ?></strong></td>
                                <td><?= htmlspecialchars($r['ten_nv'] ?? '') ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($r['bo_phan'] ?? 'N/A') ?></span></td>
                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($r['chuc_vu'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($r['base_tinh'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['ma_nv_ql'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['ten_nv_ql'] ?? '') ?></td>
                                <td><?= !empty($r['ngay_vao_cty']) ? date('d/m/Y', strtotime($r['ngay_vao_cty'])) : '' ?></td>
                                <td class="text-end"><?= number_format($r['ds_tim_kiem'], 0) ?>đ</td>
                                <td class="text-end"><?= number_format($r['ds_tien_do'], 0) ?>đ</td>
                                <td class="text-end">
                                    <strong class="<?= ($r['ty_le'] >= $ty_le_nghi_van) ? 'text-danger' : 'text-success' ?>">
                                        <?= number_format($r['ty_le'] * 100, 2) ?>%
                                    </strong>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary" 
                                            type="button"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#detailModal"
                                            onclick="showReportDetails('<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>', '<?= htmlspecialchars(json_encode($tong_tien_ky_detailed), ENT_QUOTES) ?>')">
                                        <i class="fas fa-eye"></i> Xem
                                    </button>
                                </td>
                                <td class="text-end">
                                    <?php if (isset($r['is_suspect']) && $r['is_suspect']): ?>
                                        <div class="d-flex flex-column align-items-end">
                                            <span class="badge bg-danger">⚠️ NGHI VẤN</span>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-success">✅ OK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <!-- Dòng Lịch Sử -->
                             <?php if ($has_history && isset($history_data)): ?>
                                <?php foreach ($history_data as $hMonth => $hData): ?>
                                    <?php 
                                        $hEmp = $hData['report'][$ma_nv] ?? null;
                                        if ($hEmp): 
                                            $hClass = ($hEmp['is_nghi_van']) ? 'bg-danger-subtle' : 'table-warning';
                                    ?>
                                    <tr class="history-row <?= $rowId ?> <?= $hClass ?>" style="display: none;">
                                        <td class="text-center">
                                            <small class="fw-bold text-muted"><?= date('m/y', strtotime($hMonth)) ?></small>
                                        </td>
                                        <td colspan="8" class="text-end text-muted fst-italic">
                                            <small>Lịch sử (<?= date('d/m', strtotime($hData['tu_ngay'])) ?> - <?= date('d/m', strtotime($hData['den_ngay'])) ?>)</small>
                                        </td>
                                        <td class="text-end text-muted"><?= number_format($hEmp['ds_tim_kiem'], 0) ?>đ</td>
                                        <td class="text-end text-muted"><?= number_format($hEmp['ds_tien_do'], 0) ?>đ</td>
                                        <td class="text-end">
                                            <span class="<?= ($hEmp['is_nghi_van']) ? 'text-danger' : 'text-success' ?>">
                                                <?= number_format($hEmp['ty_le'] * 100, 2) ?>%
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-secondary" 
                                                    type="button"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#detailModal"
                                                    onclick="showReportDetails('<?= htmlspecialchars(json_encode($hEmp), ENT_QUOTES) ?>', '<?= htmlspecialchars(json_encode($hData['stats_detailed'] ?? []), ENT_QUOTES) ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($hEmp['is_nghi_van']): ?>
                                                <div class="d-flex flex-column align-items-end">
                                                    <small class="text-danger fw-bold">Nghi Vấn</small>
                                                </div>
                                            <?php else: ?>
                                                <small class="text-success">OK</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="14" class="text-center text-muted py-5">Không có dữ liệu</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- ✅ Debug Info -->
                <?php if (!empty($debug_info)): ?>
                <div class="debug-info">
                    <strong>📊 Thông Tin:</strong> <?= htmlspecialchars($debug_info) ?>
                </div>
                <?php endif; ?>
                    </div><!-- End #table-pane -->

                    <!-- ✅ Tab 2: Biểu Đồ So Sánh -->
                    <div class="tab-pane fade" id="chart-pane" role="tabpanel" aria-labelledby="chart-tab">
                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0 text-primary"><i class="fas fa-chart-bar me-2"></i>So Sánh Dữ Liệu Các Tháng</h5>
                                        <div class="d-flex gap-2 align-items-center">
                                            <div class="input-group input-group-sm" style="width: auto;">
                                                <span class="input-group-text"><i class="fas fa-list-ol"></i> Hiển thị</span>
                                                <select class="form-select" id="chartLimit" onchange="renderRevenueCharts()">
                                                    <option value="20" selected>Top 20</option>
                                                    <option value="30">Top 30</option>
                                                    <option value="50">Top 50</option>
                                                    <option value="100">Top 100</option>
                                                    <option value="0">Tất cả</option>
                                                </select>
                                            </div>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <input type="radio" class="btn-check" name="chartMetric" id="metricRevenue" value="revenue" checked onchange="renderRevenueCharts()">
                                                <label class="btn btn-outline-primary" for="metricRevenue"><i class="fas fa-money-bill-wave"></i> </label>
    
                                                <input type="radio" class="btn-check" name="chartMetric" id="metricEmployee" value="employee" onchange="renderRevenueCharts()">
                                                <label class="btn btn-outline-primary" for="metricEmployee"><i class="fas fa-users"></i> </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div id="chartContainer" style="min-height: 400px; position: relative; padding: 15px;">
                                            <canvas id="revenueBarChart"></canvas>
                                        </div>
                                </div>
                            </div>
                            <!-- ✅ BIỂU ĐỒ HEATMAP PHÂN BỐ DOANH SỐ -->
                            <div class="col-md-12 mb-4">
                                <div class="card shadow-sm border-warning">
                                    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><i class="fas fa-th me-2"></i>Bản Đồ Nhiệt - Phân Bố Doanh Số NV Theo Ngày</h5>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-dark active" id="btnHeatmapCompare">
                                                <i class="fas fa-calendar-alt"></i> Biểu Đồ Nhiệt Các Tháng So Sánh
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-warning mb-3">
                                            <i class="fas fa-lightbulb"></i> <strong>Gợi ý:</strong> 
                                            Ô <span class="badge bg-danger">màu đỏ</span> = doanh số cao bất thường. 
                                            NV có nhiều ô trắng + 1-2 ô đỏ = dấu hiệu dồn đơn.
                                            <br><small class="text-muted" id="heatmapPeriodInfo"></small>
                                        </div>
                                        <div id="heatmapContainer" style="overflow-x: auto;">
                                            <div id="heatmapLoading" class="text-center py-5">
                                                <i class="fas fa-spinner fa-spin fa-2x text-warning"></i>
                                                <p class="mt-2">Đang tạo bản đồ nhiệt...</p>
                                            </div>
                                            <div id="heatmapTable" style="display: none;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- End #chart-pane -->
                </div><!-- End .tab-content -->
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ✅ Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title" id="detailModalLabel">Chi Tiết Nhân Viên - <span id="modalEmpName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalContent" style="max-height: 80vh; overflow-y: auto;">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ✅ GLOBAL VARIABLES
let currentEmployeeData = null;
let currentBenchmark = null;

async function showReportDetails(jsonData, jsonBenchmark) {
    try {
        const data = JSON.parse(jsonData);
        const benchmark = JSON.parse(jsonBenchmark);
        
        currentEmployeeData = data;
        currentBenchmark = benchmark;
        
        document.getElementById('modalEmpName').textContent = data.ten_nv + ' (' + data.ma_nv + ')';
        
        renderEmployeeInfoTab();
        
    } catch (e) {
        console.error('Error parsing data:', e);
        document.getElementById('modalContent').innerHTML = '<p class="text-danger"><strong>Lỗi tải dữ liệu:</strong> ' + e.message + '</p>';
    }
}

function renderEmployeeInfoTab() {
    const data = currentEmployeeData;
    const benchmark = currentBenchmark;
    
    // --- 📊 CHUẨN HÓA DỮ LIỆU ---
    const totalSalesRange_NV = data.ds_tien_do || 0;
    const totalSalesMonth_NV = data.ds_tong_thang_nv || 0;
    const maxDailyRange_NV = data.ds_ngay_cao_nhat_nv_khoang || 0;
    const activeDaysRange_NV = data.so_ngay_co_doanh_so_khoang || 0;
    const activeDaysMonth_NV = data.so_ngay_co_doanh_so_thang || 0;

    // Mới: Số đơn và Số khách
    const ordersRange_NV = data.so_don_khoang || 0;
    const customersRange_NV = data.so_kh_khoang || 0;
    const aovRange_NV = ordersRange_NV > 0 ? (totalSalesRange_NV / ordersRange_NV) : 0;

    const daysInPeriod = benchmark.so_ngay || 1;
    const daysInMonth = benchmark.so_ngay_trong_thang || 1;

    // --- 🛡️ LOGIC SO SÁNH (DAILY VS DAILY) ---
    const dailyAvgActual_NV = activeDaysRange_NV > 0 ? (totalSalesRange_NV / activeDaysRange_NV) : 0;
    const dailyAvgSystem_Khoang = benchmark.ds_tb_chung_khoang || 0;
    const systemMaxAvg_Khoang = benchmark.ds_ngay_cao_nhat_tb_khoang || 0;

    // --- 🔍 CHỈ SỐ BẤT THƯỜNG ---
    const concentrationIndex = totalSalesRange_NV > 0 ? (maxDailyRange_NV / totalSalesRange_NV * 100) : 0;
    const selectionWeight = totalSalesMonth_NV > 0 ? (totalSalesRange_NV / totalSalesMonth_NV * 100) : 0;
    const maxVsSystemAvg = dailyAvgSystem_Khoang > 0 ? (maxDailyRange_NV / dailyAvgSystem_Khoang) : 0;

    // 4. Chỉ số ổn định (Stability Index) - Đơn giản hóa: Nếu Doanh số TB ngày ≈ Max ngày thì rất ổn định
    const stabilityIndex = maxDailyRange_NV > 0 ? (dailyAvgActual_NV / maxDailyRange_NV * 100) : 0;

    const formatCurrency = (val) => {
        if (isNaN(val) || val === 0) return '0đ';
        return Math.round(val).toLocaleString('vi-VN') + 'đ';
    };
    
    const getBadge = (val, threshold, label, suffix = '', reverse = false) => {
        let color = 'bg-success';
        if (reverse) {
            if (val <= threshold / 2) color = 'bg-danger';
            else if (val <= threshold) color = 'bg-warning text-dark';
        } else {
            if (val >= threshold * 2) color = 'bg-danger';
            else if (val >= threshold) color = 'bg-warning text-dark';
        }
        return `<span class="badge ${color}">${val.toFixed(1)}${suffix} ${label}</span>`;
    };

    let html = `
        <ul class="nav nav-tabs mb-3" id="detailTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info-content" type="button" onclick="renderEmployeeInfoTab()">
                    <i class="fas fa-microscope"></i> Phân Tích Bất Thường
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders-content" type="button" onclick="renderOrdersTab()">
                    <i class="fas fa-shopping-cart"></i> Chi Tiết Đơn Hàng
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="detailTabContent">
            <div class="tab-pane fade show active" id="info-content">
                <!-- 1. Thông tin cơ bản -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="p-3 border rounded shadow-sm bg-light">
                            <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-user-circle"></i> Nhân Sự & Chất Lượng Bán Hàng</h6>
                            <div class="row g-2">
                                <div class="col-md-6"><strong>Họ Tên:</strong> ${escapeHtml(data.ten_nv)}</div>
                                <div class="col-md-6"><strong>Bộ Phận:</strong> <span class="badge bg-secondary">${escapeHtml(data.bo_phan || 'N/A')}</span></div>
                                
                                <div class="col-md-3 text-primary"><strong><i class="fas fa-box"></i> Tổng đơn:</strong> ${ordersRange_NV} đơn</div>
                                <div class="col-md-3 text-primary"><strong><i class="fas fa-users"></i> Số khách:</strong> ${customersRange_NV} KH</div>
                                <div class="col-md-3 text-primary"><strong><i class="fas fa-tag"></i> AOV (TB đơn):</strong> ${formatCurrency(aovRange_NV)}</div>
                                <div class="col-md-3 text-success"><strong><i class="fas fa-shield-alt"></i> GKHL:</strong> ${formatCurrency(data.ds_gkhl_khoang || 0)}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- 2. Chỉ số hiệu suất & Bất thường -->
                    <div class="col-md-7">
                        <div class="p-3 border rounded shadow-sm h-100">
                            <h6 class="text-danger border-bottom pb-2 mb-3"><i class="fas fa-exclamation-triangle"></i> Chỉ Số Cảnh Báo Bất Thường</h6>
                            
                            <div class="mb-3">
                                <label class="d-flex justify-content-between mb-1">
                                    <span><strong>Tỷ lệ tập trung (Max KH / Khoảng):</strong></span>
                                    <span>${getBadge((data.ds_kh_lon_nhat_khoang / totalSalesRange_NV * 100) || 0, 70, 'Tập trung', '%')}</span>
                                </label>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar ${(data.ds_kh_lon_nhat_khoang / totalSalesRange_NV) > 0.7 ? 'bg-danger' : 'bg-warning'}" role="progressbar" style="width: ${(data.ds_kh_lon_nhat_khoang / totalSalesRange_NV * 100) || 0}%"></div>
                                </div>
                                <small class="text-muted">1 Khách hàng chiếm quá nhiều doanh số trong khoảng rà soát.</small>
                            </div>

                            <div class="mb-3">
                                <label class="d-flex justify-content-between mb-1">
                                    <span><strong>Tỷ lệ dồn ngày (Max Day / Khoảng):</strong></span>
                                    <span>${getBadge(concentrationIndex, 80, 'Dồn ngày', '%')}</span>
                                </label>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar ${concentrationIndex > 80 ? 'bg-danger' : 'bg-warning'}" role="progressbar" style="width: ${concentrationIndex}%"></div>
                                </div>
                                <small class="text-muted">Dấu hiệu dồn nhiều đơn vào 1 ngày duy nhất.</small>
                            </div>

                            <div class="mb-3">
                                <label class="d-flex justify-content-between mb-1">
                                    <span><strong>Tiến độ tháng rà soát:</strong></span>
                                    <span>${getBadge(selectionWeight, 50, 'Tiến độ', '%')}</span>
                                </label>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar ${selectionWeight > 50 ? 'bg-danger' : 'bg-info'}" role="progressbar" style="width: ${selectionWeight}%"></div>
                                </div>
                                <small class="text-muted">Doanh số khoảng này chiếm bao nhiêu % của cả tháng.</small>
                            </div>

                            <div class="p-2 border rounded bg-light small">
                                <strong><i class="fas fa-info-circle"></i> Minh chứng hành vi:</strong> 
                                ${data.ds_kh_lon_nhat_khoang / totalSalesRange_NV > 0.7 ? '<br>• <span class="text-danger">Doanh số tập trung bất thường vào 1 khách hàng (' + formatCurrency(data.ds_kh_lon_nhat_khoang) + ').</span>' : ''}
                                ${concentrationIndex > 80 ? '<br>• <span class="text-danger">Doanh số tập trung dồn vào 1 ngày duy nhất (' + formatCurrency(maxDailyRange_NV) + ').</span>' : ''}
                                ${data.ds_gkhl_khoang / totalSalesRange_NV < 0.2 ? '<br>• <span class="text-warning">Tỷ lệ doanh số từ khách hàng GKHL thấp (' + ((data.ds_gkhl_khoang/totalSalesRange_NV || 0)*100).toFixed(1) + '%).</span>' : ''}
                                ${customersRange_NV < 3 && totalSalesRange_NV > 10000000 ? '<br>• <span class="text-danger">Doanh số lớn nhưng số khách hàng phát sinh quá ít.</span>' : ''}
                            </div>
                        </div>
                    </div>

                    <!-- 3. So sánh chi tiết -->
                    <div class="col-md-5">
                        <div class="p-3 border rounded shadow-sm h-100 bg-white">
                            <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-balance-scale"></i> So Với Hệ Thống</h6>
                            
                             <div class="mb-3">
                                <div class="small fw-bold">DS Trung Bình Ngày:</div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fs-6">${formatCurrency(dailyAvgActual_NV)}</span>
                                    <span class="text-muted" style="font-size: 0.8rem;">Hệ thống: ${formatCurrency(dailyAvgSystem_Khoang)}</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="small fw-bold">AOV TB Đơn:</div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fs-6">${formatCurrency(aovRange_NV)}</span>
                                    <span class="text-muted" style="font-size: 0.8rem;">Hệ thống: ${formatCurrency(benchmark.aov_khoang || 0)}</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="small fw-bold">Số Đơn / Ngày:</div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fs-6">${(ordersRange_NV / (activeDaysRange_NV || 1)).toFixed(1)} đơn</span>
                                    <span class="text-muted" style="font-size: 0.8rem;">Hệ thống: ${parseFloat(benchmark.orders_per_day_khoang || 0).toFixed(1)} đơn</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="small fw-bold">Ngày Cao Nhất (Max Daily):</div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fs-6 text-danger">${formatCurrency(maxDailyRange_NV)}</span>
                                    <span class="text-muted" style="font-size: 0.8rem;">Hệ thống: ${formatCurrency(systemMaxAvg_Khoang)}</span>
                                </div>
                            </div>

                            <div class="mt-4 pt-2 border-top">
                                <h6 class="text-secondary mb-2"><i class="fas fa-history"></i> Bao Phủ Thị Trường</h6>
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span>Tỷ lệ khách hàng/đơn:</span>
                                    <span><strong>${(ordersRange_NV > 0 ? (customersRange_NV / ordersRange_NV * 100) : 0).toFixed(0)}%</strong></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span>Tỷ lệ khách GKHL:</span>
                                    <span><strong>${(customersRange_NV > 0 ? ((data.so_kh_gkhl_khoang || 0) / customersRange_NV * 100) : 0).toFixed(0)}%</strong> <small class="text-muted">(HT: ${(parseFloat(benchmark.gkhl_rate_khoang || 0)*100).toFixed(0)}%)</small></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span>Số Khách / Ngày:</span>
                                    <span><strong>${(customersRange_NV / (activeDaysRange_NV || 1)).toFixed(1)} KH</strong> <small class="text-muted">(HT: ${parseFloat(benchmark.cust_per_day_khoang || 0).toFixed(1)})</small></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span>Tỷ lệ hoạt động tháng:</span>
                                    <span><strong>${activeDaysMonth_NV} / ${daysInMonth} ngày</strong></span>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-info" style="width: ${(activeDaysMonth_NV / daysInMonth * 100)}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade" id="orders-content">
                <div id="ordersTableContainer">
                    <p class="text-center pt-5"><i class="fas fa-spinner fa-spin"></i> Đang tải dữ liệu đơn hàng...</p>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('modalContent').innerHTML = html;
}

async function renderOrdersTab() {
    const container = document.getElementById('ordersTableContainer');
    if (!container) return;
    
    container.innerHTML = '<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Đang tải đơn hàng...</p>';
    
    try {
        const params = new URLSearchParams(window.location.search);
        // ✅ Ưu tiên lấy ngày từ data nhân viên (nếu là lịch sử), fallback về URL param
        const tu_ngay = currentEmployeeData.tu_ngay || params.get('tu_ngay');
        const den_ngay = currentEmployeeData.den_ngay || params.get('den_ngay');
        const dsr_code = currentEmployeeData.ma_nv;
        
        const url = `nhanvien_report.php?action=get_orders&dsr_code=${dsr_code}&tu_ngay=${tu_ngay}&den_ngay=${den_ngay}`;
        
        const response = await fetch(url);
        const text = await response.text();
        
        let orders;
        try {
            orders = JSON.parse(text);
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            container.innerHTML = `<p class="text-danger">Lỗi parse JSON.</p>`;
            return;
        }
        
        if (orders.error) {
            container.innerHTML = `<p class="text-danger">${orders.error}</p>`;
            return;
        }
        
        if (!orders || orders.length === 0) {
            container.innerHTML = '<p class="text-center text-muted">Không có đơn hàng nào</p>';
            return;
        }
        
        // ✅ GỘP ĐƠN HÀNG THEO MÃ ĐƠN
        const groupedOrders = {};
        orders.forEach(order => {
            const key = order.ma_don;
            if (!groupedOrders[key]) {
                groupedOrders[key] = {
                    ma_don: order.ma_don,
                    ngay_dat: order.ngay_dat,
                    ma_kh: order.ma_kh,
                    ten_kh: order.ten_kh,
                    ma_so_thue: order.ma_so_thue,
                    phan_loai_nhom_kh: order.phan_loai_nhom_kh,
                    loai_kh: order.loai_kh,
                    dia_chi_kh: order.dia_chi_kh,
                    tinh_kh: order.tinh_kh,
                    gkhl_status: order.gkhl_status,
                    dang_ky_chuong_trinh: order.dang_ky_chuong_trinh,
                    dang_ky_muc_doanh_so: order.dang_ky_muc_doanh_so,
                    dang_ky_trung_bay: order.dang_ky_trung_bay,
                    tong_so_tien: 0,
                    tong_so_luong: 0,
                    chi_tiet: []
                };
            }
            groupedOrders[key].tong_so_tien += parseFloat(order.so_tien || 0);
            groupedOrders[key].tong_so_luong += parseInt(order.so_luong || 0);
            groupedOrders[key].chi_tiet.push({
                ma_san_pham: order.ma_san_pham,
                loai_ban: order.loai_ban,
                so_tien: order.so_tien,
                so_luong: order.so_luong
            });
        });
        
        const orderList = Object.values(groupedOrders);
        let totalAmount = 0;
        let totalQty = 0;
        orderList.forEach(o => {
            totalAmount += o.tong_so_tien;
            totalQty += o.tong_so_luong;
        });
        
        let html = `
            <div class="alert alert-info">
                <strong>📊 Tổng quan:</strong> ${orderList.length} đơn hàng (${orders.length} dòng chi tiết) | 
                <strong>Tổng tiền:</strong> ${totalAmount.toLocaleString('vi-VN')}đ |
                <strong>Tổng SL:</strong> ${totalQty.toLocaleString('vi-VN')}
            </div>
            
            <div style="max-height: 65vh; overflow-y: auto;">
                <table class="table table-sm table-hover" id="ordersGroupTable">
                    <thead class="table-light" style="position: sticky; top: 0; z-index: 5;">
                        <tr>
                            <th style="width: 30px;"></th>
                            <th>Mã Đơn</th>
                            <th>Ngày</th>
                            <th>Mã KH</th>
                            <th>Tên KH</th>
                            <th>Địa Chỉ</th>
                            <th>Nhóm KH</th>
                            <th>Loại KH</th>
                            <th>MST</th>
                            <th>Tỉnh</th>
                            <th>GKHL</th>
                            <th class="text-end">Số Tiền</th>
                            <th class="text-center">SL</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        orderList.forEach((order, index) => {
            const rowId = 'order_' + index;
            const gkhlBadge = order.gkhl_status === 'Y' 
                ? `<span class="badge bg-success" title="ĐK CT: ${escapeHtml(order.dang_ky_chuong_trinh || '-')}\nĐK DS: ${escapeHtml(order.dang_ky_muc_doanh_so || '-')}\nTB: ${escapeHtml(order.dang_ky_trung_bay || '-')}">Có</span>`
                : '<span class="badge bg-secondary">Không</span>';
            
            // Dòng đơn tổng (có thể click để expand)
            html += `
                <tr class="order-row table-warning" style="cursor: pointer;" onclick="toggleOrderDetail('${rowId}')">
                    <td><i class="fas fa-chevron-right" id="icon_${rowId}"></i></td>
                    <td><small><strong>${escapeHtml(order.ma_don)}</strong></small></td>
                    <td><small>${escapeHtml(order.ngay_dat?.split(' ')[0] || '')}</small></td>
                    <td><small>${escapeHtml(order.ma_kh)}</small></td>
                    <td><small>${escapeHtml(order.ten_kh)}</small></td>
                    <td><small>${escapeHtml(order.dia_chi_kh || '-')}</small></td>
                    <td><small>${escapeHtml(order.phan_loai_nhom_kh || '-')}</small></td>
                    <td><small><span class="badge bg-info text-dark">${escapeHtml(order.loai_kh || '-')}</span></small></td>
                    <td><small>${escapeHtml(order.ma_so_thue || '-')}</small></td>
                    <td><small>${escapeHtml(order.tinh_kh || '-')}</small></td>
                    <td>${gkhlBadge}</td>
                    <td class="text-end"><small><strong>${order.tong_so_tien.toLocaleString('vi-VN')}đ</strong></small></td>
                    <td class="text-center"><small>${order.tong_so_luong}</small></td>
                </tr>
            `;
            
            // Chi tiết đơn hàng (ẩn mặc định)
            html += `<tr id="${rowId}" class="order-detail" style="display: none;">
                <td colspan="13" style="padding: 0; background: #f8f9fa;">
                    <div style="padding: 10px 20px;">
                        <div class="mb-2">
                            <small class="text-muted">
                                <strong>Địa chỉ:</strong> ${escapeHtml(order.dia_chi_kh || 'N/A')} | 
                                <strong>Nhóm KH:</strong> ${escapeHtml(order.phan_loai_nhom_kh || 'N/A')}
                            </small>
                        </div>`;
            
            // Hiển thị GKHL nếu có
            if (order.gkhl_status === 'Y') {
                html += `<div class="mb-2 p-2" style="background: #d4edda; border-radius: 5px;">
                    <small>
                        <strong><i class="fas fa-link"></i> Gắn Kết Hoa Linh:</strong>
                        <span class="badge bg-primary ms-2">ĐK CT: ${escapeHtml(order.dang_ky_chuong_trinh || '-')}</span>
                        <span class="badge bg-warning text-dark ms-1">ĐK DS: ${escapeHtml(order.dang_ky_muc_doanh_so || '-')}</span>
                        <span class="badge bg-info ms-1">TB: ${escapeHtml(order.dang_ky_trung_bay || '-')}</span>
                    </small>
                </div>`;
            }
            
            html += `<table class="table table-sm table-bordered mb-0">
                            <thead class="table-secondary">
                                <tr>
                                    <th>Mã SP</th>
                                    <th>Loại Bán</th>
                                    <th class="text-end">Số Tiền</th>
                                    <th class="text-center">SL</th>
                                </tr>
                            </thead>
                            <tbody>`;
            
            order.chi_tiet.forEach(item => {
                html += `<tr>
                    <td><small>${escapeHtml(item.ma_san_pham || '-')}</small></td>
                    <td><small><strong class="text-dark">${escapeHtml(item.loai_ban || '-')}</strong></small></td>
                    <td class="text-end"><small>${parseFloat(item.so_tien).toLocaleString('vi-VN')}đ</small></td>
                    <td class="text-center"><small>${item.so_luong || 0}</small></td>
                </tr>`;
            });
            
            html += `</tbody></table></div></td></tr>`;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
        
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading orders:', error);
        container.innerHTML = `<p class="text-danger">Lỗi tải dữ liệu: ${error.message}</p>`;
    }
}

// Function toggle hiển thị chi tiết đơn
function toggleOrderDetail(rowId) {
    const row = document.getElementById(rowId);
    const icon = document.getElementById('icon_' + rowId);
    if (row.style.display === 'none') {
        row.style.display = 'table-row';
        icon.classList.remove('fa-chevron-right');
        icon.classList.add('fa-chevron-down');
    } else {
        row.style.display = 'none';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-right');
    }
}


function toggleHistory(rowId, btn) {
    const rows = document.getElementsByClassName(rowId);
    const icon = btn.querySelector('i');
    
    for (let row of rows) {
        if (row.style.display === 'none') {
            row.style.display = 'table-row';
            icon.classList.remove('fa-plus-square');
            icon.classList.add('fa-minus-square');
        } else {
            row.style.display = 'none';
            icon.classList.remove('fa-minus-square');
            icon.classList.add('fa-plus-square');
        }
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectThang = document.getElementById('selectThang');
    const tuNgayInput = document.getElementById('tuNgay');
    const denNgayInput = document.getElementById('denNgay');

    // ✅ KHOẢNG NGÀY THỰC TẾ TỪ DATABASE (thay vì tính theo lịch)
    const dateRanges = <?= json_encode($date_ranges ?? [], JSON_UNESCAPED_UNICODE) ?>;

    // 1. Xử lý khi thay đổi Tháng/Năm
    selectThang.addEventListener('change', function() {
        const monthVal = this.value; // Định dạng YYYY-MM
        if (!monthVal) return;

        // ✅ Sử dụng khoảng ngày thực tế từ database
        const range = dateRanges[monthVal];
        if (range) {
            tuNgayInput.value = range.min_date;
            denNgayInput.value = range.max_date;
            tuNgayInput.min = range.min_date;
            tuNgayInput.max = range.max_date;
            denNgayInput.min = range.min_date;
            denNgayInput.max = range.max_date;
        } else {
            // Fallback: nếu không có data, dùng tháng lịch
            const [year, month] = monthVal.split('-').map(Number);
            const lastDay = new Date(year, month, 0).getDate();
            const firstDate = `${monthVal}-01`;
            const lastDate = `${monthVal}-${String(lastDay).padStart(2, '0')}`;
            tuNgayInput.value = firstDate;
            denNgayInput.value = lastDate;
            tuNgayInput.min = firstDate;
            tuNgayInput.max = lastDate;
            denNgayInput.min = firstDate;
            denNgayInput.max = lastDate;
        }
    });

    // 2. Ràng buộc Từ Ngày <= Đến Ngày
    tuNgayInput.addEventListener('change', function() {
        if (this.value > denNgayInput.value) {
            denNgayInput.value = this.value;
        }
        denNgayInput.min = this.value;
    });

    denNgayInput.addEventListener('change', function() {
        if (this.value < tuNgayInput.value) {
            tuNgayInput.value = this.value;
        }
    });

    // ✅ Kích hoạt giới hạn ngay khi load trang
    if (selectThang.value) {
        const monthVal = selectThang.value;
        const range = dateRanges[monthVal];
        if (range) {
            tuNgayInput.min = range.min_date;
            tuNgayInput.max = range.max_date;
            denNgayInput.min = range.min_date;
            denNgayInput.max = range.max_date;
        }
    }
});

// ✅ REVENUE CHARTS IMPLEMENTATION
// Utility
function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

let barChartInstance = null;
let trendChartInstance = null;

function renderRevenueCharts() {
    // 1. Dữ liệu từ PHP
    const reportData = <?= json_encode($report) ?>;
    const historyData = <?= json_encode($history_data) ?>;
    const currentMonth = "<?= $thang ?>";
    const metric = document.querySelector('input[name="chartMetric"]:checked').value;
    const limit = parseInt(document.getElementById('chartLimit').value) || 0;
    
    // Thu thập các tháng để vẽ
    const allMonths = Object.keys(historyData).reverse();
    allMonths.push(currentMonth);

    const container = document.getElementById('chartContainer');
    const barCtx = document.getElementById('revenueBarChart').getContext('2d');
    
    if (barChartInstance) barChartInstance.destroy();

    if (metric === 'revenue') {
        // 2. Chuẩn bị dữ liệu cho Bar Chart
        let topNV = reportData
            .filter(r => r.ds_tim_kiem > 0)
            .sort((a, b) => b.ds_tim_kiem - a.ds_tim_kiem);
        
        if (limit > 0) {
            topNV = topNV.slice(0, limit);
        } else {
            // Nếu "Tất cả", giới hạn thực tế để tránh crash trình duyệt nếu có ngàn người
            topNV = topNV.slice(0, 300);
        }

        // Cập nhật chiều cao container dựa trên số lượng NV
        const calculatedHeight = Math.max(400, topNV.length * 35 + 100);
        container.style.height = calculatedHeight + 'px';
        
        const labels = topNV.map(r => r.ten_nv);
        const datasets = allMonths.map(month => {
            let monthData = [];
            let label = "Tháng " + month.split('-').reverse().join('/');
            
            topNV.forEach(nv => {
                let value = 0;
                if (month === currentMonth) {
                    value = nv.ds_tim_kiem;
                } else if (historyData[month] && historyData[month].report[nv.ma_nv]) {
                    value = historyData[month].report[nv.ma_nv].ds_tim_kiem;
                }
                monthData.push(value);
            });
            
            const hash = month.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
            const hue = (hash * 137.5) % 360;
            const color = `hsla(${hue}, 70%, 60%, 0.8)`;
            
            return {
                label: label,
                data: monthData,
                backgroundColor: color,
                borderColor: color.replace('0.8', '1'),
                borderWidth: 1
            };
        });

        barChartInstance = new Chart(barCtx, {
            type: 'bar',
            data: { labels, datasets },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { 
                        display: true, 
                        text: 'So Sánh Doanh Thu Nhân Viên (Sắp xếp theo tháng hiện tại)', 
                        font: { size: 16, weight: 'bold' },
                        padding: { bottom: 20 }
                    },
                    legend: {
                        position: 'top',
                        labels: { usePointStyle: true, padding: 20 }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.parsed.x !== null) {
                                    label += new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(context.parsed.x);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: '#f0f0f0' },
                        ticks: {
                            callback: function(value) {
                                return (value / 1000000).toLocaleString() + 'M';
                            },
                            font: { size: 11 }
                        }
                    },
                    y: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 12, weight: '500' },
                            padding: 10
                        }
                    }
                },
                layout: {
                    padding: { left: 10, right: 30, top: 0, bottom: 10 }
                },
                barThickness: 'flex',
                maxBarThickness: 30,
                categoryPercentage: 0.8,
                barPercentage: 0.9
            }
        });
    } else {
        // Metric: Employee Count
        container.style.height = '400px';
        const labels = allMonths.map(m => "Tháng " + m.split('-').reverse().join('/'));
        const employeeCounts = allMonths.map(month => {
            if (month === currentMonth) {
                return <?= json_encode($tong_tien_ky_detailed['so_nhan_vien_thang'] ?? 0) ?>;
            } else {
                return historyData[month]?.stats_detailed?.so_nhan_vien_thang || 0;
            }
        });

        barChartInstance = new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Số Nhân Viên Hoạt Động',
                    data: employeeCounts,
                    backgroundColor: 'rgba(102, 126, 234, 0.7)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'Tổng Số Nhân Viên Có Doanh Số Theo Tháng', font: { size: 16 } },
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }

    // Load heatmap chart automatically
    loadHeatmapChart();
}

// ✅ HEATMAP CHART IMPLEMENTATION
let currentHeatmapData = null;
let currentHeatmapMode = 'all'; // 'all' = trong khoảng thời gian, 'suspect' = trong tháng

async function loadHeatmapChart() {
    const params = new URLSearchParams(window.location.search);
    const tu_ngay = params.get('tu_ngay');
    const den_ngay = params.get('den_ngay');
    const thang = params.get('thang');
    
    if (!tu_ngay || !den_ngay || !thang) return;

    // Show loading
    const loadingElem = document.getElementById('heatmapLoading');
    const tableElem = document.getElementById('heatmapTable');
    if (loadingElem) loadingElem.style.display = 'block';
    if (tableElem) tableElem.style.display = 'none';

    try {
        const historyData = <?= json_encode($history_data ?? []) ?>;
        const currentMonth = "<?= $thang ?>";
        const allMonths = Object.keys(historyData).reverse();
        allMonths.push(currentMonth);

        // Fetch data for all months
        const fetchPromises = allMonths.map(async (month) => {
            let start, end;
            if (month === currentMonth) {
                start = tu_ngay;
                end = den_ngay;
            } else {
                start = historyData[month].tu_ngay;
                end = historyData[month].den_ngay;
            }
            
            const url = `nhanvien_report.php?action=get_daily_sales&tu_ngay=${start}&den_ngay=${end}&thang=${month}&type=all`;
            const response = await fetch(url);
            const result = await response.json();
            
            // Nếu là metric revenue, ta có thể lọc data heatmap tương ứng với top NV đã chọn
            let filteredData = result.data || [];
            const limit = parseInt(document.getElementById('chartLimit').value) || 0;
            const metric = document.querySelector('input[name="chartMetric"]:checked').value;

            // Nếu muốn heatmap sync với chart Top NV
            if (metric === 'revenue' && limit > 0) {
                const reportData = <?= json_encode($report) ?>;
                const topNVIds = reportData
                    .filter(r => r.ds_tim_kiem > 0)
                    .sort((a, b) => b.ds_tim_kiem - a.ds_tim_kiem)
                    .slice(0, limit)
                    .map(r => r.ma_nv);
                
                filteredData = filteredData.filter(d => topNVIds.includes(d.dsr_code));
            }

            return { month, start, end, data: filteredData };
        });

        const results = await Promise.all(fetchPromises);
        
        // Clear previous content
        if (tableElem) {
            tableElem.innerHTML = '';
            
            results.reverse().forEach(res => {
                const monthTitle = document.createElement('h6');
                monthTitle.className = 'mt-4 mb-2 text-primary border-bottom pb-1';
                monthTitle.innerHTML = `<i class="fas fa-calendar-day me-2"></i>Tháng ${res.month.split('-').reverse().join('/')} <small class="text-muted">(${res.start} ~ ${res.end})</small>`;
                tableElem.appendChild(monthTitle);
                
                const tableContainer = document.createElement('div');
                tableContainer.className = 'mb-4';
                tableElem.appendChild(tableContainer);
                
                renderMonthHeatmap(res.data, res.start, res.end, tableContainer);
            });

            // Add legend once at the bottom
            const legend = document.createElement('div');
            legend.className = 'mt-2 small text-muted';
            legend.innerHTML = `
                <strong>Chú thích:</strong> 
                <span class="badge bg-danger">Đỏ</span> Doanh số cao (>80% max) |
                <span class="badge bg-warning text-dark">Cam</span> Trung bình cao (50-80%) |
                <span class="badge bg-success">Xanh</span> Bình thường |
                <span class="badge bg-light text-dark">Xám</span> Không có đơn |
                <strong>Tập Trung:</strong> % doanh số của ngày cao nhất so với tổng (cao = bất thường)
            `;
            tableElem.appendChild(legend);
        }

        if (loadingElem) loadingElem.style.display = 'none';
        if (tableElem) tableElem.style.display = 'block';

        const periodInfo = document.getElementById('heatmapPeriodInfo');
        if (periodInfo) {
            periodInfo.innerHTML = `<strong>Đang hiển thị biểu đồ nhiệt cho:</strong> ${allMonths.map(m => 'Tháng ' + m.split('-').reverse().join('/')).join(', ')}`;
        }

    } catch (error) {
        console.error('Error loading heatmap:', error);
        if (loadingElem) loadingElem.innerHTML = `<p class="text-danger"><i class="fas fa-exclamation-circle"></i> Lỗi: ${error.message}</p>`;
    }
}

function renderMonthHeatmap(data, tu_ngay, den_ngay, container) {
    if (!data || data.length === 0) {
        container.innerHTML = '<p class="text-muted text-center py-3">Không có dữ liệu trong khoảng này</p>';
        return;
    }

    const dates = [];
    let currentDate = new Date(tu_ngay);
    const endDate = new Date(den_ngay);
    while (currentDate <= endDate) {
        dates.push(currentDate.toISOString().split('T')[0]);
        currentDate.setDate(currentDate.getDate() + 1);
    }

    const employeeData = {};
    data.forEach(row => {
        if (!employeeData[row.dsr_code]) {
            employeeData[row.dsr_code] = { ten_nv: row.ten_nv, dailyData: {} };
        }
        const value = parseFloat(row.doanh_so) || 0;
        employeeData[row.dsr_code].dailyData[row.ngay] = value;
    });

    const limit = parseInt(document.getElementById('chartLimit').value) || 0;

    let employeeList = Object.entries(employeeData)
        .map(([code, info]) => {
            const values = Object.values(info.dailyData);
            const total = values.reduce((a, b) => a + b, 0);
            const maxDay = Math.max(...values, 0);
            const activeDays = values.filter(v => v > 0).length;
            const concentration = total > 0 ? (maxDay / total * 100) : 0;
            return { code, ten_nv: info.ten_nv, total, maxDay, activeDays, concentration, dailyData: info.dailyData };
        })
        .sort((a, b) => b.concentration - a.concentration);

    if (limit > 0) {
        employeeList = employeeList.slice(0, limit);
    }

    const getHeatColor = (value, empMax) => {
        if (value === 0) return 'background: #f8f9fa; color: #ccc;';
        const intensity = Math.min(value / (empMax * 0.7), 1);
        if (intensity > 0.8) return 'background: #dc3545; color: white; font-weight: bold;';
        if (intensity > 0.5) return 'background: #fd7e14; color: white;';
        if (intensity > 0.3) return 'background: #ffc107; color: #333;';
        return 'background: #28a745; color: white;';
    };

    let html = `
        <div class="table-responsive shadow-sm rounded border">
            <table class="table table-sm table-hover table-bordered mb-0" style="font-size: 11px; min-width: 1000px;">
                <thead class="bg-dark text-white">
                    <tr>
                        <th style="position: sticky; left: 0; background: #2c3e50; z-index: 4; color: white; width: 180px;">Nhân Viên</th>
                        <th class="text-center" style="width: 70px; background: #34495e;">Tập Trung</th>
                        <th class="text-center" style="width: 60px; background: #34495e;">Ngày HĐ</th>
                        ${dates.map(d => {
                            const date = new Date(d);
                            const isWeekend = date.getDay() === 0 || date.getDay() === 6;
                            const bg = isWeekend ? 'background: #e74c3c;' : 'background: #34495e;';
                            return `<th class="text-center" style="width: 45px; padding: 4px 2px; ${bg} color: white;">${date.getDate()}<br><small>${['CN','T2','T3','T4','T5','T6','T7'][date.getDay()]}</small></th>`;
                        }).join('')}
                    </tr>
                </thead>
                <tbody>
    `;

    employeeList.forEach(emp => {
        const concClass = emp.concentration > 70 ? 'bg-danger text-white' : 
                         emp.concentration > 50 ? 'bg-warning text-dark' : 'bg-success text-white';
        
        html += `
            <tr>
                <td style="position: sticky; left: 0; background: white; z-index: 3; white-space: nowrap; font-weight: 600;" title="${escapeHtml(emp.ten_nv)} (${emp.code})">
                    <div style="width: 170px; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(emp.ten_nv)}</div>
                </td>
                <td class="text-center ${concClass}" style="font-weight: bold; vertical-align: middle;">
                    ${emp.concentration.toFixed(0)}%
                </td>
                <td class="text-center" style="vertical-align: middle;">
                    <span class="badge bg-light text-dark border">${emp.activeDays}/${dates.length}</span>
                </td>
                ${dates.map(d => {
                    const value = emp.dailyData[d] || 0;
                    const style = getHeatColor(value, emp.maxDay);
                    const displayValue = value > 0 ? (value / 1000000).toFixed(1) : '-';
                    const weight = value > 0 ? 'font-weight: 500;' : '';
                    return `<td class="text-center" style="${style} ${weight} vertical-align: middle;" title="${value.toLocaleString('vi-VN')}đ">${displayValue}</td>`;
                }).join('')}
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// Heatmap logic completed above

// Auto-load charts when switching to chart tab
document.addEventListener('DOMContentLoaded', function() {
    const chartTab = document.getElementById('chart-tab');
    if (chartTab) {
        chartTab.addEventListener('shown.bs.tab', function() {
            // Load heatmap chart if not loaded yet
            if (!currentHeatmapData) {
                loadHeatmapChart('all');
            }
        });
    }

    // ✅ CẬP NHẬT GIỚI HẠN NGÀY KHI CHỌN THÁNG
    const selectThang = document.getElementById('selectThang');
    const tuNgay = document.getElementById('tuNgay');
    const denNgay = document.getElementById('denNgay');
    const dateRanges = <?= json_encode($date_ranges ?? []) ?>;

    if (selectThang && tuNgay && denNgay) {
        selectThang.addEventListener('change', function() {
            const selected = this.value;
            const range = dateRanges[selected];
            
            if (range) {
                tuNgay.min = range.min_date;
                tuNgay.max = range.max_date;
                tuNgay.value = range.min_date;
                
                denNgay.min = range.min_date;
                denNgay.max = range.max_date;
                denNgay.value = range.max_date;
            } else {
                const firstDay = selected + '-01';
                // Tạm tính ngày cuối tháng
                const date = new Date(selected + '-01');
                const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0).toISOString().split('T')[0];
                
                tuNgay.min = firstDay;
                tuNgay.max = lastDay;
                tuNgay.value = firstDay;
                
                denNgay.min = firstDay;
                denNgay.max = lastDay;
                denNgay.value = lastDay;
            }
        });
    }
});
</script>
</body>
</html>