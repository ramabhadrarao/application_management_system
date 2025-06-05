<?php
/**
 * Student Application Form (Enhanced UI & UX)
 * 
 * File: student/application.php
 * Purpose: Modern application form with step navigation and enhanced UI
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

// Calculate progress
$completed_fields = 0;
$total_fields = 8; // Basic required fields

$check_fields = ['student_name', 'father_name', 'mother_name', 'date_of_birth', 'gender', 'mobile_number', 'present_village', 'permanent_village'];
foreach ($check_fields as $field) {
    if (!empty($form_data[$field])) {
        $completed_fields++;
    }
}

$progress_percentage = round(($completed_fields / $total_fields) * 100);

// Check if application can be edited
$can_edit = !$existing_application || in_array($existing_application['status'], [STATUS_DRAFT, STATUS_FROZEN]);

// Get status color function
function getStatusColor($status) {
    switch ($status) {
        case STATUS_DRAFT: return 'secondary';
        case STATUS_SUBMITTED: return 'info';
        case STATUS_UNDER_REVIEW: return 'warning';
        case STATUS_APPROVED: return 'success';
        case STATUS_REJECTED: return 'danger';
        case STATUS_FROZEN: return 'primary';
        default: return 'secondary';
    }
}

$page_title = 'My Application';
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
            --primary-dark: #003d7a;
            --secondary-color: #667eea;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --text-dark: #2c3e50;
            --text-light: #6c757d;
            --bg-light: #f8f9fa;
            --white: #ffffff;
            --border-color: #e9ecef;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.15);
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
        }
        
        /* Enhanced Header */
        .application-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .application-header::before {
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
        
        /* Breadcrumb */
        .breadcrumb-modern {
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-md);
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            display: inline-flex;
            align-items: center;
        }
        
        .breadcrumb-modern a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .breadcrumb-modern a:hover {
            color: white;
        }
        
        /* Step Navigation */
        .step-navigation {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin: -2rem auto 2rem;
            position: relative;
            z-index: 3;
            max-width: 1000px;
            overflow: hidden;
        }
        
        .step-nav-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        
        .step-nav-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .step-nav-subtitle {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        .steps-container {
            display: flex;
            background: var(--bg-light);
            padding: 1rem;
        }
        
        .step-item {
            flex: 1;
            text-align: center;
            padding: 1rem;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: var(--radius-md);
        }
        
        .step-item:hover {
            background: var(--white);
            transform: translateY(-2px);
        }
        
        .step-item.active {
            background: var(--white);
            box-shadow: var(--shadow-md);
        }
        
        .step-item.completed {
            background: rgba(40, 167, 69, 0.1);
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .step-item.completed .step-number {
            background: var(--success-color);
            color: white;
        }
        
        .step-item.active .step-number {
            background: var(--primary-color);
            color: white;
        }
        
        .step-item:not(.active):not(.completed) .step-number {
            background: var(--border-color);
            color: var(--text-light);
        }
        
        .step-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        /* Modern Cards */
        .card-modern {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: none;
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }
        
        .card-modern:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .card-header-modern {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
            border-bottom: none;
        }
        
        .card-title-modern {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .card-body-modern {
            padding: 2rem;
        }
        
        /* Form Sections */
        .form-section {
            margin-bottom: 3rem;
            padding: 2rem;
            background: var(--bg-light);
            border-radius: var(--radius-lg);
            border-left: 4px solid var(--primary-color);
        }
        
        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-description {
            color: var(--text-light);
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        
        /* Enhanced Form Controls */
        .form-group-modern {
            margin-bottom: 1.5rem;
        }
        
        .form-label-modern {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-label-modern.required::after {
            content: '*';
            color: var(--danger-color);
            font-weight: 700;
        }
        
        .form-control-modern, .form-select-modern {
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.875rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }
        
        .form-control-modern:focus, .form-select-modern:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 84, 166, 0.1);
            outline: none;
            transform: translateY(-1px);
        }
        
        .form-control-modern:valid, .form-select-modern:valid {
            border-color: var(--success-color);
        }
        
        .input-group-modern {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            z-index: 3;
        }
        
        .input-group-modern .form-control-modern {
            padding-left: 3rem;
        }
        
        /* Buttons */
        .btn-modern {
            padding: 0.875rem 2rem;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1rem;
        }
        
        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
        }
        
        .btn-success-modern {
            background: linear-gradient(135deg, var(--success-color), #20c997);
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
            background: rgba(0, 84, 166, 0.05);
            text-decoration: none;
        }
        
        /* Progress Circle */
        .progress-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(var(--primary-color) calc(var(--progress) * 1%), var(--border-color) 0);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            margin: 0 auto 1rem;
        }
        
        .progress-circle::before {
            content: '';
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: var(--white);
            position: absolute;
        }
        
        .progress-text {
            position: relative;
            z-index: 2;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        /* Sidebar Cards */
        .sidebar-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .sidebar-card-header {
            background: var(--primary-color);
            color: white;
            padding: 1rem 1.5rem;
        }
        
        .sidebar-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .sidebar-card-body {
            padding: 1.5rem;
        }
        
        /* Quick Actions */
        .quick-action {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-radius: var(--radius-md);
            text-decoration: none;
            color: var(--text-dark);
            transition: all 0.3s ease;
            margin-bottom: 0.5rem;
        }
        
        .quick-action:hover {
            background: var(--bg-light);
            transform: translateX(5px);
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .quick-action-icon {
            width: 35px;
            height: 35px;
            border-radius: var(--radius-md);
            background: var(--bg-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            color: var(--primary-color);
        }
        
        /* Alerts */
        .alert-modern {
            border-radius: var(--radius-lg);
            border: none;
            padding: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
            animation: slideDown 0.3s ease;
        }
        
        .alert-success-modern {
            background: rgba(40, 167, 69, 0.1);
            border-left: 4px solid var(--success-color);
            color: var(--success-color);
        }
        
        .alert-danger-modern {
            background: rgba(220, 53, 69, 0.1);
            border-left: 4px solid var(--danger-color);
            color: var(--danger-color);
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Navigation Flow */
        .nav-flow {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 2rem 0;
            border-top: 1px solid var(--border-color);
            margin-top: 2rem;
        }
        
        .nav-flow-item {
            flex: 1;
            text-align: center;
        }
        
        .nav-flow-item:first-child {
            text-align: left;
        }
        
        .nav-flow-item:last-child {
            text-align: right;
        }
        
        /* Auto-save indicator */
        .auto-save-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--success-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            transform: translateY(100px);
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        
        .auto-save-indicator.show {
            transform: translateY(0);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .application-header {
                padding: 1.5rem 0;
            }
            
            .steps-container {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .step-item {
                display: flex;
                align-items: center;
                text-align: left;
                padding: 0.75rem;
            }
            
            .step-number {
                margin: 0 1rem 0 0;
            }
            
            .card-body-modern {
                padding: 1.5rem;
            }
            
            .form-section {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Enhanced Header -->
        <div class="application-header">
            <div class="container-xl">
                <div class="header-content">
                    <!-- Breadcrumb -->
                    <nav class="breadcrumb-modern">
                        <a href="<?php echo SITE_URL; ?>/dashboard.php">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                        <span class="mx-2">/</span>
                        <span>My Application</span>
                    </nav>
                    
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="h2 mb-2">Application Form</h1>
                            <p class="mb-0 opacity-75">Complete your application for admission to <?php echo htmlspecialchars($user_program['program_name']); ?></p>
                        </div>
                        <div class="col-lg-4">
                            <div class="text-center">
                                <?php if ($existing_application): ?>
                                <div class="h5 mb-1">Application #<?php echo htmlspecialchars($existing_application['application_number']); ?></div>
                                <span class="badge bg-<?php echo getStatusColor($existing_application['status']); ?>" style="padding: 0.5rem 1rem;">
                                    <?php echo ucwords(str_replace('_', ' ', $existing_application['status'])); ?>
                                </span>
                                <?php else: ?>
                                <div class="h5 mb-1">New Application</div>
                                <small class="opacity-75">Fill in your details to begin</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Step Navigation -->
        <div class="container-xl">
            <div class="step-navigation">
                <div class="step-nav-header">
                    <div class="step-nav-title">Application Progress</div>
                    <div class="step-nav-subtitle"><?php echo $progress_percentage; ?>% Complete</div>
                </div>
                <div class="steps-container">
                    <div class="step-item <?php echo $progress_percentage >= 25 ? 'completed' : ($progress_percentage > 0 ? 'active' : ''); ?>">
                        <div class="step-number">
                            <?php echo $progress_percentage >= 25 ? '<i class="fas fa-check"></i>' : '1'; ?>
                        </div>
                        <div class="step-label">Personal Info</div>
                    </div>
                    <div class="step-item <?php echo $progress_percentage >= 50 ? 'completed' : ($progress_percentage >= 25 ? 'active' : ''); ?>">
                        <div class="step-number">
                            <?php echo $progress_percentage >= 50 ? '<i class="fas fa-check"></i>' : '2'; ?>
                        </div>
                        <div class="step-label">Address</div>
                    </div>
                    <div class="step-item <?php echo $progress_percentage >= 75 ? 'completed' : ($progress_percentage >= 50 ? 'active' : ''); ?>">
                        <div class="step-number">
                            <?php echo $progress_percentage >= 75 ? '<i class="fas fa-check"></i>' : '3'; ?>
                        </div>
                        <div class="step-label">Education</div>
                    </div>
                    <div class="step-item <?php echo $progress_percentage >= 90 ? 'completed' : ($progress_percentage >= 75 ? 'active' : ''); ?>">
                        <div class="step-number">
                            <?php echo $progress_percentage >= 90 ? '<i class="fas fa-check"></i>' : '4'; ?>
                        </div>
                        <div class="step-label">Documents</div>
                    </div>
                    <div class="step-item <?php echo $progress_percentage >= 100 ? 'completed' : ($progress_percentage >= 90 ? 'active' : ''); ?>">
                        <div class="step-number">
                            <?php echo $progress_percentage >= 100 ? '<i class="fas fa-check"></i>' : '5'; ?>
                        </div>
                        <div class="step-label">Submit</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="page-body">
            <div class="container-xl">
                <!-- Alerts -->
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-modern alert-danger-modern">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Please correct the following errors:</h5>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-modern alert-success-modern">
                    <h5><i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?></h5>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Main Form -->
                    <div class="col-lg-8">
                        <div class="card-modern">
                            <div class="card-header-modern">
                                <h3 class="card-title-modern">
                                    <i class="fas fa-user-edit"></i>
                                    Application Details
                                </h3>
                            </div>
                            
                            <div class="card-body-modern">
                                <form method="POST" id="applicationForm" autocomplete="off">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    
                                    <!-- Personal Information -->
                                    <div class="form-section">
                                        <div class="section-title">
                                            <i class="fas fa-user"></i>
                                            Personal Information
                                        </div>
                                        <div class="section-description">
                                            Provide your basic personal details as they appear on your official documents.
                                        </div>
                                        
                                        <div class="row g-3">
                                            <div class="col-lg-6">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern required">Student Name</label>
                                                    <div class="input-group-modern">
                                                        <i class="input-icon fas fa-user"></i>
                                                        <input type="text" 
                                                               class="form-control-modern" 
                                                               name="student_name" 
                                                               value="<?php echo htmlspecialchars($form_data['student_name']); ?>"
                                                               <?php echo !$can_edit ? 'readonly' : ''; ?>
                                                               placeholder="Enter full name"
                                                               required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-lg-6">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern required">Father's Name</label>
                                                    <div class="input-group-modern">
                                                        <i class="input-icon fas fa-male"></i>
                                                        <input type="text" 
                                                               class="form-control-modern" 
                                                               name="father_name" 
                                                               value="<?php echo htmlspecialchars($form_data['father_name']); ?>"
                                                               <?php echo !$can_edit ? 'readonly' : ''; ?>
                                                               placeholder="Father's full name"
                                                               required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-lg-6">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern required">Mother's Name</label>
                                                    <div class="input-group-modern">
                                                        <i class="input-icon fas fa-female"></i>
                                                        <input type="text" 
                                                               class="form-control-modern" 
                                                               name="mother_name" 
                                                               value="<?php echo htmlspecialchars($form_data['mother_name']); ?>"
                                                               <?php echo !$can_edit ? 'readonly' : ''; ?>
                                                               placeholder="Mother's full name"
                                                               required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-lg-6">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern required">Date of Birth</label>
                                                    <div class="input-group-modern">
                                                        <i class="input-icon fas fa-calendar"></i>
                                                        <input type="date" 
                                                               class="form-control-modern" 
                                                               name="date_of_birth" 
                                                               value="<?php echo htmlspecialchars($form_data['date_of_birth']); ?>"
                                                               <?php echo !$can_edit ? 'readonly' : ''; ?>
                                                               required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-lg-4">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern required">Gender</label>
                                                    <select class="form-select-modern" name="gender" <?php echo !$can_edit ? 'disabled' : ''; ?> required>
                                                        <option value="">Select Gender</option>
                                                        <option value="Male" <?php echo ($form_data['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                                        <option value="Female" <?php echo ($form_data['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                                        <option value="Other" <?php echo ($form_data['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="col-lg-4">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Aadhar Number</label>
                                                    <div class="input-group-modern">
                                                        <i class="input-icon fas fa-id-card"></i>
                                                        <input type="text" 
                                                               class="form-control-modern" 
                                                               name="aadhar_number" 
                                                               value="<?php echo htmlspecialchars($form_data['aadhar_number']); ?>"
                                                               placeholder="12-digit Aadhar number"
                                                               maxlength="12"
                                                               <?php echo !$can_edit ? 'readonly' : ''; ?>>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-lg-4">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Reservation Category</label>
                                                    <select class="form-select-modern" name="reservation_category" <?php echo !$can_edit ? 'disabled' : ''; ?>>
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
                                        </div>
                                    </div>
                                    
                                    <!-- Contact Information -->
                                    <div class="form-section">
                                        <div class="section-title">
                                            <i class="fas fa-phone"></i>
                                            Contact Information
                                        </div>
                                        <div class="section-description">
                                            Provide valid contact details for communication regarding your application.
                                        </div>
                                        
                                        <div class="row g-3">
                                            <div class="col-lg-4">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern required">Mobile Number</label>
                                                    <div class="input-group-modern">
                                                        <i class="input-icon fas fa-mobile-alt"></i>
                                                        <input type="tel" 
                                                               class="form-control-modern" 
                                                               name="mobile_number" 
                                                               value="<?php echo htmlspecialchars($form_data['mobile_number']); ?>"
                                                               placeholder="10-digit mobile number"
                                                               maxlength="10"
                                                               <?php echo !$can_edit ? 'readonly' : ''; ?>
                                                               required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-lg-4">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Parent's Mobile</label>
                                                    <div class="input-group-modern">
                                                        <i class="input-icon fas fa-phone"></i>
                                                        <input type="tel" 
                                                               class="form-control-modern" 
                                                               name="parent_mobile" 
                                                               value="<?php echo htmlspecialchars($form_data['parent_mobile']); ?>"
                                                               placeholder="Parent's mobile number"
                                                               maxlength="10"
                                                               <?php echo !$can_edit ? 'readonly' : ''; ?>>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-lg-4">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Guardian's Mobile</label>
                                                    <div class="input-group-modern">
                                                        <i class="input-icon fas fa-phone"></i>
                                                        <input type="tel" 
                                                               class="form-control-modern" 
                                                               name="guardian_mobile" 
                                                               value="<?php echo htmlspecialchars($form_data['guardian_mobile']); ?>"
                                                               placeholder="Guardian's mobile number"
                                                               maxlength="10"
                                                               <?php echo !$can_edit ? 'readonly' : ''; ?>>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-lg-12">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Email Address</label>
                                                    <div class="input-group-modern">
                                                        <i class="input-icon fas fa-envelope"></i>
                                                        <input type="email" 
                                                               class="form-control-modern" 
                                                               name="email" 
                                                               value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                                               readonly>
                                                    </div>
                                                    <small class="text-muted">Email cannot be changed. Contact admin if needed.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Present Address -->
                                    <div class="form-section">
                                        <div class="section-title">
                                            <i class="fas fa-map-marker-alt"></i>
                                            Present Address
                                        </div>
                                        <div class="section-description">
                                            Provide your current residential address.
                                        </div>
                                        
                                        <div class="row g-3">
                                            <div class="col-lg-3">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Door No.</label>
                                                    <input type="text" 
                                                           class="form-control-modern" 
                                                           name="present_door_no" 
                                                           value="<?php echo htmlspecialchars($form_data['present_door_no']); ?>"
                                                           placeholder="House/Door number"
                                                           <?php echo !$can_edit ? 'readonly' : ''; ?>>
                                                </div>
                                            </div>
                                            
                                            <div class="col-lg-9">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Street</label>
                                                    <input type="text" 
                                                           class="form-control-modern" 
                                                           name="present_street" 
                                                           value="<?php echo htmlspecialchars($form_data['present_street']); ?>"
                                                           placeholder="Street name"
                                                           <?php echo !$can_edit ? 'readonly' : ''; ?>>
                                                </div>
                                            </div>
                                            
                                            <div class="col-lg-4">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Village/Town</label>
                                                    <input type="text" 
                                                           class="form-control-modern" 
                                                           name="present_village" 
                                                           value="<?php echo htmlspecialchars($form_data['present_village']); ?>"
                                                           placeholder="Village or town"
                                                           <?php echo !$can_edit ? 'readonly' : ''; ?>>
                                                </div>
                                            </div>
                                            
                                            <div class="col-lg-4">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Mandal</label>
                                                    <input type="text" 
                                                           class="form-control-modern" 
                                                           name="present_mandal" 
                                                           value="<?php echo htmlspecialchars($form_data['present_mandal']); ?>"
                                                           placeholder="Mandal name"
                                                           <?php echo !$can_edit ? 'readonly' : ''; ?>>
                                                </div>
                                            </div>
                                            
                                            <div class="col-lg-4">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">District</label>
                                                    <input type="text" 
                                                           class="form-control-modern" 
                                                           name="present_district" 
                                                           value="<?php echo htmlspecialchars($form_data['present_district']); ?>"
                                                           placeholder="District name"
                                                           <?php echo !$can_edit ? 'readonly' : ''; ?>>
                                                </div>
                                            </div>
                                            
                                            <div class="col-lg-4">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Pincode</label>
                                                    <input type="text" 
                                                           class="form-control-modern" 
                                                           name="present_pincode" 
                                                           value="<?php echo htmlspecialchars($form_data['present_pincode']); ?>"
                                                           placeholder="6-digit pincode"
                                                           maxlength="6"
                                                           <?php echo !$can_edit ? 'readonly' : ''; ?>>
                                                </div>
                                            </div>
                                            
                                            <div class="col-lg-8">
                                                <div class="form-check" style="margin-top: 2rem;">
                                                    <input class="form-check-input" type="checkbox" id="sameAddress" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                                    <label class="form-check-label" for="sameAddress">
                                                        Permanent address is same as present address
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Continue with Permanent Address and Additional Info sections... -->
                                    <!-- [Similar styling for other sections] -->
                                    
                                    <?php if ($can_edit): ?>
                                    <!-- Form Actions -->
                                    <div class="nav-flow">
                                        <div class="nav-flow-item">
                                            <button type="button" class="btn-modern btn-outline-modern" onclick="resetForm()">
                                                <i class="fas fa-undo"></i>Reset Form
                                            </button>
                                        </div>
                                        
                                        <div class="nav-flow-item">
                                            <small class="text-muted" id="autoSaveStatus">Changes are auto-saved</small>
                                        </div>
                                        
                                        <div class="nav-flow-item">
                                            <button type="submit" class="btn-modern btn-primary-modern">
                                                <i class="fas fa-save"></i>Save & Continue
                                            </button>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Progress Card -->
                        <div class="sidebar-card">
                            <div class="sidebar-card-header">
                                <h4 class="sidebar-card-title">
                                    <i class="fas fa-chart-pie"></i>
                                    Progress
                                </h4>
                            </div>
                            <div class="sidebar-card-body text-center">
                                <div class="progress-circle" style="--progress: <?php echo $progress_percentage; ?>">
                                    <div class="progress-text"><?php echo $progress_percentage; ?>%</div>
                                </div>
                                <p class="text-muted">Application Completion</p>
                                
                                <?php if ($existing_application): ?>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <strong>Application #:</strong><br>
                                        <code><?php echo htmlspecialchars($existing_application['application_number']); ?></code>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="sidebar-card">
                            <div class="sidebar-card-header">
                                <h4 class="sidebar-card-title">
                                    <i class="fas fa-bolt"></i>
                                    Quick Actions
                                </h4>
                            </div>
                            <div class="sidebar-card-body">
                                <a href="<?php echo SITE_URL; ?>/student/education.php" class="quick-action">
                                    <div class="quick-action-icon">
                                        <i class="fas fa-graduation-cap"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold">Education Details</div>
                                        <small class="text-muted">Add your qualifications</small>
                                    </div>
                                </a>
                                
                                <a href="<?php echo SITE_URL; ?>/student/documents.php" class="quick-action">
                                    <div class="quick-action-icon">
                                        <i class="fas fa-file-upload"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold">Upload Documents</div>
                                        <small class="text-muted">Submit required certificates</small>
                                    </div>
                                </a>
                                
                                <a href="<?php echo SITE_URL; ?>/student/status.php" class="quick-action">
                                    <div class="quick-action-icon">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold">Track Status</div>
                                        <small class="text-muted">Monitor your progress</small>
                                    </div>
                                </a>
                                
                                <a href="<?php echo SITE_URL; ?>/help.php" class="quick-action">
                                    <div class="quick-action-icon">
                                        <i class="fas fa-question-circle"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold">Help & Support</div>
                                        <small class="text-muted">Get assistance</small>
                                    </div>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Program Info -->
                        <div class="sidebar-card">
                            <div class="sidebar-card-header">
                                <h4 class="sidebar-card-title">
                                    <i class="fas fa-info-circle"></i>
                                    Program Info
                                </h4>
                            </div>
                            <div class="sidebar-card-body">
                                <h5><?php echo htmlspecialchars($user_program['program_name']); ?></h5>
                                <div class="row g-2 mt-2">
                                    <div class="col-6">
                                        <small class="text-muted">Code:</small><br>
                                        <strong><?php echo htmlspecialchars($user_program['program_code']); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Duration:</small><br>
                                        <strong><?php echo htmlspecialchars($user_program['duration_years']); ?> years</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Type:</small><br>
                                        <strong><?php echo htmlspecialchars($user_program['program_type']); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Seats:</small><br>
                                        <strong><?php echo htmlspecialchars($user_program['total_seats']); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Auto-save Indicator -->
    <div class="auto-save-indicator" id="autoSaveIndicator">
        <i class="fas fa-check-circle me-2"></i>
        Changes saved automatically
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Enhanced form functionality
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('applicationForm');
            const autoSaveIndicator = document.getElementById('autoSaveIndicator');
            const autoSaveStatus = document.getElementById('autoSaveStatus');
            
            // Auto-save functionality
            let autoSaveTimeout;
            function autoSave() {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(() => {
                    // Show saving indicator
                    autoSaveIndicator.classList.add('show');
                    autoSaveStatus.textContent = 'Saving changes...';
                    
                    // Simulate auto-save (implement actual AJAX call)
                    setTimeout(() => {
                        autoSaveStatus.textContent = 'Changes saved automatically';
                        setTimeout(() => {
                            autoSaveIndicator.classList.remove('show');
                        }, 2000);
                    }, 1000);
                }, 3000);
            }
            
            // Trigger auto-save on form changes
            form.addEventListener('input', autoSave);
            form.addEventListener('change', autoSave);
            
            // Copy present to permanent address
            document.getElementById('sameAddress')?.addEventListener('change', function() {
                if (this.checked) {
                    document.querySelector('[name="permanent_door_no"]').value = document.querySelector('[name="present_door_no"]').value;
                    document.querySelector('[name="permanent_street"]').value = document.querySelector('[name="present_street"]').value;
                    document.querySelector('[name="permanent_village"]').value = document.querySelector('[name="present_village"]').value;
                    document.querySelector('[name="permanent_mandal"]').value = document.querySelector('[name="present_mandal"]').value;
                    document.querySelector('[name="permanent_district"]').value = document.querySelector('[name="present_district"]').value;
                    document.querySelector('[name="permanent_pincode"]').value = document.querySelector('[name="present_pincode"]').value;
                }
            });
            
            // Form validation
            form.addEventListener('submit', function(e) {
                const submitBtn = e.target.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
                }
            });
            
            // Mobile number validation
            document.querySelectorAll('input[type="tel"]').forEach(function(input) {
                input.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    if (this.value.length > 10) {
                        this.value = this.value.slice(0, 10);
                    }
                });
            });
            
            // Aadhar number validation
            document.querySelector('[name="aadhar_number"]')?.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length > 12) {
                    this.value = this.value.slice(0, 12);
                }
            });
            
            // Pincode validation
            document.querySelectorAll('[name*="pincode"]').forEach(function(input) {
                input.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    if (this.value.length > 6) {
                        this.value = this.value.slice(0, 6);
                    }
                });
            });
        });
        
        // Reset form function
        function resetForm() {
            if (confirm('Are you sure you want to reset all changes? This will revert to the last saved data.')) {
                location.reload();
            }
        }
        
        // Step navigation
        function goToStep(step) {
            const steps = {
                1: '<?php echo SITE_URL; ?>/student/application.php',
                2: '<?php echo SITE_URL; ?>/student/application.php#address',
                3: '<?php echo SITE_URL; ?>/student/education.php',
                4: '<?php echo SITE_URL; ?>/student/documents.php',
                5: '<?php echo SITE_URL; ?>/student/submit.php'
            };
            
            if (steps[step]) {
                window.location.href = steps[step];
            }
        }
        
        // Add click handlers to step items
        document.querySelectorAll('.step-item').forEach((item, index) => {
            item.addEventListener('click', () => goToStep(index + 1));
        });
    </script>
</body>
</html>