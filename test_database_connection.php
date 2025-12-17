<?php
/**
 * Test Database Connection Script
 * 
 * This script tests the database connection created in config/database.php
 */

// Include the database configuration
require_once __DIR__ . '/config/database.php';

echo "=== اختبار اتصال قاعدة البيانات ===\n\n";

try {
    // Test the connection
    if (testDatabaseConnection()) {
        echo "✅ تم الاتصال بقاعدة البيانات بنجاح!\n\n";
        
        // Get database configuration info
        $config = getDatabaseConfig();
        echo "معلومات الاتصال:\n";
        echo "- المضيف: " . $config['host'] . "\n";
        echo "- قاعدة البيانات: " . $config['database'] . "\n";
        echo "- الترميز: " . $config['charset'] . "\n";
        echo "- المنفذ: " . $config['port'] . "\n\n";
        
        // Test a simple query
        global $pdo;
        $stmt = $pdo->query("SELECT VERSION() as mysql_version");
        $result = $stmt->fetch();
        echo "إصدار MySQL: " . $result['mysql_version'] . "\n";
        
        // Test character set
        $stmt = $pdo->query("SELECT @@character_set_connection as charset");
        $result = $stmt->fetch();
        echo "ترميز الاتصال: " . $result['charset'] . "\n\n";
        
        echo "✅ جميع الاختبارات نجحت!\n";
        
    } else {
        echo "❌ فشل الاتصال بقاعدة البيانات!\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}