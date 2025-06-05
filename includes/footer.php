<?php
/**
 * Common Footer Include
 * 
 * File: includes/footer.php
 * Purpose: Common footer with scripts for all pages
 * Author: Student Application Management System
 * Created: 2025
 */
?>
                </div>
            </div>
            
            <!-- Footer -->
            <footer class="footer footer-transparent d-print-none">
                <div class="container-xl">
                    <div class="row text-center align-items-center flex-row-reverse">
                        <div class="col-lg-auto ms-lg-auto">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li class="list-inline-item">
                                    <a href="<?php echo SITE_URL; ?>/help.php" class="link-secondary">Help</a>
                                </li>
                                <li class="list-inline-item">
                                    <a href="<?php echo SITE_URL; ?>/contact.php" class="link-secondary">Contact</a>
                                </li>
                                <li class="list-inline-item">
                                    <a href="<?php echo SITE_URL; ?>/privacy.php" class="link-secondary">Privacy Policy</a>
                                </li>
                            </ul>
                        </div>
                        <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li class="list-inline-item">
                                    Copyright &copy; <?php echo date('Y'); ?>
                                    <a href="<?php echo SITE_URL; ?>" class="link-secondary"><?php echo SITE_NAME; ?></a>.
                                    All rights reserved.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    
    <!-- Tabler Core -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Sweet Alert 2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Common JavaScript -->
    <script>
        // Global CSRF token
        const csrfToken = '<?php echo generateCSRFToken(); ?>';
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert:not(.alert-permanent)').fadeOut('slow');
        }, 5000);
        
        // Confirmation dialogs
        document.addEventListener('DOMContentLoaded', function() {
            // Confirm delete actions
            document.querySelectorAll('[data-confirm-delete]').forEach(function(element) {
                element.addEventListener('click', function(e) {
                    e.preventDefault();
                    const message = this.getAttribute('data-confirm-delete') || 'Are you sure you want to delete this item?';
                    
                    Swal.fire({
                        title: 'Are you sure?',
                        text: message,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Yes, delete it!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            if (this.tagName === 'A') {
                                window.location.href = this.href;
                            } else if (this.tagName === 'BUTTON' && this.form) {
                                this.form.submit();
                            }
                        }
                    });
                });
            });
            
            // Confirm action dialogs
            document.querySelectorAll('[data-confirm]').forEach(function(element) {
                element.addEventListener('click', function(e) {
                    e.preventDefault();
                    const message = this.getAttribute('data-confirm') || 'Are you sure you want to perform this action?';
                    
                    Swal.fire({
                        title: 'Confirm Action',
                        text: message,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#0054a6',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, proceed',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            if (this.tagName === 'A') {
                                window.location.href = this.href;
                            } else if (this.tagName === 'BUTTON' && this.form) {
                                this.form.submit();
                            }
                        }
                    });
                });
            });
            
            // Loading states for buttons
            document.querySelectorAll('form').forEach(function(form) {
                form.addEventListener('submit', function() {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Processing...';
                        
                        // Re-enable after 10 seconds as fallback
                        setTimeout(function() {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = submitBtn.getAttribute('data-original-text') || 'Submit';
                        }, 10000);
                    }
                });
            });
            
            // Store original button text
            document.querySelectorAll('button[type="submit"]').forEach(function(btn) {
                btn.setAttribute('data-original-text', btn.innerHTML);
            });
        });
        
        // AJAX helper function
        function ajaxRequest(url, data, method = 'POST') {
            return fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: method !== 'GET' ? JSON.stringify(data) : null
            })
            .then(response => response.json())
            .catch(error => {
                console.error('AJAX Error:', error);
                throw error;
            });
        }
        
        // Show success message
        function showSuccessMessage(message, title = 'Success!') {
            Swal.fire({
                title: title,
                text: message,
                icon: 'success',
                confirmButtonColor: '#0054a6'
            });
        }
        
        // Show error message
        function showErrorMessage(message, title = 'Error!') {
            Swal.fire({
                title: title,
                text: message,
                icon: 'error',
                confirmButtonColor: '#d33'
            });
        }
        
        // Show info message
        function showInfoMessage(message, title = 'Information') {
            Swal.fire({
                title: title,
                text: message,
                icon: 'info',
                confirmButtonColor: '#0054a6'
            });
        }
        
        // Copy to clipboard function
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                showSuccessMessage('Copied to clipboard!');
            }).catch(function() {
                showErrorMessage('Failed to copy to clipboard');
            });
        }
        
        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Validate file upload
        function validateFileUpload(fileInput, allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'], maxSize = 5242880) {
            const file = fileInput.files[0];
            if (!file) return true;
            
            // Check file type
            const fileName = file.name.toLowerCase();
            const fileExtension = fileName.split('.').pop();
            
            if (!allowedTypes.includes(fileExtension)) {
                showErrorMessage(`Invalid file type. Allowed types: ${allowedTypes.join(', ')}`);
                fileInput.value = '';
                return false;
            }
            
            // Check file size
            if (file.size > maxSize) {
                showErrorMessage(`File size too large. Maximum allowed: ${formatFileSize(maxSize)}`);
                fileInput.value = '';
                return false;
            }
            
            return true;
        }
        
        // Auto-save draft functionality
        let autoSaveTimeout;
        function enableAutoSave(formSelector, saveUrl, interval = 30000) {
            const form = document.querySelector(formSelector);
            if (!form) return;
            
            function autoSave() {
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                data.auto_save = true;
                
                fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        // Show subtle save indicator
                        const indicator = document.querySelector('#auto-save-indicator');
                        if (indicator) {
                            indicator.textContent = 'Draft saved at ' + new Date().toLocaleTimeString();
                            indicator.classList.add('text-success');
                            setTimeout(() => indicator.classList.remove('text-success'), 2000);
                        }
                    }
                })
                .catch(error => console.log('Auto-save failed:', error));
            }
            
            // Auto-save on form changes
            form.addEventListener('input', function() {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(autoSave, interval);
            });
            
            // Auto-save periodically
            setInterval(autoSave, interval * 2);
        }
        
        // Session timeout warning
        let sessionWarningShown = false;
        function checkSessionTimeout() {
            const lastActivity = <?php echo isset($_SESSION['last_activity']) ? $_SESSION['last_activity'] : 'Date.now()/1000'; ?>;
            const sessionTimeout = <?php echo SESSION_TIMEOUT; ?>;
            const currentTime = Date.now() / 1000;
            const timeRemaining = sessionTimeout - (currentTime - lastActivity);
            
            if (timeRemaining <= 300 && !sessionWarningShown) { // 5 minutes warning
                sessionWarningShown = true;
                Swal.fire({
                    title: 'Session Expiring',
                    text: 'Your session will expire in 5 minutes. Do you want to extend it?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Extend Session',
                    cancelButtonText: 'Logout'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Extend session by making a request
                        fetch('<?php echo SITE_URL; ?>/ajax/extend-session.php', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-Token': csrfToken
                            }
                        }).then(() => {
                            sessionWarningShown = false;
                            showSuccessMessage('Session extended successfully');
                        });
                    } else {
                        window.location.href = '<?php echo SITE_URL; ?>/auth/logout.php';
                    }
                });
            } else if (timeRemaining <= 0) {
                window.location.href = '<?php echo SITE_URL; ?>/auth/logout.php?reason=timeout';
            }
        }
        
        // Check session timeout every minute
        if (<?php echo isLoggedIn() ? 'true' : 'false'; ?>) {
            setInterval(checkSessionTimeout, 60000);
        }
        
        // Responsive table wrapper
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.table:not(.table-responsive *)').forEach(function(table) {
                if (!table.parentElement.classList.contains('table-responsive')) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'table-responsive';
                    table.parentNode.insertBefore(wrapper, table);
                    wrapper.appendChild(table);
                }
            });
        });
        
        // Print functionality
        function printPage() {
            window.print();
        }
        
        // Export to PDF (basic)
        function exportToPDF() {
            window.print();
        }
    </script>
    
    <?php if (isset($extra_js)): ?>
        <?php echo $extra_js; ?>
    <?php endif; ?>
    
    <!-- Page-specific JavaScript -->
    <?php if (isset($page_js)): ?>
        <script><?php echo $page_js; ?></script>
    <?php endif; ?>
</body>
</html>