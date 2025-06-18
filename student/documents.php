<?php
/**
 * Student Documents Upload & Management (Fixed & Enhanced)
 * 
 * File: student/documents.php
 * Purpose: Upload and manage required documents with working backend
 * Author: Student Application Management System
 * Created: 2025
 * FIXED: Now shows only program-specific document requirements
 */

require_once '../config/config.php';
require_once '../classes/User.php';
require_once '../classes/Application.php';
require_once '../classes/Program.php';

// Require student login
requireLogin();
requirePermission('edit_own_application');

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

// Debug: Check upload directory
$debug_info = [];
$upload_base_dir = dirname(__DIR__) . '/uploads/';
$upload_documents_dir = $upload_base_dir . 'documents/';
$user_upload_dir = $upload_documents_dir . $current_user_id . '/';

$debug_info['upload_base_exists'] = is_dir($upload_base_dir);
$debug_info['upload_base_writable'] = is_writable($upload_base_dir);
$debug_info['upload_documents_exists'] = is_dir($upload_documents_dir);
$debug_info['upload_documents_writable'] = is_writable($upload_documents_dir);
$debug_info['user_upload_exists'] = is_dir($user_upload_dir);
$debug_info['user_upload_writable'] = is_writable($user_upload_dir);

// Create directories if they don't exist
if (!is_dir($upload_base_dir)) {
    mkdir($upload_base_dir, 0755, true);
}
if (!is_dir($upload_documents_dir)) {
    mkdir($upload_documents_dir, 0755, true);
}
if (!is_dir($user_upload_dir)) {
    mkdir($user_upload_dir, 0755, true);
}

// FIXED: Get program-specific requirements with upload status
$requirements_query = "
    SELECT 
        pcr.id as requirement_id,
        pcr.certificate_type_id,
        ct.name as certificate_name,
        ct.description,
        ct.file_types_allowed,
        ct.max_file_size_mb,
        pcr.is_required,
        pcr.special_instructions,
        pcr.display_order,
        ad.id as document_id,
        ad.document_name as uploaded_filename,
        ad.is_verified,
        ad.date_created as upload_date,
        ad.verification_remarks,
        fu.original_name,
        fu.file_size,
        fu.uuid as file_uuid,
        fu.mime_type,
        fu.upload_date as file_upload_date
    FROM program_certificate_requirements pcr
    JOIN certificate_types ct ON pcr.certificate_type_id = ct.id
    LEFT JOIN application_documents ad ON pcr.certificate_type_id = ad.certificate_type_id 
                                        AND ad.application_id = :application_id
    LEFT JOIN file_uploads fu ON ad.file_upload_id = fu.uuid
    WHERE pcr.program_id = :program_id
    AND ct.is_active = 1
    ORDER BY pcr.display_order ASC, ct.name ASC
";

$stmt = $db->prepare($requirements_query);
$stmt->bindParam(':application_id', $student_application['id']);
$stmt->bindParam(':program_id', $student_application['program_id']);
$stmt->execute();
$requirements = $stmt->fetchAll();

// Get program details
$user_program = $program->getById($student_application['program_id']);

// Count statistics
$total_required = 0;
$total_uploaded = 0;
$total_verified = 0;
$total_optional = 0;

foreach ($requirements as $req) {
    if ($req['is_required']) {
        $total_required++;
        if ($req['document_id']) {
            $total_uploaded++;
            if ($req['is_verified']) {
                $total_verified++;
            }
        }
    } else {
        $total_optional++;
    }
}

$page_title = 'Document Upload';
$page_subtitle = 'Upload required documents for ' . htmlspecialchars($user_program['program_name']);
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
        /* Enhanced CSS with better visual feedback */
        :root {
            --primary-color: #0054a6;
            --primary-dark: #003d7a;
            --secondary-color: #667eea;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --text-dark: #2c3e50;
            --text-light: #6c757d;
            --bg-light: #f8f9fa;
            --white: #ffffff;
            --border-color: #e9ecef;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.15);
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
        }
        
        /* Header */
        .page-header-modern {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .page-header-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 50%, rgba(255,255,255,0.1) 0%, transparent 50%);
        }
        
        .header-content {
            position: relative;
            z-index: 2;
        }
        
        /* Stats Section */
        .stats-section {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 2rem;
            margin: -3rem auto 2rem;
            position: relative;
            z-index: 3;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Program Badge */
        .program-badge {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            padding: 0.5rem 1.5rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        /* Document Cards */
        .document-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .document-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }
        
        .document-card.uploaded {
            border-color: var(--success-color);
            background: rgba(40, 167, 69, 0.02);
        }
        
        .document-card.verified {
            border-color: var(--primary-color);
            background: rgba(0, 84, 166, 0.02);
        }
        
        .document-header {
            background: var(--bg-light);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }
        
        .document-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }
        
        .document-description {
            color: var(--text-light);
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }
        
        .special-instructions {
            background: rgba(23, 162, 184, 0.1);
            border-left: 3px solid var(--info-color);
            padding: 0.75rem;
            margin-top: 0.75rem;
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
            font-size: 0.85rem;
            color: var(--info-color);
        }
        
        .document-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: absolute;
            top: 1rem;
            right: 1rem;
        }
        
        .badge-required {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .badge-optional {
            background: rgba(108, 117, 125, 0.1);
            color: var(--text-light);
        }
        
        .badge-uploaded {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .badge-verified {
            background: rgba(0, 84, 166, 0.1);
            color: var(--primary-color);
        }
        
        .document-body {
            padding: 1.5rem;
        }
        
        /* File Upload Area */
        .upload-area {
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            background: var(--white);
        }
        
        .upload-area:hover, .upload-area.dragover {
            border-color: var(--primary-color);
            background: rgba(0, 84, 166, 0.05);
        }
        
        .upload-area.uploading {
            border-color: var(--info-color);
            background: rgba(23, 162, 184, 0.05);
            pointer-events: none;
        }
        
        .upload-icon {
            font-size: 3rem;
            color: var(--text-light);
            margin-bottom: 1rem;
            transition: color 0.3s ease;
        }
        
        .upload-area:hover .upload-icon,
        .upload-area.dragover .upload-icon {
            color: var(--primary-color);
        }
        
        .upload-text {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .upload-subtext {
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .file-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        /* File Preview */
        .file-preview {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: var(--bg-light);
            border-radius: var(--radius-md);
            margin-top: 1rem;
        }
        
        .file-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            color: white;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .file-meta {
            color: var(--text-light);
            font-size: 0.85rem;
        }
        
        .file-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Buttons */
        .btn-modern {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
        }
        
        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
        }
        
        .btn-success-modern {
            background: linear-gradient(135deg, var(--success-color), #20c997);
            color: white;
        }
        
        .btn-danger-modern {
            background: linear-gradient(135deg, var(--danger-color), #e74c3c);
            color: white;
        }
        
        .btn-outline-modern {
            background: transparent;
            border: 2px solid var(--border-color);
            color: var(--text-dark);
        }
        
        .btn-outline-modern:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: rgba(0, 84, 166, 0.05);
            text-decoration: none;
        }
        
        /* Progress Bar */
        .upload-progress {
            margin-top: 1rem;
            display: none;
        }
        
        .progress-bar-modern {
            height: 8px;
            background: var(--border-color);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 4px;
            transition: width 0.3s ease;
            width: 0%;
        }
        
        .progress-text {
            font-size: 0.85rem;
            color: var(--text-light);
            text-align: center;
        }
        
        /* Alert Messages */
        .alert-modern {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.95rem;
            position: relative;
            animation: slideDown 0.3s ease;
        }
        
        .alert-success-modern {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            color: var(--success-color);
        }
        
        .alert-danger-modern {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: var(--danger-color);
        }
        
        .alert-info-modern {
            background: rgba(23, 162, 184, 0.1);
            border: 1px solid rgba(23, 162, 184, 0.2);
            color: var(--info-color);
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            border-radius: var(--radius-lg);
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Verification status */
        .verification-status {
            background: rgba(0, 84, 166, 0.05);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .verification-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.5rem;
        }
        
        .verification-icon.verified {
            background: var(--success-color);
            color: white;
        }
        
        .verification-icon.pending {
            background: var(--warning-color);
            color: white;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-light);
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .stats-section {
                margin-top: -2rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Header -->
        <div class="page-header-modern">
            <div class="container-xl">
                <div class="header-content">
                    <!-- Breadcrumb -->
                    <nav class="breadcrumb-modern mb-3" style="background: rgba(255, 255, 255, 0.1); border-radius: var(--radius-md); padding: 0.75rem 1rem;">
                        <a href="<?php echo SITE_URL; ?>/dashboard.php" style="color: rgba(255, 255, 255, 0.8); text-decoration: none;">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                        <span class="mx-2">/</span>
                        <span>Documents</span>
                    </nav>
                    
                    <!-- Program Badge -->
                    <div class="program-badge">
                        <i class="fas fa-graduation-cap"></i>
                        <?php echo htmlspecialchars($user_program['program_name']); ?>
                    </div>
                    
                    <div class="row align-items-center">
                        <div class="col">
                            <h1 class="h2 mb-2"><?php echo $page_title; ?></h1>
                            <p class="mb-0 opacity-75"><?php echo $page_subtitle; ?></p>
                        </div>
                        <div class="col-auto">
                            <div class="text-end">
                                <div class="h4 mb-0">Application #<?php echo htmlspecialchars($student_application['application_number']); ?></div>
                                <small class="opacity-75">Upload all required documents</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="page-body">
            <div class="container-xl">
                <!-- Statistics Section -->
                <div class="stats-section">
                    <div class="row">
                        <div class="col-md-3 col-6">
                            <div class="stat-item">
                                <div class="stat-number text-primary"><?php echo $total_required; ?></div>
                                <div class="stat-label">Required Documents</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-item">
                                <div class="stat-number text-success"><?php echo $total_uploaded; ?></div>
                                <div class="stat-label">Uploaded</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-item">
                                <div class="stat-number text-info"><?php echo $total_verified; ?></div>
                                <div class="stat-label">Verified</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-item">
                                <div class="stat-number text-secondary"><?php echo $total_optional; ?></div>
                                <div class="stat-label">Optional</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Global Alert Container -->
                <div id="alertContainer"></div>
                
                <!-- Global Loading Overlay -->
                <div class="loading-overlay" id="globalLoading">
                    <div class="spinner"></div>
                </div>
                
                <!-- Document Requirements Overview -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-list-check text-primary me-2"></i>
                            Document Requirements for <?php echo htmlspecialchars($user_program['program_name']); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <p class="text-muted mb-3">
                                    Please upload all required documents for your <strong><?php echo htmlspecialchars($user_program['program_name']); ?></strong> application. 
                                    Ensure that all documents are clear, legible, and in the correct format.
                                </p>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="bg-light p-3 rounded">
                                    <h6><i class="fas fa-info-circle text-info me-2"></i>Upload Guidelines</h6>
                                    <ul class="list-unstyled mb-0 small">
                                        <li class="mb-1">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Maximum file size: <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?>MB
                                        </li>
                                        <li class="mb-1">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Supported formats: PDF, JPG, PNG
                                        </li>
                                        <li class="mb-1">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Ensure documents are clear and legible
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Document Upload Cards -->
                <?php if (empty($requirements)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <h4>No Document Requirements Found</h4>
                    <p>No documents are required for your program at this time.</p>
                    <a href="<?php echo SITE_URL; ?>/dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
                <?php else: ?>
                
                <div class="row">
                    <?php foreach ($requirements as $req): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="document-card <?php echo $req['document_id'] ? ($req['is_verified'] ? 'verified' : 'uploaded') : ''; ?>" 
                             data-cert-id="<?php echo $req['certificate_type_id']; ?>">
                             
                            <!-- Loading overlay for individual cards -->
                            <div class="loading-overlay card-loading" id="loading-<?php echo $req['certificate_type_id']; ?>">
                                <div class="spinner"></div>
                            </div>
                            
                            <div class="document-header">
                                <div class="document-title">
                                    <?php echo htmlspecialchars($req['certificate_name']); ?>
                                </div>
                                <?php if ($req['description']): ?>
                                <div class="document-description">
                                    <?php echo htmlspecialchars($req['description']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($req['special_instructions']): ?>
                                <div class="special-instructions">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <?php echo htmlspecialchars($req['special_instructions']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="document-badge <?php 
                                    if ($req['document_id']) {
                                        echo $req['is_verified'] ? 'badge-verified' : 'badge-uploaded';
                                    } else {
                                        echo $req['is_required'] ? 'badge-required' : 'badge-optional';
                                    }
                                ?>">
                                    <i class="fas fa-<?php 
                                        if ($req['document_id']) {
                                            echo $req['is_verified'] ? 'check-circle' : 'clock';
                                        } else {
                                            echo $req['is_required'] ? 'asterisk' : 'circle';
                                        }
                                    ?>"></i>
                                    <?php 
                                        if ($req['document_id']) {
                                            echo $req['is_verified'] ? 'Verified' : 'Uploaded';
                                        } else {
                                            echo $req['is_required'] ? 'Required' : 'Optional';
                                        }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="document-body">
                                <?php if ($req['document_id']): ?>
                                <!-- Existing document display -->
                                <div class="file-preview">
                                    <div class="file-icon" style="background: <?php echo $req['is_verified'] ? 'var(--primary-color)' : 'var(--success-color)'; ?>;">
                                        <i class="fas fa-<?php 
                                            $extension = $req['original_name'] ? strtolower(pathinfo($req['original_name'], PATHINFO_EXTENSION)) : 'file';
                                            echo ($extension === 'pdf') ? 'file-pdf' : 'file-image';
                                        ?>"></i>
                                    </div>
                                    <div class="file-info">
                                        <div class="file-name"><?php echo htmlspecialchars($req['original_name'] ?: $req['uploaded_filename']); ?></div>
                                        <div class="file-meta">
                                            <?php if ($req['file_size']): ?>
                                            <?php echo number_format($req['file_size'] / 1024, 1); ?> KB • 
                                            <?php endif; ?>
                                            Uploaded on <?php echo formatDateTime($req['upload_date']); ?>
                                        </div>
                                    </div>
                                    <div class="file-actions">
                                        <button type="button" class="btn-modern btn-outline-modern" onclick="viewDocument('<?php echo $req['file_uuid']; ?>')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn-modern btn-outline-modern" onclick="downloadDocument('<?php echo $req['file_uuid']; ?>')">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button type="button" class="btn-modern btn-danger-modern" onclick="replaceDocument(<?php echo $req['certificate_type_id']; ?>)">
                                            <i class="fas fa-redo"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <?php if ($req['is_verified']): ?>
                                <div class="verification-status">
                                    <div class="d-flex align-items-center">
                                        <div class="verification-icon verified">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div>
                                            <strong>Document Verified</strong><br>
                                            <small class="text-muted">Verified on <?php echo formatDateTime($req['verified_at'] ?: $req['date_updated']); ?></small>
                                        </div>
                                    </div>
                                    <?php if ($req['verification_remarks']): ?>
                                    <div class="mt-2 p-2 bg-light rounded">
                                        <small><strong>Remarks:</strong> <?php echo htmlspecialchars($req['verification_remarks']); ?></small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php else: ?>
                                <!-- Upload form -->
                                <div class="upload-form">
                                    <div class="upload-area" onclick="triggerFileInput(this)" data-cert-id="<?php echo $req['certificate_type_id']; ?>">
                                        <input type="file" 
                                               class="file-input" 
                                               accept=".pdf,.jpg,.jpeg,.png"
                                               onchange="handleFileSelect(this)"
                                               data-cert-id="<?php echo $req['certificate_type_id']; ?>">
                                        <div class="upload-content">
                                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                            <div class="upload-text">Click to upload or drag & drop</div>
                                            <div class="upload-subtext">
                                                <?php 
                                                $allowed = $req['file_types_allowed'] ?: 'pdf,jpg,jpeg,png';
                                                $max_size = $req['max_file_size_mb'] ?: (MAX_FILE_SIZE / 1024 / 1024);
                                                echo strtoupper(str_replace(',', ', ', $allowed)) . ' up to ' . $max_size . 'MB';
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="upload-progress" id="progress-<?php echo $req['certificate_type_id']; ?>">
                                        <div class="progress-bar-modern">
                                            <div class="progress-fill"></div>
                                        </div>
                                        <div class="progress-text">Uploading...</div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="<?php echo SITE_URL; ?>/student/application.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Application
                            </a>
                            
                            <div class="text-center">
                                <small class="text-muted">All required documents must be uploaded before submission</small>
                            </div>
                            
                            <a href="<?php echo SITE_URL; ?>/student/document-verify.php" class="btn btn-primary">
                                <i class="fas fa-check-circle me-2"></i>Check Verification Status
                            </a>
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
        // Global configuration
        const uploadConfig = {
            maxFileSize: <?php echo MAX_FILE_SIZE; ?>,
            allowedTypes: <?php echo json_encode(ALLOWED_FILE_TYPES); ?>,
            csrfToken: '<?php echo generateCSRFToken(); ?>',
            uploadUrl: '<?php echo SITE_URL; ?>/ajax/upload-document.php'
        };
        
        // File upload functionality
        function triggerFileInput(uploadArea) {
            const fileInput = uploadArea.querySelector('.file-input');
            if (fileInput) {
                fileInput.click();
            }
        }
        
        function handleFileSelect(input) {
            const file = input.files[0];
            if (!file) return;
            
            const certificateId = input.getAttribute('data-cert-id');
            
            console.log('File selected:', file.name, 'Size:', file.size, 'Type:', file.type);
            console.log('Certificate ID:', certificateId);
            
            // Validate file
            if (!validateFile(file)) {
                input.value = '';
                return;
            }
            
            // Find upload area and upload file
            const uploadArea = input.closest('.upload-area');
            uploadDocument(file, certificateId, uploadArea);
        }
        
        function uploadDocument(file, certificateId, uploadArea) {
            console.log('Starting upload for certificate ID:', certificateId);
            
            const formData = new FormData();
            formData.append('document', file);
            formData.append('certificate_type_id', certificateId);
            formData.append('csrf_token', uploadConfig.csrfToken);
            
            const progressDiv = document.getElementById('progress-' + certificateId);
            const progressFill = progressDiv?.querySelector('.progress-fill');
            const progressText = progressDiv?.querySelector('.progress-text');
            const cardLoading = document.getElementById('loading-' + certificateId);
            
            // Show progress
            uploadArea.classList.add('uploading');
            if (progressDiv) progressDiv.style.display = 'block';
            if (cardLoading) cardLoading.style.display = 'flex';
            
            // Create XMLHttpRequest for progress tracking
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable && progressFill && progressText) {
                    const progress = (e.loaded / e.total) * 100;
                    progressFill.style.width = progress + '%';
                    progressText.textContent = `Uploading... ${Math.round(progress)}%`;
                }
            });
            
            xhr.addEventListener('load', function() {
                console.log('Upload response status:', xhr.status);
                console.log('Upload response:', xhr.responseText);
                
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        console.log('Parsed response:', response);
                        
                        if (response.success) {
                            showAlert('Document uploaded successfully! The page will refresh to show your uploaded document.', 'success');
                            
                            // Reload the page to update the UI
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        } else {
                            showAlert(response.message || 'Upload failed', 'danger');
                            resetUploadArea(uploadArea, progressDiv, cardLoading);
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        showAlert('Error processing upload response: ' + e.message, 'danger');
                        resetUploadArea(uploadArea, progressDiv, cardLoading);
                    }
                } else {
                    console.error('HTTP error:', xhr.status, xhr.statusText);
                    showAlert(`Upload failed. Server error: ${xhr.status} ${xhr.statusText}`, 'danger');
                    resetUploadArea(uploadArea, progressDiv, cardLoading);
                }
            });
            
            xhr.addEventListener('error', function() {
                console.error('Network error during upload');
                showAlert('Upload failed. Please check your internet connection and try again.', 'danger');
                resetUploadArea(uploadArea, progressDiv, cardLoading);
            });
            
            xhr.addEventListener('timeout', function() {
                console.error('Upload timeout');
                showAlert('Upload timed out. Please try again with a smaller file.', 'danger');
                resetUploadArea(uploadArea, progressDiv, cardLoading);
            });
            
            xhr.timeout = 60000; // 60 second timeout
            xhr.open('POST', uploadConfig.uploadUrl);
            xhr.send(formData);
        }
        
        function resetUploadArea(uploadArea, progressDiv, cardLoading) {
            uploadArea.classList.remove('uploading');
            if (progressDiv) progressDiv.style.display = 'none';
            if (cardLoading) cardLoading.style.display = 'none';
            
            const fileInput = uploadArea.querySelector('.file-input');
            if (fileInput) fileInput.value = '';
        }
        
        function validateFile(file) {
            console.log('Validating file:', file.name, file.size, file.type);
            
            const extension = file.name.split('.').pop().toLowerCase();
            
            if (!uploadConfig.allowedTypes.includes(extension)) {
                showAlert(`Invalid file type. Only ${uploadConfig.allowedTypes.join(', ').toUpperCase()} files are allowed.`, 'danger');
                return false;
            }
            
            if (file.size > uploadConfig.maxFileSize) {
                showAlert(`File size too large. Maximum allowed size is ${(uploadConfig.maxFileSize / 1024 / 1024)}MB.`, 'danger');
                return false;
            }
            
            return true;
        }
        
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alertId = 'alert-' + Date.now();
            const alertHtml = `
                <div class="alert-modern alert-${type}-modern" id="${alertId}">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'}"></i>
                    <span>${message}</span>
                    <button type="button" class="btn-close position-absolute top-0 end-0 p-3" onclick="document.getElementById('${alertId}').remove()"></button>
                </div>
            `;
            
            alertContainer.innerHTML = alertHtml;
            
            // Auto remove after 8 seconds
            setTimeout(() => {
                const alert = document.getElementById(alertId);
                if (alert) {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }
            }, 8000);
        }
        
        function viewDocument(fileUuid) {
            if (fileUuid) {
                window.open('<?php echo SITE_URL; ?>/view-document.php?file=' + encodeURIComponent(fileUuid), '_blank');
            } else {
                showAlert('Document not available for viewing', 'danger');
            }
        }
        
        function downloadDocument(fileUuid) {
            if (fileUuid) {
                window.location.href = '<?php echo SITE_URL; ?>/download-document.php?file=' + encodeURIComponent(fileUuid);
            } else {
                showAlert('Document not available for download', 'danger');
            }
        }
        
        function replaceDocument(certificateId) {
            if (confirm('Are you sure you want to replace this document? The current document will be removed.')) {
                // Create a hidden file input
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = '.pdf,.jpg,.jpeg,.png';
                input.onchange = function() {
                    const file = this.files[0];
                    if (file && validateFile(file)) {
                        // Find the document card for this certificate
                        const documentCard = document.querySelector(`[data-cert-id="${certificateId}"]`);
                        const uploadArea = documentCard?.querySelector('.upload-area') || documentCard;
                        if (uploadArea) {
                            uploadDocument(file, certificateId, uploadArea);
                        }
                    }
                };
                input.click();
            }
        }
        
        // Drag and drop functionality
        document.addEventListener('DOMContentLoaded', function() {
            const uploadAreas = document.querySelectorAll('.upload-area');
            
            uploadAreas.forEach(area => {
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    area.addEventListener(eventName, preventDefaults, false);
                });
                
                ['dragenter', 'dragover'].forEach(eventName => {
                    area.addEventListener(eventName, () => area.classList.add('dragover'), false);
                });
                
                ['dragleave', 'drop'].forEach(eventName => {
                    area.addEventListener(eventName, () => area.classList.remove('dragover'), false);
                });
                
                area.addEventListener('drop', handleDrop, false);
            });
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        function handleDrop(e) {
            const files = e.dataTransfer.files;
            const uploadArea = e.target.closest('.upload-area');
            const certificateId = uploadArea.getAttribute('data-cert-id');
            
            if (files.length > 0) {
                const file = files[0];
                if (validateFile(file)) {
                    uploadDocument(file, certificateId, uploadArea);
                }
            }
        }
        
        // Debug functionality
        function toggleDebug() {
            const panel = document.getElementById('debugPanel');
            if (panel) {
                panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
            }
        }
        
        // Test upload configuration on page load
        console.log('Upload configuration:', uploadConfig);
        console.log('Program-specific certificates loaded:', <?php echo count($requirements); ?>);
    </script>
</body>
</html>