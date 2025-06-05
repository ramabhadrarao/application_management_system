<?php
/**
 * Users Management - Add New User
 * 
 * File: admin/users/add.php
 * Purpose: Add new user form with validation
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

// Initialize variables
$errors = [];
$success_message = '';
$form_data = [
    'email' => '',
    'role' => 'student',
    'program_id' => '',
    'password' => '',
    'confirm_password' => '',
    'is_active' => 1,
    'email_verified' => 1
];

// Get all active programs for dropdown
$programs = $program->getAllActivePrograms();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        
        // Collect form data
        $form_data = [
            'email' => strtolower(trim($_POST['email'])),
            'role' => $_POST['role'],
            'program_id' => $_POST['program_id'] ?: null,
            'password' => $_POST['password'],
            'confirm_password' => $_POST['confirm_password'],
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'email_verified' => isset($_POST['email_verified']) ? 1 : 0
        ];
        
        // Validation
        if (empty($form_data['email'])) {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif ($user->emailExists($form_data['email'])) {
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
        
        if (empty($form_data['password'])) {
            $errors[] = 'Password is required.';
        } elseif (strlen($form_data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $form_data['password'])) {
            $errors[] = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
        }
        
        if ($form_data['password'] !== $form_data['confirm_password']) {
            $errors[] = 'Passwords do not match.';
        }
        
        // If no errors, create the user
        if (empty($errors)) {
            $user->email = $form_data['email'];
            $user->password = $form_data['password'];
            $user->role = $form_data['role'];
            $user->is_active = $form_data['is_active'];
            $user->email_verified = $form_data['email_verified'];
            $user->program_id = $form_data['program_id'];
            
            if ($user->register()) {
                $success_message = 'User created successfully! Login credentials have been set.';
                
                // Reset form data
                $form_data = [
                    'email' => '',
                    'role' => 'student',
                    'program_id' => '',
                    'password' => '',
                    'confirm_password' => '',
                    'is_active' => 1,
                    'email_verified' => 1
                ];
            } else {
                $errors[] = 'Failed to create user. Please try again.';
            }
        }
    } else {
        $errors[] = 'Invalid request. Please try again.';
    }
}

$page_title = 'Add New User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
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
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
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
        
        .btn-outline-secondary {
            border-radius: 8px;
            padding: 0.75rem 2rem;
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
        
        .role-description {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #6c757d;
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
        
        .form-check-input {
            width: 18px;
            height: 18px;
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
                            <i class="fas fa-user-plus me-2"></i>
                            Add New User
                        </h2>
                    </div>
                    <div class="col-auto ms-auto">
                        <div class="btn-list">
                            <a href="students.php" class="btn btn-light">
                                <i class="fas fa-users me-2"></i>Students
                            </a>
                            <a href="admins.php" class="btn btn-light">
                                <i class="fas fa-user-shield me-2"></i>Admin Users
                            </a>
                            <a href="../dashboard.php" class="btn btn-light">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="page-body">
            <div class="container-xl">
                <!-- Success Message -->
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <div class="mt-2">
                        <a href="students.php" class="btn btn-sm btn-outline-success">View All Users</a>
                        <button type="button" class="btn btn-sm btn-success" onclick="resetForm()">Add Another User</button>
                    </div>
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
                
                <!-- Add User Form -->
                <div class="form-card">
                    <form method="POST" id="addUserForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <!-- Basic Information -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-user"></i>
                                Basic Information
                            </h3>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                           placeholder="user@example.com" required>
                                    <div class="form-text">This will be used as the login username</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">User Role <span class="text-danger">*</span></label>
                                    <select class="form-select" name="role" id="userRole" required>
                                        <option value="">Select Role</option>
                                        <option value="student" <?php echo $form_data['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                        <option value="program_admin" <?php echo $form_data['role'] === 'program_admin' ? 'selected' : ''; ?>>Program Admin</option>
                                        <option value="admin" <?php echo $form_data['role'] === 'admin' ? 'selected' : ''; ?>>System Admin</option>
                                    </select>
                                    <div class="role-description" id="roleDescription">
                                        Select a role to see description
                                    </div>
                                </div>
                                
                                <div class="col-md-6" id="programField" style="display: none;">
                                    <label class="form-label">Program <span class="text-danger">*</span></label>
                                    <select class="form-select" name="program_id" id="programSelect">
                                        <option value="">Select Program</option>
                                        <?php foreach ($programs as $prog): ?>
                                        <option value="<?php echo $prog['id']; ?>" 
                                                <?php echo $form_data['program_id'] == $prog['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($prog['program_name'] . ' (' . $prog['program_code'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Required for student accounts</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Security -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-lock"></i>
                                Security
                            </h3>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="password" 
                                           id="password" placeholder="Enter password" required>
                                    
                                    <!-- Password Strength Indicator -->
                                    <div class="password-strength">
                                        <div class="strength-meter">
                                            <div class="strength-fill" id="strengthFill"></div>
                                        </div>
                                        <div class="strength-text" id="strengthText">Enter a strong password</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="confirm_password" 
                                           id="confirmPassword" placeholder="Confirm password" required>
                                    <div class="invalid-feedback" id="passwordMatchFeedback" style="display: none;">
                                        Passwords do not match.
                                    </div>
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
                                               id="isActive" <?php echo $form_data['is_active'] ? 'checked' : ''; ?>>
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
                                            <small class="text-muted">Mark email as verified (recommended for admin-created accounts)</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="row g-3 mt-4">
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-user-plus me-2"></i>
                                    Create User
                                </button>
                            </div>
                            <div class="col-md-6">
                                <a href="students.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-times me-2"></i>
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </form>
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
            const roleDescription = document.getElementById('roleDescription');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            const roleDescriptions = {
                'student': 'Can create and manage their own application, upload documents, and view application status.',
                'program_admin': 'Can manage applications for assigned programs, review documents, and manage students.',
                'admin': 'Has full system access including user management, program management, and system settings.'
            };
            
            // Role change handler
            userRole.addEventListener('change', function() {
                const selectedRole = this.value;
                
                if (selectedRole === 'student') {
                    programField.style.display = 'block';
                    programSelect.required = true;
                } else {
                    programField.style.display = 'none';
                    programSelect.required = false;
                }
                
                if (roleDescriptions[selectedRole]) {
                    roleDescription.textContent = roleDescriptions[selectedRole];
                } else {
                    roleDescription.textContent = 'Select a role to see description';
                }
            });
            
            // Initialize role display
            userRole.dispatchEvent(new Event('change'));
            
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
            passwordInput.addEventListener('input', function() {
                checkPasswordStrength(this.value);
                checkPasswordMatch();
            });
            
            // Confirm password input event
            confirmPasswordInput.addEventListener('input', function() {
                checkPasswordMatch();
            });
            
            function checkPasswordMatch() {
                const password = passwordInput.value;
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
            
            // Form validation
            const form = document.getElementById('addUserForm');
            form.addEventListener('submit', function(e) {
                const isStrongPassword = checkPasswordStrength(passwordInput.value);
                const passwordsMatch = checkPasswordMatch();
                
                if (!isStrongPassword || !passwordsMatch) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (!isStrongPassword) {
                        passwordInput.focus();
                    } else if (!passwordsMatch) {
                        confirmPasswordInput.focus();
                    }
                    return;
                }
                
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                    form.classList.add('was-validated');
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
        
        function resetForm() {
            document.getElementById('addUserForm').reset();
            document.getElementById('userRole').dispatchEvent(new Event('change'));
        }
    </script>
</body>
</html>