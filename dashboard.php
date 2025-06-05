<?php
/**
 * Main Dashboard with Role-based Redirection
 * 
 * File: dashboard.php
 * Purpose: Central dashboard that redirects based on user role
 * Author: Student Application Management System
 * Created: 2025
 */

require_once 'config/config.php';
require_once 'classes/User.php';

// Require authentication
requireLogin();

$current_user_role = getCurrentUserRole();

// Redirect based on user role
switch ($current_user_role) {
    case ROLE_ADMIN:
    case ROLE_PROGRAM_ADMIN:
        // Redirect to admin dashboard
        header('Location: ' . SITE_URL . '/admin/dashboard.php');
        exit;
        
    case ROLE_STUDENT:
        // Continue to student dashboard (code below)
        break;
        
    default:
        // Unknown role, logout and redirect to login
        session_destroy();
        header('Location: ' . SITE_URL . '/auth/login.php?error=invalid_role');
        exit;
}

// Student Dashboard Code (from the existing dashboard.php)
require_once 'classes/Application.php';
require_once 'classes/Program.php';

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$application = new Application($db);
$program = new Program($db);

$current_user_id = getCurrentUserId();

$page_title = 'Student Dashboard';

// Get user details
$user_details = $user->getUserById($current_user_id);
$user_program = $program->getById($user_details['program_id']);

// Get student application
$student_application = $application->getByUserId($current_user_id);

// Calculate application progress
function calculateApplicationProgress($app) {
    if (!$app) return ['percentage' => 0, 'completed_steps' => 0, 'total_steps' => 5];
    
    $total_steps = 5;
    $completed_steps = 0;
    
    // Step 1: Basic info completed
    if (!empty($app['student_name']) && !empty($app['father_name']) && 
        !empty($app['mobile_number']) && !empty($app['email'])) {
        $completed_steps++;
    }
    
    // Step 2: Address info completed
    if (!empty($app['present_village']) && !empty($app['permanent_village'])) {
        $completed_steps++;
    }
    
    // Step 3: Additional details completed
    if (!empty($app['caste']) && !empty($app['religion'])) {
        $completed_steps++;
    }
    
    // Step 4: Documents uploaded (placeholder)
    if ($app['status'] !== STATUS_DRAFT) {
        $completed_steps++;
    }
    
    // Step 5: Submitted
    if (in_array($app['status'], [STATUS_SUBMITTED, STATUS_UNDER_REVIEW, STATUS_APPROVED, STATUS_REJECTED, STATUS_FROZEN])) {
        $completed_steps++;
    }
    
    return [
        'percentage' => round(($completed_steps / $total_steps) * 100),
        'completed_steps' => $completed_steps,
        'total_steps' => $total_steps
    ];
}

$progress = calculateApplicationProgress($student_application);

// Get status color
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

// Get next action
function getNextAction($app) {
    if (!$app) {
        return ['text' => 'Start Application', 'url' => 'student/application.php', 'color' => 'primary'];
    }
    
    switch ($app['status']) {
        case STATUS_DRAFT:
            return ['text' => 'Continue Application', 'url' => 'student/application.php', 'color' => 'primary'];
        case STATUS_SUBMITTED:
            return ['text' => 'Upload Documents', 'url' => 'student/documents.php', 'color' => 'info'];
        case STATUS_UNDER_REVIEW:
            return ['text' => 'View Status', 'url' => 'student/status.php', 'color' => 'warning'];
        case STATUS_APPROVED:
            return ['text' => 'Download Admission Letter', 'url' => 'student/admission-letter.php', 'color' => 'success'];
        case STATUS_REJECTED:
            return ['text' => 'View Feedback', 'url' => 'student/status.php', 'color' => 'danger'];
        default:
            return ['text' => 'View Application', 'url' => 'student/application.php', 'color' => 'secondary'];
    }
}

$next_action = getNextAction($student_application);
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
    
    <style>
        :root {
            --primary-color: #0054a6;
            --primary-dark: #003d7a;
            --secondary-color: #667eea;
            --accent-color: #764ba2;
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
        
        .page-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Modern Header */
        .header-modern {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .header-modern::before {
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
        
        .welcome-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .welcome-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-right: 1.5rem;
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
        
        /* Progress Card */
        .progress-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .progress-card::before {
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
        
        .progress-content {
            position: relative;
            z-index: 2;
        }
        
        .progress-percentage {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .progress-text {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 1.5rem;
        }
        
        .progress-bar-modern {
            height: 8px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .progress-fill {
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 4px;
            transition: width 0.8s ease;
        }
        
        /* Quick Actions */
        .quick-action {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
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
        
        /* Status Timeline */
        .status-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.75rem;
            top: 0.5rem;
            width: 12px;
            height: 12px;
            background: var(--border-color);
            border-radius: 50%;
            border: 3px solid var(--white);
            box-shadow: 0 0 0 3px var(--border-color);
        }
        
        .timeline-item.completed::before {
            background: var(--success-color);
            box-shadow: 0 0 0 3px var(--success-color);
        }
        
        .timeline-item.current::before {
            background: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 3px var(--primary-color); }
            50% { box-shadow: 0 0 0 8px rgba(0, 84, 166, 0.3); }
            100% { box-shadow: 0 0 0 3px var(--primary-color); }
        }
        
        .timeline-item::after {
            content: '';
            position: absolute;
            left: -1.125rem;
            top: 1.5rem;
            width: 2px;
            height: calc(100% - 1rem);
            background: var(--border-color);
        }
        
        .timeline-item:last-child::after {
            display: none;
        }
        
        .timeline-content h6 {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .timeline-content small {
            color: var(--text-light);
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
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Next Action Button */
        .next-action-btn {
            padding: 1rem 2rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        
        .next-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            text-decoration: none;
        }
        
        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .btn-info-modern {
            background: linear-gradient(135deg, var(--info-color), var(--secondary-color));
            color: white;
        }
        
        .btn-success-modern {
            background: linear-gradient(135deg, var(--success-color), #20c997);
            color: white;
        }
        
        .btn-warning-modern {
            background: linear-gradient(135deg, var(--warning-color), #fd7e14);
            color: var(--text-dark);
        }
        
        .btn-danger-modern {
            background: linear-gradient(135deg, var(--danger-color), #e74c3c);
            color: white;
        }
        
        /* Recent Activity */
        .activity-item {
            display: flex;
            align-items: center;
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
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .activity-time {
            color: var(--text-light);
            font-size: 0.85rem;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .welcome-title {
                font-size: 2rem;
            }
            
            .user-avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
                margin-right: 1rem;
            }
            
            .progress-percentage {
                font-size: 2.5rem;
            }
            
            .quick-actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Modern Header -->
        <div class="header-modern">
            <div class="container-xl">
                <div class="header-content">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="user-avatar">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                        </div>
                        <div class="col">
                            <h1 class="welcome-title">
                                Welcome back, <?php echo htmlspecialchars(explode('@', $user_details['email'])[0]); ?>!
                            </h1>
                            <p class="welcome-subtitle">
                                <?php echo htmlspecialchars($user_program['program_name'] ?? 'Your Academic Journey'); ?> • Academic Year <?php echo CURRENT_ACADEMIC_YEAR; ?>
                            </p>
                        </div>
                        <div class="col-auto">
                            <div class="text-end">
                                <div class="h3 mb-1"><?php echo date('d M Y'); ?></div>
                                <div class="opacity-75"><?php echo date('l'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="page-body">
            <div class="container-xl">
                <div class="row g-4">
                    <!-- Application Progress -->
                    <div class="col-lg-4">
                        <div class="dashboard-card progress-card">
                            <div class="card-body-modern progress-content">
                                <div class="progress-percentage"><?php echo $progress['percentage']; ?>%</div>
                                <div class="progress-text">Application Complete</div>
                                
                                <div class="progress-bar-modern">
                                    <div class="progress-fill" style="width: <?php echo $progress['percentage']; ?>%"></div>
                                </div>
                                
                                <div class="text-white-50">
                                    <?php echo $progress['completed_steps']; ?> of <?php echo $progress['total_steps']; ?> steps completed
                                </div>
                                
                                <?php if ($student_application): ?>
                                <div class="mt-3">
                                    <small class="text-white-75">
                                        Application #: <strong><?php echo htmlspecialchars($student_application['application_number']); ?></strong>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Current Status -->
                    <div class="col-lg-4">
                        <div class="dashboard-card">
                            <div class="card-header-modern">
                                <h3 class="card-title-modern">
                                    <i class="fas fa-clipboard-check text-primary"></i>
                                    Current Status
                                </h3>
                            </div>
                            <div class="card-body-modern text-center">
                                <?php if ($student_application): ?>
                                <div class="mb-3">
                                    <span class="badge bg-<?php echo getStatusColor($student_application['status']); ?> badge-lg" style="padding: 0.75rem 1.5rem; font-size: 1rem;">
                                        <?php echo ucwords(str_replace('_', ' ', $student_application['status'])); ?>
                                    </span>
                                </div>
                                
                                <?php if ($student_application['submitted_at']): ?>
                                <p class="text-muted mb-3">
                                    Submitted on <?php echo formatDate($student_application['submitted_at']); ?>
                                </p>
                                <?php endif; ?>
                                
                                <a href="<?php echo $next_action['url']; ?>" 
                                   class="next-action-btn btn-<?php echo $next_action['color']; ?>-modern">
                                    <i class="fas fa-arrow-right"></i>
                                    <?php echo $next_action['text']; ?>
                                </a>
                                <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-plus-circle fa-3x text-primary mb-3"></i>
                                    <h4>Start Your Application</h4>
                                    <p class="text-muted">Begin your academic journey by creating your application.</p>
                                    <a href="student/application.php" class="next-action-btn btn-primary-modern">
                                        <i class="fas fa-plus"></i>
                                        Create Application
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Program Info -->
                    <div class="col-lg-4">
                        <div class="dashboard-card">
                            <div class="card-header-modern">
                                <h3 class="card-title-modern">
                                    <i class="fas fa-graduation-cap text-primary"></i>
                                    Program Details
                                </h3>
                            </div>
                            <div class="card-body-modern">
                                <h5 class="mb-3"><?php echo htmlspecialchars($user_program['program_name']); ?></h5>
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="stat-card">
                                            <div class="stat-number"><?php echo htmlspecialchars($user_program['duration_years']); ?></div>
                                            <div class="stat-label">Years</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stat-card">
                                            <div class="stat-number"><?php echo htmlspecialchars($user_program['total_seats']); ?></div>
                                            <div class="stat-label">Total Seats</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <strong>Department:</strong> <?php echo htmlspecialchars($user_program['department']); ?><br>
                                        <strong>Program Code:</strong> <?php echo htmlspecialchars($user_program['program_code']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row g-4 mt-2">
                    <div class="col-12">
                        <h3 class="mb-4">
                            <i class="fas fa-bolt text-primary me-2"></i>
                            Quick Actions
                        </h3>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <a href="student/application.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-edit"></i>
                            </div>
                            <h5 class="action-title">My Application</h5>
                            <p class="action-description">View and edit your application details</p>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <a href="student/documents.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-file-upload"></i>
                            </div>
                            <h5 class="action-title">Documents</h5>
                            <p class="action-description">Upload and manage required documents</p>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <a href="student/status.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h5 class="action-title">Track Status</h5>
                            <p class="action-description">Monitor your application progress</p>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <a href="profile.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-user-cog"></i>
                            </div>
                            <h5 class="action-title">Profile</h5>
                            <p class="action-description">Manage your account settings</p>
                        </a>
                    </div>
                </div>
                
                <!-- Application Timeline & Recent Activity -->
                <div class="row g-4 mt-2">
                    <!-- Application Timeline -->
                    <div class="col-lg-6">
                        <div class="dashboard-card">
                            <div class="card-header-modern">
                                <h3 class="card-title-modern">
                                    <i class="fas fa-timeline text-primary"></i>
                                    Application Timeline
                                </h3>
                            </div>
                            <div class="card-body-modern">
                                <div class="status-timeline">
                                    <div class="timeline-item completed">
                                        <div class="timeline-content">
                                            <h6>Account Created</h6>
                                            <small>Registration completed successfully</small>
                                        </div>
                                    </div>
                                    
                                    <div class="timeline-item <?php echo $progress['percentage'] >= 20 ? 'completed' : 'current'; ?>">
                                        <div class="timeline-content">
                                            <h6>Application Started</h6>
                                            <small><?php echo $progress['percentage'] >= 20 ? 'Personal details filled' : 'Fill in your personal information'; ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="timeline-item <?php echo $progress['percentage'] >= 60 ? 'completed' : ($progress['percentage'] >= 20 ? 'current' : ''); ?>">
                                        <div class="timeline-content">
                                            <h6>Documents Upload</h6>
                                            <small><?php echo $progress['percentage'] >= 60 ? 'All documents submitted' : 'Upload required certificates'; ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="timeline-item <?php echo $progress['percentage'] >= 80 ? 'completed' : ($progress['percentage'] >= 60 ? 'current' : ''); ?>">
                                        <div class="timeline-content">
                                            <h6>Application Review</h6>
                                            <small><?php echo $progress['percentage'] >= 80 ? 'Under admin review' : 'Submit for review'; ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="timeline-item <?php echo $student_application && $student_application['status'] === STATUS_APPROVED ? 'completed' : ($progress['percentage'] >= 80 ? 'current' : ''); ?>">
                                        <div class="timeline-content">
                                            <h6>Final Decision</h6>
                                            <small><?php echo $student_application && $student_application['status'] === STATUS_APPROVED ? 'Application approved!' : 'Awaiting final decision'; ?></small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3 text-center">
                                    <a href="student/timeline.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-history me-2"></i>View Detailed Timeline
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="col-lg-6">
                        <div class="dashboard-card">
                            <div class="card-header-modern">
                                <h3 class="card-title-modern">
                                    <i class="fas fa-history text-primary"></i>
                                    Recent Activity
                                </h3>
                            </div>
                            <div class="card-body-modern p-0">
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">Account Created</div>
                                        <div class="activity-time"><?php echo formatDate($user_details['date_created']); ?></div>
                                    </div>
                                </div>
                                
                                <?php if ($student_application): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">Application Started</div>
                                        <div class="activity-time"><?php echo formatDate($student_application['date_created']); ?></div>
                                    </div>
                                </div>
                                
                                <?php if ($student_application['submitted_at']): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-paper-plane"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">Application Submitted</div>
                                        <div class="activity-time"><?php echo formatDate($student_application['submitted_at']); ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                                
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-sign-in-alt"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">Last Login</div>
                                        <div class="activity-time"><?php echo $user_details['last_login'] ? formatDateTime($user_details['last_login']) : 'First time login'; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Important Information -->
                <div class="row g-4 mt-2">
                    <div class="col-12">
                        <div class="dashboard-card">
                            <div class="card-header-modern">
                                <h3 class="card-title-modern">
                                    <i class="fas fa-info-circle text-primary"></i>
                                    Important Information
                                </h3>
                            </div>
                            <div class="card-body-modern">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-calendar-alt text-info me-2"></i>Important Dates</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-2">
                                                <strong>Application Deadline:</strong> 
                                                <span class="text-danger"><?php echo formatDate(APPLICATION_END_DATE); ?></span>
                                            </li>
                                            <li class="mb-2">
                                                <strong>Document Submission:</strong> 
                                                <span class="text-warning"><?php echo formatDate(APPLICATION_END_DATE); ?></span>
                                            </li>
                                            <li class="mb-2">
                                                <strong>Academic Year:</strong> 
                                                <span class="text-info"><?php echo CURRENT_ACADEMIC_YEAR; ?></span>
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-question-circle text-success me-2"></i>Need Help?</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-2">
                                                <a href="help.php" class="text-decoration-none">
                                                    <i class="fas fa-book me-2"></i>Help & FAQ
                                                </a>
                                            </li>
                                            <li class="mb-2">
                                                <a href="contact.php" class="text-decoration-none">
                                                    <i class="fas fa-phone me-2"></i>Contact Support
                                                </a>
                                            </li>
                                            <li class="mb-2">
                                                <a href="mailto:<?php echo ADMIN_EMAIL; ?>" class="text-decoration-none">
                                                    <i class="fas fa-envelope me-2"></i><?php echo ADMIN_EMAIL; ?>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
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
    
    <script>
        // Animate progress bar on load
        document.addEventListener('DOMContentLoaded', function() {
            const progressFill = document.querySelector('.progress-fill');
            if (progressFill) {
                const targetWidth = progressFill.style.width;
                progressFill.style.width = '0%';
                setTimeout(() => {
                    progressFill.style.width = targetWidth;
                }, 500);
            }
            
            // Animate counters
            const counters = document.querySelectorAll('.stat-number');
            counters.forEach(counter => {
                const target = parseInt(counter.textContent);
                const increment = target / 30;
                let current = 0;
                
                const updateCounter = () => {
                    if (current < target) {
                        current += increment;
                        counter.textContent = Math.ceil(current);
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.textContent = target;
                    }
                };
                
                setTimeout(updateCounter, Math.random() * 1000);
            });
            
            // Add click animation to quick actions
            document.querySelectorAll('.quick-action, .next-action-btn').forEach(element => {
                element.addEventListener('click', function(e) {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
        });
        
        // Auto-refresh recent activity (placeholder)
        function refreshActivity() {
            // This would fetch new activity data via AJAX
            console.log('Refreshing activity data...');
        }
        
        // Refresh every 5 minutes
        setInterval(refreshActivity, 300000);
    </script>
</body>
</html>