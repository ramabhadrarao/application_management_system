<?php
/**
 * Users Management - Bulk Actions
 * 
 * File: admin/users/bulk-actions.php
 * Purpose: Handle bulk operations on multiple users
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../../config/config.php';
require_once '../../classes/User.php';

// Set JSON header
header('Content-Type: application/json');

// Require admin login
requireLogin();
requirePermission(ROLE_ADMIN);

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$current_user_role = getCurrentUserRole();
$current_user_id = getCurrentUserId();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request token']);
    exit;
}

// Get action and user IDs
$action = isset($_POST['action']) ? sanitizeInput($_POST['action']) : '';
$user_ids = isset($_POST['user_ids']) ? $_POST['user_ids'] : [];
$send_notification = isset($_POST['send_notification']) ? (bool)$_POST['send_notification'] : false;

// Validate input
if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

if (empty($user_ids) || !is_array($user_ids)) {
    echo json_encode(['success' => false, 'message' => 'No users selected']);
    exit;
}

// Remove current user from selection to prevent self-modification
$user_ids = array_filter($user_ids, function($id) use ($current_user_id) {
    return $id !== $current_user_id;
});

if (empty($user_ids)) {
    echo json_encode(['success' => false, 'message' => 'Cannot perform bulk actions on your own account']);
    exit;
}

$success_count = 0;
$error_count = 0;
$errors = [];
$processed_users = [];

try {
    $db->beginTransaction();
    
    switch ($action) {
        case 'activate':
        case 'deactivate':
            $new_status = ($action === 'activate') ? 1 : 0;
            
            foreach ($user_ids as $user_id) {
                try {
                    // Get user details for logging
                    $user_details = $user->getUserById($user_id);
                    if (!$user_details) {
                        $errors[] = "User not found: $user_id";
                        $error_count++;
                        continue;
                    }
                    
                    // Skip if already in desired state
                    if ($user_details['is_active'] == $new_status) {
                        $processed_users[] = [
                            'id' => $user_id,
                            'email' => $user_details['email'],
                            'action' => 'skipped',
                            'reason' => 'Already ' . ($new_status ? 'active' : 'inactive')
                        ];
                        continue;
                    }
                    
                    // Update user status
                    $query = "UPDATE users SET is_active = :status, date_updated = CURRENT_TIMESTAMP WHERE id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':status', $new_status);
                    $stmt->bindParam(':user_id', $user_id);
                    
                    if ($stmt->execute()) {
                        // Log the action
                        $log_query = "INSERT INTO system_logs (user_id, action, table_name, record_id, old_values, new_values) 
                                      VALUES (:admin_id, :action, 'users', :user_id, :old_values, :new_values)";
                        $stmt = $db->prepare($log_query);
                        $stmt->bindParam(':admin_id', $current_user_id);
                        $stmt->bindParam(':action', strtoupper($action) . '_USER_BULK');
                        $stmt->bindParam(':user_id', $user_id);
                        $stmt->bindParam(':old_values', json_encode(['is_active' => $user_details['is_active']]));
                        $stmt->bindParam(':new_values', json_encode(['is_active' => $new_status]));
                        $stmt->execute();
                        
                        // If deactivating, clear user sessions
                        if ($action === 'deactivate') {
                            $session_query = "DELETE FROM user_sessions WHERE user_id = :user_id";
                            $stmt = $db->prepare($session_query);
                            $stmt->bindParam(':user_id', $user_id);
                            $stmt->execute();
                        }
                        
                        // Send notification if requested
                        if ($send_notification) {
                            $notification_title = $action === 'activate' ? 'Account Activated' : 'Account Deactivated';
                            $notification_message = $action === 'activate' 
                                ? 'Your account has been activated by an administrator. You can now access the system.'
                                : 'Your account has been deactivated by an administrator. Please contact support if you have questions.';
                            
                            $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                                           VALUES (:user_id, :title, :message, :type)";
                            $stmt = $db->prepare($notif_query);
                            $stmt->bindParam(':user_id', $user_id);
                            $stmt->bindParam(':title', $notification_title);
                            $stmt->bindParam(':message', $notification_message);
                            $stmt->bindValue(':type', $action === 'activate' ? 'success' : 'warning');
                            $stmt->execute();
                        }
                        
                        $processed_users[] = [
                            'id' => $user_id,
                            'email' => $user_details['email'],
                            'action' => $action,
                            'status' => 'success'
                        ];
                        $success_count++;
                    } else {
                        $errors[] = "Failed to $action user: " . $user_details['email'];
                        $error_count++;
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Error processing user $user_id: " . $e->getMessage();
                    $error_count++;
                }
            }
            break;
            
        case 'delete':
            foreach ($user_ids as $user_id) {
                try {
                    // Get user details
                    $user_details = $user->getUserById($user_id);
                    if (!$user_details) {
                        $errors[] = "User not found: $user_id";
                        $error_count++;
                        continue;
                    }
                    
                    // Check for applications
                    $check_query = "SELECT COUNT(*) as count FROM applications WHERE user_id = :user_id";
                    $stmt = $db->prepare($check_query);
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->execute();
                    $result = $stmt->fetch();
                    
                    if ($result['count'] > 0) {
                        $errors[] = "Cannot delete " . $user_details['email'] . ": has " . $result['count'] . " applications";
                        $error_count++;
                        continue;
                    }
                    
                    // Check if program admin with assigned programs
                    if ($user_details['role'] === ROLE_PROGRAM_ADMIN) {
                        $check_query = "SELECT COUNT(*) as count FROM programs WHERE program_admin_id = :user_id";
                        $stmt = $db->prepare($check_query);
                        $stmt->bindParam(':user_id', $user_id);
                        $stmt->execute();
                        $result = $stmt->fetch();
                        
                        if ($result['count'] > 0) {
                            $errors[] = "Cannot delete " . $user_details['email'] . ": assigned to " . $result['count'] . " programs";
                            $error_count++;
                            continue;
                        }
                    }
                    
                    // Log the deletion
                    $log_query = "INSERT INTO system_logs (user_id, action, table_name, record_id, old_values) 
                                  VALUES (:admin_id, 'DELETE_USER_BULK', 'users', :user_id, :old_values)";
                    $stmt = $db->prepare($log_query);
                    $stmt->bindParam(':admin_id', $current_user_id);
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->bindParam(':old_values', json_encode($user_details));
                    $stmt->execute();
                    
                    // Delete user sessions
                    $query = "DELETE FROM user_sessions WHERE user_id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->execute();
                    
                    // Delete notifications
                    $query = "DELETE FROM notifications WHERE user_id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->execute();
                    
                    // Delete the user
                    $query = "DELETE FROM users WHERE id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $user_id);
                    
                    if ($stmt->execute()) {
                        $processed_users[] = [
                            'id' => $user_id,
                            'email' => $user_details['email'],
                            'action' => 'deleted',
                            'status' => 'success'
                        ];
                        $success_count++;
                    } else {
                        $errors[] = "Failed to delete user: " . $user_details['email'];
                        $error_count++;
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Error deleting user $user_id: " . $e->getMessage();
                    $error_count++;
                }
            }
            break;
            
        case 'send_password_reset':
            foreach ($user_ids as $user_id) {
                try {
                    // Get user details
                    $user_details = $user->getUserById($user_id);
                    if (!$user_details) {
                        $errors[] = "User not found: $user_id";
                        $error_count++;
                        continue;
                    }
                    
                    // Generate password reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    
                    // Update user with reset token
                    $query = "UPDATE users SET password_reset_token = :token, password_reset_expires = :expires 
                              WHERE id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':token', $reset_token);
                    $stmt->bindParam(':expires', $expires);
                    $stmt->bindParam(':user_id', $user_id);
                    
                    if ($stmt->execute()) {
                        // Send notification
                        $notification_title = 'Password Reset Request';
                        $notification_message = 'A password reset has been initiated for your account by an administrator. Please check your email for reset instructions.';
                        
                        $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                                       VALUES (:user_id, :title, :message, 'info')";
                        $stmt = $db->prepare($notif_query);
                        $stmt->bindParam(':user_id', $user_id);
                        $stmt->bindParam(':title', $notification_title);
                        $stmt->bindParam(':message', $notification_message);
                        $stmt->execute();
                        
                        // Log the action
                        $log_query = "INSERT INTO system_logs (user_id, action, table_name, record_id) 
                                      VALUES (:admin_id, 'PASSWORD_RESET_INITIATED_BULK', 'users', :user_id)";
                        $stmt = $db->prepare($log_query);
                        $stmt->bindParam(':admin_id', $current_user_id);
                        $stmt->bindParam(':user_id', $user_id);
                        $stmt->execute();
                        
                        $processed_users[] = [
                            'id' => $user_id,
                            'email' => $user_details['email'],
                            'action' => 'password_reset_sent',
                            'status' => 'success'
                        ];
                        $success_count++;
                    } else {
                        $errors[] = "Failed to send password reset to: " . $user_details['email'];
                        $error_count++;
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Error sending password reset to user $user_id: " . $e->getMessage();
                    $error_count++;
                }
            }
            break;
            
        case 'change_role':
            $new_role = isset($_POST['new_role']) ? sanitizeInput($_POST['new_role']) : '';
            
            if (empty($new_role) || !in_array($new_role, [ROLE_ADMIN, ROLE_PROGRAM_ADMIN, ROLE_STUDENT])) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Invalid role specified']);
                exit;
            }
            
            foreach ($user_ids as $user_id) {
                try {
                    // Get user details
                    $user_details = $user->getUserById($user_id);
                    if (!$user_details) {
                        $errors[] = "User not found: $user_id";
                        $error_count++;
                        continue;
                    }
                    
                    // Skip if already has this role
                    if ($user_details['role'] === $new_role) {
                        $processed_users[] = [
                            'id' => $user_id,
                            'email' => $user_details['email'],
                            'action' => 'skipped',
                            'reason' => 'Already has role: ' . $new_role
                        ];
                        continue;
                    }
                    
                    // Update user role
                    $query = "UPDATE users SET role = :role, date_updated = CURRENT_TIMESTAMP WHERE id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':role', $new_role);
                    $stmt->bindParam(':user_id', $user_id);
                    
                    if ($stmt->execute()) {
                        // Log the action
                        $log_query = "INSERT INTO system_logs (user_id, action, table_name, record_id, old_values, new_values) 
                                      VALUES (:admin_id, 'CHANGE_ROLE_BULK', 'users', :user_id, :old_values, :new_values)";
                        $stmt = $db->prepare($log_query);
                        $stmt->bindParam(':admin_id', $current_user_id);
                        $stmt->bindParam(':user_id', $user_id);
                        $stmt->bindParam(':old_values', json_encode(['role' => $user_details['role']]));
                        $stmt->bindParam(':new_values', json_encode(['role' => $new_role]));
                        $stmt->execute();
                        
                        // Send notification
                        if ($send_notification) {
                            $notification_title = 'Role Changed';
                            $notification_message = 'Your account role has been changed to: ' . ucwords(str_replace('_', ' ', $new_role));
                            
                            $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                                           VALUES (:user_id, :title, :message, 'info')";
                            $stmt = $db->prepare($notif_query);
                            $stmt->bindParam(':user_id', $user_id);
                            $stmt->bindParam(':title', $notification_title);
                            $stmt->bindParam(':message', $notification_message);
                            $stmt->execute();
                        }
                        
                        $processed_users[] = [
                            'id' => $user_id,
                            'email' => $user_details['email'],
                            'action' => 'role_changed',
                            'old_role' => $user_details['role'],
                            'new_role' => $new_role,
                            'status' => 'success'
                        ];
                        $success_count++;
                    } else {
                        $errors[] = "Failed to change role for: " . $user_details['email'];
                        $error_count++;
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Error changing role for user $user_id: " . $e->getMessage();
                    $error_count++;
                }
            }
            break;
            
        case 'verify_email':
            foreach ($user_ids as $user_id) {
                try {
                    // Get user details
                    $user_details = $user->getUserById($user_id);
                    if (!$user_details) {
                        $errors[] = "User not found: $user_id";
                        $error_count++;
                        continue;
                    }
                    
                    // Skip if already verified
                    if ($user_details['email_verified']) {
                        $processed_users[] = [
                            'id' => $user_id,
                            'email' => $user_details['email'],
                            'action' => 'skipped',
                            'reason' => 'Email already verified'
                        ];
                        continue;
                    }
                    
                    // Verify email
                    $query = "UPDATE users SET email_verified = 1, date_updated = CURRENT_TIMESTAMP WHERE id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $user_id);
                    
                    if ($stmt->execute()) {
                        // Send notification
                        if ($send_notification) {
                            $notification_title = 'Email Verified';
                            $notification_message = 'Your email address has been verified by an administrator.';
                            
                            $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                                           VALUES (:user_id, :title, :message, 'success')";
                            $stmt = $db->prepare($notif_query);
                            $stmt->bindParam(':user_id', $user_id);
                            $stmt->bindParam(':title', $notification_title);
                            $stmt->bindParam(':message', $notification_message);
                            $stmt->execute();
                        }
                        
                        $processed_users[] = [
                            'id' => $user_id,
                            'email' => $user_details['email'],
                            'action' => 'email_verified',
                            'status' => 'success'
                        ];
                        $success_count++;
                    } else {
                        $errors[] = "Failed to verify email for: " . $user_details['email'];
                        $error_count++;
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Error verifying email for user $user_id: " . $e->getMessage();
                    $error_count++;
                }
            }
            break;
            
        default:
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Invalid action specified']);
            exit;
    }
    
    $db->commit();
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => "Bulk operation completed: $success_count successful, $error_count failed",
        'statistics' => [
            'total_selected' => count($user_ids),
            'success_count' => $success_count,
            'error_count' => $error_count,
            'processed_users' => $processed_users
        ]
    ];
    
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    
    // Add action-specific messages
    switch ($action) {
        case 'activate':
            $response['action_message'] = "$success_count users activated successfully";
            break;
        case 'deactivate':
            $response['action_message'] = "$success_count users deactivated successfully";
            break;
        case 'delete':
            $response['action_message'] = "$success_count users deleted successfully";
            break;
        case 'send_password_reset':
            $response['action_message'] = "$success_count password reset emails sent";
            break;
        case 'change_role':
            $response['action_message'] = "$success_count user roles changed";
            break;
        case 'verify_email':
            $response['action_message'] = "$success_count email addresses verified";
            break;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Bulk user operation error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred during bulk operation: ' . $e->getMessage()
    ]);
}
?>