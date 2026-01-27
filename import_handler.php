<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once 'controllers/ImportController.php';
$controller = new ImportController();
$controller->upload();