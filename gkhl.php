<?php
session_start();
require_once 'controllers/GkhlController.php';
require_once 'views/components/navbar.php';

$controller = new GkhlController();
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