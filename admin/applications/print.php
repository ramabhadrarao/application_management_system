<?php
/**
 * Print Application Page
 * 
 * File: admin/applications/print.php
 * Purpose: Generate printable version of application
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

// Get education details
$edu_query = "
    SELECT ed.*, el.level_name
    FROM application_education_details ed
    JOIN education_levels el ON ed.education_level_id = el.id
    WHERE ed.application_id = :app_id
    ORDER BY el.display_order ASC
";
$stmt = $db->prepare($edu_query);
$stmt->bindParam(':app_id', $app_id);
$stmt->execute();
$education_details = $stmt->fetchAll();

// Get documents
$docs_query = "
    SELECT ad.*, ct.name as certificate_name
    FROM application_documents ad
    JOIN certificate_types ct ON ad.certificate_type_id = ct.id
    WHERE ad.application_id = :app_id
    ORDER BY ct.display_order ASC
";
$stmt = $db->prepare($docs_query);
$stmt->bindParam(':app_id', $app_id);
$stmt->execute();
$documents = $stmt->fetchAll();

// Get study history
$study_query = "
    SELECT * FROM application_study_history 
    WHERE application_id = :app_id 
    ORDER BY display_order ASC
";
$stmt = $db->prepare($study_query);
$stmt->bindParam(':app_id', $app_id);
$stmt->execute();
$study_history = $stmt->fetchAll();

$page_title = 'Print Application - ' . $app_details['application_number'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <style>
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            body {
                margin: 0;
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .page-break {
                page-break-after: always;
            }
        }
        
        body {
            font-family: 'Arial', sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            background: white;
            margin: 0;
            padding: 20px;
        }
        
        .print-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .print-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #0054a6;
        }
        
        .college-name {
            font-size: 24px;
            font-weight: bold;
            color: #0054a6;
            margin-bottom: 5px;
        }
        
        .application-title {
            font-size: 20px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .application-number {
            font-size: 16px;
            color: #666;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #0054a6;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            padding: 5px 0;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
            margin-bottom: 2px;
        }
        
        .info-value {
            color: #333;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        table th,
        table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        
        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-submitted { background: #17a2b8; color: white; }
        .status-approved { background: #28a745; color: white; }
        .status-rejected { background: #dc3545; color: white; }
        .status-under_review { background: #ffc107; color: #333; }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }
        
        .signature-box {
            text-align: center;
            width: 200px;
        }
        
        .signature-line {
            border-bottom: 1px solid #333;
            margin-bottom: 5px;
            height: 40px;
        }
        
        .print-actions {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background: #0054a6;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <!-- Print Actions -->
    <div class="print-actions no-print">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i> Print
        </button>
        <a href="view.php?id=<?php echo $app_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    
    <!-- Print Content -->
    <div class="print-container">
        <!-- Header -->
        <div class="print-header">
            <div class="college-name"><?php echo SITE_NAME; ?></div>
            <div class="application-title">APPLICATION FORM</div>
            <div class="application-number">Application No: <?php echo htmlspecialchars($app_details['application_number']); ?></div>
            <div>Academic Year: <?php echo htmlspecialchars($app_details['academic_year']); ?></div>
        </div>
        
        <!-- Program & Status -->
        <div class="section">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Program Applied For:</div>
                    <div class="info-value"><?php echo htmlspecialchars($app_details['program_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Application Status:</div>
                    <div class="info-value">
                        <span class="status-badge status-<?php echo $app_details['status']; ?>">
                            <?php echo ucwords(str_replace('_', ' ', $app_details['status'])); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Personal Information -->
        <div class="section">
            <h3 class="section-title">Personal Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Student Name:</div>
                    <div class="info-value"><?php echo htmlspecialchars($app_details['student_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Father's Name:</div>
                    <div class="info-value"><?php echo htmlspecialchars($app_details['father_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Mother's Name:</div>
                    <div class="info-value"><?php echo htmlspecialchars($app_details['mother_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Date of Birth:</div>
                    <div class="info-value"><?php echo formatDate($app_details['date_of_birth']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Gender:</div>
                    <div class="info-value"><?php echo htmlspecialchars($app_details['gender']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Aadhar Number:</div>
                    <div class="info-value"><?php echo htmlspecialchars($app_details['aadhar_number'] ?: 'Not Provided'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Contact Information -->
        <div class="section">
            <h3 class="section-title">Contact Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Mobile Number:</div>
                    <div class="info-value"><?php echo htmlspecialchars($app_details['mobile_number']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email:</div>
                    <div class="info-value"><?php echo htmlspecialchars($app_details['email']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Parent Mobile:</div>
                    <div class="info-value"><?php echo htmlspecialchars($app_details['parent_mobile'] ?: 'Not Provided'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Guardian Mobile:</div>
                    <div class="info-value"><?php echo htmlspecialchars($app_details['guardian_mobile'] ?: 'Not Provided'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Address Information -->
        <div class="section">
            <h3 class="section-title">Address Information</h3>
            
            <h4 style="margin-top: 15px;">Present Address:</h4>
            <div class="info-grid">
                <div class="info-item full-width">
                    <div class="info-value">
                        <?php 
                        $present_address = [];
                        if ($app_details['present_door_no']) $present_address[] = $app_details['present_door_no'];
                        if ($app_details['present_street']) $present_address[] = $app_details['present_street'];
                        if ($app_details['present_village']) $present_address[] = $app_details['present_village'];
                        if ($app_details['present_mandal']) $present_address[] = $app_details['present_mandal'];
                        if ($app_details['present_district']) $present_address[] = $app_details['present_district'];
                        if ($app_details['present_pincode']) $present_address[] = 'PIN: ' . $app_details['present_pincode'];
                        echo htmlspecialchars(implode(', ', $present_address) ?: 'Not Provided');
                        ?>
                    </div>
                </div>
            </div>
            
            <h4 style="margin-top: 15px;">Permanent Address:</h4>
            <div class="info-grid">
                <div class="info-item full-width">
                    <div class="info-value">
                        <?php 
                        $permanent_address = [];
                        if ($app_details['permanent_door_no']) $permanent_address[] = $app_details['permanent_door_no'];
                        if ($app_details['permanent_street']) $permanent_address[] = $app_details['permanent_street'];
                        if ($app_details['permanent_village']) $permanent_address[] = $app_details['permanent_village'];
                        if ($app_details['permanent_mandal']) $permanent_address[] = $app_details['permanent_mandal'];
                        if ($app_details['permanent_district']) $permanent_address[] = $app_details['permanent_district'];
                        if ($app_details['permanent_pincode']) $permanent_address[] = 'PIN: ' . $app_details['permanent_pincode'];
                        echo htmlspecialchars(implode(', ', $permanent_address) ?: 'Not Provided');
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Additional Information -->
        <div class="section">
            <h3 class="section-title">Additional Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Religion:</div>
                    <div class="info-value"><?php echo htmlspecialchars($app_details['religion'] ?: 'Not Provided'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Caste:</div>
                    <div class="info-value"><?php echo htmlspecialchars($app_details['caste'] ?: 'Not Provided'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Reservation Category:</div>
                    <div class="info-value"><?php echo htmlspecialchars($app_details['reservation_category']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Physically Handicapped:</div>
                    <div class="info-value"><?php echo $app_details['is_physically_handicapped'] ? 'Yes' : 'No'; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Education Details -->
        <?php if (!empty($education_details)): ?>
        <div class="section">
            <h3 class="section-title">Education Details</h3>
            <table>
                <thead>
                    <tr>
                        <th>Level</th>
                        <th>Institution</th>
                        <th>Board/University</th>
                        <th>Year</th>
                        <th>Marks</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($education_details as $edu): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($edu['level_name']); ?></td>
                        <td><?php echo htmlspecialchars($edu['institution_name']); ?></td>
                        <td><?php echo htmlspecialchars($edu['board_university_name'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($edu['pass_year']); ?></td>
                        <td><?php echo $edu['marks_obtained'] ? $edu['marks_obtained'] . '/' . $edu['maximum_marks'] : '-'; ?></td>
                        <td><?php echo $edu['percentage'] ? number_format($edu['percentage'], 2) . '%' : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Documents Uploaded -->
        <?php if (!empty($documents)): ?>
        <div class="section">
            <h3 class="section-title">Documents Uploaded</h3>
            <table>
                <thead>
                    <tr>
                        <th>Document Type</th>
                        <th>File Name</th>
                        <th>Upload Date</th>
                        <th>Verification Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($doc['certificate_name']); ?></td>
                        <td><?php echo htmlspecialchars($doc['document_name']); ?></td>
                        <td><?php echo formatDate($doc['date_created']); ?></td>
                        <td>
                            <?php if ($doc['is_verified']): ?>
                                <span style="color: green;">✓ Verified</span>
                            <?php else: ?>
                                <span style="color: orange;">Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Declaration -->
        <div class="section">
            <h3 class="section-title">Declaration</h3>
            <p style="text-align: justify; line-height: 1.8;">
                I hereby declare that all the information provided in this application form is true and correct to the best of my knowledge. 
                I understand that any false information may lead to the cancellation of my application/admission.
            </p>
        </div>
        
        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div>Date</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div>Signature of Student</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div>Signature of Parent/Guardian</div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div style="text-align: center; color: #666; font-size: 12px;">
                <p>Application Generated on: <?php echo date('d-m-Y H:i:s'); ?></p>
                <p><?php echo SITE_NAME; ?> | <?php echo ADMIN_EMAIL; ?> | <?php echo SUPPORT_PHONE; ?></p>
            </div>
        </div>
    </div>
    
    <script>
        // Auto print on load (optional)
        // window.onload = function() {
        //     window.print();
        // };
    </script>
</body>
</html>