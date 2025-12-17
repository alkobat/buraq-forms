<?php
declare(strict_types=1);

/**
 * Database Configuration and PDO Connection
 * 
 * This file contains the database connection setup using PDO
 * with UTF-8 support for Arabic and proper error handling
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'buraq-forms');
define('DB_CHARSET', 'utf8mb4');

try {
    // Create DSN (Data Source Name)
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    
    // Create PDO connection
    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            // Set error mode to throw exceptions
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            
            // Set default fetch mode to associative array
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            
            // Disable prepared statement emulation for security
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    
    // Set character set to UTF-8 (for full Arabic support)
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    
} catch (PDOException $e) {
    // Log error and display user-friendly message
    error_log('Database Connection Error: ' . $e->getMessage());
    die('خطأ في الاتصال بقاعدة البيانات. يرجى محاولة لاحقاً.');
}

?>