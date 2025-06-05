<?php
/**
 * Users Management - Students List
 * 
 * File: admin/users/students.php
 * Purpose: Manage all student users with search, filter, and bulk operations
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../../config/config.php';
require_once '../../classes/User.php';
require_once '../../classes/Program.php';
require_once '../../classes/Application.php';

// Require admin login
requireLogin();
requirePermission('all');

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$program = new Program($db);
$application = new Application($db);

$current_user_role = getCurrentUserRole();
$current_user_id = getCurrentUserId();

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        $action = $_POST['bulk_action'];
        $selected_users = $_POST['selected_users'] ?? [];
        
        if (!empty($selected_users)) {
            $success_count = 0;
            foreach ($selected_users as $user_id) {
                if ($action === 'activate' || $action === 'deactivate') {
                    if ($user->toggleUserStatus($user_id)) {
                        $success_count++;
                    }
                }
            }
            
            if ($success_count > 0) {
                $message = "Successfully updated $success_count users.";
                $message_type = 'success';
            } else {
                $message = "No users were updated.";
                $message_type = 'warning';
            }
        } else {
            $message = "Please select users to perform bulk action.";
            $message_type = 'warning';
        }
    }
}

// Get filter parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'program_id' => $_GET['program_id'] ?? '',
    'status' => $_GET['status'] ?? '',
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = RECORDS_PER_PAGE;

// Get students with filters
$students = $user->getAllUsers(ROLE_STUDENT, $page, $limit, $filters['search']);
$total_students = $user->getUserCount(ROLE_STUDENT, $filters['search']);
$total_pages = ceil($total_students / $limit);

// Get all programs for filter
$programs = $program->getAllActivePrograms();

$page_title = 'Manage Students';
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
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.75rem;
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
        
        .pagination-wrapper {
            background: white;
            border-radius: 0 0 12px 12px;
            padding: 1rem 1.5rem;
            border-top: 1px solid #e9ecef;
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
                            <i class="fas fa-users me-2"></i>
                            Manage Students
                        </h2>
                    </div>
                    <div class="col-auto ms-auto">
                        <div class="btn-list">
                            <a href="add.php" class="btn btn-light">
                                <i class="fas fa-plus me-2"></i>Add Student
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
                <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="filter-card">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   value="<?php echo htmlspecialchars($filters['search']); ?>"
                                   placeholder="Search by email or name...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Program</label>
                            <select class="form-select" name="program_id">
                                <option value="">All Programs</option>
                                <?php foreach ($programs as $prog): ?>
                                <option value="<?php echo $prog['id']; ?>" 
                                        <?php echo $filters['program_id'] == $prog['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prog['program_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="1" <?php echo $filters['status'] === '1' ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo $filters['status'] === '0' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                                <a href="students.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <form method="POST" id="bulkForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="fw-bold"><span id="selectedCount">0</span> users selected</span>
                            </div>
                            <div class="col-auto">
                                <select name="bulk_action" class="form-select" required>
                                    <option value="">Choose action...</option>
                                    <option value="activate">Activate Users</option>
                                    <option value="deactivate">Deactivate Users</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-warning">Apply</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearSelection()">Clear</button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Students Table -->
                <div class="data-table">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" class="form-check-input" id="selectAll">
                                    </th>
                                    <th>Student</th>
                                    <th>Program</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Registered</th>
                                    <th width="150">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                        <h5>No Students Found</h5>
                                        <p class="text-muted">No students match your search criteria.</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input user-checkbox" 
                                               name="selected_users[]" value="<?php echo $student['id']; ?>">
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar">
                                                <?php echo strtoupper(substr($student['email'], 0, 2)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($student['email']); ?></div>
                                                <div class="text-muted small">ID: <?php echo substr($student['id'], 0, 8); ?>...</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($student['program_name']): ?>
                                            <span class="badge bg-info">
                                                <?php echo htmlspecialchars($student['program_code']); ?>
                                            </span>
                                            <div class="text-muted small">
                                                <?php echo htmlspecialchars($student['program_name']); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">No Program</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $student['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $student['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($student['last_login']): ?>
                                            <div><?php echo formatDateTime($student['last_login']); ?></div>
                                        <?php else: ?>
                                            <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?php echo formatDate($student['date_created']); ?></div>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?php echo $student['id']; ?>" 
                                               class="btn btn-outline-info btn-action" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $student['id']; ?>" 
                                               class="btn btn-outline-primary btn-action" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($student['is_active']): ?>
                                            <button type="button" class="btn btn-outline-warning btn-action" 
                                                    onclick="toggleUserStatus('<?php echo $student['id']; ?>', 'deactivate')" title="Deactivate">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                            <?php else: ?>
                                            <button type="button" class="btn btn-outline-success btn-action" 
                                                    onclick="toggleUserStatus('<?php echo $student['id']; ?>', 'activate')" title="Activate">
                                                <i class="fas fa-check"></i>
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
                                    <?php echo min($page * $limit, $total_students); ?> of 
                                    <?php echo $total_students; ?> students
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
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Select All functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActions();
        });
        
        // Individual checkbox change
        document.querySelectorAll('.user-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActions);
        });
        
        function updateBulkActions() {
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            if (checkedBoxes.length > 0) {
                bulkActions.style.display = 'block';
                selectedCount.textContent = checkedBoxes.length;
                
                // Add hidden inputs for selected users
                const form = document.getElementById('bulkForm');
                const existingInputs = form.querySelectorAll('input[name="selected_users[]"]');
                existingInputs.forEach(input => input.remove());
                
                checkedBoxes.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_users[]';
                    input.value = checkbox.value;
                    form.appendChild(input);
                });
            } else {
                bulkActions.style.display = 'none';
            }
        }
        
        function clearSelection() {
            document.querySelectorAll('.user-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            document.getElementById('selectAll').checked = false;
            updateBulkActions();
        }
        
        function toggleUserStatus(userId, action) {
            const actionText = action === 'activate' ? 'activate' : 'deactivate';
            
            if (confirm(`Are you sure you want to ${actionText} this user?`)) {
                // Create a form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="bulk_action" value="${action}">
                    <input type="hidden" name="selected_users[]" value="${userId}">
                `;
                document.body.appendChild(form);
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
    </script>
</body>
</html>