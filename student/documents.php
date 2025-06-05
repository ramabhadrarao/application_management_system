<?php
/**
 * Student Documents Upload & Management
 * 
 * File: student/documents.php
 * Purpose: Upload and manage required documents with modern UI
 * Author: Student Application Management System
 * Created: 2025
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
$errors = [];
$success_message = '';

// Get user details and application
$user_details = $user->getUserById($current_user_id);
$student_application = $application->getByUserId($current_user_id);

if (!$student_application) {
    header('Location: ' . SITE_URL . '/student/application.php?error=no_application');
    exit;
}

// Get program requirements
$program_requirements = $program->getCertificateRequirements($student_application['program_id']);

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        $certificate_type_id = sanitizeInput($_POST['certificate_type_id']);
        
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['document'];
            
            // Validate file type
            $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_extension, $allowed_types)) {
                $errors[] = 'Invalid file type. Only PDF, JPG, JPEG, and PNG files are allowed.';
            } elseif ($file['size'] > MAX_FILE_SIZE) {
                $errors[] = 'File size too large. Maximum allowed size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB.';
            } else {
                // Create upload directory if it doesn't exist
                $upload_dir = '../' . UPLOAD_DOCUMENTS_PATH . $current_user_id . '/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Generate unique filename
                $filename = uniqid() . '_' . time() . '.' . $file_extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Store file info in database (you'll need to create this functionality)
                    $success_message = 'Document uploaded successfully!';
                } else {
                    $errors[] = 'Failed to upload file. Please try again.';
                }
            }
        } else {
            $errors[] = 'Please select a file to upload.';
        }
    } else {
        $errors[] = 'Invalid request. Please try again.';
    }
}

$page_title = 'Document Upload';
$page_subtitle = 'Upload required documents for your application';
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
        
        /* Navigation Breadcrumb */
        .breadcrumb-modern {
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-md);
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
        }
        
        .breadcrumb-modern a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }
        
        .breadcrumb-modern a:hover {
            color: white;
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
        }
        
        .document-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }
        
        .document-header {
            background: var(--bg-light);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
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
        }
        
        .upload-area:hover, .upload-area.dragover {
            border-color: var(--primary-color);
            background: rgba(0, 84, 166, 0.05);
        }
        
        .upload-area.has-file {
            border-color: var(--success-color);
            background: rgba(40, 167, 69, 0.05);
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
        
        .upload-area.has-file .upload-icon {
            color: var(--success-color);
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
        
        .file-size {
            color: var(--text-light);
            font-size: 0.85rem;
        }
        
        .file-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Buttons */
        .btn-modern {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
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
        
        /* Requirements Panel */
        .requirements-panel {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .requirements-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .requirement-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .requirement-item:last-child {
            border-bottom: none;
        }
        
        .requirement-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 0.8rem;
        }
        
        .requirement-content {
            flex: 1;
        }
        
        .requirement-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .requirement-note {
            color: var(--text-light);
            font-size: 0.85rem;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .page-header-modern {
                padding: 1.5rem 0;
            }
            
            .document-card {
                margin-bottom: 1rem;
            }
            
            .upload-area {
                padding: 1.5rem;
            }
            
            .upload-icon {
                font-size: 2rem;
            }
            
            .file-preview {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .file-actions {
                width: 100%;
                justify-content: space-between;
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
                    <nav class="breadcrumb-modern">
                        <a href="<?php echo SITE_URL; ?>/dashboard.php">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                        <span class="mx-2">/</span>
                        <span>Documents</span>
                    </nav>
                    
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
                <!-- Alert Messages -->
                <?php if (!empty($errors)): ?>
                <div class="alert-modern alert-danger-modern">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Please correct the following errors:</strong>
                        <ul style="margin: 0.5rem 0 0 0; padding-left: 1.25rem;">
                            <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                <div class="alert-modern alert-success-modern">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success_message; ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Document Requirements Overview -->
                <div class="requirements-panel">
                    <h3 class="requirements-title">
                        <i class="fas fa-list-check text-primary"></i>
                        Document Requirements
                    </h3>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <p class="text-muted mb-3">
                                Please upload all required documents for your application. Ensure that all documents are clear, legible, and in the correct format.
                            </p>
                            
                            <div class="requirement-items">
                                <?php foreach ($program_requirements as $req): ?>
                                <div class="requirement-item">
                                    <div class="requirement-icon" style="background: <?php echo $req['is_required'] ? 'var(--danger-color)' : 'var(--text-light)'; ?>; color: white;">
                                        <i class="fas fa-<?php echo $req['is_required'] ? 'asterisk' : 'circle'; ?>"></i>
                                    </div>
                                    <div class="requirement-content">
                                        <div class="requirement-name"><?php echo htmlspecialchars($req['certificate_name']); ?></div>
                                        <?php if ($req['description']): ?>
                                        <div class="requirement-note"><?php echo htmlspecialchars($req['description']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="requirement-status">
                                        <span class="document-badge <?php echo $req['is_required'] ? 'badge-required' : 'badge-optional'; ?>">
                                            <?php echo $req['is_required'] ? 'Required' : 'Optional'; ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="bg-light p-3 rounded">
                                <h6><i class="fas fa-info-circle text-info me-2"></i>Upload Guidelines</h6>
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Maximum file size: <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?>MB
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Supported formats: PDF, JPG, PNG
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Ensure documents are clear and legible
                                    </li>
                                    <li class="mb-0">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Upload one document at a time
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Document Upload Cards -->
                <div class="row">
                    <?php foreach ($program_requirements as $req): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="document-card">
                            <div class="document-header">
                                <div class="document-title">
                                    <?php echo htmlspecialchars($req['certificate_name']); ?>
                                </div>
                                <?php if ($req['description']): ?>
                                <div class="document-description">
                                    <?php echo htmlspecialchars($req['description']); ?>
                                </div>
                                <?php endif; ?>
                                <div class="document-badges">
                                    <span class="document-badge <?php echo $req['is_required'] ? 'badge-required' : 'badge-optional'; ?>">
                                        <i class="fas fa-<?php echo $req['is_required'] ? 'asterisk' : 'circle'; ?>"></i>
                                        <?php echo $req['is_required'] ? 'Required' : 'Optional'; ?>
                                    </span>
                                    <!-- Status badge would go here based on upload status -->
                                </div>
                            </div>
                            
                            <div class="document-body">
                                <form method="POST" enctype="multipart/form-data" class="upload-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="certificate_type_id" value="<?php echo $req['certificate_type_id']; ?>">
                                    
                                    <div class="upload-area" onclick="triggerFileInput(this)">
                                        <input type="file" 
                                               name="document" 
                                               class="file-input" 
                                               accept=".pdf,.jpg,.jpeg,.png"
                                               onchange="handleFileSelect(this)">
                                        <div class="upload-content">
                                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                            <div class="upload-text">Click to upload or drag & drop</div>
                                            <div class="upload-subtext">
                                                PDF, JPG, PNG up to <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?>MB
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="file-preview" style="display: none;">
                                        <div class="file-icon">
                                            <i class="fas fa-file"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name"></div>
                                            <div class="file-size"></div>
                                        </div>
                                        <div class="file-actions">
                                            <button type="button" class="btn-modern btn-outline-modern btn-sm" onclick="removeFile(this)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="upload-progress">
                                        <div class="progress-bar-modern">
                                            <div class="progress-fill"></div>
                                        </div>
                                        <div class="progress-text">Uploading...</div>
                                    </div>
                                    
                                    <div class="upload-actions mt-3" style="display: none;">
                                        <button type="submit" name="upload_document" class="btn-modern btn-primary-modern w-100">
                                            <i class="fas fa-upload"></i>
                                            Upload Document
                                        </button>
                                    </div>
                                </form>
                                
                                <!-- Existing document display (if uploaded) -->
                                <div class="existing-document" style="display: none;">
                                    <div class="file-preview">
                                        <div class="file-icon" style="background: var(--success-color);">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Document Uploaded</div>
                                            <div class="file-size">Uploaded on: March 15, 2025</div>
                                        </div>
                                        <div class="file-actions">
                                            <button type="button" class="btn-modern btn-outline-modern btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn-modern btn-outline-modern btn-sm">
                                                <i class="fas fa-download"></i>
                                            </button>
                                            <button type="button" class="btn-modern btn-danger-modern btn-sm">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Action Buttons -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="<?php echo SITE_URL; ?>/student/application.php" class="btn-modern btn-outline-modern">
                                <i class="fas fa-arrow-left"></i>
                                Back to Application
                            </a>
                            
                            <div class="text-center">
                                <small class="text-muted">All required documents must be uploaded before submission</small>
                            </div>
                            
                            <a href="<?php echo SITE_URL; ?>/student/status.php" class="btn-modern btn-success-modern">
                                <i class="fas fa-arrow-right"></i>
                                Continue to Status
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
        // File upload functionality
        function triggerFileInput(uploadArea) {
            const fileInput = uploadArea.querySelector('.file-input');
            fileInput.click();
        }
        
        function handleFileSelect(input) {
            const file = input.files[0];
            if (!file) return;
            
            const uploadArea = input.closest('.upload-area');
            const filePreview = uploadArea.nextElementSibling;
            const uploadActions = uploadArea.parentElement.querySelector('.upload-actions');
            
            // Validate file
            if (!validateFile(file)) {
                input.value = '';
                return;
            }
            
            // Update UI
            uploadArea.classList.add('has-file');
            uploadArea.querySelector('.upload-text').textContent = 'File selected';
            uploadArea.querySelector('.upload-subtext').textContent = 'Click to change file';
            
            // Show file preview
            const fileName = filePreview.querySelector('.file-name');
            const fileSize = filePreview.querySelector('.file-size');
            
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            
            filePreview.style.display = 'flex';
            uploadActions.style.display = 'block';
            
            // Update file icon based on type
            const fileIcon = filePreview.querySelector('.file-icon i');
            const extension = file.name.split('.').pop().toLowerCase();
            
            switch (extension) {
                case 'pdf':
                    fileIcon.className = 'fas fa-file-pdf';
                    break;
                case 'jpg':
                case 'jpeg':
                case 'png':
                    fileIcon.className = 'fas fa-file-image';
                    break;
                default:
                    fileIcon.className = 'fas fa-file';
            }
        }
        
        function removeFile(button) {
            const form = button.closest('form');
            const fileInput = form.querySelector('.file-input');
            const uploadArea = form.querySelector('.upload-area');
            const filePreview = form.querySelector('.file-preview');
            const uploadActions = form.querySelector('.upload-actions');
            
            // Reset form
            fileInput.value = '';
            uploadArea.classList.remove('has-file');
            uploadArea.querySelector('.upload-text').textContent = 'Click to upload or drag & drop';
            uploadArea.querySelector('.upload-subtext').textContent = 'PDF, JPG, PNG up to <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?>MB';
            
            // Hide elements
            filePreview.style.display = 'none';
            uploadActions.style.display = 'none';
        }
        
        function validateFile(file) {
            const maxSize = <?php echo MAX_FILE_SIZE; ?>;
            const allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'];
            const extension = file.name.split('.').pop().toLowerCase();
            
            if (!allowedTypes.includes(extension)) {
                showAlert('Invalid file type. Only PDF, JPG, JPEG, and PNG files are allowed.', 'danger');
                return false;
            }
            
            if (file.size > maxSize) {
                showAlert('File size too large. Maximum allowed size is <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?>MB.', 'danger');
                return false;
            }
            
            return true;
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function showAlert(message, type) {
            const alertHtml = `
                <div class="alert-modern alert-${type}-modern" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', alertHtml);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                const alert = document.querySelector('.alert-modern[style*="position: fixed"]');
                if (alert) {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }
            }, 5000);
        }
        
        // Handle form submission with progress
        document.querySelectorAll('.upload-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const progressDiv = this.querySelector('.upload-progress');
                const progressFill = this.querySelector('.progress-fill');
                const progressText = this.querySelector('.progress-text');
                const submitBtn = this.querySelector('button[type="submit"]');
                
                // Show progress
                progressDiv.style.display = 'block';
                submitBtn.disabled = true;
                
                // Simulate upload progress (replace with actual XMLHttpRequest)
                let progress = 0;
                const interval = setInterval(() => {
                    progress += Math.random() * 15;
                    if (progress > 100) progress = 100;
                    
                    progressFill.style.width = progress + '%';
                    progressText.textContent = `Uploading... ${Math.round(progress)}%`;
                    
                    if (progress >= 100) {
                        clearInterval(interval);
                        progressText.textContent = 'Upload complete!';
                        
                        setTimeout(() => {
                            // Submit the form normally or via AJAX
                            this.submit();
                        }, 500);
                    }
                }, 100);
            });
        });
        
        // Drag and drop functionality
        document.querySelectorAll('.upload-area').forEach(area => {
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
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        function handleDrop(e) {
            const files = e.dataTransfer.files;
            const fileInput = e.target.closest('.upload-area').querySelector('.file-input');
            
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(fileInput);
            }
        }
        
        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert-modern').forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>