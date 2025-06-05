<?php
/**
 * User Profile Management
 * 
 * File: profile.php
 * Purpose: User profile view and basic editing
 * Author: Student Application Management System
 * Created: 2025
 */

require_once 'config/config.php';
require_once 'classes/User.php';
require_once 'classes/Program.php';

// Require authentication
requireLogin();

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$program = new Program($db);

$current_user_id = getCurrentUserId();
$current_user_role = getCurrentUserRole();

$errors = [];
$success_message = '';

// Get user details
$user_details = $user->getUserById($current_user_id);

if (!$user_details) {
    header('Location: ' . SITE_URL . '/auth/logout.php');
    exit;
}

// Get program details if user has a program
$user_program = null;
if ($user_details['program_id']) {
    $user_program = $program->getById($user_details['program_id']);
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        
        $new_email = sanitizeInput($_POST['email']);
        $new_program_id = $current_user_role === ROLE_STUDENT ? sanitizeInput($_POST['program_id']) : $user_details['program_id'];
        
        // Validation
        if (empty($new_email)) {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif ($new_email !== $user_details['email'] && $user->emailExists($new_email)) {
            $errors[] = 'This email address is already registered.';
        }
        
        if ($current_user_role === ROLE_STUDENT && empty($new_program_id)) {
            $errors[] = 'Please select a program.';
        }
        
        if (empty($errors)) {
            $update_data = [
                'email' => $new_email,
                'program_id' => $new_program_id
            ];
            
            $user->id = $current_user_id;
            if ($user->updateProfile($update_data)) {
                $_SESSION['user_email'] = $new_email;
                $_SESSION['program_id'] = $new_program_id;
                $success_message = 'Profile updated successfully!';
                
                // Refresh user data
                $user_details = $user->getUserById($current_user_id);
                if ($user_details['program_id']) {
                    $user_program = $program->getById($user_details['program_id']);
                }
            } else {
                $errors[] = 'Failed to update profile. Please try again.';
            }
        }
    } else {
        $errors[] = 'Invalid request. Please try again.';
    }
}

// Get all programs for students
$programs = [];
if ($current_user_role === ROLE_STUDENT) {
    $programs = $program->getAllActivePrograms();
}

$page_title = 'My Profile';
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
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --text-dark: #2c3e50;
            --text-light: #6c757d;
            --bg-light: #f8f9fa;
            --white: #ffffff;
            --border-color: #e9ecef;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
            --radius-lg: 0.75rem;
        }
        
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            color: white;
            padding: 3rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
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
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 1.5rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
        }
        
        .profile-name {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        
        .profile-role {
            font-size: 1.1rem;
            opacity: 0.9;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .profile-stats {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            display: block;
        }
        
        .stat-label {
            font-size: 0.85rem;
            opacity: 0.8;
        }
        
        .card-modern {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: none;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-icon {
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
        
        .info-content {
            flex: 1;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .btn-modern {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
        }
        
        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
        }
        
        .btn-outline-modern {
            background: transparent;
            border: 2px solid var(--border-color);
            color: var(--text-dark);
        }
        
        .btn-outline-modern:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: rgba(0, 84, 166, 0.05);
            text-decoration: none;
        }
        
        .btn-danger-modern {
            background: linear-gradient(135deg, var(--danger-color), #e74c3c);
            color: white;
        }
        
        .alert-modern {
            border-radius: var(--radius-lg);
            border: none;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .form-floating-modern .form-control {
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            padding: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-floating-modern .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 84, 166, 0.1);
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .action-card {
            background: var(--white);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-dark);
        }
        
        .action-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            text-decoration: none;
            color: var(--text-dark);
        }
        
        .action-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }
        
        .action-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .action-description {
            font-size: 0.85rem;
            color: var(--text-light);
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="container">
                <div class="header-content">
                    <!-- Breadcrumb -->
                    <nav class="mb-4" style="background: rgba(255, 255, 255, 0.1); border-radius: var(--radius-lg); padding: 0.75rem 1rem;">
                        <a href="<?php echo SITE_URL; ?>/dashboard.php" style="color: rgba(255, 255, 255, 0.8); text-decoration: none;">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                        <span class="mx-2">/</span>
                        <span>Profile</span>
                    </nav>
                    
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    
                    <h1 class="profile-name"><?php echo htmlspecialchars(explode('@', $user_details['email'])[0]); ?></h1>
                    <p class="profile-role">
                        <?php 
                        switch($current_user_role) {
                            case ROLE_ADMIN: echo 'System Administrator'; break;
                            case ROLE_PROGRAM_ADMIN: echo 'Program Administrator'; break;
                            case ROLE_STUDENT: echo 'Student'; break;
                            default: echo 'User';
                        }
                        ?>
                    </p>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo formatDate($user_details['date_created']); ?></span>
                            <span class="stat-label">Member Since</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $user_details['last_login'] ? formatDate($user_details['last_login']) : 'Never'; ?></span>
                            <span class="stat-label">Last Login</span>
                        </div>
                        <?php if ($current_user_role === ROLE_STUDENT && $user_program): ?>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo htmlspecialchars($user_program['program_code']); ?></span>
                            <span class="stat-label">Program</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="container py-4">
            <!-- Alerts -->
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-modern">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Please correct the following errors:</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-modern">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Profile Information -->
                <div class="col-lg-8">
                    <div class="card-modern">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">
                                <i class="fas fa-user-edit me-2"></i>
                                Profile Information
                            </h4>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating-modern">
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?php echo htmlspecialchars($user_details['email']); ?>" required>
                                            <label>Email Address</label>
                                        </div>
                                    </div>
                                    
                                    <?php if ($current_user_role === ROLE_STUDENT): ?>
                                    <div class="col-md-6">
                                        <select class="form-control" name="program_id" required style="padding: 1rem; border-radius: var(--radius-lg); border: 2px solid var(--border-color);">
                                            <option value="">Select Program</option>
                                            <?php foreach ($programs as $prog): ?>
                                            <option value="<?php echo $prog['id']; ?>" 
                                                    <?php echo ($user_details['program_id'] == $prog['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($prog['program_name'] . ' (' . $prog['program_code'] . ')'); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label class="form-label mt-2">Program</label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-end mt-4">
                                    <button type="submit" name="update_profile" class="btn btn-primary-modern">
                                        <i class="fas fa-save me-2"></i>
                                        Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Account Details -->
                    <div class="card-modern">
                        <div class="card-header">
                            <h4 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Account Details
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Email Address</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user_details['email']); ?></div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-user-tag"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Account Type</div>
                                    <div class="info-value">
                                        <?php 
                                        switch($current_user_role) {
                                            case ROLE_ADMIN: echo 'System Administrator'; break;
                                            case ROLE_PROGRAM_ADMIN: echo 'Program Administrator'; break;
                                            case ROLE_STUDENT: echo 'Student Account'; break;
                                            default: echo 'User Account';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($user_program): ?>
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Program</div>
                                    <div class="info-value">
                                        <?php echo htmlspecialchars($user_program['program_name']); ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($user_program['program_code'] . ' - ' . $user_program['department']); ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-calendar-plus"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Account Created</div>
                                    <div class="info-value"><?php echo formatDateTime($user_details['date_created']); ?></div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Last Login</div>
                                    <div class="info-value">
                                        <?php echo $user_details['last_login'] ? formatDateTime($user_details['last_login']) : 'First time login'; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Account Status</div>
                                    <div class="info-value">
                                        <?php if ($user_details['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($user_details['email_verified']): ?>
                                        <span class="badge bg-info ms-2">Verified</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning ms-2">Unverified</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="col-lg-4">
                    <div class="card-modern">
                        <div class="card-header">
                            <h4 class="mb-0">
                                <i class="fas fa-bolt me-2"></i>
                                Quick Actions
                            </h4>
                        </div>
                        <div class="card-body p-3">
                            <div class="d-grid gap-2">
                                <a href="change-password.php" class="btn btn-outline-modern">
                                    <i class="fas fa-key me-2"></i>
                                    Change Password
                                </a>
                                
                                <?php if ($current_user_role === ROLE_STUDENT): ?>
                                <a href="student/application.php" class="btn btn-outline-modern">
                                    <i class="fas fa-file-alt me-2"></i>
                                    My Application
                                </a>
                                <a href="student/documents.php" class="btn btn-outline-modern">
                                    <i class="fas fa-upload me-2"></i>
                                    Upload Documents
                                </a>
                                <a href="student/status.php" class="btn btn-outline-modern">
                                    <i class="fas fa-chart-line me-2"></i>
                                    Application Status
                                </a>
                                <?php endif; ?>
                                
                                <a href="help.php" class="btn btn-outline-modern">
                                    <i class="fas fa-question-circle me-2"></i>
                                    Help & Support
                                </a>
                                
                                <hr>
                                
                                <a href="auth/logout.php" class="btn btn-danger-modern">
                                    <i class="fas fa-sign-out-alt me-2"></i>
                                    Logout
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account Security -->
                    <div class="card-modern">
                        <div class="card-header">
                            <h4 class="mb-0">
                                <i class="fas fa-shield-alt me-2"></i>
                                Account Security
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-key"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Password</div>
                                    <div class="info-value">Last changed: Unknown</div>
                                </div>
                                <div>
                                    <a href="change-password.php" class="btn btn-sm btn-outline-primary">Change</a>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-mobile-alt"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Two-Factor Authentication</div>
                                    <div class="info-value">
                                        <span class="badge bg-warning">Not Enabled</span>
                                    </div>
                                </div>
                                <div>
                                    <button class="btn btn-sm btn-outline-success" disabled>Enable</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions Grid -->
            <?php if ($current_user_role === ROLE_STUDENT): ?>
            <div class="quick-actions">
                <a href="student/application.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="action-title">My Application</div>
                    <div class="action-description">View and edit your application details</div>
                </a>
                
                <a href="student/documents.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-file-upload"></i>
                    </div>
                    <div class="action-title">Upload Documents</div>
                    <div class="action-description">Upload required certificates and documents</div>
                </a>
                
                <a href="student/status.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="action-title">Track Status</div>
                    <div class="action-description">Monitor your application progress</div>
                </a>
                
                <a href="help.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <div class="action-title">Help & FAQ</div>
                    <div class="action-description">Get answers to common questions</div>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script>
        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-modern');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
        
        // Smooth hover effects
        document.querySelectorAll('.action-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(-4px)';
            });
        });
    </script>
</body>
</html>