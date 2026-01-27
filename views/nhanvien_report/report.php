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
            background: linear-gradient(90deg, #fee 0%, #fdd 100%) !important; 
            border-left: 4px solid #dc3545 !important; 
        }
        .bg-orange-highlight { 
            background: linear-gradient(90deg, #fff5e6 0%, #ffe6cc 100%) !important; 
            border-left: 4px solid #ff9800 !important; 
        }
        .bg-none-highlight { 
            background: white !important; 
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
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><i class="fas fa-calendar-alt"></i> Tháng</label>
                        <select name="thang" id="selectThang" class="form-select" required>
                            <?php foreach ($available_months as $m): ?>
                                <option value="<?= htmlspecialchars($m) ?>" <?= ($m === $thang) ? 'selected' : '' ?>>
                                    Tháng <?= date('m/Y', strtotime($m . '-01')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Từ Ngày</label>
                        <input type="date" name="tu_ngay" id="tuNgay" class="form-control" 
                               value="<?= htmlspecialchars($tu_ngay) ?>" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Đến Ngày</label>
                        <input type="date" name="den_ngay" id="denNgay" class="form-control" 
                               value="<?= htmlspecialchars($den_ngay) ?>" required>
                    </div>
                    
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Rà Soát
                        </button>
                    </div>
                    <div class="col-md-1">
                        <a href="nhanvien_report.php" class="btn btn-secondary w-100">
                            <i class="fas fa-sync"></i> Reset
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
                            <small><i class="fas fa-eye"></i> Tỉ Lệ Hoàn Thành Nghi Vấn (Kết quả chung × 1.5)</small>
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
                        <div class="legend-color" style="background: white; border-left-color: #e0e0e0;"></div>
                        <span><strong>Trắng:</strong> Không Nghi Vấn (OK)</span>
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
                                <th style="width: 100px;">Mã GSBH</th>
                                <th style="width: 80px;">Tỉnh</th>
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
                                if ($r['highlight_type'] === 'red') {
                                    $row_class = 'bg-red-highlight';
                                } elseif ($r['highlight_type'] === 'orange') {
                                    $row_class = 'bg-orange-highlight';
                                } else {
                                    $row_class = 'bg-none-highlight';
                                }
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td class="text-center fw-bold">
                                    <?php if ($r['rank'] > 0): ?>
                                        <span class="badge <?= ($r['highlight_type'] === 'red') ? 'bg-danger' : 'bg-warning text-dark' ?>">#<?= $r['rank'] ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($r['ma_nv']) ?></strong></td>
                                <td><?= htmlspecialchars($r['ten_nv'] ?? '') ?></td>
                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($r['ma_gsbh'] ?: 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($r['tinh'] ?? '') ?></td>
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
                                    <?php if ($r['is_suspect']): ?>
                                        <span class="badge bg-danger">⚠️ NGHI VẤN</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">✅ OK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="10" class="text-center text-muted py-5">Không có dữ liệu</td></tr>
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
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ✅ Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title" id="detailModalLabel">Chi Tiết Nhân Viên - <span id="modalEmpName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalContent" style="max-height: 600px; overflow-y: auto;">
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
    
    const dsTBKhoang_NV = data.ds_tien_do;
    const dsTBKhoang_Chung = benchmark.ds_tb_chung_khoang;
    const dsMaxKhoang_NV = data.ds_ngay_cao_nhat_nv_khoang;
    const dsMaxKhoang_Chung = benchmark.ds_ngay_cao_nhat_tb_khoang;
    
    const dsTBThang_NV = data.ds_tong_thang_nv;
    const dsTBThang_Chung = benchmark.ds_tb_chung_thang;
    const dsMaxThang_NV = data.ds_ngay_cao_nhat_nv_thang;
    const dsMaxThang_Chung = benchmark.ds_ngay_cao_nhat_tb_thang;
    
    const soNgayKhoang_NV = data.so_ngay_co_doanh_so_khoang || 0;
    const soNgayThang_NV = data.so_ngay_co_doanh_so_thang || 0;
    const soNgayTrongKhoang = benchmark.so_ngay || 1;
    const soNgayTrongThang = benchmark.so_ngay_trong_thang || 1;
    
    const formatCurrency = (val) => {
        if (isNaN(val) || val === 0) return '0đ';
        return parseFloat(val).toLocaleString('vi-VN') + 'đ';
    };
    
    const calcPercent = (emp, system) => {
        if (system === 0 || isNaN(system)) return 0;
        return ((emp - system) / system * 100);
    };
    
    const getCompareIcon = (emp, system) => {
        return (emp >= system) ? '✅' : '⚠️';
    };
    
    const getCompareColor = (emp, system) => {
        return (emp >= system) ? '#28a745' : '#dc3545';
    };
    
    let html = `
        <ul class="nav nav-tabs mb-3" id="detailTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info-content" 
                        type="button" role="tab" onclick="renderEmployeeInfoTab()">
                    <i class="fas fa-user"></i> Thông Tin NV
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders-content" 
                        type="button" role="tab" onclick="renderOrdersTab()">
                    <i class="fas fa-shopping-cart"></i> Đơn Hàng
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="detailTabContent">
            <div class="tab-pane fade show active" id="info-content" role="tabpanel">
                <div style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 15px;">
                    <h6 style="border-bottom: 2px solid #667eea; padding-bottom: 10px; margin-bottom: 15px;">
                        <i class="fas fa-user-circle"></i> Thông Tin Nhân Viên
                    </h6>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div><strong>Mã NV:</strong> ${escapeHtml(data.ma_nv)}</div>
                        <div><strong>Tên:</strong> ${escapeHtml(data.ten_nv)}</div>
                        <div><strong>Mã GSBH:</strong> <span class="badge bg-info">${escapeHtml(data.ma_gsbh || 'N/A')}</span></div>
                        <div><strong>Tỉnh:</strong> ${escapeHtml(data.tinh || 'N/A')}</div>
                    </div>
                </div>

                <div style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 15px;">
                    <h6 style="border-bottom: 2px solid #667eea; padding-bottom: 10px; margin-bottom: 15px;">
                        <i class="fas fa-calendar-days"></i> So Sánh Trong Khoảng Thời Gian
                    </h6>
                    
                    <div style="margin-bottom: 10px;">
                        <strong>📊 DS TB/Ngày (NV):</strong> ${formatCurrency(dsTBKhoang_NV)}
                    </div>
                    <div style="margin-bottom: 10px;">
                        <strong>📊 DS TB/Ngày (Chung):</strong> ${formatCurrency(dsTBKhoang_Chung)}
                    </div>
                    <div style="margin-bottom: 15px;">
                        <strong>📊 Chênh Lệch:</strong> 
                        <span style="color: ${getCompareColor(dsTBKhoang_NV, dsTBKhoang_Chung)};">
                            ${getCompareIcon(dsTBKhoang_NV, dsTBKhoang_Chung)} ${Math.abs(calcPercent(dsTBKhoang_NV, dsTBKhoang_Chung)).toFixed(1)}%
                        </span>
                    </div>
                    
                    <div style="margin-bottom: 10px;">
                        <strong>📈 DS Ngày Cao Nhất (NV):</strong> ${formatCurrency(dsMaxKhoang_NV)}
                    </div>
                    <div style="margin-bottom: 10px;">
                        <strong>📈 DS Ngày Cao Nhất TB (Chung):</strong> ${formatCurrency(dsMaxKhoang_Chung)}
                    </div>
                    <div>
                        <strong>📈 Chênh Lệch:</strong> 
                        <span style="color: ${getCompareColor(dsMaxKhoang_NV, dsMaxKhoang_Chung)};">
                            ${getCompareIcon(dsMaxKhoang_NV, dsMaxKhoang_Chung)} ${Math.abs(calcPercent(dsMaxKhoang_NV, dsMaxKhoang_Chung)).toFixed(1)}%
                        </span>
                    </div>
                </div>

                <div style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 15px;">
                    <h6 style="border-bottom: 2px solid #667eea; padding-bottom: 10px; margin-bottom: 15px;">
                        <i class="fas fa-calendar-alt"></i> So Sánh Trong Tháng
                    </h6>
                    
                    <div style="margin-bottom: 10px;">
                        <strong>📋 DS Tháng (NV):</strong> ${formatCurrency(dsTBThang_NV)}
                    </div>
                    <div style="margin-bottom: 10px;">
                        <strong>📋 DS TB/Ngày/NV (Chung):</strong> ${formatCurrency(dsTBThang_Chung)}
                    </div>
                    <div style="margin-bottom: 15px;">
                        <strong>📋 % So Với Chung:</strong> 
                        <span style="color: ${getCompareColor(dsTBThang_NV, dsTBThang_Chung)};">
                            ${getCompareIcon(dsTBThang_NV, dsTBThang_Chung)} ${Math.abs(calcPercent(dsTBThang_NV, dsTBThang_Chung)).toFixed(1)}%
                        </span>
                    </div>
                    
                    <div style="margin-bottom: 10px;">
                        <strong>📈 DS Ngày Cao Nhất (NV-Tháng):</strong> ${formatCurrency(dsMaxThang_NV)}
                    </div>
                    <div style="margin-bottom: 10px;">
                        <strong>📈 DS Ngày Cao Nhất TB (Chung-Tháng):</strong> ${formatCurrency(dsMaxThang_Chung)}
                    </div>
                    <div>
                        <strong>📈 Chênh Lệch:</strong> 
                        <span style="color: ${getCompareColor(dsMaxThang_NV, dsMaxThang_Chung)};">
                            ${getCompareIcon(dsMaxThang_NV, dsMaxThang_Chung)} ${Math.abs(calcPercent(dsMaxThang_NV, dsMaxThang_Chung)).toFixed(1)}%
                        </span>
                    </div>
                </div>

                <div style="background: white; padding: 20px; border-radius: 10px;">
                    <h6 style="border-bottom: 2px solid #667eea; padding-bottom: 10px; margin-bottom: 15px;">
                        <i class="fas fa-calendar-check"></i> Ngày Hoạt Động
                    </h6>
                    <div style="margin-bottom: 10px;">
                        <strong>📅 Ngày Có Doanh Số (Khoảng):</strong> ${soNgayKhoang_NV} / ${soNgayTrongKhoang} ngày
                    </div>
                    <div style="margin-bottom: 10px;">
                        <strong>📊 % Hoạt Động (Khoảng):</strong> ${(soNgayTrongKhoang > 0 ? (soNgayKhoang_NV / soNgayTrongKhoang * 100) : 0).toFixed(1)}%
                    </div>
                    
                    <div style="margin-bottom: 10px; margin-top: 15px;">
                        <strong>📅 Ngày Có Doanh Số (Tháng):</strong> ${soNgayThang_NV} / ${soNgayTrongThang} ngày
                    </div>
                    <div>
                        <strong>📊 % Hoạt Động (Tháng):</strong> ${(soNgayTrongThang > 0 ? (soNgayThang_NV / soNgayTrongThang * 100) : 0).toFixed(1)}%
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade" id="orders-content" role="tabpanel">
                <div id="ordersTableContainer">
                    <p class="text-center"><i class="fas fa-spinner fa-spin"></i> Đang tải...</p>
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
        const tu_ngay = params.get('tu_ngay');
        const den_ngay = params.get('den_ngay');
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
        
        let totalAmount = 0;
        orders.forEach(o => totalAmount += parseFloat(o.so_tien || 0));
        
        let html = `
            <div class="alert alert-info">
                <strong>📊 Tổng quan:</strong> ${orders.length} chi tiết đơn hàng | 
                <strong>Tổng tiền:</strong> ${totalAmount.toLocaleString('vi-VN')}đ
            </div>
            
            <div style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm table-hover">
                    <thead class="table-light" style="position: sticky; top: 0; z-index: 5;">
                        <tr>
                            <th>STT</th>
                            <th>Mã Đơn</th>
                            <th>Ngày Đặt</th>
                            <th>Mã KH</th>
                            <th>Tên KH</th>
                            <th>Địa Chỉ</th>
                            <th>Tỉnh</th>
                            <th class="text-end">Số Tiền</th>
                            <th class="text-center">SL</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        orders.forEach((order, index) => {
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td><small><strong>${escapeHtml(order.ma_don)}</strong></small></td>
                    <td><small>${escapeHtml(order.ngay_dat)}</small></td>
                    <td><small>${escapeHtml(order.ma_kh)}</small></td>
                    <td><small>${escapeHtml(order.ten_kh)}</small></td>
                    <td><small>${escapeHtml(order.dia_chi_kh || '-')}</small></td>
                    <td><small>${escapeHtml(order.tinh_kh || '-')}</small></td>
                    <td class="text-end"><small><strong>${parseFloat(order.so_tien).toLocaleString('vi-VN')}đ</strong></small></td>
                    <td class="text-center"><small>${order.so_luong || 0}</small></td>
                </tr>
            `;
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

    // Hàm lấy ngày cuối cùng của tháng (YYYY-MM)
    function getLastDayOfMonth(yearMonth) {
        const [year, month] = yearMonth.split('-').map(Number);
        return new Date(year, month, 0).getDate();
    }

    // 1. Xử lý khi thay đổi Tháng/Năm
    selectThang.addEventListener('change', function() {
        const monthVal = this.value; // Định dạng YYYY-MM
        if (!monthVal) return;

        const lastDay = getLastDayOfMonth(monthVal);
        
        const firstDate = `${monthVal}-01`;
        const lastDate = `${monthVal}-${lastDay}`;

        // Cập nhật giá trị
        tuNgayInput.value = firstDate;
        denNgayInput.value = lastDate;

        // Cập nhật min/max để giới hạn người dùng không chọn ngoài tháng
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

    // Kích hoạt giới hạn ngay khi load trang nếu đã có tháng được chọn sẵn
    if (selectThang.value) {
        const monthVal = selectThang.value;
        const lastDay = getLastDayOfMonth(monthVal);
        tuNgayInput.min = `${monthVal}-01`;
        tuNgayInput.max = `${monthVal}-${lastDay}`;
        denNgayInput.min = `${monthVal}-01`;
        denNgayInput.max = `${monthVal}-${lastDay}`;
    }
});
</script>
</body>
</html>