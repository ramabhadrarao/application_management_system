<?php
/**
 * Academic History Management
 * 
 * File: student/academic-history.php
 * Purpose: Manage study history for previous 7 years
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

// Get existing study history
$history_query = "
    SELECT * FROM application_study_history 
    WHERE application_id = :application_id 
    ORDER BY display_order ASC, academic_year DESC
";
$stmt = $db->prepare($history_query);
$stmt->bindParam(':application_id', $student_application['id']);
$stmt->execute();
$study_history = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        
        if (isset($_POST['add_history'])) {
            // Add new study history entry
            $form_data = [
                'application_id' => $student_application['id'],
                'class_standard' => sanitizeInput($_POST['class_standard']),
                'place_of_study' => sanitizeInput($_POST['place_of_study']),
                'school_college_name' => sanitizeInput($_POST['school_college_name']),
                'academic_year' => sanitizeInput($_POST['academic_year'])
            ];
            
            // Validation
            if (empty($form_data['class_standard'])) {
                $errors[] = 'Class/Standard is required.';
            }
            
            if (empty($form_data['school_college_name'])) {
                $errors[] = 'School/College name is required.';
            }
            
            if (empty($form_data['academic_year'])) {
                $errors[] = 'Academic year is required.';
            } elseif (!preg_match('/^\d{4}-\d{4}$/', $form_data['academic_year'])) {
                $errors[] = 'Academic year must be in format YYYY-YYYY (e.g., 2020-2021).';
            }
            
            if (empty($errors)) {
                try {
                    $insert_query = "
                        INSERT INTO application_study_history 
                        (application_id, class_standard, place_of_study, school_college_name, academic_year)
                        VALUES (:application_id, :class_standard, :place_of_study, :school_college_name, :academic_year)
                    ";
                    
                    $stmt = $db->prepare($insert_query);
                    foreach ($form_data as $key => $value) {
                        $stmt->bindValue(':' . $key, $value);
                    }
                    
                    if ($stmt->execute()) {
                        $success_message = 'Study history added successfully!';
                        
                        // Refresh data
                        $stmt = $db->prepare($history_query);
                        $stmt->bindParam(':application_id', $student_application['id']);
                        $stmt->execute();
                        $study_history = $stmt->fetchAll();
                    } else {
                        $errors[] = 'Failed to add study history.';
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif (isset($_POST['update_history'])) {
            // Update existing study history entry
            $history_id = (int)sanitizeInput($_POST['history_id']);
            $form_data = [
                'class_standard' => sanitizeInput($_POST['class_standard']),
                'place_of_study' => sanitizeInput($_POST['place_of_study']),
                'school_college_name' => sanitizeInput($_POST['school_college_name']),
                'academic_year' => sanitizeInput($_POST['academic_year'])
            ];
            
            // Validation (same as add)
            if (empty($form_data['class_standard'])) {
                $errors[] = 'Class/Standard is required.';
            }
            
            if (empty($form_data['school_college_name'])) {
                $errors[] = 'School/College name is required.';
            }
            
            if (empty($form_data['academic_year'])) {
                $errors[] = 'Academic year is required.';
            } elseif (!preg_match('/^\d{4}-\d{4}$/', $form_data['academic_year'])) {
                $errors[] = 'Academic year must be in format YYYY-YYYY (e.g., 2020-2021).';
            }
            
            if (empty($errors)) {
                try {
                    $update_query = "
                        UPDATE application_study_history 
                        SET class_standard = :class_standard,
                            place_of_study = :place_of_study,
                            school_college_name = :school_college_name,
                            academic_year = :academic_year
                        WHERE id = :id AND application_id = :application_id
                    ";
                    
                    $stmt = $db->prepare($update_query);
                    foreach ($form_data as $key => $value) {
                        $stmt->bindValue(':' . $key, $value);
                    }
                    $stmt->bindParam(':id', $history_id);
                    $stmt->bindParam(':application_id', $student_application['id']);
                    
                    if ($stmt->execute()) {
                        $success_message = 'Study history updated successfully!';
                        
                        // Refresh data
                        $stmt = $db->prepare($history_query);
                        $stmt->bindParam(':application_id', $student_application['id']);
                        $stmt->execute();
                        $study_history = $stmt->fetchAll();
                    } else {
                        $errors[] = 'Failed to update study history.';
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif (isset($_POST['delete_history'])) {
            // Delete study history entry
            $history_id = (int)sanitizeInput($_POST['history_id']);
            
            try {
                $delete_query = "DELETE FROM application_study_history WHERE id = :id AND application_id = :application_id";
                $stmt = $db->prepare($delete_query);
                $stmt->bindParam(':id', $history_id);
                $stmt->bindParam(':application_id', $student_application['id']);
                
                if ($stmt->execute()) {
                    $success_message = 'Study history deleted successfully!';
                    
                    // Refresh data
                    $stmt = $db->prepare($history_query);
                    $stmt->bindParam(':application_id', $student_application['id']);
                    $stmt->execute();
                    $study_history = $stmt->fetchAll();
                } else {
                    $errors[] = 'Failed to delete study history.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    } else {
        $errors[] = 'Invalid request. Please try again.';
    }
}

$page_title = 'Academic History';
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
        
        .history-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            color: white;
            padding: 3rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .history-header::before {
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
        
        .history-icon {
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
        
        .history-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        
        .history-subtitle {
            opacity: 0.9;
            text-align: center;
        }
        
        .history-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .history-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .card-header-modern {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
            padding: 1.5rem;
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
        
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-color);
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 2rem;
            margin-left: 1rem;
        }
        
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        
        .timeline-marker {
            position: absolute;
            left: -2rem;
            top: 0.5rem;
            width: 16px;
            height: 16px;
            background: var(--primary-color);
            border-radius: 50%;
            border: 3px solid var(--white);
            box-shadow: 0 0 0 3px var(--primary-color);
        }
        
        .timeline-content {
            background: var(--bg-light);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            position: relative;
        }
        
        .timeline-content::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 20px;
            width: 0;
            height: 0;
            border-top: 10px solid transparent;
            border-bottom: 10px solid transparent;
            border-right: 10px solid var(--bg-light);
        }
        
        .history-item-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .history-details {
            flex: 1;
        }
        
        .history-class {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .history-institution {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .history-meta {
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .history-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-modern {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
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
        
        .btn-danger-modern {
            background: linear-gradient(135deg, var(--danger-color), #e74c3c);
            color: white;
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
        
        .alert-modern {
            border-radius: var(--radius-lg);
            border: none;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-light);
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .add-form {
            background: rgba(0, 84, 166, 0.05);
            border: 2px solid rgba(0, 84, 166, 0.1);
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .help-text {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 0.5rem;
        }
        
        .year-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .year-btn {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            background: var(--white);
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            font-size: 0.85rem;
        }
        
        .year-btn:hover, .year-btn.selected {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Header -->
        <div class="history-header">
            <div class="container">
                <div class="header-content">
                    <!-- Breadcrumb -->
                    <nav class="mb-3" style="background: rgba(255, 255, 255, 0.1); border-radius: var(--radius-lg); padding: 0.75rem 1rem; display: inline-block;">
                        <a href="<?php echo SITE_URL; ?>/dashboard.php" style="color: rgba(255, 255, 255, 0.8); text-decoration: none;">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                        <span class="mx-2">/</span>
                        <a href="<?php echo SITE_URL; ?>/student/education.php" style="color: rgba(255, 255, 255, 0.8); text-decoration: none;">
                            Education
                        </a>
                        <span class="mx-2">/</span>
                        <span>Academic History</span>
                    </nav>
                    
                    <div class="history-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h1 class="history-title">Academic History</h1>
                    <p class="history-subtitle">Manage your study history for the past 7 years</p>
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
            
            <!-- Add New History Form -->
            <div class="history-card">
                <div class="card-header-modern">
                    <h3 class="card-title-modern">
                        <i class="fas fa-plus-circle"></i>
                        Add Study History
                    </h3>
                </div>
                <div class="card-body-modern">
                    <div class="add-form">
                        <form method="POST" id="addHistoryForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="form-floating-modern">
                                        <input type="text" class="form-control-modern" name="class_standard" 
                                               id="classStandard" placeholder=" " required>
                                        <label class="form-label-modern">Class/Standard *</label>
                                    </div>
                                    <div class="help-text">e.g., 6th, 7th, 8th, 9th, 10th</div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="form-floating-modern">
                                        <input type="text" class="form-control-modern" name="academic_year" 
                                               id="academicYear" placeholder=" " required pattern="\d{4}-\d{4}">
                                        <label class="form-label-modern">Academic Year *</label>
                                    </div>
                                    <div class="help-text">Format: 2020-2021</div>
                                    <div class="year-selector">
                                        <?php
                                        $current_year = date('Y');
                                        for ($year = $current_year - 10; $year <= $current_year; $year++) {
                                            $academic_year = $year . '-' . ($year + 1);
                                            echo "<div class='year-btn' onclick='selectYear(\"$academic_year\")'>$academic_year</div>";
                                        }
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="form-floating-modern">
                                        <input type="text" class="form-control-modern" name="place_of_study" 
                                               id="placeOfStudy" placeholder=" ">
                                        <label class="form-label-modern">Place of Study</label>
                                    </div>
                                    <div class="help-text">City/Town where you studied</div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="d-grid">
                                        <button type="submit" name="add_history" class="btn-modern btn-primary-modern">
                                            <i class="fas fa-plus"></i>Add Entry
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="form-floating-modern">
                                        <input type="text" class="form-control-modern" name="school_college_name" 
                                               id="schoolCollegeName" placeholder=" " required>
                                        <label class="form-label-modern">School/College Name *</label>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Study History Timeline -->
            <div class="history-card">
                <div class="card-header-modern">
                    <h3 class="card-title-modern">
                        <i class="fas fa-timeline"></i>
                        Your Study History
                    </h3>
                </div>
                <div class="card-body-modern">
                    <?php if (empty($study_history)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h4>No Study History Added</h4>
                        <p>Add your study history for the past 7 years using the form above.</p>
                    </div>
                    <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($study_history as $history): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <div class="history-item-header">
                                    <div class="history-details">
                                        <div class="history-class"><?php echo htmlspecialchars($history['class_standard']); ?></div>
                                        <div class="history-institution"><?php echo htmlspecialchars($history['school_college_name']); ?></div>
                                        <div class="history-meta">
                                            <?php if ($history['place_of_study']): ?>
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($history['place_of_study']); ?> • 
                                            <?php endif; ?>
                                            <i class="fas fa-calendar-alt me-1"></i>
                                            <?php echo htmlspecialchars($history['academic_year']); ?>
                                        </div>
                                    </div>
                                    <div class="history-actions">
                                        <button type="button" class="btn-modern btn-outline-modern" 
                                                onclick="editHistory(<?php echo $history['id']; ?>, '<?php echo htmlspecialchars($history['class_standard']); ?>', '<?php echo htmlspecialchars($history['place_of_study']); ?>', '<?php echo htmlspecialchars($history['school_college_name']); ?>', '<?php echo htmlspecialchars($history['academic_year']); ?>')">
                                            <i class="fas fa-edit"></i>Edit
                                        </button>
                                        <button type="button" class="btn-modern btn-danger-modern" 
                                                onclick="deleteHistory(<?php echo $history['id']; ?>, '<?php echo htmlspecialchars($history['class_standard']); ?>')">
                                            <i class="fas fa-trash"></i>Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Navigation -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo SITE_URL; ?>/student/education.php" class="btn-modern btn-outline-modern">
                            <i class="fas fa-arrow-left"></i>Back to Education
                        </a>
                        <a href="<?php echo SITE_URL; ?>/student/documents.php" class="btn-modern btn-primary-modern">
                            <i class="fas fa-arrow-right"></i>Continue to Documents
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Study History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="history_id" id="editHistoryId">
                    
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating-modern">
                                    <input type="text" class="form-control-modern" name="class_standard" 
                                           id="editClassStandard" placeholder=" " required>
                                    <label class="form-label-modern">Class/Standard *</label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-floating-modern">
                                    <input type="text" class="form-control-modern" name="academic_year" 
                                           id="editAcademicYear" placeholder=" " required pattern="\d{4}-\d{4}">
                                    <label class="form-label-modern">Academic Year *</label>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-floating-modern">
                                    <input type="text" class="form-control-modern" name="place_of_study" 
                                           id="editPlaceOfStudy" placeholder=" ">
                                    <label class="form-label-modern">Place of Study</label>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-floating-modern">
                                    <input type="text" class="form-control-modern" name="school_college_name" 
                                           id="editSchoolCollegeName" placeholder=" " required>
                                    <label class="form-label-modern">School/College Name *</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" name="update_history" class="btn-modern btn-primary-modern">
                            <i class="fas fa-save"></i>Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this study history entry?</p>
                    <div class="alert alert-warning">
                        <strong id="deleteClassName"></strong> study history will be permanently removed.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="history_id" id="deleteHistoryId">
                        <button type="submit" name="delete_history" class="btn-modern btn-danger-modern">
                            <i class="fas fa-trash"></i>Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function selectYear(academicYear) {
            document.getElementById('academicYear').value = academicYear;
            
            // Update button states
            document.querySelectorAll('.year-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            event.target.classList.add('selected');
        }
        
        function editHistory(id, classStandard, placeOfStudy, schoolCollegeName, academicYear) {
            document.getElementById('editHistoryId').value = id;
            document.getElementById('editClassStandard').value = classStandard;
            document.getElementById('editPlaceOfStudy').value = placeOfStudy;
            document.getElementById('editSchoolCollegeName').value = schoolCollegeName;
            document.getElementById('editAcademicYear').value = academicYear;
            
            // Trigger floating labels
            document.querySelectorAll('#editModal .form-control-modern').forEach(input => {
                if (input.value) {
                    input.classList.add('has-value');
                }
            });
            
            const editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }
        
        function deleteHistory(id, className) {
            document.getElementById('deleteHistoryId').value = id;
            document.getElementById('deleteClassName').textContent = className;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
        
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
        
        // Form validation
        document.getElementById('addHistoryForm').addEventListener('submit', function(e) {
            const academicYear = document.getElementById('academicYear').value;
            const yearPattern = /^\d{4}-\d{4}$/;
            
            if (!yearPattern.test(academicYear)) {
                e.preventDefault();
                alert('Academic year must be in format YYYY-YYYY (e.g., 2020-2021)');
                return false;
            }
        });
        
        document.getElementById('editForm').addEventListener('submit', function(e) {
            const academicYear = document.getElementById('editAcademicYear').value;
            const yearPattern = /^\d{4}-\d{4}$/;
            
            if (!yearPattern.test(academicYear)) {
                e.preventDefault();
                alert('Academic year must be in format YYYY-YYYY (e.g., 2020-2021)');
                return false;
            }
        });
    </script>
</body>
</html>