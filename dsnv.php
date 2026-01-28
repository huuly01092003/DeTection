<?php
session_start();
require_once 'controllers/DsnvController.php';
require_once 'views/components/navbar.php';

$controller = new DsnvController();
$action = $_GET['action'] ?? 'import';

switch ($action) {
    case 'upload':
        $controller->handleUpload();
        break;
    case 'list':
        $controller->showList();
        break;
    default:
        $controller->showImportForm();
        break;
}