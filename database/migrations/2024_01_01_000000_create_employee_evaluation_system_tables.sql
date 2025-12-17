-- ===================================================================
-- Employee Evaluation System Database Migration
-- Created: 2024-01-01
-- Description: Complete database structure for dynamic forms system
-- ===================================================================

-- Enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ===================================================================
-- TABLE: admins
-- Description: System administrators and managers
-- ===================================================================
CREATE TABLE IF NOT EXISTS `admins` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL COMMENT 'Admin full name',
    `email` varchar(255) NOT NULL COMMENT 'Admin email address',
    `password` varchar(255) NOT NULL COMMENT 'Encrypted password',
    `role` enum('super_admin','admin','manager') NOT NULL DEFAULT 'manager' COMMENT 'Admin role level',
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `admins_email_unique` (`email`),
    KEY `admins_role_index` (`role`),
    KEY `admins_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System administrators table';

-- ===================================================================
-- TABLE: departments
-- Description: Organization departments/units
-- ===================================================================
CREATE TABLE IF NOT EXISTS `departments` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL COMMENT 'Department name',
    `description` text DEFAULT NULL COMMENT 'Department description',
    `manager_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Department manager ID',
    `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Is department active',
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `departments_name_unique` (`name`),
    KEY `departments_manager_id_foreign` (`manager_id`),
    KEY `departments_is_active_index` (`is_active`),
    KEY `departments_created_at_index` (`created_at`),
    
    CONSTRAINT `departments_manager_id_foreign` FOREIGN KEY (`manager_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Organization departments table';

-- ===================================================================
-- TABLE: forms
-- Description: Dynamic forms definitions
-- ===================================================================
CREATE TABLE IF NOT EXISTS `forms` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL COMMENT 'Form title',
    `description` text DEFAULT NULL COMMENT 'Form description',
    `slug` varchar(255) NOT NULL COMMENT 'Form URL slug (unique identifier)',
    `created_by` bigint(20) UNSIGNED NOT NULL COMMENT 'Form creator admin ID',
    `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'Form status',
    `allow_multiple_submissions` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Allow multiple submissions per user',
    `show_department_field` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Show department selection field',
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `forms_slug_unique` (`slug`),
    KEY `forms_created_by_foreign` (`created_by`),
    KEY `forms_status_index` (`status`),
    KEY `forms_created_at_index` (`created_at`),
    
    CONSTRAINT `forms_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dynamic forms definitions table';

-- ===================================================================
-- TABLE: form_departments
-- Description: Link forms to allowed departments (optional restriction)
-- ===================================================================
CREATE TABLE IF NOT EXISTS `form_departments` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `form_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Form ID',
    `department_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Department ID',
    `created_at` timestamp NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `form_departments_form_department_unique` (`form_id`, `department_id`),
    KEY `form_departments_form_id_index` (`form_id`),
    KEY `form_departments_department_id_index` (`department_id`),

    CONSTRAINT `form_departments_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `form_departments_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Forms to departments pivot table';

-- ===================================================================
-- TABLE: form_fields
-- Description: Form fields configuration
-- ===================================================================
CREATE TABLE IF NOT EXISTS `form_fields` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `form_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Parent form ID',
    `field_type` enum('text','email','number','date','textarea','select','radio','checkbox','file','repeater','date_range') NOT NULL COMMENT 'Field type',
    `label` varchar(255) NOT NULL COMMENT 'Field display label',
    `placeholder` varchar(255) DEFAULT NULL COMMENT 'Field placeholder text',
    `is_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Is field required',
    `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Is field active',
    `field_options` json DEFAULT NULL COMMENT 'Field options (for select, radio, checkbox)',
    `source_type` enum('static','departments','custom') NOT NULL DEFAULT 'static' COMMENT 'Options source type',
    `parent_field_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Parent field for repeater fields',
    `field_key` varchar(255) NOT NULL COMMENT 'Unique field key per form',
    `order_index` int(11) NOT NULL DEFAULT 0 COMMENT 'Field display order',
    `validation_rules` json DEFAULT NULL COMMENT 'Field validation rules',
    `helper_text` text DEFAULT NULL COMMENT 'Helper text for field',
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    
    PRIMARY KEY (`id`),
    KEY `form_fields_form_id_foreign` (`form_id`),
    KEY `form_fields_parent_field_id_foreign` (`parent_field_id`),
    KEY `form_fields_field_key_index` (`field_key`),
    KEY `form_fields_order_index` (`order_index`),
    KEY `form_fields_field_type_index` (`field_type`),
    KEY `form_fields_is_active_index` (`is_active`),
    UNIQUE KEY `form_fields_form_id_field_key_unique` (`form_id`, `field_key`),
    
    CONSTRAINT `form_fields_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `form_fields_parent_field_id_foreign` FOREIGN KEY (`parent_field_id`) REFERENCES `form_fields` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Form fields configuration table';

-- ===================================================================
-- TABLE: form_submissions
-- Description: Form submission headers
-- ===================================================================
CREATE TABLE IF NOT EXISTS `form_submissions` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `form_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Submitted form ID',
    `submitted_by` varchar(255) NOT NULL COMMENT 'Submitter name/email',
    `department_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Submitter department',
    `status` enum('pending','completed','archived') NOT NULL DEFAULT 'pending' COMMENT 'Submission status',
    `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Submission timestamp',
    `ip_address` varchar(45) DEFAULT NULL COMMENT 'Submitter IP address',
    `reference_code` varchar(50) NOT NULL COMMENT 'Unique reference code',
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `form_submissions_reference_code_unique` (`reference_code`),
    KEY `form_submissions_form_id_foreign` (`form_id`),
    KEY `form_submissions_department_id_foreign` (`department_id`),
    KEY `form_submissions_status_index` (`status`),
    KEY `form_submissions_submitted_at_index` (`submitted_at`),
    
    CONSTRAINT `form_submissions_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `form_submissions_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Form submission headers table';

-- ===================================================================
-- TABLE: submission_answers
-- Description: Form submission detailed answers
-- ===================================================================
CREATE TABLE IF NOT EXISTS `submission_answers` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `submission_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Parent submission ID',
    `field_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Field ID',
    `answer` text DEFAULT NULL COMMENT 'Field answer/value',
    `file_path` varchar(500) DEFAULT NULL COMMENT 'Uploaded file path',
    `file_name` varchar(255) DEFAULT NULL COMMENT 'Uploaded file name',
    `file_size` bigint(20) DEFAULT NULL COMMENT 'Uploaded file size in bytes',
    `repeat_index` int(11) NOT NULL DEFAULT 0 COMMENT 'Repeat index for repeater fields',
    
    PRIMARY KEY (`id`),
    KEY `submission_answers_submission_id_foreign` (`submission_id`),
    KEY `submission_answers_field_id_foreign` (`field_id`),
    KEY `submission_answers_repeat_index` (`repeat_index`),
    KEY `submission_answers_field_repeat_index` (`field_id`, `repeat_index`),
    
    CONSTRAINT `submission_answers_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `form_submissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `submission_answers_field_id_foreign` FOREIGN KEY (`field_id`) REFERENCES `form_fields` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Form submission detailed answers table';

-- ===================================================================
-- TABLE: system_settings
-- Description: System configuration settings
-- ===================================================================
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` varchar(100) NOT NULL COMMENT 'Setting identifier key',
    `setting_value` longtext DEFAULT NULL COMMENT 'Setting value (JSON)',
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System configuration settings table';

-- ===================================================================
-- INDEXES FOR PERFORMANCE
-- ===================================================================

-- Additional composite indexes for better query performance
ALTER TABLE `form_fields` ADD INDEX `form_fields_form_order` (`form_id`, `order_index`);
ALTER TABLE `submission_answers` ADD INDEX `submission_answers_submission_field` (`submission_id`, `field_id`);
ALTER TABLE `form_submissions` ADD INDEX `form_submissions_form_status` (`form_id`, `status`);
ALTER TABLE `departments` ADD INDEX `departments_active_manager` (`is_active`, `manager_id`);

-- ===================================================================
-- INITIAL DATA
-- ===================================================================

-- Insert default system settings
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
('forms_allowed_mime', '["pdf","doc","docx","jpg","jpeg","png","gif","txt","csv","xlsx","xls"]', NOW(), NOW()),
('forms_max_upload_mb', '10', NOW(), NOW()),
('forms_upload_path', 'storage/forms/', NOW(), NOW()),
('reference_code_prefix', 'REF-', NOW(), NOW()),
('reference_code_length', '8', NOW(), NOW()),
('forms_date_format', 'Y-m-d', NOW(), NOW()),
('forms_datetime_format', 'Y-m-d H:i:s', NOW(), NOW()),
('forms_timezone', 'UTC', NOW(), NOW());

-- Insert default admin (password: admin123 - should be changed in production)
INSERT IGNORE INTO `admins` (`id`, `name`, `email`, `password`, `role`, `created_at`, `updated_at`) VALUES
(1, 'System Administrator', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', NOW(), NOW());

-- Insert sample departments
INSERT IGNORE INTO `departments` (`id`, `name`, `description`, `manager_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'إدارة الموارد البشرية', 'إدارة شؤون الموظفين والموارد البشرية', 1, 1, NOW(), NOW()),
(2, 'إدارة تقنية المعلومات', 'إدارة الأنظمة والتقنية', 1, 1, NOW(), NOW()),
(3, 'إدارة المالية', 'إدارة الأمور المالية والمحاسبية', 1, 1, NOW(), NOW()),
(4, 'إدارة التسويق', 'إدارة التسويق والمبيعات', 1, 1, NOW(), NOW());

-- ===================================================================
-- MIGRATION COMPLETE
-- ===================================================================

-- Verify tables were created
SELECT 'Migration completed successfully!' AS status, 
       COUNT(*) AS tables_created 
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name IN ('admins','departments','forms','form_departments','form_fields','form_submissions','submission_answers','system_settings');