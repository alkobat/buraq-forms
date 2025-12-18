<?php
declare(strict_types=1);

/**
 * Database Connection Test
 * اختبار الاتصال بقاعدة البيانات
 */

// تحميل ملف الاتصال
require 'config/database.php';

// ===================================================================
// Functions
// ===================================================================

function testDatabaseConnection(): bool
{
    global $pdo;
    
    try {
        // اختبار الاتصال
        $result = $pdo->query("SELECT 1");
        if ($result) {
            echo "✅ الاتصال بقاعدة البيانات: نجح\n";
            return true;
        }
    } catch (Exception $e) {
        echo "❌ الاتصال بقاعدة البيانات: فشل\n";
        echo "خطأ: " . $e->getMessage() . "\n";
        return false;
    }
    
    return false;
}

function testTables(): bool
{
    global $pdo;
    
    $tables = [
        'admins',
        'departments',
        'forms',
        'form_fields',
        'form_submissions',
        'submission_answers',
        'system_settings',
        'file_download_logs'
    ];
    
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "\n📋 فحص الجداول:\n";
        echo str_repeat("-", 50) . "\n";
        
        $allExist = true;
        foreach ($tables as $table) {
            if (in_array($table, $existingTables)) {
                echo "✅ جدول '$table': موجود\n";
            } else {
                echo "❌ جدول '$table': غير موجود\n";
                $allExist = false;
            }
        }
        
        return $allExist;
    } catch (Exception $e) {
        echo "❌ خطأ في فحص الجداول: " . $e->getMessage() . "\n";
        return false;
    }
}

function testDepartments(): bool
{
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM departments");
        $count = $stmt->fetchColumn();
        
        echo "\n📊 الإدارات:\n";
        echo str_repeat("-", 50) . "\n";
        echo "عدد الإدارات: $count\n";
        
        if ($count > 0) {
            $stmt = $pdo->query("SELECT id, name FROM departments LIMIT 5");
            $departments = $stmt->fetchAll();
            foreach ($departments as $dept) {
                echo "  - {$dept['name']} (ID: {$dept['id']})\n";
            }
            return true;
        } else {
            echo "⚠️ لا توجد إدارات في قاعدة البيانات\n";
            return false;
        }
    } catch (Exception $e) {
        echo "❌ خطأ في فحص الإدارات: " . $e->getMessage() . "\n";
        return false;
    }
}

function testSystemSettings(): bool
{
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM system_settings");
        $count = $stmt->fetchColumn();
        
        echo "\n⚙️ الإعدادات:\n";
        echo str_repeat("-", 50) . "\n";
        echo "عدد الإعدادات: $count\n";
        
        if ($count > 0) {
            $stmt = $pdo->query("SELECT setting_key FROM system_settings");
            $settings = $stmt->fetchAll();
            foreach ($settings as $setting) {
                echo "  - {$setting['setting_key']}\n";
            }
            return true;
        }
        return false;
    } catch (Exception $e) {
        echo "❌ خطأ في فحص الإعدادات: " . $e->getMessage() . "\n";
        return false;
    }
}

function testCharset(): bool
{
    global $pdo;
    
    try {
        $stmt = $pdo->query("SHOW VARIABLES LIKE 'character_set%'");
        $charsets = $stmt->fetchAll();
        
        echo "\n🔤 الترميز:\n";
        echo str_repeat("-", 50) . "\n";
        
        foreach ($charsets as $charset) {
            echo "{$charset['Variable_name']}: {$charset['Value']}\n";
        }
        
        // اختبار العربية
        $stmt = $pdo->prepare("SELECT ? as test");
        $stmt->execute(["اختبار العربية"]);
        $result = $stmt->fetch();
        
        if ($result['test'] === "اختبار العربية") {
            echo "✅ دعم اللغة العربية: يعمل\n";
            return true;
        } else {
            echo "❌ مشكلة في دعم اللغة العربية\n";
            return false;
        }
    } catch (Exception $e) {
        echo "❌ خطأ في فحص الترميز: " . $e->getMessage() . "\n";
        return false;
    }
}

// ===================================================================
// Run Tests
// ===================================================================

echo "\n";
echo "╔" . str_repeat("═", 48) . "╗\n";
echo "║" . str_repeat(" ", 10) . "اختبار قاعدة البيانات BuraqForms" . str_repeat(" ", 6) . "║\n";
echo "╚" . str_repeat("═", 48) . "╝\n";

$results = [];

// Test 1: Database Connection
$results['connection'] = testDatabaseConnection();

// Test 2: Tables
$results['tables'] = testTables();

// Test 3: Departments
$results['departments'] = testDepartments();

// Test 4: System Settings
$results['settings'] = testSystemSettings();

// Test 5: Charset
$results['charset'] = testCharset();

// ===================================================================
// Summary
// ===================================================================

echo "\n";
echo "╔" . str_repeat("═", 48) . "╗\n";
echo "║" . str_repeat(" ", 15) . "ملخص الاختبارات" . str_repeat(" ", 18) . "║\n";
echo "╠" . str_repeat("═", 48) . "╣\n";

$total = count($results);
$passed = count(array_filter($results));

foreach ($results as $name => $result) {
    $status = $result ? "✅ نجح" : "❌ فشل";
    $name_display = match($name) {
        'connection' => 'الاتصال',
        'tables' => 'الجداول',
        'departments' => 'الإدارات',
        'settings' => 'الإعدادات',
        'charset' => 'الترميز'
    };
    printf("║ %-30s %s %s\n", $name_display, str_repeat(" ", 10 - strlen($name_display)), $status);
}

echo "╠" . str_repeat("═", 48) . "╣\n";
printf("║ النتيجة النهائية: %d من %d اختبارات نجحت              ║\n", $passed, $total);
echo "╚" . str_repeat("═", 48) . "╝\n\n";

if ($passed === $total) {
    echo "🎉 جميع الاختبارات نجحت! قاعدة البيانات جاهزة للاستخدام.\n\n";
    exit(0);
} else {
    echo "⚠️ بعض الاختبارات فشلت. يرجى التحقق من الأخطاء أعلاه.\n\n";
    exit(1);
}
