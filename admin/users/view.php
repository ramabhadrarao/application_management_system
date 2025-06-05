<?php
/**
 * Users Management - View User Profile
 * 
 * File: admin/users/view.php
 * Purpose: View detailed user profile and activity
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../../config/config.php';
require_once '../../classes/User.php';
require_once '../../classes/Application.php';
require_once '../../classes/Program.php';

// Require admin login
requireLogin();

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$application = new Application($db);
$program = new Program($db);

$current_user_role = getCurrentUserRole();
$current_user_id = getCurrentUserId();

// Get user ID from URL
$user_id = isset($_GET['id']) ? sanitizeInput($_GET['id']) : '';

if (empty($user_id)) {
    header('Location: students.php?error=invalid_user');
    exit;
}

// Get user details
$user_details = $user->getUserById($user_id);

if (!$user_details) {
    header('Location: students.php?error=user_not_found');
    exit;
}

// Check permissions
if ($current_user_role === ROLE_PROGRAM_ADMIN && $user_details['role'] !== ROLE_STUDENT) {
    header('Location: students.php?error=access_denied');
    exit;
}

// Get user applications (if student)
$user_applications = [];
if ($user_details['role'] === ROLE_STUDENT) {
    $query = "SELECT a.*, p.program_name, p.program_code 
              FROM applications a 
              LEFT JOIN programs p ON a.program_id = p.id 
              WHERE a.user_id = :user_id 
              ORDER BY a.date_created DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user_applications = $stmt->fetchAll();
}

// Get managed programs (if program admin)
$managed_programs = [];
if ($user_details['role'] === ROLE_PROGRAM_ADMIN) {
    $managed_programs = $program->getProgramsByAdmin($user_id);
}

// Get login history
$query = "SELECT * FROM system_logs 
          WHERE user_id = :user_id AND action = 'LOGIN' 
          ORDER BY date_created DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$login_history = $stmt->fetchAll();

$page_title = 'User Profile: ' . $user_details['email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?php echo htmlspecialchars($page_title . ' - ' . SITE_NAME); ?></title>
    
    <!-- CSS files -->
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/css/tabler.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    
    <style>
        :root {
            --primary-color: #0054a6;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .user-header-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary-color);
        }
        
        .info-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
            height: 100%;
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 2rem;
            margin-right: 1.5rem;
        }
        
        .role-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .role-admin {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .role-program-admin {
            background: rgba(0, 123, 255, 0.1);
            color: #007bff;
        }
        
        .role-student {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-inactive {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        
        .info-value {
            color: #6c757d;
            text-align: right;
        }
        
        .application-item, .program-item, .login-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s ease;
        }
        
        .application-item:hover, .program-item:hover, .login-item:hover {
            background: #f8f9fa;
        }
        
        .application-item:last-child, .program-item:last-child, .login-item:last-child {
            border-bottom: none;
        }
        
        .application-number, .program-code {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-card.success { border-left-color: var(--success-color); }
        .stat-card.warning { border-left-color: var(--warning-color); }
        .stat-card.danger { border-left-color: var(--danger-color); }
        .stat-card.info { border-left-color: var(--info-color); }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-number.success { color: var(--success-color); }
        .stat-number.warning { color: var(--warning-color); }
        .stat-number.danger { color: var(--danger-color); }
        .stat-number.info { color: var(--info-color); }
    </style>
</head>
<body>
    <div class="page">
        <!-- Header -->
        <div class="page-header">
            <div class="container-xl">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="page-pretitle">User Management</div>
                        <h2 class="page-title">
                            <i class="fas fa-user me-2"></i>
                            User Profile
                        </h2>
                    </div>
                    <div class="col-auto ms-auto">
                        <div class="btn-list">
                            <?php if ($current_user_role === ROLE_ADMIN): ?>
                            <a href="edit.php?id=<?php echo $user_id; ?>" class="btn btn-warning">
                                <i class="fas fa-edit me-2"></i>Edit User
                            </a>
                            <?php endif; ?>
                            <a href="<?php echo $user_details['role'] === 'student' ? 'students.php' : 'admins.php'; ?>" class="btn btn-light">
                                <i class="fas fa-list me-2"></i>Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="page-body">
            <div class="container-xl">
                <!-- User Header -->
                <div class="user-header-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user_details['email'], 0, 2)); ?>
                                </div>
                                <div>
                                    <h3 class="mb-2"><?php echo htmlspecialchars($user_details['email']); ?></h3>
                                    <div class="d-flex gap-2 align-items-center mb-2">
                                        <span class="role-badge role-<?php echo str_replace('_', '-', $user_details['role']); ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $user_details['role'])); ?>
                                        </span>
                                        <span class="status-badge <?php echo $user_details['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $user_details['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        <?php if ($user_details['email_verified']): ?>
                                        <span class="badge bg-success">Email Verified</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning">Email Not Verified</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($user_details['program_name']): ?>
                                    <div class="text-muted">
                                        <i class="fas fa-graduation-cap me-1"></i>
                                        <?php echo htmlspecialchars($user_details['program_name']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="text-muted small">
                                <div><strong>User ID:</strong> <?php echo $user_details['id']; ?></div>
                                <div><strong>Created:</strong> <?php echo formatDateTime($user_details['date_created']); ?></div>
                                <div><strong>Last Login:</strong> <?php echo $user_details['last_login'] ? formatDateTime($user_details['last_login']) : 'Never'; ?></div>
                                <div><strong>Login Attempts:</strong> <?php echo $user_details['login_attempts']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics for Students -->
                <?php if ($user_details['role'] === ROLE_STUDENT): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($user_applications); ?></div>
                        <div class="text-muted">Total Applications</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-number success">
                            <?php echo count(array_filter($user_applications, function($app) { return $app['status'] === 'approved'; })); ?>
                        </div>
                        <div class="text-muted">Approved</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-number warning">
                            <?php echo count(array_filter($user_applications, function($app) { return in_array($app['status'], ['submitted', 'under_review']); })); ?>
                        </div>
                        <div class="text-muted">Under Review</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-number danger">
                            <?php echo count(array_filter($user_applications, function($app) { return $app['status'] === 'rejected'; })); ?>
                        </div>
                        <div class="text-muted">Rejected</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Details and Activity -->
                <div class="row g-4">
                    <!-- User Details -->
                    <div class="col-md-4">
                        <div class="info-card">
                            <h5 class="card-title">
                                <i class="fas fa-info-circle"></i>
                                User Details
                            </h5>
                            
                            <div class="info-row">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?php echo htmlspecialchars($user_details['email']); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Role</span>
                                <span class="info-value"><?php echo ucwords(str_replace('_', ' ', $user_details['role'])); ?></span>
                            </div>
                            
                            <?php if ($user_details['program_name']): ?>
                            <div class="info-row">
                                <span class="info-label">Program</span>
                                <span class="info-value"><?php echo htmlspecialchars($user_details['program_name']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="info-row">
                                <span class="info-label">Status</span>
                                <span class="info-value">
                                    <?php echo $user_details['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Email Verified</span>
                                <span class="info-value">
                                    <?php echo $user_details['email_verified'] ? 'Yes' : 'No'; ?>
                                </span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Created</span>
                                <span class="info-value"><?php echo formatDate($user_details['date_created']); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Last Updated</span>
                                <span class="info-value"><?php echo formatDate($user_details['date_updated']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Applications or Programs -->
                    <div class="col-md-4">
                        <div class="info-card">
                            <?php if ($user_details['role'] === ROLE_STUDENT): ?>
                            <h5 class="card-title">
                                <i class="fas fa-file-alt"></i>
                                Applications
                                <span class="badge bg-primary ms-2"><?php echo count($user_applications); ?></span>
                            </h5>
                            
                            <?php if (empty($user_applications)): ?>
                            <div class="empty-state">
                                <i class="fas fa-file-alt fa-2x mb-2"></i>
                                <p class="mb-0">No applications found</p>
                            </div>
                            <?php else: ?>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($user_applications as $app): ?>
                                <div class="application-item">
                                    <div>
                                        <div class="application-number"><?php echo htmlspecialchars($app['application_number']); ?></div>
                                        <div class="text-muted small">
                                            <?php echo htmlspecialchars($app['program_name']); ?> • 
                                            <?php echo formatDate($app['date_created']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="status-badge status-<?php echo $app['status']; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $app['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php elseif ($user_details['role'] === ROLE_PROGRAM_ADMIN): ?>
                            <h5 class="card-title">
                                <i class="fas fa-graduation-cap"></i>
                                Managed Programs
                                <span class="badge bg-primary ms-2"><?php echo count($managed_programs); ?></span>
                            </h5>
                            
                            <?php if (empty($managed_programs)): ?>
                            <div class="empty-state">
                                <i class="fas fa-graduation-cap fa-2x mb-2"></i>
                                <p class="mb-0">No programs assigned</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($managed_programs as $prog): ?>
                            <div class="program-item">
                                <div>
                                    <div class="program-code"><?php echo htmlspecialchars($prog['program_code']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($prog['program_name']); ?></div>
                                </div>
                                <div>
                                    <span class="badge bg-info"><?php echo $prog['program_type']; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <?php else: ?>
                            <h5 class="card-title">
                                <i class="fas fa-shield-alt"></i>
                                System Administrator
                            </h5>
                            <div class="empty-state">
                                <i class="fas fa-shield-alt fa-2x mb-2"></i>
                                <p class="mb-0">Full system access</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Login History -->
                    <div class="col-md-4">
                        <div class="info-card">
                            <h5 class="card-title">
                                <i class="fas fa-history"></i>
                                Recent Login Activity
                                <span class="badge bg-secondary ms-2"><?php echo count($login_history); ?></span>
                            </h5>
                            
                            <?php if (empty($login_history)): ?>
                            <div class="empty-state">
                                <i class="fas fa-history fa-2x mb-2"></i>
                                <p class="mb-0">No login history</p>
                            </div>
                            <?php else: ?>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($login_history as $login): ?>
                                <div class="login-item">
                                    <div>
                                        <div class="fw-bold"><?php echo formatDateTime($login['date_created']); ?></div>
                                        <?php if (!empty($login['ip_address'])): ?>
                                        <div class="text-muted small">IP: <?php echo htmlspecialchars($login['ip_address']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <i class="fas fa-sign-in-alt text-success"></i>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
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
        // Print functionality
        function printProfile() {
            window.print();
        }
        
        // Export functionality (basic)
        function exportProfile() {
            // Simple implementation - could be enhanced with PDF generation
            printProfile();
        }
    </script>
</body>
</html>