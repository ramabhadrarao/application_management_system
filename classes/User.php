<?php
/**
 * User Management Class
 * 
 * File: classes/User.php
 * Purpose: Handle all user-related operations including registration, login, and management
 * Author: Student Application Management System
 * Created: 2025
 */

// Use absolute path detection to avoid path issues
if (!defined('CONFIG_LOADED')) {
    // Detect if we're being called from root or subdirectory
    $config_path = '';
    if (file_exists('config/config.php')) {
        $config_path = 'config/config.php';
    } elseif (file_exists('../config/config.php')) {
        $config_path = '../config/config.php';
    } elseif (file_exists('../../config/config.php')) {
        $config_path = '../../config/config.php';
    }
    
    if ($config_path) {
        require_once $config_path;
        define('CONFIG_LOADED', true);
    }
}

class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $email;
    public $password;
    public $role;
    public $is_active;
    public $email_verified;
    public $program_id;
    public $last_login;
    public $login_attempts;
    public $locked_until;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Register a new user
     * @return boolean
     */
    public function register() {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                      SET id=:id, email=:email, password=:password, role=:role, 
                          is_active=:is_active, email_verified=:email_verified, program_id=:program_id";

            $stmt = $this->conn->prepare($query);

            // Generate UUID for user ID
            $this->id = $this->generateUUID();
            
            // Hash password
            $hashed_password = password_hash($this->password, PASSWORD_DEFAULT);

            $stmt->bindParam(":id", $this->id);
            $stmt->bindParam(":email", $this->email);
            $stmt->bindParam(":password", $hashed_password);
            $stmt->bindParam(":role", $this->role);
            $stmt->bindParam(":is_active", $this->is_active);
            $stmt->bindParam(":email_verified", $this->email_verified);
            $stmt->bindParam(":program_id", $this->program_id);

            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("User registration error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Authenticate user login
     * @return boolean
     */
    public function login() {
        try {
            // Check if account is locked
            if ($this->isAccountLocked()) {
                return false;
            }

            $query = "SELECT id, email, password, role, is_active, email_verified, program_id, 
                             login_attempts, locked_until
                      FROM " . $this->table_name . " 
                      WHERE email = :email";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":email", $this->email);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && $row['is_active'] == 1) {
                if (password_verify($this->password, $row['password'])) {
                    // Successful login
                    $this->id = $row['id'];
                    $this->role = $row['role'];
                    $this->is_active = $row['is_active'];
                    $this->email_verified = $row['email_verified'];
                    $this->program_id = $row['program_id'];
                    
                    // Reset login attempts and update last login
                    $this->resetLoginAttempts();
                    $this->updateLastLogin();
                    
                    // Set session variables
                    $_SESSION['user_id'] = $this->id;
                    $_SESSION['user_email'] = $this->email;
                    $_SESSION['user_role'] = $this->role;
                    $_SESSION['program_id'] = $this->program_id;
                    $_SESSION['last_activity'] = time();
                    
                    return true;
                } else {
                    // Failed login - increment attempts
                    $this->incrementLoginAttempts();
                }
            }
            return false;
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user email exists
     * @param string $email
     * @return boolean
     */
    public function emailExists($email) {
        try {
            $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            error_log("Email exists check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin() {
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET last_login = CURRENT_TIMESTAMP 
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $this->id);
            $stmt->execute();
        } catch(PDOException $e) {
            error_log("Update last login error: " . $e->getMessage());
        }
    }

    /**
     * Change user password
     * @param string $old_password
     * @param string $new_password
     * @return boolean
     */
    public function changePassword($old_password, $new_password) {
        try {
            // Verify old password
            $query = "SELECT password FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $this->id);
            $stmt->execute();
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!password_verify($old_password, $row['password'])) {
                return false;
            }

            // Update password
            $query = "UPDATE " . $this->table_name . " 
                      SET password = :password, date_updated = CURRENT_TIMESTAMP 
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt->bindParam(":password", $new_password_hash);
            $stmt->bindParam(":id", $this->id);
            
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Password change error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all users with pagination and filtering
     * @param string $role
     * @param int $page
     * @param int $limit
     * @param string $search
     * @return array
     */
    public function getAllUsers($role = null, $page = 1, $limit = 20, $search = '') {
        try {
            $offset = ($page - 1) * $limit;
            
            $where_conditions = [];
            $params = [];
            
            if ($role) {
                $where_conditions[] = "u.role = :role";
                $params[':role'] = $role;
            }
            
            if ($search) {
                $where_conditions[] = "(u.email LIKE :search OR p.program_name LIKE :search)";
                $params[':search'] = "%$search%";
            }
            
            $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
            
            $query = "SELECT u.*, p.program_name, p.program_code
                      FROM " . $this->table_name . " u
                      LEFT JOIN programs p ON u.program_id = p.id
                      " . $where_clause . "
                      ORDER BY u.date_created DESC 
                      LIMIT :limit OFFSET :offset";

            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
            $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get all users error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get total user count for pagination
     * @param string $role
     * @param string $search
     * @return int
     */
    public function getUserCount($role = null, $search = '') {
        try {
            $where_conditions = [];
            $params = [];
            
            if ($role) {
                $where_conditions[] = "u.role = :role";
                $params[':role'] = $role;
            }
            
            if ($search) {
                $where_conditions[] = "(u.email LIKE :search OR p.program_name LIKE :search)";
                $params[':search'] = "%$search%";
            }
            
            $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
            
            $query = "SELECT COUNT(*) as total 
                      FROM " . $this->table_name . " u
                      LEFT JOIN programs p ON u.program_id = p.id
                      " . $where_clause;

            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $row['total'];
        } catch(PDOException $e) {
            error_log("Get user count error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Toggle user active status
     * @param string $user_id
     * @return boolean
     */
    public function toggleUserStatus($user_id) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END,
                          date_updated = CURRENT_TIMESTAMP
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $user_id);
            
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Toggle user status error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user by ID
     * @param string $user_id
     * @return array|false
     */
    public function getUserById($user_id) {
        try {
            $query = "SELECT u.*, p.program_name, p.program_code
                      FROM " . $this->table_name . " u
                      LEFT JOIN programs p ON u.program_id = p.id
                      WHERE u.id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $user_id);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get user by ID error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user profile
     * @param array $data
     * @return boolean
     */
    public function updateProfile($data) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET email = :email, program_id = :program_id, 
                          date_updated = CURRENT_TIMESTAMP
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":email", $data['email']);
            $stmt->bindParam(":program_id", $data['program_id']);
            $stmt->bindParam(":id", $this->id);
            
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Update profile error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if account is locked due to failed login attempts
     * @return boolean
     */
    private function isAccountLocked() {
        try {
            $query = "SELECT login_attempts, locked_until FROM " . $this->table_name . " 
                      WHERE email = :email";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":email", $this->email);
            $stmt->execute();
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                // Check if account is temporarily locked
                if ($row['locked_until'] && strtotime($row['locked_until']) > time()) {
                    return true;
                }
            }
            
            return false;
        } catch(PDOException $e) {
            error_log("Check account locked error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Increment login attempts
     */
    private function incrementLoginAttempts() {
        try {
            // Define constants as variables to pass by reference
            $max_attempts = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;
            $lockout_time = defined('LOCKOUT_TIME') ? LOCKOUT_TIME : 1800; // 30 minutes
            
            $query = "UPDATE " . $this->table_name . " 
                      SET login_attempts = login_attempts + 1,
                          locked_until = CASE 
                              WHEN login_attempts + 1 >= :max_attempts 
                              THEN DATE_ADD(NOW(), INTERVAL :lockout_time SECOND)
                              ELSE locked_until 
                          END
                      WHERE email = :email";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":max_attempts", $max_attempts, PDO::PARAM_INT);
            $stmt->bindValue(":lockout_time", $lockout_time, PDO::PARAM_INT);
            $stmt->bindParam(":email", $this->email);
            $stmt->execute();
        } catch(PDOException $e) {
            error_log("Increment login attempts error: " . $e->getMessage());
        }
    }

    /**
     * Reset login attempts after successful login
     */
    private function resetLoginAttempts() {
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET login_attempts = 0, locked_until = NULL
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $this->id);
            $stmt->execute();
        } catch(PDOException $e) {
            error_log("Reset login attempts error: " . $e->getMessage());
        }
    }

    /**
     * Generate UUID
     * @return string
     */
    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Logout user
     */
    public static function logout() {
        // Destroy session
        session_unset();
        session_destroy();
        
        // Clear any remember me cookies
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        // Redirect to login page
        $site_url = defined('SITE_URL') ? SITE_URL : '';
        header('Location: ' . $site_url . '/auth/login.php');
        exit;
    }
}