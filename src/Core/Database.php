<?php

declare(strict_types=1);

namespace BuraqForms\Core;

use PDO;
use PDOException;

/**
 * Database connection helper.
 *
 * - Database::getConnection() returns a singleton PDO instance.
 * - Database::createConnection() builds a PDO instance from a config array.
 */
final class Database
{
    private static ?PDO $pdo = null;

    private static bool $envLoaded = false;

    public static function getConnection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        self::loadEnvOnce();

        $host = self::env('DB_HOST', 'localhost');
        $user = self::env('DB_USER', 'root');
        $pass = self::env('DB_PASS', '');
        $name = self::env('DB_NAME', 'buraq_forms');
        $charset = self::env('DB_CHARSET', 'utf8mb4');
        $port = (int) self::env('DB_PORT', '3306');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        self::$pdo = new PDO(
            $dsn,
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        self::$pdo->exec('SET NAMES utf8mb4');
        self::$pdo->exec('SET CHARACTER SET utf8mb4');
        self::$pdo->exec("SET time_zone = '+00:00'");

        return self::$pdo;
    }

    /**
     * @param array{host:string,database:string,username:string,password:string,charset:string,port?:int,options?:array<int,mixed>} $config
     */
    public static function createConnection(array $config): PDO
    {
        $port = (int) ($config['port'] ?? 3306);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $port,
            $config['database'],
            $config['charset']
        );

        return new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            $config['options'] ?? []
        );
    }

    private static function loadEnvOnce(): void
    {
        if (self::$envLoaded) {
            return;
        }

        self::$envLoaded = true;

        $root = dirname(__DIR__, 2);
        $path = $root . '/.env';
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

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

            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    private static function env(string $key, string $default): string
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }

        return $default;
    }
}
