<?php
session_start();
require_once 'controllers/ImportController.php';

$controller = new ImportController();
$action = $_GET['action'] ?? 'index';

if ($action === 'upload') {
    $controller->upload();
} else {
    $controller->index();
}
?>
