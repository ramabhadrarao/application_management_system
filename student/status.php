<?php
/**
 * Student Application Status Tracking
 * 
 * File: student/status.php
 * Purpose: Track application status with timeline and updates
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../config/config.php';
require_once '../classes/User.php';
require_once '../classes/Application.php';
require_once '../classes/Program.php';

// Require student login
requireLogin();
requirePermission('view_own_application');

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$application = new Application($db);
$program = new Program($db);

$current_user_id = getCurrentUserId();

// Get user details and application
$user_details = $user->getUserById($current_user_id);
$student_application = $application->getByUserId($current_user_id);

if (!$student_application) {
    header('Location: ' . SITE_URL . '/student/application.php?error=no_application');
    exit;
}

// Get application status history
$status_history = $application->getStatusHistory($student_application['id']);

// Get program details
$user_program = $program->getById($student_application['program_id']);

// Status configuration
$status_config = [
    STATUS_DRAFT => [
        'label' => 'Draft',
        'color' => 'secondary',
        'icon' => 'fas fa-edit',
        'description' => 'Application is being filled out'
    ],
    STATUS_SUBMITTED => [
        'label' => 'Submitted',
        'color' => 'info',
        'icon' => 'fas fa-paper-plane',
        'description' => 'Application submitted for review'
    ],
    STATUS_FROZEN => [
        'label' => 'Final Submission',
        'color' => 'primary',
        'icon' => 'fas fa-lock',
        'description' => 'Application frozen for review'
    ],
    STATUS_UNDER_REVIEW => [
        'label' => 'Under Review',
        'color' => 'warning',
        'icon' => 'fas fa-eye',
        'description' => 'Application is being reviewed by admissions'
    ],
    STATUS_APPROVED => [
        'label' => 'Approved',
        'color' => 'success',
        'icon' => 'fas fa-check-circle',
        'description' => 'Application approved! Congratulations!'
    ],
    STATUS_REJECTED => [
        'label' => 'Rejected',
        'color' => 'danger',
        'icon' => 'fas fa-times-circle',
        'description' => 'Application has been rejected'
    ]
];

$current_status = $status_config[$student_application['status']] ?? $status_config[STATUS_DRAFT];

// Calculate progress percentage
function getStatusProgress($status) {
    $status_order = [
        STATUS_DRAFT => 20,
        STATUS_SUBMITTED => 40,
        STATUS_FROZEN => 50,
        STATUS_UNDER_REVIEW => 75,
        STATUS_APPROVED => 100,
        STATUS_REJECTED => 100
    ];
    
    return $status_order[$status] ?? 0;
}

$progress_percentage = getStatusProgress($student_application['status']);

$page_title = 'Application Status';
$page_subtitle = 'Track your application progress';
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
        
        /* Status Header */
        .status-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 3rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .status-header::before {
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
        
        .status-icon-large {
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 1.5rem;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .status-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        
        .status-description {
            font-size: 1.2rem;
            opacity: 0.9;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        /* Progress Section */
        .progress-section {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            padding: 2rem;
            margin-top: -3rem;
            position: relative;
            z-index: 3;
        }
        
        .progress-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .progress-percentage {
            font-size: 4rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .progress-label {
            color: var(--text-light);
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }
        
        .progress-bar-container {
            background: var(--border-color);
            height: 12px;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 6px;
            transition: width 1s ease;
            position: relative;
            overflow: hidden;
        }
        
        .progress-bar-fill::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.3),
                transparent
            );
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        /* Timeline */
        .timeline-container {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .timeline-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
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
            top: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 3px solid var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            z-index: 2;
        }
        
        .timeline-marker.completed {
            background: var(--success-color);
            color: white;
        }
        
        .timeline-marker.current {
            background: var(--primary-color);
            color: white;
            animation: pulse 2s infinite;
        }
        
        .timeline-marker.pending {
            background: var(--border-color);
            color: var(--text-light);
        }
        
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(0, 84, 166, 0.7); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(0, 84, 166, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(0, 84, 166, 0); }
        }
        
        .timeline-content {
            background: var(--bg-light);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            border-left: 4px solid var(--border-color);
        }
        
        .timeline-content.completed {
            border-left-color: var(--success-color);
            background: rgba(40, 167, 69, 0.05);
        }
        
        .timeline-content.current {
            border-left-color: var(--primary-color);
            background: rgba(0, 84, 166, 0.05);
        }
        
        .timeline-date {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-bottom: 0.5rem;
        }
        
        .timeline-title-item {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .timeline-description {
            color: var(--text-light);
            margin-bottom: 0;
        }
        
        /* Status Cards */
        .status-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .status-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .status-card-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }
        
        .status-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .status-card-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .status-card-label {
            color: var(--text-light);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Document Status */
        .document-status {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 2rem;
        }
        
        .document-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .document-item:last-child {
            border-bottom: none;
        }
        
        .document-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .document-note {
            color: var(--text-light);
            font-size: 0.85rem;
        }
        
        .document-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-uploaded {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .badge-verified {
            background: rgba(0, 84, 166, 0.1);
            color: var(--primary-color);
        }
        
        .badge-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #b45309;
        }
        
        .badge-missing {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        /* Action Buttons */
        .btn-modern {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
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
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .status-header {
                padding: 2rem 0;
            }
            
            .status-icon-large {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }
            
            .status-title {
                font-size: 2rem;
            }
            
            .progress-percentage {
                font-size: 3rem;
            }
            
            .timeline {
                padding-left: 1.5rem;
            }
            
            .timeline-marker {
                left: -1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Status Header -->
        <div class="status-header">
            <div class="container-xl">
                <div class="header-content">
                    <!-- Breadcrumb -->
                    <nav class="breadcrumb-modern mb-4" style="background: rgba(255, 255, 255, 0.1); border-radius: var(--radius-md); padding: 0.75rem 1rem;">
                        <a href="<?php echo SITE_URL; ?>/dashboard.php" style="color: rgba(255, 255, 255, 0.8); text-decoration: none;">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                        <span class="mx-2">/</span>
                        <span>Application Status</span>
                    </nav>
                    
                    <div class="status-icon-large">
                        <i class="<?php echo $current_status['icon']; ?>"></i>
                    </div>
                    
                    <h1 class="status-title"><?php echo $current_status['label']; ?></h1>
                    <p class="status-description"><?php echo $current_status['description']; ?></p>
                    
                    <div class="text-center">
                        <span class="badge bg-<?php echo $current_status['color']; ?>" style="padding: 0.5rem 1.5rem; font-size: 1rem;">
                            Application #<?php echo htmlspecialchars($student_application['application_number']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="page-body">
            <div class="container-xl">
                <!-- Progress Section -->
                <div class="progress-section">
                    <div class="progress-header">
                        <div class="progress-percentage"><?php echo $progress_percentage; ?>%</div>
                        <div class="progress-label">Application Progress</div>
                    </div>
                    
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo $progress_percentage; ?>%"></div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="text-center">
                                <strong>Submitted:</strong> 
                                <?php echo $student_application['submitted_at'] ? formatDateTime($student_application['submitted_at']) : 'Not yet submitted'; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-center">
                                <strong>Program:</strong> 
                                <?php echo htmlspecialchars($user_program['program_name']); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Status Timeline -->
                <div class="timeline-container">
                    <h3 class="timeline-title">
                        <i class="fas fa-timeline text-primary"></i>
                        Application Timeline
                    </h3>
                    
                    <div class="timeline">
                        <?php 
                        $timeline_steps = [
                            ['status' => STATUS_DRAFT, 'title' => 'Application Created', 'description' => 'Your application has been started'],
                            ['status' => STATUS_SUBMITTED, 'title' => 'Application Submitted', 'description' => 'Application submitted for initial review'],
                            ['status' => STATUS_FROZEN, 'title' => 'Final Submission', 'description' => 'Application locked for detailed review'],
                            ['status' => STATUS_UNDER_REVIEW, 'title' => 'Under Review', 'description' => 'Admissions committee is reviewing your application'],
                            ['status' => STATUS_APPROVED, 'title' => 'Decision Made', 'description' => 'Final admission decision has been made']
                        ];
                        
                        $current_step = array_search($student_application['status'], array_column($timeline_steps, 'status'));
                        if ($current_step === false) $current_step = 0;
                        
                        foreach ($timeline_steps as $index => $step):
                            $marker_class = 'pending';
                            $content_class = '';
                            
                            if ($index < $current_step) {
                                $marker_class = 'completed';
                                $content_class = 'completed';
                            } elseif ($index == $current_step) {
                                $marker_class = 'current';
                                $content_class = 'current';
                            }
                            
                            // Find actual date from history
                            $step_date = '';
                            foreach ($status_history as $history) {
                                if ($history['to_status'] === $step['status']) {
                                    $step_date = formatDateTime($history['date_created']);
                                    break;
                                }
                            }
                        ?>
                        <div class="timeline-item">
                            <div class="timeline-marker <?php echo $marker_class; ?>">
                                <?php if ($marker_class === 'completed'): ?>
                                <i class="fas fa-check"></i>
                                <?php elseif ($marker_class === 'current'): ?>
                                <i class="fas fa-circle"></i>
                                <?php else: ?>
                                <i class="fas fa-circle"></i>
                                <?php endif; ?>
                            </div>
                            
                            <div class="timeline-content <?php echo $content_class; ?>">
                                <?php if ($step_date): ?>
                                <div class="timeline-date"><?php echo $step_date; ?></div>
                                <?php endif; ?>
                                <div class="timeline-title-item"><?php echo $step['title']; ?></div>
                                <div class="timeline-description"><?php echo $step['description']; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Status Cards Row -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="status-card">
                            <div class="status-card-icon" style="background: var(--info-color);">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="status-card-title">Application Date</div>
                            <div class="status-card-value"><?php echo formatDate($student_application['date_created']); ?></div>
                            <div class="status-card-label">Created On</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="status-card">
                            <div class="status-card-icon" style="background: var(--primary-color);">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div class="status-card-title">Program</div>
                            <div class="status-card-value"><?php echo htmlspecialchars($user_program['program_code']); ?></div>
                            <div class="status-card-label">Selected Course</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="status-card">
                            <div class="status-card-icon" style="background: var(--success-color);">
                                <i class="fas fa-file-check"></i>
                            </div>
                            <div class="status-card-title">Documents</div>
                            <div class="status-card-value">5/8</div>
                            <div class="status-card-label">Uploaded</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="status-card">
                            <div class="status-card-icon" style="background: var(--warning-color);">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="status-card-title">Review Time</div>
                            <div class="status-card-value">5-7</div>
                            <div class="status-card-label">Working Days</div>
                        </div>
                    </div>
                </div>
                
                <!-- Document Status and Actions -->
                <div class="row g-4">
                    <!-- Document Status -->
                    <div class="col-lg-8">
                        <div class="document-status">
                            <h4 class="mb-3">
                                <i class="fas fa-file-alt text-primary me-2"></i>
                                Document Status
                            </h4>
                            
                            <?php 
                            // Sample document status (in real implementation, get from database)
                            $documents = [
                                ['name' => '10th Marks Memo', 'status' => 'verified', 'note' => 'Verified on March 10, 2025'],
                                ['name' => 'Intermediate Marks Memo', 'status' => 'verified', 'note' => 'Verified on March 10, 2025'],
                                ['name' => 'Intermediate TC', 'status' => 'uploaded', 'note' => 'Uploaded on March 15, 2025'],
                                ['name' => 'Study Certificate', 'status' => 'pending', 'note' => 'Under verification'],
                                ['name' => 'Income Certificate', 'status' => 'uploaded', 'note' => 'Uploaded on March 16, 2025'],
                                ['name' => 'Aadhar Card', 'status' => 'missing', 'note' => 'Please upload'],
                                ['name' => 'Passport Photo', 'status' => 'missing', 'note' => 'Please upload'],
                                ['name' => 'Signature', 'status' => 'missing', 'note' => 'Please upload']
                            ];
                            ?>
                            
                            <?php foreach ($documents as $doc): ?>
                            <div class="document-item">
                                <div class="document-icon" style="background: <?php
                                    switch($doc['status']) {
                                        case 'verified': echo 'var(--primary-color)';break;
                                        case 'uploaded': echo 'var(--success-color)';break;
                                        case 'pending': echo 'var(--warning-color)';break;
                                        case 'missing': echo 'var(--danger-color)';break;
                                    }
                                ?>; color: white;">
                                    <i class="fas fa-<?php
                                        switch($doc['status']) {
                                            case 'verified': echo 'check-circle';break;
                                            case 'uploaded': echo 'file-check';break;
                                            case 'pending': echo 'clock';break;
                                            case 'missing': echo 'exclamation-triangle';break;
                                        }
                                    ?>"></i>
                                </div>
                                
                                <div class="document-info">
                                    <div class="document-name"><?php echo $doc['name']; ?></div>
                                    <div class="document-note"><?php echo $doc['note']; ?></div>
                                </div>
                                
                                <div class="document-badge badge-<?php echo $doc['status']; ?>">
                                    <?php echo ucfirst($doc['status']); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Action Panel -->
                    <div class="col-lg-4">
                        <div class="document-status">
                            <h4 class="mb-3">
                                <i class="fas fa-tasks text-primary me-2"></i>
                                Next Steps
                            </h4>
                            
                            <?php if ($student_application['status'] === STATUS_DRAFT): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Complete your application form and upload required documents.
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="<?php echo SITE_URL; ?>/student/application.php" class="btn-modern btn-primary-modern">
                                    <i class="fas fa-edit"></i>
                                    Complete Application
                                </a>
                                <a href="<?php echo SITE_URL; ?>/student/documents.php" class="btn-modern btn-outline-modern">
                                    <i class="fas fa-upload"></i>
                                    Upload Documents
                                </a>
                            </div>
                            
                            <?php elseif ($student_application['status'] === STATUS_SUBMITTED): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-clock me-2"></i>
                                Upload remaining documents and finalize your submission.
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="<?php echo SITE_URL; ?>/student/documents.php" class="btn-modern btn-primary-modern">
                                    <i class="fas fa-upload"></i>
                                    Upload Documents
                                </a>
                                <a href="<?php echo SITE_URL; ?>/student/submit.php" class="btn-modern btn-success-modern">
                                    <i class="fas fa-lock"></i>
                                    Final Submit
                                </a>
                            </div>
                            
                            <?php elseif ($student_application['status'] === STATUS_UNDER_REVIEW): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-eye me-2"></i>
                                Your application is being reviewed. You will be notified of the decision.
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button class="btn-modern btn-outline-modern" disabled>
                                    <i class="fas fa-hourglass-half"></i>
                                    Under Review
                                </button>
                            </div>
                            
                            <?php elseif ($student_application['status'] === STATUS_APPROVED): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                Congratulations! Your application has been approved.
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="<?php echo SITE_URL; ?>/student/admission-letter.php" class="btn-modern btn-success-modern">
                                    <i class="fas fa-download"></i>
                                    Download Admission Letter
                                </a>
                                <a href="<?php echo SITE_URL; ?>/student/fees.php" class="btn-modern btn-primary-modern">
                                    <i class="fas fa-credit-card"></i>
                                    Pay Fees
                                </a>
                            </div>
                            
                            <?php elseif ($student_application['status'] === STATUS_REJECTED): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-times-circle me-2"></i>
                                Your application has been rejected. Please contact admissions for details.
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="<?php echo SITE_URL; ?>/contact.php" class="btn-modern btn-outline-modern">
                                    <i class="fas fa-phone"></i>
                                    Contact Support
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Additional Actions -->
                            <hr class="my-3">
                            
                            <div class="d-grid gap-2">
                                <a href="<?php echo SITE_URL; ?>/student/application.php" class="btn-modern btn-outline-modern">
                                    <i class="fas fa-eye"></i>
                                    View Application
                                </a>
                                <a href="<?php echo SITE_URL; ?>/dashboard.php" class="btn-modern btn-outline-modern">
                                    <i class="fas fa-arrow-left"></i>
                                    Back to Dashboard
                                </a>
                            </div>
                            
                            <!-- Help Section -->
                            <div class="mt-4 p-3 bg-light rounded">
                                <h6><i class="fas fa-question-circle text-info me-2"></i>Need Help?</h6>
                                <p class="mb-2 small text-muted">
                                    If you have any questions about your application status or the admission process, please don't hesitate to contact us.
                                </p>
                                <div class="d-grid">
                                    <a href="<?php echo SITE_URL; ?>/help.php" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-book me-1"></i>Help & FAQ
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Animate progress bar on load
        document.addEventListener('DOMContentLoaded', function() {
            const progressBar = document.querySelector('.progress-bar-fill');
            if (progressBar) {
                const targetWidth = progressBar.style.width;
                progressBar.style.width = '0%';
                setTimeout(() => {
                    progressBar.style.width = targetWidth;
                }, 500);
            }
            
            // Animate progress percentage
            const progressPercent = document.querySelector('.progress-percentage');
            if (progressPercent) {
                const target = parseInt(progressPercent.textContent);
                let current = 0;
                const increment = target / 30;
                
                const updateProgress = () => {
                    if (current < target) {
                        current += increment;
                        progressPercent.textContent = Math.round(current) + '%';
                        requestAnimationFrame(updateProgress);
                    } else {
                        progressPercent.textContent = target + '%';
                    }
                };
                
                setTimeout(updateProgress, 500);
            }
            
            // Auto-refresh status every 30 seconds (in real implementation)
            setInterval(() => {
                // This would check for status updates via AJAX
                console.log('Checking for status updates...');
            }, 30000);
        });
        
        // Smooth scroll to timeline when status changes
        function scrollToTimeline() {
            document.querySelector('.timeline-container').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
        
        // Show notification for status changes
        function showStatusNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
            notification.innerHTML = `
                <i class="fas fa-bell me-2"></i>
                ${message}
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }
        
        // Simulate real-time updates (placeholder)
        if (Math.random() < 0.1) { // 10% chance to show notification
            setTimeout(() => {
                showStatusNotification('Your application status has been updated!', 'info');
            }, 2000);
        }
    </script>
</body>
</html>