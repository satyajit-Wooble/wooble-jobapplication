-- ============================================
-- WOOBLE JOB APPLICATION — AUTO DATABASE INIT
-- This runs automatically when MySQL container
-- starts for the first time
-- ============================================

CREATE DATABASE IF NOT EXISTS `wooble-job`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `wooble-job`;

-- ─────────────────────────────────────────
-- TABLE: users
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(100) NOT NULL,
    `email`      VARCHAR(150) NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL,
    `role`       ENUM('admin','candidate') NOT NULL DEFAULT 'candidate',
    `phone`      VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- TABLE: jobs
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `jobs` (
    `id`           INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_id`     INT(11) UNSIGNED NOT NULL,
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
    FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- TABLE: applications
-- ─────────────────────────────────────────
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
    FOREIGN KEY (`job_id`)       REFERENCES `jobs`(`id`)  ON DELETE CASCADE,
    FOREIGN KEY (`candidate_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- TABLE: invitations
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `invitations` (
    `id`             INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `application_id` INT(11) UNSIGNED NOT NULL,
    `candidate_id`   INT(11) UNSIGNED NOT NULL,
    `job_id`         INT(11) UNSIGNED NOT NULL,
    `interview_date` DATETIME DEFAULT NULL,
    `message`        TEXT DEFAULT NULL,
    `sent_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`candidate_id`)   REFERENCES `users`(`id`)        ON DELETE CASCADE,
    FOREIGN KEY (`job_id`)         REFERENCES `jobs`(`id`)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- DEFAULT ADMIN USER
-- Password: admin123
-- ─────────────────────────────────────────
INSERT IGNORE INTO `users`
    (`name`, `email`, `password`, `role`, `created_at`)
VALUES (
    'Super Admin',
    'admin@wooble.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    NOW()
);

-- ─────────────────────────────────────────
-- SAMPLE JOBS
-- ─────────────────────────────────────────
INSERT IGNORE INTO `jobs`
    (`admin_id`, `title`, `company`, `location`, `job_type`,
     `description`, `requirements`, `salary_min`, `salary_max`, `status`)
VALUES
(1,
 'PHP Developer',
 'Wooble Tech',
 'Bhubaneswar, Odisha',
 'full-time',
 'We are looking for an experienced PHP Developer to join our growing team. You will be responsible for building and maintaining web applications.',
 'Min 2 years PHP experience, MySQL knowledge, REST API development',
 25000, 50000, 'active'),

(1,
 'Frontend Developer',
 'Wooble Tech',
 'Remote',
 'remote',
 'We need a skilled Frontend Developer with expertise in modern JavaScript frameworks and responsive design.',
 'HTML, CSS, JavaScript, Bootstrap 5, React or Vue experience preferred',
 20000, 45000, 'active'),

(1,
 'Full Stack Developer',
 'Wooble Tech',
 'Bhubaneswar, Odisha',
 'full-time',
 'Looking for a Full Stack Developer who can work on both frontend and backend technologies.',
 'PHP, MySQL, JavaScript, REST APIs, Git knowledge required',
 35000, 70000, 'active');