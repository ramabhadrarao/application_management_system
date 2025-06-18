<?php
/**
 * View Application Details Page
 * 
 * File: admin/applications/view.php
 * Purpose: Display complete application details for admin review
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../../config/config.php';
require_once '../../classes/Application.php';
require_once '../../classes/Program.php';
require_once '../../classes/User.php';

// Require admin login
requireLogin();

$database = new Database();
$db = $database->getConnection();

$application = new Application($db);
$program = new Program($db);
$user = new User($db);

$current_user_role = getCurrentUserRole();
$current_user_id = getCurrentUserId();

// Check permissions
if (!in_array($current_user_role, [ROLE_ADMIN, ROLE_PROGRAM_ADMIN])) {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

// Get application ID
$app_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$app_id) {
    $_SESSION['flash_message'] = 'Invalid application ID.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . SITE_URL . '/admin/applications/list.php');
    exit;
}

// Get application details
$app_details = $application->getById($app_id);

if (!$app_details) {
    $_SESSION['flash_message'] = 'Application not found.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . SITE_URL . '/admin/applications/list.php');
    exit;
}

// Check if program admin has access
if ($current_user_role === ROLE_PROGRAM_ADMIN) {
    $admin_programs = $program->getProgramsByAdmin($current_user_id);
    $program_ids = array_column($admin_programs, 'id');
    
    if (!in_array($app_details['program_id'], $program_ids)) {
        $_SESSION['flash_message'] = 'Access denied.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: ' . SITE_URL . '/admin/applications/list.php');
        exit;
    }
}

// Get education details
$edu_query = "
    SELECT ed.*, el.level_name
    FROM application_education_details ed
    JOIN education_levels el ON ed.education_level_id = el.id
    WHERE ed.application_id = :app_id
    ORDER BY el.display_order ASC
";
$stmt = $db->prepare($edu_query);
$stmt->bindParam(':app_id', $app_id);
$stmt->execute();
$education_details = $stmt->fetchAll();

// Get documents
$docs_query = "
    SELECT ad.*, ct.name as certificate_name, fu.original_name, fu.file_size, 
           u.email as verified_by_email
    FROM application_documents ad
    JOIN certificate_types ct ON ad.certificate_type_id = ct.id
    LEFT JOIN file_uploads fu ON ad.file_upload_id = fu.uuid
    LEFT JOIN users u ON ad.verified_by = u.id
    WHERE ad.application_id = :app_id
    ORDER BY ct.display_order ASC
";
$stmt = $db->prepare($docs_query);
$stmt->bindParam(':app_id', $app_id);
$stmt->execute();
$documents = $stmt->fetchAll();

// Get study history
$study_query = "
    SELECT * FROM application_study_history 
    WHERE application_id = :app_id 
    ORDER BY display_order ASC
";
$stmt = $db->prepare($study_query);
$stmt->bindParam(':app_id', $app_id);
$stmt->execute();
$study_history = $stmt->fetchAll();

// Get status history
$status_history = $application->getStatusHistory($app_id);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        $new_status = $_POST['status'];
        $comments = $_POST['comments'] ?? '';
        
        if ($application->updateStatus($app_id, $new_status, $current_user_id, $comments)) {
            $_SESSION['flash_message'] = 'Application status updated successfully.';
            $_SESSION['flash_type'] = 'success';
            header('Location: ' . SITE_URL . '/admin/applications/view.php?id=' . $app_id);
            exit;
        } else {
            $_SESSION['flash_message'] = 'Failed to update application status.';
            $_SESSION['flash_type'] = 'danger';
        }
    }
}

$page_title = 'View Application - ' . $app_details['application_number'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?php echo htmlspecialchars($page_title . ' - ' . SITE_NAME); ?></title>
    
    <!-- CSS files -->
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/css/tabler.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    
    <style>
        :root {
            --primary-color: #0054a6;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .status-badge-large {
            display: inline-block;
            padding: 0.5rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 30px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-draft { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
        .status-submitted { background: rgba(23, 162, 184, 0.1); color: var(--info-color); }
        .status-under_review { background: rgba(255, 193, 7, 0.1); color: var(--warning-color); }
        .status-approved { background: rgba(40, 167, 69, 0.1); color: var(--success-color); }
        .status-rejected { background: rgba(220, 53, 69, 0.1); color: var(--danger-color); }
        .status-frozen { background: rgba(0, 84, 166, 0.1); color: var(--primary-color); }
        
        .detail-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header-custom {
            background: #f8f9fa;
            padding: 1.25rem 1.5rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        .card-title-custom {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .info-row {
            display: flex;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f1f1;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            width: 200px;
            flex-shrink: 0;
        }
        
        .info-value {
            color: #212529;
            flex: 1;
        }
        
        .document-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.2s;
        }
        
        .document-item:hover {
            background-color: #f8f9fa;
        }
        
        .document-item:last-child {
            border-bottom: none;
        }
        
        .document-icon {
            width: 40px;
            height: 40px;
            background: #e3f2fd;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: var(--primary-color);
        }
        
        .document-verified {
            background: #e8f5e9;
            color: var(--success-color);
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .document-details {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 0.5rem;
            width: 12px;
            height: 12px;
            background: var(--primary-color);
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 3px #e9ecef;
        }
        
        .timeline-item::after {
            content: '';
            position: absolute;
            left: -1.125rem;
            top: 1.5rem;
            width: 2px;
            height: calc(100% - 1rem);
            background: #e9ecef;
        }
        
        .timeline-item:last-child::after {
            display: none;
        }
        
        .timeline-content {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
        }
        
        .timeline-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .timeline-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .page-header {
                background: none !important;
                color: black !important;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- Header -->
        <div class="page-header">
            <div class="container-xl">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="page-pretitle">Application Details</div>
                        <h2 class="page-title">
                            <i class="fas fa-file-alt me-2"></i>
                            <?php echo htmlspecialchars($app_details['application_number']); ?>
                        </h2>
                    </div>
                    <div class="col-auto ms-auto">
                        <div class="btn-list no-print">
                            <?php if (in_array($app_details['status'], ['draft', 'frozen'])): ?>
                            <a href="<?php echo SITE_URL; ?>/admin/applications/edit.php?id=<?php echo $app_id; ?>" class="btn btn-primary">
                                <i class="fas fa-edit me-2"></i>Edit Application
                            </a>
                            <?php endif; ?>
                            <a href="<?php echo SITE_URL; ?>/admin/applications/print.php?id=<?php echo $app_id; ?>" class="btn btn-light" target="_blank">
                                <i class="fas fa-print me-2"></i>Print
                            </a>
                            <a href="<?php echo SITE_URL; ?>/admin/documents/verify.php?app_id=<?php echo $app_id; ?>" class="btn btn-light">
                                <i class="fas fa-file-check me-2"></i>Verify Documents
                            </a>
                            <a href="<?php echo SITE_URL; ?>/admin/applications/list.php" class="btn btn-light">
                                <i class="fas fa-arrow-left me-2"></i>Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="page-body">
            <div class="container-xl">
                <!-- Flash Messages -->
                <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_type']; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $_SESSION['flash_type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $_SESSION['flash_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php 
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_type']);
                ?>
                <?php endif; ?>
                
                <!-- Status and Quick Actions -->
                <div class="detail-card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h4 class="mb-3">Application Status</h4>
                                <span class="status-badge-large status-<?php echo $app_details['status']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $app_details['status'])); ?>
                                </span>
                                <?php if ($app_details['submitted_at']): ?>
                                <p class="text-muted mt-3 mb-0">
                                    Submitted on <?php echo formatDateTime($app_details['submitted_at']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <h4 class="mb-3">Quick Actions</h4>
                                <?php if (in_array($app_details['status'], ['submitted', 'under_review', 'frozen'])): ?>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#statusModal">
                                    <i class="fas fa-exchange-alt me-2"></i>Change Status
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-secondary" onclick="window.print()">
                                    <i class="fas fa-print me-2"></i>Print View
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Personal Information -->
                <div class="detail-card">
                    <div class="card-header-custom">
                        <h3 class="card-title-custom">
                            <i class="fas fa-user"></i>
                            Personal Information
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-row">
                                    <div class="info-label">Student Name:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['student_name']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Father's Name:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['father_name']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Mother's Name:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['mother_name']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Date of Birth:</div>
                                    <div class="info-value"><?php echo formatDate($app_details['date_of_birth']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Gender:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['gender']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-row">
                                    <div class="info-label">Mobile Number:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['mobile_number']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Email:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['email']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Aadhar Number:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['aadhar_number'] ?: 'Not Provided'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Parent Mobile:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['parent_mobile'] ?: 'Not Provided'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Guardian Mobile:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['guardian_mobile'] ?: 'Not Provided'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Program Information -->
                <div class="detail-card">
                    <div class="card-header-custom">
                        <h3 class="card-title-custom">
                            <i class="fas fa-graduation-cap"></i>
                            Program Information
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-row">
                                    <div class="info-label">Program Name:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['program_name']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Program Code:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['program_code']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-row">
                                    <div class="info-label">Department:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['department']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Academic Year:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['academic_year']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Address Information -->
                <div class="detail-card">
                    <div class="card-header-custom">
                        <h3 class="card-title-custom">
                            <i class="fas fa-map-marker-alt"></i>
                            Address Information
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-3">Present Address</h5>
                                <div class="info-row">
                                    <div class="info-label">Door No:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['present_door_no'] ?: '-'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Street:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['present_street'] ?: '-'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Village/Town:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['present_village'] ?: '-'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">District:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['present_district'] ?: '-'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Pincode:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['present_pincode'] ?: '-'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5 class="mb-3">Permanent Address</h5>
                                <div class="info-row">
                                    <div class="info-label">Door No:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['permanent_door_no'] ?: '-'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Street:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['permanent_street'] ?: '-'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Village/Town:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['permanent_village'] ?: '-'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">District:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['permanent_district'] ?: '-'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Pincode:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['permanent_pincode'] ?: '-'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Details -->
                <div class="detail-card">
                    <div class="card-header-custom">
                        <h3 class="card-title-custom">
                            <i class="fas fa-info-circle"></i>
                            Additional Details
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-row">
                                    <div class="info-label">Religion:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['religion'] ?: '-'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Caste:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['caste'] ?: '-'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Reservation Category:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['reservation_category']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Physically Handicapped:</div>
                                    <div class="info-value"><?php echo $app_details['is_physically_handicapped'] ? 'Yes' : 'No'; ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-row">
                                    <div class="info-label">Identification Mark 1:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['identification_mark_1'] ?: '-'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Identification Mark 2:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['identification_mark_2'] ?: '-'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Special Reservation:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($app_details['special_reservation'] ?: '-'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Documents -->
                <div class="detail-card">
                    <div class="card-header-custom">
                        <h3 class="card-title-custom">
                            <i class="fas fa-paperclip"></i>
                            Uploaded Documents
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($documents)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No documents uploaded yet</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                        <div class="document-item">
                            <div class="document-icon <?php echo $doc['is_verified'] ? 'document-verified' : ''; ?>">
                                <i class="fas fa-file-<?php echo getFileIcon($doc['original_name']); ?>"></i>
                            </div>
                            <div class="document-info">
                                <div class="document-name"><?php echo htmlspecialchars($doc['certificate_name']); ?></div>
                                <div class="document-details">
                                    <?php echo htmlspecialchars($doc['original_name']); ?> • 
                                    <?php echo number_format($doc['file_size'] / 1024, 2); ?> KB • 
                                    <?php if ($doc['is_verified']): ?>
                                        <span class="text-success">
                                            <i class="fas fa-check-circle"></i> Verified by <?php echo htmlspecialchars($doc['verified_by_email']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-warning">
                                            <i class="fas fa-clock"></i> Pending Verification
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="document-actions">
                                <a href="<?php echo SITE_URL; ?>/admin/documents/download.php?id=<?php echo $doc['id']; ?>" 
                                   class="btn btn-sm btn-light" target="_blank">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Status History -->
                <div class="detail-card">
                    <div class="card-header-custom">
                        <h3 class="card-title-custom">
                            <i class="fas fa-history"></i>
                            Status History
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php foreach ($status_history as $history): ?>
                            <div class="timeline-item">
                                <div class="timeline-content">
                                    <div class="timeline-title">
                                        <?php if ($history['from_status']): ?>
                                            <?php echo ucwords(str_replace('_', ' ', $history['from_status'])); ?> 
                                            <i class="fas fa-arrow-right mx-2"></i>
                                        <?php endif; ?>
                                        <?php echo ucwords(str_replace('_', ' ', $history['to_status'])); ?>
                                    </div>
                                    <?php if ($history['remarks']): ?>
                                    <div class="mb-2"><?php echo htmlspecialchars($history['remarks']); ?></div>
                                    <?php endif; ?>
                                    <div class="timeline-meta">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo formatDateTime($history['date_created']); ?>
                                        <?php if ($history['changed_by_email']): ?>
                                        • <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars($history['changed_by_email']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Application Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="update_status" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select name="status" class="form-select" required>
                                <option value="">Select Status</option>
                                <?php if ($app_details['status'] === 'submitted' || $app_details['status'] === 'frozen'): ?>
                                <option value="under_review">Under Review</option>
                                <?php endif; ?>
                                <?php if (in_array($app_details['status'], ['submitted', 'under_review'])): ?>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <?php endif; ?>
                                <?php if ($app_details['status'] === 'frozen'): ?>
                                <option value="submitted">Unfreeze (Back to Submitted)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Comments/Remarks</label>
                            <textarea name="comments" class="form-control" rows="3" 
                                      placeholder="Enter your comments about this status change..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>

<?php
/**
 * Helper function to get file icon
 */
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf': return 'pdf';
        case 'jpg':
        case 'jpeg':
        case 'png': return 'image';
        default: return 'alt';
    }
}
?>