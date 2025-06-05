<?php
/**
 * Program Management Class
 * 
 * File: classes/Program.php
 * Purpose: Handle all program-related operations including CRUD and certificate requirements
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

class Program {
    private $conn;
    private $table_name = "programs";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get all active programs
     * @return array
     */
    public function getAllActivePrograms() {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE is_active = 1 
                  ORDER BY display_order ASC, program_name ASC";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Get active programs error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get programs with pagination and filters
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getPrograms($filters = [], $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        $where_conditions = ["1=1"];
        $params = [];
        
        if (!empty($filters['program_type'])) {
            $where_conditions[] = "program_type = :program_type";
            $params[':program_type'] = $filters['program_type'];
        }
        
        if (!empty($filters['department'])) {
            $where_conditions[] = "department LIKE :department";
            $params[':department'] = "%{$filters['department']}%";
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = "(program_name LIKE :search OR program_code LIKE :search)";
            $params[':search'] = "%{$filters['search']}%";
        }
        
        if (isset($filters['is_active'])) {
            $where_conditions[] = "is_active = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }
        
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        
        $query = "SELECT p.*, u.email as admin_email,
                         (SELECT COUNT(*) FROM applications a WHERE a.program_id = p.id) as total_applications
                  FROM " . $this->table_name . " p
                  LEFT JOIN users u ON p.program_admin_id = u.id
                  " . $where_clause . "
                  ORDER BY p.display_order ASC, p.program_name ASC
                  LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
            $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Get programs error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get program count for pagination
     * @param array $filters
     * @return int
     */
    public function getProgramCount($filters = []) {
        $where_conditions = ["1=1"];
        $params = [];
        
        if (!empty($filters['program_type'])) {
            $where_conditions[] = "program_type = :program_type";
            $params[':program_type'] = $filters['program_type'];
        }
        
        if (!empty($filters['department'])) {
            $where_conditions[] = "department LIKE :department";
            $params[':department'] = "%{$filters['department']}%";
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = "(program_name LIKE :search OR program_code LIKE :search)";
            $params[':search'] = "%{$filters['search']}%";
        }
        
        if (isset($filters['is_active'])) {
            $where_conditions[] = "is_active = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }
        
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " " . $where_clause;

        try {
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $row = $stmt->fetch();
            
            return $row['total'];
        } catch(PDOException $e) {
            error_log("Get program count error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get program by ID
     * @param int $program_id
     * @return array|false
     */
    public function getById($program_id) {
        $query = "SELECT p.*, u.email as admin_email
                  FROM " . $this->table_name . " p
                  LEFT JOIN users u ON p.program_admin_id = u.id
                  WHERE p.id = :id";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $program_id);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("Get program by ID error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create new program
     * @param array $data
     * @return int|false Program ID on success, false on failure
     */
    public function create($data) {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                      SET program_code=:program_code, program_name=:program_name, 
                          program_type=:program_type, department=:department,
                          duration_years=:duration_years, total_seats=:total_seats,
                          application_start_date=:start_date, application_end_date=:end_date,
                          program_admin_id=:admin_id, eligibility_criteria=:eligibility,
                          fees_structure=:fees, description=:description,
                          display_order=:display_order";

            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(":program_code", $data['program_code']);
            $stmt->bindParam(":program_name", $data['program_name']);
            $stmt->bindParam(":program_type", $data['program_type']);
            $stmt->bindParam(":department", $data['department']);
            $stmt->bindParam(":duration_years", $data['duration_years']);
            $stmt->bindParam(":total_seats", $data['total_seats']);
            $stmt->bindParam(":start_date", $data['application_start_date']);
            $stmt->bindParam(":end_date", $data['application_end_date']);
            $stmt->bindParam(":admin_id", $data['program_admin_id']);
            $stmt->bindParam(":eligibility", $data['eligibility_criteria']);
            $stmt->bindParam(":fees", $data['fees_structure']);
            $stmt->bindParam(":description", $data['description']);
            $stmt->bindParam(":display_order", $data['display_order']);

            if ($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
            return false;
        } catch(PDOException $e) {
            error_log("Program creation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update program
     * @param int $program_id
     * @param array $data
     * @return boolean
     */
    public function update($program_id, $data) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET program_code=:program_code, program_name=:program_name, 
                          program_type=:program_type, department=:department,
                          duration_years=:duration_years, total_seats=:total_seats,
                          application_start_date=:start_date, application_end_date=:end_date,
                          program_admin_id=:admin_id, eligibility_criteria=:eligibility,
                          fees_structure=:fees, description=:description,
                          display_order=:display_order, date_updated=CURRENT_TIMESTAMP
                      WHERE id=:id";

            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(":program_code", $data['program_code']);
            $stmt->bindParam(":program_name", $data['program_name']);
            $stmt->bindParam(":program_type", $data['program_type']);
            $stmt->bindParam(":department", $data['department']);
            $stmt->bindParam(":duration_years", $data['duration_years']);
            $stmt->bindParam(":total_seats", $data['total_seats']);
            $stmt->bindParam(":start_date", $data['application_start_date']);
            $stmt->bindParam(":end_date", $data['application_end_date']);
            $stmt->bindParam(":admin_id", $data['program_admin_id']);
            $stmt->bindParam(":eligibility", $data['eligibility_criteria']);
            $stmt->bindParam(":fees", $data['fees_structure']);
            $stmt->bindParam(":description", $data['description']);
            $stmt->bindParam(":display_order", $data['display_order']);
            $stmt->bindParam(":id", $program_id);

            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Program update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Toggle program active status
     * @param int $program_id
     * @return boolean
     */
    public function toggleStatus($program_id) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END,
                          date_updated = CURRENT_TIMESTAMP
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $program_id);
            
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Toggle program status error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete program (only if no applications exist)
     * @param int $program_id
     * @return boolean
     */
    public function delete($program_id) {
        try {
            // Check if any applications exist for this program
            $query = "SELECT COUNT(*) as count FROM applications WHERE program_id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $program_id);
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                return false; // Cannot delete program with applications
            }

            $this->conn->beginTransaction();

            // Delete program certificate requirements first
            $query = "DELETE FROM program_certificate_requirements WHERE program_id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $program_id);
            $stmt->execute();

            // Delete program
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $program_id);
            
            if ($stmt->execute()) {
                $this->conn->commit();
                return true;
            }
            
            $this->conn->rollBack();
            return false;
        } catch(PDOException $e) {
            $this->conn->rollBack();
            error_log("Program deletion error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get program certificate requirements
     * @param int $program_id
     * @return array
     */
    public function getCertificateRequirements($program_id) {
        $query = "SELECT pcr.*, ct.name as certificate_name, ct.description, 
                         ct.file_types_allowed, ct.max_file_size_mb
                  FROM program_certificate_requirements pcr
                  JOIN certificate_types ct ON pcr.certificate_type_id = ct.id
                  WHERE pcr.program_id = :program_id AND ct.is_active = 1
                  ORDER BY pcr.display_order ASC, ct.display_order ASC";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":program_id", $program_id);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Get certificate requirements error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update program certificate requirements
     * @param int $program_id
     * @param array $requirements Array of certificate_type_ids
     * @return boolean
     */
    public function updateCertificateRequirements($program_id, $requirements) {
        try {
            $this->conn->beginTransaction();

            // Delete existing requirements
            $query = "DELETE FROM program_certificate_requirements WHERE program_id = :program_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":program_id", $program_id);
            $stmt->execute();

            // Insert new requirements
            if (!empty($requirements)) {
                $query = "INSERT INTO program_certificate_requirements 
                          (program_id, certificate_type_id, is_required, display_order)
                          VALUES (:program_id, :cert_id, :is_required, :display_order)";
                
                $stmt = $this->conn->prepare($query);
                
                $order = 1;
                foreach ($requirements as $requirement) {
                    $stmt->bindParam(":program_id", $program_id);
                    $stmt->bindParam(":cert_id", $requirement['certificate_type_id']);
                    $stmt->bindParam(":is_required", $requirement['is_required']);
                    $stmt->bindParam(":display_order", $order);
                    $stmt->execute();
                    $order++;
                }
            }

            $this->conn->commit();
            return true;
        } catch(PDOException $e) {
            $this->conn->rollBack();
            error_log("Update certificate requirements error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all program types
     * @return array
     */
    public function getProgramTypes() {
        return ['UG', 'PG', 'Diploma', 'Certificate'];
    }

    /**
     * Get all departments
     * @return array
     */
    public function getAllDepartments() {
        $query = "SELECT DISTINCT department FROM " . $this->table_name . " 
                  WHERE department IS NOT NULL AND department != ''
                  ORDER BY department ASC";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $departments = [];
            while ($row = $stmt->fetch()) {
                $departments[] = $row['department'];
            }
            
            return $departments;
        } catch(PDOException $e) {
            error_log("Get departments error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get programs by admin ID (for program admins)
     * @param string $admin_id
     * @return array
     */
    public function getProgramsByAdmin($admin_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE program_admin_id = :admin_id AND is_active = 1
                  ORDER BY display_order ASC, program_name ASC";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":admin_id", $admin_id);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Get programs by admin error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if user is admin of program
     * @param string $user_id
     * @param int $program_id
     * @return boolean
     */
    public function isProgramAdmin($user_id, $program_id) {
        $query = "SELECT id FROM " . $this->table_name . " 
                  WHERE id = :program_id AND program_admin_id = :user_id";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":program_id", $program_id);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            error_log("Check program admin error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get program statistics
     * @param int $program_id
     * @return array
     */
    public function getStatistics($program_id = null) {
        $where_clause = $program_id ? "WHERE p.id = :program_id" : "";
        
        $query = "SELECT p.id, p.program_name, p.program_code, p.total_seats,
                         COUNT(a.id) as total_applications,
                         COUNT(CASE WHEN a.status = 'draft' THEN 1 END) as draft_applications,
                         COUNT(CASE WHEN a.status = 'submitted' THEN 1 END) as submitted_applications,
                         COUNT(CASE WHEN a.status = 'under_review' THEN 1 END) as under_review_applications,
                         COUNT(CASE WHEN a.status = 'approved' THEN 1 END) as approved_applications,
                         COUNT(CASE WHEN a.status = 'rejected' THEN 1 END) as rejected_applications,
                         COUNT(CASE WHEN a.status = 'frozen' THEN 1 END) as frozen_applications
                  FROM " . $this->table_name . " p
                  LEFT JOIN applications a ON p.id = a.program_id AND a.academic_year = :academic_year
                  " . $where_clause . "
                  GROUP BY p.id
                  ORDER BY p.display_order ASC, p.program_name ASC";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":academic_year", '2025-26'); // Current academic year
            
            if ($program_id) {
                $stmt->bindParam(":program_id", $program_id);
            }
            
            $stmt->execute();
            
            if ($program_id) {
                return $stmt->fetch();
            } else {
                return $stmt->fetchAll();
            }
        } catch(PDOException $e) {
            error_log("Get statistics error: " . $e->getMessage());
            return $program_id ? [] : [];
        }
    }

    /**
     * Check if program code exists
     * @param string $program_code
     * @param int $exclude_id
     * @return boolean
     */
    public function programCodeExists($program_code, $exclude_id = null) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE program_code = :code";
        $params = [':code' => $program_code];
        
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
            $params[':exclude_id'] = $exclude_id;
        }
        
        try {
            $stmt = $this->conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            error_log("Check program code exists error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get available program admins (users with program_admin role)
     * @return array
     */
    public function getAvailableProgramAdmins() {
        $query = "SELECT id, email FROM users 
                  WHERE role = :role AND is_active = 1
                  ORDER BY email ASC";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":role", 'program_admin');
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Get available program admins error: " . $e->getMessage());
            return [];
        }
    }
}