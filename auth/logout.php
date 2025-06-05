<?php
/**
 * User Logout Handler
 * 
 * File: auth/logout.php
 * Purpose: Handle user logout and session destruction
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../config/config.php';

// Check if user was logged in
$was_logged_in = isLoggedIn();

// Destroy session and logout
session_unset();
session_destroy();

// Clear any remember me cookies
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Set logout message based on reason
$logout_message = 'You have been successfully logged out.';
$logout_type = 'success';

if (isset($_GET['reason'])) {
    switch ($_GET['reason']) {
        case 'timeout':
            $logout_message = 'Your session has expired. Please login again.';
            $logout_type = 'warning';
            break;
        case 'security':
            $logout_message = 'You have been logged out for security reasons.';
            $logout_type = 'danger';
            break;
        case 'inactive':
            $logout_message = 'You have been logged out due to inactivity.';
            $logout_type = 'info';
            break;
    }
}

// Start new session for flash message
session_start();
$_SESSION['flash_message'] = $logout_message;
$_SESSION['flash_type'] = $logout_type;

// Redirect to login page
header('Location: ' . SITE_URL . '/auth/login.php');
exit;
?>