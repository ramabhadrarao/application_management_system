<?php
/**
 * Application Management Class
 * 
 * File: classes/Application.php
 * Purpose: Handle all application-related operations including creation, submission, and management
 * Author: Student Application Management System
 * Created: 2025
 */

require_once '../config/config.php';

class Application {
    private $conn;
    private $table_name = "applications";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create new application
     * @param array $data
     * @return int|false Application ID on success, false on failure
     */
    public function create($data) {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                      SET application_number=:app_number, user_id=:user_id, program_id=:program_id,
                          academic_year=:academic_year, student_name=:student_name, 
                          father_name=:father_name, mother_name=:mother_name, 
                          date_of_birth=:dob, gender=:gender, mobile_number=:mobile,
                          email=:email, status=:status";

            $stmt = $this->conn->prepare($query);

            // Generate application number
            $app_number = $this->generateApplicationNumber($data['program_id']);

            $stmt->bindParam(":app_number", $app_number);
            $stmt->bindParam(":user_id", $data['user_id']);
            $stmt->bindParam(":program_id", $data['program_id']);
            $stmt->bindParam(":academic_year", $data['academic_year']);
            $stmt->bindParam(":student_name", $data['student_name']);
            $stmt->bindParam(":father_name", $data['father_name']);
            $stmt->bindParam(":mother_name", $data['mother_name']);
            $stmt->bindParam(":dob", $data['date_of_birth']);
            $stmt->bindParam(":gender", $data['gender']);
            $stmt->bindParam(":mobile", $data['mobile_number']);
            $stmt->bindParam(":email", $data['email']);
            $stmt->bindValue(":status", STATUS_DRAFT);

            if ($stmt->execute()) {
                $application_id = $this->conn->lastInsertId();
                
                // Log status change
                $this->logStatusChange($application_id, null, STATUS_DRAFT, $data['user_id'], 'Application created');
                
                return $application_id;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Application creation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update application
     * @param int $application_id
     * @param array $data
     * @return boolean
     */
    public function update($application_id, $data) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET student_name=:student_name, father_name=:father_name, 
                          mother_name=:mother_name, date_of_birth=:dob, gender=:gender,
                          mobile_number=:mobile, parent_mobile=:parent_mobile,
                          guardian_mobile=:guardian_mobile, email=:email,
                          present_door_no=:present_door_no, present_street=:present_street,
                          present_village=:present_village, present_mandal=:present_mandal,
                          present_district=:present_district, present_pincode=:present_pincode,
                          permanent_door_no=:permanent_door_no, permanent_street=:permanent_street,
                          permanent_village=:permanent_village, permanent_mandal=:permanent_mandal,
                          permanent_district=:permanent_district, permanent_pincode=:permanent_pincode,
                          religion=:religion, caste=:caste, reservation_category=:reservation_category,
                          is_physically_handicapped=:is_physically_handicapped,
                          aadhar_number=:aadhar_number, sadaram_number=:sadaram_number,
                          identification_mark_1=:identification_mark_1,
                          identification_mark_2=:identification_mark_2,
                          special_reservation=:special_reservation,
                          meeseva_caste_certificate=:meeseva_caste_certificate,
                          meeseva_income_certificate=:meeseva_income_certificate,
                          ration_card_number=:ration_card_number,
                          date_updated=CURRENT_TIMESTAMP
                      WHERE id=:id AND status IN (:draft_status, :frozen_status)";

            $stmt = $this->conn->prepare($query);

            // Bind parameters
            $stmt->bindParam(":student_name", $data['student_name']);
            $stmt->bindParam(":father_name", $data['father_name']);
            $stmt->bindParam(":mother_name", $data['mother_name']);
            $stmt->bindParam(":dob", $data['date_of_birth']);
            $stmt->bindParam(":gender", $data['gender']);
            $stmt->bindParam(":mobile", $data['mobile_number']);
            $stmt->bindParam(":parent_mobile", $data['parent_mobile']);
            $stmt->bindParam(":guardian_mobile", $data['guardian_mobile']);
            $stmt->bindParam(":email", $data['email']);
            
            // Address fields
            $stmt->bindParam(":present_door_no", $data['present_door_no']);
            $stmt->bindParam(":present_street", $data['present_street']);
            $stmt->bindParam(":present_village", $data['present_village']);
            $stmt->bindParam(":present_mandal", $data['present_mandal']);
            $stmt->bindParam(":present_district", $data['present_district']);
            $stmt->bindParam(":present_pincode", $data['present_pincode']);
            
            $stmt->bindParam(":permanent_door_no", $data['permanent_door_no']);
            $stmt->bindParam(":permanent_street", $data['permanent_street']);
            $stmt->bindParam(":permanent_village", $data['permanent_village']);
            $stmt->bindParam(":permanent_mandal", $data['permanent_mandal']);
            $stmt->bindParam(":permanent_district", $data['permanent_district']);
            $stmt->bindParam(":permanent_pincode", $data['permanent_pincode']);
            
            // Other fields
            $stmt->bindParam(":religion", $data['religion']);
            $stmt->bindParam(":caste", $data['caste']);
            $stmt->bindParam(":reservation_category", $data['reservation_category']);
            $stmt->bindParam(":is_physically_handicapped", $data['is_physically_handicapped']);
            $stmt->bindParam(":aadhar_number", $data['aadhar_number']);
            $stmt->bindParam(":sadaram_number", $data['sadaram_number']);
            $stmt->bindParam(":identification_mark_1", $data['identification_mark_1']);
            $stmt->bindParam(":identification_mark_2", $data['identification_mark_2']);
            $stmt->bindParam(":special_reservation", $data['special_reservation']);
            $stmt->bindParam(":meeseva_caste_certificate", $data['meeseva_caste_certificate']);
            $stmt->bindParam(":meeseva_income_certificate", $data['meeseva_income_certificate']);
            $stmt->bindParam(":ration_card_number", $data['ration_card_number']);
            
            $stmt->bindParam(":id", $application_id);
            $stmt->bindValue(":draft_status", STATUS_DRAFT);
            $stmt->bindValue(":frozen_status", STATUS_FROZEN);

            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Application update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get application by user ID
     * @param string $user_id
     * @return array|false
     */
    public function getByUserId($user_id) {
        $query = "SELECT a.*, p.program_name, p.program_code, p.department
                  FROM " . $this->table_name . " a
                  LEFT JOIN programs p ON a.program_id = p.id
                  WHERE a.user_id = :user_id
                  ORDER BY a.date_created DESC
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Get application by ID
     * @param int $application_id
     * @return array|false
     */
    public function getById($application_id) {
        $query = "SELECT a.*, p.program_name, p.program_code, p.department,
                         u.email as user_email
                  FROM " . $this->table_name . " a
                  LEFT JOIN programs p ON a.program_id = p.id
                  LEFT JOIN users u ON a.user_id = u.id
                  WHERE a.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $application_id);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Submit application
     * @param int $application_id
     * @param string $user_id
     * @return boolean
     */
    public function submit($application_id, $user_id) {
        try {
            // Check if application is complete
            if (!$this->isApplicationComplete($application_id)) {
                return false;
            }

            $this->conn->beginTransaction();

            // Update status to submitted
            $query = "UPDATE " . $this->table_name . " 
                      SET status = :status, submitted_at = CURRENT_TIMESTAMP
                      WHERE id = :id AND status = :draft_status";

            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":status", STATUS_SUBMITTED);
            $stmt->bindParam(":id", $application_id);
            $stmt->bindValue(":draft_status", STATUS_DRAFT);

            if ($stmt->execute() && $stmt->rowCount() > 0) {
                // Log status change
                $this->logStatusChange($application_id, STATUS_DRAFT, STATUS_SUBMITTED, $user_id, 'Application submitted');
                
                $this->conn->commit();
                return true;
            }
            
            $this->conn->rollBack();
            return false;
        } catch(PDOException $e) {
            $this->conn->rollBack();
            error_log("Application submission error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Freeze application (final submission with confirmation)
     * @param int $application_id
     * @param string $user_id
     * @return boolean
     */
    public function freeze($application_id, $user_id) {
        try {
            $this->conn->beginTransaction();

            $query = "UPDATE " . $this->table_name . " 
                      SET status = :status
                      WHERE id = :id AND status = :submitted_status";

            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":status", STATUS_FROZEN);
            $stmt->bindParam(":id", $application_id);
            $stmt->bindValue(":submitted_status", STATUS_SUBMITTED);

            if ($stmt->execute() && $stmt->rowCount() > 0) {
                // Log status change
                $this->logStatusChange($application_id, STATUS_SUBMITTED, STATUS_FROZEN, $user_id, 'Application frozen by student');
                
                $this->conn->commit();
                return true;
            }
            
            $this->conn->rollBack();
            return false;
        } catch(PDOException $e) {
            $this->conn->rollBack();
            error_log("Application freeze error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Unfreeze application (only admin/program admin can do this)
     * @param int $application_id
     * @param string $admin_id
     * @param string $reason
     * @return boolean
     */
    public function unfreeze($application_id, $admin_id, $reason = '') {
        try {
            $this->conn->beginTransaction();

            $query = "UPDATE " . $this->table_name . " 
                      SET status = :status
                      WHERE id = :id AND status = :frozen_status";

            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":status", STATUS_SUBMITTED);
            $stmt->bindParam(":id", $application_id);
            $stmt->bindValue(":frozen_status", STATUS_FROZEN);

            if ($stmt->execute() && $stmt->rowCount() > 0) {
                // Log status change
                $this->logStatusChange($application_id, STATUS_FROZEN, STATUS_SUBMITTED, $admin_id, 'Application unfrozen by admin: ' . $reason);
                
                $this->conn->commit();
                return true;
            }
            
            $this->conn->rollBack();
            return false;
        } catch(PDOException $e) {
            $this->conn->rollBack();
            error_log("Application unfreeze error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update application status (approve/reject)
     * @param int $application_id
     * @param string $status
     * @param string $admin_id
     * @param string $comments
     * @return boolean
     */
    public function updateStatus($application_id, $status, $admin_id, $comments = '') {
        try {
            $this->conn->beginTransaction();

            // Get current status
            $current_status = $this->getCurrentStatus($application_id);

            $query = "UPDATE " . $this->table_name . " 
                      SET status = :status, reviewed_by = :reviewed_by, 
                          reviewed_at = CURRENT_TIMESTAMP, approval_comments = :comments
                      WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":status", $status);
            $stmt->bindParam(":reviewed_by", $admin_id);
            $stmt->bindParam(":comments", $comments);
            $stmt->bindParam(":id", $application_id);

            if ($stmt->execute()) {
                // Log status change
                $this->logStatusChange($application_id, $current_status, $status, $admin_id, $comments);
                
                $this->conn->commit();
                return true;
            }
            
            $this->conn->rollBack();
            return false;
        } catch(PDOException $e) {
            $this->conn->rollBack();
            error_log("Application status update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get applications with filters and pagination
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getApplications($filters = [], $page = 1, $limit = RECORDS_PER_PAGE) {
        $offset = ($page - 1) * $limit;
        
        $where_conditions = [];
        $params = [];
        
        // Apply filters
        if (!empty($filters['program_id'])) {
            $where_conditions[] = "a.program_id = :program_id";
            $params[':program_id'] = $filters['program_id'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "a.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['academic_year'])) {
            $where_conditions[] = "a.academic_year = :academic_year";
            $params[':academic_year'] = $filters['academic_year'];
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = "(a.application_number LIKE :search OR a.student_name LIKE :search OR a.email LIKE :search)";
            $params[':search'] = "%{$filters['search']}%";
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "a.date_created >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "a.date_created <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        $query = "SELECT a.*, p.program_name, p.program_code, p.department,
                         COUNT(ad.id) as documents_count,
                         COUNT(CASE WHEN ad.is_verified = 1 THEN 1 END) as verified_documents
                  FROM " . $this->table_name . " a
                  LEFT JOIN programs p ON a.program_id = p.id
                  LEFT JOIN application_documents ad ON a.id = ad.application_id
                  " . $where_clause . "
                  GROUP BY a.id
                  ORDER BY a.date_created DESC 
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get application count for pagination
     * @param array $filters
     * @return int
     */
    public function getApplicationCount($filters = []) {
        $where_conditions = [];
        $params = [];
        
        // Apply same filters as getApplications
        if (!empty($filters['program_id'])) {
            $where_conditions[] = "a.program_id = :program_id";
            $params[':program_id'] = $filters['program_id'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "a.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['academic_year'])) {
            $where_conditions[] = "a.academic_year = :academic_year";
            $params[':academic_year'] = $filters['academic_year'];
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = "(a.application_number LIKE :search OR a.student_name LIKE :search OR a.email LIKE :search)";
            $params[':search'] = "%{$filters['search']}%";
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "a.date_created >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "a.date_created <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        $query = "SELECT COUNT(DISTINCT a.id) as total 
                  FROM " . $this->table_name . " a
                  LEFT JOIN programs p ON a.program_id = p.id
                  " . $where_clause;

        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $row = $stmt->fetch();
        
        return $row['total'];
    }

    /**
     * Generate application number
     * @param int $program_id
     * @return string
     */
    private function generateApplicationNumber($program_id) {
        // Get program code
        $query = "SELECT program_code FROM programs WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $program_id);
        $stmt->execute();
        $program = $stmt->fetch();
        
        $program_code = $program ? $program['program_code'] : 'GEN';
        
        // Get next sequence number for this academic year and program
        $year = date('Y');
        $query = "SELECT COUNT(*) + 1 as sequence FROM " . $this->table_name . " 
                  WHERE program_id = :program_id AND YEAR(date_created) = :year";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":program_id", $program_id);
        $stmt->bindParam(":year", $year);
        $stmt->execute();
        $result = $stmt->fetch();
        
        $sequence = str_pad($result['sequence'], 4, '0', STR_PAD_LEFT);
        
        return $program_code . $year . $sequence;
    }

    /**
     * Check if application is complete
     * @param int $application_id
     * @return boolean
     */
    private function isApplicationComplete($application_id) {
        // Check required fields
        $query = "SELECT student_name, father_name, mother_name, date_of_birth, 
                         gender, mobile_number, email
                  FROM " . $this->table_name . " 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $application_id);
        $stmt->execute();
        $app = $stmt->fetch();
        
        if (!$app || empty($app['student_name']) || empty($app['father_name']) || 
            empty($app['mother_name']) || empty($app['date_of_birth']) || 
            empty($app['gender']) || empty($app['mobile_number']) || empty($app['email'])) {
            return false;
        }
        
        // Check required documents
        $query = "SELECT COUNT(*) as missing_docs
                  FROM program_certificate_requirements pcr
                  WHERE pcr.program_id = (SELECT program_id FROM " . $this->table_name . " WHERE id = :id)
                    AND pcr.is_required = 1
                    AND NOT EXISTS (
                        SELECT 1 FROM application_documents ad 
                        WHERE ad.application_id = :id AND ad.certificate_type_id = pcr.certificate_type_id
                    )";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $application_id);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return $result['missing_docs'] == 0;
    }

    /**
     * Get current status of application
     * @param int $application_id
     * @return string|null
     */
    private function getCurrentStatus($application_id) {
        $query = "SELECT status FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $application_id);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return $result ? $result['status'] : null;
    }

    /**
     * Log status change in history
     * @param int $application_id
     * @param string $from_status
     * @param string $to_status
     * @param string $changed_by
     * @param string $remarks
     */
    private function logStatusChange($application_id, $from_status, $to_status, $changed_by, $remarks = '') {
        $query = "INSERT INTO application_status_history 
                  SET application_id = :app_id, from_status = :from_status, 
                      to_status = :to_status, changed_by = :changed_by, remarks = :remarks";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":app_id", $application_id);
        $stmt->bindParam(":from_status", $from_status);
        $stmt->bindParam(":to_status", $to_status);
        $stmt->bindParam(":changed_by", $changed_by);
        $stmt->bindParam(":remarks", $remarks);
        $stmt->execute();
    }

    /**
     * Get application status history
     * @param int $application_id
     * @return array
     */
    public function getStatusHistory($application_id) {
        $query = "SELECT ash.*, u.email as changed_by_email
                  FROM application_status_history ash
                  LEFT JOIN users u ON ash.changed_by = u.id
                  WHERE ash.application_id = :app_id
                  ORDER BY ash.date_created ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":app_id", $application_id);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}