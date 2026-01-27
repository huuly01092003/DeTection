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
        .kpi-card { 
            background: white; 
            padding: 18px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.08); 
            text-align: center; 
        }
        .kpi-value { 
            font-size: 2rem; 
            font-weight: 700; 
            color: #333; 
        }
        .kpi-label { 
            font-size: 0.85rem; 
            color: #666; 
            margin-top: 5px; 
        }
        .threshold-box { 
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); 
            padding: 15px; 
            border-radius: 10px; 
            border-left: 4px solid #ffc107; 
        }
        .violation-badge { 
            background: #dc3545; 
            color: white; 
            padding: 4px 10px; 
            border-radius: 12px; 
            font-size: 0.8rem; 
            font-weight: 600; 
        }
        .customer-row { 
            border-bottom: 1px solid #eee; 
            padding: 10px 0; 
        }
        .customer-row:hover { 
            background: #f8f9fa; 
        }
        .order-chip { 
            background: #e3f2fd; 
            padding: 3px 8px; 
            border-radius: 8px; 
            margin: 2px; 
            display: inline-block; 
            font-size: 0.75rem; 
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
            <form method="get" class="p-4" style="background: #f8f9fa; border-radius: 10px;">
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
                <!-- KPI CARDS -->
                <div class="row g-3 mt-3">
                    <div class="col-md-2">
                        <div class="kpi-card">
                            <div class="kpi-value text-primary"><?= intval($statistics['employees_with_orders']) ?></div>
                            <div class="kpi-label">Nhân Viên</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="kpi-card">
                            <div class="kpi-value text-info"><?= number_format($statistics['total_orders']) ?></div>
                            <div class="kpi-label">Tổng Đơn</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="kpi-card">
                            <div class="kpi-value text-success"><?= number_format($statistics['total_customers']) ?></div>
                            <div class="kpi-label">Tổng Khách</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="kpi-card">
                            <div class="kpi-value text-warning"><?= intval($statistics['warning_count']) ?></div>
                            <div class="kpi-label">Cảnh Báo</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="kpi-card">
                            <div class="kpi-value text-danger"><?= intval($statistics['danger_count']) ?></div>
                            <div class="kpi-label">Nghiêm Trọng</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="kpi-card">
                            <div class="kpi-value"><?= intval($statistics['normal_count']) ?></div>
                            <div class="kpi-label">Bình Thường</div>
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
                                <th style="width: 100px;">GSBH</th>
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
                                <td><?= htmlspecialchars($item['ten_nv']) ?></td>
                                <td><?= htmlspecialchars($item['MaGSBH'] ?? '-') ?></td>
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
                                        <i class="fas fa-eye"></i> Vi Phạm
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" 
                                            onclick="loadCustomers('<?= htmlspecialchars($item['DSRCode']) ?>', '<?= htmlspecialchars($item['ten_nv']) ?>')">
                                        <i class="fas fa-users"></i> Khách
                                    </button>
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

<!-- Modal Vi Phạm -->
<div class="modal fade" id="violationModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">Chi Tiết Vi Phạm - <span id="violationEmpName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="violationContent"></div>
        </div>
    </div>
</div>

<!-- Modal Khách Hàng -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                <h5 class="modal-title">Danh Sách Khách Hàng - <span id="customerEmpName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="customerContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Đang tải dữ liệu...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Hiển thị chi tiết vi phạm
function showDetail(data) {
    document.getElementById('violationEmpName').textContent = data.ten_nv;
    
    let html = `
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h6>Vi Phạm</h6>
                        <h3 class="text-danger">${data.violation_count} ngày</h3>
                        <small>${data.risk_analysis.violation_rate}% thời gian</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h6>Vượt Tối Đa</h6>
                        <h3 class="text-warning">${data.risk_analysis.max_violation}</h3>
                        <small>khách</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h6>Liên Tục</h6>
                        <h3 class="text-info">${data.risk_analysis.consecutive_violations}</h3>
                        <small>ngày</small>
                    </div>
                </div>
            </div>
        </div>
        
        <h6 class="border-bottom pb-2">Chi Tiết Các Ngày Vi Phạm</h6>
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <th>Ngày</th>
                        <th class="text-end">Số Khách</th>
                        <th class="text-end">Ngưỡng</th>
                        <th class="text-end">Vượt</th>
                        <th class="text-end">% So Ngưỡng</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    if (data.risk_analysis.violation_days && data.risk_analysis.violation_days.length > 0) {
        data.risk_analysis.violation_days.forEach(v => {
            html += `
                <tr>
                    <td>${v.date}</td>
                    <td class="text-end"><strong>${v.customers}</strong></td>
                    <td class="text-end">${v.threshold}</td>
                    <td class="text-end text-danger">+${v.violation}</td>
                    <td class="text-end">${v.ratio}%</td>
                </tr>
            `;
        });
    } else {
        html += '<tr><td colspan="5" class="text-center text-success">Không có vi phạm</td></tr>';
    }
    
    html += '</tbody></table></div>';
    
    document.getElementById('violationContent').innerHTML = html;
    new bootstrap.Modal(document.getElementById('violationModal')).show();
}

// Load danh sách khách hàng (AJAX)
function loadCustomers(dsrCode, empName) {
    document.getElementById('customerEmpName').textContent = empName;
    document.getElementById('customerContent').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary"></div>
            <p class="mt-2">Đang tải danh sách khách hàng...</p>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('customerModal'));
    modal.show();
    
    const params = new URLSearchParams({
        action: 'get_customers',
        dsr_code: dsrCode,
        tu_ngay: '<?= $filters['tu_ngay'] ?? '' ?>',
        den_ngay: '<?= $filters['den_ngay'] ?? '' ?>',
        product_filter: '<?= $filters['product_filter'] ?? '' ?>'
    });
    
    fetch(`nhanvien_kpi.php?${params}`)
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                renderCustomers(result.data);
            } else {
                document.getElementById('customerContent').innerHTML = `
                    <div class="alert alert-danger">Lỗi: ${result.error}</div>
                `;
            }
        })
        .catch(err => {
            document.getElementById('customerContent').innerHTML = `
                <div class="alert alert-danger">Lỗi kết nối: ${err.message}</div>
            `;
        });
}

function renderCustomers(customers) {
    if (!customers || customers.length === 0) {
        document.getElementById('customerContent').innerHTML = `
            <div class="alert alert-info">Không có khách hàng trong khoảng thời gian này</div>
        `;
        return;
    }
    
    let html = `<div class="mb-3"><strong>Tổng: ${customers.length} khách hàng</strong></div>`;
    
    customers.forEach((c, idx) => {
        html += `
            <div class="customer-row">
                <div class="row">
                    <div class="col-md-8">
                        <strong>${idx + 1}. ${escapeHtml(c.customer_name || c.CustCode)}</strong>
                        <div class="text-muted small">${escapeHtml(c.customer_address || '-')} | ${escapeHtml(c.customer_province || '-')}</div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div><strong>${formatMoney(c.total_amount)}</strong></div>
                        <div class="text-muted small">${c.order_count} đơn hàng</div>
                    </div>
                </div>
                <div class="mt-2">
        `;
        
        if (c.orders && c.orders.length > 0) {
            c.orders.forEach(o => {
                html += `<span class="order-chip">${o.date}: ${escapeHtml(o.order_number)} (${formatMoney(o.amount)})</span>`;
            });
        }
        
        html += `</div></div>`;
    });
    
    document.getElementById('customerContent').innerHTML = html;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatMoney(val) {
    return parseFloat(val).toLocaleString('vi-VN') + 'đ';
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