-- ============================================
-- WOOBLE JOB APPLICATION - DATABASE SCHEMA
-- Database: wooble-job
-- ============================================

CREATE DATABASE IF NOT EXISTS `wooble-job` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `wooble-job`;

-- TABLE: users
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(100) NOT NULL,
    `email`      VARCHAR(150) NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL,
    `role` ENUM('candidate','employer','company') NOT NULL DEFAULT 'candidate',
    `phone`      VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- TABLE: employer_profiles
-- Extra info for employers
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `employer_profiles` (
    `id`           INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`      INT(11) UNSIGNED NOT NULL UNIQUE,
    `company_name` VARCHAR(150) NOT NULL,
    `website`      VARCHAR(255) DEFAULT NULL,
    `industry`     VARCHAR(100) DEFAULT NULL,
    `description`  TEXT DEFAULT NULL,
    `location`     VARCHAR(150) DEFAULT NULL,
    `logo_path`    VARCHAR(255) DEFAULT NULL,
    `status`       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- TABLE: notifications
-- System notifications for all users
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT(11) UNSIGNED NOT NULL,
    `title`      VARCHAR(255) NOT NULL,
    `message`    TEXT NOT NULL,
    `type`       ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABLE: jobs
CREATE TABLE IF NOT EXISTS `jobs` (
    `id`           INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `posted_by`    INT(11) UNSIGNED NOT NULL,
    `title`        VARCHAR(150) NOT NULL,
    `company`      VARCHAR(100) NOT NULL,
    `location`     VARCHAR(100) NOT NULL,
    `job_type`     ENUM('full-time','part-time','remote','contract') NOT NULL,
    `salary_min`   DECIMAL(10,2) DEFAULT NULL,
    `salary_max`   DECIMAL(10,2) DEFAULT NULL,
    `description`  TEXT NOT NULL,
    `requirements` TEXT DEFAULT NULL,
    `status`       ENUM('active','closed') NOT NULL DEFAULT 'active',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`posted_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- TABLE: applications
CREATE TABLE IF NOT EXISTS `applications` (
    `id`           INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `job_id`       INT(11) UNSIGNED NOT NULL,
    `candidate_id` INT(11) UNSIGNED NOT NULL,
    `cover_letter` TEXT DEFAULT NULL,
    `resume_path`  VARCHAR(255) DEFAULT NULL,
    `status`       ENUM('pending','shortlisted','invited','rejected') NOT NULL DEFAULT 'pending',
    `admin_note`   TEXT DEFAULT NULL,
    `applied_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_application` (`job_id`, `candidate_id`),
    FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`candidate_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- TABLE: invitations
CREATE TABLE IF NOT EXISTS `invitations` (
    `id`             INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `application_id` INT(11) UNSIGNED NOT NULL,
    `candidate_id`   INT(11) UNSIGNED NOT NULL,
    `job_id`         INT(11) UNSIGNED NOT NULL,
    `interview_date` DATETIME DEFAULT NULL,
    `message`        TEXT DEFAULT NULL,
    `sent_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`candidate_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;