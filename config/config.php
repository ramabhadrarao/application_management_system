<?php
/**
 * Main Configuration File
 * 
 * File: config/config.php
 * Purpose: Application settings and constants
 * Author: Student Application Management System
 * Created: 2025
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Site Configuration
define('SITE_URL', 'http://localhost:99/application_management_system');
define('SITE_NAME', 'SWARNANDHRA College');
define('SITE_LOGO', 'assets/images/logo.png');
define('ADMIN_EMAIL', 'admin@swarnandhra.edu');
define('SUPPORT_PHONE', '+91-9876543210');

// Database Configuration
define('DB_HOST', 'localhost:99');
define('DB_NAME', 'student_application_db');
define('DB_USER', 'ramabhadrarao');
define('DB_PASS', 'nihita1981');

// Pagination Settings
define('RECORDS_PER_PAGE', 20);
define('MAX_PAGINATION_LINKS', 10);

// File Upload Settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png']);
define('UPLOAD_PATH', 'uploads/');
define('UPLOAD_DOCUMENTS_PATH', 'uploads/documents/');
define('UPLOAD_PHOTOS_PATH', 'uploads/photos/');

// User Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_PROGRAM_ADMIN', 'program_admin');
define('ROLE_STUDENT', 'student');

// Application Status
define('STATUS_DRAFT', 'draft');
define('STATUS_SUBMITTED', 'submitted');
define('STATUS_UNDER_REVIEW', 'under_review');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');
define('STATUS_CANCELLED', 'cancelled');
define('STATUS_FROZEN', 'frozen');

// Application Settings
define('CURRENT_ACADEMIC_YEAR', '2025-26');
define('APPLICATION_START_DATE', '2025-06-01');
define('APPLICATION_END_DATE', '2025-08-31');

// Email Settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');

// Security Settings
define('HASH_ALGO', PASSWORD_DEFAULT);
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 1800); // 30 minutes

// Date/Time Settings
define('DEFAULT_TIMEZONE', 'Asia/Kolkata');
date_default_timezone_set(DEFAULT_TIMEZONE);

// Include database configuration
require_once 'database.php';

/**
 * Helper Functions
 */

/**
 * Check if user is logged in
 * @return boolean
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user role
 * @return string|null
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Get current user ID
 * @return string|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Check if user has permission
 * @param string $permission
 * @return boolean
 */
function hasPermission($permission) {
    $role = getCurrentUserRole();
    
    $permissions = [
        ROLE_ADMIN => ['all'],
        ROLE_PROGRAM_ADMIN => ['view_applications', 'manage_applications', 'view_reports', 'manage_students'],
        ROLE_STUDENT => ['view_own_application', 'edit_own_application', 'submit_application']
    ];
    
    if (!isset($permissions[$role])) {
        return false;
    }
    
    return in_array('all', $permissions[$role]) || in_array($permission, $permissions[$role]);
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/auth/login.php');
        exit;
    }
}

/**
 * Redirect if user doesn't have permission
 * @param string $permission
 */
function requirePermission($permission) {
    requireLogin();
    if (!hasPermission($permission)) {
        header('Location: ' . SITE_URL . '/dashboard.php?error=access_denied');
        exit;
    }
}

/**
 * Generate CSRF token
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return boolean
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Format date for display
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'd-m-Y') {
    if (empty($date) || $date == '0000-00-00') {
        return '-';
    }
    return date($format, strtotime($date));
}

/**
 * Format date and time for display
 * @param string $datetime
 * @return string
 */
function formatDateTime($datetime) {
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return '-';
    }
    return date('d-m-Y H:i:s', strtotime($datetime));
}

/**
 * Sanitize input data
 * @param mixed $data
 * @return mixed
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate UUID
 * @return string
 */
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}