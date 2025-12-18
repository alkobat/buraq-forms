<?php

declare(strict_types=1);

use PDO;
use PDOException;

/**
 * Database Configuration and PDO Connection (PDO + utf8mb4)
 *
 * Provides:
 * - global $pdo
 * - getDatabaseConnection(): PDO
 * - testDatabaseConnection(): bool
 * - getDatabaseConfig(): array
 */

// ---------------------------------------------------------------------
// .env loader (minimal, no dependencies)
// ---------------------------------------------------------------------

/**
 * @return array<string, string>
 */
function loadEnvFile(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $vars = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));

        if ($key === '') {
            continue;
        }

        // Strip surrounding quotes
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        $vars[$key] = $value;
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }

    return $vars;
}

loadEnvFile(dirname(__DIR__) . '/.env');

// ---------------------------------------------------------------------
// Defaults (can be overridden by .env)
// ---------------------------------------------------------------------

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: '');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'buraq_forms');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
}

// ---------------------------------------------------------------------
// Connection helpers
// ---------------------------------------------------------------------

/**
 * @throws PDOException
 */
function getDatabaseConnection(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $connection = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Ensure unicode + predictable time handling
    $connection->exec('SET NAMES utf8mb4');
    $connection->exec('SET CHARACTER SET utf8mb4');
    $connection->exec("SET time_zone = '+00:00'");

    return $connection;
}

function testDatabaseConnection(): bool
{
    try {
        $pdo = getDatabaseConnection();
        $pdo->query('SELECT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

/**
 * @return array{host:string,database:string,username:string,charset:string,port:int,connected:bool}
 */
function getDatabaseConfig(): array
{
    return [
        'host' => (string) DB_HOST,
        'database' => (string) DB_NAME,
        'username' => (string) DB_USER,
        'charset' => (string) DB_CHARSET,
        'port' => (int) DB_PORT,
        'connected' => testDatabaseConnection(),
    ];
}

// ---------------------------------------------------------------------
// Global PDO instance (backwards compatible)
// ---------------------------------------------------------------------

try {
    /** @var PDO $pdo */
    $pdo = getDatabaseConnection();
} catch (PDOException $e) {
    error_log('Database Connection Error: ' . $e->getMessage());
    http_response_code(500);
    die('خطأ في الاتصال بقاعدة البيانات. يرجى محاولة لاحقاً.');
}
