<?php
/**
 * Application Timeline View
 * 
 * File: student/timeline.php
 * Purpose: Detailed timeline view of application progress
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../config/config.php';
require_once '../classes/User.php';
require_once '../classes/Application.php';
require_once '../classes/Program.php';

// Require student login
requireLogin();
requirePermission('view_own_application');

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$application = new Application($db);
$program = new Program($db);

$current_user_id = getCurrentUserId();

// Get user details and application
$user_details = $user->getUserById($current_user_id);
$student_application = $application->getByUserId($current_user_id);

if (!$student_application) {
    header('Location: ' . SITE_URL . '/student/application.php?error=no_application');
    exit;
}

// Get application status history
$status_history = $application->getStatusHistory($student_application['id']);

// Get program details
$user_program = $program->getById($student_application['program_id']);

// Get document upload history
$document_history_query = "
    SELECT 
        ad.date_created as event_date,
        'document_upload' as event_type,
        ct.name as document_name,
        ad.is_verified,
        ad.verified_at,
        verifier.email as verifier_email
    FROM application_documents ad
    JOIN certificate_types ct ON ad.certificate_type_id = ct.id
    LEFT JOIN users verifier ON ad.verified_by = verifier.id
    WHERE ad.application_id = :application_id
    
    UNION ALL
    
    SELECT 
        aed.date_created as event_date,
        'education_added' as event_type,
        CONCAT(el.level_name, ' - ', aed.institution_name) as document_name,
        0 as is_verified,
        NULL as verified_at,
        NULL as verifier_email
    FROM application_education_details aed
    JOIN education_levels el ON aed.education_level_id = el.id
    WHERE aed.application_id = :application_id
    
    ORDER BY event_date DESC
";

$stmt = $db->prepare($document_history_query);
$stmt->bindParam(':application_id', $student_application['id']);
$stmt->execute();
$document_history = $stmt->fetchAll();

// Combine all timeline events
$timeline_events = [];

// Add status history events
foreach ($status_history as $history) {
    $timeline_events[] = [
        'date' => $history['date_created'],
        'type' => 'status_change',
        'title' => 'Status Changed to ' . ucwords(str_replace('_', ' ', $history['to_status'])),
        'description' => $history['remarks'] ?: 'Application status updated',
        'icon' => getStatusIcon($history['to_status']),
        'color' => getStatusColor($history['to_status']),
        'user' => $history['changed_by_email'] ?: 'System',
        'metadata' => [
            'from_status' => $history['from_status'],
            'to_status' => $history['to_status']
        ]
    ];
}

// Add document history events
foreach ($document_history as $doc) {
    if ($doc['event_type'] === 'document_upload') {
        $timeline_events[] = [
            'date' => $doc['event_date'],
            'type' => 'document_upload',
            'title' => 'Document Uploaded',
            'description' => $doc['document_name'],
            'icon' => 'fas fa-file-upload',
            'color' => 'info',
            'user' => 'You',
            'metadata' => [
                'document_name' => $doc['document_name'],
                'is_verified' => $doc['is_verified']
            ]
        ];
        
        if ($doc['is_verified'] && $doc['verified_at']) {
            $timeline_events[] = [
                'date' => $doc['verified_at'],
                'type' => 'document_verified',
                'title' => 'Document Verified',
                'description' => $doc['document_name'] . ' has been verified',
                'icon' => 'fas fa-check-circle',
                'color' => 'success',
                'user' => $doc['verifier_email'] ?: 'Admin',
                'metadata' => [
                    'document_name' => $doc['document_name']
                ]
            ];
        }
    } elseif ($doc['event_type'] === 'education_added') {
        $timeline_events[] = [
            'date' => $doc['event_date'],
            'type' => 'education_added',
            'title' => 'Education Details Added',
            'description' => $doc['document_name'],
            'icon' => 'fas fa-graduation-cap',
            'color' => 'primary',
            'user' => 'You',
            'metadata' => []
        ];
    }
}

// Sort timeline events by date (newest first)
usort($timeline_events, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Status configuration
function getStatusIcon($status) {
    switch ($status) {
        case STATUS_DRAFT: return 'fas fa-edit';
        case STATUS_SUBMITTED: return 'fas fa-paper-plane';
        case STATUS_FROZEN: return 'fas fa-lock';
        case STATUS_UNDER_REVIEW: return 'fas fa-eye';
        case STATUS_APPROVED: return 'fas fa-check-circle';
        case STATUS_REJECTED: return 'fas fa-times-circle';
        default: return 'fas fa-circle';
    }
}

function getStatusColor($status) {
    switch ($status) {
        case STATUS_DRAFT: return 'secondary';
        case STATUS_SUBMITTED: return 'info';
        case STATUS_FROZEN: return 'primary';
        case STATUS_UNDER_REVIEW: return 'warning';
        case STATUS_APPROVED: return 'success';
        case STATUS_REJECTED: return 'danger';
        default: return 'secondary';
    }
}

$page_title = 'Application Timeline';
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
            --secondary-color: #667eea;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --text-dark: #2c3e50;
            --text-light: #6c757d;
            --bg-light: #f8f9fa;
            --white: #ffffff;