<?php
/**
 * Application Settings Page
 * 
 * File: admin/applications/settings.php
 * Purpose: Manage application-related settings
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../../config/config.php';

// Require admin login
requireLogin();

$database = new Database();
$db = $database->getConnection();

$current_user_role = getCurrentUserRole();
$current_user_id = getCurrentUserId();

// Only main admin can access settings
if ($current_user_role !== ROLE_ADMIN) {
    header('Location: ' . SITE_URL . '/admin/dashboard.php');
    exit;
}

$message = '';
$message_type = '';

// Get current settings
$settings_query = "SELECT * FROM system_settings WHERE id = 1";
$stmt = $db->prepare($settings_query);
$stmt->execute();
$settings = $stmt->fetch();

// If no settings exist, create default
if (!$settings) {
    $insert_query = "INSERT INTO system_settings (id, academic_year_current, application_enabled, auto_approve_applications, email_notifications, sms_notifications) 
                     VALUES (1, :academic_year, 1, 0, 1, 0)";
    $stmt = $db->prepare($insert_query);
    $stmt->bindValue(':academic_year', CURRENT_ACADEMIC_YEAR);
    $stmt->execute();
    
    // Fetch again
    $stmt = $db->prepare($settings_query);
    $stmt->execute();
    $settings = $stmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        
        // Update settings
        $update_query = "UPDATE system_settings SET 
                         application_enabled = :application_enabled,
                         maintenance_mode = :maintenance_mode,
                         max_file_size_mb = :max_file_size_mb,
                         allowed_file_types = :allowed_file_types,
                         application_instructions = :application_instructions,
                         contact_email = :contact_email,
                         contact_phone = :contact_phone,
                         academic_year_current = :academic_year_current,
                         auto_approve_applications = :auto_approve_applications,
                         email_notifications = :email_notifications,
                         sms_notifications = :sms_notifications,
                         application_start_date = :application_start_date,
                         application_end_date = :application_end_date
                         WHERE id = 1";
        
        $stmt = $db->prepare($update_query);
        $stmt->bindValue(':application_enabled', isset($_POST['application_enabled']) ? 1 : 0);
        $stmt->bindValue(':maintenance_mode', isset($_POST['maintenance_mode']) ? 1 : 0);
        $stmt->bindValue(':max_file_size_mb', (int)$_POST['max_file_size_mb']);
        $stmt->bindValue(':allowed_file_types', sanitizeInput($_POST['allowed_file_types']));
        $stmt->bindValue(':application_instructions', $_POST['application_instructions']);
        $stmt->bindValue(':contact_email', sanitizeInput($_POST['contact_email']));
        $stmt->bindValue(':contact_phone', sanitizeInput($_POST['contact_phone']));
        $stmt->bindValue(':academic_year_current', sanitizeInput($_POST['academic_year_current']));
        $stmt->bindValue(':auto_approve_applications', isset($_POST['auto_approve_applications']) ? 1 : 0);
        $stmt->bindValue(':email_notifications', isset($_POST['email_notifications']) ? 1 : 0);
        $stmt->bindValue(':sms_notifications', isset($_POST['sms_notifications']) ? 1 : 0);
        $stmt->bindValue(':application_start_date', $_POST['application_start_date']);
        $stmt->bindValue(':application_end_date', $_POST['application_end_date']);
        
        if ($stmt->execute()) {
            $message = 'Settings updated successfully!';
            $message_type = 'success';
            
            // Refresh settings
            $stmt = $db->prepare($settings_query);
            $stmt->execute();
            $settings = $stmt->fetch();
        } else {
            $message = 'Failed to update settings.';
            $message_type = 'danger';
        }
    }
}

// Get statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM applications WHERE academic_year = :academic_year) as total_applications,
        (SELECT COUNT(*) FROM applications WHERE status = 'submitted' AND academic_year = :academic_year) as pending_applications,
        (SELECT COUNT(*) FROM applications WHERE status = 'approved' AND academic_year = :academic_year) as approved_applications,
        (SELECT COUNT(*) FROM programs WHERE is_active = 1) as active_programs
";
$stmt = $db->prepare($stats_query);
$stmt->bindValue(':academic_year', $settings['academic_year_current']);
$stmt->execute();
$stats = $stmt->fetch();

$page_title = 'Application Settings';
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
        
        .settings-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header-custom {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .card-title-custom {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-switch-custom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .form-switch-custom:last-child {
            border-bottom: none;
        }
        
        .switch-label {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .switch-description {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .date-range-input {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .info-alert {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            color: #0d47a1;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
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
                        <div class="page-pretitle">Administration</div>
                        <h2 class="page-title">
                            <i class="fas fa-cog me-2"></i>
                            Application Settings
                        </h2>
                    </div>
                    <div class="col-auto ms-auto">
                        <div class="btn-list">
                            <a href="../dashboard.php" class="btn btn-light">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="page-body">
            <div class="container-xl">
                <!-- Messages -->
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo number_format($stats['total_applications']); ?></div>
                            <div class="stats-label">Total Applications</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="border-left-color: var(--warning-color);">
                            <div class="stats-number" style="color: var(--warning-color);"><?php echo number_format($stats['pending_applications']); ?></div>
                            <div class="stats-label">Pending Review</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="border-left-color: var(--success-color);">
                            <div class="stats-number" style="color: var(--success-color);"><?php echo number_format($stats['approved_applications']); ?></div>
                            <div class="stats-label">Approved</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="border-left-color: var(--info-color);">
                            <div class="stats-number" style="color: var(--info-color);"><?php echo number_format($stats['active_programs']); ?></div>
                            <div class="stats-label">Active Programs</div>
                        </div>
                    </div>
                </div>
                
                <!-- Settings Form -->
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <!-- General Settings -->
                    <div class="settings-card">
                        <div class="card-header-custom">
                            <h3 class="card-title-custom">
                                <i class="fas fa-sliders-h text-primary"></i>
                                General Settings
                            </h3>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label">Current Academic Year</label>
                                    <input type="text" class="form-control" name="academic_year_current" 
                                           value="<?php echo htmlspecialchars($settings['academic_year_current']); ?>" 
                                           placeholder="2025-26" required>
                                    <small class="text-muted">Format: YYYY-YY (e.g., 2025-26)</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Application Period</label>
                                    <div class="date-range-input">
                                        <input type="date" class="form-control" name="application_start_date" 
                                               value="<?php echo $settings['application_start_date']; ?>" required>
                                        <span>to</span>
                                        <input type="date" class="form-control" name="application_end_date" 
                                               value="<?php echo $settings['application_end_date']; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Contact Email</label>
                                    <input type="email" class="form-control" name="contact_email" 
                                           value="<?php echo htmlspecialchars($settings['contact_email']); ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Contact Phone</label>
                                    <input type="text" class="form-control" name="contact_phone" 
                                           value="<?php echo htmlspecialchars($settings['contact_phone']); ?>">
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Application Instructions</label>
                                    <textarea class="form-control" name="application_instructions" rows="4"><?php echo htmlspecialchars($settings['application_instructions']); ?></textarea>
                                    <small class="text-muted">These instructions will be displayed to students during application</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- System Settings -->
                    <div class="settings-card">
                        <div class="card-header-custom">
                            <h3 class="card-title-custom">
                                <i class="fas fa-server text-primary"></i>
                                System Settings
                            </h3>
                        </div>
                        <div class="card-body p-4">
                            <div class="form-switch-custom">
                                <div>
                                    <div class="switch-label">Application System</div>
                                    <div class="switch-description">Enable or disable the application system</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="application_enabled" 
                                           <?php echo $settings['application_enabled'] ? 'checked' : ''; ?>>
                                </div>
                            </div>
                            
                            <div class="form-switch-custom">
                                <div>
                                    <div class="switch-label">Maintenance Mode</div>
                                    <div class="switch-description">Enable maintenance mode to prevent access</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="maintenance_mode" 
                                           <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                </div>
                            </div>
                            
                            <div class="form-switch-custom">
                                <div>
                                    <div class="switch-label">Auto Approve Applications</div>
                                    <div class="switch-description">Automatically approve applications that meet all criteria</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_approve_applications" 
                                           <?php echo $settings['auto_approve_applications'] ? 'checked' : ''; ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- File Upload Settings -->
                    <div class="settings-card">
                        <div class="card-header-custom">
                            <h3 class="card-title-custom">
                                <i class="fas fa-upload text-primary"></i>
                                File Upload Settings
                            </h3>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label">Maximum File Size (MB)</label>
                                    <input type="number" class="form-control" name="max_file_size_mb" 
                                           value="<?php echo $settings['max_file_size_mb']; ?>" 
                                           min="1" max="50" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Allowed File Types</label>
                                    <input type="text" class="form-control" name="allowed_file_types" 
                                           value="<?php echo htmlspecialchars($settings['allowed_file_types']); ?>" required>
                                    <small class="text-muted">Comma-separated (e.g., pdf,jpg,jpeg,png)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notification Settings -->
                    <div class="settings-card">
                        <div class="card-header-custom">
                            <h3 class="card-title-custom">
                                <i class="fas fa-bell text-primary"></i>
                                Notification Settings
                            </h3>
                        </div>
                        <div class="card-body p-4">
                            <div class="form-switch-custom">
                                <div>
                                    <div class="switch-label">Email Notifications</div>
                                    <div class="switch-description">Send email notifications for application updates</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="email_notifications" 
                                           <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                </div>
                            </div>
                            
                            <div class="form-switch-custom">
                                <div>
                                    <div class="switch-label">SMS Notifications</div>
                                    <div class="switch-description">Send SMS notifications for important updates</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="sms_notifications" 
                                           <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Save Button -->
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Date validation
        document.querySelector('[name="application_start_date"]').addEventListener('change', function() {
            const startDate = this.value;
            const endDateInput = document.querySelector('[name="application_end_date"]');
            endDateInput.min = startDate;
            
            if (endDateInput.value && endDateInput.value < startDate) {
                endDateInput.value = startDate;
            }
        });
        
        // Animate stats on load
        document.addEventListener('DOMContentLoaded', function() {
            const statNumbers = document.querySelectorAll('.stats-number');
            statNumbers.forEach(stat => {
                const target = parseInt(stat.textContent.replace(/,/g, ''));
                const increment = target / 30;
                let current = 0;
                
                const updateStat = () => {
                    if (current < target) {
                        current += increment;
                        stat.textContent = Math.ceil(current).toLocaleString();
                        requestAnimationFrame(updateStat);
                    } else {
                        stat.textContent = target.toLocaleString();
                    }
                };
                
                setTimeout(updateStat, Math.random() * 1000);
            });
        });
    </script>
</body>
</html>