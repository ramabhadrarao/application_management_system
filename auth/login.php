<?php
/**
 * Enhanced User Login Page
 * 
 * File: auth/login.php
 * Purpose: Modern professional login with enhanced UI/UX
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../config/config.php';
require_once '../classes/User.php';

$public_page = true; // This is a public page

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$login_error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        $email = sanitizeInput($_POST['email']);
        $password = sanitizeInput($_POST['password']);
        
        if (empty($email) || empty($password)) {
            $login_error = 'Please enter both email and password.';
        } else {
            $user->email = $email;
            $user->password = $password;
            
            if ($user->login()) {
                // Login successful - redirect based on role
                $redirect_url = SITE_URL . '/dashboard.php';
                
                // Check if there was a requested page
                if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
                    $redirect_url = urldecode($_GET['redirect']);
                }
                
                header('Location: ' . $redirect_url);
                exit;
            } else {
                $login_error = 'Invalid email or password. Please try again.';
            }
        }
    } else {
        $login_error = 'Invalid request. Please try again.';
    }
}

$page_title = 'Login';
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
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: float 20s linear infinite;
            z-index: 0;
        }
        
        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(-50px, -50px) rotate(360deg); }
        }
        
        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1000px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 600px;
        }
        
        .login-left {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }
        
        .login-left::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            transform: translate(-50%, 50%);
        }
        
        .brand-section {
            position: relative;
            z-index: 2;
        }
        
        .brand-logo {
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
        
        .brand-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.2;
        }
        
        .brand-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            line-height: 1.5;
        }
        
        .features-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .features-list li {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.95rem;
            opacity: 0.9;
        }
        
        .features-list i {
            width: 20px;
            margin-right: 1rem;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .login-right {
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .login-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        
        .login-subtitle {
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
        
        .form-control-modern {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: var(--white);
            color: var(--text-dark);
        }
        
        .form-control-modern:focus {
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
        
        .btn-primary-modern:active {
            transform: translateY(0);
        }
        
        .btn-primary-modern:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .form-check {
            display: flex;
            align-items: center;
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
        }
        
        .form-check-input:checked {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-check-label {
            font-size: 0.9rem;
            color: var(--text-light);
            cursor: pointer;
        }
        
        .alert-modern {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: var(--danger-color);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.9rem;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border-color);
        }
        
        .divider span {
            background: var(--white);
            padding: 0 1rem;
            position: relative;
            z-index: 2;
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
            
            .login-container {
                grid-template-columns: 1fr;
                max-width: 400px;
            }
            
            .login-left {
                display: none;
            }
            
            .login-right {
                padding: 2rem;
            }
            
            .brand-title {
                font-size: 1.5rem;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .login-right {
                padding: 1.5rem;
            }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--bg-light);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner"></div>
        </div>
        
        <!-- Left Side - Branding -->
        <div class="login-left">
            <div class="brand-section">
                <div class="brand-logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h1 class="brand-title"><?php echo SITE_NAME; ?></h1>
                <p class="brand-subtitle">
                    Empowering students with seamless online admission process and comprehensive application management.
                </p>
                
                <ul class="features-list">
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>Easy online application process</span>
                    </li>
                    <li>
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Secure document upload system</span>
                    </li>
                    <li>
                        <i class="fas fa-chart-line"></i>
                        <span>Real-time application tracking</span>
                    </li>
                    <li>
                        <i class="fas fa-shield-alt"></i>
                        <span>Bank-level security & privacy</span>
                    </li>
                    <li>
                        <i class="fas fa-headset"></i>
                        <span>24/7 support & assistance</span>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Right Side - Login Form -->
        <div class="login-right">
            <div class="login-header">
                <h2 class="login-title">Welcome Back</h2>
                <p class="login-subtitle">Sign in to your account to continue</p>
            </div>
            
            <!-- Error Message -->
            <?php if (!empty($login_error)): ?>
            <div class="alert-modern">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo $login_error; ?></span>
            </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form method="POST" id="loginForm" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <input type="email" 
                               class="form-control-modern" 
                               name="email" 
                               value="<?php echo htmlspecialchars($email); ?>"
                               placeholder="Enter your email address"
                               required
                               autocomplete="email">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" 
                               class="form-control-modern" 
                               name="password" 
                               placeholder="Enter your password"
                               required
                               autocomplete="current-password">
                    </div>
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label" for="remember">
                        Remember me for 30 days
                    </label>
                </div>
                
                <button type="submit" class="btn-primary-modern" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Sign In</span>
                </button>
            </form>
            
            <div class="divider">
                <span>Need help?</span>
            </div>
            
            <div class="auth-links">
                <p>
                    <a href="<?php echo SITE_URL; ?>/auth/forgot-password.php">
                        <i class="fas fa-key me-1"></i>Forgot your password?
                    </a>
                </p>
                <p>
                    Don't have an account? 
                    <a href="<?php echo SITE_URL; ?>/auth/register.php">
                        <i class="fas fa-user-plus me-1"></i>Create new account
                    </a>
                </p>
                <p>
                    <a href="<?php echo SITE_URL; ?>/">
                        <i class="fas fa-home me-1"></i>Back to homepage
                    </a>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            // Focus on first empty input
            const emailInput = document.querySelector('[name="email"]');
            const passwordInput = document.querySelector('[name="password"]');
            
            if (emailInput && !emailInput.value) {
                emailInput.focus();
            } else if (passwordInput) {
                passwordInput.focus();
            }
            
            // Form submission with loading state
            loginForm.addEventListener('submit', function(e) {
                if (!loginForm.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                    loginForm.classList.add('was-validated');
                    return;
                }
                
                // Show loading state
                loginBtn.disabled = true;
                loadingOverlay.style.display = 'flex';
                
                const originalContent = loginBtn.innerHTML;
                loginBtn.innerHTML = '<div class="spinner" style="width: 20px; height: 20px; border-width: 2px;"></div><span>Signing in...</span>';
                
                // Re-enable after timeout as fallback
                setTimeout(() => {
                    loginBtn.disabled = false;
                    loginBtn.innerHTML = originalContent;
                    loadingOverlay.style.display = 'none';
                }, 10000);
            });
            
            // Enhanced form validation
            const inputs = document.querySelectorAll('.form-control-modern');
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
                }, 5000);
            });
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'Enter') {
                    loginForm.submit();
                }
            });
            
            // Demo credentials helper (remove in production)
            const emailField = document.querySelector('[name="email"]');
            emailField.addEventListener('dblclick', function() {
                if (confirm('Fill demo admin credentials?')) {
                    this.value = 'admin@swarnandhra.edu';
                    passwordInput.value = 'admin123';
                    passwordInput.focus();
                }
            });
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
        document.querySelectorAll('.form-group, .btn-primary-modern, .auth-links').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });
        
        // Trigger animations after a short delay
        setTimeout(() => {
            document.querySelectorAll('.form-group, .btn-primary-modern, .auth-links').forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }, 200);
    </script>
</body>
</html>