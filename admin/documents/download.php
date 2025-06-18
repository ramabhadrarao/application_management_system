<?php
/**
 * Document Download Handler
 * 
 * File: admin/documents/download.php
 * Purpose: Secure document download handler for admin
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../../config/config.php';
require_once '../../classes/Application.php';
require_once '../../classes/Program.php';

// Require admin login
requireLogin();

$database = new Database();
$db = $database->getConnection();

$application = new Application($db);
$program = new Program($db);

$current_user_role = getCurrentUserRole();
$current_user_id = getCurrentUserId();

// Check permissions
if (!in_array($current_user_role, [ROLE_ADMIN, ROLE_PROGRAM_ADMIN])) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

// Get document ID
$doc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$doc_id) {
    header('HTTP/1.0 404 Not Found');
    exit('Document not found');
}

// Get document details
$query = "
    SELECT ad.*, fu.file_path, fu.original_name, fu.mime_type, fu.file_size,
           a.id as application_id, a.program_id
    FROM application_documents ad
    JOIN file_uploads fu ON ad.file_upload_id = fu.uuid
    JOIN applications a ON ad.application_id = a.id
    WHERE ad.id = :doc_id
";

$stmt = $db->prepare($query);
$stmt->bindParam(':doc_id', $doc_id);
$stmt->execute();
$document = $stmt->fetch();

if (!$document) {
    header('HTTP/1.0 404 Not Found');
    exit('Document not found');
}

// Check if program admin has access
if ($current_user_role === ROLE_PROGRAM_ADMIN) {
    $admin_programs = $program->getProgramsByAdmin($current_user_id);
    $program_ids = array_column($admin_programs, 'id');
    
    if (!in_array($document['program_id'], $program_ids)) {
        header('HTTP/1.0 403 Forbidden');
        exit('Access denied');
    }
}

// Check if file exists
if (!file_exists($document['file_path'])) {
    header('HTTP/1.0 404 Not Found');
    exit('File not found on server');
}

// Log download
$log_query = "INSERT INTO system_logs (user_id, action, table_name, record_id, new_values) 
              VALUES (:user_id, 'DOWNLOAD', 'application_documents', :record_id, :details)";
$stmt = $db->prepare($log_query);
$stmt->bindParam(':user_id', $current_user_id);
$stmt->bindParam(':record_id', $doc_id);
$stmt->bindValue(':details', json_encode([
    'document_id' => $doc_id,
    'application_id' => $document['application_id'],
    'file_name' => $document['original_name']
]));
$stmt->execute();

// Set headers for download
header('Content-Type: ' . $document['mime_type']);
header('Content-Disposition: attachment; filename="' . $document['original_name'] . '"');
header('Content-Length: ' . $document['file_size']);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file
readfile($document['file_path']);
exit;
?>