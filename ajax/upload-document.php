<?php
/**
 * AJAX Document Upload Handler (Fixed)
 * 
 * File: ajax/upload-document.php
 * Purpose: Handle document uploads via AJAX with proper error handling
 * Author: Student Application Management System
 * Created: 2025
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in response
ini_set('log_errors', 1);

require_once '../config/config.php';
require_once '../classes/User.php';
require_once '../classes/Application.php';

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Initialize response
$response = ['success' => false, 'message' => '', 'file_id' => null];

try {
    // Check if user is logged in
    if (!isLoggedIn()) {
        throw new Exception('User not authenticated');
    }

    // Require proper permissions
    requirePermission('edit_own_application');

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        throw new Exception('Invalid security token');
    }

    // Validate required POST data
    if (!isset($_POST['certificate_type_id']) || empty($_POST['certificate_type_id'])) {
        throw new Exception('Certificate type is required');
    }

    // Validate file upload
    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        $error_code = $_FILES['document']['error'] ?? UPLOAD_ERR_NO_FILE;
        $error_message = $upload_errors[$error_code] ?? 'Unknown upload error';
        throw new Exception($error_message);
    }

    // Initialize database
    $database = new Database();
    $db = $database->getConnection();
    
    $user = new User($db);
    $application = new Application($db);
    
    $current_user_id = getCurrentUserId();
    $certificate_type_id = (int)sanitizeInput($_POST['certificate_type_id']);
    $file = $_FILES['document'];

    // Get user application
    $student_application = $application->getByUserId($current_user_id);
    if (!$student_application) {
        throw new Exception('Application not found');
    }

    // Validate file type and size
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

    // Create upload directory with proper permissions
    $upload_dir = dirname(__DIR__) . '/uploads/documents/' . $current_user_id . '/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        throw new Exception('Upload directory is not writable');
    }

    // Generate unique filename
    $file_uuid = generateUUID();
    $filename = $file_uuid . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save uploaded file');
    }

    // Verify file was actually moved and exists
    if (!file_exists($filepath)) {
        throw new Exception('File was not saved properly');
    }

    // Start database transaction
    $db->beginTransaction();

    try {
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
            throw new Exception('Failed to save file information to database');
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
            'certificate_name' => $certificate_info['name'],
            'file_size' => $file['size']
        ];

    } catch (Exception $e) {
        // Rollback transaction
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    // Log the error
    error_log("Document upload error: " . $e->getMessage());
    
    // Clean up uploaded file if it exists
    if (isset($filepath) && file_exists($filepath)) {
        unlink($filepath);
    }
    
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// Output JSON response
echo json_encode($response);
exit;
?>