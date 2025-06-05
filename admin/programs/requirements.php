<?php
/**
 * Programs Management - Certificate Requirements
 * 
 * File: admin/programs/requirements.php
 * Purpose: Manage program-specific certificate requirements
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

// Check if user has permission to manage this program
if ($current_user_role === ROLE_PROGRAM_ADMIN) {
    if (!$program->isProgramAdmin($current_user_id, $program_id)) {
        header('Location: list.php?error=access_denied');
        exit;
    }
}

// Initialize variables
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        
        $requirements = [];
        
        if (isset($_POST['requirements']) && is_array($_POST['requirements'])) {
            foreach ($_POST['requirements'] as $req) {
                if (isset($req['certificate_type_id']) && !empty($req['certificate_type_id'])) {
                    $requirements[] = [
                        'certificate_type_id' => (int)$req['certificate_type_id'],
                        'is_required' => isset($req['is_required']) ? 1 : 0
                    ];
                }
            }
        }
        
        if ($program->updateCertificateRequirements($program_id, $requirements)) {
            $message = 'Certificate requirements updated successfully.';
            $message_type = 'success';
        } else {
            $message = 'Failed to update certificate requirements.';
            $message_type = 'danger';
        }
    } else {
        $message = 'Invalid request. Please try again.';
        $message_type = 'danger';
    }
}

// Get current program requirements
$current_requirements = $program->getCertificateRequirements($program_id);
$current_requirement_ids = array_column($current_requirements, 'certificate_type_id');

// Get all available certificate types
$all_certs_query = "SELECT * FROM certificate_types WHERE is_active = 1 ORDER BY display_order ASC, name ASC";
$stmt = $db->prepare($all_certs_query);
$stmt->execute();
$all_certificates = $stmt->fetchAll();

// Get application statistics for this program
$program_stats = $program->getStatistics($program_id);

$page_title = 'Certificate Requirements: ' . $program_details['program_name'];
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
    <link href="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.css" rel="stylesheet"/>
    
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
        
        .program-info-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary-color);
        }
        
        .requirements-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .requirements-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .requirements-body {
            padding: 1.5rem;
        }
        
        .requirement-item {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .requirement-item:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(0, 84, 166, 0.1);
        }
        
        .requirement-item.active {
            border-color: var(--success-color);
            background: rgba(40, 167, 69, 0.05);
        }
        
        .drag-handle {
            cursor: move;
            color: #6c757d;
            margin-right: 1rem;
        }
        
        .drag-handle:hover {
            color: var(--primary-color);
        }
        
        .certificate-info {
            flex: 1;
        }
        
        .certificate-name {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
        }
        
        .certificate-description {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .certificate-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .requirement-controls {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: flex-end;
        }
        
        .form-check-modern {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-check-modern input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin: 0;
        }
        
        .available-certificates {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
        }
        
        .available-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .available-item:hover {
            background: #f8f9fa;
        }
        
        .available-item.selected {
            background: rgba(0, 84, 166, 0.1);
            border: 1px solid var(--primary-color);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        
        .warning-box {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .add-requirement-btn {
            border: 2px dashed #007bff;
            background: rgba(0, 123, 255, 0.05);
            color: #007bff;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .add-requirement-btn:hover {
            background: rgba(0, 123, 255, 0.1);
            border-color: #0056b3;
        }
        
        .stats-summary {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .stat-item {
            flex: 1;
            text-align: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .stat-number {
            font-weight: 700;
            color: var(--primary-color);
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
                            <i class="fas fa-file-certificate me-2"></i>
                            Certificate Requirements
                        </h2>
                    </div>
                    <div class="col-auto ms-auto">
                        <div class="btn-list">
                            <a href="view.php?id=<?php echo $program_id; ?>" class="btn btn-light">
                                <i class="fas fa-eye me-2"></i>View Program
                            </a>
                            <a href="edit.php?id=<?php echo $program_id; ?>" class="btn btn-light">
                                <i class="fas fa-edit me-2"></i>Edit Program
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
                <!-- Program Info -->
                <div class="program-info-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-1"><?php echo htmlspecialchars($program_details['program_name']); ?></h4>
                            <p class="text-muted mb-0">
                                <span class="badge bg-primary me-2"><?php echo htmlspecialchars($program_details['program_code']); ?></span>
                                <?php echo htmlspecialchars($program_details['department']); ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <?php if ($program_stats): ?>
                            <div class="stats-summary">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $program_stats['total_applications']; ?></div>
                                    <div class="text-muted small">Applications</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo count($current_requirements); ?></div>
                                    <div class="text-muted small">Requirements</div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Success/Error Messages -->
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Warning for existing applications -->
                <?php if (isset($program_stats) && $program_stats['total_applications'] > 0): ?>
                <div class="warning-box">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        <div>
                            <strong>Warning:</strong> This program has <?php echo $program_stats['total_applications']; ?> applications. 
                            Changes to certificate requirements may affect existing applications.
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Requirements Management -->
                <div class="requirements-card">
                    <div class="requirements-header">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <h4 class="mb-0">
                                    <i class="fas fa-file-alt me-2"></i>
                                    Certificate Requirements
                                </h4>
                            </div>
                            <div class="col-auto ms-auto">
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRequirementModal">
                                    <i class="fas fa-plus me-2"></i>Add Requirement
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="requirements-body">
                        <form method="POST" id="requirementsForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div id="requirementsList">
                                <?php if (empty($current_requirements)): ?>
                                <div class="text-center py-4" id="emptyState">
                                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                    <h5>No Certificate Requirements</h5>
                                    <p class="text-muted">Add certificate requirements to specify what documents students need to submit.</p>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRequirementModal">
                                        <i class="fas fa-plus me-2"></i>Add First Requirement
                                    </button>
                                </div>
                                <?php else: ?>
                                <?php foreach ($current_requirements as $index => $req): ?>
                                <div class="requirement-item active" data-certificate-id="<?php echo $req['certificate_type_id']; ?>">
                                    <div class="d-flex align-items-center">
                                        <div class="drag-handle">
                                            <i class="fas fa-grip-vertical"></i>
                                        </div>
                                        
                                        <div class="certificate-info">
                                            <div class="certificate-name"><?php echo htmlspecialchars($req['certificate_name']); ?></div>
                                            <?php if (!empty($req['description'])): ?>
                                            <div class="certificate-description"><?php echo htmlspecialchars($req['description']); ?></div>
                                            <?php endif; ?>
                                            <div class="certificate-meta">
                                                <span><i class="fas fa-file me-1"></i><?php echo htmlspecialchars($req['file_types_allowed']); ?></span>
                                                <span><i class="fas fa-weight me-1"></i>Max <?php echo $req['max_file_size_mb']; ?>MB</span>
                                            </div>
                                        </div>
                                        
                                        <div class="requirement-controls">
                                            <div class="form-check-modern">
                                                <input type="hidden" name="requirements[<?php echo $index; ?>][certificate_type_id]" value="<?php echo $req['certificate_type_id']; ?>">
                                                <input type="checkbox" name="requirements[<?php echo $index; ?>][is_required]" 
                                                       <?php echo $req['is_required'] ? 'checked' : ''; ?> class="form-check-input">
                                                <label class="form-check-label">Required</label>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRequirement(this)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($current_requirements)): ?>
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-success me-2">
                                    <i class="fas fa-save me-2"></i>Save Requirements
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                    <i class="fas fa-undo me-2"></i>Reset Changes
                                </button>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Requirement Modal -->
    <div class="modal fade" id="addRequirementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Certificate Requirement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="available-certificates">
                        <?php foreach ($all_certificates as $cert): ?>
                        <?php if (!in_array($cert['id'], $current_requirement_ids)): ?>
                        <div class="available-item" data-certificate-id="<?php echo $cert['id']; ?>" 
                             data-certificate-name="<?php echo htmlspecialchars($cert['name']); ?>"
                             data-certificate-description="<?php echo htmlspecialchars($cert['description']); ?>"
                             data-file-types="<?php echo htmlspecialchars($cert['file_types_allowed']); ?>"
                             data-max-size="<?php echo $cert['max_file_size_mb']; ?>"
                             data-default-required="<?php echo $cert['is_required']; ?>">
                            <div class="form-check me-3">
                                <input type="checkbox" class="form-check-input">
                            </div>
                            <div class="flex-fill">
                                <div class="fw-bold"><?php echo htmlspecialchars($cert['name']); ?></div>
                                <?php if (!empty($cert['description'])): ?>
                                <div class="text-muted small"><?php echo htmlspecialchars($cert['description']); ?></div>
                                <?php endif; ?>
                                <div class="text-muted small">
                                    <?php echo htmlspecialchars($cert['file_types_allowed']); ?> • 
                                    Max <?php echo $cert['max_file_size_mb']; ?>MB
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="addSelectedRequirements()">
                        <i class="fas fa-plus me-2"></i>Add Selected
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <script>
        let requirementIndex = <?php echo count($current_requirements); ?>;
        
        // Initialize sortable
        document.addEventListener('DOMContentLoaded', function() {
            const requirementsList = document.getElementById('requirementsList');
            if (requirementsList) {
                Sortable.create(requirementsList, {
                    handle: '.drag-handle',
                    animation: 150,
                    onEnd: function() {
                        updateIndices();
                    }
                });
            }
            
            // Initialize available certificates selection
            const availableItems = document.querySelectorAll('.available-item');
            availableItems.forEach(item => {
                item.addEventListener('click', function() {
                    const checkbox = this.querySelector('input[type="checkbox"]');
                    checkbox.checked = !checkbox.checked;
                    this.classList.toggle('selected', checkbox.checked);
                });
            });
        });
        
        function updateIndices() {
            const requirements = document.querySelectorAll('#requirementsList .requirement-item');
            requirements.forEach((item, index) => {
                const inputs = item.querySelectorAll('input[name*="requirements"]');
                inputs.forEach(input => {
                    const name = input.getAttribute('name');
                    const newName = name.replace(/requirements\[\d+\]/, `requirements[${index}]`);
                    input.setAttribute('name', newName);
                });
            });
        }
        
        function removeRequirement(button) {
            if (confirm('Are you sure you want to remove this requirement?')) {
                const item = button.closest('.requirement-item');
                item.remove();
                updateIndices();
                
                // Show empty state if no requirements left
                const requirementsList = document.getElementById('requirementsList');
                if (requirementsList.children.length === 0) {
                    showEmptyState();
                }
            }
        }
        
        function addSelectedRequirements() {
            const selectedItems = document.querySelectorAll('.available-item.selected');
            
            if (selectedItems.length === 0) {
                alert('Please select at least one certificate requirement.');
                return;
            }
            
            const requirementsList = document.getElementById('requirementsList');
            const emptyState = document.getElementById('emptyState');
            
            if (emptyState) {
                emptyState.remove();
            }
            
            selectedItems.forEach(item => {
                const certificateId = item.dataset.certificateId;
                const certificateName = item.dataset.certificateName;
                const description = item.dataset.certificateDescription;
                const fileTypes = item.dataset.fileTypes;
                const maxSize = item.dataset.maxSize;
                const defaultRequired = item.dataset.defaultRequired === '1';
                
                const requirementHtml = createRequirementHTML(
                    requirementIndex, 
                    certificateId, 
                    certificateName, 
                    description, 
                    fileTypes, 
                    maxSize, 
                    defaultRequired
                );
                
                requirementsList.insertAdjacentHTML('beforeend', requirementHtml);
                
                // Remove from available list
                item.remove();
                
                requirementIndex++;
            });
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('addRequirementModal'));
            modal.hide();
            
            // Show save button
            showSaveButton();
        }
        
        function createRequirementHTML(index, id, name, description, fileTypes, maxSize, required) {
            return `
                <div class="requirement-item active" data-certificate-id="${id}">
                    <div class="d-flex align-items-center">
                        <div class="drag-handle">
                            <i class="fas fa-grip-vertical"></i>
                        </div>
                        
                        <div class="certificate-info">
                            <div class="certificate-name">${name}</div>
                            ${description ? `<div class="certificate-description">${description}</div>` : ''}
                            <div class="certificate-meta">
                                <span><i class="fas fa-file me-1"></i>${fileTypes}</span>
                                <span><i class="fas fa-weight me-1"></i>Max ${maxSize}MB</span>
                            </div>
                        </div>
                        
                        <div class="requirement-controls">
                            <div class="form-check-modern">
                                <input type="hidden" name="requirements[${index}][certificate_type_id]" value="${id}">
                                <input type="checkbox" name="requirements[${index}][is_required]" 
                                       ${required ? 'checked' : ''} class="form-check-input">
                                <label class="form-check-label">Required</label>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRequirement(this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function showEmptyState() {
            const requirementsList = document.getElementById('requirementsList');
            requirementsList.innerHTML = `
                <div class="text-center py-4" id="emptyState">
                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                    <h5>No Certificate Requirements</h5>
                    <p class="text-muted">Add certificate requirements to specify what documents students need to submit.</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRequirementModal">
                        <i class="fas fa-plus me-2"></i>Add First Requirement
                    </button>
                </div>
            `;
        }
        
        function showSaveButton() {
            const existingSaveArea = document.querySelector('.text-center.mt-4');
            if (!existingSaveArea) {
                const requirementsList = document.getElementById('requirementsList');
                requirementsList.insertAdjacentHTML('afterend', `
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-success me-2">
                            <i class="fas fa-save me-2"></i>Save Requirements
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                            <i class="fas fa-undo me-2"></i>Reset Changes
                        </button>
                    </div>
                `);
            }
        }
        
        function resetForm() {
            if (confirm('Are you sure you want to reset all changes?')) {
                location.reload();
            }
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
        }, 8000);
    </script>
</body>
</html>