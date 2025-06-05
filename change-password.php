<?php
/**
 * Change Password
 * 
 * File: change-password.php
 * Purpose: Allow users to change their password
 * Author: Student Application Management System
 * Created: 2025
 */

require_once 'config/config.php';
require_once 'classes/User.php';

// Require authentication
requireLogin();

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$current_user_id = getCurrentUserId();

$errors = [];
$success_message = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        if (empty($current_password)) {
            $errors[] = 'Current password is required.';
        }
        
        if (empty($new_password)) {
            $errors[] = 'New password is required.';
        } elseif (strlen($new_password) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $new_password)) {
            $errors[] = 'New password must contain at least one uppercase letter, one lowercase letter, and one number.';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match.';
        }
        
        if ($current_password === $new_password) {
            $errors[] = 'New password must be different from current password.';
        }
        
        // If no validation errors, try to change password
        if (empty($errors)) {
            $user->id = $current_user_id;
            if ($user->changePassword($current_password, $new_password)) {
                $success_message = 'Password changed successfully!';
                // Clear form
                $_POST = [];
            } else {
                $errors[] = 'Current password is incorrect.';
            }
        }
    } else {
        $errors[] = 'Invalid request. Please try again.';
    }
}

$page_title = 'Change Password';
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        
        .password-container {
            width: 100%;
            max-width: 500px;
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        
        .password-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .password-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
        
        .password-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .password-subtitle {
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .password-body {
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .form-control-modern {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }
        
        .form-control-modern:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 84, 166, 0.1);
        }
        
        .input-group {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: var(--text-light);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: var(--primary-color);
        }
        
        .btn-change-password {
            width: 100%;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            border: none;
            border-radius: var(--radius-lg);
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-change-password:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-change-password:disabled {
            background: var(--text-light);
            cursor: not-allowed;
            transform: none;
        }
        
        .alert-modern {
            border-radius: var(--radius-lg);
            border: none;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .alert-danger-modern {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .alert-success-modern {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .password-requirements {
            background: var(--bg-light);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .requirement:last-child {
            margin-bottom: 0;
        }
        
        .requirement-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-size: 0.7rem;
            transition: all 0.3s ease;
        }
        
        .requirement.valid {
            color: var(--success-color);
        }
        
        .requirement.valid .requirement-icon {
            background: var(--success-color);
            color: white;
        }
        
        .requirement.invalid {
            color: var(--text-light);
        }
        
        .requirement.invalid .requirement-icon {
            background: var(--border-color);
            color: var(--text-light);
        }
        
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .back-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .back-link a:hover {
            color: #003d7a;
            text-decoration: underline;
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
        .strength-good { background: #17a2b8; width: 75%; }
        .strength-strong { background: var(--success-color); width: 100%; }
        
        .strength-text {
            font-size: 0.8rem;
            color: var(--text-light);
        }
    </style>
</head>
<body>
    <div class="password-container">
        <!-- Header -->
        <div class="password-header">
            <div class="password-icon">
                <i class="fas fa-key"></i>
            </div>
            <h1 class="password-title">Change Password</h1>
            <p class="password-subtitle">Update your account security</p>
        </div>
        
        <!-- Body -->
        <div class="password-body">
            <!-- Alerts -->
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
            
            <?php if (!empty($success_message)): ?>
            <div class="alert-modern alert-success-modern">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success_message; ?></span>
            </div>
            <?php endif; ?>
            
            <!-- Password Requirements -->
            <div class="password-requirements">
                <h6 class="mb-3"><i class="fas fa-shield-alt text-primary me-2"></i>Password Requirements</h6>
                <div class="requirement" id="req-length">
                    <div class="requirement-icon">
                        <i class="fas fa-times"></i>
                    </div>
                    <span>At least 8 characters long</span>
                </div>
                <div class="requirement" id="req-uppercase">
                    <div class="requirement-icon">
                        <i class="fas fa-times"></i>
                    </div>
                    <span>Contains uppercase letter (A-Z)</span>
                </div>
                <div class="requirement" id="req-lowercase">
                    <div class="requirement-icon">
                        <i class="fas fa-times"></i>
                    </div>
                    <span>Contains lowercase letter (a-z)</span>
                </div>
                <div class="requirement" id="req-number">
                    <div class="requirement-icon">
                        <i class="fas fa-times"></i>
                    </div>
                    <span>Contains number (0-9)</span>
                </div>
            </div>
            
            <!-- Password Change Form -->
            <form method="POST" id="passwordForm" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <!-- Current Password -->
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <div class="input-group">
                        <input type="password" 
                               class="form-control-modern" 
                               name="current_password" 
                               id="currentPassword"
                               placeholder="Enter your current password"
                               required
                               autocomplete="current-password">
                        <button type="button" class="password-toggle" onclick="togglePassword('currentPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <!-- New Password -->
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <div class="input-group">
                        <input type="password" 
                               class="form-control-modern" 
                               name="new_password" 
                               id="newPassword"
                               placeholder="Enter your new password"
                               required
                               autocomplete="new-password">
                        <button type="button" class="password-toggle" onclick="togglePassword('newPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    
                    <!-- Password Strength Indicator -->
                    <div class="password-strength">
                        <div class="strength-meter">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-text" id="strengthText">Enter a strong password</div>
                    </div>
                </div>
                
                <!-- Confirm Password -->
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" 
                               class="form-control-modern" 
                               name="confirm_password" 
                               id="confirmPassword"
                               placeholder="Re-enter your new password"
                               required
                               autocomplete="new-password">
                        <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback" id="passwordMatchFeedback" style="display: none; color: var(--danger-color); font-size: 0.8rem; margin-top: 0.5rem;">
                        Passwords do not match.
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="btn-change-password" id="submitBtn" disabled>
                    <i class="fas fa-key me-2"></i>
                    Change Password
                </button>
            </form>
            
            <!-- Back Link -->
            <div class="back-link">
                <a href="profile.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Profile
                </a>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    
    <script>
        // Password visibility toggle
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
        
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
            updateRequirement('req-length', requirements.length);
            updateRequirement('req-uppercase', requirements.uppercase);
            updateRequirement('req-lowercase', requirements.lowercase);
            updateRequirement('req-number', requirements.number);
            
            // Calculate score
            Object.values(requirements).forEach(req => {
                if (req) score++;
            });
            
            // Update strength bar and text
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
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
        
        function updateRequirement(elementId, isValid) {
            const element = document.getElementById(elementId);
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
        
        // Password matching checker
        function checkPasswordMatch() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const feedback = document.getElementById('passwordMatchFeedback');
            const confirmInput = document.getElementById('confirmPassword');
            
            if (confirmPassword && newPassword !== confirmPassword) {
                confirmInput.style.borderColor = 'var(--danger-color)';
                feedback.style.display = 'block';
                return false;
            } else {
                confirmInput.style.borderColor = 'var(--border-color)';
                feedback.style.display = 'none';
                return true;
            }
        }
        
        // Form validation
        function validateForm() {
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            const isCurrentPasswordValid = currentPassword.length > 0;
            const isNewPasswordStrong = checkPasswordStrength(newPassword);
            const doPasswordsMatch = checkPasswordMatch();
            const arePasswordsDifferent = currentPassword !== newPassword;
            
            const isFormValid = isCurrentPasswordValid && isNewPasswordStrong && 
                              doPasswordsMatch && arePasswordsDifferent;
            
            document.getElementById('submitBtn').disabled = !isFormValid;
            
            return isFormValid;
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const currentPasswordInput = document.getElementById('currentPassword');
            const newPasswordInput = document.getElementById('newPassword');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const form = document.getElementById('passwordForm');
            
            // Real-time validation
            [currentPasswordInput, newPasswordInput, confirmPasswordInput].forEach(input => {
                input.addEventListener('input', validateForm);
                input.addEventListener('blur', validateForm);
            });
            
            // Special handling for new password
            newPasswordInput.addEventListener('input', function() {
                checkPasswordStrength(this.value);
                validateForm();
            });
            
            // Special handling for confirm password
            confirmPasswordInput.addEventListener('input', function() {
                checkPasswordMatch();
                validateForm();
            });
            
            // Form submission
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Changing Password...';
            });
            
            // Focus on first input
            currentPasswordInput.focus();
        });
        
        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-modern');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                const form = document.getElementById('passwordForm');
                if (validateForm()) {
                    form.submit();
                }
            }
        });
    </script>
</body>
</html>