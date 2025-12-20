-- أدرج admin افتراضي للاختبار
-- كلمة المرور: password123 (مشفرة بـ bcrypt)
INSERT INTO admins (name, email, password, role, created_at, updated_at)
VALUES (
    'مسؤول النظام',
    'admin@buraqforms.com',
    '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa',
    'admin',
    NOW(),
    NOW()
);
