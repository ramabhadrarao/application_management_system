<?php
/**
 * Applications Management - List View (UPDATED WITH SETTINGS AND PRINT)
 * 
 * File: admin/applications/list.php
 * Purpose: Manage all applications with advanced filtering and bulk operations
 * Author: Student Application Management System
 * Created: 2025
 */

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Initialize variables
$message = '';
$message_type = '';

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        
        // Bulk status update
        if (isset($_POST['bulk_action']) && !empty($_POST['selected_applications'])) {
            $action = $_POST['bulk_action'];
            $selected_apps = $_POST['selected_applications'];
            $comments = $_POST['bulk_comments'] ?? '';
            
            $success_count = 0;
            foreach ($selected_apps as $app_id) {
                if ($action === 'approve' || $action === 'reject' || $action === 'under_review') {
                    $status = '';
                    if ($action === 'approve') $status = STATUS_APPROVED;
                    elseif ($action === 'reject') $status = STATUS_REJECTED;
                    elseif ($action === 'under_review') $status = STATUS_UNDER_REVIEW;
                    
                    if ($application->updateStatus($app_id, $status, $current_user_id, $comments)) {
                        $success_count++;
                    }
                }
            }
            
            if ($success_count > 0) {
                $message = "Successfully updated $success_count applications.";
                $message_type = 'success';
            } else {
                $message = "No applications were updated.";
                $message_type = 'warning';
            }
        }
        
        // Single application status update
        if (isset($_POST['update_status'])) {
            $app_id = (int)$_POST['application_id'];
            $status = $_POST['status'];
            $comments = $_POST['comments'] ?? '';
            
            if ($application->updateStatus($app_id, $status, $current_user_id, $comments)) {
                $message = 'Application status updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to update application status.';
                $message_type = 'danger';
            }
        }
    }
}

// Get filter parameters with defaults
$filters = [
    'search' => isset($_GET['search']) ? $_GET['search'] : '',
    'program_id' => isset($_GET['program_id']) ? $_GET['program_id'] : '',
    'status' => isset($_GET['status']) ? $_GET['status'] : '',
    'academic_year' => isset($_GET['academic_year']) ? $_GET['academic_year'] : CURRENT_ACADEMIC_YEAR,
    'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
    'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : '',
];

// Program admin filter
$program_ids = [];
if ($current_user_role === ROLE_PROGRAM_ADMIN) {
    $admin_programs = $program->getProgramsByAdmin($current_user_id);
    $program_ids = array_column($admin_programs, 'id');
    
    if (!empty($program_ids) && !empty($filters['program_id']) && !in_array($filters['program_id'], $program_ids)) {
        $filters['program_id'] = ''; // Reset if trying to access unauthorized program
    }
}

$page = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
$limit = RECORDS_PER_PAGE;

// Get applications with filters
$applications = $application->getApplications($filters, $page, $limit);
$total_applications = $application->getApplicationCount($filters);
$total_pages = ceil($total_applications / $limit);

// Get all programs for filter (restricted for program admins)
if ($current_user_role === ROLE_ADMIN) {
    $programs = $program->getAllActivePrograms();
} else {
    $programs = $program->getProgramsByAdmin($current_user_id);
}

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft,
        COUNT(CASE WHEN status = 'submitted' THEN 1 END) as submitted,
        COUNT(CASE WHEN status = 'under_review' THEN 1 END) as under_review,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
        COUNT(CASE WHEN status = 'frozen' THEN 1 END) as frozen
    FROM applications 
    WHERE academic_year = :academic_year
";

if ($current_user_role === ROLE_PROGRAM_ADMIN && !empty($program_ids)) {
    $stats_query .= " AND program_id IN (" . implode(',', array_map('intval', $program_ids)) . ")";
}

try {
    $stmt = $db->prepare($stats_query);
    $stmt->bindValue(':academic_year', CURRENT_ACADEMIC_YEAR);
    $stmt->execute();
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    // Handle database error
    $stats = [
        'total' => 0, 'draft' => 0, 'submitted' => 0, 
        'under_review' => 0, 'approved' => 0, 'rejected' => 0, 'frozen' => 0
    ];
    error_log("Statistics query error: " . $e->getMessage());
}

$page_title = 'Manage Applications';
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
            --secondary-color: #6c757d;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .stats-cards {
            margin-bottom: 2rem;
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
        
        .stat-card.draft { border-left-color: var(--secondary-color); }
        .stat-card.submitted { border-left-color: var(--info-color); }
        .stat-card.under-review { border-left-color: var(--warning-color); }
        .stat-card.approved { border-left-color: var(--success-color); }
        .stat-card.rejected { border-left-color: var(--danger-color); }
        .stat-card.frozen { border-left-color: var(--primary-color); }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-number.draft { color: var(--secondary-color); }
        .stat-number.submitted { color: var(--info-color); }
        .stat-number.under-review { color: var(--warning-color); }
        .stat-number.approved { color: var(--success-color); }
        .stat-number.rejected { color: var(--danger-color); }
        .stat-number.frozen { color: var(--primary-color); }
        
        .filter-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .data-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table th {
            background: #f8f9fa;
            border: none;
            font-weight: 600;
            color: #495057;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-draft { background: rgba(108, 117, 125, 0.1); color: var(--secondary-color); }
        .status-submitted { background: rgba(23, 162, 184, 0.1); color: var(--info-color); }
        .status-under_review { background: rgba(255, 193, 7, 0.1); color: var(--warning-color); }
        .status-approved { background: rgba(40, 167, 69, 0.1); color: var(--success-color); }
        .status-rejected { background: rgba(220, 53, 69, 0.1); color: var(--danger-color); }
        .status-frozen { background: rgba(0, 84, 166, 0.1); color: var(--primary-color); }
        
        .btn-action {
            padding: 0.375rem 0.75rem;
            margin: 0 0.125rem;
            border-radius: 6px;
            font-size: 0.8rem;
        }
        
        .bulk-actions {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: none;
        }
        
        .progress-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .progress-circle {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .progress-complete {
            background: var(--success-color);
        }
        
        .progress-incomplete {
            background: var(--danger-color);
        }
        
        .application-number {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .student-info {
            max-width: 200px;
        }
        
        .pagination-wrapper {
            background: white;
            border-radius: 0 0 12px 12px;
            padding: 1rem 1.5rem;
            border-top: 1px solid #e9ecef;
        }
        
        .quick-status-buttons {
            display: flex;
            gap: 0.25rem;
        }
        
        .quick-status-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.7rem;
            border-radius: 4px;
        }
        
        /* Print modal styles */
        .print-modal .modal-body {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .print-options .form-check {
            margin-bottom: 0.5rem;
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
                        <div class="page-pretitle">Administration</div>
                        <h2 class="page-title">
                            <i class="fas fa-file-alt me-2"></i>
                            Manage Applications
                        </h2>
                    </div>
                    <div class="col-auto ms-auto">
                        <div class="btn-list">
                            <button class="btn btn-light" onclick="showPrintModal()">
                                <i class="fas fa-print me-2"></i>Print
                            </button>
                            <a href="export.php?<?php echo http_build_query($filters); ?>" class="btn btn-light">
                                <i class="fas fa-download me-2"></i>Export
                            </a>
                            <?php if ($current_user_role === ROLE_ADMIN): ?>
                            <a href="<?php echo SITE_URL; ?>/admin/applications/settings.php" class="btn btn-light">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a>
                            <?php endif; ?>
                            <div class="dropdown">
                                <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-filter me-2"></i>Quick Filters
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="?status=submitted">Submitted Applications</a></li>
                                    <li><a class="dropdown-item" href="?status=under_review">Under Review</a></li>
                                    <li><a class="dropdown-item" href="?status=approved">Approved</a></li>
                                    <li><a class="dropdown-item" href="?status=rejected">Rejected</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="pending.php">Pending Reviews</a></li>
                                </ul>
                            </div>
                            <a href="../dashboard.php" class="btn btn-light">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="page-body">
            <div class="container-xl">
                <!-- Success/Error Messages -->
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="stats-cards">
                    <div class="row g-3">
                        <div class="col-sm-6 col-lg-2">
                            <div class="stat-card">
                                <div class="stat-number"><?php echo (int)$stats['total']; ?></div>
                                <div class="text-muted">Total</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <div class="stat-card submitted">
                                <div class="stat-number submitted"><?php echo (int)$stats['submitted']; ?></div>
                                <div class="text-muted">Submitted</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <div class="stat-card under-review">
                                <div class="stat-number under-review"><?php echo (int)$stats['under_review']; ?></div>
                                <div class="text-muted">Under Review</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <div class="stat-card approved">
                                <div class="stat-number approved"><?php echo (int)$stats['approved']; ?></div>
                                <div class="text-muted">Approved</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <div class="stat-card rejected">
                                <div class="stat-number rejected"><?php echo (int)$stats['rejected']; ?></div>
                                <div class="text-muted">Rejected</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <div class="stat-card frozen">
                                <div class="stat-number frozen"><?php echo (int)$stats['frozen']; ?></div>
                                <div class="text-muted">Frozen</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filter-card">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   value="<?php echo htmlspecialchars($filters['search']); ?>"
                                   placeholder="Application number, name, email...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Program</label>
                            <select class="form-select" name="program_id">
                                <option value="">All Programs</option>
                                <?php if (!empty($programs)): ?>
                                    <?php foreach ($programs as $prog): ?>
                                    <option value="<?php echo (int)$prog['id']; ?>" 
                                            <?php echo $filters['program_id'] == $prog['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prog['program_code']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="draft" <?php echo $filters['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="submitted" <?php echo $filters['status'] === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                <option value="under_review" <?php echo $filters['status'] === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                <option value="approved" <?php echo $filters['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="frozen" <?php echo $filters['status'] === 'frozen' ? 'selected' : ''; ?>>Frozen</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control" name="date_from" 
                                   value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control" name="date_to" 
                                   value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                                <a href="list.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <form method="POST" id="bulkForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="row align-items-end g-3">
                            <div class="col-auto">
                                <span class="fw-bold"><span id="selectedCount">0</span> applications selected</span>
                            </div>
                            <div class="col-auto">
                                <select name="bulk_action" class="form-select" required>
                                    <option value="">Choose action...</option>
                                    <option value="under_review">Move to Under Review</option>
                                    <option value="approve">Approve Applications</option>
                                    <option value="reject">Reject Applications</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <input type="text" name="bulk_comments" class="form-control" 
                                       placeholder="Comments (optional)" style="width: 200px;">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-warning">Apply</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearSelection()">Clear</button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Applications Table -->
                <div class="data-table">
                    <div class="table-responsive" style="max-height: 600px;">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" class="form-check-input" id="selectAll">
                                    </th>
                                    <th>Application</th>
                                    <th>Student</th>
                                    <th>Program</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th width="200">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($applications)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                        <h5>No Applications Found</h5>
                                        <p class="text-muted">No applications match your search criteria.</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input app-checkbox" 
                                               name="selected_applications[]" value="<?php echo (int)$app['id']; ?>">
                                    </td>
                                    <td>
                                        <div class="application-number"><?php echo htmlspecialchars($app['application_number']); ?></div>
                                        <div class="text-muted small">ID: <?php echo (int)$app['id']; ?></div>
                                    </td>
                                    <td>
                                        <div class="student-info">
                                            <div class="fw-bold"><?php echo htmlspecialchars($app['student_name'] ?: 'Not provided'); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($app['email']); ?></div>
                                            <?php if (!empty($app['mobile_number'])): ?>
                                            <div class="text-muted small">
                                                <i class="fas fa-phone fa-xs"></i> <?php echo htmlspecialchars($app['mobile_number']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($app['program_code']); ?></span>
                                        <div class="text-muted small"><?php echo htmlspecialchars($app['program_name']); ?></div>
                                    </td>
                                    <td>
                                        <div class="progress-indicator">
                                            <?php 
                                            $docs_count = isset($app['documents_count']) ? (int)$app['documents_count'] : 0;
                                            $required_docs = isset($app['required_documents']) ? (int)$app['required_documents'] : 1;
                                            $docs_complete = ($docs_count > 0 && $docs_count >= $required_docs);
                                            ?>
                                            <div class="progress-circle <?php echo $docs_complete ? 'progress-complete' : 'progress-incomplete'; ?>">
                                                <?php echo $docs_count; ?>
                                            </div>
                                            <span class="small text-muted">
                                                <?php echo $docs_count; ?>/<?php echo $required_docs; ?> docs
                                            </span>
                                        </div>
                                        <?php if (isset($app['verified_documents']) && $app['verified_documents'] > 0): ?>
                                        <div class="text-success small">
                                            <i class="fas fa-check-circle fa-xs"></i> <?php echo (int)$app['verified_documents']; ?> verified
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $app['status']; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $app['status'])); ?>
                                        </span>
                                        <?php if (in_array($app['status'], ['submitted', 'under_review'])): ?>
                                        <div class="quick-status-buttons mt-1">
                                            <button type="button" class="btn btn-success" 
                                                    onclick="quickStatusUpdate(<?php echo (int)$app['id']; ?>, 'approved')" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger" 
                                                    onclick="quickStatusUpdate(<?php echo (int)$app['id']; ?>, 'rejected')" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($app['submitted_at'])): ?>
                                            <div><?php echo formatDate($app['submitted_at']); ?></div>
                                            <div class="text-muted small"><?php echo date('H:i', strtotime($app['submitted_at'])); ?></div>
                                        <?php else: ?>
                                            <span class="text-muted">Not submitted</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="<?php echo SITE_URL; ?>/admin/applications/view.php?id=<?php echo (int)$app['id']; ?>" 
                                               class="btn btn-outline-info btn-action" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (in_array($app['status'], ['draft', 'frozen'])): ?>
                                            <a href="<?php echo SITE_URL; ?>/admin/applications/edit.php?id=<?php echo (int)$app['id']; ?>" 
                                               class="btn btn-outline-primary btn-action" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php endif; ?>
                                            <a href="<?php echo SITE_URL; ?>/admin/applications/print.php?id=<?php echo (int)$app['id']; ?>" 
                                               class="btn btn-outline-secondary btn-action" title="Print" target="_blank">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <div class="dropdown">
                                                <button class="btn btn-outline-secondary btn-action dropdown-toggle" 
                                                        type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if (in_array($app['status'], ['submitted', 'frozen'])): ?>
                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo (int)$app['id']; ?>, 'under_review')">
                                                        <i class="fas fa-search text-warning me-2"></i>Move to Review
                                                    </a></li>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array($app['status'], ['submitted', 'under_review'])): ?>
                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo (int)$app['id']; ?>, 'approved')">
                                                        <i class="fas fa-check text-success me-2"></i>Approve
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo (int)$app['id']; ?>, 'rejected')">
                                                        <i class="fas fa-times text-danger me-2"></i>Reject
                                                    </a></li>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($app['status'] === 'frozen'): ?>
                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo (int)$app['id']; ?>, 'submitted')">
                                                        <i class="fas fa-unlock text-info me-2"></i>Unfreeze
                                                    </a></li>
                                                    <?php endif; ?>
                                                    
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/applications/communication.php?id=<?php echo (int)$app['id']; ?>">
                                                        <i class="fas fa-comments text-primary me-2"></i>Message Student
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/documents/verify.php?app_id=<?php echo (int)$app['id']; ?>">
                                                        <i class="fas fa-file-check text-info me-2"></i>Verify Documents
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-wrapper">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="text-muted">
                                    Showing <?php echo (($page - 1) * $limit) + 1; ?> to 
                                    <?php echo min($page * $limit, $total_applications); ?> of 
                                    <?php echo $total_applications; ?> applications
                                </span>
                            </div>
                            <div class="col-auto ms-auto">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination mb-0">
                                        <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
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
                <form method="POST" id="statusForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="application_id" id="statusApplicationId">
                        <input type="hidden" name="status" id="statusValue">
                        
                        <div class="mb-3">
                            <label class="form-label">Comments</label>
                            <textarea class="form-control" name="comments" rows="3" 
                                      placeholder="Add comments about this status change..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This action will change the application status and notify the student.
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
    
    <!-- Print Modal -->
    <div class="modal fade print-modal" id="printModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Print Applications</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="printForm" target="_blank" action="print-list.php" method="GET">
                        <!-- Pass current filters -->
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>">
                        <input type="hidden" name="program_id" value="<?php echo htmlspecialchars($filters['program_id']); ?>">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filters['status']); ?>">
                        <input type="hidden" name="academic_year" value="<?php echo htmlspecialchars($filters['academic_year']); ?>">
                        <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                        <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                        
                        <h6 class="mb-3">Select Print Options:</h6>
                        
                        <div class="print-options">
                            <div class="mb-3">
                                <label class="form-label">Print Format</label>
                                <select name="format" class="form-select">
                                    <option value="summary">Summary List</option>
                                    <option value="detailed">Detailed List</option>
                                    <option value="selected" id="printSelectedOption" disabled>Selected Applications Only</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Include Columns</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="columns[]" value="application_number" checked>
                                            <label class="form-check-label">Application Number</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="columns[]" value="student_name" checked>
                                            <label class="form-check-label">Student Name</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="columns[]" value="email" checked>
                                            <label class="form-check-label">Email</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="columns[]" value="mobile" checked>
                                            <label class="form-check-label">Mobile Number</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="columns[]" value="program" checked>
                                            <label class="form-check-label">Program</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="columns[]" value="status" checked>
                                            <label class="form-check-label">Status</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="columns[]" value="submitted_date" checked>
                                            <label class="form-check-label">Submitted Date</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="columns[]" value="documents">
                                            <label class="form-check-label">Document Status</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Page Orientation</label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="orientation" value="portrait" checked>
                                        <label class="form-check-label">Portrait</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="orientation" value="landscape">
                                        <label class="form-check-label">Landscape</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This will generate a printable report of applications based on your current filters.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitPrintForm()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Select All functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.app-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActions();
        });
        
        // Individual checkbox change
        document.querySelectorAll('.app-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActions);
        });
        
        function updateBulkActions() {
            const checkedBoxes = document.querySelectorAll('.app-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            const printSelectedOption = document.getElementById('printSelectedOption');
            
            if (checkedBoxes.length > 0) {
                bulkActions.style.display = 'block';
                selectedCount.textContent = checkedBoxes.length;
                
                // Enable print selected option
                if (printSelectedOption) {
                    printSelectedOption.disabled = false;
                    printSelectedOption.textContent = `Selected Applications (${checkedBoxes.length})`;
                }
                
                // Add hidden inputs for selected applications
                const form = document.getElementById('bulkForm');
                const existingInputs = form.querySelectorAll('input[name="selected_applications[]"]');
                existingInputs.forEach(input => input.remove());
                
                checkedBoxes.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_applications[]';
                    input.value = checkbox.value;
                    form.appendChild(input);
                });
            } else {
                bulkActions.style.display = 'none';
                if (printSelectedOption) {
                    printSelectedOption.disabled = true;
                    printSelectedOption.textContent = 'Selected Applications Only';
                }
            }
        }
        
        function clearSelection() {
            document.querySelectorAll('.app-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            document.getElementById('selectAll').checked = false;
            updateBulkActions();
        }
        
        function updateStatus(applicationId, status) {
            document.getElementById('statusApplicationId').value = applicationId;
            document.getElementById('statusValue').value = status;
            
            const modal = new bootstrap.Modal(document.getElementById('statusModal'));
            modal.show();
        }
        
        function quickStatusUpdate(applicationId, status) {
            if (confirm(`Are you sure you want to ${status} this application?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="application_id" value="${applicationId}">
                    <input type="hidden" name="status" value="${status === 'approved' ? '<?php echo STATUS_APPROVED; ?>' : '<?php echo STATUS_REJECTED; ?>'}">
                    <input type="hidden" name="comments" value="Quick ${status} action">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Print functionality
        function showPrintModal() {
            const modal = new bootstrap.Modal(document.getElementById('printModal'));
            modal.show();
        }
        
        function submitPrintForm() {
            const form = document.getElementById('printForm');
            const formatSelect = form.querySelector('select[name="format"]');
            
            // If printing selected applications, add the IDs to the form
            if (formatSelect.value === 'selected') {
                const checkedBoxes = document.querySelectorAll('.app-checkbox:checked');
                
                // Remove existing selected inputs
                form.querySelectorAll('input[name="selected[]"]').forEach(input => input.remove());
                
                // Add selected application IDs
                checkedBoxes.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected[]';
                    input.value = checkbox.value;
                    form.appendChild(input);
                });
            }
            
            form.submit();
            
            // Close modal after a delay
            setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('printModal')).hide();
            }, 500);
        }
        
        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-dismissible')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);
        
        // Animate stat cards
        document.addEventListener('DOMContentLoaded', function() {
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
    </script>
</body>
</html>