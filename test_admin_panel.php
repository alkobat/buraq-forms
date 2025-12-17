<?php

declare(strict_types=1);

// اختبار سريع للواجهات الأساسية
echo "<h2>اختبار لوحة التحكم الإدارية</h2>";

// فحص الملفات الأساسية
$requiredFiles = [
    'config/database.php',
    'src/Core/Database.php',
    'src/Core/Services/DepartmentService.php',
    'src/Core/Services/FormService.php',
    'src/Core/Services/FormFieldService.php',
    'public/admin/dashboard.php',
    'public/admin/departments.php',
    'public/admin/forms.php',
    'public/admin/form-builder.php',
    'public/admin/api/forms.php',
    'public/admin/api/departments.php',
    'public/admin/api/form-fields.php',
    'public/preview-form.php',
    'public/assets/css/admin.css',
    'public/assets/js/admin.js'
];

echo "<h3>فحص الملفات المطلوبة:</h3>";
$allFilesExist = true;
foreach ($requiredFiles as $file) {
    $exists = file_exists(__DIR__ . '/' . $file);
    $status = $exists ? '✅ موجود' : '❌ مفقود';
    echo "<p>{$status} - {$file}</p>";
    if (!$exists) $allFilesExist = false;
}

// فحص الخدمات
echo "<h3>فحص الخدمات (Classes):</h3>";

try {
    require_once __DIR__ . '/src/Core/Database.php';
    require_once __DIR__ . '/src/Core/Services/FormService.php';
    require_once __DIR__ . '/src/Core/Services/FormFieldService.php';
    require_once __DIR__ . '/src/Core/Services/DepartmentService.php';
    require_once __DIR__ . '/src/helpers.php';
    
    echo "<p>✅ تم تحميل جميع الملفات بنجاح</p>";
    
    // فحص وجود الكلاسات
    $classes = [
        'EmployeeEvaluationSystem\\Core\\Database',
        'EmployeeEvaluationSystem\\Core\\Services\\FormService',
        'EmployeeEvaluationSystem\\Core\\Services\\FormFieldService',
        'EmployeeEvaluationSystem\\Core\\Services\\DepartmentService'
    ];
    
    foreach ($classes as $class) {
        $exists = class_exists($class);
        $status = $exists ? '✅ موجود' : '❌ غير موجود';
        echo "<p>{$status} - {$class}</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ خطأ في تحميل الملفات: " . $e->getMessage() . "</p>";
}

// فحص المجلدات
echo "<h3>فحص المجلدات:</h3>";
$requiredDirs = [
    'public/admin',
    'public/assets/css',
    'public/assets/js',
    'storage/forms',
    'config'
];

foreach ($requiredDirs as $dir) {
    $exists = is_dir(__DIR__ . '/' . $dir);
    $status = $exists ? '✅ موجود' : '❌ مفقود';
    echo "<p>{$status} - {$dir}/</p>";
}

// ملخص النتائج
echo "<h3>ملخص النتائج:</h3>";
if ($allFilesExist) {
    echo "<p style='color: green; font-weight: bold;'>✅ جميع الملفات الأساسية موجودة!</p>";
    echo "<p>يمكن الآن:</p>";
    echo "<ul>";
    echo "<li>الوصول للوحة التحكم: <a href='/public/admin/dashboard.php'>/public/admin/dashboard.php</a></li>";
    echo "<li>إدارة الإدارات: <a href='/public/admin/departments.php'>/public/admin/departments.php</a></li>";
    echo "<li>إدارة الاستمارات: <a href='/public/admin/forms.php'>/public/admin/forms.php</a></li>";
    echo "<li>محرر الاستمارات: <a href='/public/admin/form-builder.php'>/public/admin/form-builder.php</a></li>";
    echo "</ul>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ بعض الملفات مفقودة!</p>";
}

echo "<hr>";
echo "<h3>متطلبات قاعدة البيانات:</h3>";
echo "<p>تأكد من أن قاعدة البيانات تحتوي على الجداول التالية:</p>";
echo "<ul>";
echo "<li>departments - جدول الإدارات</li>";
echo "<li>forms - جدول الاستمارات</li>";
echo "<li>form_fields - جدول حقول الاستمارات</li>";
echo "<li>form_submissions - جدول الإجابات</li>";
echo "<li>users - جدول المستخدمين</li>";
echo "<li>system_settings - جدول الإعدادات</li>";
echo "</ul>";

echo "<p><strong>ملاحظة:</strong> هذا اختبار أولي. للاستخدام الفعلي يجب إعداد قاعدة البيانات وتحديث إعدادات الاتصال.</p>";
?>