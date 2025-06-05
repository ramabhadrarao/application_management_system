<?php
/**
 * Enhanced User Registration Page
 * 
 * File: auth/register.php
 * Purpose: Modern professional registration with enhanced UI/UX
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

// Check for pre-selected course from URL
if (isset($_GET['course'])) {
    $course_code = sanitizeInput($_GET['course']);
    foreach ($programs as $prog) {
        if ($prog['program_code'] === $course_code) {
            $form_data['program_id'] = $prog['id'];
            break;
        }
    }
}

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
        
        if (!isset($_POST['terms'])) {
            $errors[] = 'You must agree to the Terms and Conditions.';
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

$page_title = 'Create Account';
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
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 50%, var(--accent-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated background elements */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: floatReverse 25s linear infinite;
            z-index: 0;
        }
        
        @keyframes floatReverse {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(50px, 50px) rotate(-360deg); }
        }
        
        .register-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1200px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 700px;
        }
        
        .register-left {
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .register-right {
            background: linear-gradient(135deg, var(--accent-color) 0%, var(--primary-color) 100%);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .register-right::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(-50%, -50%);
        }
        
        .register-right::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            transform: translate(50%, 50%);
        }
        
        .info-section {
            position: relative;
            z-index: 2;
        }
        
        .info-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1.5rem;
        }
        
        .info-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.2;
        }
        
        .info-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            line-height: 1.5;
        }
        
        .benefits-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .benefits-list li {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.95rem;
            opacity: 0.9;
        }
        
        .benefits-list i {
            width: 20px;
            margin-right: 1rem;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        
        .register-subtitle {
            color: var(--text-light);
            font-size: 0.95rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
            font-size: 0.9rem;
        }
        
        .form-label.required::after {
            content: ' *';
            color: var(--danger-color);
        }
        
        .form-control-modern, .form-select-modern {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: var(--white);
            color: var(--text-dark);
        }
        
        .form-control-modern:focus, .form-select-modern:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 84, 166, 0.1);
            transform: translateY(-1px);
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group .form-control-modern {
            padding-left: 3rem;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            z-index: 3;
        }
        
        .password-strength {
            margin-top: 0.5rem;
        }
        
        .strength-meter {
            height: 4px;
            background: var(--border-color);
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
            color: var(--text-light);
        }
        
        .requirements-list {
            margin-top: 0.75rem;
            padding: 0.75rem;
            background: var(--bg-light);
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
            color: var(--text-light);
        }
        
        .requirement.valid {
            color: var(--success-color);
        }
        
        .requirement i {
            width: 16px;
            margin-right: 0.5rem;
        }
        
        .btn-primary-modern {
            width: 100%;
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: var(--radius-md);
            color: white;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 100%);
        }
        
        .btn-primary-modern:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .form-check {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            background: var(--white);
            cursor: pointer;
            margin-top: 2px;
            flex-shrink: 0;
        }
        
        .form-check-input:checked {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-check-label {
            font-size: 0.85rem;
            color: var(--text-light);
            cursor: pointer;
            line-height: 1.4;
        }
        
        .form-check-label a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .form-check-label a:hover {
            text-decoration: underline;
        }
        
        .alert-modern {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.9rem;
            animation: slideDown 0.3s ease;
        }
        
        .alert-danger-modern {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: var(--danger-color);
        }
        
        .alert-success-modern {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            color: var(--success-color);
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .auth-links {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .auth-links a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .auth-links a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            border-radius: 24px;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .register-container {
                grid-template-columns: 1fr;
                max-width: 500px;
            }
            
            .register-right {
                order: -1;
                padding: 2rem;
                min-height: auto;
            }
            
            .register-left {
                padding: 2rem;
            }
            
            .info-title {
                font-size: 1.5rem;
            }
            
            .register-title {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .register-left, .register-right {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner"></div>
        </div>
        
        <!-- Left Side - Registration Form -->
        <div class="register-left">
            <div class="register-header">
                <h2 class="register-title">Create Your Account</h2>
                <p class="register-subtitle">Join thousands of students and start your academic journey</p>
            </div>
            
            <!-- Success Message -->
            <?php if (!empty($success_message)): ?>
            <div class="alert-modern alert-success-modern">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong><?php echo $success_message; ?></strong>
                    <div class="mt-2">
                        <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn-primary-modern" style="width: auto; padding: 0.5rem 1rem; font-size: 0.85rem;">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Login Now</span>
                        </a>
                    </div>
                </div>
            </div>
            <?php else: ?>
            
            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
            <div class="alert-modern alert-danger-modern">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Please correct the following errors:</strong>
                    <ul style="margin: 0.5rem 0 0 0; padding-left: 1.25rem;">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Registration Form -->
            <form method="POST" id="registerForm" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <!-- Email -->
                <div class="form-group">
                    <label class="form-label required">Email Address</label>
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <input type="email" 
                               class="form-control-modern" 
                               name="email" 
                               value="<?php echo htmlspecialchars($form_data['email']); ?>"
                               placeholder="Enter your email address"
                               required>
                    </div>
                    <div class="form-text" style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.25rem;">
                        Use a valid email address. You'll need it to access your account.
                    </div>
                </div>
                
                <!-- Program Selection -->
                <div class="form-group">
                    <label class="form-label required">Select Program</label>
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <select class="form-select-modern" name="program_id" required style="padding-left: 3rem;">
                            <option value="">Choose a program...</option>
                            <?php 
                            $program_types = ['UG' => 'Undergraduate', 'PG' => 'Postgraduate', 'Diploma' => 'Diploma'];
                            $current_type = '';
                            foreach ($programs as $prog): 
                                if ($current_type !== $prog['program_type']) {
                                    if ($current_type !== '') echo '</optgroup>';
                                    $current_type = $prog['program_type'];
                                    echo '<optgroup label="' . $program_types[$current_type] . ' Programs">';
                                }
                            ?>
                            <option value="<?php echo $prog['id']; ?>" 
                                    <?php echo ($form_data['program_id'] == $prog['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($prog['program_name'] . ' (' . $prog['program_code'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                            <?php if ($current_type !== '') echo '</optgroup>'; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Password -->
                <div class="form-group">
                    <label class="form-label required">Password</label>
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" 
                               class="form-control-modern" 
                               name="password" 
                               id="password"
                               placeholder="Create a strong password"
                               required>
                    </div>
                    
                    <!-- Password Strength Indicator -->
                    <div class="password-strength">
                        <div class="strength-meter">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-text" id="strengthText">Enter a strong password</div>
                    </div>
                    
                    <!-- Password Requirements -->
                    <div class="requirements-list" id="requirements">
                        <div class="requirement" id="req-length">
                            <i class="fas fa-times"></i>
                            <span>At least 8 characters long</span>
                        </div>
                        <div class="requirement" id="req-uppercase">
                            <i class="fas fa-times"></i>
                            <span>Contains uppercase letter (A-Z)</span>
                        </div>
                        <div class="requirement" id="req-lowercase">
                            <i class="fas fa-times"></i>
                            <span>Contains lowercase letter (a-z)</span>
                        </div>
                        <div class="requirement" id="req-number">
                            <i class="fas fa-times"></i>
                            <span>Contains number (0-9)</span>
                        </div>
                    </div>
                </div>
                
                <!-- Confirm Password -->
                <div class="form-group">
                    <label class="form-label required">Confirm Password</label>
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" 
                               class="form-control-modern" 
                               name="confirm_password" 
                               id="confirmPassword"
                               placeholder="Re-enter your password"
                               required>
                    </div>
                    <div class="invalid-feedback" id="passwordMatchFeedback" style="display: none; color: var(--danger-color); font-size: 0.8rem; margin-top: 0.25rem;">
                        Passwords do not match.
                    </div>
                </div>
                
                <!-- Terms and Conditions -->
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="terms" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the 
                        <a href="<?php echo SITE_URL; ?>/terms.php" target="_blank">Terms and Conditions</a> 
                        and 
                        <a href="<?php echo SITE_URL; ?>/privacy.php" target="_blank">Privacy Policy</a>.
                        I understand that I will receive communications regarding my application and admission process.
                    </label>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="btn-primary-modern" id="registerBtn">
                    <i class="fas fa-user-plus"></i>
                    <span>Create Account</span>
                </button>
            </form>
            
            <!-- Login Link -->
            <div class="auth-links">
                <p>
                    Already have an account? 
                    <a href="<?php echo SITE_URL; ?>/auth/login.php">
                        <i class="fas fa-sign-in-alt me-1"></i>Sign in here
                    </a>
                </p>
                <p>
                    <a href="<?php echo SITE_URL; ?>/">
                        <i class="fas fa-home me-1"></i>Back to homepage
                    </a>
                </p>
            </div>
            
            <?php endif; ?>
        </div>
        
        <!-- Right Side - Information -->
        <div class="register-right">
            <div class="info-section">
                <div class="info-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <h1 class="info-title">Start Your Journey</h1>
                <p class="info-subtitle">
                    Join our community of learners and unlock your potential with world-class education and industry-relevant programs.
                </p>
                
                <ul class="benefits-list">
                    <li>
                        <i class="fas fa-graduation-cap"></i>
                        <span>Access to 25+ cutting-edge programs</span>
                    </li>
                    <li>
                        <i class="fas fa-laptop-code"></i>
                        <span>Modern labs and latest technology</span>
                    </li>
                    <li>
                        <i class="fas fa-users"></i>
                        <span>Expert faculty and industry mentors</span>
                    </li>
                    <li>
                        <i class="fas fa-briefcase"></i>
                        <span>100% placement assistance guarantee</span>
                    </li>
                    <li>
                        <i class="fas fa-award"></i>
                        <span>NAAC accredited institution</span>
                    </li>
                    <li>
                        <i class="fas fa-globe-americas"></i>
                        <span>Global career opportunities</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const registerForm = document.getElementById('registerForm');
            const registerBtn = document.getElementById('registerBtn');
            const loadingOverlay = document.getElementById('loadingOverlay');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
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
                const feedback = document.getElementById('passwordMatchFeedback');
                
                if (confirmPassword && password !== confirmPassword) {
                    confirmPasswordInput.style.borderColor = 'var(--danger-color)';
                    feedback.style.display = 'block';
                    return false;
                } else {
                    confirmPasswordInput.style.borderColor = 'var(--border-color)';
                    feedback.style.display = 'none';
                    return true;
                }
            }
            
            // Form validation and submission
            registerForm.addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const isStrongPassword = checkPasswordStrength(password);
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
                
                if (!registerForm.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                    registerForm.classList.add('was-validated');
                    return;
                }
                
                // Show loading state
                registerBtn.disabled = true;
                loadingOverlay.style.display = 'flex';
                
                const originalContent = registerBtn.innerHTML;
                registerBtn.innerHTML = '<div class="spinner" style="width: 20px; height: 20px; border-width: 2px;"></div><span>Creating Account...</span>';
                
                // Re-enable after timeout as fallback
                setTimeout(() => {
                    registerBtn.disabled = false;
                    registerBtn.innerHTML = originalContent;
                    loadingOverlay.style.display = 'none';
                }, 15000);
            });
            
            // Enhanced form validation
            const inputs = document.querySelectorAll('.form-control-modern, .form-select-modern');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.value.trim() !== '') {
                        this.style.borderColor = 'var(--success-color)';
                    } else if (this.hasAttribute('required')) {
                        this.style.borderColor = 'var(--danger-color)';
                    }
                });
                
                input.addEventListener('input', function() {
                    this.style.borderColor = 'var(--border-color)';
                });
            });
            
            // Auto-hide alerts
            const alerts = document.querySelectorAll('.alert-modern');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 300);
                }, 8000);
            });
            
            // Focus on first empty input
            const emailInput = document.querySelector('[name="email"]');
            if (emailInput && !emailInput.value) {
                emailInput.focus();
            }
            
            // Program selection enhancement
            const programSelect = document.querySelector('[name="program_id"]');
            programSelect.addEventListener('change', function() {
                if (this.value) {
                    this.style.borderColor = 'var(--success-color)';
                }
            });
            
            // Terms checkbox validation
            const termsCheckbox = document.getElementById('terms');
            termsCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    registerBtn.disabled = false;
                    registerBtn.style.opacity = '1';
                } else {
                    registerBtn.disabled = true;
                    registerBtn.style.opacity = '0.5';
                }
            });
            
            // Initially disable submit button until terms are accepted
            registerBtn.disabled = true;
            registerBtn.style.opacity = '0.5';
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'Enter') {
                    if (!registerBtn.disabled) {
                        registerForm.submit();
                    }
                }
            });
            
            // Email validation
            const emailField = document.querySelector('[name="email"]');
            emailField.addEventListener('blur', function() {
                const email = this.value.trim();
                if (email && !isValidEmail(email)) {
                    this.style.borderColor = 'var(--danger-color)';
                    showFieldError(this, 'Please enter a valid email address');
                } else {
                    this.style.borderColor = 'var(--border-color)';
                    hideFieldError(this);
                }
            });
            
            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
            
            function showFieldError(field, message) {
                let errorEl = field.parentNode.querySelector('.field-error');
                if (!errorEl) {
                    errorEl = document.createElement('div');
                    errorEl.className = 'field-error';
                    errorEl.style.cssText = 'color: var(--danger-color); font-size: 0.8rem; margin-top: 0.25rem;';
                    field.parentNode.appendChild(errorEl);
                }
                errorEl.textContent = message;
            }
            
            function hideFieldError(field) {
                const errorEl = field.parentNode.querySelector('.field-error');
                if (errorEl) {
                    errorEl.remove();
                }
            }
        });
        
        // Smooth animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        // Add entrance animations
        document.querySelectorAll('.form-group, .btn-primary-modern, .auth-links, .form-check').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });
        
        // Trigger animations after a short delay
        setTimeout(() => {
            document.querySelectorAll('.form-group, .btn-primary-modern, .auth-links, .form-check').forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 150);
            });
        }, 300);
    </script>
</body>
</html>