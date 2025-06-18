<?php
/**
 * Document Verification Page
 * 
 * File: admin/documents/verify.php
 * Purpose: Verify uploaded documents for applications
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
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

// Get application ID
$app_id = isset($_GET['app_id']) ? (int)$_GET['app_id'] : 0;

if (!$app_id) {
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

$message = '';
$message_type = '';

// Handle document verification
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        
        if (isset($_POST['verify_document'])) {
            $doc_id = (int)$_POST['document_id'];
            $is_verified = $_POST['is_verified'] == '1' ? 1 : 0;
            $remarks = sanitizeInput($_POST['verification_remarks']);
            
            $query = "UPDATE application_documents 
                      SET is_verified = :is_verified, 
                          verified_by = :verified_by,
                          verified_at = NOW(),
                          verification_remarks = :remarks
                      WHERE id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':is_verified', $is_verified);
            $stmt->bindParam(':verified_by', $current_user_id);
            $stmt->bindParam(':remarks', $remarks);
            $stmt->bindParam(':id', $doc_id);
            
            if ($stmt->execute()) {
                $message = 'Document verification status updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to update verification status.';
                $message_type = 'danger';
            }
        }
    }
}

// Get application documents
$docs_query = "
    SELECT ad.*, ct.name as certificate_name, ct.description,
           fu.original_name, fu.file_path, fu.file_size, fu.mime_type,
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

// Get required documents
$req_docs_query = "
    SELECT ct.id, ct.name, pcr.is_required
    FROM program_certificate_requirements pcr
    JOIN certificate_types ct ON pcr.certificate_type_id = ct.id
    WHERE pcr.program_id = :program_id
    ORDER BY pcr.display_order ASC
";

$stmt = $db->prepare($req_docs_query);
$stmt->bindParam(':program_id', $app_details['program_id']);
$stmt->execute();
$required_docs = $stmt->fetchAll();

$page_title = 'Verify Documents - ' . $app_details['application_number'];
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
        
        .app-info-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .doc-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .doc-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .doc-card.verified {
            border-left-color: var(--success-color);
        }
        
        .doc-card.rejected {
            border-left-color: var(--danger-color);
        }
        
        .doc-card.missing {
            border-left-color: var(--warning-color);
            background: #fff8e1;
        }
        
        .doc-preview {
            width: 100%;
            max-height: 600px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .verification-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .verification-status.verified {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .verification-status.rejected {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .verification-status.pending {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .doc-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .preview-content {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            max-width: 90%;
            max-height: 90%;
            overflow: auto;
        }
        
        .preview-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
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
                        <div class="page-pretitle">Document Verification</div>
                        <h2 class="page-title">
                            <i class="fas fa-file-check me-2"></i>
                            Verify Documents
                        </h2>
                    </div>
                    <div class="col-auto ms-auto">
                        <div class="btn-list">
                            <a href="../applications/view.php?id=<?php echo $app_id; ?>" class="btn btn-light">
                                <i class="fas fa-eye me-2"></i>View Application
                            </a>
                            <a href="../applications/list.php" class="btn btn-light">
                                <i class="fas fa-arrow-left me-2"></i>Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="page-body">
            <div class="container-xl">
                <!-- Messages -->
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Application Info -->
                <div class="app-info-card">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Application Number:</strong><br>
                            <span class="text-primary"><?php echo htmlspecialchars($app_details['application_number']); ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Student Name:</strong><br>
                            <?php echo htmlspecialchars($app_details['student_name']); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Program:</strong><br>
                            <?php echo htmlspecialchars($app_details['program_name']); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Status:</strong><br>
                            <span class="badge bg-<?php echo getStatusColor($app_details['status']); ?>">
                                <?php echo ucwords(str_replace('_', ' ', $app_details['status'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Required Documents -->
                <h3 class="mb-3">
                    <i class="fas fa-clipboard-list me-2"></i>
                    Required Documents
                </h3>
                
                <?php foreach ($required_docs as $req_doc): ?>
                <?php
                // Find uploaded document
                $uploaded_doc = null;
                foreach ($documents as $doc) {
                    if ($doc['certificate_type_id'] == $req_doc['id']) {
                        $uploaded_doc = $doc;
                        break;
                    }
                }
                ?>
                
                <div class="doc-card <?php echo $uploaded_doc ? ($uploaded_doc['is_verified'] ? 'verified' : 'rejected') : 'missing'; ?>">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <h5 class="mb-1">
                                <?php echo htmlspecialchars($req_doc['name']); ?>
                                <?php if ($req_doc['is_required']): ?>
                                <span class="badge bg-danger ms-2">Required</span>
                                <?php endif; ?>
                            </h5>
                            <?php if ($uploaded_doc): ?>
                            <p class="text-muted mb-0">
                                <small>
                                    <i class="fas fa-file me-1"></i>
                                    <?php echo htmlspecialchars($uploaded_doc['original_name']); ?>
                                    (<?php echo number_format($uploaded_doc['file_size'] / 1024, 2); ?> KB)
                                </small>
                            </p>
                            <?php else: ?>
                            <p class="text-danger mb-0">
                                <small><i class="fas fa-exclamation-triangle me-1"></i>Not uploaded</small>
                            </p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4">
                            <?php if ($uploaded_doc): ?>
                            <div class="verification-status <?php echo $uploaded_doc['is_verified'] ? 'verified' : ($uploaded_doc['verified_by'] ? 'rejected' : 'pending'); ?>">
                                <?php if ($uploaded_doc['is_verified']): ?>
                                    <i class="fas fa-check-circle"></i>Verified
                                <?php elseif ($uploaded_doc['verified_by']): ?>
                                    <i class="fas fa-times-circle"></i>Rejected
                                <?php else: ?>
                                    <i class="fas fa-clock"></i>Pending Verification
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($uploaded_doc['verified_by']): ?>
                            <small class="text-muted d-block mt-1">
                                By: <?php echo htmlspecialchars($uploaded_doc['verified_by_email']); ?><br>
                                On: <?php echo formatDateTime($uploaded_doc['verified_at']); ?>
                            </small>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4">
                            <?php if ($uploaded_doc): ?>
                            <div class="doc-actions">
                                <button class="btn btn-sm btn-info" onclick="previewDocument('<?php echo htmlspecialchars($uploaded_doc['file_path']); ?>', '<?php echo htmlspecialchars($uploaded_doc['mime_type']); ?>')">
                                    <i class="fas fa-eye"></i> Preview
                                </button>
                                <a href="download.php?id=<?php echo $uploaded_doc['id']; ?>" class="btn btn-sm btn-secondary" target="_blank">
                                    <i class="fas fa-download"></i> Download
                                </a>
                                <button class="btn btn-sm btn-primary" onclick="showVerificationForm(<?php echo $uploaded_doc['id']; ?>)">
                                    <i class="fas fa-check"></i> Verify
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($uploaded_doc && $uploaded_doc['verification_remarks']): ?>
                    <div class="mt-3 p-3 bg-light rounded">
                        <strong>Verification Remarks:</strong>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($uploaded_doc['verification_remarks'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <!-- Additional Uploaded Documents -->
                <?php
                $additional_docs = [];
                foreach ($documents as $doc) {
                    $is_required = false;
                    foreach ($required_docs as $req) {
                        if ($req['id'] == $doc['certificate_type_id']) {
                            $is_required = true;
                            break;
                        }
                    }
                    if (!$is_required) {
                        $additional_docs[] = $doc;
                    }
                }
                ?>
                
                <?php if (!empty($additional_docs)): ?>
                <h3 class="mt-4 mb-3">
                    <i class="fas fa-paperclip me-2"></i>
                    Additional Documents
                </h3>
                
                <?php foreach ($additional_docs as $doc): ?>
                <div class="doc-card <?php echo $doc['is_verified'] ? 'verified' : ''; ?>">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <h5 class="mb-1"><?php echo htmlspecialchars($doc['certificate_name']); ?></h5>
                            <p class="text-muted mb-0">
                                <small>
                                    <i class="fas fa-file me-1"></i>
                                    <?php echo htmlspecialchars($doc['original_name']); ?>
                                    (<?php echo number_format($doc['file_size'] / 1024, 2); ?> KB)
                                </small>
                            </p>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="verification-status <?php echo $doc['is_verified'] ? 'verified' : ($doc['verified_by'] ? 'rejected' : 'pending'); ?>">
                                <?php if ($doc['is_verified']): ?>
                                    <i class="fas fa-check-circle"></i>Verified
                                <?php elseif ($doc['verified_by']): ?>
                                    <i class="fas fa-times-circle"></i>Rejected
                                <?php else: ?>
                                    <i class="fas fa-clock"></i>Pending Verification
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="doc-actions">
                                <button class="btn btn-sm btn-info" onclick="previewDocument('<?php echo htmlspecialchars($doc['file_path']); ?>', '<?php echo htmlspecialchars($doc['mime_type']); ?>')">
                                    <i class="fas fa-eye"></i> Preview
                                </button>
                                <a href="download.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-secondary" target="_blank">
                                    <i class="fas fa-download"></i> Download
                                </a>
                                <button class="btn btn-sm btn-primary" onclick="showVerificationForm(<?php echo $doc['id']; ?>)">
                                    <i class="fas fa-check"></i> Verify
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Document Preview Modal -->
    <div id="previewModal" class="preview-modal">
        <div class="preview-close" onclick="closePreview()">
            <i class="fas fa-times"></i>
        </div>
        <div class="preview-content" id="previewContent">
            <!-- Preview content will be loaded here -->
        </div>
    </div>
    
    <!-- Verification Form Modal -->
    <div class="modal fade" id="verificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verify Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="verify_document" value="1">
                        <input type="hidden" name="document_id" id="verifyDocId">
                        
                        <div class="mb-3">
                            <label class="form-label">Verification Status</label>
                            <select name="is_verified" class="form-select" required>
                                <option value="">Select Status</option>
                                <option value="1">Verified - Document is Valid</option>
                                <option value="0">Rejected - Document is Invalid</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Verification Remarks</label>
                            <textarea name="verification_remarks" class="form-control" rows="3" 
                                      placeholder="Enter any remarks about this document..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Verification</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function previewDocument(filePath, mimeType) {
            const modal = document.getElementById('previewModal');
            const content = document.getElementById('previewContent');
            
            if (mimeType.includes('image')) {
                content.innerHTML = '<img src="' + filePath + '" class="doc-preview">';
            } else if (mimeType.includes('pdf')) {
                content.innerHTML = '<iframe src="' + filePath + '" class="doc-preview" style="width:100%;height:600px;"></iframe>';
            } else {
                content.innerHTML = '<p class="text-center">Preview not available for this file type. Please download to view.</p>';
            }
            
            modal.style.display = 'flex';
        }
        
        function closePreview() {
            document.getElementById('previewModal').style.display = 'none';
        }
        
        function showVerificationForm(docId) {
            document.getElementById('verifyDocId').value = docId;
            const modal = new bootstrap.Modal(document.getElementById('verificationModal'));
            modal.show();
        }
        
        // Close preview modal on outside click
        document.getElementById('previewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePreview();
            }
        });
        
        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
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
 * Helper function to get status color
 */
function getStatusColor($status) {
    switch ($status) {
        case STATUS_DRAFT: return 'secondary';
        case STATUS_SUBMITTED: return 'info';
        case STATUS_UNDER_REVIEW: return 'warning';
        case STATUS_APPROVED: return 'success';
        case STATUS_REJECTED: return 'danger';
        case STATUS_FROZEN: return 'primary';
        default: return 'secondary';
    }
}
?>