-- ==========================================
-- STUDENT APPLICATION MANAGEMENT SYSTEM
-- Complete Database Schema with Sample Data
-- ==========================================
-- File: database/complete_database.sql
-- Purpose: Complete database setup with all tables, indexes, views, and sample data
-- Author: Student Application Management System
-- Created: 2025
-- ==========================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `student_application_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `student_application_db`;

-- Set SQL mode and foreign key checks
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

-- ==========================================
-- CORE TABLES
-- ==========================================

-- Users table (matching the PHP application structure)
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` VARCHAR(36) PRIMARY KEY,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin','program_admin','student') NOT NULL DEFAULT 'student',
    `is_active` TINYINT(1) DEFAULT 1,
    `email_verified` TINYINT(1) DEFAULT 0,
    `program_id` INT DEFAULT NULL,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    `login_attempts` INT DEFAULT 0,
    `locked_until` TIMESTAMP NULL DEFAULT NULL,
    `password_reset_token` VARCHAR(255) DEFAULT NULL,
    `password_reset_expires` TIMESTAMP NULL DEFAULT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_email_users` (`email`),
    KEY `idx_role_users` (`role`),
    KEY `idx_active_users` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Programs/Departments table
DROP TABLE IF EXISTS `programs`;
CREATE TABLE `programs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `program_code` VARCHAR(20) NOT NULL UNIQUE,
    `program_name` VARCHAR(255) NOT NULL,
    `program_type` ENUM('UG','PG','Diploma','Certificate') NOT NULL,
    `department` VARCHAR(255) NOT NULL,
    `duration_years` DECIMAL(2,1) NOT NULL,
    `total_seats` INT DEFAULT 0,
    `application_start_date` DATE,
    `application_end_date` DATE,
    `program_admin_id` VARCHAR(36),
    `eligibility_criteria` TEXT,
    `fees_structure` TEXT,
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `display_order` INT DEFAULT 0,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`program_admin_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    KEY `idx_program_code` (`program_code`),
    KEY `idx_program_type` (`program_type`),
    KEY `idx_active_programs` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update users table to reference programs
ALTER TABLE `users` ADD FOREIGN KEY (`program_id`) REFERENCES `programs`(`id`) ON DELETE SET NULL;

-- Certificate types master table
DROP TABLE IF EXISTS `certificate_types`;
CREATE TABLE `certificate_types` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `file_types_allowed` VARCHAR(255) DEFAULT 'pdf,jpg,jpeg,png',
    `max_file_size_mb` INT DEFAULT 5,
    `is_required` TINYINT(1) DEFAULT 1,
    `display_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_active_certificates` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Program-specific certificate requirements
DROP TABLE IF EXISTS `program_certificate_requirements`;
CREATE TABLE `program_certificate_requirements` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `program_id` INT NOT NULL,
    `certificate_type_id` INT NOT NULL,
    `is_required` TINYINT(1) DEFAULT 1,
    `special_instructions` TEXT,
    `display_order` INT DEFAULT 0,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`program_id`) REFERENCES `programs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`certificate_type_id`) REFERENCES `certificate_types`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_program_certificate` (`program_id`, `certificate_type_id`),
    KEY `idx_program_requirements` (`program_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- File uploads table
DROP TABLE IF EXISTS `file_uploads`;
CREATE TABLE `file_uploads` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `uuid` VARCHAR(36) NOT NULL UNIQUE,
    `filename` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT NOT NULL,
    `mime_type` VARCHAR(100),
    `description` TEXT,
    `uploaded_by` VARCHAR(36),
    `upload_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_verified` TINYINT(1) DEFAULT 0,
    `verified_by` VARCHAR(36),
    `verified_at` TIMESTAMP NULL,
    INDEX `idx_uuid` (`uuid`),
    INDEX `idx_uploaded_by` (`uploaded_by`),
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Main applications table
DROP TABLE IF EXISTS `applications`;
CREATE TABLE `applications` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `application_number` VARCHAR(50) NOT NULL UNIQUE,
    `user_id` VARCHAR(36) NOT NULL,
    `program_id` INT NOT NULL,
    `academic_year` VARCHAR(10) NOT NULL,
    
    -- Application Status
    `status` ENUM('draft','submitted','under_review','approved','rejected','cancelled','frozen') DEFAULT 'draft',
    `submitted_at` TIMESTAMP NULL,
    `reviewed_by` VARCHAR(36),
    `reviewed_at` TIMESTAMP NULL,
    `approval_comments` TEXT,
    
    -- Personal Details
    `student_name` VARCHAR(255) NOT NULL,
    `father_name` VARCHAR(255) NOT NULL,
    `mother_name` VARCHAR(255) NOT NULL,
    `date_of_birth` DATE NOT NULL,
    `gender` ENUM('Male','Female','Other') NOT NULL,
    `aadhar_number` VARCHAR(12),
    `mobile_number` VARCHAR(15) NOT NULL,
    `parent_mobile` VARCHAR(15),
    `guardian_mobile` VARCHAR(15),
    `email` VARCHAR(255) NOT NULL,
    
    -- Address Details
    `present_door_no` VARCHAR(50),
    `present_street` VARCHAR(255),
    `present_village` VARCHAR(255),
    `present_mandal` VARCHAR(255),
    `present_district` VARCHAR(255),
    `present_pincode` VARCHAR(10),
    
    `permanent_door_no` VARCHAR(50),
    `permanent_street` VARCHAR(255),
    `permanent_village` VARCHAR(255),
    `permanent_mandal` VARCHAR(255),
    `permanent_district` VARCHAR(255),
    `permanent_pincode` VARCHAR(10),
    
    -- Additional Details
    `religion` VARCHAR(50),
    `caste` VARCHAR(50),
    `reservation_category` ENUM('OC','BC-A','BC-B','BC-C','BC-D','BC-E','SC','ST','EWS','PH') DEFAULT 'OC',
    `is_physically_handicapped` TINYINT(1) DEFAULT 0,
    `sadaram_number` VARCHAR(50),
    `identification_mark_1` VARCHAR(255),
    `identification_mark_2` VARCHAR(255),
    
    -- Special Categories
    `special_reservation` VARCHAR(500),
    `meeseva_caste_certificate` VARCHAR(50),
    `meeseva_income_certificate` VARCHAR(50),
    `ration_card_number` VARCHAR(50),
    
    -- Photos and signatures
    `photo_attachment_id` VARCHAR(36),
    `signature_attachment_id` VARCHAR(36),
    
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`program_id`) REFERENCES `programs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_application_number` (`application_number`),
    INDEX `idx_user_program` (`user_id`, `program_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_academic_year` (`academic_year`),
    INDEX `idx_program_status` (`program_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- NORMALIZED EDUCATION DETAILS TABLES
-- ==========================================

-- Education levels master table
DROP TABLE IF EXISTS `education_levels`;
CREATE TABLE `education_levels` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `level_code` VARCHAR(20) NOT NULL UNIQUE,
    `level_name` VARCHAR(100) NOT NULL,
    `display_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_active_levels` (`is_active`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Normalized education details table
DROP TABLE IF EXISTS `application_education_details`;
CREATE TABLE `application_education_details` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `application_id` INT NOT NULL,
    `education_level_id` INT NOT NULL,
    
    -- Common fields for all education levels
    `hall_ticket_number` VARCHAR(50),
    `institution_name` VARCHAR(500) NOT NULL,
    `board_university_name` VARCHAR(255),
    `course_name` VARCHAR(255),
    `specialization` VARCHAR(255),
    `medium_of_instruction` VARCHAR(50),
    `pass_year` YEAR NOT NULL,
    `passout_type` ENUM('Regular','Supplementary','Betterment','Compartment') DEFAULT 'Regular',
    
    -- Marks and percentage
    `marks_obtained` INT,
    `maximum_marks` INT,
    `percentage` DECIMAL(5,2),
    `cgpa` DECIMAL(4,2),
    `grade` VARCHAR(10),
    
    -- Subject-wise details (JSON for flexibility)
    `subject_marks` JSON,
    
    -- Additional fields
    `languages_studied` VARCHAR(255),
    `second_language` VARCHAR(50),
    `bridge_course` VARCHAR(255),
    `gap_year_reason` TEXT,
    
    -- Verification status
    `is_verified` TINYINT(1) DEFAULT 0,
    `verified_by` VARCHAR(36),
    `verified_at` TIMESTAMP NULL,
    `verification_remarks` TEXT,
    
    -- Meta fields
    `display_order` INT DEFAULT 0,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`education_level_id`) REFERENCES `education_levels`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    -- Ensure one record per education level per application
    UNIQUE KEY `unique_app_education_level` (`application_id`, `education_level_id`),
    
    INDEX `idx_application_education` (`application_id`),
    INDEX `idx_education_level` (`education_level_id`),
    INDEX `idx_pass_year` (`pass_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Study history for previous 7 years
DROP TABLE IF EXISTS `application_study_history`;
CREATE TABLE `application_study_history` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `application_id` INT NOT NULL,
    `class_standard` VARCHAR(10) NOT NULL,
    `place_of_study` VARCHAR(255),
    `school_college_name` VARCHAR(500),
    `academic_year` VARCHAR(10),
    `display_order` INT DEFAULT 0,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
    INDEX `idx_application_study` (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Application documents/certificates
DROP TABLE IF EXISTS `application_documents`;
CREATE TABLE `application_documents` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `application_id` INT NOT NULL,
    `certificate_type_id` INT NOT NULL,
    `file_upload_id` VARCHAR(36) NOT NULL,
    `document_name` VARCHAR(255),
    `remarks` TEXT,
    `is_verified` TINYINT(1) DEFAULT 0,
    `verified_by` VARCHAR(36),
    `verified_at` TIMESTAMP NULL,
    `verification_remarks` TEXT,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`certificate_type_id`) REFERENCES `certificate_types`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`file_upload_id`) REFERENCES `file_uploads`(`uuid`) ON DELETE CASCADE,
    FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    UNIQUE KEY `unique_app_certificate` (`application_id`, `certificate_type_id`),
    INDEX `idx_application_docs` (`application_id`),
    INDEX `idx_verification_status` (`is_verified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Application communication/messages
DROP TABLE IF EXISTS `application_communications`;
CREATE TABLE `application_communications` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `application_id` INT NOT NULL,
    `from_user_id` VARCHAR(36),
    `to_user_id` VARCHAR(36),
    `message_type` ENUM('query','response','system','notification') DEFAULT 'query',
    `subject` VARCHAR(255),
    `message` TEXT NOT NULL,
    `attachment_id` VARCHAR(36),
    `is_read` TINYINT(1) DEFAULT 0,
    `read_at` TIMESTAMP NULL,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`to_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_application_comm` (`application_id`),
    INDEX `idx_to_user_read` (`to_user_id`, `is_read`),
    INDEX `idx_message_type` (`message_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Application status history/audit trail
DROP TABLE IF EXISTS `application_status_history`;
CREATE TABLE `application_status_history` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `application_id` INT NOT NULL,
    `from_status` VARCHAR(50),
    `to_status` VARCHAR(50) NOT NULL,
    `changed_by` VARCHAR(36),
    `remarks` TEXT,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_application_status` (`application_id`),
    INDEX `idx_status_change` (`from_status`, `to_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System settings
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
    `id` INT PRIMARY KEY DEFAULT 1,
    `application_enabled` TINYINT(1) DEFAULT 1,
    `maintenance_mode` TINYINT(1) DEFAULT 0,
    `max_file_size_mb` INT DEFAULT 5,
    `allowed_file_types` VARCHAR(255) DEFAULT 'pdf,jpg,jpeg,png',
    `application_instructions` TEXT,
    `contact_email` VARCHAR(255),
    `contact_phone` VARCHAR(20),
    `academic_year_current` VARCHAR(10),
    `auto_approve_applications` TINYINT(1) DEFAULT 0,
    `email_notifications` TINYINT(1) DEFAULT 1,
    `sms_notifications` TINYINT(1) DEFAULT 0,
    `site_name` VARCHAR(255) DEFAULT 'SWARNANDHRA College',
    `site_logo` VARCHAR(255),
    `application_start_date` DATE,
    `application_end_date` DATE,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- ADDITIONAL USEFUL TABLES
-- ==========================================

-- Session management table
DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
    `id` VARCHAR(128) PRIMARY KEY,
    `user_id` VARCHAR(36),
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `payload` TEXT,
    `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_sessions` (`user_id`),
    INDEX `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification system
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` VARCHAR(36) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info','success','warning','danger') DEFAULT 'info',
    `is_read` TINYINT(1) DEFAULT 0,
    `action_url` VARCHAR(500),
    `expires_at` TIMESTAMP NULL,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_notifications` (`user_id`, `is_read`),
    INDEX `idx_notification_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System logs
DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE `system_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` VARCHAR(36),
    `action` VARCHAR(255) NOT NULL,
    `table_name` VARCHAR(100),
    `record_id` VARCHAR(50),
    `old_values` JSON,
    `new_values` JSON,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_logs` (`user_id`),
    INDEX `idx_action_logs` (`action`),
    INDEX `idx_table_logs` (`table_name`),
    INDEX `idx_date_logs` (`date_created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- SAMPLE DATA INSERTION
-- ==========================================

-- Insert default education levels
INSERT INTO `education_levels` (`level_code`, `level_name`, `display_order`) VALUES
('SSC', '10th / SSC / CBSE / ICSE', 1),
('INTER', 'Intermediate / 12th / Plus Two', 2),
('DIPLOMA', 'Diploma', 3),
('GRADUATION', 'Graduation / Bachelor Degree', 4),
('POST_GRADUATION', 'Post Graduation / Master Degree', 5),
('DOCTORATE', 'Doctorate / PhD', 6),
('OTHER', 'Other Qualification', 7);

-- Insert default certificate types
INSERT INTO `certificate_types` (`name`, `description`, `is_required`, `display_order`) VALUES
('10th Marks Memo', '10th/SSC marks memorandum', 1, 1),
('Intermediate Marks Memo', 'Intermediate marks memorandum', 1, 2),
('Intermediate TC', 'Intermediate Transfer Certificate', 1, 3),
('6th to Intermediate Study Certificate', 'Study certificate from 6th to Intermediate', 1, 4),
('Income Certificate / EWS Certificate', 'Income or EWS Certificate', 1, 5),
('Caste Certificate', 'Caste certificate (if applicable)', 0, 6),
('Aadhar Card Copy', 'Aadhar card xerox copy', 1, 7),
('Passport Size Photo', 'Recent passport size photograph', 1, 8),
('Student Signature', 'Signature of the student', 1, 9),
('Birth Certificate', 'Birth certificate or equivalent', 0, 10),
('Character Certificate', 'Character certificate from previous institution', 0, 11),
('Migration Certificate', 'Migration certificate (if applicable)', 0, 12),
('Graduation Marks Memo', 'Graduation marks memorandum (for PG courses)', 0, 13),
('Graduation Certificate', 'Graduation certificate (for PG courses)', 0, 14),
('Domicile Certificate', 'Domicile certificate', 0, 15);

-- Insert default programs
INSERT INTO `programs` (`program_code`, `program_name`, `program_type`, `department`, `duration_years`, `total_seats`, `eligibility_criteria`, `fees_structure`, `description`, `application_start_date`, `application_end_date`, `display_order`) VALUES
('BCA', 'Bachelor of Computer Applications', 'UG', 'Computer Applications', 3.0, 60, 'Intermediate (10+2) with Mathematics as one of the subjects', '₹45,000 per year', 'Three-year undergraduate program focusing on computer applications and programming', '2025-06-01', '2025-08-31', 1),
('BBA', 'Bachelor of Business Administration', 'UG', 'Management', 3.0, 60, 'Intermediate (10+2) in any stream with minimum 50% marks', '₹40,000 per year', 'Three-year undergraduate program in business administration and management', '2025-06-01', '2025-08-31', 2),
('MCA', 'Master of Computer Applications', 'PG', 'Computer Applications', 2.0, 30, 'Graduation with Mathematics/Computer Science with minimum 55% marks', '₹55,000 per year', 'Two-year postgraduate program in computer applications', '2025-06-01', '2025-08-31', 3),
('MBA', 'Master of Business Administration', 'PG', 'Management', 2.0, 60, 'Graduation in any discipline with minimum 50% marks', '₹60,000 per year', 'Two-year postgraduate program in business administration', '2025-06-01', '2025-08-31', 4),
('BTECH-CSE', 'B.Tech Computer Science Engineering', 'UG', 'Computer Science', 4.0, 120, 'Intermediate (10+2) with Physics, Chemistry, Mathematics and minimum 75% marks', '₹75,000 per year', 'Four-year undergraduate engineering program in computer science', '2025-06-01', '2025-08-31', 5),
('BTECH-ECE', 'B.Tech Electronics & Communication', 'UG', 'Electronics', 4.0, 60, 'Intermediate (10+2) with Physics, Chemistry, Mathematics and minimum 75% marks', '₹75,000 per year', 'Four-year undergraduate engineering program in electronics', '2025-06-01', '2025-08-31', 6),
('BCOM', 'Bachelor of Commerce', 'UG', 'Commerce', 3.0, 80, 'Intermediate (10+2) with Commerce/Mathematics with minimum 50% marks', '₹35,000 per year', 'Three-year undergraduate program in commerce and accounting', '2025-06-01', '2025-08-31', 7),
('BSC-IT', 'Bachelor of Science in Information Technology', 'UG', 'Information Technology', 3.0, 40, 'Intermediate (10+2) with Mathematics and Science subjects', '₹50,000 per year', 'Three-year undergraduate program in information technology', '2025-06-01', '2025-08-31', 8);

-- Insert default admin user (password: admin123)
INSERT INTO `users` (`id`, `email`, `password`, `role`, `is_active`, `email_verified`) VALUES
('550e8400-e29b-41d4-a716-446655440000', 'admin@swarnandhra.edu', '$2y$10$MCDwBCNp3At18GEW93KyyOsGQa1CNJzKlPJQp.0znWqu48LujZjjm', 'admin', 1, 1);

-- Insert sample program admins (password: admin123)
INSERT INTO `users` (`id`, `email`, `password`, `role`, `is_active`, `email_verified`, `program_id`) VALUES
('550e8400-e29b-41d4-a716-446655440001', 'bca.admin@swarnandhra.edu', '$2y$10$MCDwBCNp3At18GEW93KyyOsGQa1CNJzKlPJQp.0znWqu48LujZjjm', 'program_admin', 1, 1, 1),
('550e8400-e29b-41d4-a716-446655440002', 'mba.admin@swarnandhra.edu', '$2y$10$MCDwBCNp3At18GEW93KyyOsGQa1CNJzKlPJQp.0znWqu48LujZjjm', 'program_admin', 1, 1, 4),
('550e8400-e29b-41d4-a716-446655440003', 'btech.admin@swarnandhra.edu', '$2y$10$MCDwBCNp3At18GEW93KyyOsGQa1CNJzKlPJQp.0znWqu48LujZjjm', 'program_admin', 1, 1, 5);

-- Update programs with admin assignments
UPDATE `programs` SET `program_admin_id` = '550e8400-e29b-41d4-a716-446655440001' WHERE `id` = 1; -- BCA
UPDATE `programs` SET `program_admin_id` = '550e8400-e29b-41d4-a716-446655440001' WHERE `id` = 3; -- MCA
UPDATE `programs` SET `program_admin_id` = '550e8400-e29b-41d4-a716-446655440002' WHERE `id` = 2; -- BBA
UPDATE `programs` SET `program_admin_id` = '550e8400-e29b-41d4-a716-446655440002' WHERE `id` = 4; -- MBA
UPDATE `programs` SET `program_admin_id` = '550e8400-e29b-41d4-a716-446655440003' WHERE `id` = 5; -- BTECH-CSE
UPDATE `programs` SET `program_admin_id` = '550e8400-e29b-41d4-a716-446655440003' WHERE `id` = 6; -- BTECH-ECE

-- Insert sample students (password: student123)
INSERT INTO `users` (`id`, `email`, `password`, `role`, `is_active`, `email_verified`, `program_id`) VALUES
('550e8400-e29b-41d4-a716-446655440010', 'student1@example.com', '$2y$10$h8jKXY9fFm2L4nQ3rB6PzO.VsWxE1mN7cK5zI8qR0oA2sT9vU4wX6', 'student', 1, 1, 1),
('550e8400-e29b-41d4-a716-446655440011', 'student2@example.com', '$2y$10$h8jKXY9fFm2L4nQ3rB6PzO.VsWxE1mN7cK5zI8qR0oA2sT9vU4wX6', 'student', 1, 1, 2),
('550e8400-e29b-41d4-a716-446655440012', 'student3@example.com', '$2y$10$h8jKXY9fFm2L4nQ3rB6PzO.VsWxE1mN7cK5zI8qR0oA2sT9vU4wX6', 'student', 1, 1, 5),
('550e8400-e29b-41d4-a716-446655440013', 'student4@example.com', '$2y$10$h8jKXY9fFm2L4nQ3rB6PzO.VsWxE1mN7cK5zI8qR0oA2sT9vU4wX6', 'student', 1, 1, 1),
('550e8400-e29b-41d4-a716-446655440014', 'student5@example.com', '$2y$10$h8jKXY9fFm2L4nQ3rB6PzO.VsWxE1mN7cK5zI8qR0oA2sT9vU4wX6', 'student', 1, 1, 4);

-- Insert program certificate requirements for BCA
INSERT INTO `program_certificate_requirements` (`program_id`, `certificate_type_id`, `is_required`, `display_order`) VALUES
(1, 1, 1, 1),  -- 10th Marks Memo
(1, 2, 1, 2),  -- Intermediate Marks Memo
(1, 3, 1, 3),  -- Intermediate TC
(1, 4, 1, 4),  -- Study Certificate
(1, 5, 1, 5),  -- Income Certificate
(1, 7, 1, 6),  -- Aadhar Card
(1, 8, 1, 7),  -- Photo
(1, 9, 1, 8);  -- Signature

-- Insert program certificate requirements for BBA
INSERT INTO `program_certificate_requirements` (`program_id`, `certificate_type_id`, `is_required`, `display_order`) VALUES
(2, 1, 1, 1),  -- 10th Marks Memo
(2, 2, 1, 2),  -- Intermediate Marks Memo
(2, 3, 1, 3),  -- Intermediate TC
(2, 4, 1, 4),  -- Study Certificate
(2, 5, 1, 5),  -- Income Certificate
(2, 7, 1, 6),  -- Aadhar Card
(2, 8, 1, 7),  -- Photo
(2, 9, 1, 8);  -- Signature

-- Insert program certificate requirements for MCA
INSERT INTO `program_certificate_requirements` (`program_id`, `certificate_type_id`, `is_required`, `display_order`) VALUES
(3, 1, 1, 1),   -- 10th Marks Memo
(3, 2, 1, 2),   -- Intermediate Marks Memo
(3, 3, 1, 3),   -- Intermediate TC
(3, 13, 1, 4),  -- Graduation Marks Memo
(3, 14, 1, 5),  -- Graduation Certificate
(3, 12, 1, 6),  -- Migration Certificate
(3, 5, 1, 7),   -- Income Certificate
(3, 7, 1, 8),   -- Aadhar Card
(3, 8, 1, 9),   -- Photo
(3, 9, 1, 10);  -- Signature

-- Insert program certificate requirements for MBA
INSERT INTO `program_certificate_requirements` (`program_id`, `certificate_type_id`, `is_required`, `display_order`) VALUES
(4, 1, 1, 1),   -- 10th Marks Memo
(4, 2, 1, 2),   -- Intermediate Marks Memo
(4, 13, 1, 3),  -- Graduation Marks Memo
(4, 14, 1, 4),  -- Graduation Certificate
(4, 12, 1, 5),  -- Migration Certificate
(4, 5, 1, 6),   -- Income Certificate
(4, 7, 1, 7),   -- Aadhar Card
(4, 8, 1, 8),   -- Photo
(4, 9, 1, 9);   -- Signature

-- Insert program certificate requirements for B.Tech CSE
INSERT INTO `program_certificate_requirements` (`program_id`, `certificate_type_id`, `is_required`, `display_order`) VALUES
(5, 1, 1, 1),  -- 10th Marks Memo
(5, 2, 1, 2),  -- Intermediate Marks Memo
(5, 3, 1, 3),  -- Intermediate TC
(5, 4, 1, 4),  -- Study Certificate
(5, 5, 1, 5),  -- Income Certificate
(5, 6, 0, 6),  -- Caste Certificate (optional)
(5, 7, 1, 7),  -- Aadhar Card
(5, 8, 1, 8),  -- Photo
(5, 9, 1, 9),  -- Signature
(5, 11, 1, 10); -- Character Certificate

-- Insert sample applications
INSERT INTO `applications` (`application_number`, `user_id`, `program_id`, `academic_year`, `status`, `student_name`, `father_name`, `mother_name`, `date_of_birth`, `gender`, `mobile_number`, `email`, `present_village`, `permanent_village`, `religion`, `caste`, `reservation_category`, `submitted_at`) VALUES
('BCA20250001', '550e8400-e29b-41d4-a716-446655440010', 1, '2025-26', 'submitted', 'Rajesh Kumar', 'Ramesh Kumar', 'Sunita Devi', '2005-08-15', 'Male', '9876543210', 'student1@example.com', 'Vijayawada', 'Vijayawada', 'Hindu', 'Kamma', 'OC', '2025-06-15 10:30:00'),
('BBA20250001', '550e8400-e29b-41d4-a716-446655440011', 2, '2025-26', 'approved', 'Priya Sharma', 'Suresh Sharma', 'Lakshmi Sharma', '2004-12-22', 'Female', '9765432109', 'student2@example.com', 'Guntur', 'Guntur', 'Hindu', 'Reddy', 'BC-A', '2025-06-10 14:20:00'),
('BTECH20250001', '550e8400-e29b-41d4-a716-446655440012', 5, '2025-26', 'under_review', 'Arjun Reddy', 'Venkata Reddy', 'Padmavathi', '2005-03-10', 'Male', '9654321098', 'student3@example.com', 'Hyderabad', 'Nellore', 'Hindu', 'Reddy', 'BC-B', '2025-06-12 09:15:00');

-- Insert sample education details
INSERT INTO `application_education_details` (`application_id`, `education_level_id`, `hall_ticket_number`, `institution_name`, `board_university_name`, `course_name`, `pass_year`, `marks_obtained`, `maximum_marks`, `percentage`) VALUES
(1, 1, '1234567890', 'ABC High School', 'AP Board', 'SSC', 2021, 520, 600, 86.67),
(1, 2, '0987654321', 'XYZ Junior College', 'AP Board', 'MPC', 2023, 950, 1000, 95.00),
(2, 1, '2345678901', 'DEF High School', 'CBSE', 'Class X', 2020, 456, 500, 91.20),
(2, 2, '1098765432', 'GHI Junior College', 'CBSE', 'Commerce', 2022, 475, 500, 95.00),
(3, 1, '3456789012', 'JKL High School', 'TS Board', 'SSC', 2021, 540, 600, 90.00),
(3, 2, '2109876543', 'MNO Junior College', 'TS Board', 'BiPC', 2023, 980, 1000, 98.00);

-- Insert application status history
INSERT INTO `application_status_history` (`application_id`, `from_status`, `to_status`, `changed_by`, `remarks`) VALUES
(1, NULL, 'draft', '550e8400-e29b-41d4-a716-446655440010', 'Application created'),
(1, 'draft', 'submitted', '550e8400-e29b-41d4-a716-446655440010', 'Application submitted by student'),
(2, NULL, 'draft', '550e8400-e29b-41d4-a716-446655440011', 'Application created'),
(2, 'draft', 'submitted', '550e8400-e29b-41d4-a716-446655440011', 'Application submitted by student'),
(2, 'submitted', 'approved', '550e8400-e29b-41d4-a716-446655440002', 'Application approved after document verification'),
(3, NULL, 'draft', '550e8400-e29b-41d4-a716-446655440012', 'Application created'),
(3, 'draft', 'submitted', '550e8400-e29b-41d4-a716-446655440012', 'Application submitted by student'),
(3, 'submitted', 'under_review', '550e8400-e29b-41d4-a716-446655440003', 'Application under review');

-- Insert default system settings
INSERT INTO `system_settings` (`application_enabled`, `academic_year_current`, `contact_email`, `contact_phone`, `application_instructions`, `site_name`, `application_start_date`, `application_end_date`) VALUES
(1, '2025-26', 'admissions@swarnandhra.edu', '+91-9876543210', 
'Welcome to SWARNANDHRA College Online Admission System. Please fill all required details carefully and upload all necessary documents in PDF format only. Ensure all information is accurate as changes may not be possible after submission.', 
'SWARNANDHRA College', '2025-06-01', '2025-08-31');

-- Insert sample notifications
INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `action_url`) VALUES
('550e8400-e29b-41d4-a716-446655440010', 'Application Submitted', 'Your application has been submitted successfully. Application number: BCA20250001', 'success', '/student/status.php'),
('550e8400-e29b-41d4-a716-446655440011', 'Application Approved', 'Congratulations! Your application has been approved. Please check your email for further instructions.', 'success', '/student/status.php'),
('550e8400-e29b-41d4-a716-446655440012', 'Application Under Review', 'Your application is currently under review. You will be notified once the review is complete.', 'info', '/student/status.php');

-- ==========================================
-- VIEWS FOR REPORTING AND DASHBOARD
-- ==========================================

-- Application summary view
CREATE OR REPLACE VIEW `application_summary_view` AS
SELECT 
    a.id,
    a.application_number,
    a.student_name,
    a.mobile_number,
    a.email,
    a.status,
    a.submitted_at,
    a.date_created,
    p.program_name,
    p.program_code,
    p.program_type,
    p.department,
    COUNT(ad.id) as documents_uploaded,
    COUNT(CASE WHEN ad.is_verified = 1 THEN 1 END) as documents_verified,
    pcr.required_documents,
    u.email as user_email
FROM `applications` a
LEFT JOIN `programs` p ON a.program_id = p.id
LEFT JOIN `users` u ON a.user_id = u.id
LEFT JOIN `application_documents` ad ON a.id = ad.application_id
LEFT JOIN (
    SELECT program_id, COUNT(*) as required_documents 
    FROM `program_certificate_requirements` 
    WHERE is_required = 1 
    GROUP BY program_id
) pcr ON p.id = pcr.program_id
GROUP BY a.id;

-- Program-wise application statistics
CREATE OR REPLACE VIEW `program_statistics_view` AS
SELECT 
    p.id,
    p.program_code,
    p.program_name,
    p.program_type,
    p.department,
    p.total_seats,
    COUNT(CASE WHEN a.status = 'draft' THEN 1 END) as draft_applications,
    COUNT(CASE WHEN a.status = 'submitted' THEN 1 END) as submitted_applications,
    COUNT(CASE WHEN a.status = 'under_review' THEN 1 END) as under_review_applications,
    COUNT(CASE WHEN a.status = 'approved' THEN 1 END) as approved_applications,
    COUNT(CASE WHEN a.status = 'rejected' THEN 1 END) as rejected_applications,
    COUNT(CASE WHEN a.status = 'frozen' THEN 1 END) as frozen_applications,
    COUNT(a.id) as total_applications,
    ROUND((COUNT(CASE WHEN a.status = 'approved' THEN 1 END) / p.total_seats) * 100, 2) as seat_fill_percentage
FROM `programs` p
LEFT JOIN `applications` a ON p.id = a.program_id AND a.academic_year = '2025-26'
WHERE p.is_active = 1
GROUP BY p.id
ORDER BY p.display_order ASC, p.program_name ASC;

-- User activity view
CREATE OR REPLACE VIEW `user_activity_view` AS
SELECT 
    u.id,
    u.email,
    u.role,
    u.is_active,
    u.last_login,
    u.date_created,
    p.program_name,
    COUNT(a.id) as application_count,
    MAX(a.date_updated) as last_application_activity
FROM `users` u
LEFT JOIN `programs` p ON u.program_id = p.id
LEFT JOIN `applications` a ON u.id = a.user_id
GROUP BY u.id
ORDER BY u.last_login DESC;

-- Document verification status view
CREATE OR REPLACE VIEW `document_verification_view` AS
SELECT 
    a.id as application_id,
    a.application_number,
    a.student_name,
    p.program_name,
    ct.name as certificate_name,
    ad.is_verified,
    ad.verified_by,
    ad.verified_at,
    ad.verification_remarks,
    fu.original_name as file_name,
    fu.file_size,
    fu.upload_date
FROM `applications` a
JOIN `programs` p ON a.program_id = p.id
JOIN `program_certificate_requirements` pcr ON p.id = pcr.program_id
JOIN `certificate_types` ct ON pcr.certificate_type_id = ct.id
LEFT JOIN `application_documents` ad ON a.id = ad.application_id AND ct.id = ad.certificate_type_id
LEFT JOIN `file_uploads` fu ON ad.file_upload_id = fu.uuid
WHERE pcr.is_required = 1
ORDER BY a.application_number, pcr.display_order;

-- ==========================================
-- STORED PROCEDURES FOR COMMON OPERATIONS
-- ==========================================

DELIMITER $$

-- Procedure to get application statistics for dashboard
CREATE PROCEDURE `GetApplicationStatistics`(
    IN p_program_id INT,
    IN p_academic_year VARCHAR(10)
)
BEGIN
    IF p_program_id IS NULL THEN
        SELECT 
            COUNT(*) as total_applications,
            COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_count,
            COUNT(CASE WHEN status = 'submitted' THEN 1 END) as submitted_count,
            COUNT(CASE WHEN status = 'under_review' THEN 1 END) as under_review_count,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
            COUNT(CASE WHEN status = 'frozen' THEN 1 END) as frozen_count
        FROM applications 
        WHERE academic_year = p_academic_year;
    ELSE
        SELECT 
            COUNT(*) as total_applications,
            COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_count,
            COUNT(CASE WHEN status = 'submitted' THEN 1 END) as submitted_count,
            COUNT(CASE WHEN status = 'under_review' THEN 1 END) as under_review_count,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
            COUNT(CASE WHEN status = 'frozen' THEN 1 END) as frozen_count
        FROM applications 
        WHERE program_id = p_program_id AND academic_year = p_academic_year;
    END IF;
END$$

-- Procedure to check application completeness
CREATE PROCEDURE `CheckApplicationCompleteness`(
    IN p_application_id INT
)
BEGIN
    DECLARE required_docs INT DEFAULT 0;
    DECLARE uploaded_docs INT DEFAULT 0;
    DECLARE missing_fields INT DEFAULT 0;
    
    -- Check required documents
    SELECT COUNT(*) INTO required_docs
    FROM program_certificate_requirements pcr
    JOIN applications a ON pcr.program_id = a.program_id
    WHERE a.id = p_application_id AND pcr.is_required = 1;
    
    -- Check uploaded documents
    SELECT COUNT(*) INTO uploaded_docs
    FROM application_documents ad
    WHERE ad.application_id = p_application_id;
    
    -- Check missing required fields
    SELECT COUNT(*) INTO missing_fields
    FROM applications 
    WHERE id = p_application_id 
    AND (student_name IS NULL OR student_name = ''
         OR father_name IS NULL OR father_name = ''
         OR mother_name IS NULL OR mother_name = ''
         OR date_of_birth IS NULL
         OR gender IS NULL OR gender = ''
         OR mobile_number IS NULL OR mobile_number = ''
         OR email IS NULL OR email = '');
    
    SELECT 
        required_docs,
        uploaded_docs,
        missing_fields,
        CASE 
            WHEN missing_fields = 0 AND uploaded_docs >= required_docs THEN 1 
            ELSE 0 
        END as is_complete;
END$$

-- Procedure to generate application number
CREATE PROCEDURE `GenerateApplicationNumber`(
    IN p_program_id INT,
    OUT p_application_number VARCHAR(50)
)
BEGIN
    DECLARE program_code VARCHAR(20);
    DECLARE current_year YEAR;
    DECLARE sequence_num INT;
    
    SELECT program_code INTO program_code FROM programs WHERE id = p_program_id;
    SET current_year = YEAR(CURDATE());
    
    SELECT COALESCE(MAX(CAST(RIGHT(application_number, 4) AS UNSIGNED)), 0) + 1 INTO sequence_num
    FROM applications 
    WHERE program_id = p_program_id 
    AND YEAR(date_created) = current_year;
    
    SET p_application_number = CONCAT(program_code, current_year, LPAD(sequence_num, 4, '0'));
END$$

DELIMITER ;

-- ==========================================
-- INDEXES FOR BETTER PERFORMANCE
-- ==========================================

-- Additional performance indexes
CREATE INDEX `idx_applications_status_program` ON `applications`(`status`, `program_id`);
CREATE INDEX `idx_applications_academic_year_status` ON `applications`(`academic_year`, `status`);
CREATE INDEX `idx_documents_verification_status` ON `application_documents`(`is_verified`, `verified_at`);
CREATE INDEX `idx_communications_unread` ON `application_communications`(`to_user_id`, `is_read`);
CREATE INDEX `idx_users_role_active` ON `users`(`role`, `is_active`);
CREATE INDEX `idx_programs_type_active` ON `programs`(`program_type`, `is_active`);
CREATE INDEX `idx_education_app_level` ON `application_education_details`(`application_id`, `education_level_id`);

-- ==========================================
-- TRIGGERS FOR AUDIT AND AUTOMATION
-- ==========================================

DELIMITER $$

-- Trigger to log user activities
CREATE TRIGGER `user_activity_log` 
AFTER UPDATE ON `users` 
FOR EACH ROW 
BEGIN
    IF NEW.last_login != OLD.last_login THEN
        INSERT INTO `system_logs` (`user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`) 
        VALUES (NEW.id, 'LOGIN', 'users', NEW.id, 
                JSON_OBJECT('last_login', OLD.last_login), 
                JSON_OBJECT('last_login', NEW.last_login));
    END IF;
END$$

-- Trigger to create notification on application status change
CREATE TRIGGER `application_status_notification` 
AFTER UPDATE ON `applications` 
FOR EACH ROW 
BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`) 
        VALUES (NEW.user_id, 
                CONCAT('Application Status Updated'),
                CONCAT('Your application status has been changed to: ', UPPER(REPLACE(NEW.status, '_', ' '))),
                CASE NEW.status 
                    WHEN 'approved' THEN 'success'
                    WHEN 'rejected' THEN 'danger'
                    ELSE 'info'
                END);
    END IF;
END$$

DELIMITER ;

-- ==========================================
-- FINALIZE SETUP
-- ==========================================

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- ==========================================
-- VERIFICATION QUERIES
-- ==========================================

-- Check table creation
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS "Size (MB)"
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'student_application_db' 
ORDER BY TABLE_NAME;

-- Check sample data
SELECT 'Users' as Table_Name, COUNT(*) as Record_Count FROM users
UNION ALL
SELECT 'Programs', COUNT(*) FROM programs
UNION ALL
SELECT 'Applications', COUNT(*) FROM applications
UNION ALL
SELECT 'Certificate Types', COUNT(*) FROM certificate_types
UNION ALL
SELECT 'Education Levels', COUNT(*) FROM education_levels;

-- ==========================================
-- DEFAULT LOGIN CREDENTIALS
-- ==========================================
/*
ADMIN LOGIN:
Email: admin@swarnandhra.edu
Password: admin123

PROGRAM ADMIN LOGINS:
Email: bca.admin@swarnandhra.edu
Password: admin123

Email: mba.admin@swarnandhra.edu  
Password: admin123

Email: btech.admin@swarnandhra.edu
Password: admin123

STUDENT LOGINS:
Email: student1@example.com
Password: student123

Email: student2@example.com
Password: student123

Email: student3@example.com
Password: student123
*/

-- ==========================================
-- END OF DATABASE SETUP
-- ==========================================