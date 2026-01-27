<?php
/**
 * ============================================
 * INDEX PAGE - REDIRECT TO NHANVIEN_REPORT
 * ============================================
 * 
 * Users must login first, then they will see nhanvien_report.php as homepage
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Not logged in - redirect to login
    header('Location: login.php');
    exit;
}

// Logged in - redirect directly to nhanvien_report (main homepage)
header('Location: nhanvien_report.php');
exit;
?>