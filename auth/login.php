<?php
/**
 * User Login Page
 * 
 * File: auth/login.php
 * Purpose: Handle user authentication and login
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
    
    <style>
        :root {
            --tblr-primary: #0054a6;
            --tblr-primary-rgb: 0, 84, 166;
        }
        
        .login-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .card-login {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .brand-logo {
            font-size: 2.5rem;
            color: var(--tblr-primary);
            margin-bottom: 1rem;
        }
        
        .login-form .form-control {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            font-size: 1rem;
        }
        
        .login-form .btn {
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
        
        .features-grid {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            border-radius: 1rem;
            padding: 2rem;
            color: white;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        @media (max-width: 768px) {
            .features-grid {
                margin-top: 2rem;
            }
        }
    </style>
</head>
<body class="d-flex flex-column login-page">
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="row">
                        <!-- Login Form -->
                        <div class="col-lg-6">
                            <div class="card card-login">
                                <div class="card-body p-4">
                                    <!-- Brand -->
                                    <div class="text-center mb-4">
                                        <div class="brand-logo">
                                            <i class="fas fa-graduation-cap"></i>
                                        </div>
                                        <h2 class="h3 text-primary"><?php echo SITE_NAME; ?></h2>
                                        <p class="text-muted">Student Application Management System</p>
                                    </div>
                                    
                                    <!-- Error Message -->
                                    <?php if (!empty($login_error)): ?>
                                    <div class="alert alert-danger alert-dismissible" role="alert">
                                        <div class="d-flex">
                                            <div class="flex-fill">
                                                <i class="fas fa-exclamation-circle me-2"></i>
                                                <?php echo $login_error; ?>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Login Form -->
                                    <form method="POST" class="login-form" autocomplete="off">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        
                                        <div class="mb-3">
                                            <div class="form-floating">
                                                <input type="email" 
                                                       class="form-control" 
                                                       id="email" 
                                                       name="email" 
                                                       placeholder="name@example.com"
                                                       value="<?php echo htmlspecialchars($email); ?>"
                                                       required>
                                                <label for="email">
                                                    <i class="fas fa-envelope me-2"></i>Email Address
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4">
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
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                                <label class="form-check-label" for="remember">
                                                    Remember me
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid mb-3">
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="fas fa-sign-in-alt me-2"></i>Login
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <!-- Additional Links -->
                                    <div class="text-center">
                                        <p class="text-muted mb-2">
                                            <a href="<?php echo SITE_URL; ?>/auth/forgot-password.php" class="text-decoration-none">
                                                Forgot your password?
                                            </a>
                                        </p>
                                        <p class="text-muted">
                                            Don't have an account? 
                                            <a href="<?php echo SITE_URL; ?>/auth/register.php" class="text-primary text-decoration-none">
                                                Register here
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Features/Information -->
                        <div class="col-lg-6 d-none d-lg-block">
                            <div class="features-grid h-100">
                                <h3 class="text-white mb-4">Welcome to <?php echo SITE_NAME; ?></h3>
                                <p class="text-white-50 mb-4">
                                    Our online application system makes it easy to apply for academic programs. 
                                    Manage your application, upload documents, and track your status all in one place.
                                </p>
                                
                                <div class="feature-item">
                                    <div class="feature-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div>
                                        <h5 class="text-white mb-1">Easy Application</h5>
                                        <p class="text-white-50 mb-0">Fill out your application online with our user-friendly form.</p>
                                    </div>
                                </div>
                                
                                <div class="feature-item">
                                    <div class="feature-icon">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>
                                    <div>
                                        <h5 class="text-white mb-1">Document Upload</h5>
                                        <p class="text-white-50 mb-0">Securely upload and manage all required documents.</p>
                                    </div>
                                </div>
                                
                                <div class="feature-item">
                                    <div class="feature-icon">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div>
                                        <h5 class="text-white mb-1">Track Progress</h5>
                                        <p class="text-white-50 mb-0">Monitor your application status in real-time.</p>
                                    </div>
                                </div>
                                
                                <div class="feature-item">
                                    <div class="feature-icon">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                    <div>
                                        <h5 class="text-white mb-1">Secure & Safe</h5>
                                        <p class="text-white-50 mb-0">Your data is protected with industry-standard security.</p>
                                    </div>
                                </div>
                                
                                <div class="mt-4 pt-4 border-top border-white-10">
                                    <p class="text-white-50 mb-2">
                                        <i class="fas fa-phone me-2"></i>
                                        Need help? Call us at <?php echo SUPPORT_PHONE; ?>
                                    </p>
                                    <p class="text-white-50 mb-0">
                                        <i class="fas fa-envelope me-2"></i>
                                        Or email us at <?php echo ADMIN_EMAIL; ?>
                                    </p>
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
        // Auto-hide alerts
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
        
        // Focus on first input
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            if (emailInput && !emailInput.value) {
                emailInput.focus();
            } else {
                const passwordInput = document.getElementById('password');
                if (passwordInput) {
                    passwordInput.focus();
                }
            }
        });
        
        // Form validation
        (function() {
            'use strict';
            
            const form = document.querySelector('.login-form');
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        })();
    </script>
</body>
</html>