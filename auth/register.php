<?php
/**
 * User Registration Page
 * 
 * File: auth/register.php
 * Purpose: Handle student registration
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../config/config.php';
require_once '../classes/User.php';
require_once '../classes/Program.php';

$public_page = true; // This is a public page

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$program = new Program($db);

$errors = [];
$success_message = '';
$form_data = [
    'email' => '',
    'program_id' => '',
    'password' => '',
    'confirm_password' => ''
];

// Get all active programs for dropdown
$programs = $program->getAllActivePrograms();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        // Sanitize and validate input
        $form_data['email'] = sanitizeInput($_POST['email']);
        $form_data['program_id'] = sanitizeInput($_POST['program_id']);
        $form_data['password'] = $_POST['password']; // Don't sanitize password
        $form_data['confirm_password'] = $_POST['confirm_password'];
        
        // Validation
        if (empty($form_data['email'])) {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif ($user->emailExists($form_data['email'])) {
            $errors[] = 'This email address is already registered.';
        }
        
        if (empty($form_data['program_id'])) {
            $errors[] = 'Please select a program.';
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
            $user->role = ROLE_STUDENT;
            $user->is_active = 1; // Auto-activate student accounts
            $user->email_verified = 0; // Will need email verification
            $user->program_id = $form_data['program_id'];
            
            if ($user->register()) {
                $success_message = 'Registration successful! You can now login with your credentials.';
                // Clear form data
                $form_data = [
                    'email' => '',
                    'program_id' => '',
                    'password' => '',
                    'confirm_password' => ''
                ];
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    } else {
        $errors[] = 'Invalid request. Please try again.';
    }
}

$page_title = 'Register';
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
    
    <style>
        :root {
            --tblr-primary: #0054a6;
            --tblr-primary-rgb: 0, 84, 166;
        }
        
        .register-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .card-register {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .brand-logo {
            font-size: 2rem;
            color: var(--tblr-primary);
            margin-bottom: 1rem;
        }
        
        .register-form .form-control, .register-form .form-select {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            font-size: 1rem;
        }
        
        .register-form .btn {
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-floating label {
            padding-left: 1rem;
        }
        
        .alert {
            border-radius: 0.5rem;
            border: none;
        }
        
        .text-primary {
            color: var(--tblr-primary) !important;
        }
        
        .btn-primary {
            background-color: var(--tblr-primary);
            border-color: var(--tblr-primary);
        }
        
        .btn-primary:hover {
            background-color: #003d7a;
            border-color: #003d7a;
        }
        
        .password-strength {
            margin-top: 0.25rem;
        }
        
        .password-strength-bar {
            height: 4px;
            border-radius: 2px;
            background-color: #e9ecef;
            overflow: hidden;
        }
        
        .password-strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-weak { background-color: #dc3545; width: 25%; }
        .strength-fair { background-color: #fd7e14; width: 50%; }
        .strength-good { background-color: #ffc107; width: 75%; }
        .strength-strong { background-color: #198754; width: 100%; }
        
        .requirements-list {
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
        }
        
        .requirement i {
            width: 16px;
            margin-right: 0.5rem;
        }
        
        .requirement.valid i {
            color: #198754;
        }
        
        .requirement.invalid i {
            color: #dc3545;
        }
    </style>
</head>
<body class="d-flex flex-column register-page">
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    <div class="card card-register">
                        <div class="card-body p-4">
                            <!-- Brand -->
                            <div class="text-center mb-4">
                                <div class="brand-logo">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <h2 class="h3 text-primary"><?php echo SITE_NAME; ?></h2>
                                <p class="text-muted">Create your student account</p>
                            </div>
                            
                            <!-- Success Message -->
                            <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible" role="alert">
                                <div class="d-flex">
                                    <div class="flex-fill">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <?php echo $success_message; ?>
                                    </div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <div class="text-center">
                                <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login Now
                                </a>
                            </div>
                            <?php else: ?>
                            
                            <!-- Error Messages -->
                            <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible" role="alert">
                                <div class="d-flex">
                                    <div class="flex-fill">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Please correct the following errors:</strong>
                                        <ul class="mb-0 mt-2">
                                            <?php foreach ($errors as $error): ?>
                                            <li><?php echo $error; ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Registration Form -->
                            <form method="POST" class="register-form" autocomplete="off" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                
                                <!-- Email -->
                                <div class="mb-3">
                                    <div class="form-floating">
                                        <input type="email" 
                                               class="form-control" 
                                               id="email" 
                                               name="email" 
                                               placeholder="name@example.com"
                                               value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                               required>
                                        <label for="email">
                                            <i class="fas fa-envelope me-2"></i>Email Address
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        Use a valid email address. You'll need it to access your account.
                                    </div>
                                </div>
                                
                                <!-- Program Selection -->
                                <div class="mb-3">
                                    <label for="program_id" class="form-label">
                                        <i class="fas fa-graduation-cap me-2"></i>Select Program
                                    </label>
                                    <select class="form-select" id="program_id" name="program_id" required>
                                        <option value="">Choose a program...</option>
                                        <?php foreach ($programs as $prog): ?>
                                        <option value="<?php echo $prog['id']; ?>" 
                                                <?php echo ($form_data['program_id'] == $prog['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($prog['program_name'] . ' (' . $prog['program_code'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">
                                        Select the program you want to apply for.
                                    </div>
                                </div>
                                
                                <!-- Password -->
                                <div class="mb-3">
                                    <div class="form-floating">
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               placeholder="Password"
                                               required>
                                        <label for="password">
                                            <i class="fas fa-lock me-2"></i>Password
                                        </label>
                                    </div>
                                    
                                    <!-- Password Strength Indicator -->
                                    <div class="password-strength">
                                        <div class="password-strength-bar">
                                            <div class="password-strength-fill" id="strengthBar"></div>
                                        </div>
                                        <small class="text-muted" id="strengthText">Enter a strong password</small>
                                    </div>
                                    
                                    <!-- Password Requirements -->
                                    <div class="requirements-list">
                                        <div class="requirement invalid" id="req-length">
                                            <i class="fas fa-times"></i>
                                            <span>At least 8 characters long</span>
                                        </div>
                                        <div class="requirement invalid" id="req-uppercase">
                                            <i class="fas fa-times"></i>
                                            <span>Contains uppercase letter</span>
                                        </div>
                                        <div class="requirement invalid" id="req-lowercase">
                                            <i class="fas fa-times"></i>
                                            <span>Contains lowercase letter</span>
                                        </div>
                                        <div class="requirement invalid" id="req-number">
                                            <i class="fas fa-times"></i>
                                            <span>Contains number</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Confirm Password -->
                                <div class="mb-4">
                                    <div class="form-floating">
                                        <input type="password" 
                                               class="form-control" 
                                               id="confirm_password" 
                                               name="confirm_password" 
                                               placeholder="Confirm Password"
                                               required>
                                        <label for="confirm_password">
                                            <i class="fas fa-lock me-2"></i>Confirm Password
                                        </label>
                                    </div>
                                    <div class="invalid-feedback" id="password-match-feedback">
                                        Passwords do not match.
                                    </div>
                                </div>
                                
                                <!-- Terms and Conditions -->
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                        <label class="form-check-label" for="terms">
                                            I agree to the 
                                            <a href="<?php echo SITE_URL; ?>/terms.php" target="_blank" class="text-primary">
                                                Terms and Conditions
                                            </a> and 
                                            <a href="<?php echo SITE_URL; ?>/privacy.php" target="_blank" class="text-primary">
                                                Privacy Policy
                                            </a>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-primary btn-lg" id="registerBtn">
                                        <i class="fas fa-user-plus me-2"></i>Create Account
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Login Link -->
                            <div class="text-center">
                                <p class="text-muted">
                                    Already have an account? 
                                    <a href="<?php echo SITE_URL; ?>/auth/login.php" class="text-primary text-decoration-none">
                                        Login here
                                    </a>
                                </p>
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
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            const registerBtn = document.getElementById('registerBtn');
            const form = document.querySelector('.register-form');
            
            // Password requirements elements
            const reqLength = document.getElementById('req-length');
            const reqUppercase = document.getElementById('req-uppercase');
            const reqLowercase = document.getElementById('req-lowercase');
            const reqNumber = document.getElementById('req-number');
            
            // Password strength checker
            function checkPasswordStrength(password) {
                let score = 0;
                const requirements = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /\d/.test(password)
                };
                
                // Update requirement indicators
                updateRequirement(reqLength, requirements.length);
                updateRequirement(reqUppercase, requirements.uppercase);
                updateRequirement(reqLowercase, requirements.lowercase);
                updateRequirement(reqNumber, requirements.number);
                
                // Calculate score
                Object.values(requirements).forEach(req => {
                    if (req) score++;
                });
                
                // Update strength bar and text
                strengthBar.className = 'password-strength-fill';
                
                if (score === 0) {
                    strengthText.textContent = 'Enter a password';
                } else if (score === 1) {
                    strengthBar.classList.add('strength-weak');
                    strengthText.textContent = 'Weak password';
                } else if (score === 2) {
                    strengthBar.classList.add('strength-fair');
                    strengthText.textContent = 'Fair password';
                } else if (score === 3) {
                    strengthBar.classList.add('strength-good');
                    strengthText.textContent = 'Good password';
                } else if (score === 4) {
                    strengthBar.classList.add('strength-strong');
                    strengthText.textContent = 'Strong password';
                }
                
                return score >= 4; // All requirements met
            }
            
            function updateRequirement(element, isValid) {
                const icon = element.querySelector('i');
                if (isValid) {
                    element.classList.remove('invalid');
                    element.classList.add('valid');
                    icon.className = 'fas fa-check';
                } else {
                    element.classList.remove('valid');
                    element.classList.add('invalid');
                    icon.className = 'fas fa-times';
                }
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
                
                if (confirmPassword && password !== confirmPassword) {
                    confirmPasswordInput.setCustomValidity('Passwords do not match');
                    confirmPasswordInput.classList.add('is-invalid');
                } else {
                    confirmPasswordInput.setCustomValidity('');
                    confirmPasswordInput.classList.remove('is-invalid');
                }
            }
            
            // Form validation
            form.addEventListener('submit', function(event) {
                const password = passwordInput.value;
                const isStrongPassword = checkPasswordStrength(password);
                
                if (!isStrongPassword) {
                    event.preventDefault();
                    event.stopPropagation();
                    passwordInput.setCustomValidity('Password does not meet requirements');
                } else {
                    passwordInput.setCustomValidity('');
                }
                
                checkPasswordMatch();
                
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            });
            
            // Auto-hide alerts
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    if (alert.classList.contains('alert-danger')) {
                        setTimeout(() => alert.style.display = 'none', 8000);
                    } else {
                        setTimeout(() => alert.style.display = 'none', 5000);
                    }
                });
            }, 100);
            
            // Focus on first input
            const emailInput = document.getElementById('email');
            if (emailInput && !emailInput.value) {
                emailInput.focus();
            }
        });
    </script>
</body>
</html>