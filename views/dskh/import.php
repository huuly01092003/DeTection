
<?php
$currentPage = 'import';
require_once dirname(__DIR__) . '/components/navbar.php';renderNavbar($currentPage);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Import DSKH</title>
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
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
        }
        .upload-area {
            border: 3px dashed #667eea;
            border-radius: 15px;
            padding: 50px;
            text-align: center;
            cursor: pointer;
        }
        .upload-area:hover {
            border-color: #764ba2;
            background: #f8f9ff;
        }
        .btn-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 40px;
            border-radius: 25px;
            color: white;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="import-card">
                    <div class="card-header">
                        <h3><i class="fas fa-users me-2"></i>Import Danh sách Khách hàng</h3>
                    </div>
                    <div class="card-body p-5">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible">
                                <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['success']); ?>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible">
                                <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>

                        <div class="alert alert-info">
                            <strong>Cấu trúc file CSV:</strong><br>
                            <code>ma_kh, area, ma_gsbh, ma_npp, ma_nvbh, ten_nvbh, ten_kh, loai_kh, dia_chi, quan_huyen, tinh, location</code>
                        </div>

                        <form method="POST" action="dskh.php?action=upload" enctype="multipart/form-data">
                            <div class="mb-4">
                                <div class="upload-area" id="uploadArea">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                    <h5>Kéo thả file hoặc click để chọn</h5>
                                    <input type="file" name="csv_file" class="d-none" id="csvFile" accept=".csv" required>
                                </div>
                                <div id="fileName" class="mt-3 text-center"></div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-custom btn-lg">
                                    <i class="fas fa-upload me-2"></i>Import Dữ liệu
                                </button>
                                <a href="dskh.php?action=list" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-eye me-2"></i>Xem Danh sách
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

        uploadArea.onclick = () => csvFile.click();
        csvFile.onchange = () => {
            if (csvFile.files.length > 0) {
                fileName.innerHTML = `<div class="alert alert-info"><i class="fas fa-file-csv me-2"></i>${csvFile.files[0].name}</div>`;
            }
        };
    </script>
</body>
</html>