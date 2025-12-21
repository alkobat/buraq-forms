<?php
/**
 * اختبار نظام التحقق من الهوية - BuraqForms
 * 
 * هذا الملف يقوم بفحص جميع مكونات نظام الأمان والتحقق من الهوية
 * للتأكد من عمله بشكل صحيح وآمن.
 */

declare(strict_types=1);

// Start session for testing
session_start();

echo "<!DOCTYPE html>
<html lang='ar' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>اختبار نظام التحقق من الهوية</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css' rel='stylesheet'>
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .test-section { margin: 20px 0; padding: 20px; border-radius: 10px; }
        .test-pass { background-color: #d4edda; border: 1px solid #c3e6cb; }
        .test-fail { background-color: #f8d7da; border: 1px solid #f5c6cb; }
        .test-info { background-color: #d1ecf1; border: 1px solid #bee5eb; }
        .code-block { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; }
    </style>
</head>
<body class='bg-light'>";

echo "<div class='container mt-5'>";
echo "<h1 class='text-center mb-5'><i class='fas fa-shield-alt'></i> اختبار نظام التحقق من الهوية</h1>";

// Include required files
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/Core/Auth.php';

use BuraqForms\Core\Auth;

echo "<div class='row'>";
echo "<div class='col-12'>";

// Test counter
$tests_passed = 0;
$tests_failed = 0;
$total_tests = 0;

// Helper function to run test
function run_test($test_name, $callback) {
    global $tests_passed, $tests_failed, $total_tests;
    $total_tests++;
    
    echo "<div class='test-section test-info mb-3'>";
    echo "<h5><i class='fas fa-play-circle'></i> اختبار: {$test_name}</h5>";
    
    try {
        $result = $callback();
        if ($result['success']) {
            echo "<div class='alert alert-success'><i class='fas fa-check'></i> نجح الاختبار</div>";
            if (isset($result['message'])) {
                echo "<p>{$result['message']}</p>";
            }
            $tests_passed++;
        } else {
            echo "<div class='alert alert-danger'><i class='fas fa-times'></i> فشل الاختبار</div>";
            echo "<p><strong>خطأ:</strong> {$result['message']}</p>";
            $tests_failed++;
        }
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> خطأ في الاختبار</div>";
        echo "<p><strong>خطأ:</strong> " . $e->getMessage() . "</p>";
        $tests_failed++;
    }
    
    echo "</div>";
}

// ========================================
// اختبار 1: التحقق من Auth Class
// ========================================
run_test("تحميل Auth Class", function() {
    if (class_exists('BuraqForms\\Core\\Auth')) {
        return ['success' => true, 'message' => 'Auth Class تم تحميلها بنجاح'];
    } else {
        return ['success' => false, 'message' => 'فشل في تحميل Auth Class'];
    }
});

// ========================================
// اختبار 2: اختبار helper functions
// ========================================
run_test("تحميل Helper Functions", function() {
    $functions_to_test = [
        'is_logged_in',
        'current_user', 
        'require_auth',
        'require_role',
        'login_user',
        'logout_user',
        'generate_csrf_token',
        'verify_csrf_token',
        'has_permission',
        'can_access'
    ];
    
    $missing_functions = [];
    foreach ($functions_to_test as $function) {
        if (!function_exists($function)) {
            $missing_functions[] = $function;
        }
    }
    
    if (empty($missing_functions)) {
        return ['success' => true, 'message' => 'جميع Helper Functions متوفرة'];
    } else {
        return ['success' => false, 'message' => 'دوال مفقودة: ' . implode(', ', $missing_functions)];
    }
});

// اختبار 3: اختبار RolePermissionService
run_test("تحميل RolePermissionService", function() {
    if (!file_exists(__DIR__ . '/src/Core/Services/RolePermissionService.php')) {
        return ['success' => false, 'message' => 'ملف RolePermissionService غير موجود'];
    }
    
    if (!class_exists('BuraqForms\\Core\\Services\\RolePermissionService')) {
        return ['success' => false, 'message' => 'فشل في تحميل RolePermissionService'];
    }
    
    return ['success' => true, 'message' => 'RolePermissionService تم تحميلها بنجاح'];
});

// ========================================
// اختبار 5: اختبار CSRF Token
// ========================================
run_test("توليد والتحقق من CSRF Token", function() {
    // Generate token
    $token = generate_csrf_token();
    
    if (empty($token) || strlen($token) < 40) {
        return ['success' => false, 'message' => 'فشل في توليد CSRF Token'];
    }
    
    // Verify token
    if (!verify_csrf_token($token)) {
        return ['success' => false, 'message' => 'فشل في التحقق من CSRF Token'];
    }
    
    return ['success' => true, 'message' => 'CSRF Token يعمل بشكل صحيح'];
});

// ========================================
// اختبار 5: اختبار Session Security
// ========================================
run_test("Session Security", function() {
    // Test session validation (should return true when not logged in)
    $is_valid = validate_session();
    
    if ($is_valid === true) {
        return ['success' => true, 'message' => 'Session Security يعمل بشكل صحيح'];
    } else {
        return ['success' => false, 'message' => 'مشكلة في Session Security'];
    }
});

// ========================================
// اختبار 6: اختبار نظام الأدوار والصلاحيات
// ========================================
run_test("نظام الأدوار والصلاحيات الجديد", function() {
    // Test loading RolePermissionService
    if (!class_exists('BuraqForms\\Core\\Services\\RolePermissionService')) {
        return ['success' => false, 'message' => 'RolePermissionService غير متوفر'];
    }
    
    // Test available roles
    $roles = get_available_roles();
    
    if (empty($roles)) {
        return ['success' => false, 'message' => 'لا توجد أدوار متاحة'];
    }
    
    // Check if essential roles exist
    $role_names = array_column($roles, 'role_name');
    $essential_roles = ['admin', 'manager', 'editor', 'viewer'];
    
    foreach ($essential_roles as $role) {
        if (!in_array($role, $role_names)) {
            return ['success' => false, 'message' => "دور {$role} غير موجود"];
        }
    }
    
    // Test permission system
    $permissionService = new BuraqForms\Core\Services\RolePermissionService();
    $permissions = $permissionService->getAllPermissions();
    
    if (empty($permissions)) {
        return ['success' => false, 'message' => 'لا توجد صلاحيات متاحة'];
    }
    
    return ['success' => true, 'message' => 'نظام الأدوار والصلاحيات الجديد يعمل بشكل صحيح'];
});

// ========================================
// اختبار 8: اختبار جداول RBAC
// ========================================
run_test("جداول RBAC (الأدوار والصلاحيات)", function() {
    try {
        require_once __DIR__ . '/config/database.php';
        
        $required_tables = ['admin_roles', 'admin_permissions', 'admin_role_assignments', 'admin_role_permissions', 'audit_logs'];
        $missing_tables = [];
        
        foreach ($required_tables as $table) {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            
            if ($stmt->rowCount() === 0) {
                $missing_tables[] = $table;
            }
        }
        
        if (!empty($missing_tables)) {
            return ['success' => false, 'message' => 'جداول مفقودة: ' . implode(', ', $missing_tables)];
        }
        
        // Check if data exists
        $stmt = $pdo->query("SELECT COUNT(*) FROM admin_roles");
        $role_count = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM admin_permissions");
        $permission_count = $stmt->fetchColumn();
        
        if ($role_count === 0 || $permission_count === 0) {
            return ['success' => false, 'message' => 'البيانات الأساسية للأدوار والصلاحيات غير موجودة'];
        }
        
        return ['success' => true, 'message' => 'جداول RBAC موجودة ومعبأة بالبيانات'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'خطأ في فحص جداول RBAC: ' . $e->getMessage()];
    }
});

// ========================================
// اختبار 9: اختبار database connection
// ========================================
run_test("اتصال قاعدة البيانات", function() {
    try {
        require_once __DIR__ . '/config/database.php';
        
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            return ['success' => false, 'message' => 'فشل في الاتصال بقاعدة البيانات'];
        }
        
        // Test simple query
        $stmt = $pdo->query('SELECT 1');
        if ($stmt === false) {
            return ['success' => false, 'message' => 'فشل في تنفيذ استعلام تجريبي'];
        }
        
        return ['success' => true, 'message' => 'اتصال قاعدة البيانات يعمل بشكل صحيح'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'خطأ في قاعدة البيانات: ' . $e->getMessage()];
    }
});

// ========================================
// اختبار 7: اختبار وجود جدول admins
// ========================================
run_test("جدول المستخدمين admins", function() {
    try {
        require_once __DIR__ . '/config/database.php';
        
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'admins'");
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'جدول admins غير موجود'];
        }
        
        // Check if admin user exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ?");
        $stmt->execute(['admin@buraqforms.com']);
        $count = $stmt->fetchColumn();
        
        if ($count === 0) {
            return ['success' => false, 'message' => 'لا يوجد مستخدم افتراضي للاختبار'];
        }
        
        return ['success' => true, 'message' => 'جدول admins موجود ويحتوي على مستخدم افتراضي'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'خطأ في فحص جدول admins: ' . $e->getMessage()];
    }
});

// ========================================
// اختبار 8: اختبار محاولة تسجيل دخول وهمية
// ========================================
run_test("محاولة تسجيل دخول تجريبية", function() {
    // Test with non-existent user
    $result = Auth::login_user('nonexistent@test.com', 'wrongpassword');
    
    if (!$result['success']) {
        return ['success' => true, 'message' => 'نظام التحقق يرفض البيانات الخاطئة بشكل صحيح'];
    } else {
        return ['success' => false, 'message' => 'نظام التحقق يقبل بيانات خاطئة - خطأ أمني!'];
    }
});

// ========================================
// اختبار 9: فحص ملفات النظام المحمية
// ========================================
run_test("حماية صفحات النظام", function() {
    $protected_files = [
        '/home/engine/project/public/admin/dashboard.php',
        '/home/engine/project/public/admin/forms.php',
        '/home/engine/project/public/admin/form-submissions.php',
        '/home/engine/project/public/admin/departments.php'
    ];
    
    $missing_protection = [];
    
    foreach ($protected_files as $file) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            if (strpos($content, 'require_auth()') === false) {
                $missing_protection[] = basename($file);
            }
        }
    }
    
    if (empty($missing_protection)) {
        return ['success' => true, 'message' => 'جميع الصفحات المحمية تحتوي على checks الأمنية'];
    } else {
        return ['success' => false, 'message' => 'صفحات بدون حماية: ' . implode(', ', $missing_protection)];
    }
});

// ========================================
// اختبار 10: فحص إعدادات الأمان
// ========================================
run_test("ملف إعدادات الأمان", function() {
    $security_file = __DIR__ . '/config/security.php';
    
    if (!file_exists($security_file)) {
        return ['success' => false, 'message' => 'ملف security.php غير موجود'];
    }
    
    $config = require $security_file;
    
    $required_sections = ['session', 'csrf', 'password', 'login'];
    $missing_sections = [];
    
    foreach ($required_sections as $section) {
        if (!isset($config[$section])) {
            $missing_sections[] = $section;
        }
    }
    
    if (empty($missing_sections)) {
        return ['success' => true, 'message' => 'إعدادات الأمان مكتملة'];
    } else {
        return ['success' => false, 'message' => 'أقسام مفقودة في security.php: ' . implode(', ', $missing_sections)];
    }
});

// ========================================
// Summary Section
// ========================================
echo "<div class='test-section test-info'>";
echo "<h4><i class='fas fa-chart-bar'></i> ملخص الاختبارات</h4>";

$success_rate = round(($tests_passed / $total_tests) * 100, 1);

echo "<div class='row'>";
echo "<div class='col-md-4'>";
echo "<div class='card text-center'>";
echo "<div class='card-body'>";
echo "<h5 class='card-title'>إجمالي الاختبارات</h5>";
echo "<h2 class='text-primary'>{$total_tests}</h2>";
echo "</div></div></div>";

echo "<div class='col-md-4'>";
echo "<div class='card text-center'>";
echo "<div class='card-body'>";
echo "<h5 class='card-title'>نجحت</h5>";
echo "<h2 class='text-success'>{$tests_passed}</h2>";
echo "</div></div></div>";

echo "<div class='col-md-4'>";
echo "<div class='card text-center'>";
echo "<div class='card-body'>";
echo "<h5 class='card-title'>فشلت</h5>";
echo "<h2 class='text-danger'>{$tests_failed}</h2>";
echo "</div></div></div>";

echo "</div>";

echo "<div class='mt-3'>";
echo "<h5>معدل النجاح: {$success_rate}%</h5>";
if ($success_rate >= 90) {
    echo "<div class='alert alert-success'><i class='fas fa-check-circle'></i> ممتاز! النظام يعمل بشكل صحيح</div>";
} elseif ($success_rate >= 70) {
    echo "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> جيد مع بعض التحسينات المطلوبة</div>";
} else {
    echo "<div class='alert alert-danger'><i class='fas fa-times-circle'></i> يحتاج إصلاحات جوهرية</div>";
}
echo "</div>";

echo "</div>";

// ========================================
// Usage Instructions
// ========================================
echo "<div class='test-section test-info'>";
echo "<h4><i class='fas fa-info-circle'></i> تعليمات الاستخدام</h4>";

echo "<div class='row'>";
echo "<div class='col-md-6'>";
echo "<h5>للمطورين:</h5>";
echo "<ul>";
echo "<li>استخدم <code>require_auth()</code> لحماية الصفحات</li>";
echo "<li>استخدم <code>require_role('admin')</code> لصفحات المدير فقط</li>";
echo "<li>استخدم <code>verify_csrf_token(\$token)</code> في النماذج</li>";
echo "<li>استخدم <code>has_permission('permission.name')</code> للتحقق من الصلاحيات</li>";
echo "</ul>";
echo "</div>";

echo "<div class='col-md-6'>";
echo "<h5>للاختبار:</h5>";
echo "<ul>";
echo "<li>البريد: <code>admin@buraqforms.com</code></li>";
echo "<li>كلمة المرور: <code>password123</code></li>";
echo "<li>الدور: <code>admin</code></li>";
echo "</ul>";
echo "</div>";

echo "</div>";

echo "</div>";

echo "</div>"; // col-12
echo "</div>"; // row

echo "</div>"; // container

echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>";
echo "<script src='https://kit.fontawesome.com/your-fontawesome-kit.js'></script>";
echo "</body></html>";

?>