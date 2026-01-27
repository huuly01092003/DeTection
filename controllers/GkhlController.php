<?php
require_once 'models/GkhlModel.php';

class GkhlController {
    private $model;

    public function __construct() {
        $this->model = new GkhlModel();
    }

    public function showImportForm() {
        require_once 'views/gkhl/import.php';
    }

    public function handleUpload() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: gkhl.php');
            exit;
        }

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = '❌ Vui lòng chọn file CSV';
            header('Location: gkhl.php');
            exit;
        }

        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'csv') {
            $_SESSION['error'] = '❌ Chỉ chấp nhận file CSV';
            header('Location: gkhl.php');
            exit;
        }

        $result = $this->model->importCSV($file['tmp_name']);
        
        if ($result['success']) {
            $message = "✅ <strong>Import GKHL thành công!</strong><br>";
            
            // Thêm thông tin DELETE nếu có (chuyển NVBH)
            if (!empty($result['deleted']) && $result['deleted'] > 0) {
                $message .= "🗑️ Bản ghi xóa (chuyển NVBH): <strong style='color: #dc3545;'>{$result['deleted']}</strong><br>";
            }
            
            // Thêm thông tin UPDATE nếu có
            if (!empty($result['updated']) && $result['updated'] > 0) {
                $message .= "🔄 Bản ghi cập nhật: <strong style='color: #ffc107;'>{$result['updated']}</strong><br>";
            }
            
            $message .= "📊 Bản ghi thêm mới: <strong style='color: #28a745;'>{$result['inserted']}</strong><br>";
            
            if (!empty($result['skipped']) && $result['skipped'] > 0) {
                $message .= "⭐️ Bỏ qua: <strong>{$result['skipped']}</strong> dòng (MaKHDMS trống)<br>";
            }
            
            if (!empty($result['errors']) && $result['errors'] > 0) {
                $message .= "⚠️ Lỗi: <strong>{$result['errors']}</strong> dòng<br>";
                $message .= "<small class='text-muted d-block mt-2'>💡 <strong>Gợi ý:</strong> Kiểm tra dữ liệu nhập vào</small>";
            }
            
            $message .= "<small class='text-muted d-block mt-2'>⚙️ <strong>Phương pháp:</strong> MaKHDMS (Detect MaNVBH Change)</small>";
            
            $_SESSION['success'] = $message;
        } else {
            $_SESSION['error'] = "❌ <strong>Import thất bại:</strong> {$result['error']}";
        }

        header('Location: gkhl.php');
        exit;
    }

    public function showList() {
        $filters = [
            'ma_nvbh' => $_GET['ma_nvbh'] ?? '',
            'ma_kh_dms' => $_GET['ma_kh_dms'] ?? '',
            'khop_sdt' => $_GET['khop_sdt'] ?? '',
            'nam_sinh' => $_GET['nam_sinh'] ?? ''
        ];

        $data = $this->model->getAll($filters);
        $saleStaff = $this->model->getSaleStaff();
        $birthYears = $this->model->getBirthYears();
        $totalCount = $this->model->getTotalCount();
        $phoneMatchCount = $this->model->getPhoneMatchCount();

        require_once 'views/gkhl/list.php';
    }
}
?>