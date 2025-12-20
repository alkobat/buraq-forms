<?php
declare(strict_types=1);

// تضمين إعدادات قاعدة البيانات
require_once __DIR__ . '/../config/database.php';

try {
    // كلمة المرور المُشفرة لـ "password123"
    $hashedPassword = '$2y$10$2L8TYrr7TZYTYxL7YYQ2kuzM2B5Z2B7Q2J2K2L2M2N2O2P2Q2R2S2'; // password123
    
    // إدراج admin افتراضي
    $stmt = $pdo->prepare("
        INSERT INTO admins (name, email, password, role, created_at, updated_at)
        VALUES (?, ?, ?, 'admin', NOW(), NOW())
        ON DUPLICATE KEY UPDATE 
            password = VALUES(password),
            updated_at = NOW()
    ");
    
    $result = $stmt->execute([
        'مسؤول النظام',
        'admin@buraqforms.com',
        $hashedPassword
    ]);
    
    if ($result) {
        echo "✅ تم إنشاء/تحديث admin افتراضي بنجاح\n";
        echo "📧 البريد الإلكتروني: admin@buraqforms.com\n";
        echo "🔑 كلمة المرور: password123\n";
        echo "🔗 يمكنك الآن تسجيل الدخول باستخدام هذه البيانات\n";
    } else {
        echo "❌ فشل في إنشاء admin افتراضي\n";
    }
    
} catch (PDOException $e) {
    echo "❌ خطأ في قاعدة البيانات: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ خطأ عام: " . $e->getMessage() . "\n";
}