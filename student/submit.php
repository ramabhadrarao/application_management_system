<?php
/**
 * Final Application Submission & Freeze
 * 
 * File: student/submit.php
 * Purpose: Final submission and freeze application
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../config/config.php';
require_once '../classes/User.php';
require_once '../classes/Application.php';
require_once '../classes/Program.php';

// Require student login
requireLogin();
requirePermission('submit_application');

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

// Only allow submission if status is SUBMITTED
if ($student_application['status'] !== STATUS_SUBMITTED) {
    $redirect_url = SITE_URL . '/student/status.php';
    if ($student_application['status'] === STATUS_DRAFT) {
        $redirect_url = SITE_URL . '/student/application.php?error=not_submitted';
    }
    header('Location: ' . $redirect_url);
    exit;
}

// Get program details and requirements
$user_program = $program->getById($student_application['program_id']);
$program_requirements = $program->getCertificateRequirements($student_application['program_id']);

// Check application completeness
$completeness_query = "
    SELECT 
        pcr.certificate_type_id,
        ct.name as certificate_name,
        pcr.is_required,
        ad.id as document_id,
        ad.is_verified
    FROM program_certificate_requirements pcr
    JOIN certificate_types ct ON pcr.certificate_type_id = ct.id
    LEFT JOIN application_documents ad ON pcr.certificate_type_id = ad.certificate_type_id 
                                        AND ad.application_id = :application_id
    WHERE pcr.program_id = :program_id
    ORDER BY pcr.display_order ASC
";

$stmt = $db->prepare($completeness_query);
$stmt->bindParam(':application_id', $student_application['id']);
$stmt->bindParam(':program_id', $student_application['program_id']);
$stmt->execute();
$document_status = $stmt->fetchAll();

// Calculate completion statistics
$required_docs = 0;
$uploaded_docs = 0;
$verified_docs = 0;
$missing_required = [];

foreach ($document_status as $doc) {
    if ($doc['is_required']) {
        $required_docs++;
        if ($doc['document_id']) {
            $uploaded_docs++;
            if ($doc['is_verified']) {
                $verified_docs++;
            }
        } else {
            $missing_required[] = $doc['certificate_name'];
        }
    }
}

$is_complete = empty($missing_required);

// Handle final submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['final_submit'])) {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        
        if (!isset($_POST['declaration']) || $_POST['declaration'] !== '1') {
            $errors[] = 'You must accept the declaration to proceed.';
        }
        
        if (!$is_complete) {
            $errors[] = 'All required documents must be uploaded before final submission.';
        }
        
        if (empty($errors)) {
            if ($application->freeze($student_application['id'], $current_user_id)) {
                $success_message = 'Application successfully submitted and frozen! You will receive an email confirmation shortly.';
                // Refresh application data
                $student_application = $application->getByUserId($current_user_id);
            } else {
                $errors[] = 'Failed to submit application. Please try again.';
            }
        }
    } else {
        $errors[] = 'Invalid request. Please try again.';
    }
}

$page_title = 'Final Submission';
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
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --text-dark: #2c3e50;
            --text-light: #6c757d;
            --bg-light: #f8f9fa;
            --white: #ffffff;
            --border-color: #e9ecef;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
            --radius-lg: 0.75rem;
        }
        
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
        }
        
        .submit-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            color: white;
            padding: 3rem 0;
            text-align: center;
        }
        
        .submit-icon {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
        }
        
        .submit-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .submit-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .card-modern {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: none;
            overflow: hidden;
        }
        
        .checklist-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .checklist-item:last-child {
            border-bottom: none;
        }
        
        .checklist-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        
        .icon-success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .icon-warning {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .icon-danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .declaration-box {
            background: #fff3cd;
            border: 2px solid #ffeaa7;
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin: 2rem 0;
        }
        
        .btn-submit-final {
            background: linear-gradient(135deg, var(--success-color), #20c997);
            border: none;
            color: white;
            padding: 1rem 2rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .btn-submit-final:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
        }
        
        .btn-submit-final:disabled {
            background: var(--text-light);
            cursor: not-allowed;
            transform: none;
        }
        
        .alert-modern {
            border-radius: var(--radius-lg);
            border: none;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stats-card {
            text-align: center;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            background: var(--white);
            border: 2px solid var(--border-color);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stats-label {
            color: var(--text-light);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="submit-header">
        <div class="container">
            <div class="submit-icon">
                <i class="fas fa-paper-plane"></i>
            </div>
            <h1 class="submit-title">Final Submission</h1>
            <p class="submit-subtitle">Review and submit your application</p>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container py-4">
        <!-- Status Messages -->
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-modern">
            <h5><i class="fas fa-exclamation-triangle me-2"></i>Action Required</h5>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-modern">
            <h5><i class="fas fa-check-circle me-2"></i>Success!</h5>
            <p class="mb-0"><?php echo $success_message; ?></p>
            <hr>
            <a href="<?php echo SITE_URL; ?>/student/status.php" class="btn btn-success">
                <i class="fas fa-chart-line me-2"></i>View Application Status
            </a>
        </div>
        <?php endif; ?>
        
        <?php if ($student_application['status'] === STATUS_FROZEN): ?>
        <div class="alert alert-info alert-modern text-center">
            <h4><i class="fas fa-lock me-2"></i>Application Successfully Submitted</h4>
            <p class="mb-3">Your application has been frozen and submitted for review. No further changes can be made.</p>
            <a href="<?php echo SITE_URL; ?>/student/status.php" class="btn btn-primary">
                <i class="fas fa-chart-line me-2"></i>Track Application Status
            </a>
        </div>
        <?php else: ?>
        
        <!-- Application Summary -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card-modern">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-clipboard-check me-2"></i>
                            Application Summary
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-6">
                                <strong>Application Number:</strong><br>
                                <code><?php echo htmlspecialchars($student_application['application_number']); ?></code>
                            </div>
                            <div class="col-sm-6">
                                <strong>Program:</strong><br>
                                <?php echo htmlspecialchars($user_program['program_name']); ?>
                            </div>
                            <div class="col-sm-6 mt-3">
                                <strong>Student Name:</strong><br>
                                <?php echo htmlspecialchars($student_application['student_name']); ?>
                            </div>
                            <div class="col-sm-6 mt-3">
                                <strong>Academic Year:</strong><br>
                                <?php echo CURRENT_ACADEMIC_YEAR; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="stats-card">
                            <div class="stats-number text-primary"><?php echo $uploaded_docs; ?>/<?php echo $required_docs; ?></div>
                            <div class="stats-label">Documents</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stats-card">
                            <div class="stats-number text-success"><?php echo $verified_docs; ?></div>
                            <div class="stats-label">Verified</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Document Checklist -->
        <div class="card-modern mb-4">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-list-check me-2"></i>
                    Document Checklist
                </h4>
            </div>
            <div class="card-body">
                <?php foreach ($document_status as $doc): ?>
                <div class="checklist-item">
                    <div class="checklist-icon <?php
                        if ($doc['document_id']) {
                            echo $doc['is_verified'] ? 'icon-success' : 'icon-warning';
                        } else {
                            echo $doc['is_required'] ? 'icon-danger' : 'icon-warning';
                        }
                    ?>">
                        <i class="fas fa-<?php
                            if ($doc['document_id']) {
                                echo $doc['is_verified'] ? 'check' : 'clock';
                            } else {
                                echo $doc['is_required'] ? 'times' : 'minus';
                            }
                        ?>"></i>
                    </div>
                    
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><?php echo htmlspecialchars($doc['certificate_name']); ?></div>
                        <small class="text-muted">
                            <?php
                            if ($doc['document_id']) {
                                echo $doc['is_verified'] ? 'Verified' : 'Uploaded - Pending verification';
                            } else {
                                echo $doc['is_required'] ? 'Required - Not uploaded' : 'Optional - Not uploaded';
                            }
                            ?>
                        </small>
                    </div>
                    
                    <div>
                        <?php if ($doc['is_required']): ?>
                        <span class="badge bg-danger">Required</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Optional</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (!empty($missing_required)): ?>
                <div class="alert alert-warning mt-3">
                    <strong>Missing Required Documents:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($missing_required as $missing): ?>
                        <li><?php echo htmlspecialchars($missing); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <hr>
                    <a href="<?php echo SITE_URL; ?>/student/documents.php" class="btn btn-warning btn-sm">
                        <i class="fas fa-upload me-1"></i>Upload Missing Documents
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($is_complete): ?>
        <!-- Declaration and Final Submit -->
        <form method="POST" id="finalSubmitForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="declaration-box">
                <h5><i class="fas fa-exclamation-triangle text-warning me-2"></i>Important Declaration</h5>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="declaration" value="1" id="declaration" required>
                    <label class="form-check-label" for="declaration">
                        <strong>I hereby declare that:</strong>
                        <ul class="mt-2 mb-0">
                            <li>All information provided in this application is true and accurate to the best of my knowledge.</li>
                            <li>I understand that any false information may result in rejection of my application or cancellation of admission.</li>
                            <li>I have uploaded all required documents and they are genuine.</li>
                            <li>Once submitted, I will not be able to make any changes to my application.</li>
                            <li>I agree to abide by the rules and regulations of the institution.</li>
                        </ul>
                    </label>
                </div>
            </div>
            
            <div class="text-center">
                <button type="submit" name="final_submit" class="btn btn-submit-final" id="submitBtn" disabled>
                    <i class="fas fa-lock me-2"></i>
                    Final Submit & Freeze Application
                </button>
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        After final submission, your application will be locked and sent for review.
                    </small>
                </div>
            </div>
        </form>
        <?php endif; ?>
        
        <!-- Navigation -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="d-flex justify-content-between">
                    <a href="<?php echo SITE_URL; ?>/student/documents.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Documents
                    </a>
                    <a href="<?php echo SITE_URL; ?>/student/status.php" class="btn btn-outline-primary">
                        <i class="fas fa-chart-line me-2"></i>View Status
                    </a>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script>
        // Enable submit button only when declaration is checked
        document.getElementById('declaration')?.addEventListener('change', function() {
            document.getElementById('submitBtn').disabled = !this.checked;
        });
        
        // Confirmation before final submit
        document.getElementById('finalSubmitForm')?.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to submit your application? This action cannot be undone.')) {
                e.preventDefault();
            } else {
                // Show loading state
                const btn = document.getElementById('submitBtn');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            }
        });
    </script>
</body>
</html>