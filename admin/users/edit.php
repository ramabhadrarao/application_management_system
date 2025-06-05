<?php
/**
 * Users Management - Edit User
 * 
 * File: admin/users/edit.php
 * Purpose: Edit existing user details
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../../config/config.php';
require_once '../../classes/User.php';
require_once '../../classes/Program.php';

// Require admin login
requireLogin();
requirePermission(ROLE_ADMIN);

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
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

// Initialize variables
$errors = [];
$success_message = '';
$form_data = [
    'email' => $user_details['email'],
    'role' => $user_details['role'],
    'program_id' => $user_details['program_id'],
    'is_active' => $user_details['is_active'],
    'email_verified' => $user_details['email_verified']
];

// Get all active programs for dropdown
$programs = $program->getAllActivePrograms();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        
        if (isset($_POST['update_profile'])) {
            // Update profile information
            $form_data = [
                'email' => strtolower(trim($_POST['email'])),
                'role' => $_POST['role'],
                'program_id' => $_POST['program_id'] ?: null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'email_verified' => isset($_POST['email_verified']) ? 1 : 0
            ];
            
            // Validation
            if (empty($form_data['email'])) {
                $errors[] = 'Email address is required.';
            } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            } elseif ($form_data['email'] !== $user_details['email'] && $user->emailExists($form_data['email'])) {
                $errors[] = 'This email address is already registered.';
            }
            
            if (empty($form_data['role'])) {
                $errors[] = 'User role is required.';
            } elseif (!in_array($form_data['role'], [ROLE_ADMIN, ROLE_PROGRAM_ADMIN, ROLE_STUDENT])) {
                $errors[] = 'Invalid user role selected.';
            }
            
            if ($form_data['role'] === ROLE_STUDENT && empty($form_data['program_id'])) {
                $errors[] = 'Program is required for student accounts.';
            }
            
            // Prevent self-deactivation
            if ($user_id === $current_user_id && !$form_data['is_active']) {
                $errors[] = 'You cannot deactivate your own account.';
            }
            
            // If no errors, update the user
            if (empty($errors)) {
                try {
                    $query = "UPDATE users SET email = :email, role = :role, program_id = :program_id, 
                              is_active = :is_active, email_verified = :email_verified, 
                              date_updated = CURRENT_TIMESTAMP WHERE id = :id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':email', $form_data['email']);
                    $stmt->bindParam(':role', $form_data['role']);
                    $stmt->bindParam(':program_id', $form_data['program_id']);
                    $stmt->bindParam(':is_active', $form_data['is_active']);
                    $stmt->bindParam(':email_verified', $form_data['email_verified']);
                    $stmt->bindParam(':id', $user_id);
                    
                    if ($stmt->execute()) {
                        $success_message = 'User profile updated successfully!';
                        
                        // Refresh user details
                        $user_details = $user->getUserById($user_id);
                        $form_data = [
                            'email' => $user_details['email'],
                            'role' => $user_details['role'],
                            'program_id' => $user_details['program_id'],
                            'is_active' => $user_details['is_active'],
                            'email_verified' => $user_details['email_verified']
                        ];
                    } else {
                        $errors[] = 'Failed to update user profile.';
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif (isset($_POST['change_password'])) {
            // Change password
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (empty($new_password)) {
                $errors[] = 'New password is required.';
            } elseif (strlen($new_password) < 8) {
                $errors[] = 'Password must be at least 8 characters long.';
            } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $new_password)) {
                $errors[] = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
            } elseif ($new_password !== $confirm_password) {
                $errors[] = 'Passwords do not match.';
            }
            
            if (empty($errors)) {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $query = "UPDATE users SET password = :password, date_updated = CURRENT_TIMESTAMP WHERE id = :id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':id', $user_id);
                    
                    if ($stmt->execute()) {
                        $success_message = 'Password changed successfully!';
                    } else {
                        $errors[] = 'Failed to change password.';
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        }
    } else {
        $errors[] = 'Invalid request. Please try again.';
    }
}

$page_title = 'Edit User: ' . $user_details['email'];
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
        
        .user-info-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary-color);
        }
        
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 84, 166, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
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
        
        .form-check-custom {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .warning-box {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .password-strength {
            margin-top: 0.5rem;
        }
        
        .strength-meter {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        
        .strength-fill {
            height: 100%;
            border-radius: 2px;
            transition: all 0.3s ease;
            width: 0%;
        }
        
        .strength-weak { background: var(--danger-color); width: 25%; }
        .strength-fair { background: var(--warning-color); width: 50%; }
        .strength-good { background: var(--info-color); width: 75%; }
        .strength-strong { background: var(--success-color); width: 100%; }
        
        .strength-text {
            font-size: 0.8rem;
            color: #6c757d;
        }
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
                            <i class="fas fa-user-edit me-2"></i>
                            Edit User
                        </h2>
                    </div>
                    <div class="col-auto ms-auto">
                        <div class="btn-list">
                            <a href="view.php?id=<?php echo $user_id; ?>" class="btn btn-light">
                                <i class="fas fa-eye me-2"></i>View Profile
                            </a>
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
                <!-- User Info Card -->
                <div class="user-info-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user_details['email'], 0, 2)); ?>
                                </div>
                                <div>
                                    <h4 class="mb-1"><?php echo htmlspecialchars($user_details['email']); ?></h4>
                                    <div class="d-flex gap-2 align-items-center">
                                        <span class="role-badge role-<?php echo str_replace('_', '-', $user_details['role']); ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $user_details['role'])); ?>
                                        </span>
                                        <span class="status-badge <?php echo $user_details['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $user_details['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        <?php if ($user_id === $current_user_id): ?>
                                        <span class="badge bg-info">Your Account</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="text-muted small">
                                <div><strong>User ID:</strong> <?php echo substr($user_details['id'], 0, 8); ?>...</div>
                                <div><strong>Created:</strong> <?php echo formatDate($user_details['date_created']); ?></div>
                                <div><strong>Last Login:</strong> <?php echo $user_details['last_login'] ? formatDateTime($user_details['last_login']) : 'Never'; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Success Message -->
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Please correct the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Warning for self-edit -->
                <?php if ($user_id === $current_user_id): ?>
                <div class="warning-box">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        <div>
                            <strong>Warning:</strong> You are editing your own account. Be careful not to lock yourself out.
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Profile Form -->
                    <div class="col-md-8">
                        <div class="form-card">
                            <form method="POST" id="profileForm" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="update_profile" value="1">
                                
                                <!-- Basic Information -->
                                <div class="form-section">
                                    <h3 class="section-title">
                                        <i class="fas fa-user"></i>
                                        Profile Information
                                    </h3>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                                   required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">User Role <span class="text-danger">*</span></label>
                                            <select class="form-select" name="role" id="userRole" required>
                                                <option value="student" <?php echo $form_data['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                                <option value="program_admin" <?php echo $form_data['role'] === 'program_admin' ? 'selected' : ''; ?>>Program Admin</option>
                                                <option value="admin" <?php echo $form_data['role'] === 'admin' ? 'selected' : ''; ?>>System Admin</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6" id="programField" style="<?php echo $form_data['role'] === 'student' ? 'display: block;' : 'display: none;'; ?>">
                                            <label class="form-label">Program</label>
                                            <select class="form-select" name="program_id" id="programSelect">
                                                <option value="">No Program Assigned</option>
                                                <?php foreach ($programs as $prog): ?>
                                                <option value="<?php echo $prog['id']; ?>" 
                                                        <?php echo $form_data['program_id'] == $prog['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($prog['program_name'] . ' (' . $prog['program_code'] . ')'); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Account Settings -->
                                <div class="form-section">
                                    <h3 class="section-title">
                                        <i class="fas fa-cog"></i>
                                        Account Settings
                                    </h3>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-check-custom">
                                                <input class="form-check-input" type="checkbox" name="is_active" 
                                                       id="isActive" <?php echo $form_data['is_active'] ? 'checked' : ''; ?>
                                                       <?php echo $user_id === $current_user_id ? 'disabled' : ''; ?>>
                                                <label class="form-check-label" for="isActive">
                                                    <strong>Account Active</strong><br>
                                                    <small class="text-muted">User can login and access the system</small>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-check-custom">
                                                <input class="form-check-input" type="checkbox" name="email_verified" 
                                                       id="emailVerified" <?php echo $form_data['email_verified'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="emailVerified">
                                                    <strong>Email Verified</strong><br>
                                                    <small class="text-muted">Mark email as verified</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-save me-2"></i>
                                        Update Profile
                                    </button>
                                    <a href="<?php echo $user_details['role'] === 'student' ? 'students.php' : 'admins.php'; ?>" 
                                       class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Password Form -->
                    <div class="col-md-4">
                        <div class="form-card">
                            <form method="POST" id="passwordForm" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="change_password" value="1">
                                
                                <h3 class="section-title">
                                    <i class="fas fa-lock"></i>
                                    Change Password
                                </h3>
                                
                                <div class="mb-3">
                                    <label class="form-label">New Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="new_password" 
                                           id="newPassword" required>
                                    
                                    <!-- Password Strength Indicator -->
                                    <div class="password-strength">
                                        <div class="strength-meter">
                                            <div class="strength-fill" id="strengthFill"></div>
                                        </div>
                                        <div class="strength-text" id="strengthText">Enter a strong password</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="confirm_password" 
                                           id="confirmPassword" required>
                                    <div class="invalid-feedback" id="passwordMatchFeedback" style="display: none;">
                                        Passwords do not match.
                                    </div>
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" class="btn btn-warning w-100">
                                        <i class="fas fa-key me-2"></i>
                                        Change Password
                                    </button>
                                </div>
                            </form>
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
        document.addEventListener('DOMContentLoaded', function() {
            const userRole = document.getElementById('userRole');
            const programField = document.getElementById('programField');
            const programSelect = document.getElementById('programSelect');
            const newPasswordInput = document.getElementById('newPassword');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            // Role change handler
            userRole.addEventListener('change', function() {
                if (this.value === 'student') {
                    programField.style.display = 'block';
                    programSelect.required = true;
                } else {
                    programField.style.display = 'none';
                    programSelect.required = false;
                }
            });
            
            // Password strength checker
            function checkPasswordStrength(password) {
                let score = 0;
                const requirements = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /\d/.test(password)
                };
                
                Object.values(requirements).forEach(req => {
                    if (req) score++;
                });
                
                strengthFill.className = 'strength-fill';
                
                if (score === 0) {
                    strengthText.textContent = 'Enter a password';
                } else if (score === 1) {
                    strengthFill.classList.add('strength-weak');
                    strengthText.textContent = 'Weak password';
                } else if (score === 2) {
                    strengthFill.classList.add('strength-fair');
                    strengthText.textContent = 'Fair password';
                } else if (score === 3) {
                    strengthFill.classList.add('strength-good');
                    strengthText.textContent = 'Good password';
                } else if (score === 4) {
                    strengthFill.classList.add('strength-strong');
                    strengthText.textContent = 'Strong password';
                }
                
                return score >= 4;
            }
            
            // Password input event
            newPasswordInput.addEventListener('input', function() {
                checkPasswordStrength(this.value);
                checkPasswordMatch();
            });
            
            // Confirm password input event
            confirmPasswordInput.addEventListener('input', function() {
                checkPasswordMatch();
            });
            
            function checkPasswordMatch() {
                const password = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const feedback = document.getElementById('passwordMatchFeedback');
                
                if (confirmPassword && password !== confirmPassword) {
                    confirmPasswordInput.style.borderColor = 'var(--danger-color)';
                    feedback.style.display = 'block';
                    return false;
                } else {
                    confirmPasswordInput.style.borderColor = '#e9ecef';
                    feedback.style.display = 'none';
                    return true;
                }
            }
            
            // Password form validation
            const passwordForm = document.getElementById('passwordForm');
            passwordForm.addEventListener('submit', function(e) {
                const isStrongPassword = checkPasswordStrength(newPasswordInput.value);
                const passwordsMatch = checkPasswordMatch();
                
                if (!isStrongPassword || !passwordsMatch) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (!isStrongPassword) {
                        newPasswordInput.focus();
                    } else if (!passwordsMatch) {
                        confirmPasswordInput.focus();
                    }
                    return;
                }
                
                if (confirm('Are you sure you want to change the password?')) {
                    return true;
                } else {
                    e.preventDefault();
                }
            });
            
            // Profile form validation
            const profileForm = document.getElementById('profileForm');
            profileForm.addEventListener('submit', function(e) {
                if (!profileForm.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                    profileForm.classList.add('was-validated');
                }
            });
            
            // Auto-hide alerts
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 8000);
        });
    </script>
</body>
</html>