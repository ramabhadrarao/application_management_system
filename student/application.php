<?php
/**
 * Student Application Form
 * 
 * File: student/application.php
 * Purpose: Main application form for students to fill their details
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../config/config.php';
require_once '../classes/User.php';
require_once '../classes/Application.php';
require_once '../classes/Program.php';

// Require student login
requireLogin();
requirePermission('edit_own_application');

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$application = new Application($db);
$program = new Program($db);

$current_user_id = getCurrentUserId();
$errors = [];
$success_message = '';

// Get user details
$user_details = $user->getUserById($current_user_id);
$user_program = $program->getById($user_details['program_id']);

// Get existing application or initialize new one
$existing_application = $application->getByUserId($current_user_id);

// Initialize form data
$form_data = [
    'student_name' => '',
    'father_name' => '',
    'mother_name' => '',
    'date_of_birth' => '',
    'gender' => '',
    'aadhar_number' => '',
    'mobile_number' => '',
    'parent_mobile' => '',
    'guardian_mobile' => '',
    'email' => $user_details['email'],
    'present_door_no' => '',
    'present_street' => '',
    'present_village' => '',
    'present_mandal' => '',
    'present_district' => '',
    'present_pincode' => '',
    'permanent_door_no' => '',
    'permanent_street' => '',
    'permanent_village' => '',
    'permanent_mandal' => '',
    'permanent_district' => '',
    'permanent_pincode' => '',
    'religion' => '',
    'caste' => '',
    'reservation_category' => 'OC',
    'is_physically_handicapped' => 0,
    'sadaram_number' => '',
    'identification_mark_1' => '',
    'identification_mark_2' => '',
    'special_reservation' => '',
    'meeseva_caste_certificate' => '',
    'meeseva_income_certificate' => '',
    'ration_card_number' => ''
];

// If application exists, populate form data
if ($existing_application) {
    foreach ($form_data as $key => $default_value) {
        if (isset($existing_application[$key])) {
            $form_data[$key] = $existing_application[$key];
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        
        // Get form data
        foreach ($form_data as $key => $default_value) {
            if (isset($_POST[$key])) {
                $form_data[$key] = sanitizeInput($_POST[$key]);
            }
        }
        
        // Validation
        $required_fields = [
            'student_name' => 'Student Name',
            'father_name' => 'Father Name',
            'mother_name' => 'Mother Name',
            'date_of_birth' => 'Date of Birth',
            'gender' => 'Gender',
            'mobile_number' => 'Mobile Number'
        ];
        
        foreach ($required_fields as $field => $label) {
            if (empty($form_data[$field])) {
                $errors[] = $label . ' is required.';
            }
        }
        
        // Validate date of birth
        if (!empty($form_data['date_of_birth'])) {
            $dob = DateTime::createFromFormat('Y-m-d', $form_data['date_of_birth']);
            if (!$dob || $dob->format('Y-m-d') !== $form_data['date_of_birth']) {
                $errors[] = 'Please enter a valid date of birth.';
            } else {
                $age = $dob->diff(new DateTime())->y;
                if ($age < 16 || $age > 35) {
                    $errors[] = 'Age should be between 16 and 35 years.';
                }
            }
        }
        
        // Validate mobile number
        if (!empty($form_data['mobile_number'])) {
            if (!preg_match('/^[6-9]\d{9}$/', $form_data['mobile_number'])) {
                $errors[] = 'Please enter a valid 10-digit mobile number.';
            }
        }
        
        // Validate Aadhar number
        if (!empty($form_data['aadhar_number'])) {
            if (!preg_match('/^\d{12}$/', $form_data['aadhar_number'])) {
                $errors[] = 'Please enter a valid 12-digit Aadhar number.';
            }
        }
        
        // If no errors, save/update application
        if (empty($errors)) {
            $form_data['user_id'] = $current_user_id;
            $form_data['program_id'] = $user_details['program_id'];
            $form_data['academic_year'] = CURRENT_ACADEMIC_YEAR;
            
            if ($existing_application) {
                // Update existing application
                if ($application->update($existing_application['id'], $form_data)) {
                    $success_message = 'Application updated successfully!';
                    // Refresh application data
                    $existing_application = $application->getByUserId($current_user_id);
                } else {
                    $errors[] = 'Failed to update application. Please try again.';
                }
            } else {
                // Create new application
                $application_id = $application->create($form_data);
                if ($application_id) {
                    $success_message = 'Application created successfully!';
                    // Get the newly created application
                    $existing_application = $application->getByUserId($current_user_id);
                } else {
                    $errors[] = 'Failed to create application. Please try again.';
                }
            }
        }
    } else {
        $errors[] = 'Invalid request. Please try again.';
    }
}

$page_title = 'My Application';
$page_subtitle = 'Fill in your application details';

// Check if application can be edited
$can_edit = !$existing_application || in_array($existing_application['status'], [STATUS_DRAFT, STATUS_FROZEN]);

include '../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user me-2"></i>Personal Information
                </h3>
                <?php if ($existing_application): ?>
                <div class="card-actions">
                    <span class="badge bg-<?php echo getStatusColor($existing_application['status']); ?>">
                        <?php echo ucwords(str_replace('_', ' ', $existing_application['status'])); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible m-3" role="alert">
                <div class="d-flex">
                    <div class="flex-fill">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Please correct the following errors:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible m-3" role="alert">
                <div class="d-flex">
                    <div class="flex-fill">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success_message; ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <form method="POST" id="applicationForm" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="card-body">
                    <!-- Basic Information -->
                    <h4 class="mb-3">Basic Information</h4>
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label required">Student Name</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="student_name" 
                                   value="<?php echo htmlspecialchars($form_data['student_name']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>
                                   required>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label required">Father's Name</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="father_name" 
                                   value="<?php echo htmlspecialchars($form_data['father_name']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>
                                   required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label required">Mother's Name</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="mother_name" 
                                   value="<?php echo htmlspecialchars($form_data['mother_name']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>
                                   required>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label required">Date of Birth</label>
                            <input type="date" 
                                   class="form-control" 
                                   name="date_of_birth" 
                                   value="<?php echo htmlspecialchars($form_data['date_of_birth']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>
                                   required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label required">Gender</label>
                            <select class="form-select" name="gender" <?php echo !$can_edit ? 'disabled' : ''; ?> required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo ($form_data['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($form_data['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($form_data['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Aadhar Number</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="aadhar_number" 
                                   value="<?php echo htmlspecialchars($form_data['aadhar_number']); ?>"
                                   placeholder="12-digit Aadhar number"
                                   maxlength="12"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Reservation Category</label>
                            <select class="form-select" name="reservation_category" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                <option value="OC" <?php echo ($form_data['reservation_category'] == 'OC') ? 'selected' : ''; ?>>OC</option>
                                <option value="BC-A" <?php echo ($form_data['reservation_category'] == 'BC-A') ? 'selected' : ''; ?>>BC-A</option>
                                <option value="BC-B" <?php echo ($form_data['reservation_category'] == 'BC-B') ? 'selected' : ''; ?>>BC-B</option>
                                <option value="BC-C" <?php echo ($form_data['reservation_category'] == 'BC-C') ? 'selected' : ''; ?>>BC-C</option>
                                <option value="BC-D" <?php echo ($form_data['reservation_category'] == 'BC-D') ? 'selected' : ''; ?>>BC-D</option>
                                <option value="BC-E" <?php echo ($form_data['reservation_category'] == 'BC-E') ? 'selected' : ''; ?>>BC-E</option>
                                <option value="SC" <?php echo ($form_data['reservation_category'] == 'SC') ? 'selected' : ''; ?>>SC</option>
                                <option value="ST" <?php echo ($form_data['reservation_category'] == 'ST') ? 'selected' : ''; ?>>ST</option>
                                <option value="EWS" <?php echo ($form_data['reservation_category'] == 'EWS') ? 'selected' : ''; ?>>EWS</option>
                                <option value="PH" <?php echo ($form_data['reservation_category'] == 'PH') ? 'selected' : ''; ?>>PH</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <h4 class="mb-3 mt-4">Contact Information</h4>
                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label required">Mobile Number</label>
                            <input type="tel" 
                                   class="form-control" 
                                   name="mobile_number" 
                                   value="<?php echo htmlspecialchars($form_data['mobile_number']); ?>"
                                   placeholder="10-digit mobile number"
                                   maxlength="10"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>
                                   required>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Parent's Mobile</label>
                            <input type="tel" 
                                   class="form-control" 
                                   name="parent_mobile" 
                                   value="<?php echo htmlspecialchars($form_data['parent_mobile']); ?>"
                                   placeholder="Parent's mobile number"
                                   maxlength="10"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Guardian's Mobile</label>
                            <input type="tel" 
                                   class="form-control" 
                                   name="guardian_mobile" 
                                   value="<?php echo htmlspecialchars($form_data['guardian_mobile']); ?>"
                                   placeholder="Guardian's mobile number"
                                   maxlength="10"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-lg-12">
                            <label class="form-label">Email Address</label>
                            <input type="email" 
                                   class="form-control" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                   readonly>
                            <div class="form-text">Email cannot be changed. Contact admin if needed.</div>
                        </div>
                    </div>
                    
                    <!-- Present Address -->
                    <h4 class="mb-3 mt-4">Present Address</h4>
                    <div class="row mb-3">
                        <div class="col-lg-3">
                            <label class="form-label">Door No.</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="present_door_no" 
                                   value="<?php echo htmlspecialchars($form_data['present_door_no']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-lg-9">
                            <label class="form-label">Street</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="present_street" 
                                   value="<?php echo htmlspecialchars($form_data['present_street']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label">Village/Town</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="present_village" 
                                   value="<?php echo htmlspecialchars($form_data['present_village']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Mandal</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="present_mandal" 
                                   value="<?php echo htmlspecialchars($form_data['present_mandal']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">District</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="present_district" 
                                   value="<?php echo htmlspecialchars($form_data['present_district']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label">Pincode</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="present_pincode" 
                                   value="<?php echo htmlspecialchars($form_data['present_pincode']); ?>"
                                   maxlength="6"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-lg-8 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="sameAddress" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                <label class="form-check-label" for="sameAddress">
                                    Permanent address is same as present address
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Permanent Address -->
                    <h4 class="mb-3 mt-4">Permanent Address</h4>
                    <div class="row mb-3">
                        <div class="col-lg-3">
                            <label class="form-label">Door No.</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="permanent_door_no" 
                                   value="<?php echo htmlspecialchars($form_data['permanent_door_no']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-lg-9">
                            <label class="form-label">Street</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="permanent_street" 
                                   value="<?php echo htmlspecialchars($form_data['permanent_street']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label">Village/Town</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="permanent_village" 
                                   value="<?php echo htmlspecialchars($form_data['permanent_village']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Mandal</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="permanent_mandal" 
                                   value="<?php echo htmlspecialchars($form_data['permanent_mandal']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">District</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="permanent_district" 
                                   value="<?php echo htmlspecialchars($form_data['permanent_district']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label">Pincode</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="permanent_pincode" 
                                   value="<?php echo htmlspecialchars($form_data['permanent_pincode']); ?>"
                                   maxlength="6"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <h4 class="mb-3 mt-4">Additional Information</h4>
                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label">Religion</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="religion" 
                                   value="<?php echo htmlspecialchars($form_data['religion']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Caste</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="caste" 
                                   value="<?php echo htmlspecialchars($form_data['caste']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Sadaram Number</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="sadaram_number" 
                                   value="<?php echo htmlspecialchars($form_data['sadaram_number']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label">Identification Mark 1</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="identification_mark_1" 
                                   value="<?php echo htmlspecialchars($form_data['identification_mark_1']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Identification Mark 2</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="identification_mark_2" 
                                   value="<?php echo htmlspecialchars($form_data['identification_mark_2']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label">Meeseva Caste Certificate</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="meeseva_caste_certificate" 
                                   value="<?php echo htmlspecialchars($form_data['meeseva_caste_certificate']); ?>"
                                   placeholder="Certificate number"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Meeseva Income Certificate</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="meeseva_income_certificate" 
                                   value="<?php echo htmlspecialchars($form_data['meeseva_income_certificate']); ?>"
                                   placeholder="Certificate number"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Ration Card Number</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="ration_card_number" 
                                   value="<?php echo htmlspecialchars($form_data['ration_card_number']); ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-lg-12">
                            <label class="form-label">Special Reservation (if any)</label>
                            <textarea class="form-control" 
                                      name="special_reservation" 
                                      rows="3"
                                      <?php echo !$can_edit ? 'readonly' : ''; ?>
                                      placeholder="Mention any special reservations like Sports, NCC, etc."><?php echo htmlspecialchars($form_data['special_reservation']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-lg-12">
                            <div class="form-check">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       name="is_physically_handicapped" 
                                       value="1"
                                       <?php echo ($form_data['is_physically_handicapped'] == 1) ? 'checked' : ''; ?>
                                       <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                <label class="form-check-label">
                                    I am physically handicapped
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($can_edit): ?>
                <div class="card-footer bg-transparent">
                    <div class="d-flex justify-content-between">
                        <div>
                            <small class="text-muted" id="auto-save-indicator">
                                Changes will be auto-saved
                            </small>
                        </div>
                        <div>
                            <button type="button" class="btn btn-secondary me-2" onclick="resetForm()">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Application
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Application Status -->
        <?php if ($existing_application): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle me-2"></i>Application Status
                </h3>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Application Number</span>
                        <code><?php echo htmlspecialchars($existing_application['application_number']); ?></code>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Status</span>
                        <span class="badge bg-<?php echo getStatusColor($existing_application['status']); ?>">
                            <?php echo ucwords(str_replace('_', ' ', $existing_application['status'])); ?>
                        </span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Program</span>
                        <span><?php echo htmlspecialchars($user_program['program_code']); ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Academic Year</span>
                        <span><?php echo CURRENT_ACADEMIC_YEAR; ?></span>
                    </div>
                    <?php if ($existing_application['submitted_at']): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Submitted On</span>
                        <span><?php echo formatDate($existing_application['submitted_at']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($existing_application['status'] === STATUS_DRAFT): ?>
                <div class="mt-3">
                    <a href="<?php echo SITE_URL; ?>/student/documents.php" class="btn btn-success w-100">
                        <i class="fas fa-arrow-right me-2"></i>Next: Upload Documents
                    </a>
                </div>
                <?php elseif ($existing_application['status'] === STATUS_SUBMITTED): ?>
                <div class="mt-3">
                    <a href="<?php echo SITE_URL; ?>/student/submit.php" class="btn btn-warning w-100">
                        <i class="fas fa-lock me-2"></i>Final Submit & Freeze
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Program Information -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-graduation-cap me-2"></i>Program Details
                </h3>
            </div>
            <div class="card-body">
                <h5><?php echo htmlspecialchars($user_program['program_name']); ?></h5>
                <p class="text-muted mb-2">
                    <strong>Code:</strong> <?php echo htmlspecialchars($user_program['program_code']); ?><br>
                    <strong>Type:</strong> <?php echo htmlspecialchars($user_program['program_type']); ?><br>
                    <strong>Duration:</strong> <?php echo htmlspecialchars($user_program['duration_years']); ?> years<br>
                    <strong>Department:</strong> <?php echo htmlspecialchars($user_program['department']); ?>
                </p>
                
                <?php if ($user_program['eligibility_criteria']): ?>
                <div class="mt-3">
                    <strong>Eligibility:</strong>
                    <p class="text-muted small mb-0">
                        <?php echo nl2br(htmlspecialchars($user_program['eligibility_criteria'])); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Links -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-external-link-alt me-2"></i>Quick Links
                </h3>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <a href="<?php echo SITE_URL; ?>/student/documents.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-alt me-2"></i>Upload Documents
                    </a>
                    <a href="<?php echo SITE_URL; ?>/student/status.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-line me-2"></i>Application Status
                    </a>
                    <a href="<?php echo SITE_URL; ?>/profile.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-user me-2"></i>My Profile
                    </a>
                    <a href="<?php echo SITE_URL; ?>/help.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-question-circle me-2"></i>Help & FAQ
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * Helper function to get status color for badges
 */
function getStatusColor($status) {
    switch ($status) {
        case STATUS_DRAFT: return 'secondary';
        case STATUS_SUBMITTED: return 'info';
        case STATUS_UNDER_REVIEW: return 'warning';
        case STATUS_APPROVED: return 'success';
        case STATUS_REJECTED: return 'danger';
        case STATUS_FROZEN: return 'dark';
        default: return 'secondary';
    }
}

// Page-specific JavaScript
$page_js = "
// Auto-save functionality
enableAutoSave('#applicationForm', '" . SITE_URL . "/ajax/save-application.php', 30000);

// Copy present to permanent address
document.getElementById('sameAddress').addEventListener('change', function() {
    if (this.checked) {
        document.querySelector('[name=\"permanent_door_no\"]').value = document.querySelector('[name=\"present_door_no\"]').value;
        document.querySelector('[name=\"permanent_street\"]').value = document.querySelector('[name=\"present_street\"]').value;
        document.querySelector('[name=\"permanent_village\"]').value = document.querySelector('[name=\"present_village\"]').value;
        document.querySelector('[name=\"permanent_mandal\"]').value = document.querySelector('[name=\"present_mandal\"]').value;
        document.querySelector('[name=\"permanent_district\"]').value = document.querySelector('[name=\"present_district\"]').value;
        document.querySelector('[name=\"permanent_pincode\"]').value = document.querySelector('[name=\"present_pincode\"]').value;
    }
});

// Reset form function
function resetForm() {
    if (confirm('Are you sure you want to reset all changes? This will revert to the last saved data.')) {
        location.reload();
    }
}

// Validate mobile numbers
document.querySelectorAll('input[type=\"tel\"]').forEach(function(input) {
    input.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length > 10) {
            this.value = this.value.slice(0, 10);
        }
    });
});

// Validate Aadhar number
document.querySelector('[name=\"aadhar_number\"]').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
    if (this.value.length > 12) {
        this.value = this.value.slice(0, 12);
    }
});

// Validate pincode
document.querySelectorAll('[name*=\"pincode\"]').forEach(function(input) {
    input.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length > 6) {
            this.value = this.value.slice(0, 6);
        }
    });
});
";

include '../includes/footer.php';
?>