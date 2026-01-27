<?php
require_once 'models/DskhModel.php';

class DskhController {
    private $model;
    private const PAGE_SIZE = 50;

    public function __construct() {
        $this->model = new DskhModel();
    }

    public function showImportForm() {
        require_once 'views/dskh/import.php';
    }

    public function handleUpload() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: dskh.php');
            exit;
        }

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Vui lòng chọn file CSV';
            header('Location: dskh.php');
            exit;
        }

        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'csv') {
            $_SESSION['error'] = 'Chỉ chấp nhận file CSV';
            header('Location: dskh.php');
            exit;
        }

        $result = $this->model->importCSV($file['tmp_name']);
        
        if ($result['success']) {
            $_SESSION['success'] = "Import thành công {$result['inserted']} bản ghi vào DSKH";
        } else {
            $_SESSION['error'] = "Import thất bại: {$result['error']}";
        }

        header('Location: dskh.php');
        exit;
    }

    // ✅ CẬP NHẬT: Thêm xử lý phân trang
    public function showList() {
        // Lấy page từ GET, mặc định trang 1
        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) $page = 1;
        
        $filters = [
            'tinh' => $_GET['tinh'] ?? '',
            'quan_huyen' => $_GET['quan_huyen'] ?? '',
            'ma_kh' => $_GET['ma_kh'] ?? '',
            'loai_kh' => $_GET['loai_kh'] ?? ''
        ];

        // Lấy dữ liệu với phân trang
        $data = $this->model->getAll($filters, $page);
        
        // Lấy tổng số bản ghi theo filter
        $totalCount = $this->model->getFilteredCount($filters);
        
        // Tính toán phân trang
        $totalPages = ceil($totalCount / self::PAGE_SIZE);
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
        }
        
        $provinces = $this->model->getProvinces();
        $districts = $this->model->getDistricts();
        $customerTypes = $this->model->getCustomerTypes();
        $totalCountAll = $this->model->getTotalCount(); // Tổng tất cả

        require_once 'views/dskh/list.php';
    }
}
?>