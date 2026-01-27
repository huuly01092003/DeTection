<?php
/**
 * ============================================
 * LOGOUT HANDLER
 * ============================================
 */

session_start();

require_once 'middleware/AuthMiddleware.php';

// Perform logout
AuthMiddleware::logout();

// Set success message
session_start();
$_SESSION['success'] = 'Bạn đã đăng xuất thành công.';

// Redirect to login
header('Location: login.php');
exit;
?>