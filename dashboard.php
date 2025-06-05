<?php
/**
 * Modern Dashboard Page
 * 
 * File: dashboard.php
 * Purpose: Role-based dashboard with modern consistent styling
 * Author: Student Application Management System
 * Created: 2025
 */

require_once 'config/config.php';
require_once 'classes/User.php';
require_once 'classes/Application.php';
require_once 'classes/Program.php';

// Require authentication
requireLogin();

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$application = new Application($db);
$program = new Program($db);

$current_user_role = getCurrentUserRole();
$current_user_id = getCurrentUserId();

$page_title = 'Dashboard';
$page_subtitle = 'Welcome to your dashboard';

// Get user details
$user_details = $user->getUserById($current_user_id);

// Initialize dashboard data
$dashboard_data = [];

// Role-specific data loading
switch ($current_user_role) {
    case ROLE_STUDENT:
        // Student dashboard data
        $student_application = $application->getByUserId($current_user_id);
        $dashboard_data['application'] = $student_application;
        
        if ($student_application) {
            // Get application progress
            $dashboard_data['progress'] = calculateApplicationProgress($student_application);
        }
        break;
        
    case ROLE_PROGRAM_ADMIN:
        // Program admin dashboard data
        $admin_programs = $program->getProgramsByAdmin($current_user_id);
        $dashboard_data['programs'] = $admin_programs;
        
        // Get applications for admin's programs
        $program_ids = array_column($admin_programs, 'id');
        if (!empty($program_ids)) {
            $filters = ['program_id' => $program_ids[0]]; // Default to first program
            $dashboard_data['applications'] = $application->getApplications($filters, 1, 10);
            $dashboard_data['program_stats'] = $program->getStatistics($program_ids[0]);
        }
        break;
        
    case ROLE_ADMIN:
        // Admin dashboard data
        $dashboard_data['all_programs'] = $program->getAllActivePrograms();
        $dashboard_data['overall_stats'] = $program->getStatistics();
        
        // Recent applications
        $dashboard_data['recent_applications'] = $application->getApplications([], 1, 10);
        break;
}

/**
 * Calculate application completion progress
 */
function calculateApplicationProgress($app) {
    $total_steps = 5; // Basic info, education, documents, review, submit
    $completed_steps = 0;
    
    // Basic info completed
    if (!empty($app['student_name']) && !empty($app['father_name']) && 
        !empty($app['mobile_number']) && !empty($app['email'])) {
        $completed_steps++;
    }
    
    // Address info completed
    if (!empty($app['present_village']) && !empty($app['permanent_village'])) {
        $completed_steps++;
    }
    
    // Additional details completed
    if (!empty($app['caste']) && !empty($app['religion'])) {
        $completed_steps++;
    }
    
    // Documents uploaded (simplified check)
    if ($app['status'] !== STATUS_DRAFT) {
        $completed_steps++;
    }
    
    // Submitted
    if ($app['status'] !== STATUS_DRAFT) {
        $completed_steps++;
    }
    
    return [
        'percentage' => round(($completed_steps / $total_steps) * 100),
        'completed_steps' => $completed_steps,
        'total_steps' => $total_steps
    ];
}

/**
 * Helper function to get status color for badges
 */
function getStatusColor($status) {
    switch ($status) {
        case STATUS_DRAFT: return 'secondary';
        case STATUS_SUBMITTED: return 'info';
        case STATUS_UNDER_REVIEW: return 'warning';
        case STATUS_APPROVED: return 'success';
        case STATUS_REJECTED: return 'danger';
        case STATUS_FROZEN: return 'primary';
        default: return 'secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    
    <!-- CSS files -->
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/css/tabler.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="assets/css/global-styles.css" rel="stylesheet"/>
    
    <style>
        /* Dashboard-specific styles */
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
            background: var(--bg-light);
        }
        
        .dashboard-sidebar {
            width: 280px;
            background: var(--bg-white);
            box-shadow: var(--shadow-sm);
            border-right: 1px solid var(--border-light);
        }
        
        .dashboard-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .dashboard-topbar {
            background: var(--bg-white);
            padding: var(--spacing-lg) var(--spacing-xl);
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .dashboard-content {
            flex: 1;
            padding: var(--spacing-xl);
            overflow-y: auto;
        }
        
        .welcome-banner {
            background: var(--gradient-primary);
            color: white;
            padding: var(--spacing-xxl);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-xl);
            position: relative;
            overflow: hidden;
        }
        
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50px, -50px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
        }
        
        .action-card {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            text-decoration: none;
            color: var(--text-dark);
        }
        
        .action-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-4px);
            border-color: var(--primary-color);
            color: var(--text-dark);
            text-decoration: none;
        }
        
        .action-icon {
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--spacing-md);
            color: white;
            font-size: 1.5rem;
        }
        
        .progress-section {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            box-shadow: var(--shadow-md);
            margin-bottom: var(--spacing-xl);
        }
        
        .application-timeline {
            position: relative;
            padding-left: var(--spacing-xl);
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: var(--spacing-lg);
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 8px;
            width: 12px;
            height: 12px;
            background: var(--border-color);
            border-radius: 50%;
            border: 3px solid var(--bg-white);
        }
        
        .timeline-item.completed::before {
            background: var(--success-color);
        }
        
        .timeline-item.current::before {
            background: var(--primary-color);
        }
        
        .timeline-item::after {
            content: '';
            position: absolute;
            left: -18px;
            top: 20px;
            width: 2px;
            height: calc(100% - 12px);
            background: var(--border-light);
        }
        
        .timeline-item:last-child::after {
            display: none;
        }
        
        .recent-table {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }
        
        @media (max-width: 768px) {
            .dashboard-layout {
                flex-direction: column;
            }
            
            .dashboard-sidebar {
                width: 100%;
                position: fixed;
                top: 0;
                left: -100%;
                z-index: 1000;
                transition: left 0.3s ease;
            }
            
            .dashboard-sidebar.show {
                left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="dashboard-sidebar">
            <div class="sidebar-header-modern">
                <div class="navbar-brand-modern">
                    <img src="assets/images/logo.png" alt="<?php echo SITE_NAME; ?>" style="width: 40px; height: 40px; margin-right: 12px; border-radius: 8px;" 
                         onerror="this.style.display='none'">
                    <span><?php echo SITE_NAME; ?></span>
                </div>
            </div>
            
            <nav class="sidebar-nav-modern">
                <!-- Dashboard -->
                <div class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                
                <?php if ($current_user_role === ROLE_STUDENT): ?>
                <!-- Student Menu -->
                <div class="nav-item">
                    <a class="nav-link" href="student/application.php">
                        <i class="fas fa-file-alt"></i>
                        <span>My Application</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link" href="student/documents.php">
                        <i class="fas fa-paperclip"></i>
                        <span>Documents</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link" href="student/status.php">
                        <i class="fas fa-chart-line"></i>
                        <span>Application Status</span>
                    </a>
                </div>
                
                <?php elseif ($current_user_role === ROLE_PROGRAM_ADMIN): ?>
                <!-- Program Admin Menu -->
                <div class="nav-item">
                    <a class="nav-link" href="admin/applications/list.php">
                        <i class="fas fa-list"></i>
                        <span>All Applications</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link" href="admin/applications/pending.php">
                        <i class="fas fa-clock"></i>
                        <span>Pending Review</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link" href="admin/students/list.php">
                        <i class="fas fa-users"></i>
                        <span>Students</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link" href="admin/reports/program.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </div>
                
                <?php elseif ($current_user_role === ROLE_ADMIN): ?>
                <!-- Admin Menu -->
                <div class="nav-item">
                    <a class="nav-link" href="admin/applications/list.php">
                        <i class="fas fa-file-alt"></i>
                        <span>Applications</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link" href="admin/users/students.php">
                        <i class="fas fa-user-graduate"></i>
                        <span>Students</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link" href="admin/users/program-admins.php">
                        <i class="fas fa-user-tie"></i>
                        <span>Program Admins</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link" href="admin/programs/list.php">
                        <i class="fas fa-graduation-cap"></i>
                        <span>Programs</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link" href="admin/reports/overview.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a class="nav-link" href="admin/settings/general.php">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Profile -->
                <div class="nav-item" style="margin-top: auto;">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="dashboard-main">
            <!-- Top Bar -->
            <header class="dashboard-topbar">
                <div class="d-flex-modern align-items-center-modern">
                    <button class="btn-modern btn-secondary-modern d-md-none me-3" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h4 class="mb-0-modern"><?php echo $page_title; ?></h4>
                        <small class="text-muted-modern"><?php echo $page_subtitle; ?></small>
                    </div>
                </div>
                
                <div class="d-flex-modern align-items-center-modern">
                    <!-- Notifications -->
                    <div class="dropdown me-3">
                        <button class="btn-modern btn-secondary-modern" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="badge badge-danger-modern" style="position: absolute; top: -5px; right: -5px; font-size: 0.6rem;">3</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" style="width: 300px;">
                            <h6 class="dropdown-header">Notifications</h6>
                            <a href="#" class="dropdown-item">
                                <div class="d-flex">
                                    <div class="flex-fill">
                                        <div class="font-weight-medium">Application submitted</div>
                                        <div class="text-muted small">2 minutes ago</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="dropdown">
                        <button class="btn-modern btn-secondary-modern" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($user_details['email']); ?>
                            <i class="fas fa-chevron-down ms-2"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user me-2"></i>Profile
                            </a>
                            <a href="change-password.php" class="dropdown-item">
                                <i class="fas fa-key me-2"></i>Change Password
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="auth/logout.php" class="dropdown-item text-danger">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Dashboard Content -->
            <main class="dashboard-content">
                <!-- Welcome Banner -->
                <div class="welcome-banner fade-in">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-2">Welcome back, <?php echo htmlspecialchars(explode('@', $user_details['email'])[0]); ?>!</h2>
                            <p class="mb-0 opacity-75">
                                <?php if ($current_user_role === ROLE_STUDENT): ?>
                                Manage your application for <?php echo htmlspecialchars($user_details['program_name'] ?? 'your selected program'); ?>
                                <?php elseif ($current_user_role === ROLE_PROGRAM_ADMIN): ?>
                                Manage applications and students for your programs
                                <?php else: ?>
                                Administrative dashboard for managing the entire system
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="fs-1">
                                <i class="fas fa-<?php echo $current_user_role === ROLE_STUDENT ? 'user-graduate' : 
                                    ($current_user_role === ROLE_PROGRAM_ADMIN ? 'user-tie' : 'crown'); ?>"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($current_user_role === ROLE_STUDENT): ?>
                <!-- Student Dashboard -->
                
                <!-- Application Progress -->
                <?php if ($dashboard_data['application']): ?>
                <div class="progress-section slide-up">
                    <div class="row">
                        <div class="col-md-8">
                            <h5 class="mb-3">
                                <i class="fas fa-chart-line text-primary-modern me-2"></i>
                                Application Progress
                            </h5>
                            
                            <?php 
                            $progress = $dashboard_data['progress'];
                            $status = $dashboard_data['application']['status'];
                            ?>
                            
                            <div class="mb-4">
                                <div class="d-flex-modern justify-content-between-modern mb-2">
                                    <span class="text-muted-modern">Completion Progress</span>
                                    <span class="fw-bold text-primary-modern"><?php echo $progress['percentage']; ?>%</span>
                                </div>
                                <div class="progress-modern">
                                    <div class="progress-bar-modern" style="width: <?php echo $progress['percentage']; ?>%"></div>
                                </div>
                                <small class="text-muted-modern">
                                    <?php echo $progress['completed_steps']; ?> of <?php echo $progress['total_steps']; ?> steps completed
                                </small>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <strong>Application Number:</strong><br>
                                    <code class="text-primary-modern"><?php echo htmlspecialchars($dashboard_data['application']['application_number']); ?></code>
                                </div>
                                <div class="col-md-4">
                                    <strong>Status:</strong><br>
                                    <span class="badge-modern badge-<?php echo getStatusColor($status); ?>-modern">
                                        <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                    </span>
                                </div>
                                <div class="col-md-4">
                                    <strong>Submitted:</strong><br>
                                    <?php echo $dashboard_data['application']['submitted_at'] 
                                        ? formatDateTime($dashboard_data['application']['submitted_at']) 
                                        : '<span class="text-muted-modern">Not yet submitted</span>'; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <h6 class="mb-3">Application Timeline</h6>
                            <div class="application-timeline">
                                <div class="timeline-item completed">
                                    <strong>Application Created</strong><br>
                                    <small class="text-muted-modern">✓ Complete</small>
                                </div>
                                <div class="timeline-item <?php echo $progress['percentage'] >= 40 ? 'completed' : 'current'; ?>">
                                    <strong>Personal Details</strong><br>
                                    <small class="text-muted-modern"><?php echo $progress['percentage'] >= 40 ? '✓ Complete' : 'In Progress'; ?></small>
                                </div>
                                <div class="timeline-item <?php echo $progress['percentage'] >= 60 ? 'completed' : ''; ?>">
                                    <strong>Documents Upload</strong><br>
                                    <small class="text-muted-modern"><?php echo $progress['percentage'] >= 60 ? '✓ Complete' : 'Pending'; ?></small>
                                </div>
                                <div class="timeline-item <?php echo $progress['percentage'] >= 80 ? 'completed' : ''; ?>">
                                    <strong>Review & Submit</strong><br>
                                    <small class="text-muted-modern"><?php echo $progress['percentage'] >= 80 ? '✓ Complete' : 'Pending'; ?></small>
                                </div>
                                <div class="timeline-item <?php echo $status === STATUS_APPROVED ? 'completed' : ''; ?>">
                                    <strong>Approval</strong><br>
                                    <small class="text-muted-modern"><?php echo $status === STATUS_APPROVED ? '✓ Approved' : 'Under Review'; ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Quick Actions -->
                <div class="mb-4">
                    <h5 class="mb-3">
                        <i class="fas fa-bolt text-primary-modern me-2"></i>
                        Quick Actions
                    </h5>
                    <div class="quick-actions-grid">
                        <a href="student/application.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-edit"></i>
                            </div>
                            <h6>Edit Application</h6>
                            <p class="text-muted-modern mb-0">Update your application details</p>
                        </a>
                        
                        <a href="student/documents.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-upload"></i>
                            </div>
                            <h6>Upload Documents</h6>
                            <p class="text-muted-modern mb-0">Submit required certificates</p>
                        </a>
                        
                        <a href="student/status.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-eye"></i>
                            </div>
                            <h6>View Status</h6>
                            <p class="text-muted-modern mb-0">Track application progress</p>
                        </a>
                        
                        <a href="profile.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <h6>Update Profile</h6>
                            <p class="text-muted-modern mb-0">Manage account settings</p>
                        </a>
                    </div>
                </div>
                
                <?php elseif ($current_user_role === ROLE_PROGRAM_ADMIN): ?>
                <!-- Program Admin Dashboard -->
                
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stats-card-modern">
                        <div class="stats-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <span class="stats-number"><?php echo $dashboard_data['program_stats']['total_applications'] ?? 0; ?></span>
                        <span class="stats-label">Total Applications</span>
                    </div>
                    
                    <div class="stats-card-modern">
                        <div class="stats-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <span class="stats-number"><?php echo $dashboard_data['program_stats']['submitted_applications'] ?? 0; ?></span>
                        <span class="stats-label">Pending Review</span>
                    </div>
                    
                    <div class="stats-card-modern">
                        <div class="stats-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <span class="stats-number"><?php echo $dashboard_data['program_stats']['approved_applications'] ?? 0; ?></span>
                        <span class="stats-label">Approved</span>
                    </div>
                    
                    <div class="stats-card-modern">
                        <div class="stats-icon">
                            <i class="fas fa-times"></i>
                        </div>
                        <span class="stats-number"><?php echo $dashboard_data['program_stats']['rejected_applications'] ?? 0; ?></span>
                        <span class="stats-label">Rejected</span>
                    </div>
                </div>
                
                <?php else: // ROLE_ADMIN ?>
                <!-- Admin Dashboard -->
                
                <!-- Overall Statistics -->
                <div class="stats-grid">
                    <?php if (!empty($dashboard_data['overall_stats'])): ?>
                    <?php 
                    $total_apps = array_sum(array_column($dashboard_data['overall_stats'], 'total_applications'));
                    $total_approved = array_sum(array_column($dashboard_data['overall_stats'], 'approved_applications'));
                    $total_pending = array_sum(array_column($dashboard_data['overall_stats'], 'submitted_applications'));
                    $total_programs = count($dashboard_data['overall_stats']);
                    ?>
                    
                    <div class="stats-card-modern">
                        <div class="stats-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <span class="stats-number"><?php echo $total_programs; ?></span>
                        <span class="stats-label">Active Programs</span>
                    </div>
                    
                    <div class="stats-card-modern">
                        <div class="stats-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <span class="stats-number"><?php echo $total_apps; ?></span>
                        <span class="stats-label">Total Applications</span>
                    </div>
                    
                    <div class="stats-card-modern">
                        <div class="stats-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <span class="stats-number"><?php echo $total_pending; ?></span>
                        <span class="stats-label">Pending Review</span>
                    </div>
                    
                    <div class="stats-card-modern">
                        <div class="stats-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <span class="stats-number"><?php echo $total_approved; ?></span>
                        <span class="stats-label">Approved</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php endif; ?>
                
                <!-- Recent Applications Table -->
                <?php if (!empty($dashboard_data['recent_applications']) || !empty($dashboard_data['applications'])): ?>
                <div class="recent-table">
                    <div class="card-header-modern">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>Recent Applications
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Application #</th>
                                    <th>Student Name</th>
                                    <?php if ($current_user_role === ROLE_ADMIN): ?>
                                    <th>Program</th>
                                    <?php endif; ?>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $apps = $dashboard_data['recent_applications'] ?? $dashboard_data['applications'] ?? [];
                                foreach (array_slice($apps, 0, 5) as $app): 
                                ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($app['application_number']); ?></code></td>
                                    <td><?php echo htmlspecialchars($app['student_name']); ?></td>
                                    <?php if ($current_user_role === ROLE_ADMIN): ?>
                                    <td><?php echo htmlspecialchars($app['program_code'] ?? 'N/A'); ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="badge-modern badge-<?php echo getStatusColor($app['status']); ?>-modern">
                                            <?php echo ucwords(str_replace('_', ' ', $app['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($app['date_created']); ?></td>
                                    <td>
                                        <a href="admin/applications/view.php?id=<?php echo $app['id']; ?>" 
                                           class="btn-modern btn-sm-modern btn-primary-modern">
                                            View
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.dashboard-sidebar').classList.toggle('show');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                const sidebar = document.querySelector('.dashboard-sidebar');
                const toggle = document.getElementById('sidebarToggle');
                
                if (sidebar && !sidebar.contains(e.target) && !toggle?.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
        
        // Auto-hide alerts
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 5000);
        
        // Add loading states to buttons
        document.querySelectorAll('.btn-modern').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!this.disabled && this.type === 'submit') {
                    this.disabled = true;
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
                    
                    setTimeout(() => {
                        this.disabled = false;
                        this.innerHTML = originalText;
                    }, 3000);
                }
            });
        });
    </script>
</body>
</html>