<?php
/**
 * Common Header Include
 * 
 * File: includes/header.php
 * Purpose: Common header with navigation for all pages
 * Author: Student Application Management System
 * Created: 2025
 */

if (!isset($_SESSION)) {
    session_start();
}

require_once '../config/config.php';

// Check if user is logged in for protected pages
if (!isset($public_page) || !$public_page) {
    requireLogin();
}

$current_user_role = getCurrentUserRole();
$current_user_id = getCurrentUserId();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- CSS files -->
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/css/tabler.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/icons@2.44.0/icons-sprite.svg" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    
    <style>
        :root {
            --tblr-primary: #0054a6;
            --tblr-primary-rgb: 0, 84, 166;
        }
        
        .navbar-brand-image {
            height: 2rem;
        }
        
        .avatar-sm {
            width: 2rem;
            height: 2rem;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .application-card {
            transition: all 0.2s ease;
        }
        
        .application-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .sidebar-nav .nav-link {
            border-radius: 0.375rem;
            margin-bottom: 0.25rem;
        }
        
        .sidebar-nav .nav-link:hover {
            background-color: rgba(var(--tblr-primary-rgb), 0.1);
        }
        
        .sidebar-nav .nav-link.active {
            background-color: var(--tblr-primary);
            color: white;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--tblr-primary) 0%, #0066cc 100%);
            color: white;
        }
        
        .card-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .card-stats .card-body {
            padding: 1.5rem;
        }
        
        .stats-icon {
            opacity: 0.7;
            font-size: 2rem;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn-loading {
            position: relative;
        }
        
        .btn-loading:disabled {
            opacity: 0.7;
        }
        
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(var(--tblr-primary-rgb), 0.05);
        }
        
        .alert-modern {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .form-floating label {
            padding-left: 0.75rem;
        }
        
        @media (max-width: 768px) {
            .navbar-nav .nav-link {
                padding: 0.5rem 1rem;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
    
    <?php if (isset($extra_css)): ?>
        <?php echo $extra_css; ?>
    <?php endif; ?>
</head>
<body>
    <div class="page">
        <!-- Sidebar -->
        <?php if (isLoggedIn()): ?>
        <aside class="navbar navbar-vertical navbar-expand-lg navbar-dark">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <h1 class="navbar-brand navbar-brand-autodark">
                    <a href="<?php echo SITE_URL; ?>/dashboard.php" class="text-decoration-none text-white">
                        <i class="fas fa-graduation-cap me-2"></i>
                        <?php echo SITE_NAME; ?>
                    </a>
                </h1>
                
                <div class="collapse navbar-collapse" id="sidebar-menu">
                    <ul class="navbar-nav pt-lg-3 sidebar-nav">
                        <!-- Dashboard -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>" 
                               href="<?php echo SITE_URL; ?>/dashboard.php">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="fas fa-tachometer-alt"></i>
                                </span>
                                <span class="nav-link-title">Dashboard</span>
                            </a>
                        </li>
                        
                        <?php if ($current_user_role === ROLE_STUDENT): ?>
                        <!-- Student Menu -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'application') !== false) ? 'active' : ''; ?>" 
                               href="<?php echo SITE_URL; ?>/student/application.php">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="fas fa-file-alt"></i>
                                </span>
                                <span class="nav-link-title">My Application</span>
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'documents.php') ? 'active' : ''; ?>" 
                               href="<?php echo SITE_URL; ?>/student/documents.php">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="fas fa-paperclip"></i>
                                </span>
                                <span class="nav-link-title">Documents</span>
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'status.php') ? 'active' : ''; ?>" 
                               href="<?php echo SITE_URL; ?>/student/status.php">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="fas fa-chart-line"></i>
                                </span>
                                <span class="nav-link-title">Application Status</span>
                            </a>
                        </li>
                        
                        <?php elseif ($current_user_role === ROLE_PROGRAM_ADMIN): ?>
                        <!-- Program Admin Menu -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo (strpos($_SERVER['PHP_SELF'], 'applications') !== false) ? 'active' : ''; ?>" 
                               href="#applications" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="fas fa-file-alt"></i>
                                </span>
                                <span class="nav-link-title">Applications</span>
                            </a>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/applications/list.php">
                                    <i class="fas fa-list me-2"></i>All Applications
                                </a>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/applications/pending.php">
                                    <i class="fas fa-clock me-2"></i>Pending Review
                                </a>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/applications/approved.php">
                                    <i class="fas fa-check me-2"></i>Approved
                                </a>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/applications/rejected.php">
                                    <i class="fas fa-times me-2"></i>Rejected
                                </a>
                            </div>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'students') !== false) ? 'active' : ''; ?>" 
                               href="<?php echo SITE_URL; ?>/admin/students/list.php">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="fas fa-users"></i>
                                </span>
                                <span class="nav-link-title">Students</span>
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'reports') !== false) ? 'active' : ''; ?>" 
                               href="<?php echo SITE_URL; ?>/admin/reports/program.php">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="fas fa-chart-bar"></i>
                                </span>
                                <span class="nav-link-title">Reports</span>
                            </a>
                        </li>
                        
                        <?php elseif ($current_user_role === ROLE_ADMIN): ?>
                        <!-- Admin Menu -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo (strpos($_SERVER['PHP_SELF'], 'applications') !== false) ? 'active' : ''; ?>" 
                               href="#applications" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="fas fa-file-alt"></i>
                                </span>
                                <span class="nav-link-title">Applications</span>
                            </a>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/applications/list.php">
                                    <i class="fas fa-list me-2"></i>All Applications
                                </a>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/applications/pending.php">
                                    <i class="fas fa-clock me-2"></i>Pending Review
                                </a>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/applications/approved.php">
                                    <i class="fas fa-check me-2"></i>Approved
                                </a>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/applications/rejected.php">
                                    <i class="fas fa-times me-2"></i>Rejected
                                </a>
                            </div>
                        </li>
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo (strpos($_SERVER['PHP_SELF'], 'users') !== false) ? 'active' : ''; ?>" 
                               href="#users" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="fas fa-users"></i>
                                </span>
                                <span class="nav-link-title">Users</span>
                            </a>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/users/students.php">
                                    <i class="fas fa-user-graduate me-2"></i>Students
                                </a>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/users/program-admins.php">
                                    <i class="fas fa-user-tie me-2"></i>Program Admins
                                </a>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/users/add.php">
                                    <i class="fas fa-user-plus me-2"></i>Add User
                                </a>
                            </div>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'programs') !== false) ? 'active' : ''; ?>" 
                               href="<?php echo SITE_URL; ?>/admin/programs/list.php">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="fas fa-graduation-cap"></i>
                                </span>
                                <span class="nav-link-title">Programs</span>
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'reports') !== false) ? 'active' : ''; ?>" 
                               href="<?php echo SITE_URL; ?>/admin/reports/overview.php">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="fas fa-chart-bar"></i>
                                </span>
                                <span class="nav-link-title">Reports</span>
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'settings') !== false) ? 'active' : ''; ?>" 
                               href="<?php echo SITE_URL; ?>/admin/settings/general.php">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="fas fa-cog"></i>
                                </span>
                                <span class="nav-link-title">Settings</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <!-- Common Menu Items -->
                        <li class="nav-item mt-auto">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>" 
                               href="<?php echo SITE_URL; ?>/profile.php">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="fas fa-user"></i>
                                </span>
                                <span class="nav-link-title">Profile</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </aside>
        <?php endif; ?>
        
        <!-- Page wrapper -->
        <div class="page-wrapper">
            <?php if (isLoggedIn()): ?>
            <!-- Top Navigation -->
            <header class="navbar navbar-expand-md navbar-light d-print-none">
                <div class="container-xl">
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    
                    <div class="navbar-nav flex-row order-md-last">
                        <!-- Notifications -->
                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Open notifications">
                                <span class="avatar avatar-sm">
                                    <i class="fas fa-bell"></i>
                                </span>
                                <span class="badge bg-red position-absolute top-0 start-100 translate-middle badge-pill">3</span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                                <h6 class="dropdown-header">Notifications</h6>
                                <a href="#" class="dropdown-item">
                                    <div class="d-flex">
                                        <div class="flex-fill">
                                            <div class="font-weight-medium">Application submitted</div>
                                            <div class="text-muted small">2 minutes ago</div>
                                        </div>
                                    </div>
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="#" class="dropdown-item text-center">View all notifications</a>
                            </div>
                        </div>
                        
                        <!-- User Menu -->
                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Open user menu">
                                <span class="avatar avatar-sm">
                                    <i class="fas fa-user"></i>
                                </span>
                                <div class="d-none d-xl-block ps-2">
                                    <div><?php echo $_SESSION['user_email']; ?></div>
                                    <div class="mt-1 small text-muted"><?php echo ucfirst($current_user_role); ?></div>
                                </div>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                                <a href="<?php echo SITE_URL; ?>/profile.php" class="dropdown-item">
                                    <i class="fas fa-user me-2"></i>Profile
                                </a>
                                <a href="<?php echo SITE_URL; ?>/change-password.php" class="dropdown-item">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="<?php echo SITE_URL; ?>/auth/logout.php" class="dropdown-item text-danger">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            <?php endif; ?>
            
            <!-- Page header -->
            <?php if (isset($page_title)): ?>
            <div class="page-header d-print-none">
                <div class="container-xl">
                    <div class="row g-2 align-items-center">
                        <div class="col">
                            <h2 class="page-title">
                                <?php echo $page_title; ?>
                            </h2>
                            <?php if (isset($page_subtitle)): ?>
                            <div class="text-muted mt-1"><?php echo $page_subtitle; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (isset($page_actions)): ?>
                        <div class="col-auto">
                            <?php echo $page_actions; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Page body -->
            <div class="page-body">
                <div class="container-xl">
                    <!-- Flash Messages -->
                    <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-<?php echo $_SESSION['flash_type']; ?> alert-dismissible alert-modern" role="alert">
                                <div class="d-flex">
                                    <div class="flex-fill">
                                        <?php echo $_SESSION['flash_message']; ?>
                                    </div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        </div>
                    </div>
                    <?php 
                    unset($_SESSION['flash_message']);
                    unset($_SESSION['flash_type']);
                    endif; 
                    ?>