-- ===================================================================
-- BuraqForms - الأدوار والصلاحيات (RBAC) Tables
-- Adds Role-Based Access Control tables to the existing database
-- ===================================================================

USE buraq_forms;

-- جدول الأدوار
CREATE TABLE IF NOT EXISTS `admin_roles` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `role_name` VARCHAR(50) UNIQUE NOT NULL,
    `role_description` TEXT,
    `is_system_role` BOOLEAN DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`role_name`),
    INDEX (`is_system_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الصلاحيات
CREATE TABLE IF NOT EXISTS `admin_permissions` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `permission_name` VARCHAR(100) UNIQUE NOT NULL,
    `permission_description` TEXT,
    `permission_group` VARCHAR(50) NOT NULL,
    `is_system_permission` BOOLEAN DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`permission_name`),
    INDEX (`permission_group`),
    INDEX (`is_system_permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول ربط الأدوار بالصلاحيات (Many-to-Many)
CREATE TABLE IF NOT EXISTS `admin_role_permissions` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `role_id` INT NOT NULL,
    `permission_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_role_permission` (`role_id`, `permission_id`),
    FOREIGN KEY (`role_id`) REFERENCES `admin_roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `admin_permissions`(`id`) ON DELETE CASCADE,
    INDEX (`role_id`),
    INDEX (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول إسناد الأدوار للمستخدمين
CREATE TABLE IF NOT EXISTS `admin_role_assignments` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `admin_id` INT NOT NULL,
    `role_id` INT NOT NULL,
    `department_id` INT NULL,
    `assigned_by` INT NULL,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL,
    `is_active` BOOLEAN DEFAULT 1,
    UNIQUE KEY `unique_admin_role_dept` (`admin_id`, `role_id`, `department_id`),
    FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`role_id`) REFERENCES `admin_roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL,
    INDEX (`admin_id`),
    INDEX (`role_id`),
    INDEX (`department_id`),
    INDEX (`is_active`),
    INDEX (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول تسجيل العمليات (Audit Logs)
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `admin_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `resource` VARCHAR(100) NULL,
    `status` ENUM('success', 'failure', 'warning') DEFAULT 'success',
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `details` JSON NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE SET NULL,
    INDEX (`admin_id`),
    INDEX (`action`),
    INDEX (`status`),
    INDEX (`created_at`),
    INDEX (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الإعدادات المتقدمة
CREATE TABLE IF NOT EXISTS `advanced_settings` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT NULL,
    `setting_type` ENUM('string', 'integer', 'boolean', 'json', 'encrypted') DEFAULT 'string',
    `description` TEXT NULL,
    `is_system_setting` BOOLEAN DEFAULT 0,
    `updated_by` INT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`updated_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL,
    INDEX (`setting_key`),
    INDEX (`setting_type`),
    INDEX (`is_system_setting`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- إدراج الأدوار الافتراضية
-- ===================================================================

INSERT IGNORE INTO `admin_roles` (`role_name`, `role_description`, `is_system_role`) VALUES
('admin', 'مدير النظام - جميع الصلاحيات', 1),
('manager', 'مدير - إدارة الإدارات والمحتوى', 1),
('editor', 'محرر - إنشاء وتعديل الاستمارات', 1),
('viewer', 'مراقب - عرض فقط', 1);

-- ===================================================================
-- إدراج الصلاحيات الافتراضية
-- ===================================================================

INSERT IGNORE INTO `admin_permissions` (`permission_name`, `permission_description`, `permission_group`, `is_system_permission`) VALUES
-- صلاحيات لوحة التحكم
('dashboard.view', 'عرض لوحة التحكم', 'dashboard', 1),

-- صلاحيات الاستمارات
('forms.view', 'عرض الاستمارات', 'forms', 1),
('forms.create', 'إنشاء استمارات جديدة', 'forms', 1),
('forms.edit', 'تعديل الاستمارات', 'forms', 1),
('forms.delete', 'حذف الاستمارات', 'forms', 1),
('forms.publish', 'نشر وإلغاء نشر الاستمارات', 'forms', 1),

-- صلاحيات الإجابات
('submissions.view', 'عرض إجابات الاستمارات', 'submissions', 1),
('submissions.edit', 'تعديل إجابات الاستمارات', 'submissions', 1),
('submissions.delete', 'حذف إجابات الاستمارات', 'submissions', 1),
('submissions.export', 'تصدير إجابات الاستمارات', 'submissions', 1),

-- صلاحيات الإدارات
('departments.view', 'عرض الإدارات', 'departments', 1),
('departments.manage', 'إدارة الإدارات', 'departments', 1),
('departments.edit', 'تعديل الإدارات', 'departments', 1),

-- صلاحيات التقارير
('reports.view', 'عرض التقارير', 'reports', 1),
('reports.export', 'تصدير التقارير', 'reports', 1),
('reports.generate', 'إنشاء تقارير مخصصة', 'reports', 1),

-- صلاحيات الصلاحيات والأدوار
('permissions.view', 'عرض الصلاحيات والأدوار', 'permissions', 1),
('permissions.manage', 'إدارة الصلاحيات والأدوار', 'permissions', 1),
('permissions.assign', 'تعيين الأدوار للمستخدمين', 'permissions', 1),

-- صلاحيات الإعدادات
('settings.view', 'عرض إعدادات النظام', 'settings', 1),
('settings.manage', 'إدارة إعدادات النظام', 'settings', 1),
('settings.advanced', 'إدارة الإعدادات المتقدمة', 'settings', 1),

-- صلاحيات الأمان
('security.view', 'عرض سجلات الأمان', 'security', 1),
('security.manage', 'إدارة إعدادات الأمان', 'security', 1),
('security.audit', 'عرض سجلات التدقيق', 'security', 1),

-- صلاحيات النسخ الاحتياطية
('backup.view', 'عرض النسخ الاحتياطية', 'backup', 1),
('backup.create', 'إنشاء نسخة احتياطية', 'backup', 1),
('backup.restore', 'استعادة نسخة احتياطية', 'backup', 1),
('backup.delete', 'حذف نسخ احتياطية', 'backup', 1),

-- صلاحيات الإشعارات
('notifications.view', 'عرض الإشعارات', 'notifications', 1),
('notifications.send', 'إرسال إشعارات', 'notifications', 1),
('notifications.manage', 'إدارة الإشعارات', 'notifications', 1);

-- ===================================================================
-- ربط الأدوار بالصلاحيات
-- ===================================================================

-- صلاحيات المدير (admin)
INSERT IGNORE INTO `admin_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM admin_roles r, admin_permissions p WHERE r.role_name = 'admin';

-- صلاحيات المدير (manager)
INSERT IGNORE INTO `admin_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id 
FROM admin_roles r, admin_permissions p 
WHERE r.role_name = 'manager' 
AND p.permission_name IN (
    'dashboard.view',
    'forms.view', 'forms.create', 'forms.edit', 'forms.publish',
    'submissions.view', 'submissions.edit', 'submissions.export',
    'departments.view', 'departments.manage', 'departments.edit',
    'reports.view', 'reports.export', 'reports.generate'
);

-- صلاحيات المحرر (editor)
INSERT IGNORE INTO `admin_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id 
FROM admin_roles r, admin_permissions p 
WHERE r.role_name = 'editor' 
AND p.permission_name IN (
    'dashboard.view',
    'forms.view', 'forms.create', 'forms.edit', 'forms.publish',
    'submissions.view', 'submissions.edit',
    'departments.view',
    'reports.view'
);

-- صلاحيات المراقب (viewer)
INSERT IGNORE INTO `admin_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id 
FROM admin_roles r, admin_permissions p 
WHERE r.role_name = 'viewer' 
AND p.permission_name IN (
    'dashboard.view',
    'forms.view',
    'submissions.view',
    'departments.view',
    'reports.view'
);

-- ===================================================================
-- إسناد الأدوار للمستخدمين الموجودين
-- ===================================================================

-- إسناد دور admin للمستخدم admin@buraqforms.com (ID = 1)
INSERT IGNORE INTO `admin_role_assignments` (`admin_id`, `role_id`, `assigned_by`) 
SELECT a.id, r.id, a.id 
FROM admins a, admin_roles r 
WHERE a.email = 'admin@buraqforms.com' AND r.role_name = 'admin';

-- ===================================================================
-- إدراج إعدادات افتراضية
-- ===================================================================

INSERT IGNORE INTO `advanced_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_system_setting`) VALUES
('system_name', 'BuraqForms', 'string', 'اسم النظام', 1),
('system_version', '1.0.0', 'string', 'إصدار النظام', 1),
('max_login_attempts', '5', 'integer', 'أقصى عدد لمحاولات تسجيل الدخول', 1),
('session_timeout', '7200', 'integer', 'مدة انتهاء الجلسة بالثواني', 1),
('enable_audit_logging', 'true', 'boolean', 'تفعيل سجلات التدقيق', 1),
('maintenance_mode', 'false', 'boolean', 'وضع الصيانة', 1),
('email_notifications', 'true', 'boolean', 'تفعيل إشعارات البريد الإلكتروني', 1);

-- ===================================================================
-- إضافة عمود department_id لجدول forms إذا لم يكن موجود
-- ===================================================================

SET @sql = CONCAT('ALTER TABLE forms ADD COLUMN IF NOT EXISTS department_id INT NULL');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة foreign key constraint إذا لم يكن موجود
SET @sql = CONCAT('ALTER TABLE forms ADD CONSTRAINT IF NOT EXISTS fk_forms_department 
                  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة فهارس
SET @sql = 'ALTER TABLE forms ADD INDEX IF NOT EXISTS idx_forms_department (department_id)';
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;