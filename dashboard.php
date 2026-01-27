<?php
/**
 * ============================================
 * DASHBOARD - REDIRECT TO NHANVIEN_REPORT
 * ============================================
 * After login, users are redirected to nhanvien_report.php (Employee Sales Report)
 * as the main homepage
 */

session_start();

require_once 'middleware/AuthMiddleware.php';

// Require login
AuthMiddleware::requireLogin();

// Redirect to nhanvien_report page (new homepage)
header('Location: nhanvien_report.php');
exit;
?>