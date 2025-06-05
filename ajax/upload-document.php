<?php
/**
 * Document Upload Handler
 * 
 * File: ajax/upload-document.php
 * Purpose: Handle document uploads via AJAX
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../config/config.php';
require_once '../classes/User.php';
require_once '../classes/Application.php';

// Require student login
requireLogin();
requirePermission('edit_own_application');

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$application = new Application($db);

$current_user_id = getCurrentUserId();
$response = ['success' => false, 'message' => '', 'file_id' => null];

try {
    // Get user application
    $student_application = $application->getByUserId($current_user_id);
    
    if (!$student_application) {
        throw new Exception('Application not found');
    }

    // Validate POST data
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        throw new Exception('Invalid request token');
    }

    if (!isset($_POST['certificate_type_id']) || empty($_POST['certificate_type_id'])) {
        throw new Exception('Certificate type is required');
    }

    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Please select a valid file to upload');
    }

    $certificate_type_id = (int)sanitizeInput($_POST['certificate_type_id']);
    $file = $_FILES['document'];

    // Validate file
    $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        throw new Exception('Invalid file type. Only PDF, JPG, JPEG, and PNG files are allowed.');
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File size too large. Maximum allowed size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB.');
    }

    // Verify certificate type exists and is valid for this program
    $cert_check_query = "
        SELECT ct.name, pcr.is_required 
        FROM certificate_types ct
        LEFT JOIN program_certificate_requirements pcr ON ct.id = pcr.certificate_type_id 
            AND pcr.program_id = :program_id
        WHERE ct.id = :cert_type_id AND ct.is_active = 1
    ";
    
    $stmt = $db->prepare($cert_check_query);
    $stmt->bindParam(':cert_type_id', $certificate_type_id);
    $stmt->bindParam(':program_id', $student_application['program_id']);
    $stmt->execute();
    $certificate_info = $stmt->fetch();

    if (!$certificate_info) {
        throw new Exception('Invalid certificate type');
    }

    // Create upload directory
    $upload_dir = '../uploads/documents/' . $current_user_id . '/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    // Generate unique filename
    $file_uuid = generateUUID();
    $filename = $file_uuid . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save uploaded file');
    }

    // Start database transaction
    $db->beginTransaction();

    // Insert file record
    $file_insert_query = "
        INSERT INTO file_uploads (
            uuid, filename, original_name, file_path, file_size, 
            mime_type, uploaded_by, upload_date
        ) VALUES (
            :uuid, :filename, :original_name, :file_path, :file_size,
            :mime_type, :uploaded_by, NOW()
        )
    ";

    $stmt = $db->prepare($file_insert_query);
    $stmt->bindParam(':uuid', $file_uuid);
    $stmt->bindParam(':filename', $filename);
    $stmt->bindParam(':original_name', $file['name']);
    $stmt->bindParam(':file_path', $filepath);
    $stmt->bindParam(':file_size', $file['size']);
    $stmt->bindParam(':mime_type', $file['type']);
    $stmt->bindParam(':uploaded_by', $current_user_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to save file information');
    }

    // Check if document already exists for this application and certificate type
    $existing_doc_query = "
        SELECT id FROM application_documents 
        WHERE application_id = :app_id AND certificate_type_id = :cert_type_id
    ";
    
    $stmt = $db->prepare($existing_doc_query);
    $stmt->bindParam(':app_id', $student_application['id']);
    $stmt->bindParam(':cert_type_id', $certificate_type_id);
    $stmt->execute();
    $existing_doc = $stmt->fetch();

    if ($existing_doc) {
        // Update existing document record
        $update_doc_query = "
            UPDATE application_documents 
            SET file_upload_id = :file_id, 
                document_name = :doc_name,
                is_verified = 0,
                verified_by = NULL,
                verified_at = NULL,
                verification_remarks = NULL,
                date_updated = NOW()
            WHERE id = :doc_id
        ";
        
        $stmt = $db->prepare($update_doc_query);
        $stmt->bindParam(':file_id', $file_uuid);
        $stmt->bindParam(':doc_name', $file['name']);
        $stmt->bindParam(':doc_id', $existing_doc['id']);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update document record');
        }
    } else {
        // Insert new document record
        $insert_doc_query = "
            INSERT INTO application_documents (
                application_id, certificate_type_id, file_upload_id, 
                document_name, date_created
            ) VALUES (
                :app_id, :cert_type_id, :file_id, :doc_name, NOW()
            )
        ";
        
        $stmt = $db->prepare($insert_doc_query);
        $stmt->bindParam(':app_id', $student_application['id']);
        $stmt->bindParam(':cert_type_id', $certificate_type_id);
        $stmt->bindParam(':file_id', $file_uuid);
        $stmt->bindParam(':doc_name', $file['name']);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create document record');
        }
    }

    // Commit transaction
    $db->commit();

    $response = [
        'success' => true,
        'message' => 'Document uploaded successfully!',
        'file_id' => $file_uuid,
        'filename' => $file['name'],
        'certificate_name' => $certificate_info['name']
    ];

} catch (Exception $e) {
    // Rollback transaction if active
    if ($db->inTransaction()) {
        $db->rollback();
    }
    
    // Clean up uploaded file if it exists
    if (isset($filepath) && file_exists($filepath)) {
        unlink($filepath);
    }
    
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);

/**
 * Generate UUID for file identification
 */
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
?>