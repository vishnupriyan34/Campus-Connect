-- Campus Connect Database Schema

CREATE DATABASE IF NOT EXISTS `campus_connect`;
USE `campus_connect`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('student', 'staff', 'admin') NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Students Table
CREATE TABLE IF NOT EXISTS `students` (
  `user_id` INT PRIMARY KEY,
  `department` VARCHAR(50) NOT NULL,
  `cgpa` DECIMAL(4,2) NOT NULL,
  `backlog_count` INT DEFAULT 0,
  `resume_path` VARCHAR(255) DEFAULT NULL,
  `skills` TEXT DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Companies Table
CREATE TABLE IF NOT EXISTS `companies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `package_range` VARCHAR(50) DEFAULT NULL,
  `contact_name` VARCHAR(100) DEFAULT NULL,
  `contact_email` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Drives Table
CREATE TABLE IF NOT EXISTS `drives` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `job_description` TEXT DEFAULT NULL,
  `eligibility_cgpa` DECIMAL(4,2) DEFAULT 0.00,
  `eligibility_branch` VARCHAR(255) DEFAULT 'All', -- Comma-separated or 'All'
  `eligibility_max_backlogs` INT DEFAULT 0,
  `test_date` DATETIME DEFAULT NULL,
  `interview_date` DATETIME DEFAULT NULL,
  `status` ENUM('open', 'closed') DEFAULT 'open',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Applications Table
CREATE TABLE IF NOT EXISTS `applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `drive_id` INT NOT NULL,
  `status` ENUM('Applied', 'Shortlisted', 'Selected', 'Rejected') DEFAULT 'Applied',
  `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `match_score` INT DEFAULT 0,
  `match_missing_skills` TEXT DEFAULT NULL,
  UNIQUE KEY `unique_student_drive` (`student_id`, `drive_id`),
  FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`drive_id`) REFERENCES `drives` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Offer Letters Table
CREATE TABLE IF NOT EXISTS `offer_letters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `drive_id` INT NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `uploaded_by` INT DEFAULT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`drive_id`) REFERENCES `drives` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL, -- NULL indicates global broadcast
  `message` TEXT NOT NULL,
  `read_status` TINYINT(1) DEFAULT 0, -- 0 = unread, 1 = read
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Verification Requests Table
CREATE TABLE IF NOT EXISTS `verification_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `field_name` ENUM('cgpa', 'backlog_count') NOT NULL,
  `old_value` VARCHAR(50) NOT NULL,
  `new_value` VARCHAR(50) NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `reason` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `resolved_by` INT DEFAULT NULL,
  `resolved_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Audit Logs Table
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `actor_id` INT DEFAULT NULL,
  `action` VARCHAR(255) NOT NULL,
  `target` VARCHAR(255) NOT NULL,
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
