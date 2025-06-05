<?php
/**
 * Programs Management - View Program Details
 * 
 * File: admin/programs/view.php
 * Purpose: View detailed program information and statistics
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../../config/config.php';
require_once '../../classes/Program.php';
require_once '../../classes/Application.php';
require_once '../../classes/User.php';

// Require admin login
requireLogin();

$database = new Database();
$db = $database->getConnection();

$program = new Program($db);
$application = new Application($db);
$user = new User($db);

$current_user_role = getCurrentUserRole();
$current_user_id = getCurrentUserId();

// Get program ID from URL
$program_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$program_id) {
    header('Location: list.php?error=invalid_program');
    exit;
}

// Get program details
$program_details = $program->getById($program_id);

if (!$program_details) {
    header('Location: list.php?error=program_not_found');
    exit;
}

// Check if user has permission to view this program
if ($current_user_role === ROLE_PROGRAM_ADMIN) {
    if (!$program->isProgramAdmin($current_user_id, $program_id)) {
        header('Location: list.php?error=access_denied');
        exit;
    }
}

// Get program statistics
$program_stats = $program->getStatistics($program_id);

// Get certificate requirements
$certificate_requirements = $program->getCertificateRequirements($program_id);

// Get recent applications for this program
$recent_applications_query = "
    SELECT a.*, u.email as student_email
    FROM applications a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.program_id = :program_id 
    AND a.academic_year = :academic_year
    ORDER BY a.date_created DESC 
    LIMIT 10
";

$stmt = $db->prepare($recent_applications_query);
$stmt->bindParam(':program_id', $program_id);
$stmt->bindValue(':academic_year', '2025-26'); // Current academic year
$stmt->execute();
$recent_applications = $stmt->fetchAll();

// Get monthly application trends for this program
$monthly_trends_query = "
    SELECT 
        DATE_FORMAT(date_created, '%Y-%m') as month,
        COUNT(*) as application_count,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count
    FROM applications 
    WHERE program_id = :program_id 
    AND date_created >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(date_created, '%Y-%m')
    ORDER BY month ASC
";

$stmt = $db->prepare($monthly_trends_query);
$stmt->bindParam(':program_id', $program_id);
$stmt->execute();
$monthly_trends = $stmt->fetchAll();

// Get application status distribution
$status_distribution_query = "
    SELECT 
        status,
        COUNT(*) as count
    FROM applications 
    WHERE program_id = :program_id 
    AND academic_year = :academic_year
    GROUP BY status
";

$stmt = $db->prepare($status_distribution_query);
$stmt->bindParam(':program_id', $program_id);
$stmt->bindValue(':academic_year', '2025-26');
$stmt->execute();
$status_distribution = $stmt->fetchAll();

// Get top performing students (approved applications with highest scores)
$top_students_query = "
    SELECT a.*, u.email as student_email,
           AVG(ar.overall_score) as avg_score
    FROM applications a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN application_reviews ar ON a.id = ar.application_id
    WHERE a.program_id = :program_id 
    AND a.status = 'approved'
    AND a.academic_year = :academic_year
    GROUP BY a.id
    HAVING avg_score IS NOT NULL
    ORDER BY avg_score DESC
    LIMIT 5
";

$stmt = $db->prepare($top_students_query);
$stmt->bindParam(':program_id', $program_id);
$stmt->bindValue(':academic_year', '2025-26');
$stmt->execute();
$top_students = $stmt->fetchAll();

// Get document verification statistics
$doc_verification_query = "
    SELECT 
        ct.name as certificate_name,
        COUNT(ad.id) as total_submissions,
        COUNT(CASE WHEN ad.is_verified = 1 THEN 1 END) as verified_submissions,
        COUNT(CASE WHEN ad.is_verified = 0 THEN 1 END) as pending_submissions
    FROM certificate_types ct
    LEFT JOIN program_certificate_requirements pcr ON ct.id = pcr.certificate_type_id
    LEFT JOIN application_documents ad ON ct.id = ad.certificate_type_id
    LEFT JOIN applications a ON ad.application_id = a.id
    WHERE pcr.program_id = :program_id
    AND (a.program_id = :program_id OR a.program_id IS NULL)
    GROUP BY ct.id, ct.name
    ORDER BY pcr.display_order ASC
";

$stmt = $db->prepare($doc_verification_query);
$stmt->bindParam(':program_id', $program_id);
$stmt->execute();
$doc_verification_stats = $stmt->fetchAll();

$page_title = 'Program Details: ' . $program_details['program_name'];
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    
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
        
        .program-header-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary-color);
        }
        
        .info-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
            height: 100%;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid var(--primary-color);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card.success { border-left-color: var(--success-color); }
        .stat-card.warning { border-left-color: var(--warning-color); }
        .stat-card.danger { border-left-color: var(--danger-color); }
        .stat-card.info { border-left-color: var(--info-color); }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-number.success { color: var(--success-color); }
        .stat-number.warning { color: var(--warning-color); }
        .stat-number.danger { color: var(--danger-color); }
        .stat-number.info { color: var(--info-color); }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        
        .info-value {
            color: #6c757d;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-inactive {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .requirements-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .requirement-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .requirement-item:last-child {
            border-bottom: none;
        }
        
        .requirement-name {
            font-weight: 500;
        }
        
        .requirement-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .required-badge {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .optional-badge {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info-color);
        }
        
        .application-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s ease;
        }
        
        .application-item:hover {
            background: #f8f9fa;
        }
        
        .application-item:last-child {
            border-bottom: none;
        }
        
        .application-number {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            padding: 1rem;
        }
        
        .progress-bar-custom {
            height: 20px;
            border-radius: 10px;
            background: #e9ecef;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, var(--success-color), #20c997);
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .verification-stats {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .verification-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
        }
        
        .verification-progress {
            width: 200px;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .verification-progress-fill {
            height: 100%;
            background: linear-gradient(135deg, var(--success-color), #20c997);
            transition: width 0.3s ease;
        }
        
        .quick-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .quick-action-btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .student-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        
        .student-score {
            font-weight: 600;
            color: var(--success-color);
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
                        <div class="page-pretitle">Programs Management</div>
                        <h2 class="page-title">
                            <i class="fas fa-graduation-cap me-2"></i>
                            Program Details
                        </h2>
                    </div>
                    <div class="col-auto ms-auto">
                        <div class="btn-list">
                            <?php if ($current_user_role === ROLE_ADMIN || $program->isProgramAdmin($current_user_id, $program_id)): ?>
                            <a href="edit.php?id=<?php echo $program_id; ?>" class="btn btn-warning">
                                <i class="fas fa-edit me-2"></i>Edit Program
                            </a>
                            <a href="requirements.php?id=<?php echo $program_id; ?>" class="btn btn-info">
                                <i class="fas fa-file-alt me-2"></i>Requirements
                            </a>
                            <?php endif; ?>
                            <a href="../applications/list.php?program_id=<?php echo $program_id; ?>" class="btn btn-success">
                                <i class="fas fa-list me-2"></i>View Applications
                            </a>
                            <a href="list.php" class="btn btn-light">
                                <i class="fas fa-list me-2"></i>Back to Programs
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="page-body">
            <div class="container-xl">
                <!-- Program Header -->
                <div class="program-header-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center mb-2">
                                <h3 class="mb-0 me-3"><?php echo htmlspecialchars($program_details['program_name']); ?></h3>
                                <span class="status-badge <?php echo $program_details['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $program_details['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            <div class="d-flex gap-3 text-muted mb-2">
                                <span><i class="fas fa-code me-1"></i> <?php echo htmlspecialchars($program_details['program_code']); ?></span>
                                <span><i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($program_details['department']); ?></span>
                                <span><i class="fas fa-clock me-1"></i> <?php echo $program_details['duration_years']; ?> Years</span>
                                <span><i class="fas fa-users me-1"></i> <?php echo $program_details['total_seats']; ?> Seats</span>
                            </div>
                            <div class="quick-actions">
                                <a href="../applications/list.php?program_id=<?php echo $program_id; ?>&status=submitted" 
                                   class="quick-action-btn btn btn-outline-primary">
                                    <i class="fas fa-clock me-1"></i>Pending Reviews
                                </a>
                                <a href="../applications/list.php?program_id=<?php echo $program_id; ?>&status=approved" 
                                   class="quick-action-btn btn btn-outline-success">
                                    <i class="fas fa-check me-1"></i>Approved
                                </a>
                                <a href="../reports/program.php?id=<?php echo $program_id; ?>" 
                                   class="quick-action-btn btn btn-outline-info">
                                    <i class="fas fa-chart-bar me-1"></i>Reports
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="badge bg-primary fs-6 mb-2"><?php echo htmlspecialchars($program_details['program_type']); ?></div>
                            <?php if ($program_details['admin_email']): ?>
                            <div class="text-muted small">
                                <i class="fas fa-user-tie me-1"></i>
                                Admin: <?php echo htmlspecialchars($program_details['admin_email']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <?php if ($program_stats): ?>
                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $program_stats['total_applications']; ?></div>
                            <div class="text-muted">Total Applications</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="stat-card success">
                            <div class="stat-number success"><?php echo $program_stats['approved_applications']; ?></div>
                            <div class="text-muted">Approved</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="stat-card warning">
                            <div class="stat-number warning"><?php echo $program_stats['under_review_applications']; ?></div>
                            <div class="text-muted">Under Review</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="stat-card info">
                            <div class="stat-number info"><?php echo $program_stats['submitted_applications']; ?></div>
                            <div class="text-muted">Submitted</div>
                        </div>
                    </div>
                </div>
                
                <!-- Seat Utilization -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="info-card">
                            <h5 class="card-title">
                                <i class="fas fa-chart-pie"></i>
                                Seat Utilization
                            </h5>
                            <?php 
                            $fill_percentage = $program_details['total_seats'] > 0 ? 
                                round(($program_stats['approved_applications'] / $program_details['total_seats']) * 100, 1) : 0;
                            ?>
                            <div class="progress-bar-custom">
                                <div class="progress-fill" style="width: <?php echo min(100, $fill_percentage); ?>%">
                                    <?php echo $program_stats['approved_applications']; ?> / <?php echo $program_details['total_seats']; ?> (<?php echo $fill_percentage; ?>%)
                                </div>
                            </div>
                            <div class="text-muted small">
                                <?php if ($fill_percentage >= 90): ?>
                                    <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                    Nearly full - <?php echo $program_details['total_seats'] - $program_stats['approved_applications']; ?> seats remaining
                                <?php elseif ($fill_percentage >= 75): ?>
                                    <i class="fas fa-info-circle text-info me-1"></i>
                                    <?php echo $program_details['total_seats'] - $program_stats['approved_applications']; ?> seats remaining
                                <?php else: ?>
                                    <i class="fas fa-check-circle text-success me-1"></i>
                                    <?php echo $program_details['total_seats'] - $program_stats['approved_applications']; ?> seats available
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Program Information -->
                <div class="row g-4 mb-4">
                    <!-- Basic Information -->
                    <div class="col-md-6">
                        <div class="info-card">
                            <h5 class="card-title">
                                <i class="fas fa-info-circle"></i>
                                Basic Information
                            </h5>
                            
                            <div class="info-row">
                                <span class="info-label">Program Code</span>
                                <span class="info-value"><?php echo htmlspecialchars($program_details['program_code']); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Program Type</span>
                                <span class="info-value"><?php echo htmlspecialchars($program_details['program_type']); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Department</span>
                                <span class="info-value"><?php echo htmlspecialchars($program_details['department']); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Duration</span>
                                <span class="info-value"><?php echo $program_details['duration_years']; ?> Years</span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Total Seats</span>
                                <span class="info-value"><?php echo $program_details['total_seats']; ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Display Order</span>
                                <span class="info-value"><?php echo $program_details['display_order']; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Application Dates -->
                    <div class="col-md-6">
                        <div class="info-card">
                            <h5 class="card-title">
                                <i class="fas fa-calendar-alt"></i>
                                Application Period
                            </h5>
                            
                            <div class="info-row">
                                <span class="info-label">Start Date</span>
                                <span class="info-value"><?php echo formatDate($program_details['application_start_date']); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">End Date</span>
                                <span class="info-value"><?php echo formatDate($program_details['application_end_date']); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Status</span>
                                <span class="info-value">
                                    <?php
                                    $today = date('Y-m-d');
                                    $start_date = $program_details['application_start_date'];
                                    $end_date = $program_details['application_end_date'];
                                    
                                    if ($today < $start_date) {
                                        echo '<span class="badge bg-secondary">Not Started</span>';
                                    } elseif ($today > $end_date) {
                                        echo '<span class="badge bg-danger">Closed</span>';
                                    } else {
                                        echo '<span class="badge bg-success">Open</span>';
                                    }
                                    ?>
                                </span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Created</span>
                                <span class="info-value"><?php echo formatDateTime($program_details['date_created']); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Last Updated</span>
                                <span class="info-value"><?php echo formatDateTime($program_details['date_updated']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Program Details -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <div class="info-card">
                            <h5 class="card-title">
                                <i class="fas fa-file-text"></i>
                                Program Details
                            </h5>
                            
                            <?php if (!empty($program_details['description'])): ?>
                            <div class="mb-3">
                                <h6>Description</h6>
                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($program_details['description'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <h6>Eligibility Criteria</h6>
                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($program_details['eligibility_criteria'])); ?></p>
                            </div>
                            
                            <?php if (!empty($program_details['fees_structure'])): ?>
                            <div class="mb-3">
                                <h6>Fee Structure</h6>
                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($program_details['fees_structure'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Certificate Requirements and Document Verification -->
                <div class="row g-4 mb-4">
                    <!-- Certificate Requirements -->
                    <div class="col-md-6">
                        <div class="info-card">
                            <h5 class="card-title">
                                <i class="fas fa-file-certificate"></i>
                                Certificate Requirements
                                <span class="badge bg-primary ms-2"><?php echo count($certificate_requirements); ?></span>
                            </h5>
                            
                            <?php if (empty($certificate_requirements)): ?>
                            <div class="empty-state">
                                <i class="fas fa-file-alt fa-2x mb-2"></i>
                                <p class="text-muted mb-0">No certificate requirements configured</p>
                                <?php if ($current_user_role === ROLE_ADMIN || $program->isProgramAdmin($current_user_id, $program_id)): ?>
                                <a href="requirements.php?id=<?php echo $program_id; ?>" class="btn btn-sm btn-outline-primary mt-2">
                                    Configure Requirements
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <ul class="requirements-list">
                                <?php foreach ($certificate_requirements as $req): ?>
                                <li class="requirement-item">
                                    <div>
                                        <div class="requirement-name"><?php echo htmlspecialchars($req['certificate_name']); ?></div>
                                        <?php if (!empty($req['description'])): ?>
                                        <div class="text-muted small"><?php echo htmlspecialchars($req['description']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="requirement-badge <?php echo $req['is_required'] ? 'required-badge' : 'optional-badge'; ?>">
                                        <?php echo $req['is_required'] ? 'Required' : 'Optional'; ?>
                                    </span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            
                            <?php if ($current_user_role === ROLE_ADMIN || $program->isProgramAdmin($current_user_id, $program_id)): ?>
                            <div class="text-center mt-3">
                                <a href="requirements.php?id=<?php echo $program_id; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit me-1"></i>Manage Requirements
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Document Verification Statistics -->
                    <div class="col-md-6">
                        <div class="info-card">
                            <h5 class="card-title">
                                <i class="fas fa-check-circle"></i>
                                Document Verification
                            </h5>
                            
                            <?php if (empty($doc_verification_stats)): ?>
                            <div class="empty-state">
                                <i class="fas fa-file-check fa-2x mb-2"></i>
                                <p class="text-muted mb-0">No document submissions yet</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($doc_verification_stats as $doc_stat): ?>
                            <div class="verification-stats">
                                <div class="verification-item">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($doc_stat['certificate_name']); ?></div>
                                        <div class="text-muted small">
                                            <?php echo $doc_stat['verified_submissions']; ?> verified / <?php echo $doc_stat['total_submissions']; ?> total
                                        </div>
                                    </div>
                                    <div>
                                        <?php 
                                        $verification_percentage = $doc_stat['total_submissions'] > 0 ? 
                                            round(($doc_stat['verified_submissions'] / $doc_stat['total_submissions']) * 100, 1) : 0;
                                        ?>
                                        <div class="verification-progress">
                                            <div class="verification-progress-fill" style="width: <?php echo $verification_percentage; ?>%"></div>
                                        </div>
                                        <div class="text-muted small"><?php echo $verification_percentage; ?>%</div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Applications and Top Students -->
                <div class="row g-4 mb-4">
                    <!-- Recent Applications -->
                    <div class="col-md-6">
                        <div class="info-card">
                            <h5 class="card-title">
                                <i class="fas fa-clock"></i>
                                Recent Applications
                                <span class="badge bg-info ms-2"><?php echo count($recent_applications); ?></span>
                            </h5>
                            
                            <?php if (empty($recent_applications)): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                <p class="text-muted mb-0">No applications yet</p>
                            </div>
                            <?php else: ?>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($recent_applications as $app): ?>
                                <div class="application-item">
                                    <div>
                                        <div class="application-number"><?php echo htmlspecialchars($app['application_number']); ?></div>
                                        <div class="text-muted small">
                                            <?php echo htmlspecialchars($app['student_name'] ?: 'Name not provided'); ?> • 
                                            <?php echo formatDate($app['date_created']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="status-badge status-<?php echo $app['status']; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $app['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="text-center mt-3">
                                <a href="../applications/list.php?program_id=<?php echo $program_id; ?>" class="btn btn-sm btn-outline-info">
                                    <i class="fas fa-list me-1"></i>View All Applications
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Top Performing Students -->
                    <div class="col-md-6">
                        <div class="info-card">
                            <h5 class="card-title">
                                <i class="fas fa-star"></i>
                                Top Performing Students
                                <span class="badge bg-warning ms-2"><?php echo count($top_students); ?></span>
                            </h5>
                            
                            <?php if (empty($top_students)): ?>
                            <div class="empty-state">
                                <i class="fas fa-trophy fa-2x mb-2"></i>
                                <p class="text-muted mb-0">No reviewed applications yet</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($top_students as $student): ?>
                            <div class="student-item">
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($student['student_name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($student['application_number']); ?></div>
                                </div>
                                <div class="student-score">
                                    <?php echo number_format($student['avg_score'], 1); ?>/10
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Application Trends Chart -->
                <?php if (!empty($monthly_trends)): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="info-card">
                            <h5 class="card-title">
                                <i class="fas fa-chart-line"></i>
                                Application Trends (Last 12 Months)
                            </h5>
                            <div class="chart-container">
                                <canvas id="trendsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Status Distribution Chart -->
                <?php if (!empty($status_distribution)): ?>
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="info-card">
                            <h5 class="card-title">
                                <i class="fas fa-chart-pie"></i>
                                Application Status Distribution
                            </h5>
                            <div class="chart-container">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <script>
        // Application trends chart
        <?php if (!empty($monthly_trends)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('trendsChart').getContext('2d');
            
            const monthLabels = <?php echo json_encode(array_column($monthly_trends, 'month')); ?>;
            const applicationData = <?php echo json_encode(array_column($monthly_trends, 'application_count')); ?>;
            const approvedData = <?php echo json_encode(array_column($monthly_trends, 'approved_count')); ?>;
            
            const trendsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: monthLabels.map(month => {
                        const date = new Date(month + '-01');
                        return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
                    }),
                    datasets: [{
                        label: 'Total Applications',
                        data: applicationData,
                        borderColor: '#0054a6',
                        backgroundColor: 'rgba(0, 84, 166, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Approved Applications',
                        data: approvedData,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        });
        <?php endif; ?>
        
        // Status distribution chart
        <?php if (!empty($status_distribution)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            
            const statusLabels = <?php echo json_encode(array_column($status_distribution, 'status')); ?>;
            const statusData = <?php echo json_encode(array_column($status_distribution, 'count')); ?>;
            
            const statusColors = {
                'draft': '#6c757d',
                'submitted': '#17a2b8',
                'under_review': '#ffc107',
                'approved': '#28a745',
                'rejected': '#dc3545',
                'frozen': '#007bff',
                'cancelled': '#6f42c1'
            };
            
            const backgroundColors = statusLabels.map(status => statusColors[status] || '#6c757d');
            
            const statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusLabels.map(status => status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ')),
                    datasets: [{
                        data: statusData,
                        backgroundColor: backgroundColors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        });
        <?php endif; ?>
        
        // Animate progress bars
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-fill, .verification-progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
            
            // Animate stat numbers
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const target = parseInt(stat.textContent);
                if (target > 0) {
                    const increment = target / 30;
                    let current = 0;
                    
                    const updateStat = () => {
                        if (current < target) {
                            current += increment;
                            stat.textContent = Math.ceil(current);
                            requestAnimationFrame(updateStat);
                        } else {
                            stat.textContent = target;
                        }
                    };
                    
                    setTimeout(updateStat, Math.random() * 1000);
                }
            });
        });
        
        // Print functionality
        function printProgramDetails() {
            window.print();
        }
        
        // Export functionality (basic)
        function exportProgramData() {
            window.print();
        }
    </script>
</body>
</html>