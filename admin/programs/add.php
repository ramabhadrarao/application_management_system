<?php
/**
 * Programs Management - Add New Program
 * 
 * File: admin/programs/add.php
 * Purpose: Add new program form with validation
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

// Initialize variables
$errors = [];
$success_message = '';
$form_data = [
    'program_code' => '',
    'program_name' => '',
    'program_type' => 'UG',
    'department' => '',
    'duration_years' => 3,
    'total_seats' => 60,
    'application_start_date' => date('Y-m-d'),
    'application_end_date' => date('Y-m-d', strtotime('+3 months')),
    'program_admin_id' => '',
    'eligibility_criteria' => '',
    'fees_structure' => '',
    'description' => '',
    'display_order' => 0
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        
        // Collect form data
        $form_data = [
            'program_code' => strtoupper(trim($_POST['program_code'])),
            'program_name' => trim($_POST['program_name']),
            'program_type' => $_POST['program_type'],
            'department' => trim($_POST['department']),
            'duration_years' => (float)$_POST['duration_years'],
            'total_seats' => (int)$_POST['total_seats'],
            'application_start_date' => $_POST['application_start_date'],
            'application_end_date' => $_POST['application_end_date'],
            'program_admin_id' => $_POST['program_admin_id'] ?: null,
            'eligibility_criteria' => trim($_POST['eligibility_criteria']),
            'fees_structure' => trim($_POST['fees_structure']),
            'description' => trim($_POST['description']),
            'display_order' => (int)$_POST['display_order']
        ];
        
        // Validation
        if (empty($form_data['program_code'])) {
            $errors[] = 'Program code is required.';
        } elseif (!preg_match('/^[A-Z0-9_-]+$/', $form_data['program_code'])) {
            $errors[] = 'Program code can only contain uppercase letters, numbers, hyphens, and underscores.';
        } elseif ($program->programCodeExists($form_data['program_code'])) {
            $errors[] = 'Program code already exists. Please choose a different code.';
        }
        
        if (empty($form_data['program_name'])) {
            $errors[] = 'Program name is required.';
        }
        
        if (empty($form_data['department'])) {
            $errors[] = 'Department is required.';
        }
        
        if ($form_data['duration_years'] <= 0 || $form_data['duration_years'] > 10) {
            $errors[] = 'Duration must be between 0.5 and 10 years.';
        }
        
        if ($form_data['total_seats'] <= 0 || $form_data['total_seats'] > 1000) {
            $errors[] = 'Total seats must be between 1 and 1000.';
        }
        
        if (empty($form_data['application_start_date'])) {
            $errors[] = 'Application start date is required.';
        }
        
        if (empty($form_data['application_end_date'])) {
            $errors[] = 'Application end date is required.';
        }
        
        if ($form_data['application_start_date'] >= $form_data['application_end_date']) {
            $errors[] = 'Application end date must be after start date.';
        }
        
        if (empty($form_data['eligibility_criteria'])) {
            $errors[] = 'Eligibility criteria is required.';
        }
        
        // If no errors, create the program
        if (empty($errors)) {
            $program_id = $program->create($form_data);
            
            if ($program_id) {
                $success_message = 'Program created successfully!';
                
                // Reset form data
                $form_data = [
                    'program_code' => '',
                    'program_name' => '',
                    'program_type' => 'UG',
                    'department' => '',
                    'duration_years' => 3,
                    'total_seats' => 60,
                    'application_start_date' => date('Y-m-d'),
                    'application_end_date' => date('Y-m-d', strtotime('+3 months')),
                    'program_admin_id' => '',
                    'eligibility_criteria' => '',
                    'fees_structure' => '',
                    'description' => '',
                    'display_order' => 0
                ];
            } else {
                $errors[] = 'Failed to create program. Please try again.';
            }
        }
    } else {
        $errors[] = 'Invalid request. Please try again.';
    }
}

// Get available program admins
$program_admins = $program->getAvailableProgramAdmins();

// Get existing departments for autocomplete
$departments = $program->getAllDepartments();

$page_title = 'Add New Program';
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
        
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 84, 166, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        
        .btn-outline-secondary {
            border-radius: 8px;
            padding: 0.75rem 2rem;
        }
        
        .invalid-feedback {
            display: block;
            color: var(--danger-color);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        .form-text {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px 0 0 8px;
        }
        
        .input-group .form-control {
            border-radius: 0 8px 8px 0;
        }
        
        .preview-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        
        .program-code-preview {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1.1rem;
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
                            <i class="fas fa-plus-circle me-2"></i>
                            Add New Program
                        </h2>
                    </div>
                    <div class="col-auto ms-auto">
                        <div class="btn-list">
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
                <!-- Success Message -->
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <div class="mt-2">
                        <a href="list.php" class="btn btn-sm btn-outline-success">View All Programs</a>
                        <a href="requirements.php?id=<?php echo $program_id ?? ''; ?>" class="btn btn-sm btn-success">Configure Requirements</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Please correct the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Add Program Form -->
                <div class="form-card">
                    <form method="POST" id="addProgramForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <!-- Basic Information -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-info-circle"></i>
                                Basic Information
                            </h3>
                            
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Program Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="program_code" 
                                           value="<?php echo htmlspecialchars($form_data['program_code']); ?>"
                                           placeholder="e.g., BCA, MBA, BTECH-CSE" 
                                           style="text-transform: uppercase;" required>
                                    <div class="form-text">Unique identifier for the program (uppercase letters, numbers, hyphens only)</div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Program Type <span class="text-danger">*</span></label>
                                    <select class="form-select" name="program_type" required>
                                        <option value="UG" <?php echo $form_data['program_type'] === 'UG' ? 'selected' : ''; ?>>Undergraduate (UG)</option>
                                        <option value="PG" <?php echo $form_data['program_type'] === 'PG' ? 'selected' : ''; ?>>Postgraduate (PG)</option>
                                        <option value="Diploma" <?php echo $form_data['program_type'] === 'Diploma' ? 'selected' : ''; ?>>Diploma</option>
                                        <option value="Certificate" <?php echo $form_data['program_type'] === 'Certificate' ? 'selected' : ''; ?>>Certificate</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Display Order</label>
                                    <input type="number" class="form-control" name="display_order" 
                                           value="<?php echo $form_data['display_order']; ?>" min="0" max="999">
                                    <div class="form-text">Order in which program appears in lists (0 = first)</div>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Program Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="program_name" 
                                           value="<?php echo htmlspecialchars($form_data['program_name']); ?>"
                                           placeholder="e.g., Bachelor of Computer Applications" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Department <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="department" 
                                           value="<?php echo htmlspecialchars($form_data['department']); ?>"
                                           placeholder="e.g., Computer Science" 
                                           list="departmentsList" required>
                                    <datalist id="departmentsList">
                                        <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label">Duration (Years) <span class="text-danger">*</span></label>
                                    <select class="form-select" name="duration_years" required>
                                        <option value="0.5" <?php echo $form_data['duration_years'] == 0.5 ? 'selected' : ''; ?>>6 Months</option>
                                        <option value="1" <?php echo $form_data['duration_years'] == 1 ? 'selected' : ''; ?>>1 Year</option>
                                        <option value="1.5" <?php echo $form_data['duration_years'] == 1.5 ? 'selected' : ''; ?>>1.5 Years</option>
                                        <option value="2" <?php echo $form_data['duration_years'] == 2 ? 'selected' : ''; ?>>2 Years</option>
                                        <option value="3" <?php echo $form_data['duration_years'] == 3 ? 'selected' : ''; ?>>3 Years</option>
                                        <option value="4" <?php echo $form_data['duration_years'] == 4 ? 'selected' : ''; ?>>4 Years</option>
                                        <option value="5" <?php echo $form_data['duration_years'] == 5 ? 'selected' : ''; ?>>5 Years</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label">Total Seats <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="total_seats" 
                                           value="<?php echo $form_data['total_seats']; ?>" 
                                           min="1" max="1000" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Application Period -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-calendar-alt"></i>
                                Application Period
                            </h3>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Application Start Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="application_start_date" 
                                           value="<?php echo $form_data['application_start_date']; ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Application End Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="application_end_date" 
                                           value="<?php echo $form_data['application_end_date']; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Administration -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-user-tie"></i>
                                Program Administration
                            </h3>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Program Administrator</label>
                                    <select class="form-select" name="program_admin_id">
                                        <option value="">No Administrator Assigned</option>
                                        <?php foreach ($program_admins as $admin): ?>
                                        <option value="<?php echo $admin['id']; ?>" 
                                                <?php echo $form_data['program_admin_id'] === $admin['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($admin['email']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Admin user who will manage this program</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Program Details -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-file-text"></i>
                                Program Details
                            </h3>
                            
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Eligibility Criteria <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="eligibility_criteria" rows="3" 
                                              placeholder="e.g., Intermediate (10+2) with Mathematics as one of the subjects with minimum 50% marks" required><?php echo htmlspecialchars($form_data['eligibility_criteria']); ?></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Fee Structure</label>
                                    <textarea class="form-control" name="fees_structure" rows="3" 
                                              placeholder="e.g., ₹45,000 per year (Tuition Fee), ₹5,000 (One-time Registration), Total: ₹50,000"><?php echo htmlspecialchars($form_data['fees_structure']); ?></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Program Description</label>
                                    <textarea class="form-control" name="description" rows="4" 
                                              placeholder="Brief description of the program, career opportunities, etc."><?php echo htmlspecialchars($form_data['description']); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Preview -->
                        <div class="preview-card" id="programPreview" style="display: none;">
                            <h5 class="mb-3">
                                <i class="fas fa-eye me-2"></i>
                                Program Preview
                            </h5>
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="program-code-preview" id="previewCode"></h6>
                                    <h4 id="previewName"></h4>
                                    <p class="text-muted" id="previewDepartment"></p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="badge bg-primary fs-6" id="previewType"></div>
                                    <div class="mt-2">
                                        <small class="text-muted">Duration: <span id="previewDuration"></span></small><br>
                                        <small class="text-muted">Seats: <span id="previewSeats"></span></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="row g-3 mt-4">
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-plus-circle me-2"></i>
                                    Create Program
                                </button>
                            </div>
                            <div class="col-md-6">
                                <a href="list.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-times me-2"></i>
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation and preview
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('addProgramForm');
            const preview = document.getElementById('programPreview');
            
            // Live preview functionality
            const previewElements = {
                code: document.getElementById('previewCode'),
                name: document.getElementById('previewName'),
                department: document.getElementById('previewDepartment'),
                type: document.getElementById('previewType'),
                duration: document.getElementById('previewDuration'),
                seats: document.getElementById('previewSeats')
            };
            
            function updatePreview() {
                const formData = new FormData(form);
                const code = formData.get('program_code');
                const name = formData.get('program_name');
                const department = formData.get('department');
                const type = formData.get('program_type');
                const duration = formData.get('duration_years');
                const seats = formData.get('total_seats');
                
                if (code || name) {
                    preview.style.display = 'block';
                    previewElements.code.textContent = code || 'PROGRAM-CODE';
                    previewElements.name.textContent = name || 'Program Name';
                    previewElements.department.textContent = department || 'Department';
                    previewElements.type.textContent = type || 'UG';
                    previewElements.duration.textContent = duration ? duration + ' years' : '- years';
                    previewElements.seats.textContent = seats || '0';
                } else {
                    preview.style.display = 'none';
                }
            }
            
            // Add event listeners for live preview
            ['program_code', 'program_name', 'department', 'program_type', 'duration_years', 'total_seats'].forEach(field => {
                const element = document.querySelector(`[name="${field}"]`);
                if (element) {
                    element.addEventListener('input', updatePreview);
                    element.addEventListener('change', updatePreview);
                }
            });
            
            // Program code validation
            const programCodeInput = document.querySelector('[name="program_code"]');
            programCodeInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase().replace(/[^A-Z0-9\-_]/g, '');
                updatePreview();
            });
            
            // Date validation
            const startDateInput = document.querySelector('[name="application_start_date"]');
            const endDateInput = document.querySelector('[name="application_end_date"]');
            
            function validateDates() {
                const startDate = new Date(startDateInput.value);
                const endDate = new Date(endDateInput.value);
                
                if (startDate && endDate && startDate >= endDate) {
                    endDateInput.setCustomValidity('End date must be after start date');
                } else {
                    endDateInput.setCustomValidity('');
                }
            }
            
            startDateInput.addEventListener('change', validateDates);
            endDateInput.addEventListener('change', validateDates);
            
            // Form submission validation
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                form.classList.add('was-validated');
                
                // Custom validation
                const programCode = document.querySelector('[name="program_code"]').value;
                if (!/^[A-Z0-9\-_]+$/.test(programCode)) {
                    e.preventDefault();
                    alert('Program code can only contain uppercase letters, numbers, hyphens, and underscores.');
                    return;
                }
                
                validateDates();
                if (!endDateInput.checkValidity()) {
                    e.preventDefault();
                    alert('Please ensure the application end date is after the start date.');
                    return;
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
            }, 10000);
            
            // Initialize preview
            updatePreview();
        });
    </script>
</body>
</html>