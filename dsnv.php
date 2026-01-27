<?php
/**
 * DSNV (Danh Sách Nhân Viên) - Main Router
 * 
 * Routes:
 * - / (default) or ?action=import -> Import form (Admin only)
 * - ?action=import (POST) -> Handle CSV import (Admin only)
 * - ?action=list -> Show employee list (All authenticated users)
 * - ?action=export -> Export to CSV (Admin and User only)
 * - ?action=delete -> Delete employee (Admin only)
 * - ?action=get&ma_nv=XXX -> Get single employee (All authenticated users)
 */

require_once __DIR__ . '/controllers/DsnvController.php';

$controller = new DsnvController();
$action = $_GET['action'] ?? 'import';

try {
    switch ($action) {
        case 'import':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Handle POST - import CSV
                $controller->handleImport();
            } else {
                // Show import form
                $controller->showImportForm();
            }
            break;
        
        case 'list':
            // Show employee list
            $controller->showList();
            break;
        
        case 'export':
            // Export to CSV
            $controller->exportCSV();
            break;
        
        case 'delete':
            // Delete employee
            $controller->deleteEmployee();
            break;
        
        case 'get':
            // Get single employee
            $controller->getEmployee();
            break;
        
        default:
            http_response_code(404);
            die('Action not found');
    }
} catch (Exception $e) {
    http_response_code(500);
    
    // Check if it's an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    } else {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Error</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container mt-5">
                <div class="alert alert-danger">
                    <h4 class="alert-heading">Error</h4>
                    <p>' . htmlspecialchars($e->getMessage()) . '</p>
                    <hr>
                    <a href="dsnv.php?action=list" class="btn btn-primary">Back to List</a>
                </div>
            </div>
        </body>
        </html>';
    }
}
?>