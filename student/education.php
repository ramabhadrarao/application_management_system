<?php
/**
 * Educational Qualification Entry
 * 
 * File: student/education.php
 * Purpose: Enter and manage educational qualifications
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../config/config.php';
require_once '../classes/User.php';
require_once '../classes/Application.php';

// Require student login
requireLogin();
requirePermission('edit_own_application');

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$application = new Application($db);

$current_user_id = getCurrentUserId();
$errors = [];
$success_message = '';

// Get user details and application
$user_details = $user->getUserById($current_user_id);
$student_application = $application->getByUserId($current_user_id);

if (!$student_application) {
    header('Location: ' . SITE_URL . '/student/application.php?error=no_application');
    exit;
}

// Get education levels
$education_levels_query = "SELECT * FROM education_levels WHERE is_active = 1 ORDER BY display_order ASC";
$stmt = $db->prepare($education_levels_query);
$stmt->execute();
$education_levels = $stmt->fetchAll();

// Get existing education details
$existing_education_query = "
    SELECT aed.*, el.level_name, el.level_code 
    FROM application_education_details aed
    JOIN education_levels el ON aed.education_level_id = el.id
    WHERE aed.application_id = :application_id
    ORDER BY el.display_order ASC
";
$stmt = $db->prepare($existing_education_query);
$stmt->bindParam(':application_id', $student_application['id']);
$stmt->execute();
$existing_education = $stmt->fetchAll();

// Convert to associative array for easier access
$education_data = [];
foreach ($existing_education as $edu) {
    $education_data[$edu['education_level_id']] = $edu;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        
        $education_level_id = (int)sanitizeInput($_POST['education_level_id']);
        
        // Get form data
        $form_data = [
            'application_id' => $student_application['id'],
            'education_level_id' => $education_level_id,
            'hall_ticket_number' => sanitizeInput($_POST['hall_ticket_number']),
            'institution_name' => sanitizeInput($_POST['institution_name']),
            'board_university_name' => sanitizeInput($_POST['board_university_name']),
            'course_name' => sanitizeInput($_POST['course_name']),
            'specialization' => sanitizeInput($_POST['specialization']),
            'medium_of_instruction' => sanitizeInput($_POST['medium_of_instruction']),
            'pass_year' => (int)sanitizeInput($_POST['pass_year']),
            'passout_type' => sanitizeInput($_POST['passout_type']),
            'marks_obtained' => !empty($_POST['marks_obtained']) ? (int)sanitizeInput($_POST['marks_obtained']) : null,
            'maximum_marks' => !empty($_POST['maximum_marks']) ? (int)sanitizeInput($_POST['maximum_marks']) : null,
            'percentage' => !empty($_POST['percentage']) ? (float)sanitizeInput($_POST['percentage']) : null,
            'cgpa' => !empty($_POST['cgpa']) ? (float)sanitizeInput($_POST['cgpa']) : null,
            'grade' => sanitizeInput($_POST['grade']),
            'languages_studied' => sanitizeInput($_POST['languages_studied']),
            'second_language' => sanitizeInput($_POST['second_language']),
            'bridge_course' => sanitizeInput($_POST['bridge_course']),
            'gap_year_reason' => sanitizeInput($_POST['gap_year_reason'])
        ];
        
        // Validation
        if (empty($form_data['institution_name'])) {
            $errors[] = 'Institution name is required.';
        }
        
        if (empty($form_data['board_university_name'])) {
            $errors[] = 'Board/University name is required.';
        }
        
        if (empty($form_data['pass_year']) || $form_data['pass_year'] < 1980 || $form_data['pass_year'] > date('Y')) {
            $errors[] = 'Please enter a valid pass year.';
        }
        
        // Validate marks/percentage
        if (!empty($form_data['marks_obtained']) && !empty($form_data['maximum_marks'])) {
            if ($form_data['marks_obtained'] > $form_data['maximum_marks']) {
                $errors[] = 'Marks obtained cannot be greater than maximum marks.';
            } else {
                // Calculate percentage if not provided
                if (empty($form_data['percentage'])) {
                    $form_data['percentage'] = round(($form_data['marks_obtained'] / $form_data['maximum_marks']) * 100, 2);
                }
            }
        }
        
        if (!empty($form_data['percentage']) && ($form_data['percentage'] < 0 || $form_data['percentage'] > 100)) {
            $errors[] = 'Percentage must be between 0 and 100.';
        }
        
        if (!empty($form_data['cgpa']) && ($form_data['cgpa'] < 0 || $form_data['cgpa'] > 10)) {
            $errors[] = 'CGPA must be between 0 and 10.';
        }
        
        if (empty($errors)) {
            try {
                // Check if record exists
                $check_query = "SELECT id FROM application_education_details 
                               WHERE application_id = :app_id AND education_level_id = :level_id";
                $stmt = $db->prepare($check_query);
                $stmt->bindParam(':app_id', $student_application['id']);
                $stmt->bindParam(':level_id', $education_level_id);
                $stmt->execute();
                $existing_record = $stmt->fetch();
                
                if ($existing_record) {
                    // Update existing record
                    $update_query = "
                        UPDATE application_education_details 
                        SET hall_ticket_number = :hall_ticket_number,
                            institution_name = :institution_name,
                            board_university_name = :board_university_name,
                            course_name = :course_name,
                            specialization = :specialization,
                            medium_of_instruction = :medium_of_instruction,
                            pass_year = :pass_year,
                            passout_type = :passout_type,
                            marks_obtained = :marks_obtained,
                            maximum_marks = :maximum_marks,
                            percentage = :percentage,
                            cgpa = :cgpa,
                            grade = :grade,
                            languages_studied = :languages_studied,
                            second_language = :second_language,
                            bridge_course = :bridge_course,
                            gap_year_reason = :gap_year_reason,
                            date_updated = CURRENT_TIMESTAMP
                        WHERE id = :id
                    ";
                    
                    $stmt = $db->prepare($update_query);
                    foreach ($form_data as $key => $value) {
                        if ($key !== 'application_id' && $key !== 'education_level_id') {
                            $stmt->bindValue(':' . $key, $value);
                        }
                    }
                    $stmt->bindParam(':id', $existing_record['id']);
                    
                    if ($stmt->execute()) {
                        $success_message = 'Education details updated successfully!';
                    } else {
                        $errors[] = 'Failed to update education details.';
                    }
                } else {
                    // Insert new record
                    $insert_query = "
                        INSERT INTO application_education_details (
                            application_id, education_level_id, hall_ticket_number,
                            institution_name, board_university_name, course_name,
                            specialization, medium_of_instruction, pass_year,
                            passout_type, marks_obtained, maximum_marks,
                            percentage, cgpa, grade, languages_studied,
                            second_language, bridge_course, gap_year_reason
                        ) VALUES (
                            :application_id, :education_level_id, :hall_ticket_number,
                            :institution_name, :board_university_name, :course_name,
                            :specialization, :medium_of_instruction, :pass_year,
                            :passout_type, :marks_obtained, :maximum_marks,
                            :percentage, :cgpa, :grade, :languages_studied,
                            :second_language, :bridge_course, :gap_year_reason
                        )
                    ";
                    
                    $stmt = $db->prepare($insert_query);
                    foreach ($form_data as $key => $value) {
                        $stmt->bindValue(':' . $key, $value);
                    }
                    
                    if ($stmt->execute()) {
                        $success_message = 'Education details added successfully!';
                    } else {
                        $errors[] = 'Failed to add education details.';
                    }
                }
                
                // Refresh existing education data
                if (empty($errors)) {
                    $stmt = $db->prepare($existing_education_query);
                    $stmt->bindParam(':application_id', $student_application['id']);
                    $stmt->execute();
                    $existing_education = $stmt->fetchAll();
                    
                    $education_data = [];
                    foreach ($existing_education as $edu) {
                        $education_data[$edu['education_level_id']] = $edu;
                    }
                }
                
            } catch (PDOException $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    } else {
        $errors[] = 'Invalid request. Please try again.';
    }
}

$page_title = 'Educational Qualifications';
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
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --text-dark: #2c3e50;
            --text-light: #6c757d;
            --bg-light: #f8f9fa;
            --white: #ffffff;
            --border-color: #e9ecef;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
            --radius-lg: 0.75rem;
        }
        
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
        }
        
        .education-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            color: white;
            padding: 3rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .education-header::before {
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
        
        .education-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
        
        .education-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        
        .education-subtitle {
            opacity: 0.9;
            text-align: center;
        }
        
        .education-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .education-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .card-header-modern {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
            padding: 1.5rem;
            position: relative;
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
        
        .level-selector {
            background: var(--bg-light);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .level-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .level-card {
            background: var(--white);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        
        .level-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .level-card.completed {
            border-color: var(--success-color);
            background: rgba(40, 167, 69, 0.05);
        }
        
        .level-card.completed::after {
            content: '';
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            width: 20px;
            height: 20px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .level-card.completed::before {
            content: '✓';
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            width: 20px;
            height: 20px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            font-weight: bold;
            z-index: 2;
        }
        
        .level-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.2rem;
        }
        
        .level-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .level-description {
            color: var(--text-light);
            font-size: 0.85rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-floating-modern {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .form-control-modern, .form-select-modern {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }
        
        .form-control-modern:focus, .form-select-modern:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 84, 166, 0.1);
        }
        
        .form-label-modern {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: var(--white);
            padding: 0 0.5rem;
            color: var(--text-light);
            transition: all 0.3s ease;
            pointer-events: none;
        }
        
        .form-control-modern:focus + .form-label-modern,
        .form-control-modern:not(:placeholder-shown) + .form-label-modern {
            top: -0.5rem;
            font-size: 0.85rem;
            color: var(--primary-color);
        }
        
        .btn-modern {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
        }
        
        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
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
            text-decoration: none;
        }
        
        .alert-modern {
            border-radius: var(--radius-lg);
            border: none;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .calculation-helper {
            background: rgba(0, 84, 166, 0.05);
            border: 1px solid rgba(0, 84, 166, 0.2);
            border-radius: var(--radius-lg);
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .help-text {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 0.5rem;
        }
        
        .existing-education {
            background: var(--bg-light);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .education-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .summary-item {
            text-align: center;
        }
        
        .summary-label {
            font-size: 0.8rem;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        
        .summary-value {
            font-weight: 600;
            color: var(--text-dark);
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Header -->
        <div class="education-header">
            <div class="container">
                <div class="header-content">
                    <!-- Breadcrumb -->
                    <nav class="mb-3" style="background: rgba(255, 255, 255, 0.1); border-radius: var(--radius-lg); padding: 0.75rem 1rem; display: inline-block;">
                        <a href="<?php echo SITE_URL; ?>/dashboard.php" style="color: rgba(255, 255, 255, 0.8); text-decoration: none;">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                        <span class="mx-2">/</span>
                        <span>Educational Qualifications</span>
                    </nav>
                    
                    <div class="education-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h1 class="education-title">Educational Qualifications</h1>
                    <p class="education-subtitle">Add and manage your educational background</p>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="container py-4">
            <!-- Alerts -->
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-modern">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Please correct the following errors:</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-modern">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
            </div>
            <?php endif; ?>
            
            <!-- Education Level Selector -->
            <div class="level-selector">
                <h3 class="mb-3">
                    <i class="fas fa-layer-group text-primary me-2"></i>
                    Select Education Level
                </h3>
                <div class="level-grid">
                    <?php foreach ($education_levels as $level): ?>
                    <div class="level-card <?php echo isset($education_data[$level['id']]) ? 'completed' : ''; ?>" 
                         onclick="selectEducationLevel(<?php echo $level['id']; ?>, '<?php echo htmlspecialchars($level['level_name']); ?>')">
                        <div class="level-icon">
                            <i class="fas fa-<?php 
                                switch($level['level_code']) {
                                    case 'SSC': echo 'school';break;
                                    case 'INTER': echo 'building';break;
                                    case 'DIPLOMA': echo 'certificate';break;
                                    case 'GRADUATION': echo 'graduation-cap';break;
                                    case 'POST_GRADUATION': echo 'user-graduate';break;
                                    case 'DOCTORATE': echo 'award';break;
                                    default: echo 'book';
                                }
                            ?>"></i>
                        </div>
                        <div class="level-name"><?php echo htmlspecialchars($level['level_name']); ?></div>
                        <div class="level-description">
                            <?php echo isset($education_data[$level['id']]) ? 'Completed' : 'Click to add'; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Existing Education Summary -->
            <?php if (!empty($existing_education)): ?>
            <div class="education-card">
                <div class="card-header-modern">
                    <h3 class="card-title-modern">
                        <i class="fas fa-list-alt"></i>
                        Your Education Summary
                    </h3>
                </div>
                <div class="card-body-modern">
                    <?php foreach ($existing_education as $edu): ?>
                    <div class="existing-education">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="mb-0"><?php echo htmlspecialchars($edu['level_name']); ?></h5>
                            <button class="btn-modern btn-outline-modern btn-sm" 
                                    onclick="editEducation(<?php echo $edu['education_level_id']; ?>, '<?php echo htmlspecialchars($edu['level_name']); ?>')">
                                <i class="fas fa-edit"></i>Edit
                            </button>
                        </div>
                        
                        <div class="education-summary">
                            <div class="summary-item">
                                <div class="summary-label">Institution</div>
                                <div class="summary-value"><?php echo htmlspecialchars($edu['institution_name']); ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Board/University</div>
                                <div class="summary-value"><?php echo htmlspecialchars($edu['board_university_name']); ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Pass Year</div>
                                <div class="summary-value"><?php echo $edu['pass_year']; ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Percentage</div>
                                <div class="summary-value">
                                    <?php echo $edu['percentage'] ? number_format($edu['percentage'], 2) . '%' : 'N/A'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Education Form (Hidden by default) -->
            <div class="education-card" id="educationForm" style="display: none;">
                <div class="card-header-modern">
                    <h3 class="card-title-modern">
                        <i class="fas fa-plus-circle"></i>
                        <span id="formTitle">Add Education Details</span>
                    </h3>
                </div>
                <div class="card-body-modern">
                    <form method="POST" id="educationDetailsForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="education_level_id" id="educationLevelId">
                        
                        <!-- Basic Information -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="fas fa-info-circle"></i>
                                Basic Information
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating-modern">
                                        <input type="text" class="form-control-modern" name="hall_ticket_number" 
                                               id="hallTicketNumber" placeholder=" ">
                                        <label class="form-label-modern">Hall Ticket / Roll Number</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating-modern">
                                        <input type="text" class="form-control-modern" name="institution_name" 
                                               id="institutionName" placeholder=" " required>
                                        <label class="form-label-modern">Institution Name *</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating-modern">
                                        <input type="text" class="form-control-modern" name="board_university_name" 
                                               id="boardUniversityName" placeholder=" " required>
                                        <label class="form-label-modern">Board / University Name *</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating-modern">
                                        <input type="text" class="form-control-modern" name="course_name" 
                                               id="courseName" placeholder=" ">
                                        <label class="form-label-modern">Course / Stream</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating-modern">
                                        <input type="text" class="form-control-modern" name="specialization" 
                                               id="specialization" placeholder=" ">
                                        <label class="form-label-modern">Specialization</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <select class="form-select-modern" name="medium_of_instruction" id="mediumOfInstruction">
                                        <option value="">Select Medium</option>
                                        <option value="English">English</option>
                                        <option value="Telugu">Telugu</option>
                                        <option value="Hindi">Hindi</option>
                                        <option value="Tamil">Tamil</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    <div class="help-text">Medium of instruction</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Academic Performance -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="fas fa-chart-line"></i>
                                Academic Performance
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="form-floating-modern">
                                        <input type="number" class="form-control-modern" name="pass_year" 
                                               id="passYear" placeholder=" " min="1980" max="<?php echo date('Y'); ?>" required>
                                        <label class="form-label-modern">Pass Year *</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <select class="form-select-modern" name="passout_type" id="passoutType">
                                        <option value="Regular">Regular</option>
                                        <option value="Supplementary">Supplementary</option>
                                        <option value="Betterment">Betterment</option>
                                        <option value="Compartment">Compartment</option>
                                    </select>
                                    <div class="help-text">Type of pass</div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="form-floating-modern">
                                        <input type="number" class="form-control-modern" name="marks_obtained" 
                                               id="marksObtained" placeholder=" " min="0" onchange="calculatePercentage()">
                                        <label class="form-label-modern">Marks Obtained</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="form-floating-modern">
                                        <input type="number" class="form-control-modern" name="maximum_marks" 
                                               id="maximumMarks" placeholder=" " min="0" onchange="calculatePercentage()">
                                        <label class="form-label-modern">Maximum Marks</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-floating-modern">
                                        <input type="number" class="form-control-modern" name="percentage" 
                                               id="percentage" placeholder=" " min="0" max="100" step="0.01">
                                        <label class="form-label-modern">Percentage</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-floating-modern">
                                        <input type="number" class="form-control-modern" name="cgpa" 
                                               id="cgpa" placeholder=" " min="0" max="10" step="0.01">
                                        <label class="form-label-modern">CGPA (if applicable)</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-floating-modern">
                                        <input type="text" class="form-control-modern" name="grade" 
                                               id="grade" placeholder=" ">
                                        <label class="form-label-modern">Grade (if applicable)</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="calculation-helper" id="calculationHelper" style="display: none;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span><i class="fas fa-calculator me-2"></i>Calculated Percentage:</span>
                                    <strong id="calculatedPercentage">0%</strong>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Additional Details -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="fas fa-plus-circle"></i>
                                Additional Details
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating-modern">
                                        <input type="text" class="form-control-modern" name="languages_studied" 
                                               id="languagesStudied" placeholder=" ">
                                        <label class="form-label-modern">Languages Studied</label>
                                    </div>
                                    <div class="help-text">e.g., English, Telugu, Hindi</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating-modern">
                                        <input type="text" class="form-control-modern" name="second_language" 
                                               id="secondLanguage" placeholder=" ">
                                        <label class="form-label-modern">Second Language</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating-modern">
                                        <input type="text" class="form-control-modern" name="bridge_course" 
                                               id="bridgeCourse" placeholder=" ">
                                        <label class="form-label-modern">Bridge Course (if any)</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating-modern">
                                        <textarea class="form-control-modern" name="gap_year_reason" 
                                                  id="gapYearReason" placeholder=" " rows="3" style="min-height: 60px;"></textarea>
                                        <label class="form-label-modern">Gap Year Reason (if applicable)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn-modern btn-outline-modern" onclick="cancelForm()">
                                <i class="fas fa-times"></i>Cancel
                            </button>
                            <button type="submit" class="btn-modern btn-primary-modern">
                                <i class="fas fa-save"></i>Save Education Details
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Navigation -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo SITE_URL; ?>/student/application.php" class="btn-modern btn-outline-modern">
                            <i class="fas fa-arrow-left"></i>Back to Application
                        </a>
                        <a href="<?php echo SITE_URL; ?>/student/academic-history.php" class="btn-modern btn-outline-modern">
                            <i class="fas fa-history"></i>Academic History
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    
    <script>
        // Store existing education data for editing
        const existingEducationData = <?php echo json_encode($education_data); ?>;
        
        function selectEducationLevel(levelId, levelName) {
            // Show the form
            document.getElementById('educationForm').style.display = 'block';
            document.getElementById('educationLevelId').value = levelId;
            document.getElementById('formTitle').textContent = 
                existingEducationData[levelId] ? 'Edit ' + levelName : 'Add ' + levelName;
            
            // Load existing data if available
            if (existingEducationData[levelId]) {
                loadEducationData(existingEducationData[levelId]);
            } else {
                clearForm();
            }
            
            // Scroll to form
            document.getElementById('educationForm').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        }
        
        function editEducation(levelId, levelName) {
            selectEducationLevel(levelId, levelName);
        }
        
        function loadEducationData(data) {
            // Populate form with existing data
            document.getElementById('hallTicketNumber').value = data.hall_ticket_number || '';
            document.getElementById('institutionName').value = data.institution_name || '';
            document.getElementById('boardUniversityName').value = data.board_university_name || '';
            document.getElementById('courseName').value = data.course_name || '';
            document.getElementById('specialization').value = data.specialization || '';
            document.getElementById('mediumOfInstruction').value = data.medium_of_instruction || '';
            document.getElementById('passYear').value = data.pass_year || '';
            document.getElementById('passoutType').value = data.passout_type || 'Regular';
            document.getElementById('marksObtained').value = data.marks_obtained || '';
            document.getElementById('maximumMarks').value = data.maximum_marks || '';
            document.getElementById('percentage').value = data.percentage || '';
            document.getElementById('cgpa').value = data.cgpa || '';
            document.getElementById('grade').value = data.grade || '';
            document.getElementById('languagesStudied').value = data.languages_studied || '';
            document.getElementById('secondLanguage').value = data.second_language || '';
            document.getElementById('bridgeCourse').value = data.bridge_course || '';
            document.getElementById('gapYearReason').value = data.gap_year_reason || '';
            
            // Trigger floating labels
            document.querySelectorAll('.form-control-modern').forEach(input => {
                if (input.value) {
                    input.classList.add('has-value');
                }
            });
        }
        
        function clearForm() {
            document.getElementById('educationDetailsForm').reset();
            document.getElementById('calculationHelper').style.display = 'none';
        }
        
        function cancelForm() {
            document.getElementById('educationForm').style.display = 'none';
            clearForm();
        }
        
        function calculatePercentage() {
            const marksObtained = parseFloat(document.getElementById('marksObtained').value);
            const maximumMarks = parseFloat(document.getElementById('maximumMarks').value);
            
            if (marksObtained && maximumMarks && maximumMarks > 0) {
                const calculatedPercentage = (marksObtained / maximumMarks * 100).toFixed(2);
                
                document.getElementById('calculatedPercentage').textContent = calculatedPercentage + '%';
                document.getElementById('calculationHelper').style.display = 'block';
                
                // Auto-fill percentage if empty
                if (!document.getElementById('percentage').value) {
                    document.getElementById('percentage').value = calculatedPercentage;
                }
            } else {
                document.getElementById('calculationHelper').style.display = 'none';
            }
        }
        
        // Form validation
        document.getElementById('educationDetailsForm').addEventListener('submit', function(e) {
            const institutionName = document.getElementById('institutionName').value.trim();
            const boardUniversityName = document.getElementById('boardUniversityName').value.trim();
            const passYear = document.getElementById('passYear').value;
            
            if (!institutionName || !boardUniversityName || !passYear) {
                e.preventDefault();
                alert('Please fill in all required fields (marked with *)');
                return false;
            }
            
            const currentYear = new Date().getFullYear();
            if (passYear < 1980 || passYear > currentYear) {
                e.preventDefault();
                alert('Please enter a valid pass year between 1980 and ' + currentYear);
                return false;
            }
            
            const marksObtained = parseFloat(document.getElementById('marksObtained').value);
            const maximumMarks = parseFloat(document.getElementById('maximumMarks').value);
            
            if (marksObtained && maximumMarks && marksObtained > maximumMarks) {
                e.preventDefault();
                alert('Marks obtained cannot be greater than maximum marks');
                return false;
            }
            
            const percentage = parseFloat(document.getElementById('percentage').value);
            if (percentage && (percentage < 0 || percentage > 100)) {
                e.preventDefault();
                alert('Percentage must be between 0 and 100');
                return false;
            }
            
            const cgpa = parseFloat(document.getElementById('cgpa').value);
            if (cgpa && (cgpa < 0 || cgpa > 10)) {
                e.preventDefault();
                alert('CGPA must be between 0 and 10');
                return false;
            }
        });
        
        // Floating label functionality
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-control-modern');
            
            inputs.forEach(input => {
                // Check if input has value on load
                if (input.value) {
                    input.classList.add('has-value');
                }
                
                input.addEventListener('input', function() {
                    if (this.value) {
                        this.classList.add('has-value');
                    } else {
                        this.classList.remove('has-value');
                    }
                });
            });
        });
        
        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-modern');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cancelForm();
            }
        });
        
        // Auto-save functionality (placeholder)
        let autoSaveTimeout;
        document.querySelectorAll('.form-control-modern, .form-select-modern').forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(() => {
                    // Auto-save logic would go here
                    console.log('Auto-saving...');
                }, 2000);
            });
        });
    </script>
</body>
</html>