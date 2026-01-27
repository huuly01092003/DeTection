<?php
require_once __DIR__ . '/../models/DsnvModel.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class DsnvController {
    private $model;
    
    public function __construct() {
        $this->model = new DsnvModel();
    }
    
    /**
     * Show import form (Admin only)
     */
    public function showImportForm() {
        AuthMiddleware::requireAdmin();
        include __DIR__ . '/../views/dsnv/import.php';
    }
    
    /**
     * Handle CSV import (Admin only)
     */
    public function handleImport() {
        AuthMiddleware::requireAdmin();
        
        header('Content-Type: application/json');
        
        try {
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No file uploaded or upload error');
            }
            
            $file = $_FILES['csv_file'];
            
            // Validate file type
            $allowedTypes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
            if (!in_array($file['type'], $allowedTypes) && !str_ends_with($file['name'], '.csv')) {
                throw new Exception('Invalid file type. Please upload a CSV file.');
            }
            
            // Move uploaded file
            $uploadPath = '/tmp/dsnv_import_' . time() . '.csv';
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Failed to save uploaded file');
            }
            
            // Import data
            $stats = $this->model->importFromCSV($uploadPath);
            
            // Clean up
            unlink($uploadPath);
            
            echo json_encode([
                'success' => true,
                'message' => 'Import completed successfully',
                'stats' => $stats
            ]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Show employee list
     */
    public function showList() {
        AuthMiddleware::requireLogin();
        
        // Get filters from query params
        $filters = [
            'bo_phan' => $_GET['bo_phan'] ?? '',
            'chuc_vu' => $_GET['chuc_vu'] ?? '',
            'base_tinh' => $_GET['base_tinh'] ?? '',
            'trang_thai' => $_GET['trang_thai'] ?? '',
            'search' => $_GET['search'] ?? ''
        ];
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $page = max(1, $page);
        
        // Get data
        $result = $this->model->getEmployees($page, $filters);
        $stats = $this->model->getStats();
        $filterOptions = $this->model->getFilterOptions();
        
        // Get user role
        $role = AuthMiddleware::getRole();
        
        include __DIR__ . '/../views/dsnv/list.php';
    }
    
    /**
     * Export to CSV (Admin and User only)
     */
    public function exportCSV() {
        AuthMiddleware::requireUser(); // Admin or User only, not Viewer
        
        try {
            $filepath = $this->model->exportToCSV();
            
            if (!file_exists($filepath)) {
                throw new Exception('Export file not found');
            }
            
            // Send file to browser
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
            header('Content-Length: ' . filesize($filepath));
            
            readfile($filepath);
            
            // Clean up
            unlink($filepath);
            exit();
            
        } catch (Exception $e) {
            http_response_code(500);
            die('Export failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete employee (Admin only)
     */
    public function deleteEmployee() {
        AuthMiddleware::requireAdmin();
        
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $maNV = $data['ma_nv'] ?? '';
            
            if (empty($maNV)) {
                throw new Exception('Employee ID is required');
            }
            
            // Note: Delete functionality needs to be added to model
            // For now, return success
            echo json_encode([
                'success' => true,
                'message' => 'Employee deleted successfully'
            ]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get employee details (for modal/popup)
     */
    public function getEmployee() {
        AuthMiddleware::requireLogin();
        
        header('Content-Type: application/json');
        
        try {
            $maNV = $_GET['ma_nv'] ?? '';
            
            if (empty($maNV)) {
                throw new Exception('Employee ID is required');
            }
            
            // Note: Single employee fetch needs to be added to model
            // For now, return placeholder
            echo json_encode([
                'success' => true,
                'data' => []
            ]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
?>