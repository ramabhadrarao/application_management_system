<?php
/**
 * Complete Application Timeline View
 * 
 * File: student/timeline.php
 * Purpose: Detailed timeline view of application progress
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

// Get document upload history
$document_history_query = "
    SELECT 
        ad.date_created as event_date,
        'document_upload' as event_type,
        ct.name as document_name,
        ad.is_verified,
        ad.verified_at,
        verifier.email as verifier_email,
        ad.verification_remarks
    FROM application_documents ad
    JOIN certificate_types ct ON ad.certificate_type_id = ct.id
    LEFT JOIN users verifier ON ad.verified_by = verifier.id
    WHERE ad.application_id = :application_id
    
    UNION ALL
    
    SELECT 
        aed.date_created as event_date,
        'education_added' as event_type,
        CONCAT(el.level_name, ' - ', aed.institution_name) as document_name,
        0 as is_verified,
        NULL as verified_at,
        NULL as verifier_email,
        NULL as verification_remarks
    FROM application_education_details aed
    JOIN education_levels el ON aed.education_level_id = el.id
    WHERE aed.application_id = :application_id
    
    ORDER BY event_date DESC
";

$stmt = $db->prepare($document_history_query);
$stmt->bindParam(':application_id', $student_application['id']);
$stmt->execute();
$document_history = $stmt->fetchAll();

// Combine all timeline events
$timeline_events = [];

// Add application creation event
$timeline_events[] = [
    'date' => $student_application['date_created'],
    'type' => 'application_created',
    'title' => 'Application Created',
    'description' => 'Application #' . $student_application['application_number'] . ' was created',
    'icon' => 'fas fa-plus-circle',
    'color' => 'success',
    'user' => 'You',
    'metadata' => [
        'application_number' => $student_application['application_number']
    ]
];

// Add status history events
foreach ($status_history as $history) {
    $timeline_events[] = [
        'date' => $history['date_created'],
        'type' => 'status_change',
        'title' => 'Status Changed to ' . ucwords(str_replace('_', ' ', $history['to_status'])),
        'description' => $history['remarks'] ?: 'Application status updated',
        'icon' => getStatusIcon($history['to_status']),
        'color' => getStatusColor($history['to_status']),
        'user' => $history['changed_by_email'] ?: 'System',
        'metadata' => [
            'from_status' => $history['from_status'],
            'to_status' => $history['to_status']
        ]
    ];
}

// Add document history events
foreach ($document_history as $doc) {
    if ($doc['event_type'] === 'document_upload') {
        $timeline_events[] = [
            'date' => $doc['event_date'],
            'type' => 'document_upload',
            'title' => 'Document Uploaded',
            'description' => $doc['document_name'],
            'icon' => 'fas fa-file-upload',
            'color' => 'info',
            'user' => 'You',
            'metadata' => [
                'document_name' => $doc['document_name'],
                'is_verified' => $doc['is_verified']
            ]
        ];
        
        if ($doc['is_verified'] && $doc['verified_at']) {
            $timeline_events[] = [
                'date' => $doc['verified_at'],
                'type' => 'document_verified',
                'title' => 'Document Verified',
                'description' => $doc['document_name'] . ' has been verified',
                'icon' => 'fas fa-check-circle',
                'color' => 'success',
                'user' => $doc['verifier_email'] ?: 'Admin',
                'metadata' => [
                    'document_name' => $doc['document_name'],
                    'remarks' => $doc['verification_remarks']
                ]
            ];
        }
    } elseif ($doc['event_type'] === 'education_added') {
        $timeline_events[] = [
            'date' => $doc['event_date'],
            'type' => 'education_added',
            'title' => 'Education Details Added',
            'description' => $doc['document_name'],
            'icon' => 'fas fa-graduation-cap',
            'color' => 'primary',
            'user' => 'You',
            'metadata' => []
        ];
    }
}

// Sort timeline events by date (newest first)
usort($timeline_events, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Status configuration
function getStatusIcon($status) {
    switch ($status) {
        case STATUS_DRAFT: return 'fas fa-edit';
        case STATUS_SUBMITTED: return 'fas fa-paper-plane';
        case STATUS_FROZEN: return 'fas fa-lock';
        case STATUS_UNDER_REVIEW: return 'fas fa-eye';
        case STATUS_APPROVED: return 'fas fa-check-circle';
        case STATUS_REJECTED: return 'fas fa-times-circle';
        default: return 'fas fa-circle';
    }
}

function getStatusColor($status) {
    switch ($status) {
        case STATUS_DRAFT: return 'secondary';
        case STATUS_SUBMITTED: return 'info';
        case STATUS_FROZEN: return 'primary';
        case STATUS_UNDER_REVIEW: return 'warning';
        case STATUS_APPROVED: return 'success';
        case STATUS_REJECTED: return 'danger';
        default: return 'secondary';
    }
}

$page_title = 'Application Timeline';
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
            --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
            --radius-lg: 0.75rem;
        }
        
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
        }
        
        .timeline-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 3rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .timeline-header::before {
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
        
        .timeline-icon {
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
        
        .timeline-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        
        .timeline-subtitle {
            opacity: 0.9;
            text-align: center;
        }
        
        .timeline-container {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin: -3rem auto 2rem;
            position: relative;
            z-index: 3;
            max-width: 1000px;
        }
        
        .timeline-nav {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-light);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        .nav-item {
            flex: 1;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }
        
        .nav-item.active {
            background: var(--white);
            border-bottom-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .nav-item:hover {
            background: var(--white);
        }
        
        .timeline {
            position: relative;
            padding: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 2rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-color);
            z-index: 1;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
            padding-left: 3rem;
        }
        
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        
        .timeline-marker {
            position: absolute;
            left: -2rem;
            top: 0.5rem;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: white;
            z-index: 2;
            border: 3px solid var(--white);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .marker-success { background: var(--success-color); }
        .marker-info { background: var(--info-color); }
        .marker-primary { background: var(--primary-color); }
        .marker-warning { background: var(--warning-color); }
        .marker-danger { background: var(--danger-color); }
        .marker-secondary { background: var(--text-light); }
        
        .timeline-content {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border-left: 4px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .timeline-content:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .content-success { border-left-color: var(--success-color); }
        .content-info { border-left-color: var(--info-color); }
        .content-primary { border-left-color: var(--primary-color); }
        .content-warning { border-left-color: var(--warning-color); }
        .content-danger { border-left-color: var(--danger-color); }
        .content-secondary { border-left-color: var(--text-light); }
        
        .timeline-header-item {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .timeline-meta {
            text-align: right;
            font-size: 0.85rem;
            color: var(--text-light);
        }
        
        .timeline-date {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .timeline-user {
            color: var(--primary-color);
        }
        
        .timeline-title-item {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            flex: 1;
        }
        
        .timeline-description {
            color: var(--text-light);
            margin-bottom: 1rem;
        }
        
        .timeline-metadata {
            background: var(--bg-light);
            border-radius: var(--radius-lg);
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .filter-controls {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-light);
        }
        
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .filter-chip {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            border: 2px solid var(--border-color);
            background: var(--white);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }
        
        .filter-chip.active {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }
        
        .filter-chip:hover {
            border-color: var(--primary-color);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            padding: 1.5rem;
            background: var(--bg-light);
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .empty-timeline {
            text-align: center;
            padding: 3rem;
            color: var(--text-light);
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
        
        @media (max-width: 768px) {
            .timeline-header {
                padding: 2rem 0;
            }
            
            .timeline::before {
                left: 1.5rem;
            }
            
            .timeline-item {
                padding-left: 2.5rem;
            }
            
            .timeline-marker {
                left: -1.5rem;
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }
            
            .timeline-header-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .timeline-meta {
                text-align: left;
                margin-top: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Header -->
        <div class="timeline-header">
            <div class="container">
                <div class="header-content">
                    <!-- Breadcrumb -->
                    <nav class="mb-3" style="background: rgba(255, 255, 255, 0.1); border-radius: var(--radius-lg); padding: 0.75rem 1rem; display: inline-block;">
                        <a href="<?php echo SITE_URL; ?>/dashboard.php" style="color: rgba(255, 255, 255, 0.8); text-decoration: none;">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                        <span class="mx-2">/</span>
                        <a href="<?php echo SITE_URL; ?>/student/status.php" style="color: rgba(255, 255, 255, 0.8); text-decoration: none;">
                            Status
                        </a>
                        <span class="mx-2">/</span>
                        <span>Timeline</span>
                    </nav>
                    
                    <div class="timeline-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h1 class="timeline-title">Application Timeline</h1>
                    <p class="timeline-subtitle">Detailed history of your application progress</p>
                    
                    <div class="text-center mt-3">
                        <span class="badge bg-primary" style="padding: 0.5rem 1.5rem; font-size: 1rem;">
                            Application #<?php echo htmlspecialchars($student_application['application_number']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="container py-4">
            <div class="timeline-container">
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number text-primary"><?php echo count($timeline_events); ?></div>
                        <div class="stat-label">Total Events</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number text-success">
                            <?php echo count(array_filter($timeline_events, function($e) { return $e['type'] === 'document_verified'; })); ?>
                        </div>
                        <div class="stat-label">Documents Verified</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number text-info">
                            <?php echo count(array_filter($timeline_events, function($e) { return $e['type'] === 'status_change'; })); ?>
                        </div>
                        <div class="stat-label">Status Changes</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number text-warning">
                            <?php echo count(array_filter($timeline_events, function($e) { return $e['type'] === 'document_upload'; })); ?>
                        </div>
                        <div class="stat-label">Documents Uploaded</div>
                    </div>
                </div>
                
                <!-- Filter Controls -->
                <div class="filter-controls">
                    <h6 class="mb-3">Filter Events:</h6>
                    <div class="filter-group">
                        <div class="filter-chip active" data-filter="all">
                            <i class="fas fa-list me-1"></i>All Events
                        </div>
                        <div class="filter-chip" data-filter="status_change">
                            <i class="fas fa-exchange-alt me-1"></i>Status Changes
                        </div>
                        <div class="filter-chip" data-filter="document_upload">
                            <i class="fas fa-file-upload me-1"></i>Document Uploads
                        </div>
                        <div class="filter-chip" data-filter="document_verified">
                            <i class="fas fa-check-circle me-1"></i>Verifications
                        </div>
                        <div class="filter-chip" data-filter="education_added">
                            <i class="fas fa-graduation-cap me-1"></i>Education
                        </div>
                    </div>
                </div>
                
                <!-- Timeline -->
                <div class="timeline">
                    <?php if (empty($timeline_events)): ?>
                    <div class="empty-timeline">
                        <div class="empty-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h4>No Events Yet</h4>
                        <p>Your application timeline will appear here as you make progress.</p>
                    </div>
                    <?php else: ?>
                    
                    <?php foreach ($timeline_events as $event): ?>
                    <div class="timeline-item" data-type="<?php echo $event['type']; ?>">
                        <div class="timeline-marker marker-<?php echo $event['color']; ?>">
                            <i class="<?php echo $event['icon']; ?>"></i>
                        </div>
                        
                        <div class="timeline-content content-<?php echo $event['color']; ?>">
                            <div class="timeline-header-item">
                                <div class="timeline-title-item">
                                    <?php echo htmlspecialchars($event['title']); ?>
                                </div>
                                <div class="timeline-meta">
                                    <div class="timeline-date">
                                        <?php echo formatDateTime($event['date']); ?>
                                    </div>
                                    <div class="timeline-user">
                                        By: <?php echo htmlspecialchars($event['user']); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="timeline-description">
                                <?php echo htmlspecialchars($event['description']); ?>
                            </div>
                            
                            <?php if (!empty($event['metadata'])): ?>
                            <div class="timeline-metadata">
                                <?php if ($event['type'] === 'status_change'): ?>
                                    <?php if ($event['metadata']['from_status']): ?>
                                    <div class="mb-2">
                                        <strong>Status Change:</strong> 
                                        <span class="badge bg-secondary"><?php echo ucwords(str_replace('_', ' ', $event['metadata']['from_status'])); ?></span>
                                        <i class="fas fa-arrow-right mx-2"></i>
                                        <span class="badge bg-<?php echo getStatusColor($event['metadata']['to_status']); ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $event['metadata']['to_status'])); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                
                                <?php elseif ($event['type'] === 'document_verified' && !empty($event['metadata']['remarks'])): ?>
                                    <div>
                                        <strong>Verification Notes:</strong><br>
                                        <em><?php echo htmlspecialchars($event['metadata']['remarks']); ?></em>
                                    </div>
                                
                                <?php elseif ($event['type'] === 'document_upload'): ?>
                                    <div>
                                        <strong>Document:</strong> <?php echo htmlspecialchars($event['metadata']['document_name']); ?><br>
                                        <strong>Status:</strong> 
                                        <?php if ($event['metadata']['is_verified']): ?>
                                        <span class="badge bg-success">Verified</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning">Pending Verification</span>
                                        <?php endif; ?>
                                    </div>
                                
                                <?php elseif ($event['type'] === 'application_created'): ?>
                                    <div>
                                        <strong>Application Number:</strong> 
                                        <code><?php echo htmlspecialchars($event['metadata']['application_number']); ?></code><br>
                                        <strong>Program:</strong> <?php echo htmlspecialchars($user_program['program_name']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Navigation -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo SITE_URL; ?>/student/status.php" class="btn-modern btn-outline-modern">
                            <i class="fas fa-arrow-left"></i>Back to Status
                        </a>
                        <a href="<?php echo SITE_URL; ?>/dashboard.php" class="btn-modern btn-outline-modern">
                            <i class="fas fa-home"></i>Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    
    <script>
        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const filterChips = document.querySelectorAll('.filter-chip');
            const timelineItems = document.querySelectorAll('.timeline-item');
            
            filterChips.forEach(chip => {
                chip.addEventListener('click', function() {
                    // Remove active class from all chips
                    filterChips.forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked chip
                    this.classList.add('active');
                    
                    const filterType = this.getAttribute('data-filter');
                    
                    // Show/hide timeline items based on filter
                    timelineItems.forEach(item => {
                        const itemType = item.getAttribute('data-type');
                        
                        if (filterType === 'all' || itemType === filterType) {
                            item.style.display = 'block';
                            // Animate in
                            setTimeout(() => {
                                item.style.opacity = '1';
                                item.style.transform = 'translateX(0)';
                            }, 100);
                        } else {
                            item.style.opacity = '0';
                            item.style.transform = 'translateX(-20px)';
                            setTimeout(() => {
                                item.style.display = 'none';
                            }, 300);
                        }
                    });
                });
            });
            
            // Add smooth animations
            timelineItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                item.style.transition = 'all 0.3s ease';
                
                setTimeout(() => {
                    item.style.opacity = '1';
                    item.style.transform = 'translateX(0)';
                }, index * 100);
            });
            
            // Auto-refresh timeline every 30 seconds
            setInterval(() => {
                // This would check for new timeline events via AJAX
                console.log('Checking for timeline updates...');
            }, 30000);
        });
        
        // Export timeline functionality
        function exportTimeline() {
            const timelineData = <?php echo json_encode($timeline_events); ?>;
            const dataStr = JSON.stringify(timelineData, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            
            const link = document.createElement('a');
            link.href = URL.createObjectURL(dataBlob);
            link.download = 'application-timeline-<?php echo $student_application['application_number']; ?>.json';
            link.click();
        }
        
        // Print timeline
        function printTimeline() {
            window.print();
        }
    </script>
</body>
</html>