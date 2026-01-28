<?php
$currentPage = 'import';
require_once dirname(__DIR__) . '/components/navbar.php';
renderNavbar($currentPage);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Danh Sách Nhân Viên</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }
        .import-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border: none;
        }
        .upload-area {
            border: 3px dashed #667eea;
            border-radius: 15px;
            padding: 50px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        .upload-area:hover {
            border-color: #764ba2;
            background: #f8f9fa;
        }
        .btn-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 40px;
            border-radius: 25px;
            color: white;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(118, 75, 162, 0.4);
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="import-card">
                    <div class="card-header">
                        <h3 class="mb-0"><i class="fas fa-users me-2"></i>Import Danh Sách Nhân Viên (DSNV)</h3>
                    </div>
                    <div class="card-body p-5">
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

                        <div class="info-box">
                            <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Cấu trúc file CSV</h6>
                            <p class="mb-0 small">File CSV cần có <strong>14 cột</strong> theo thứ tự:</p>
                            <code style="font-size: 0.85rem;">
                                ma_nv, ho_ten, gioi_tinh, ngay_sinh, sdt_ca_nhan, 
                                bo_phan, chuc_vu, base_tinh, khu_vuc, kenh_ban_hang, 
                                ngay_vao_cty, trang_thai, ma_nv_ql, ten_nv_ql
                            </code>
                        </div>

                        <form method="POST" action="dsnv.php?action=upload" enctype="multipart/form-data" id="uploadForm">
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-file-csv me-2"></i>File CSV
                                </label>
                                <div class="upload-area" id="uploadArea">
                                    <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color: #764ba2;"></i>
                                    <h5>Kéo thả file hoặc click để chọn</h5>
                                    <p class="text-muted mb-0">Chỉ chấp nhận file .csv</p>
                                    <input type="file" name="csv_file" class="d-none" id="csvFile" accept=".csv" required>
                                </div>
                                <div id="fileName" class="mt-3 text-center"></div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-custom btn-lg">
                                    <i class="fas fa-upload me-2"></i>Import Dữ liệu
                                </button>
                                <a href="dsnv.php?action=list" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-eye me-2"></i>Xem Danh sách
                                </a>
                                <a href="index.php" class="btn btn-outline-dark btn-lg">
                                    <i class="fas fa-home me-2"></i>Trang chủ
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const csvFile = document.getElementById('csvFile');
        const fileName = document.getElementById('fileName');

        uploadArea.addEventListener('click', () => csvFile.click());

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#764ba2';
            uploadArea.style.background = '#f8f9fa';
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.style.borderColor = '#667eea';
            uploadArea.style.background = 'white';
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            csvFile.files = e.dataTransfer.files;
            displayFileName();
        });

        csvFile.addEventListener('change', displayFileName);

        function displayFileName() {
            if (csvFile.files.length > 0) {
                fileName.innerHTML = `<div class="alert alert-info"><i class="fas fa-file-csv me-2"></i><strong>${csvFile.files[0].name}</strong></div>`;
            }
        }
    </script>
</body>
</html>