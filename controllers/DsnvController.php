<?php
require_once 'models/DsnvModel.php';

class DsnvController {
    private $model;

    public function __construct() {
        $this->model = new DsnvModel();
    }

    public function showImportForm() {
        require_once 'views/dsnv/import.php';
    }

    public function handleUpload() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: dsnv.php');
            exit;
        }

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = '❌ Vui lòng chọn file CSV';
            header('Location: dsnv.php');
            exit;
        }

        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'csv') {
            $_SESSION['error'] = '❌ Chỉ chấp nhận file CSV';
            header('Location: dsnv.php');
            exit;
        }

        $result = $this->model->importCSV($file['tmp_name']);
        
        if ($result['success']) {
            $message = "✅ <strong>Import DSNV thành công!</strong><br>";
            
            // Thêm thông tin UPDATE nếu có
            if (!empty($result['updated']) && $result['updated'] > 0) {
                $message .= "🔄 Bản ghi cập nhật: <strong style='color: #ffc107;'>{$result['updated']}</strong><br>";
            }
            
            $message .= "📊 Bản ghi thêm mới: <strong style='color: #28a745;'>{$result['inserted']}</strong><br>";
            
            if (!empty($result['skipped']) && $result['skipped'] > 0) {
                $message .= "⭐️ Bỏ qua: <strong>{$result['skipped']}</strong> dòng (MaNV trống)<br>";
            }
            
            if (!empty($result['errors']) && $result['errors'] > 0) {
                $message .= "⚠️ Lỗi: <strong>{$result['errors']}</strong> dòng<br>";
                $message .= "<small class='text-muted d-block mt-2'>💡 <strong>Gợi ý:</strong> Kiểm tra dữ liệu nhập vào</small>";
            }
            
            $_SESSION['success'] = $message;
        } else {
            $_SESSION['error'] = "❌ <strong>Import thất bại:</strong> {$result['error']}";
        }

        header('Location: dsnv.php');
        exit;
    }

    public function showList() {
        $filters = [
            'bo_phan' => $_GET['bo_phan'] ?? '',
            'chuc_vu' => $_GET['chuc_vu'] ?? '',
            'base_tinh' => $_GET['base_tinh'] ?? '',
            'trang_thai' => $_GET['trang_thai'] ?? '',
            'search' => $_GET['search'] ?? ''
        ];

        $data = $this->model->getAll($filters);
        $departments = $this->model->getDepartments();
        $positions = $this->model->getPositions();
        $provinces = $this->model->getProvinces();
        $statuses = $this->model->getStatuses();
        $totalCount = $this->model->getTotalCount();
        $activeCount = $this->model->getActiveCount();

        require_once 'views/dsnv/list.php';
    }
}
?>