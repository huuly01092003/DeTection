<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Nhân Viên - DSNV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }
        
        .import-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            border: none;
        }
        
        .card-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 25px;
        }
        
        .card-header h3 {
            margin: 0;
            font-weight: 600;
        }
        
        .upload-area {
            border: 3px dashed #10b981;
            border-radius: 15px;
            padding: 60px 20px;
            text-align: center;
            background: #f0fdf4;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .upload-area:hover {
            background: #dcfce7;
            border-color: #059669;
        }
        
        .upload-area.dragover {
            background: #dcfce7;
            border-color: #059669;
            transform: scale(1.02);
        }
        
        .upload-icon {
            font-size: 64px;
            color: #10b981;
            margin-bottom: 20px;
        }
        
        .btn-import {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            padding: 12px 40px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 10px;
        }
        
        .btn-import:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .stats-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stats-number {
            font-size: 32px;
            font-weight: bold;
            color: #10b981;
        }
        
        .stats-label {
            color: #6b7280;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .file-info {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            display: none;
        }
        
        .progress {
            height: 25px;
            border-radius: 10px;
            display: none;
            margin-top: 20px;
        }
        
        .progress-bar {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            font-weight: 600;
        }
        
        .instructions {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .instructions h5 {
            color: #92400e;
            margin-bottom: 10px;
        }
        
        .instructions ul {
            margin: 0;
            padding-left: 20px;
            color: #78350f;
        }
    </style>
</head>
<body>
    <div class="container import-container">
        <!-- Header -->
        <div class="text-center mb-4">
            <h1 class="text-white mb-3">
                <i class="fas fa-users"></i> Import Danh Sách Nhân Viên
            </h1>
            <a href="dsnv.php?action=list" class="btn btn-light">
                <i class="fas fa-list"></i> Xem Danh Sách
            </a>
        </div>
        
        <!-- Instructions -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="instructions">
                    <h5><i class="fas fa-info-circle"></i> Hướng Dẫn Import</h5>
                    <ul>
                        <li>File CSV phải có các cột: Mã NV, Họ tên, Giới tính, Ngày sinh, SĐT cá nhân, Bộ phận, Chức vụ, Base tỉnh, Khu vực, Kênh bán hàng, Ngày vào CTY, Trạng thái, Mã NV QL, Tên NV QL</li>
                        <li>Hệ thống tự động phát hiện: <strong>INSERT</strong> (nhân viên mới), <strong>UPDATE</strong> (thay đổi thông tin), <strong>DELETE</strong> (nhân viên không còn trong file)</li>
                        <li>Định dạng ngày: DD/MM/YYYY hoặc YYYY-MM-DD</li>
                        <li>Mã NV là trường bắt buộc và không được trùng lặp</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Upload Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-file-upload"></i> Upload File CSV</h3>
            </div>
            <div class="card-body">
                <form id="importForm" enctype="multipart/form-data">
                    <div class="upload-area" id="uploadArea">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <h4 class="mb-3">Kéo thả file CSV vào đây</h4>
                        <p class="text-muted mb-3">hoặc</p>
                        <label for="csvFile" class="btn btn-primary btn-lg">
                            <i class="fas fa-folder-open"></i> Chọn File
                        </label>
                        <input type="file" id="csvFile" name="csv_file" accept=".csv" style="display: none;" required>
                    </div>
                    
                    <div class="file-info" id="fileInfo">
                        <i class="fas fa-file-csv text-success"></i>
                        <strong id="fileName"></strong>
                        <span class="text-muted" id="fileSize"></span>
                    </div>
                    
                    <div class="progress" id="uploadProgress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-import btn-lg" id="importBtn">
                            <i class="fas fa-upload"></i> Import Dữ Liệu
                        </button>
                    </div>
                </form>
                
                <div id="resultArea" class="mt-4"></div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('csvFile');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const importForm = document.getElementById('importForm');
        const importBtn = document.getElementById('importBtn');
        const resultArea = document.getElementById('resultArea');
        const uploadProgress = document.getElementById('uploadProgress');
        
        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0 && files[0].name.endsWith('.csv')) {
                fileInput.files = files;
                showFileInfo(files[0]);
            }
        });
        
        // File input change
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                showFileInfo(e.target.files[0]);
            }
        });
        
        function showFileInfo(file) {
            fileName.textContent = file.name;
            fileSize.textContent = `(${(file.size / 1024).toFixed(2)} KB)`;
            fileInfo.style.display = 'block';
        }
        
        // Form submit
        importForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!fileInput.files.length) {
                showAlert('danger', 'Vui lòng chọn file CSV!');
                return;
            }
            
            const formData = new FormData();
            formData.append('csv_file', fileInput.files[0]);
            
            importBtn.disabled = true;
            importBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
            uploadProgress.style.display = 'block';
            resultArea.innerHTML = '';
            
            // Simulate progress
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += 10;
                if (progress >= 90) {
                    clearInterval(progressInterval);
                } else {
                    updateProgress(progress);
                }
            }, 200);
            
            try {
                const response = await fetch('dsnv.php?action=import', {
                    method: 'POST',
                    body: formData
                });
                
                clearInterval(progressInterval);
                updateProgress(100);
                
                const result = await response.json();
                
                setTimeout(() => {
                    uploadProgress.style.display = 'none';
                    
                    if (result.success) {
                        showSuccessStats(result.stats);
                    } else {
                        showAlert('danger', result.error || 'Import thất bại!');
                    }
                    
                    importBtn.disabled = false;
                    importBtn.innerHTML = '<i class="fas fa-upload"></i> Import Dữ Liệu';
                }, 500);
                
            } catch (error) {
                clearInterval(progressInterval);
                uploadProgress.style.display = 'none';
                showAlert('danger', 'Lỗi kết nối: ' + error.message);
                importBtn.disabled = false;
                importBtn.innerHTML = '<i class="fas fa-upload"></i> Import Dữ Liệu';
            }
        });
        
        function updateProgress(percent) {
            const progressBar = uploadProgress.querySelector('.progress-bar');
            progressBar.style.width = percent + '%';
            progressBar.textContent = percent + '%';
        }
        
        function showSuccessStats(stats) {
            const html = `
                <div class="alert alert-success">
                    <h4 class="alert-heading"><i class="fas fa-check-circle"></i> Import Thành Công!</h4>
                    <hr>
                    <div class="row g-3 mt-3">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number text-success">${stats.inserted}</div>
                                <div class="stats-label">Thêm Mới</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number text-primary">${stats.updated}</div>
                                <div class="stats-label">Cập Nhật</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number text-danger">${stats.deleted}</div>
                                <div class="stats-label">Xóa</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number text-secondary">${stats.unchanged}</div>
                                <div class="stats-label">Không Đổi</div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-4">
                        <a href="dsnv.php?action=list" class="btn btn-success">
                            <i class="fas fa-list"></i> Xem Danh Sách
                        </a>
                    </div>
                </div>
            `;
            resultArea.innerHTML = html;
        }
        
        function showAlert(type, message) {
            const html = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            resultArea.innerHTML = html;
        }
    </script>
</body>
</html>