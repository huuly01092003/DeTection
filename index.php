<?php
/**
 * ============================================
 * NEW INDEX PAGE - REDIRECT TO AUTH SYSTEM
 * ============================================
 * 
 * This replaces the old import page as homepage
 * Users must login first, then see dashboard (report page)
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Not logged in - redirect to login
    header('Location: login.php');
    exit;
}

// Logged in - redirect to dashboard (which redirects to report.php)
header('Location: dashboard.php');
exit;
?>