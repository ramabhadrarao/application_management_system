<?php
/**
 * Document Verification Status
 * 
 * File: student/document-verify.php
 * Purpose: View document verification status and details
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

// Get document verification status
$verification_query = "
    SELECT 
        ad.id,
        ad.document_name,
        ad.is_verified,
        ad.verified_by,
        ad.verified_at,
        ad.verification_remarks,
        ad.date_created as uploaded_at,
        ct.name as certificate_name,
        ct.description as certificate_description,
        fu.original_name,
        fu.file_size,
        fu.mime_type,
        fu.file_path,
        pcr.is_required,
        verifier.email as verifier_email
    FROM application_documents ad
    JOIN certificate_types ct ON ad.certificate_type_id = ct.id
    JOIN file_uploads fu ON ad.file_upload_id = fu.uuid
    LEFT JOIN program_certificate_requirements pcr ON ct.id = pcr.certificate_type_id 
                                                   AND pcr.program_id = :program_id
    LEFT JOIN users verifier ON ad.verified_by = verifier.id
    WHERE ad.application_id = :application_id
    ORDER BY pcr.display_order ASC, ct.name ASC
";

$stmt = $db->prepare($verification_query);
$stmt->bindParam(':application_id', $student_application['id']);
$stmt->bindParam(':program_id', $student_application['program_id']);
$stmt->execute();
$documents = $stmt->fetchAll();

// Get missing required documents
$missing_query = "
    SELECT 
        ct.id,
        ct.name as certificate_name,
        ct.description,
        pcr.is_required
    FROM program_certificate_requirements pcr
    JOIN certificate_types ct ON pcr.certificate_type_id = ct.id
    WHERE pcr.program_id = :program_id 
    AND pcr.is_required = 1
    AND NOT EXISTS (
        SELECT 1 FROM application_documents ad 
        WHERE ad.application_id = :application_id 
        AND ad.certificate_type_id = ct.id
    )
    ORDER BY pcr.display_order ASC
";

$stmt = $db->prepare($missing_query);
$stmt->bindParam(':program_id', $student_application['program_id']);
$stmt->bindParam(':application_id', $student_application['id']);
$stmt->execute();
$missing_documents = $stmt->fetchAll();

// Calculate statistics
$total_uploaded = count($documents);
$verified_count = 0;
$pending_count = 0;

foreach ($documents as $doc) {
    if ($doc['is_verified']) {
        $verified_count++;
    } else {
        $pending_count++;
    }
}

$page_title = 'Document Verification';
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
            --info-color: #17a2b8;
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
        
        .verify-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            color: white;
            padding: 3rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .verify-header::before {
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
        
        .verify-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
        
        .verify-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        
        .verify-subtitle {
            opacity: 0.9;
            text-align: center;
        }
        
        .stats-row {
            margin: 2rem 0;
        }
        
        .stat-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
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
        
        .document-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .document-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .document-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .document-type {
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .badge-verified {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            border: 2px solid rgba(40, 167, 69, 0.2);
        }
        
        .badge-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
            border: 2px solid rgba(255, 193, 7, 0.2);
        }
        
        .badge-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
            border: 2px solid rgba(220, 53, 69, 0.2);
        }
        
        .document-body {
            padding: 1.5rem;
        }
        
        .verification-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .file-preview {
            display: flex;
            align-items: center;
            background: var(--bg-light);
            border-radius: var(--radius-lg);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .file-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            color: white;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .file-details {
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
        
        .verification-remarks {
            background: rgba(0, 84, 166, 0.05);
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 0 var(--radius-lg) var(--radius-lg) 0;
            margin-top: 1rem;
        }
        
        .remarks-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }
        
        .btn-modern {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-lg);
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
            background: linear-gradient(135deg, var(--primary-color), #667eea);
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
            text-decoration: none;
        }
        
        .missing-card {
            background: rgba(220, 53, 69, 0.05);
            border: 2px solid rgba(220, 53, 69, 0.2);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .missing-icon {
            width: 40px;
            height: 40px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .alert-modern {
            border-radius: var(--radius-lg);
            border: none;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 2rem;
            padding-bottom: 1.5rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-color);
        }
        
        .timeline-item:last-child::before {
            height: 1.5rem;
        }
        
        .timeline-marker {
            position: absolute;
            left: 0;
            top: 0.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid var(--white);
            z-index: 2;
        }
        
        .timeline-marker.verified {
            background: var(--success-color);
        }
        
        .timeline-marker.pending {
            background: var(--warning-color);
        }
        
        .timeline-content {
            margin-left: 1rem;
        }
        
        .timeline-date {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-bottom: 0.25rem;
        }
        
        .timeline-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .timeline-description {
            color: var(--text-light);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Header -->
        <div class="verify-header">
            <div class="container">
                <div class="header-content">
                    <!-- Breadcrumb -->
                    <nav class="mb-3" style="background: rgba(255, 255, 255, 0.1); border-radius: var(--radius-lg); padding: 0.75rem 1rem; display: inline-block;">
                        <a href="<?php echo SITE_URL; ?>/dashboard.php" style="color: rgba(255, 255, 255, 0.8); text-decoration: none;">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                        <span class="mx-2">/</span>
                        <span>Document Verification</span>
                    </nav>
                    
                    <div class="verify-icon">
                        <i class="fas fa-file-check"></i>
                    </div>
                    <h1 class="verify-title">Document Verification Status</h1>
                    <p class="verify-subtitle">Track the verification status of your uploaded documents</p>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="container py-4">
            <!-- Statistics -->
            <div class="stats-row">
                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-number text-primary"><?php echo $total_uploaded; ?></div>
                            <div class="stat-label">Total Uploaded</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-number text-success"><?php echo $verified_count; ?></div>
                            <div class="stat-label">Verified</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-number text-warning"><?php echo $pending_count; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-number text-danger"><?php echo count($missing_documents); ?></div>
                            <div class="stat-label">Missing</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Missing Documents Alert -->
            <?php if (!empty($missing_documents)): ?>
            <div class="alert alert-warning alert-modern">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Missing Required Documents</h5>
                <p class="mb-3">The following required documents are missing from your application:</p>
                <ul class="mb-3">
                    <?php foreach ($missing_documents as $missing): ?>
                    <li><?php echo htmlspecialchars($missing['certificate_name']); ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?php echo SITE_URL; ?>/student/documents.php" class="btn btn-warning">
                    <i class="fas fa-upload me-2"></i>Upload Missing Documents
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Document List -->
            <div class="row">
                <div class="col-lg-8">
                    <h3 class="mb-4">
                        <i class="fas fa-file-alt text-primary me-2"></i>
                        Uploaded Documents
                    </h3>
                    
                    <?php if (empty($documents)): ?>
                    <div class="alert alert-info alert-modern text-center">
                        <h5><i class="fas fa-info-circle me-2"></i>No Documents Uploaded</h5>
                        <p class="mb-3">You haven't uploaded any documents yet.</p>
                        <a href="<?php echo SITE_URL; ?>/student/documents.php" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i>Upload Documents
                        </a>
                    </div>
                    <?php else: ?>
                    
                    <?php foreach ($documents as $doc): ?>
                    <div class="document-card">
                        <div class="document-header">
                            <div class="document-info">
                                <div class="document-name"><?php echo htmlspecialchars($doc['certificate_name']); ?></div>
                                <div class="document-type"><?php echo htmlspecialchars($doc['certificate_description']); ?></div>
                            </div>
                            <div class="status-badge <?php echo $doc['is_verified'] ? 'badge-verified' : 'badge-pending'; ?>">
                                <i class="fas fa-<?php echo $doc['is_verified'] ? 'check-circle' : 'clock'; ?>"></i>
                                <?php echo $doc['is_verified'] ? 'Verified' : 'Pending'; ?>
                            </div>
                        </div>
                        
                        <div class="document-body">
                            <!-- File Preview -->
                            <div class="file-preview">
                                <div class="file-icon">
                                    <i class="fas fa-<?php 
                                        $extension = strtolower(pathinfo($doc['original_name'], PATHINFO_EXTENSION));
                                        echo ($extension === 'pdf') ? 'file-pdf' : 'file-image';
                                    ?>"></i>
                                </div>
                                <div class="file-details">
                                    <div class="file-name"><?php echo htmlspecialchars($doc['original_name']); ?></div>
                                    <div class="file-meta">
                                        <?php echo number_format($doc['file_size'] / 1024, 1); ?> KB • 
                                        Uploaded on <?php echo formatDateTime($doc['uploaded_at']); ?>
                                    </div>
                                </div>
                                <div class="file-actions">
                                    <a href="#" class="btn-modern btn-outline-modern" onclick="viewDocument('<?php echo htmlspecialchars($doc['file_path']); ?>')">
                                        <i class="fas fa-eye"></i>View
                                    </a>
                                    <a href="#" class="btn-modern btn-primary-modern" onclick="downloadDocument('<?php echo htmlspecialchars($doc['file_path']); ?>')">
                                        <i class="fas fa-download"></i>Download
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Verification Details -->
                            <div class="verification-details">
                                <div class="detail-item">
                                    <div class="detail-label">Status</div>
                                    <div class="detail-value">
                                        <?php if ($doc['is_verified']): ?>
                                        <span class="text-success">
                                            <i class="fas fa-check-circle me-1"></i>Verified
                                        </span>
                                        <?php else: ?>
                                        <span class="text-warning">
                                            <i class="fas fa-clock me-1"></i>Pending Verification
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($doc['is_verified'] && $doc['verified_at']): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Verified On</div>
                                    <div class="detail-value"><?php echo formatDateTime($doc['verified_at']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($doc['is_verified'] && $doc['verifier_email']): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Verified By</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($doc['verifier_email']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Document Type</div>
                                    <div class="detail-value">
                                        <?php echo $doc['is_required'] ? 'Required' : 'Optional'; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Verification Remarks -->
                            <?php if (!empty($doc['verification_remarks'])): ?>
                            <div class="verification-remarks">
                                <div class="remarks-title">
                                    <i class="fas fa-comment-alt me-2"></i>Verification Notes
                                </div>
                                <div><?php echo nl2br(htmlspecialchars($doc['verification_remarks'])); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Verification Timeline -->
                    <div class="document-card">
                        <div class="document-header">
                            <div class="document-info">
                                <div class="document-name">Verification Timeline</div>
                                <div class="document-type">Track your document verification progress</div>
                            </div>
                        </div>
                        <div class="document-body">
                            <div class="timeline">
                                <?php
                                // Create timeline from document history
                                $timeline_events = [];
                                
                                foreach ($documents as $doc) {
                                    $timeline_events[] = [
                                        'date' => $doc['uploaded_at'],
                                        'title' => 'Document Uploaded',
                                        'description' => $doc['certificate_name'],
                                        'status' => 'uploaded'
                                    ];
                                    
                                    if ($doc['is_verified'] && $doc['verified_at']) {
                                        $timeline_events[] = [
                                            'date' => $doc['verified_at'],
                                            'title' => 'Document Verified',
                                            'description' => $doc['certificate_name'],
                                            'status' => 'verified'
                                        ];
                                    }
                                }
                                
                                // Sort by date
                                usort($timeline_events, function($a, $b) {
                                    return strtotime($b['date']) - strtotime($a['date']);
                                });
                                
                                foreach (array_slice($timeline_events, 0, 5) as $event):
                                ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker <?php echo $event['status']; ?>"></div>
                                    <div class="timeline-content">
                                        <div class="timeline-date"><?php echo formatDateTime($event['date']); ?></div>
                                        <div class="timeline-title"><?php echo $event['title']; ?></div>
                                        <div class="timeline-description"><?php echo htmlspecialchars($event['description']); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php if (empty($timeline_events)): ?>
                                <div class="text-center py-3 text-muted">
                                    <i class="fas fa-clock fa-2x mb-2"></i>
                                    <p>No verification activity yet</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="document-card">
                        <div class="document-header">
                            <div class="document-info">
                                <div class="document-name">Quick Actions</div>
                                <div class="document-type">Manage your documents</div>
                            </div>
                        </div>
                        <div class="document-body">
                            <div class="d-grid gap-2">
                                <a href="<?php echo SITE_URL; ?>/student/documents.php" class="btn-modern btn-primary-modern">
                                    <i class="fas fa-upload"></i>Upload Documents
                                </a>
                                <a href="<?php echo SITE_URL; ?>/student/application.php" class="btn-modern btn-outline-modern">
                                    <i class="fas fa-edit"></i>Edit Application
                                </a>
                                <a href="<?php echo SITE_URL; ?>/student/status.php" class="btn-modern btn-outline-modern">
                                    <i class="fas fa-chart-line"></i>Application Status
                                </a>
                                <a href="<?php echo SITE_URL; ?>/help.php" class="btn-modern btn-outline-modern">
                                    <i class="fas fa-question-circle"></i>Help & Support
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Support -->
                    <div class="document-card">
                        <div class="document-header">
                            <div class="document-info">
                                <div class="document-name">Need Help?</div>
                                <div class="document-type">Contact our support team</div>
                            </div>
                        </div>
                        <div class="document-body">
                            <p class="text-muted mb-3">
                                If you have questions about document verification or need assistance, 
                                please contact our admissions team.
                            </p>
                            <div class="d-grid gap-2">
                                <a href="mailto:<?php echo ADMIN_EMAIL; ?>" class="btn-modern btn-outline-modern">
                                    <i class="fas fa-envelope"></i>Email Support
                                </a>
                                <a href="tel:<?php echo SUPPORT_PHONE; ?>" class="btn-modern btn-outline-modern">
                                    <i class="fas fa-phone"></i>Call Support
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Document Viewer Modal -->
    <div class="modal fade" id="documentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Document Viewer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <iframe id="documentFrame" style="width: 100%; height: 500px; border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function viewDocument(filePath) {
            const modal = new bootstrap.Modal(document.getElementById('documentModal'));
            const frame = document.getElementById('documentFrame');
            
            // For security, you should implement a proper document viewer endpoint
            frame.src = `<?php echo SITE_URL; ?>/view-document.php?file=${encodeURIComponent(filePath)}`;
            modal.show();
        }
        
        function downloadDocument(filePath) {
            // Create a temporary link to download the file
            const link = document.createElement('a');
            link.href = `<?php echo SITE_URL; ?>/download-document.php?file=${encodeURIComponent(filePath)}`;
            link.download = '';
            link.click();
        }
        
        // Auto-refresh verification status every 30 seconds
        setInterval(() => {
            // Check for verification updates via AJAX
            fetch('<?php echo SITE_URL; ?>/ajax/check-verification-updates.php')
                .then(response => response.json())
                .then(data => {
                    if (data.hasUpdates) {
                        location.reload();
                    }
                })
                .catch(console.error);
        }, 30000);
        
        // Show notification if new verification updates
        document.addEventListener('DOMContentLoaded', function() {
            // Check if there are recent verifications to highlight
            const recentVerifications = document.querySelectorAll('.badge-verified');
            recentVerifications.forEach(badge => {
                // Add pulse animation for recently verified documents
                badge.style.animation = 'pulse 2s infinite';
            });
            
            setTimeout(() => {
                recentVerifications.forEach(badge => {
                    badge.style.animation = '';
                });
            }, 10000);
        });
    </script>
</body>
</html>