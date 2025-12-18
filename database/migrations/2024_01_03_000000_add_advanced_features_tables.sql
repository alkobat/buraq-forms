-- جداول الميزات المتقدمة لنظام الاستمارات
-- Migration: 2024_01_03_000000_add_advanced_features_tables.sql

-- جدول حفظ المسودات (Drafts)
CREATE TABLE IF NOT EXISTS `drafts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `form_id` int(11) NOT NULL,
  `user_session_id` varchar(255) NOT NULL,
  `user_ip` varchar(45) NOT NULL,
  `form_data` longtext NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_form_session` (`form_id`, `user_session_id`),
  KEY `idx_session` (`user_session_id`),
  KEY `idx_form` (`form_id`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `fk_drafts_form` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول حفظ التصفيات المفضلة
CREATE TABLE IF NOT EXISTS `saved_filters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `filter_name` varchar(255) NOT NULL,
  `filter_type` enum('submissions','forms') NOT NULL,
  `filter_criteria` longtext NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_type` (`filter_type`),
  KEY `idx_default` (`is_default`),
  CONSTRAINT `fk_saved_filters_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الإشعارات
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('new_submission','form_completed','admin_alert','system') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `recipient_id` int(11) NULL,
  `recipient_type` enum('admin','email','sms') NOT NULL DEFAULT 'admin',
  `recipient_contact` varchar(255) NULL,
  `form_id` int(11) NULL,
  `submission_id` int(11) NULL,
  `status` enum('pending','sent','failed','read') NOT NULL DEFAULT 'pending',
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `is_email_sent` tinyint(1) DEFAULT 0,
  `is_sms_sent` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `error_message` text NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recipient` (`recipient_id`, `recipient_type`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_form` (`form_id`),
  KEY `idx_submission` (`submission_id`),
  CONSTRAINT `fk_notifications_form` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notifications_submission` FOREIGN KEY (`submission_id`) REFERENCES `form_submissions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول التعليقات على الإجابات
CREATE TABLE IF NOT EXISTS `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `submission_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `comment_type` enum('general','note','flag','internal') NOT NULL DEFAULT 'general',
  `comment` text NOT NULL,
  `is_internal` tinyint(1) DEFAULT 0,
  `parent_id` int(11) NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_submission` (`submission_id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_parent` (`parent_id`),
  CONSTRAINT `fk_comments_submission` FOREIGN KEY (`submission_id`) REFERENCES `form_submissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الأدوار الإدارية
CREATE TABLE IF NOT EXISTS `admin_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL,
  `role_description` text NULL,
  `is_system_role` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الصلاحيات
CREATE TABLE IF NOT EXISTS `admin_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `permission_name` varchar(100) NOT NULL,
  `permission_description` text NULL,
  `permission_group` varchar(50) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_permission_name` (`permission_name`),
  KEY `idx_group` (`permission_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول ربط الأدوار بالصلاحيات
CREATE TABLE IF NOT EXISTS `admin_role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_permission` (`role_id`, `permission_id`),
  KEY `idx_role` (`role_id`),
  KEY `idx_permission` (`permission_id`),
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `admin_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `admin_permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول ربط المشرفين بالأدوار
CREATE TABLE IF NOT EXISTS `admin_role_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `department_id` int(11) NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_admin_role` (`admin_id`, `role_id`, `department_id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_role` (`role_id`),
  KEY `idx_department` (`department_id`),
  CONSTRAINT `fk_admin_roles_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_admin_roles_role` FOREIGN KEY (`role_id`) REFERENCES `admin_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_admin_roles_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول سجل الأنشطة (Audit Logs)
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NULL,
  `old_values` longtext NULL,
  `new_values` longtext NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_action` (`action`),
  KEY `idx_entity` (`entity_type`, `entity_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_audit_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول التقارير المخصصة
CREATE TABLE IF NOT EXISTS `custom_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NULL,
  `report_config` longtext NOT NULL,
  `report_type` enum('submissions','departments','forms','analytics') NOT NULL,
  `created_by` int(11) NOT NULL,
  `is_shared` tinyint(1) DEFAULT 0,
  `schedule_type` enum('none','daily','weekly','monthly') NOT NULL DEFAULT 'none',
  `last_generated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_type` (`report_type`),
  KEY `idx_schedule` (`schedule_type`),
  CONSTRAINT `fk_custom_reports_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول قوالب الاستمارات المحفوظة
CREATE TABLE IF NOT EXISTS `form_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(255) NOT NULL,
  `template_description` text NULL,
  `form_config` longtext NOT NULL,
  `category` varchar(100) NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `usage_count` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_category` (`category`),
  KEY `idx_public` (`is_public`),
  CONSTRAINT `fk_form_templates_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول النسخ الاحتياطية
CREATE TABLE IF NOT EXISTS `system_backups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `backup_name` varchar(255) NOT NULL,
  `backup_type` enum('full','data','forms','submissions') NOT NULL,
  `backup_file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `record_count` int(11) NOT NULL,
  `status` enum('completed','failed','in_progress') NOT NULL DEFAULT 'in_progress',
  `error_message` text NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_type` (`backup_type`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_system_backups_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول إعدادات النظام المتقدمة
CREATE TABLE IF NOT EXISTS `advanced_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_category` varchar(50) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext NULL,
  `setting_type` enum('string','number','boolean','json','array') NOT NULL DEFAULT 'string',
  `description` text NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_category_key` (`setting_category`, `setting_key`),
  KEY `idx_category` (`setting_category`),
  KEY `idx_public` (`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدراج البيانات الأساسية للأدوار
INSERT INTO `admin_roles` (`role_name`, `role_description`, `is_system_role`) VALUES
('Super Admin', 'صلاحيات كاملة على النظام', 1),
('Admin', 'إدارة عامة للنظام', 1),
('Manager', 'إدارة محددة للقسم', 1),
('Viewer', 'عرض فقط', 1);

-- إدراج البيانات الأساسية للصلاحيات
INSERT INTO `admin_permissions` (`permission_name`, `permission_description`, `permission_group`) VALUES
('forms.create', 'إنشاء استمارات جديدة', 'forms'),
('forms.edit', 'تعديل الاستمارات', 'forms'),
('forms.delete', 'حذف الاستمارات', 'forms'),
('forms.view', 'عرض الاستمارات', 'forms'),
('submissions.view', 'عرض الإجابات', 'submissions'),
('submissions.edit', 'تعديل الإجابات', 'submissions'),
('submissions.delete', 'حذف الإجابات', 'submissions'),
('submissions.export', 'تصدير الإجابات', 'submissions'),
('departments.manage', 'إدارة الإدارات', 'departments'),
('reports.generate', 'إنشاء التقارير', 'reports'),
('users.manage', 'إدارة المستخدمين', 'users'),
('system.settings', 'إعدادات النظام', 'system'),
('notifications.manage', 'إدارة الإشعارات', 'notifications'),
('templates.manage', 'إدارة القوالب', 'templates'),
('backups.manage', 'إدارة النسخ الاحتياطية', 'backups');

-- إدراج صلاحيات الأدوار الافتراضية
-- Super Admin: جميع الصلاحيات
INSERT INTO `admin_role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `admin_permissions`;

-- Admin: معظم الصلاحيات except users.manage
INSERT INTO `admin_role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `admin_permissions` WHERE `permission_name` != 'users.manage';

-- Manager: صلاحيات محدودة
INSERT INTO `admin_role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `admin_permissions` 
WHERE `permission_name` IN ('forms.create', 'forms.edit', 'forms.view', 'submissions.view', 'submissions.edit', 'reports.generate');

-- Viewer: عرض فقط
INSERT INTO `admin_role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `admin_permissions` 
WHERE `permission_name` IN ('forms.view', 'submissions.view', 'reports.generate');

-- إدراج الإعدادات الافتراضية
INSERT INTO `advanced_settings` (`setting_category`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_public`) VALUES
('notifications', 'email_enabled', '1', 'boolean', 'تفعيل الإشعارات عبر البريد الإلكتروني', 1),
('notifications', 'sms_enabled', '0', 'boolean', 'تفعيل الإشعارات عبر SMS', 1),
('notifications', 'new_submission_email', '1', 'boolean', 'إرسال إشعار عند استقبال استمارة جديدة', 1),
('ui', 'dark_mode_enabled', '0', 'boolean', 'تفعيل الوضع الليلي', 1),
('ui', 'auto_save_interval', '30', 'number', 'فترة الحفظ التلقائي بالثواني', 1),
('ui', 'items_per_page', '20', 'number', 'عدد العناصر في الصفحة الواحدة', 1),
('validation', 'enable_ajax_validation', '1', 'boolean', 'تفعيل التحقق الفوري', 1),
('validation', 'sanitize_input', '1', 'boolean', 'تنظيف المدخلات تلقائياً', 1),
('system', 'maintenance_mode', '0', 'boolean', 'وضع الصيانة', 0),
('system', 'backup_retention_days', '30', 'number', 'عدد أيام الاحتفاظ بالنسخ الاحتياطية', 0);

-- إنشاء indexes لتحسين الأداء
CREATE INDEX idx_drafts_session_form ON drafts(user_session_id, form_id);
CREATE INDEX idx_notifications_recipient_status ON notifications(recipient_id, status, recipient_type);
CREATE INDEX idx_audit_logs_entity_time ON audit_logs(entity_type, entity_id, created_at);
CREATE INDEX idx_comments_submission_time ON comments(submission_id, created_at);
CREATE INDEX idx_submissions_department_status ON form_submissions(department_id, status, submitted_at);

COMMIT;