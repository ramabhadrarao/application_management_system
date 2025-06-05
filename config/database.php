<?php
/**
 * Database Configuration Class
 * 
 * File: config/database.php
 * Purpose: Handles database connection using PDO
 * Author: Student Application Management System
 * Created: 2025
 */

class Database {
    private $host = "localhost";
    private $db_name = "student_application_db";
    private $username = "ramabhadrarao";
    private $password = "nihita1981";
    private $conn;

    /**
     * Get database connection
     * @return PDO connection object
     */
    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8", 
                $this->username, 
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
            die();
        }
        return $this->conn;
    }

    /**
     * Test database connection
     * @return boolean
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            return $conn !== null;
        } catch(Exception $e) {
            return false;
        }
    }
}