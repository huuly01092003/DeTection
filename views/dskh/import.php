<?php
// views/dskh/import.php
$currentPage = 'import';

// Use same auth/loader as other pages if possible, or fallback
require_once dirname(__DIR__) . '/components/navbar.php';

// Simulate standard page header data if needed, or just include navbar
// renderNavbar($currentPage) is called below in body usually in this project structure? 
// The original file called it before DOCTYPE, which is weird but okay. 
// I will move it to body or keep it top if that's how it works.
// Correction: standard pages seem to include navbar_loader.php inside body or have it separate.
// The original file had `require_once ...; renderNavbar($currentPage);` at the top.
// I will adapt to use the new layout style but keep navbar working.
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import DSKH</title>
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

        .data-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            padding: 30px;
        }

        .upload-area {
            border: 2px dashed #667eea;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .upload-area:hover {
            background: #f0f7ff;
            border-color: #764ba2;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-weight: 500;
        }
        .btn-gradient:hover {
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <?php renderNavbar($currentPage); ?>

    <div class="container-fluid mt-4">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-file-import me-2"></i>Import Danh Sách Khách Hàng</h2>
                    <p class="mb-0 opacity-75">Tải lên file CSV danh sách khách hàng</p>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="data-card">
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

                    <div class="alert alert-info border-0 bg-info-subtle">
                        <h6 class="alert-heading fw-bold"><i class="fas fa-info-circle me-2"></i>Cấu trúc file CSV yêu cầu:</h6>
                        <hr>
                        <code class="text-dark">ma_kh, area, ma_gsbh, ma_npp, ma_nvbh, ten_nvbh, ten_kh, loai_kh, dia_chi, quan_huyen, tinh, location</code>
                    </div>

                    <form method="POST" action="dskh.php?action=upload" enctype="multipart/form-data">
                        <div class="mb-4">
                            <div class="upload-area" id="uploadArea">
                                <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                <h5 class="fw-bold">Kéo thả hoặc chọn file CSV</h5>
                                <p class="text-muted small mb-0">Chỉ chấp nhận file .csv</p>
                                <input type="file" name="csv_file" class="d-none" id="csvFile" accept=".csv" required>
                            </div>
                            <div id="fileName" class="mt-3 text-center"></div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-gradient btn-lg py-3">
                                <i class="fas fa-upload me-2"></i>Tiến Hành Import
                            </button>
                            <a href="dskh.php?action=list" class="btn btn-light btn-lg border">
                                <i class="fas fa-list me-2"></i>Xem Danh Sách Đã Import
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const csvFile = document.getElementById('csvFile');
        const fileName = document.getElementById('fileName');

        uploadArea.onclick = () => csvFile.click();
        
        // Drag and drop effects
        uploadArea.ondragover = (e) => {
            e.preventDefault();
            uploadArea.style.background = '#eef2ff';
            uploadArea.style.borderColor = '#667eea';
        };
        uploadArea.ondragleave = () => {
            uploadArea.style.background = '#f8f9fa';
            uploadArea.style.borderColor = '#667eea';
        };
        uploadArea.ondrop = (e) => {
            e.preventDefault();
            uploadArea.style.background = '#f8f9fa';
            csvFile.files = e.dataTransfer.files;
            updateFileName();
        };

        csvFile.onchange = updateFileName;

        function updateFileName() {
            if (csvFile.files.length > 0) {
                fileName.innerHTML = `
                    <div class="d-inline-flex align-items-center px-3 py-2 rounded-pill bg-primary-subtle text-primary border border-primary">
                        <i class="fas fa-file-csv me-2"></i>
                        <strong>${csvFile.files[0].name}</strong>
                    </div>`;
            }
        }
    </script>
</body>
</html>