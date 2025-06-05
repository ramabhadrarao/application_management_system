<?php
/**
 * Programs Management - List View
 * 
 * File: admin/programs/list.php
 * Purpose: Manage all programs with CRUD operations
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../../config/config.php';
require_once '../../classes/Program.php';
require_once '../../classes/User.php';

// Require admin login
requireLogin();
requirePermission('all');

$database = new Database();
$db = $database->getConnection();

$program = new Program($db);
$user = new User($db);

$current_user_role = getCurrentUserRole();
$current_user_id = getCurrentUserId();

// Handle program actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        
        // Toggle program status
        if (isset($_POST['toggle_status'])) {
            $program_id = (int)$_POST['program_id'];
            if ($program->toggleStatus($program_id)) {
                $message = 'Program status updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to update program status.';
                $message_type = 'danger';
            }
        }
        
        // Delete program
        if (isset($_POST['delete_program'])) {
            $program_id = (int)$_POST['program_id'];
            if ($program->delete($program_id)) {
                $message = 'Program deleted successfully.';
                $message_type = 'success';
            } else {
                $message = 'Cannot delete program. Applications exist for this program.';
                $message_type = 'warning';
            }
        }
    }
}

// Get filter parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'program_type' => $_GET['program_type'] ?? '',
    'department' => $_GET['department'] ?? '',
    'is_active' => isset($_GET['is_active']) ? (int)$_GET['is_active'] : null,
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = RECORDS_PER_PAGE;

// Get programs with filters
$programs = $program->getPrograms($filters, $page, $limit);
$total_programs = $program->getProgramCount($filters);
$total_pages = ceil($total_programs / $limit);

// Get available program types and departments
$program_types = $program->getProgramTypes();
$departments = $program->getAllDepartments();

$page_title = 'Manage Programs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
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
        }
        
        .program-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .program-ug { background: rgba(0, 123, 255, 0.1); color: #007bff; }
        .program-pg { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .program-diploma { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .program-certificate { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        
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
        
        .btn-action {
            padding: 0.375rem 0.75rem;
            margin: 0 0.125rem;
            border-radius: 6px;
            font-size: 0.8rem;
        }
        
        .pagination-wrapper {
            background: white;
            border-radius: 0 0 12px 12px;
            padding: 1rem 1.5rem;
            border-top: 1px solid #e9ecef;
        }
        
        .progress-bar-custom {
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, var(--success-color), #20c997);
            transition: width 0.3s ease;
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
                            <i class="fas fa-graduation-cap me-2"></i>
                            Manage Programs
                        </h2>
                    </div>
                    <div class="col-auto ms-auto">
                        <div class="btn-list">
                            <a href="add.php" class="btn btn-light">
                                <i class="fas fa-plus me-2"></i>Add Program
                            </a>
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
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="stats-cards">
                    <div class="row g-3">
                        <?php
                        $total_active = 0;
                        $total_inactive = 0;
                        $ug_programs = 0;
                        $pg_programs = 0;
                        
                        foreach ($programs as $prog) {
                            if ($prog['is_active']) $total_active++; else $total_inactive++;
                            if ($prog['program_type'] === 'UG') $ug_programs++;
                            if ($prog['program_type'] === 'PG') $pg_programs++;
                        }
                        ?>
                        <div class="col-sm-6 col-lg-3">
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $total_programs; ?></div>
                                <div class="text-muted">Total Programs</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="stat-card success">
                                <div class="stat-number success"><?php echo $total_active; ?></div>
                                <div class="text-muted">Active Programs</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="stat-card info">
                                <div class="stat-number info"><?php echo $ug_programs; ?></div>
                                <div class="text-muted">UG Programs</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="stat-card warning">
                                <div class="stat-number warning"><?php echo $pg_programs; ?></div>
                                <div class="text-muted">PG Programs</div>
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
                                   placeholder="Search by program name or code...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="program_type">
                                <option value="">All Types</option>
                                <?php foreach ($program_types as $type): ?>
                                <option value="<?php echo $type; ?>" 
                                        <?php echo $filters['program_type'] === $type ? 'selected' : ''; ?>>
                                    <?php echo $type; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept; ?>" 
                                        <?php echo $filters['department'] === $dept ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="is_active">
                                <option value="">All Status</option>
                                <option value="1" <?php echo $filters['is_active'] === 1 ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo $filters['is_active'] === 0 ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2 d-md-flex">
                                <button type="submit" class="btn btn-primary flex-fill">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                                <a href="list.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Programs Table -->
                <div class="data-table">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Program</th>
                                    <th>Type</th>
                                    <th>Department</th>
                                    <th>Duration</th>
                                    <th>Seats</th>
                                    <th>Applications</th>
                                    <th>Admin</th>
                                    <th>Status</th>
                                    <th width="180">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($programs)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                                        <h5>No Programs Found</h5>
                                        <p class="text-muted">No programs match your search criteria.</p>
                                        <a href="add.php" class="btn btn-primary">Add First Program</a>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($programs as $prog): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($prog['program_name']); ?></div>
                                            <div class="text-muted small">
                                                Code: <?php echo htmlspecialchars($prog['program_code']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="program-badge program-<?php echo strtolower($prog['program_type']); ?>">
                                            <?php echo htmlspecialchars($prog['program_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?php echo htmlspecialchars($prog['department']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo $prog['duration_years']; ?> Years
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo $prog['total_seats']; ?></div>
                                        <?php if ($prog['total_applications'] > 0): ?>
                                        <div class="progress-bar-custom mt-1">
                                            <?php 
                                            $fill_percentage = min(100, ($prog['total_applications'] / $prog['total_seats']) * 100);
                                            ?>
                                            <div class="progress-fill" style="width: <?php echo $fill_percentage; ?>%"></div>
                                        </div>
                                        <div class="text-muted small"><?php echo round($fill_percentage, 1); ?>% filled</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo $prog['total_applications'] ?? 0; ?></div>
                                        <div class="text-muted small">Applications</div>
                                    </td>
                                    <td>
                                        <?php if ($prog['admin_email']): ?>
                                            <div class="text-truncate" style="max-width: 120px;" title="<?php echo htmlspecialchars($prog['admin_email']); ?>">
                                                <?php echo htmlspecialchars($prog['admin_email']); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $prog['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $prog['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?php echo $prog['id']; ?>" 
                                               class="btn btn-outline-info btn-action" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $prog['id']; ?>" 
                                               class="btn btn-outline-primary btn-action" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="requirements.php?id=<?php echo $prog['id']; ?>" 
                                               class="btn btn-outline-warning btn-action" title="Manage Requirements">
                                                <i class="fas fa-file-alt"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-<?php echo $prog['is_active'] ? 'warning' : 'success'; ?> btn-action" 
                                                    onclick="toggleProgramStatus(<?php echo $prog['id']; ?>, '<?php echo $prog['is_active'] ? 'deactivate' : 'activate'; ?>')" 
                                                    title="<?php echo $prog['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas fa-<?php echo $prog['is_active'] ? 'ban' : 'check'; ?>"></i>
                                            </button>
                                            <?php if (($prog['total_applications'] ?? 0) == 0): ?>
                                            <button type="button" class="btn btn-outline-danger btn-action" 
                                                    onclick="deleteProgram(<?php echo $prog['id']; ?>)" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
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
                                    <?php echo min($page * $limit, $total_programs); ?> of 
                                    <?php echo $total_programs; ?> programs
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
    
    <!-- Hidden Forms for Actions -->
    <form id="actionForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="program_id" id="actionProgramId">
        <input type="hidden" name="action_type" id="actionType">
    </form>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleProgramStatus(programId, action) {
            const actionText = action === 'activate' ? 'activate' : 'deactivate';
            
            if (confirm(`Are you sure you want to ${actionText} this program?`)) {
                const form = document.getElementById('actionForm');
                document.getElementById('actionProgramId').value = programId;
                form.innerHTML += `<input type="hidden" name="toggle_status" value="1">`;
                form.submit();
            }
        }
        
        function deleteProgram(programId) {
            if (confirm('Are you sure you want to delete this program? This action cannot be undone.')) {
                const form = document.getElementById('actionForm');
                document.getElementById('actionProgramId').value = programId;
                form.innerHTML += `<input type="hidden" name="delete_program" value="1">`;
                form.submit();
            }
        }
        
        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Animate progress bars
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 300);
            });
        });
    </script>
</body>
</html>