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
    `role` ENUM('candidate','employer','company') NOT NULL DEFAULT 'candidate',
    `phone`      VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- TABLE: employer_profiles
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

-- ─────────────────────────────────────────
-- TABLE: jobs
-- ─────────────────────────────────────────
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
-- DEFAULT USERS
-- Password for all: admin123
-- ─────────────────────────────────────────
-- ── Default Company (Super Admin) ─────────
INSERT IGNORE INTO `users`
    (name, email, password, role, created_at)
VALUES (
    'Wooble',
    'company@wooble.com',
    '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B9bd/C2',
    'company',
    NOW()
);

-- ── Default Employer ──────────────────────
INSERT IGNORE INTO `users`
    (name, email, password, role, created_at)
VALUES (
    'Wooble Tech HR',
    'employer@wooble.com',
    '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B9bd/C2',
    'employer',
    NOW()
);

-- ── Default Employer Profile ──────────────
INSERT IGNORE INTO `employer_profiles`
    (user_id, company_name, website, industry,
     location, description, status)
VALUES (
    2,
    'Wooble Tech',
    'https://wooble.org',
    'Technology',
    'Bhubaneswar, Odisha',
    'We build proof of work platform for careers.',
    'approved'
);

-- ── Sample Jobs (posted by employer) ──────
INSERT IGNORE INTO `jobs`
    (posted_by, title, company, location, job_type,
     description, requirements, salary_min, salary_max, status)
VALUES
(2, 'PHP Developer',      'Wooble Tech', 'Bhubaneswar', 'full-time',
 'Looking for PHP Developer.',
 'Min 2 years PHP, MySQL',       25000, 50000, 'active'),

(2, 'Frontend Developer', 'Wooble Tech', 'Remote',       'remote',
 'Looking for Frontend Developer.',
 'HTML, CSS, JavaScript, Bootstrap', 20000, 45000, 'active'),

(2, 'Full Stack Developer','Wooble Tech','Bhubaneswar',  'full-time',
 'Looking for Full Stack Developer.',
 'PHP, MySQL, JavaScript, REST APIs', 35000, 70000, 'active');