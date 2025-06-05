<?php
/**
 * Programs Management - Delete Program
 * 
 * File: admin/programs/delete.php
 * Purpose: Delete program with validation (AJAX endpoint)
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../../config/config.php';
require_once '../../classes/Program.php';

// Set JSON header
header('Content-Type: application/json');

// Require admin login
requireLogin();
requirePermission('all');

$database = new Database();
$db = $database->getConnection();
$program = new Program($db);

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

// Get program ID
$program_id = isset($_POST['program_id']) ? (int)$_POST['program_id'] : 0;

if (!$program_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid program ID']);
    exit;
}

try {
    // Get program details first
    $program_details = $program->getById($program_id);
    
    if (!$program_details) {
        echo json_encode(['success' => false, 'message' => 'Program not found']);
        exit;
    }
    
    // Check if user has permission to delete this program
    if ($current_user_role === ROLE_PROGRAM_ADMIN) {
        if (!$program->isProgramAdmin($current_user_id, $program_id)) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
    }
    
    // Check if program has any applications
    $check_query = "SELECT COUNT(*) as count FROM applications WHERE program_id = :program_id";
    $stmt = $db->prepare($check_query);
    $stmt->bindParam(':program_id', $program_id);
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot delete program. ' . $result['count'] . ' applications exist for this program.',
            'application_count' => $result['count']
        ]);
        exit;
    }
    
    // Attempt to delete the program
    if ($program->delete($program_id)) {
        // Log the deletion
        $log_query = "INSERT INTO system_logs (user_id, action, table_name, record_id, old_values) 
                      VALUES (:user_id, 'DELETE', 'programs', :record_id, :old_values)";
        $stmt = $db->prepare($log_query);
        $stmt->bindParam(':user_id', $current_user_id);
        $stmt->bindParam(':record_id', $program_id);
        $stmt->bindParam(':old_values', json_encode($program_details));
        $stmt->execute();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Program "' . $program_details['program_name'] . '" deleted successfully',
            'program_name' => $program_details['program_name']
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to delete program. Please try again.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Program deletion error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while deleting the program'
    ]);
}
?>