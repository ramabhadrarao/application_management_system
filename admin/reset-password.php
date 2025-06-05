<?php
/**
 * Password Reset Utility
 * 
 * File: admin/reset-password.php
 * Purpose: Reset passwords and unlock accounts for admin use
 * Author: Student Application Management System
 * Created: 2025
 */

// This is an admin utility file - use with caution
require_once '../config/config.php';

$message = '';
$message_type = '';

// Database connection
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] == 'reset_password') {
        $email = trim($_POST['email']);
        $new_password = trim($_POST['new_password']);
        
        if (empty($email) || empty($new_password)) {
            $message = 'Email and new password are required.';
            $message_type = 'danger';
        } else {
            try {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password and reset login attempts
                $query = "UPDATE users SET 
                          password = :password, 
                          login_attempts = 0, 
                          locked_until = NULL,
                          date_updated = CURRENT_TIMESTAMP 
                          WHERE email = :email";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(":password", $hashed_password);
                $stmt->bindParam(":email", $email);
                
                if ($stmt->execute() && $stmt->rowCount() > 0) {
                    $message = "Password reset successfully for: $email";
                    $message_type = 'success';
                } else {
                    $message = "User not found: $email";
                    $message_type = 'warning';
                }
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
    
    if ($_POST['action'] == 'unlock_account') {
        $email = trim($_POST['email']);
        
        if (empty($email)) {
            $message = 'Email is required.';
            $message_type = 'danger';
        } else {
            try {
                // Unlock account and reset login attempts
                $query = "UPDATE users SET 
                          login_attempts = 0, 
                          locked_until = NULL,
                          is_active = 1,
                          date_updated = CURRENT_TIMESTAMP 
                          WHERE email = :email";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(":email", $email);
                
                if ($stmt->execute() && $stmt->rowCount() > 0) {
                    $message = "Account unlocked successfully for: $email";
                    $message_type = 'success';
                } else {
                    $message = "User not found: $email";
                    $message_type = 'warning';
                }
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
    
    if ($_POST['action'] == 'create_admin') {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        
        if (empty($email) || empty($password)) {
            $message = 'Email and password are required.';
            $message_type = 'danger';
        } else {
            try {
                // Check if user already exists
                $check_query = "SELECT id FROM users WHERE email = :email";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(":email", $email);
                $check_stmt->execute();
                
                if ($check_stmt->rowCount() > 0) {
                    $message = "User already exists: $email";
                    $message_type = 'warning';
                } else {
                    // Generate UUID
                    $user_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                        mt_rand(0, 0xffff),
                        mt_rand(0, 0x0fff) | 0x4000,
                        mt_rand(0, 0x3fff) | 0x8000,
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                    );
                    
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Create admin user
                    $query = "INSERT INTO users (id, email, password, role, is_active, email_verified) 
                              VALUES (:id, :email, :password, 'admin', 1, 1)";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":id", $user_id);
                    $stmt->bindParam(":email", $email);
                    $stmt->bindParam(":password", $hashed_password);
                    
                    if ($stmt->execute()) {
                        $message = "Admin user created successfully: $email";
                        $message_type = 'success';
                    } else {
                        $message = "Failed to create admin user";
                        $message_type = 'danger';
                    }
                }
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

// Get all users for reference
try {
    $users_query = "SELECT id, email, role, is_active, login_attempts, locked_until, last_login FROM users ORDER BY role, email";
    $users_stmt = $db->prepare($users_query);
    $users_stmt->execute();
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
    $message = "Error fetching users: " . $e->getMessage();
    $message_type = 'danger';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Password Reset Utility - SWARNANDHRA College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        .container {
            padding: 2rem 0;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #0054a6 0%, #667eea 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0054a6 0%, #667eea 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0054a6;
            box-shadow: 0 0 0 0.2rem rgba(0, 84, 166, 0.25);
        }
        .table th {
            background: #f8f9fa;
            border: none;
            font-weight: 600;
        }
        .badge-locked {
            background: #dc3545;
        }
        .badge-active {
            background: #198754;
        }
        .badge-inactive {
            background: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Header -->
                <div class="text-center mb-4">
                    <h1 class="text-white mb-2">
                        <i class="fas fa-tools me-2"></i>
                        Password Reset Utility
                    </h1>
                    <p class="text-white-50">Administrative tool for managing user accounts and passwords</p>
                </div>
                
                <!-- Alert Messages -->
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Reset Password -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-key me-2"></i>Reset Password
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="reset_password">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" class="form-control" name="email" required 
                                               placeholder="Enter user email">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" class="form-control" name="new_password" required 
                                               placeholder="Enter new password" minlength="6">
                                        <div class="form-text">Minimum 6 characters</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-sync-alt me-2"></i>Reset Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Unlock Account -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-unlock me-2"></i>Unlock Account
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="unlock_account">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" class="form-control" name="email" required 
                                               placeholder="Enter user email">
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <small>
                                            <i class="fas fa-info-circle me-1"></i>
                                            This will reset login attempts and unlock the account
                                        </small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-warning w-100">
                                        <i class="fas fa-unlock-alt me-2"></i>Unlock Account
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Create Admin -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-shield me-2"></i>Create Admin User
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="row g-3">
                                    <input type="hidden" name="action" value="create_admin">
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Admin Email</label>
                                        <input type="email" class="form-control" name="email" required 
                                               placeholder="admin@example.com">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Admin Password</label>
                                        <input type="password" class="form-control" name="password" required 
                                               placeholder="Strong password" minlength="8">
                                    </div>
                                    
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-user-plus me-2"></i>Create Admin User
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Current Users -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>Current Users
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Login Attempts</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['locked_until'] && strtotime($user['locked_until']) > time()): ?>
                                                <span class="badge badge-locked">Locked</span>
                                            <?php elseif ($user['is_active']): ?>
                                                <span class="badge badge-active">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-inactive">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['login_attempts'] > 0): ?>
                                                <span class="badge bg-warning"><?php echo $user['login_attempts']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="fillEmail('<?php echo htmlspecialchars($user['email']); ?>')">
                                                Quick Reset
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Default Credentials -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Default Credentials
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <h6>Admin Account:</h6>
                                        <ul class="list-unstyled">
                                            <li><strong>Email:</strong> admin@swarnandhra.edu</li>
                                            <li><strong>Password:</strong> admin123</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-4">
                                        <h6>Program Admin:</h6>
                                        <ul class="list-unstyled">
                                            <li><strong>Email:</strong> bca.admin@swarnandhra.edu</li>
                                            <li><strong>Password:</strong> admin123</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-4">
                                        <h6>Student Account:</h6>
                                        <ul class="list-unstyled">
                                            <li><strong>Email:</strong> student1@example.com</li>
                                            <li><strong>Password:</strong> student123</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Back to Login -->
                <div class="text-center mt-4">
                    <a href="../auth/login.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i>Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fillEmail(email) {
            // Fill email in reset password form
            document.querySelector('input[name="email"]').value = email;
            document.querySelector('input[name="new_password"]').focus();
        }
        
        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>