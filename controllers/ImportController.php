<?php
require_once 'models/OrderDetailModel.php';

class ImportController {
    private $model;

    public function __construct() {
        $this->model = new OrderDetailModel();
    }

    public function index() {
        require_once 'views/import.php';
    }

    public function upload() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php');
            exit;
        }

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Vui lòng chọn file CSV';
            header('Location: index.php');
            exit;
        }

        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'csv') {
            $_SESSION['error'] = 'Chỉ chấp nhận file CSV';
            header('Location: index.php');
            exit;
        }

        $result = $this->model->importCSV($file['tmp_name']);
        
        if ($result['success']) {
            $message = "✅ <strong>Import thành công!</strong><br>";
            $message .= "📊 Bản ghi thêm/cập nhật: <strong style='color: #28a745;'>{$result['inserted']}</strong><br>";
            
            if (!empty($result['skipped']) && $result['skipped'] > 0) {
                $message .= "⏭️  Bỏ qua: <strong>{$result['skipped']}</strong> dòng (dữ liệu không đủ hoặc không hợp lệ)<br>";
            }
            
            if (!empty($result['errors']) && $result['errors'] > 0) {
                $message .= "⚠️  Lỗi: <strong>{$result['errors']}</strong> dòng<br>";
            }
            
            if (!empty($result['total_lines'])) {
                $message .= "<small class='text-muted'>📝 Tổng dòng xử lý: {$result['total_lines']}</small>";
            }
            
            $_SESSION['success'] = $message;
        } else {
            $_SESSION['error'] = "❌ <strong>Import thất bại:</strong> {$result['error']}";
        }

        header('Location: import.php');
        exit;
    }
}
?>