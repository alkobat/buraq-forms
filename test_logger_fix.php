<?php
/**
 * Test script to verify Logger class fix
 * This test verifies that Logger works both as static and instance
 */

// Test 1: Check if Logger class exists and can be loaded
require_once 'src/Core/Logger.php';
use BuraqForms\Core\Logger;

echo "✅ Logger class loaded successfully\n";

// Test 2: Check static methods (used by Auth.php)
try {
    Logger::error('Test static error from Auth.php style', ['test' => true]);
    echo "✅ Static Logger::error() works\n";
} catch (Exception $e) {
    echo "❌ Static Logger::error() failed: " . $e->getMessage() . "\n";
}

try {
    Logger::info('Test static info', ['test' => true]);
    echo "✅ Static Logger::info() works\n";
} catch (Exception $e) {
    echo "❌ Static Logger::info() failed: " . $e->getMessage() . "\n";
}

// Test 3: Check instance methods (used by services)
try {
    $logger = new Logger();
    $logger->error('Test instance error from service style', ['service' => true]);
    echo "✅ Instance Logger->error() works\n";
} catch (Exception $e) {
    echo "❌ Instance Logger->error() failed: " . $e->getMessage() . "\n";
}

try {
    $logger = new Logger();
    $logger->info('Test instance info', ['service' => true]);
    echo "✅ Instance Logger->info() works\n";
} catch (Exception $e) {
    echo "❌ Instance Logger->info() failed: " . $e->getMessage() . "\n";
}

// Test 4: Check if log file is created and has content
if (file_exists('storage/logs/app.log')) {
    $content = file_get_contents('storage/logs/app.log');
    if (!empty(trim($content))) {
        echo "✅ Log file created and contains entries\n";
        echo "Log content preview:\n";
        echo substr($content, -200) . "\n";
    } else {
        echo "⚠️ Log file exists but is empty\n";
    }
} else {
    echo "❌ Log file was not created\n";
}

echo "\n🎉 Logger fix test completed!\n";
echo "The Logger class now supports both:\n";
echo "1. Static usage: Logger::error() - used by Auth.php\n";  
echo "2. Instance usage: new Logger() - used by services\n";