<?php
/**
 * Users Management - Delete/Deactivate User
 * 
 * File: admin/users/delete.php
 * Purpose: Delete or deactivate user with validation (AJAX endpoint)
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

// Get user ID and action
$user_id = isset($_POST['user_id']) ? sanitizeInput($_POST['user_id']) : '';
$action = isset($_POST['action']) ? sanitizeInput($_POST['action']) : 'deactivate';

if (empty($user_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

// Prevent self-deletion/deactivation
if ($user_id === $current_user_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot delete or deactivate your own account']);
    exit;
}

try {
    // Get user details first
    $user_details = $user->getUserById($user_id);
    
    if (!$user_details) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    if ($action === 'delete') {
        // Check if user has any applications
        $check_query = "SELECT COUNT(*) as count FROM applications WHERE user_id = :user_id";
        $stmt = $db->prepare($check_query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'Cannot delete user. ' . $result['count'] . ' applications exist for this user. Consider deactivating instead.',
                'application_count' => $result['count'],
                'suggest_deactivate' => true
            ]);
            exit;
        }
        
        // Check if user is a program admin with assigned programs
        if ($user_details['role'] === ROLE_PROGRAM_ADMIN) {
            $check_query = "SELECT COUNT(*) as count FROM programs WHERE program_admin_id = :user_id";
            $stmt = $db->prepare($check_query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Cannot delete user. This program admin is assigned to ' . $result['count'] . ' programs. Please reassign programs first.',
                    'program_count' => $result['count']
                ]);
                exit;
            }
        }
        
        $db->beginTransaction();
        
        // Log the deletion
        $log_query = "INSERT INTO system_logs (user_id, action, table_name, record_id, old_values) 
                      VALUES (:admin_id, 'DELETE_USER', 'users', :user_id, :old_values)";
        $stmt = $db->prepare($log_query);
        $stmt->bindParam(':admin_id', $current_user_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':old_values', json_encode($user_details));
        $stmt->execute();
        
        // Delete user sessions first
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
            $db->commit();
            echo json_encode([
                'success' => true, 
                'message' => 'User "' . $user_details['email'] . '" deleted successfully',
                'user_email' => $user_details['email'],
                'action' => 'deleted'
            ]);
        } else {
            $db->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to delete user. Please try again.'
            ]);
        }
        
    } elseif ($action === 'deactivate' || $action === 'activate') {
        // Toggle user status
        $new_status = ($action === 'activate') ? 1 : 0;
        
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
            $stmt->bindParam(':action', strtoupper($action) . '_USER');
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':old_values', json_encode(['is_active' => $user_details['is_active']]));
            $stmt->bindParam(':new_values', json_encode(['is_active' => $new_status]));
            $stmt->execute();
            
            // If deactivating, also clear user sessions
            if ($action === 'deactivate') {
                $query = "DELETE FROM user_sessions WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'User "' . $user_details['email'] . '" ' . ($action === 'activate' ? 'activated' : 'deactivated') . ' successfully',
                'user_email' => $user_details['email'],
                'action' => $action,
                'new_status' => $new_status
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to ' . $action . ' user. Please try again.'
            ]);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action specified']);
    }
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("User deletion/deactivation error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while processing the request'
    ]);
}
?>