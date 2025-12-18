<?php
declare(strict_types=1);

/**
 * System Health Check
 * فحص صحة النظام الكامل
 */

require 'config/database.php';

// تحميل Services إذا كانت موجودة
$autoloadFile = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadFile)) {
    require $autoloadFile;
}

echo "\n╔════════════════════════════════════════════╗\n";
echo "║     فحص صحة نظام BuraqForms              ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

// 1. PHP Version
echo "📌 PHP Version: " . phpversion() . "\n";

// 2. Database Connection
try {
    $pdo->query("SELECT 1");
    echo "✅ Database: متصل\n";
} catch (Exception $e) {
    echo "❌ Database: قطع\n";
}

// 3. Required Directories
$dirs = [
    'public' => 'public/',
    'config' => 'config/',
    'src' => 'src/',
    'storage' => 'storage/',
];

echo "\n📁 الملفات والمجلدات:\n";
foreach ($dirs as $name => $path) {
    if (is_dir($path)) {
        echo "✅ $name: موجود\n";
    } else {
        echo "❌ $name: غير موجود\n";
    }
}

// 4. Storage Permissions
echo "\n🔐 الصلاحيات:\n";
if (is_writable('storage/')) {
    echo "✅ storage/: قابل للكتابة\n";
} else {
    echo "❌ storage/: غير قابل للكتابة\n";
}

echo "\n✨ فحص النظام اكتمل!\n\n";
