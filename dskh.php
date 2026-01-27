<?php
session_start();
require_once 'controllers/DskhController.php';
require_once 'views/components/navbar.php';

$controller = new DskhController();
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