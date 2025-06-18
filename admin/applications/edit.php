<?php
/**
 * Edit Application Page (Admin)
 * 
 * File: admin/applications/edit.php
 * Purpose: Allow admin to edit draft/frozen applications
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
$app_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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

// Check if application can be edited
if (!in_array($app_details['status'], [STATUS_DRAFT, STATUS_FROZEN])) {
    $_SESSION['flash_message'] = 'Only draft or frozen applications can be edited.';
    $_SESSION['flash_type'] = 'warning';
    header('Location: ' . SITE_URL . '/admin/applications/view.php?id=' . $app_id);
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

$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        
        // Prepare update data
        $update_data = [
            'student_name' => sanitizeInput($_POST['student_name']),
            'father_name' => sanitizeInput($_POST['father_name']),
            'mother_name' => sanitizeInput($_POST['mother_name']),
            'date_of_birth' => sanitizeInput($_POST['date_of_birth']),
            'gender' => sanitizeInput($_POST['gender']),
            'mobile_number' => sanitizeInput($_POST['mobile_number']),
            'parent_mobile' => sanitizeInput($_POST['parent_mobile']),
            'guardian_mobile' => sanitizeInput($_POST['guardian_mobile']),
            'email' => sanitizeInput($_POST['email']),
            
            // Address details
            'present_door_no' => sanitizeInput($_POST['present_door_no']),
            'present_street' => sanitizeInput($_POST['present_street']),
            'present_village' => sanitizeInput($_POST['present_village']),
            'present_mandal' => sanitizeInput($_POST['present_mandal']),
            'present_district' => sanitizeInput($_POST['present_district']),
            'present_pincode' => sanitizeInput($_POST['present_pincode']),
            
            'permanent_door_no' => sanitizeInput($_POST['permanent_door_no']),
            'permanent_street' => sanitizeInput($_POST['permanent_street']),
            'permanent_village' => sanitizeInput($_POST['permanent_village']),
            'permanent_mandal' => sanitizeInput($_POST['permanent_mandal']),
            'permanent_district' => sanitizeInput($_POST['permanent_district']),
            'permanent_pincode' => sanitizeInput($_POST['permanent_pincode']),
            
            // Additional details
            'religion' => sanitizeInput($_POST['religion']),
            'caste' => sanitizeInput($_POST['caste']),
            'reservation_category' => sanitizeInput($_POST['reservation_category']),
            'is_physically_handicapped' => isset($_POST['is_physically_handicapped']) ? 1 : 0,
            'aadhar_number' => sanitizeInput($_POST['aadhar_number']),
            'sadaram_number' => sanitizeInput($_POST['sadaram_number']),
            'identification_mark_1' => sanitizeInput($_POST['identification_mark_1']),
            'identification_mark_2' => sanitizeInput($_POST['identification_mark_2']),
            'special_reservation' => sanitizeInput($_POST['special_reservation']),
            'meeseva_caste_certificate' => sanitizeInput($_POST['meeseva_caste_certificate']),
            'meeseva_income_certificate' => sanitizeInput($_POST['meeseva_income_certificate']),
            'ration_card_number' => sanitizeInput($_POST['ration_card_number'])
        ];
        
        // Basic validation
        if (empty($update_data['student_name'])) {
            $errors[] = 'Student name is required.';
        }
        if (empty($update_data['father_name'])) {
            $errors[] = 'Father name is required.';
        }
        if (empty($update_data['mother_name'])) {
            $errors[] = 'Mother name is required.';
        }
        if (empty($update_data['date_of_birth'])) {
            $errors[] = 'Date of birth is required.';
        }
        if (empty($update_data['gender'])) {
            $errors[] = 'Gender is required.';
        }
        if (empty($update_data['mobile_number'])) {
            $errors[] = 'Mobile number is required.';
        }
        if (empty($update_data['email'])) {
            $errors[] = 'Email is required.';
        }
        
        // Update application if no errors
        if (empty($errors)) {
            if ($application->update($app_id, $update_data)) {
                // Log the update
                $log_query = "INSERT INTO system_logs (user_id, action, table_name, record_id, new_values) 
                              VALUES (:user_id, 'UPDATE', 'applications', :record_id, :new_values)";
                $stmt = $db->prepare($log_query);
                $stmt->bindParam(':user_id', $current_user_id);
                $stmt->bindParam(':record_id', $app_id);
                $stmt->bindValue(':new_values', json_encode($update_data));
                $stmt->execute();
                
                $success_message = 'Application updated successfully!';
                
                // Refresh application data
                $app_details = $application->getById($app_id);
            } else {
                $errors[] = 'Failed to update application. Please try again.';
            }
        }
    }
}

$page_title = 'Edit Application - ' . $app_details['application_number'];
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
        
        .form-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .form-label.required::after {
            content: ' *';
            color: var(--danger-color);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 84, 166, 0.25);
        }
        
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background: #003d7a;
            border-color: #003d7a;
        }
        
        .alert-info {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            color: #0d47a1;
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
                        <div class="page-pretitle">Edit Application</div>
                        <h2 class="page-title">
                            <i class="fas fa-edit me-2"></i>
                            <?php echo htmlspecialchars($app_details['application_number']); ?>
                        </h2>
                    </div>
                    <div class="col-auto ms-auto">
                        <div class="btn-list">
                            <a href="view.php?id=<?php echo $app_id; ?>" class="btn btn-light">
                                <i class="fas fa-eye me-2"></i>View Application
                            </a>
                            <a href="list.php" class="btn btn-light">
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
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Please correct the following errors:</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Info Alert -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> You are editing a <?php echo $app_details['status']; ?> application. 
                    Changes will be saved but the application status will remain unchanged.
                </div>
                
                <!-- Edit Form -->
                <form method="POST" id="editForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user me-2"></i>Personal Information
                        </h3>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label required">Student Name</label>
                                <input type="text" class="form-control" name="student_name" 
                                       value="<?php echo htmlspecialchars($app_details['student_name']); ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label required">Father's Name</label>
                                <input type="text" class="form-control" name="father_name" 
                                       value="<?php echo htmlspecialchars($app_details['father_name']); ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label required">Mother's Name</label>
                                <input type="text" class="form-control" name="mother_name" 
                                       value="<?php echo htmlspecialchars($app_details['mother_name']); ?>" required>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label required">Date of Birth</label>
                                <input type="date" class="form-control" name="date_of_birth" 
                                       value="<?php echo htmlspecialchars($app_details['date_of_birth']); ?>" required>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label required">Gender</label>
                                <select class="form-select" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo $app_details['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $app_details['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo $app_details['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Aadhar Number</label>
                                <input type="text" class="form-control" name="aadhar_number" 
                                       value="<?php echo htmlspecialchars($app_details['aadhar_number']); ?>" 
                                       pattern="[0-9]{12}" maxlength="12">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Sadaram Number</label>
                                <input type="text" class="form-control" name="sadaram_number" 
                                       value="<?php echo htmlspecialchars($app_details['sadaram_number']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-phone me-2"></i>Contact Information
                        </h3>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label required">Mobile Number</label>
                                <input type="tel" class="form-control" name="mobile_number" 
                                       value="<?php echo htmlspecialchars($app_details['mobile_number']); ?>" 
                                       pattern="[6-9][0-9]{9}" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Parent Mobile</label>
                                <input type="tel" class="form-control" name="parent_mobile" 
                                       value="<?php echo htmlspecialchars($app_details['parent_mobile']); ?>" 
                                       pattern="[6-9][0-9]{9}">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Guardian Mobile</label>
                                <input type="tel" class="form-control" name="guardian_mobile" 
                                       value="<?php echo htmlspecialchars($app_details['guardian_mobile']); ?>" 
                                       pattern="[6-9][0-9]{9}">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label required">Email Address</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($app_details['email']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Present Address -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-map-marker-alt me-2"></i>Present Address
                        </h3>
                        
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Door No</label>
                                <input type="text" class="form-control" name="present_door_no" 
                                       value="<?php echo htmlspecialchars($app_details['present_door_no']); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Street</label>
                                <input type="text" class="form-control" name="present_street" 
                                       value="<?php echo htmlspecialchars($app_details['present_street']); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Village/Town</label>
                                <input type="text" class="form-control" name="present_village" 
                                       value="<?php echo htmlspecialchars($app_details['present_village']); ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Mandal</label>
                                <input type="text" class="form-control" name="present_mandal" 
                                       value="<?php echo htmlspecialchars($app_details['present_mandal']); ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">District</label>
                                <input type="text" class="form-control" name="present_district" 
                                       value="<?php echo htmlspecialchars($app_details['present_district']); ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Pincode</label>
                                <input type="text" class="form-control" name="present_pincode" 
                                       value="<?php echo htmlspecialchars($app_details['present_pincode']); ?>" 
                                       pattern="[0-9]{6}" maxlength="6">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Permanent Address -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-home me-2"></i>Permanent Address
                            <button type="button" class="btn btn-sm btn-secondary ms-3" onclick="copyPresentAddress()">
                                <i class="fas fa-copy me-1"></i>Same as Present
                            </button>
                        </h3>
                        
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Door No</label>
                                <input type="text" class="form-control" name="permanent_door_no" 
                                       value="<?php echo htmlspecialchars($app_details['permanent_door_no']); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Street</label>
                                <input type="text" class="form-control" name="permanent_street" 
                                       value="<?php echo htmlspecialchars($app_details['permanent_street']); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Village/Town</label>
                                <input type="text" class="form-control" name="permanent_village" 
                                       value="<?php echo htmlspecialchars($app_details['permanent_village']); ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Mandal</label>
                                <input type="text" class="form-control" name="permanent_mandal" 
                                       value="<?php echo htmlspecialchars($app_details['permanent_mandal']); ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">District</label>
                                <input type="text" class="form-control" name="permanent_district" 
                                       value="<?php echo htmlspecialchars($app_details['permanent_district']); ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Pincode</label>
                                <input type="text" class="form-control" name="permanent_pincode" 
                                       value="<?php echo htmlspecialchars($app_details['permanent_pincode']); ?>" 
                                       pattern="[0-9]{6}" maxlength="6">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Details -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-info-circle me-2"></i>Additional Details
                        </h3>
                        
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Religion</label>
                                <input type="text" class="form-control" name="religion" 
                                       value="<?php echo htmlspecialchars($app_details['religion']); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Caste</label>
                                <input type="text" class="form-control" name="caste" 
                                       value="<?php echo htmlspecialchars($app_details['caste']); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Reservation Category</label>
                                <select class="form-select" name="reservation_category">
                                    <option value="OC" <?php echo $app_details['reservation_category'] == 'OC' ? 'selected' : ''; ?>>OC</option>
                                    <option value="BC-A" <?php echo $app_details['reservation_category'] == 'BC-A' ? 'selected' : ''; ?>>BC-A</option>
                                    <option value="BC-B" <?php echo $app_details['reservation_category'] == 'BC-B' ? 'selected' : ''; ?>>BC-B</option>
                                    <option value="BC-C" <?php echo $app_details['reservation_category'] == 'BC-C' ? 'selected' : ''; ?>>BC-C</option>
                                    <option value="BC-D" <?php echo $app_details['reservation_category'] == 'BC-D' ? 'selected' : ''; ?>>BC-D</option>
                                    <option value="BC-E" <?php echo $app_details['reservation_category'] == 'BC-E' ? 'selected' : ''; ?>>BC-E</option>
                                    <option value="SC" <?php echo $app_details['reservation_category'] == 'SC' ? 'selected' : ''; ?>>SC</option>
                                    <option value="ST" <?php echo $app_details['reservation_category'] == 'ST' ? 'selected' : ''; ?>>ST</option>
                                    <option value="EWS" <?php echo $app_details['reservation_category'] == 'EWS' ? 'selected' : ''; ?>>EWS</option>
                                    <option value="PH" <?php echo $app_details['reservation_category'] == 'PH' ? 'selected' : ''; ?>>PH</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Physically Handicapped</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_physically_handicapped" 
                                           value="1" <?php echo $app_details['is_physically_handicapped'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Yes</label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Identification Mark 1</label>
                                <input type="text" class="form-control" name="identification_mark_1" 
                                       value="<?php echo htmlspecialchars($app_details['identification_mark_1']); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Identification Mark 2</label>
                                <input type="text" class="form-control" name="identification_mark_2" 
                                       value="<?php echo htmlspecialchars($app_details['identification_mark_2']); ?>">
                            </div>
                            
                            <div class="col-md-12">
                                <label class="form-label">Special Reservation (if any)</label>
                                <textarea class="form-control" name="special_reservation" rows="2"><?php echo htmlspecialchars($app_details['special_reservation']); ?></textarea>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Meeseva Caste Certificate No.</label>
                                <input type="text" class="form-control" name="meeseva_caste_certificate" 
                                       value="<?php echo htmlspecialchars($app_details['meeseva_caste_certificate']); ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Meeseva Income Certificate No.</label>
                                <input type="text" class="form-control" name="meeseva_income_certificate" 
                                       value="<?php echo htmlspecialchars($app_details['meeseva_income_certificate']); ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Ration Card Number</label>
                                <input type="text" class="form-control" name="ration_card_number" 
                                       value="<?php echo htmlspecialchars($app_details['ration_card_number']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-section">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="list.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Copy present address to permanent address
        function copyPresentAddress() {
            document.getElementsByName('permanent_door_no')[0].value = document.getElementsByName('present_door_no')[0].value;
            document.getElementsByName('permanent_street')[0].value = document.getElementsByName('present_street')[0].value;
            document.getElementsByName('permanent_village')[0].value = document.getElementsByName('present_village')[0].value;
            document.getElementsByName('permanent_mandal')[0].value = document.getElementsByName('present_mandal')[0].value;
            document.getElementsByName('permanent_district')[0].value = document.getElementsByName('present_district')[0].value;
            document.getElementsByName('permanent_pincode')[0].value = document.getElementsByName('present_pincode')[0].value;
        }
        
        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Form validation
        document.getElementById('editForm').addEventListener('submit', function(e) {
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        });
    </script>
</body>
</html>