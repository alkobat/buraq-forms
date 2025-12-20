-- إضافة Admin افتراضي لنظام BuraqForms
-- كلمة المرور: password123
-- البريد الإلكتروني: admin@buraqforms.com

USE buraq_forms;

-- إدراج admin افتراضي
INSERT INTO admins (name, email, password, role, created_at, updated_at)
VALUES (
    'مسؤول النظام',
    'admin@buraqforms.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password123
    'admin',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE 
    password = VALUES(password),
    name = VALUES(name),
    updated_at = NOW();

-- عرض النتيجة
SELECT 'Admin افتراضي تم إنشاؤه بنجاح' AS status,
       id, name, email, role, created_at
FROM admins 
WHERE email = 'admin@buraqforms.com';