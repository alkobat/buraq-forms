<?php
/**
 * Integration Test for Database Configuration
 * 
 * This script demonstrates how to integrate the new database configuration
 * with the existing Employee Evaluation System.
 */

// Test 1: Include the database configuration
echo "<h2>اختبار إعدادات قاعدة البيانات</h2>";
echo "<hr>";

try {
    require_once __DIR__ . '/config/database.php';
    echo "✅ تم تحميل ملف database.php بنجاح<br>";
    
    // Test 2: Check if PDO is available
    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        echo "✅ متغير \$pdo متاح وجاهز للاستخدام<br>";
    } else {
        echo "❌ متغير \$pdo غير متاح<br>";
    }
    
    // Test 3: Test connection
    if (testDatabaseConnection()) {
        echo "✅ اختبار الاتصال نجح<br>";
    } else {
        echo "❌ اختبار الاتصال فشل<br>";
    }
    
    // Test 4: Get database info
    $config = getDatabaseConfig();
    echo "ℹ️  معلومات قاعدة البيانات:<br>";
    echo "  - المضيف: " . htmlspecialchars($config['host']) . "<br>";
    echo "  - قاعدة البيانات: " . htmlspecialchars($config['database']) . "<br>";
    echo "  - الترميز: " . htmlspecialchars($config['charset']) . "<br>";
    echo "  - المنفذ: " . htmlspecialchars($config['port']) . "<br>";
    
    // Test 5: Test with the existing system's classes
    echo "<hr>";
    echo "<h3>اختبار التوافق مع النظام الحالي</h3>";
    
    // Check if we can use the connection with existing system
    if (class_exists('\\BuraqForms\\Core\\Database')) {
        echo "✅ فئة Database الموجودة في النظام متاحة<br>";
        
        try {
            // Try to use the new connection with existing class
            $systemConfig = [
                'host' => DB_HOST,
                'database' => DB_NAME,
                'username' => DB_USER,
                'password' => DB_PASS,
                'charset' => DB_CHARSET,
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            ];
            
            $systemConnection = \\BuraqForms\\Core\\Database::createConnection($systemConfig);
            echo "✅ تم إنشاء اتصال باستخدام فئة Database الموجودة<br>";
            
        } catch (Exception $e) {
            echo "❌ خطأ في إنشاء الاتصال: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    } else {
        echo "ℹ️  فئة Database غير متاحة (قد تكون المشكلة في autoloading)<br>";
    }
    
    // Test 6: Test SQL queries
    echo "<hr>";
    echo "<h3>اختبار الاستعلامات</h3>";
    
    try {
        // Test basic query
        $stmt = $pdo->query("SELECT VERSION() as mysql_version");
        $result = $stmt->fetch();
        echo "ℹ️  إصدار MySQL: " . htmlspecialchars($result['mysql_version']) . "<br>";
        
        // Test character set
        $stmt = $pdo->query("SELECT @@character_set_connection as charset");
        $result = $stmt->fetch();
        echo "ℹ️  ترميز الاتصال: " . htmlspecialchars($result['charset']) . "<br>";
        
        // Test a table existence check (if tables exist)
        try {
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "ℹ️  عدد الجداول الموجودة: " . count($tables) . "<br>";
            
            if (count($tables) > 0) {
                echo "📋 الجداول الموجودة:<br>";
                foreach (array_slice($tables, 0, 5) as $table) {
                    echo "  - " . htmlspecialchars($table) . "<br>";
                }
                if (count($tables) > 5) {
                    echo "  ... و " . (count($tables) - 5) . " جداول أخرى<br>";
                }
            }
        } catch (Exception $e) {
            echo "ℹ️  لا توجد جداول في قاعدة البيانات (هذا طبيعي للمشروع الجديد)<br>";
        }
        
        echo "✅ جميع الاختبارات نجحت!<br>";
        
    } catch (Exception $e) {
        echo "❌ خطأ في اختبار الاستعلامات: " . htmlspecialchars($e->getMessage()) . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ خطأ عام: " . htmlspecialchars($e->getMessage()) . "<br>";
    exit(1);
}

echo "<hr>";
echo "<h3>أمثلة الاستخدام</h3>";
echo "<p>يمكنك الآن استخدام ملف database.php في ملفات النظام الجديد:</p>";
echo "<pre>";
echo "<?php\n";
echo "// إدراج ملف قاعدة البيانات\n";
echo "require_once 'config/database.php';\n";
echo "\n";
echo "// استخدام متغير \$pdo\n";
echo "\$stmt = \$pdo->prepare(\"SELECT * FROM forms WHERE status = ?\");\n";
echo "\$stmt->execute(['active']);\n";
echo "\$forms = \$stmt->fetchAll();\n";
echo "\n";
echo "// أو استخدام دالة getDatabaseConnection()\n";
echo "\$pdo = getDatabaseConnection();\n";
echo "\$stmt = \$pdo->prepare(\"INSERT INTO forms (name, description) VALUES (?, ?)\");\n";
echo "\$stmt->execute([\$name, \$description]);\n";
echo "</pre>";

echo "<p><strong>ملاحظة:</strong> في بيئة الإنتاج، يُنصح بإنشاء ملف .env وتحديث متغيرات البيئة بدلاً من تعديل ملف database.php مباشرة.</p>";

?>