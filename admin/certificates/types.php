<?php
/**
 * Certificate Requirements Management
 * 
 * File: admin/certificates/types.php
 * Purpose: Manage certificate types and program-specific requirements
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../../config/config.php';
require_once '../../classes/Program.php';

// Require admin login
requireLogin();
requirePermission('all');

$database = new Database();
$db = $database->getConnection();

$program = new Program($db);

$current_user_role = getCurrentUserRole();
$current_user_id = getCurrentUserId();

// Handle certificate type actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        
        // Add new certificate type
        if (isset($_POST['add_certificate_type'])) {
            $name = sanitizeInput($_POST['name']);
            $description = sanitizeInput($_POST['description']);
            $file_types = sanitizeInput($_POST['file_types_allowed']);
            $max_size = (int)$_POST['max_file_size_mb'];
            $is_required = isset($_POST['is_required']) ? 1 : 0;
            $display_order = (int)$_POST['display_order'];
            
            $query = "INSERT INTO certificate_types 
                      (name, description, file_types_allowed, max_file_size_mb, is_required, display_order) 
                      VALUES (:name, :description, :file_types, :max_size, :is_required, :display_order)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':file_types', $file_types);
            $stmt->bindParam(':max_size', $max_size);
            $stmt->bindParam(':is_required', $is_required);
            $stmt->bindParam(':display_order', $display_order);
            
            if ($stmt->execute()) {
                $message = 'Certificate type added successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to add certificate type.';
                $message_type = 'danger';
            }
        }
        
        // Update certificate type
        if (isset($_POST['update_certificate_type'])) {
            $cert_id = (int)$_POST['cert_id'];
            $name = sanitizeInput($_POST['name']);
            $description = sanitizeInput($_POST['description']);
            $file_types = sanitizeInput($_POST['file_types_allowed']);
            $max_size = (int)$_POST['max_file_size_mb'];
            $is_required = isset($_POST['is_required']) ? 1 : 0;
            $display_order = (int)$_POST['display_order'];
            
            $query = "UPDATE certificate_types 
                      SET name = :name, description = :description, file_types_allowed = :file_types,
                          max_file_size_mb = :max_size, is_required = :is_required, display_order = :display_order,
                          date_updated = CURRENT_TIMESTAMP
                      WHERE id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':file_types', $file_types);
            $stmt->bindParam(':max_size', $max_size);
            $stmt->bindParam(':is_required', $is_required);
            $stmt->bindParam(':display_order', $display_order);
            $stmt->bindParam(':id', $cert_id);
            
            if ($stmt->execute()) {
                $message = 'Certificate type updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to update certificate type.';
                $message_type = 'danger';
            }
        }
        
        // Toggle certificate type status
        if (isset($_POST['toggle_status'])) {
            $cert_id = (int)$_POST['cert_id'];
            
            $query = "UPDATE certificate_types 
                      SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END,
                          date_updated = CURRENT_TIMESTAMP
                      WHERE id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $cert_id);
            
            if ($stmt->execute()) {
                $message = 'Certificate type status updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to update certificate type status.';
                $message_type = 'danger';
            }
        }
        
        // Delete certificate type
        if (isset($_POST['delete_certificate_type'])) {
            $cert_id = (int)$_POST['cert_id'];
            
            // Check if certificate type is used in any program requirements
            $check_query = "SELECT COUNT(*) as count FROM program_certificate_requirements WHERE certificate_type_id = :id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':id', $cert_id);
            $check_stmt->execute();
            $result = $check_stmt->fetch();
            
            if ($result['count'] > 0) {
                $message = 'Cannot delete certificate type. It is being used in program requirements.';
                $message_type = 'warning';
            } else {
                $query = "DELETE FROM certificate_types WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $cert_id);
                
                if ($stmt->execute()) {
                    $message = 'Certificate type deleted successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to delete certificate type.';
                    $message_type = 'danger';
                }
            }
        }
    }
}

// Get all certificate types
$query = "SELECT ct.*, 
                 COUNT(pcr.id) as programs_using
          FROM certificate_types ct
          LEFT JOIN program_certificate_requirements pcr ON ct.id = pcr.certificate_type_id
          GROUP BY ct.id
          ORDER BY ct.display_order ASC, ct.name ASC";

$stmt = $db->prepare($query);
$stmt->execute();
$certificate_types = $stmt->fetchAll();

// Get programs for assignment
$programs = $program->getAllActivePrograms();

$page_title = 'Certificate Requirements Management';
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
        
        .management-tabs {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
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
        
        .certificate-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .required-badge {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .optional-badge {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info-color);
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
        
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .drag-handle {
            cursor: move;
            color: #6c757d;
        }
        
        .drag-handle:hover {
            color: var(--primary-color);
        }
        
        .file-types-input {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        
        .programs-using {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }
        
        .program-tag {
            background: rgba(0, 84, 166, 0.1);
            color: var(--primary-color);
            padding: 0.125rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
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
                            <i class="fas fa-file-certificate me-2"></i>
                            Certificate Requirements Management
                        </h2>
                    </div>
                    <div class="col-auto ms-auto">
                        <div class="btn-list">
                            <a href="program-requirements.php" class="btn btn-light">
                                <i class="fas fa-link me-2"></i>Program Requirements
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
                
                <!-- Management Tabs -->
                <div class="management-tabs">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="certificate-types-tab" data-bs-toggle="tab" 
                                    data-bs-target="#certificate-types" type="button" role="tab">
                                <i class="fas fa-file-alt me-2"></i>Certificate Types
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="add-certificate-tab" data-bs-toggle="tab" 
                                    data-bs-target="#add-certificate" type="button" role="tab">
                                <i class="fas fa-plus me-2"></i>Add New Type
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="bulk-assign-tab" data-bs-toggle="tab" 
                                    data-bs-target="#bulk-assign" type="button" role="tab">
                                <i class="fas fa-tasks me-2"></i>Bulk Assignment
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content p-3">
                        <!-- Certificate Types List -->
                        <div class="tab-pane fade show active" id="certificate-types" role="tabpanel">
                            <div class="data-table">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="certificateTypesTable">
                                        <thead>
                                            <tr>
                                                <th width="30"></th>
                                                <th>Certificate Type</th>
                                                <th>File Settings</th>
                                                <th>Default</th>
                                                <th>Programs Using</th>
                                                <th>Status</th>
                                                <th>Order</th>
                                                <th width="150">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="sortableCertificates">
                                            <?php foreach ($certificate_types as $cert): ?>
                                            <tr data-id="<?php echo $cert['id']; ?>">
                                                <td>
                                                    <div class="drag-handle">
                                                        <i class="fas fa-grip-vertical"></i>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($cert['name']); ?></div>
                                                        <div class="text-muted small"><?php echo htmlspecialchars($cert['description']); ?></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <div><strong>Types:</strong> <code><?php echo htmlspecialchars($cert['file_types_allowed']); ?></code></div>
                                                        <div><strong>Max Size:</strong> <?php echo $cert['max_file_size_mb']; ?> MB</div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="certificate-badge <?php echo $cert['is_required'] ? 'required-badge' : 'optional-badge'; ?>">
                                                        <?php echo $cert['is_required'] ? 'Required' : 'Optional'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($cert['programs_using'] > 0): ?>
                                                    <span class="badge bg-info"><?php echo $cert['programs_using']; ?> programs</span>
                                                    <?php else: ?>
                                                    <span class="text-muted">Not used</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?php echo $cert['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo $cert['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo $cert['display_order']; ?></span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-outline-primary btn-action" 
                                                                onclick="editCertificateType(<?php echo htmlspecialchars(json_encode($cert)); ?>)" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-<?php echo $cert['is_active'] ? 'warning' : 'success'; ?> btn-action" 
                                                                onclick="toggleCertificateStatus(<?php echo $cert['id']; ?>)" 
                                                                title="<?php echo $cert['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                            <i class="fas fa-<?php echo $cert['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                        </button>
                                                        <?php if ($cert['programs_using'] == 0): ?>
                                                        <button type="button" class="btn btn-outline-danger btn-action" 
                                                                onclick="deleteCertificateType(<?php echo $cert['id']; ?>)" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Add New Certificate Type -->
                        <div class="tab-pane fade" id="add-certificate" role="tabpanel">
                            <div class="form-card">
                                <h4 class="mb-4">
                                    <i class="fas fa-plus-circle text-primary me-2"></i>
                                    Add New Certificate Type
                                </h4>
                                
                                <form method="POST" id="addCertificateForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="add_certificate_type" value="1">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <label class="form-label">Certificate Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="name" required
                                                   placeholder="e.g., 10th Marks Memo">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Display Order</label>
                                            <input type="number" class="form-control" name="display_order" 
                                                   value="<?php echo count($certificate_types) + 1; ?>" min="1">
                                        </div>
                                        
                                        <div class="col-12">
                                            <label class="form-label">Description</label>
                                            <textarea class="form-control" name="description" rows="2"
                                                      placeholder="Brief description of this certificate"></textarea>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Allowed File Types</label>
                                            <input type="text" class="form-control file-types-input" name="file_types_allowed" 
                                                   value="pdf,jpg,jpeg,png" placeholder="pdf,jpg,jpeg,png">
                                            <div class="form-text">Comma-separated file extensions</div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label">Max File Size (MB)</label>
                                            <input type="number" class="form-control" name="max_file_size_mb" 
                                                   value="5" min="1" max="50">
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label">Default Setting</label>
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input" type="checkbox" name="is_required" id="isRequired">
                                                <label class="form-check-label" for="isRequired">
                                                    Required by default
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i>Add Certificate Type
                                            </button>
                                            <button type="reset" class="btn btn-outline-secondary">
                                                <i class="fas fa-undo me-2"></i>Reset Form
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Bulk Assignment -->
                        <div class="tab-pane fade" id="bulk-assign" role="tabpanel">
                            <div class="form-card">
                                <h4 class="mb-4">
                                    <i class="fas fa-tasks text-primary me-2"></i>
                                    Bulk Assignment to Programs
                                </h4>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Select certificate types and programs to quickly assign requirements.
                                </div>
                                
                                <form method="POST" id="bulkAssignForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="bulk_assign" value="1">
                                    
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label class="form-label">Select Certificate Types</label>
                                            <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                                <?php foreach ($certificate_types as $cert): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="selected_certificates[]" value="<?php echo $cert['id']; ?>"
                                                           id="cert_<?php echo $cert['id']; ?>">
                                                    <label class="form-check-label" for="cert_<?php echo $cert['id']; ?>">
                                                        <?php echo htmlspecialchars($cert['name']); ?>
                                                        <small class="text-muted d-block"><?php echo htmlspecialchars($cert['description']); ?></small>
                                                    </label>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Select Programs</label>
                                            <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                                <?php foreach ($programs as $prog): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="selected_programs[]" value="<?php echo $prog['id']; ?>"
                                                           id="prog_<?php echo $prog['id']; ?>">
                                                    <label class="form-check-label" for="prog_<?php echo $prog['id']; ?>">
                                                        <?php echo htmlspecialchars($prog['program_name']); ?>
                                                        <small class="text-muted d-block"><?php echo htmlspecialchars($prog['program_code']); ?></small>
                                                    </label>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="assign_as_required" id="assignAsRequired">
                                                <label class="form-check-label" for="assignAsRequired">
                                                    Assign as required certificates
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-link me-2"></i>Assign Selected
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="clearAllSelections()">
                                                <i class="fas fa-times me-2"></i>Clear All
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Certificate Modal -->
    <div class="modal fade" id="editCertificateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Certificate Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editCertificateForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="update_certificate_type" value="1">
                        <input type="hidden" name="cert_id" id="editCertId">
                        
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Certificate Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" id="editCertName" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Display Order</label>
                                <input type="number" class="form-control" name="display_order" id="editCertOrder" min="1">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="editCertDescription" rows="2"></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Allowed File Types</label>
                                <input type="text" class="form-control file-types-input" name="file_types_allowed" id="editCertFileTypes">
                                <div class="form-text">Comma-separated file extensions</div>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Max File Size (MB)</label>
                                <input type="number" class="form-control" name="max_file_size_mb" id="editCertMaxSize" min="1" max="50">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Default Setting</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_required" id="editCertRequired">
                                    <label class="form-check-label" for="editCertRequired">
                                        Required by default
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Certificate Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Hidden Forms for Actions -->
    <form id="actionForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="cert_id" id="actionCertId">
        <input type="hidden" name="action_type" id="actionType">
    </form>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <script>
        // Initialize sortable table
        document.addEventListener('DOMContentLoaded', function() {
            const sortableElement = document.getElementById('sortableCertificates');
            if (sortableElement) {
                const sortable = Sortable.create(sortableElement, {
                    handle: '.drag-handle',
                    animation: 150,
                    onEnd: function(evt) {
                        updateDisplayOrder();
                    }
                });
            }
        });
        
        function updateDisplayOrder() {
            const rows = document.querySelectorAll('#sortableCertificates tr');
            const updates = [];
            
            rows.forEach((row, index) => {
                const certId = row.getAttribute('data-id');
                const newOrder = index + 1;
                updates.push({ id: certId, order: newOrder });
                
                // Update the display order in the table
                const orderCell = row.querySelector('.badge.bg-secondary');
                if (orderCell) {
                    orderCell.textContent = newOrder;
                }
            });
            
            // Send AJAX request to update order
            fetch('update-order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo generateCSRFToken(); ?>'
                },
                body: JSON.stringify({ updates: updates })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Display order updated successfully', 'success');
                } else {
                    showToast('Failed to update display order', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating display order', 'error');
            });
        }
        
        function editCertificateType(certData) {
            document.getElementById('editCertId').value = certData.id;
            document.getElementById('editCertName').value = certData.name;
            document.getElementById('editCertDescription').value = certData.description || '';
            document.getElementById('editCertFileTypes').value = certData.file_types_allowed;
            document.getElementById('editCertMaxSize').value = certData.max_file_size_mb;
            document.getElementById('editCertOrder').value = certData.display_order;
            document.getElementById('editCertRequired').checked = certData.is_required == 1;
            
            const modal = new bootstrap.Modal(document.getElementById('editCertificateModal'));
            modal.show();
        }
        
        function toggleCertificateStatus(certId) {
            if (confirm('Are you sure you want to toggle the status of this certificate type?')) {
                const form = document.getElementById('actionForm');
                document.getElementById('actionCertId').value = certId;
                form.innerHTML += '<input type="hidden" name="toggle_status" value="1">';
                form.submit();
            }
        }
        
        function deleteCertificateType(certId) {
            if (confirm('Are you sure you want to delete this certificate type? This action cannot be undone.')) {
                const form = document.getElementById('actionForm');
                document.getElementById('actionCertId').value = certId;
                form.innerHTML += '<input type="hidden" name="delete_certificate_type" value="1">';
                form.submit();
            }
        }
        
        function clearAllSelections() {
            // Clear certificate selections
            document.querySelectorAll('input[name="selected_certificates[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Clear program selections
            document.querySelectorAll('input[name="selected_programs[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Clear required checkbox
            document.getElementById('assignAsRequired').checked = false;
        }
        
        function showToast(message, type) {
            const toastContainer = document.getElementById('toastContainer') || createToastContainer();
            
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-bg-${type === 'success' ? 'success' : 'danger'} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            // Remove toast element after it's hidden
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }
        
        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
            return container;
        }
        
        // Form validation
        document.getElementById('addCertificateForm').addEventListener('submit', function(e) {
            const name = this.querySelector('[name="name"]').value.trim();
            const fileTypes = this.querySelector('[name="file_types_allowed"]').value.trim();
            
            if (!name) {
                e.preventDefault();
                alert('Certificate name is required.');
                return;
            }
            
            if (!fileTypes) {
                e.preventDefault();
                alert('File types are required.');
                return;
            }
            
            // Validate file types format
            const fileTypePattern = /^[a-zA-Z0-9]+(,[a-zA-Z0-9]+)*$/;
            if (!fileTypePattern.test(fileTypes)) {
                e.preventDefault();
                alert('File types must be comma-separated without spaces (e.g., pdf,jpg,png).');
                return;
            }
        });
        
        // Bulk assignment form validation
        document.getElementById('bulkAssignForm').addEventListener('submit', function(e) {
            const selectedCerts = this.querySelectorAll('input[name="selected_certificates[]"]:checked');
            const selectedProgs = this.querySelectorAll('input[name="selected_programs[]"]:checked');
            
            if (selectedCerts.length === 0) {
                e.preventDefault();
                alert('Please select at least one certificate type.');
                return;
            }
            
            if (selectedProgs.length === 0) {
                e.preventDefault();
                alert('Please select at least one program.');
                return;
            }
            
            if (!confirm(`Are you sure you want to assign ${selectedCerts.length} certificate types to ${selectedProgs.length} programs?`)) {
                e.preventDefault();
            }
        });
        
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
        
        // Select all functionality for bulk assignment
        function addSelectAllButtons() {
            // Add select all button for certificates
            const certContainer = document.querySelector('input[name="selected_certificates[]"]').closest('.border');
            if (certContainer && !certContainer.querySelector('.select-all-btn')) {
                const selectAllBtn = document.createElement('button');
                selectAllBtn.type = 'button';
                selectAllBtn.className = 'btn btn-sm btn-outline-primary select-all-btn mb-2';
                selectAllBtn.innerHTML = '<i class="fas fa-check-square me-1"></i>Select All';
                selectAllBtn.onclick = () => toggleSelectAll('selected_certificates[]');
                certContainer.insertBefore(selectAllBtn, certContainer.firstChild);
            }
            
            // Add select all button for programs
            const progContainer = document.querySelector('input[name="selected_programs[]"]').closest('.border');
            if (progContainer && !progContainer.querySelector('.select-all-btn')) {
                const selectAllBtn = document.createElement('button');
                selectAllBtn.type = 'button';
                selectAllBtn.className = 'btn btn-sm btn-outline-primary select-all-btn mb-2';
                selectAllBtn.innerHTML = '<i class="fas fa-check-square me-1"></i>Select All';
                selectAllBtn.onclick = () => toggleSelectAll('selected_programs[]');
                progContainer.insertBefore(selectAllBtn, progContainer.firstChild);
            }
        }
        
        function toggleSelectAll(name) {
            const checkboxes = document.querySelectorAll(`input[name="${name}"]`);
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });
        }
        
        // Initialize select all buttons when bulk assign tab is shown
        document.getElementById('bulk-assign-tab').addEventListener('shown.bs.tab', function() {
            setTimeout(addSelectAllButtons, 100);
        });
        
        // File type input formatter
        document.querySelectorAll('.file-types-input').forEach(input => {
            input.addEventListener('blur', function() {
                // Remove spaces and ensure lowercase
                this.value = this.value.toLowerCase().replace(/\s+/g, '');
            });
            
            input.addEventListener('input', function() {
                // Only allow letters, numbers, and commas
                this.value = this.value.replace(/[^a-zA-Z0-9,]/g, '');
            });
        });
    </script>
</body>
</html>

<?php
// Additional functions that could be in a separate file

/**
 * Update certificate display order via AJAX
 * This would typically be in a separate file: admin/certificates/update-order.php
 */
if (false) { // This is example code for update-order.php
?>
<?php
require_once '../../config/config.php';

// Require admin login
requireLogin();
requirePermission('all');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['updates']) && is_array($input['updates'])) {
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("UPDATE certificate_types SET display_order = ? WHERE id = ?");
            
            foreach ($input['updates'] as $update) {
                $stmt->execute([$update['order'], $update['id']]);
            }
            
            $db->commit();
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $db->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>
<?php } ?>

<?php
/**
 * Program-specific certificate requirements management
 * This would typically be in: admin/certificates/program-requirements.php
 */
if (false) { // This is example code for program-requirements.php
?>
<?php
require_once '../../config/config.php';
require_once '../../classes/Program.php';

// Require admin login
requireLogin();
requirePermission('all');

$database = new Database();
$db = $database->getConnection();
$program = new Program($db);

$program_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($program_id) {
    $program_details = $program->getById($program_id);
    $requirements = $program->getCertificateRequirements($program_id);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle requirement updates
        $new_requirements = [];
        
        if (isset($_POST['requirements']) && is_array($_POST['requirements'])) {
            foreach ($_POST['requirements'] as $req) {
                $new_requirements[] = [
                    'certificate_type_id' => (int)$req['certificate_type_id'],
                    'is_required' => isset($req['is_required']) ? 1 : 0
                ];
            }
            
            if ($program->updateCertificateRequirements($program_id, $new_requirements)) {
                $message = 'Certificate requirements updated successfully.';
                $message_type = 'success';
                // Refresh requirements
                $requirements = $program->getCertificateRequirements($program_id);
            } else {
                $message = 'Failed to update certificate requirements.';
                $message_type = 'danger';
            }
        }
    }
    
    // Get all available certificate types
    $all_certs_query = "SELECT * FROM certificate_types WHERE is_active = 1 ORDER BY display_order ASC";
    $stmt = $db->prepare($all_certs_query);
    $stmt->execute();
    $all_certificates = $stmt->fetchAll();
} else {
    header('Location: list.php');
    exit;
}
?>
<!-- HTML for program-specific requirements would go here -->
<?php } ?>