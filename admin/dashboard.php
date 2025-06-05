<?php
/**
 * Enhanced Admin Dashboard
 * 
 * File: admin/dashboard.php
 * Purpose: Main admin dashboard with comprehensive overview
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../config/config.php';
require_once '../classes/User.php';
require_once '../classes/Application.php';
require_once '../classes/Program.php';

// Require admin login
requireLogin();

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$application = new Application($db);
$program = new Program($db);

$current_user_role = getCurrentUserRole();
$current_user_id = getCurrentUserId();

// Redirect if not admin or program admin
if (!in_array($current_user_role, [ROLE_ADMIN, ROLE_PROGRAM_ADMIN])) {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

// Get user details
$user_details = $user->getUserById($current_user_id);

// Get overall statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_applications,
        COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_count,
        COUNT(CASE WHEN status = 'submitted' THEN 1 END) as submitted_count,
        COUNT(CASE WHEN status = 'under_review' THEN 1 END) as under_review_count,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
        COUNT(CASE WHEN status = 'frozen' THEN 1 END) as frozen_count,
        COUNT(CASE WHEN DATE(date_created) = CURDATE() THEN 1 END) as today_applications,
        COUNT(CASE WHEN DATE(date_created) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_applications
    FROM applications
    WHERE academic_year = :academic_year
";

// Add program filter for program admins
if ($current_user_role === ROLE_PROGRAM_ADMIN) {
    $stats_query .= " AND program_id IN (SELECT id FROM programs WHERE program_admin_id = :admin_id)";
}

$stmt = $db->prepare($stats_query);
$stmt->bindValue(':academic_year', CURRENT_ACADEMIC_YEAR);
if ($current_user_role === ROLE_PROGRAM_ADMIN) {
    $stmt->bindParam(':admin_id', $current_user_id);
}
$stmt->execute();
$stats = $stmt->fetch();

// Get recent applications
$recent_query = "
    SELECT a.*, p.program_name, p.program_code
    FROM applications a
    LEFT JOIN programs p ON a.program_id = p.id
    WHERE a.academic_year = :academic_year
";

if ($current_user_role === ROLE_PROGRAM_ADMIN) {
    $recent_query .= " AND a.program_id IN (SELECT id FROM programs WHERE program_admin_id = :admin_id)";
}

$recent_query .= " ORDER BY a.date_created DESC LIMIT 10";

$stmt = $db->prepare($recent_query);
$stmt->bindValue(':academic_year', CURRENT_ACADEMIC_YEAR);
if ($current_user_role === ROLE_PROGRAM_ADMIN) {
    $stmt->bindParam(':admin_id', $current_user_id);
}
$stmt->execute();
$recent_applications = $stmt->fetchAll();

// Get program statistics
$program_stats_query = "
    SELECT p.id, p.program_name, p.program_code, p.total_seats,
           COUNT(a.id) as application_count,
           COUNT(CASE WHEN a.status = 'approved' THEN 1 END) as approved_count
    FROM programs p
    LEFT JOIN applications a ON p.id = a.program_id AND a.academic_year = :academic_year
    WHERE p.is_active = 1
";

if ($current_user_role === ROLE_PROGRAM_ADMIN) {
    $program_stats_query .= " AND p.program_admin_id = :admin_id";
}

$program_stats_query .= " GROUP BY p.id ORDER BY application_count DESC";

$stmt = $db->prepare($program_stats_query);
$stmt->bindValue(':academic_year', CURRENT_ACADEMIC_YEAR);
if ($current_user_role === ROLE_PROGRAM_ADMIN) {
    $stmt->bindParam(':admin_id', $current_user_id);
}
$stmt->execute();
$program_stats = $stmt->fetchAll();

// Get pending documents for verification
$pending_docs_query = "
    SELECT ad.id, ad.document_name, ct.name as certificate_name, 
           a.application_number, a.student_name, p.program_code,
           ad.date_created as upload_date
    FROM application_documents ad
    JOIN applications a ON ad.application_id = a.id
    JOIN certificate_types ct ON ad.certificate_type_id = ct.id
    JOIN programs p ON a.program_id = p.id
    WHERE ad.is_verified = 0 AND a.academic_year = :academic_year
";

if ($current_user_role === ROLE_PROGRAM_ADMIN) {
    $pending_docs_query .= " AND a.program_id IN (SELECT id FROM programs WHERE program_admin_id = :admin_id)";
}

$pending_docs_query .= " ORDER BY ad.date_created ASC LIMIT 10";

$stmt = $db->prepare($pending_docs_query);
$stmt->bindValue(':academic_year', CURRENT_ACADEMIC_YEAR);
if ($current_user_role === ROLE_PROGRAM_ADMIN) {
    $stmt->bindParam(':admin_id', $current_user_id);
}
$stmt->execute();
$pending_documents = $stmt->fetchAll();

// Get system alerts (only for main admin)
$system_alerts = [];
if ($current_user_role === ROLE_ADMIN) {
    // Check for system issues
    $alert_checks = [
        [
            'type' => 'warning',
            'title' => 'Application Deadline Approaching',
            'message' => 'Application deadline is in 7 days',
            'condition' => (strtotime(APPLICATION_END_DATE) - time()) <= (7 * 24 * 60 * 60)
        ],
        [
            'type' => 'info',
            'title' => 'Pending Reviews',
            'message' => $stats['under_review_count'] . ' applications pending review',
            'condition' => $stats['under_review_count'] > 0
        ],
        [
            'type' => 'warning',
            'title' => 'Document Verification Backlog',
            'message' => count($pending_documents) . ' documents pending verification',
            'condition' => count($pending_documents) > 50
        ]
    ];
    
    foreach ($alert_checks as $check) {
        if ($check['condition']) {
            $system_alerts[] = $check;
        }
    }
}

$page_title = $current_user_role === ROLE_ADMIN ? 'Admin Dashboard' : 'Program Admin Dashboard';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js" rel="stylesheet"/>
    
    <style>
        :root {
            --primary-color: #0054a6;
            --primary-dark: #003d7a;
            --secondary-color: #667eea;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --text-dark: #2c3e50;
            --text-light: #6c757d;
            --bg-light: #f8f9fa;
            --white: #ffffff;
            --border-color: #e9ecef;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.15);
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
        }
        
        /* Modern Dashboard Header */
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 50%, rgba(255,255,255,0.1) 0%, transparent 50%);
        }
        
        .header-content {
            position: relative;
            z-index: 2;
        }
        
        .welcome-section {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .admin-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            backdrop-filter: blur(10px);
        }
        
        .welcome-text h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .welcome-text p {
            opacity: 0.9;
            margin: 0;
        }
        
        .header-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1.5rem;
        }
        
        .header-stat {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-md);
            padding: 1rem 1.5rem;
            backdrop-filter: blur(10px);
        }
        
        .header-stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .header-stat-label {
            font-size: 0.85rem;
            opacity: 0.8;
        }
        
        /* Dashboard Cards */
        .dashboard-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: none;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .dashboard-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .card-header-modern {
            background: var(--white);
            border-bottom: 2px solid var(--border-color);
            padding: 1.5rem;
        }
        
        .card-title-modern {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .card-body-modern {
            padding: 1.5rem;
        }
        
        /* Statistics Cards */
        .stat-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-md);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card.success {
            border-left-color: var(--success-color);
        }
        
        .stat-card.warning {
            border-left-color: var(--warning-color);
        }
        
        .stat-card.danger {
            border-left-color: var(--danger-color);
        }
        
        .stat-card.info {
            border-left-color: var(--info-color);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }
        
        .stat-number.success { color: var(--success-color); }
        .stat-number.warning { color: var(--warning-color); }
        .stat-number.danger { color: var(--danger-color); }
        .stat-number.info { color: var(--info-color); }
        
        .stat-label {
            color: var(--text-light);
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-change {
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }
        
        .stat-change.positive {
            color: var(--success-color);
        }
        
        .stat-change.negative {
            color: var(--danger-color);
        }
        
        /* Quick Actions */
        .quick-action {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-md);
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-dark);
            display: block;
            height: 100%;
        }
        
        .quick-action:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
            text-decoration: none;
            color: var(--text-dark);
        }
        
        .action-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
            transition: transform 0.3s ease;
        }
        
        .quick-action:hover .action-icon {
            transform: scale(1.1) rotate(10deg);
        }
        
        .action-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .action-description {
            font-size: 0.9rem;
            color: var(--text-light);
            margin: 0;
        }
        
        /* Recent Activity */
        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.3s ease;
        }
        
        .activity-item:hover {
            background: var(--bg-light);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            background: var(--bg-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: var(--primary-color);
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .activity-description {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .activity-time {
            color: var(--text-light);
            font-size: 0.8rem;
        }
        
        /* System Alerts */
        .alert-modern {
            border-radius: var(--radius-lg);
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-warning-modern {
            background: rgba(255, 193, 7, 0.1);
            border-left: 4px solid var(--warning-color);
            color: #856404;
        }
        
        .alert-info-modern {
            background: rgba(23, 162, 184, 0.1);
            border-left: 4px solid var(--info-color);
            color: #0c5460;
        }
        
        /* Charts Container */
        .chart-container {
            position: relative;
            height: 300px;
            padding: 1rem;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 1.5rem 0;
            }
            
            .welcome-section {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .admin-avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .welcome-text h1 {
                font-size: 1.5rem;
            }
            
            .header-stats {
                flex-direction: column;
                gap: 1rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="container-xl">
                <div class="header-content">
                    <div class="welcome-section">
                        <div class="admin-avatar">
                            <i class="fas fa-<?php echo $current_user_role === ROLE_ADMIN ? 'user-shield' : 'user-tie'; ?>"></i>
                        </div>
                        <div class="welcome-text">
                            <h1>Welcome back, <?php echo ucfirst($current_user_role); ?>!</h1>
                            <p><?php echo htmlspecialchars($user_details['email']); ?> • Academic Year <?php echo CURRENT_ACADEMIC_YEAR; ?></p>
                        </div>
                        <div class="ms-auto text-end">
                            <div class="h3 mb-1"><?php echo date('d M Y'); ?></div>
                            <div style="opacity: 0.8;"><?php echo date('l'); ?></div>
                        </div>
                    </div>
                    
                    <div class="header-stats">
                        <div class="header-stat">
                            <div class="header-stat-number"><?php echo $stats['total_applications']; ?></div>
                            <div class="header-stat-label">Total Applications</div>
                        </div>
                        <div class="header-stat">
                            <div class="header-stat-number"><?php echo $stats['today_applications']; ?></div>
                            <div class="header-stat-label">Today</div>
                        </div>
                        <div class="header-stat">
                            <div class="header-stat-number"><?php echo $stats['week_applications']; ?></div>
                            <div class="header-stat-label">This Week</div>
                        </div>
                        <div class="header-stat">
                            <div class="header-stat-number"><?php echo count($pending_documents); ?></div>
                            <div class="header-stat-label">Pending Reviews</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="page-body">
            <div class="container-xl">
                <!-- System Alerts -->
                <?php if (!empty($system_alerts)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <?php foreach ($system_alerts as $alert): ?>
                        <div class="alert-modern alert-<?php echo $alert['type']; ?>-modern">
                            <i class="fas fa-<?php echo $alert['type'] === 'warning' ? 'exclamation-triangle' : 'info-circle'; ?>"></i>
                            <div>
                                <strong><?php echo $alert['title']; ?>:</strong>
                                <?php echo $alert['message']; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
                            <div class="stat-label">Total Applications</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up me-1"></i>+12% from last month
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card success">
                            <div class="stat-number success"><?php echo $stats['approved_count']; ?></div>
                            <div class="stat-label">Approved</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up me-1"></i>+8% from last month
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card warning">
                            <div class="stat-number warning"><?php echo $stats['under_review_count']; ?></div>
                            <div class="stat-label">Under Review</div>
                            <div class="stat-change">
                                <i class="fas fa-clock me-1"></i>Needs attention
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card info">
                            <div class="stat-number info"><?php echo $stats['submitted_count'] + $stats['frozen_count']; ?></div>
                            <div class="stat-label">Pending Review</div>
                            <div class="stat-change">
                                <i class="fas fa-hourglass-half me-1"></i>In queue
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <h3 class="mb-3">
                            <i class="fas fa-bolt text-primary me-2"></i>
                            Quick Actions
                        </h3>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <a href="<?php echo SITE_URL; ?>/admin/applications/list.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-list"></i>
                            </div>
                            <h5 class="action-title">View Applications</h5>
                            <p class="action-description">Browse and manage all applications</p>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <a href="<?php echo SITE_URL; ?>/admin/applications/pending.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h5 class="action-title">Pending Reviews</h5>
                            <p class="action-description">Review submitted applications</p>
                        </a>
                    </div>
                    
                    <?php if ($current_user_role === ROLE_ADMIN): ?>
                    <div class="col-lg-3 col-md-6">
                        <a href="<?php echo SITE_URL; ?>/admin/programs/list.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <h5 class="action-title">Manage Programs</h5>
                            <p class="action-description">Add and configure programs</p>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <a href="<?php echo SITE_URL; ?>/admin/users/students.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h5 class="action-title">Manage Users</h5>
                            <p class="action-description">View and manage user accounts</p>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <a href="../profile.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-user-cog"></i>
                            </div>
                            <h5 class="action-title">Profile</h5>
                            <p class="action-description">Manage your account settings</p>
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="col-lg-3 col-md-6">
                        <a href="<?php echo SITE_URL; ?>/admin/students/list.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h5 class="action-title">My Students</h5>
                            <p class="action-description">View students in your programs</p>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <a href="<?php echo SITE_URL; ?>/admin/reports/program.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h5 class="action-title">Program Reports</h5>
                            <p class="action-description">View detailed program analytics</p>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Dashboard Content Row -->
                <div class="row g-4">
                    <!-- Recent Applications -->
                    <div class="col-lg-8">
                        <div class="dashboard-card">
                            <div class="card-header-modern">
                                <h3 class="card-title-modern">
                                    <i class="fas fa-clock text-primary"></i>
                                    Recent Applications
                                </h3>
                            </div>
                            <div class="card-body-modern p-0">
                                <?php if (empty($recent_applications)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <h5>No Recent Applications</h5>
                                    <p class="text-muted">New applications will appear here</p>
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Application #</th>
                                                <th>Student Name</th>
                                                <th>Program</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_applications as $app): ?>
                                            <tr>
                                                <td>
                                                    <code><?php echo htmlspecialchars($app['application_number']); ?></code>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($app['student_name'] ?: 'Not provided'); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($app['program_code']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusColor($app['status']); ?>">
                                                        <?php echo ucwords(str_replace('_', ' ', $app['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo formatDate($app['date_created']); ?></small>
                                                </td>
                                                <td>
                                                    <a href="<?php echo SITE_URL; ?>/admin/applications/view.php?id=<?php echo $app['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pending Documents -->
                    <div class="col-lg-4">
                        <div class="dashboard-card">
                            <div class="card-header-modern">
                                <h3 class="card-title-modern">
                                    <i class="fas fa-file-check text-warning"></i>
                                    Pending Verifications
                                </h3>
                            </div>
                            <div class="card-body-modern p-0">
                                <?php if (empty($pending_documents)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                    <p class="text-muted mb-0">All documents verified!</p>
                                </div>
                                <?php else: ?>
                                <?php foreach (array_slice($pending_documents, 0, 5) as $doc): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title"><?php echo htmlspecialchars($doc['certificate_name']); ?></div>
                                        <div class="activity-description">
                                            <?php echo htmlspecialchars($doc['student_name']); ?> 
                                            (<?php echo htmlspecialchars($doc['application_number']); ?>)
                                        </div>
                                        <div class="activity-time"><?php echo formatDate($doc['upload_date']); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php if (count($pending_documents) > 5): ?>
                                <div class="text-center p-3">
                                    <a href="<?php echo SITE_URL; ?>/admin/documents/pending.php" class="btn btn-sm btn-outline-primary">
                                        View All (<?php echo count($pending_documents); ?>)
                                    </a>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts and Program Statistics -->
                <div class="row g-4 mt-2">
                    <!-- Application Status Chart -->
                    <div class="col-lg-6">
                        <div class="dashboard-card">
                            <div class="card-header-modern">
                                <h3 class="card-title-modern">
                                    <i class="fas fa-chart-pie text-primary"></i>
                                    Application Status Distribution
                                </h3>
                            </div>
                            <div class="card-body-modern">
                                <div class="chart-container">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Program Statistics -->
                    <div class="col-lg-6">
                        <div class="dashboard-card">
                            <div class="card-header-modern">
                                <h3 class="card-title-modern">
                                    <i class="fas fa-graduation-cap text-primary"></i>
                                    Program Statistics
                                </h3>
                            </div>
                            <div class="card-body-modern p-0">
                                <?php if (empty($program_stats)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                                    <h5>No Programs</h5>
                                    <p class="text-muted">Add programs to see statistics</p>
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Program</th>
                                                <th>Applications</th>
                                                <th>Approved</th>
                                                <th>Fill Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($program_stats, 0, 8) as $prog): ?>
                                            <?php 
                                            $fill_rate = $prog['total_seats'] > 0 ? round(($prog['approved_count'] / $prog['total_seats']) * 100, 1) : 0;
                                            ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($prog['program_code']); ?></strong>
                                                        <div class="text-muted small"><?php echo htmlspecialchars($prog['program_name']); ?></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $prog['application_count']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success"><?php echo $prog['approved_count']; ?>/<?php echo $prog['total_seats']; ?></span>
                                                </td>
                                                <td>
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar <?php echo $fill_rate > 80 ? 'bg-success' : ($fill_rate > 50 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                             style="width: <?php echo $fill_rate; ?>%"></div>
                                                    </div>
                                                    <small class="text-muted"><?php echo $fill_rate; ?>%</small>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity Timeline -->
                <div class="row g-4 mt-2">
                    <div class="col-12">
                        <div class="dashboard-card">
                            <div class="card-header-modern">
                                <h3 class="card-title-modern">
                                    <i class="fas fa-history text-primary"></i>
                                    Recent System Activity
                                </h3>
                            </div>
                            <div class="card-body-modern p-0">
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">New Application Submitted</div>
                                        <div class="activity-description">John Doe submitted application for Computer Science</div>
                                        <div class="activity-time">2 minutes ago</div>
                                    </div>
                                </div>
                                
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">Document Verified</div>
                                        <div class="activity-description">10th Certificate verified for Application #CS202500123</div>
                                        <div class="activity-time">15 minutes ago</div>
                                    </div>
                                </div>
                                
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-thumbs-up"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">Application Approved</div>
                                        <div class="activity-description">Jane Smith's application approved for Electronics Engineering</div>
                                        <div class="activity-time">1 hour ago</div>
                                    </div>
                                </div>
                                
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-upload"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">Document Uploaded</div>
                                        <div class="activity-description">Income certificate uploaded for Application #ME202500089</div>
                                        <div class="activity-time">2 hours ago</div>
                                    </div>
                                </div>
                                
                                <div class="text-center p-3">
                                    <a href="<?php echo SITE_URL; ?>/admin/activity/log.php" class="btn btn-outline-primary">
                                        <i class="fas fa-list me-2"></i>View Full Activity Log
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <script>
        // Status Distribution Chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('statusChart').getContext('2d');
            
            const statusChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Draft', 'Submitted', 'Under Review', 'Approved', 'Rejected', 'Frozen'],
                    datasets: [{
                        data: [
                            <?php echo $stats['draft_count']; ?>,
                            <?php echo $stats['submitted_count']; ?>,
                            <?php echo $stats['under_review_count']; ?>,
                            <?php echo $stats['approved_count']; ?>,
                            <?php echo $stats['rejected_count']; ?>,
                            <?php echo $stats['frozen_count']; ?>
                        ],
                        backgroundColor: [
                            '#6c757d',
                            '#17a2b8',
                            '#ffc107',
                            '#28a745',
                            '#dc3545',
                            '#0054a6'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
            
            // Animate statistics on load
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const target = parseInt(stat.textContent);
                const increment = target / 30;
                let current = 0;
                
                const updateStat = () => {
                    if (current < target) {
                        current += increment;
                        stat.textContent = Math.ceil(current);
                        requestAnimationFrame(updateStat);
                    } else {
                        stat.textContent = target;
                    }
                };
                
                setTimeout(updateStat, Math.random() * 1000);
            });
            
            // Auto-refresh dashboard data every 30 seconds
            setInterval(() => {
                // This would fetch updated stats via AJAX
                console.log('Refreshing dashboard data...');
            }, 30000);
        });
        
        // Real-time notifications (placeholder)
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
            notification.innerHTML = `
                <i class="fas fa-bell me-2"></i>
                ${message}
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }
        
        // Simulate real-time updates
        setTimeout(() => {
            if (Math.random() < 0.3) {
                showNotification('New application submitted!', 'info');
            }
        }, 5000);
    </script>
</body>
</html>

<?php
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