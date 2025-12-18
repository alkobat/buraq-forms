-- ===================================================================
-- BuraqForms - Full Database Rebuild (from scratch)
-- Database: buraq_forms
-- Charset : utf8mb4 / utf8mb4_unicode_ci
-- ===================================================================

-- 1) Drop + recreate database
DROP DATABASE IF EXISTS buraq_forms;

CREATE DATABASE IF NOT EXISTS buraq_forms
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE buraq_forms;

-- 2) Create tables
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `file_download_logs`;
DROP TABLE IF EXISTS `submission_answers`;
DROP TABLE IF EXISTS `form_submissions`;
DROP TABLE IF EXISTS `form_fields`;
DROP TABLE IF EXISTS `form_departments`;
DROP TABLE IF EXISTS `forms`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `system_settings`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `admins` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) DEFAULT 'admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`email`),
    INDEX (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `departments` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT,
    `manager_id` INT,
    `is_active` BOOLEAN DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`manager_id`) REFERENCES `admins`(`id`) ON DELETE SET NULL,
    INDEX (`is_active`),
    INDEX (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `forms` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `slug` VARCHAR(255) UNIQUE NOT NULL,
    `created_by` INT NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `allow_multiple_submissions` BOOLEAN DEFAULT 1,
    `show_department_field` BOOLEAN DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
    INDEX (`slug`),
    INDEX (`status`),
    INDEX (`created_by`),
    INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional (used by the application): link forms to departments
CREATE TABLE `form_departments` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `form_id` INT NOT NULL,
    `department_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_form_department` (`form_id`, `department_id`),
    FOREIGN KEY (`form_id`) REFERENCES `forms`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    INDEX (`form_id`),
    INDEX (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `form_fields` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `form_id` INT NOT NULL,
    `field_type` ENUM(
        'text', 'email', 'number', 'date', 'textarea',
        'select', 'radio', 'checkbox', 'file', 'repeater', 'date_range'
    ) NOT NULL,
    `label` VARCHAR(255) NOT NULL,
    `placeholder` VARCHAR(255),
    `is_required` BOOLEAN DEFAULT 0,
    `is_active` BOOLEAN DEFAULT 1,
    `field_options` JSON,
    `source_type` ENUM('static', 'departments', 'custom') DEFAULT 'static',
    `parent_field_id` INT,
    `field_key` VARCHAR(100),
    `order_index` INT DEFAULT 0,
    `validation_rules` JSON,
    `helper_text` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`form_id`) REFERENCES `forms`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_field_id`) REFERENCES `form_fields`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_field_per_form` (`form_id`, `field_key`),
    INDEX (`form_id`),
    INDEX (`field_type`),
    INDEX (`parent_field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `form_submissions` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `form_id` INT NOT NULL,
    `submitted_by` VARCHAR(255),
    `department_id` INT,
    `status` ENUM('pending', 'completed', 'archived') DEFAULT 'completed',
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45),
    `reference_code` VARCHAR(50) UNIQUE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`form_id`) REFERENCES `forms`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
    INDEX (`form_id`),
    INDEX (`department_id`),
    INDEX (`reference_code`),
    INDEX (`status`),
    INDEX (`submitted_at`),
    INDEX (`submitted_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `submission_answers` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `submission_id` INT NOT NULL,
    `field_id` INT NOT NULL,
    `answer` LONGTEXT,
    `file_path` VARCHAR(500),
    `file_name` VARCHAR(255),
    `file_size` INT,
    `repeat_index` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`submission_id`) REFERENCES `form_submissions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`field_id`) REFERENCES `form_fields`(`id`) ON DELETE CASCADE,
    INDEX (`submission_id`),
    INDEX (`field_id`),
    INDEX (`repeat_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_settings` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `file_download_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `submission_id` INT NOT NULL,
    `field_id` INT NULL,
    `file_name` VARCHAR(255),
    `downloaded_by` VARCHAR(255),
    `ip_address` VARCHAR(45),
    `downloaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`submission_id`) REFERENCES `form_submissions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`field_id`) REFERENCES `form_fields`(`id`) ON DELETE SET NULL,
    INDEX (`submission_id`),
    INDEX (`downloaded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Seed data
INSERT IGNORE INTO `admins` (`id`, `name`, `email`, `password`, `role`) VALUES
(1, 'System Administrator', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT INTO `departments` (`name`, `description`, `is_active`) VALUES
('الموارد البشرية', 'قسم الموارد البشرية', 1),
('تكنولوجيا المعلومات', 'قسم تكنولوجيا المعلومات', 1),
('المبيعات', 'قسم المبيعات', 1),
('التطوير', 'قسم التطوير والمشاريع', 1)
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`), `is_active` = VALUES(`is_active`);

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('forms_allowed_mime', '["image/jpeg", "image/png", "image/gif", "application/pdf", "application/msword", "application/vnd.ms-excel"]'),
('forms_max_upload_mb', '10'),
('pagination_limit', '20'),
('items_per_page', '20')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`), `updated_at` = CURRENT_TIMESTAMP;

-- 4) Verify
SHOW TABLES;
SELECT * FROM departments;
SELECT setting_key, setting_value FROM system_settings;
