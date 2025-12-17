<?php

declare(strict_types=1);

namespace EmployeeEvaluationSystem\Core;

use EmployeeEvaluationSystem\Core\Exceptions\DatabaseException;
use PDO;
use PDOException;

/**
 * PDO connection factory with connection sharing.
 */
final class Database
{
    private static ?PDO $pdo = null;

    /**
     * Allows tests/bootstrap code to set an existing PDO instance.
     */
    public static function setConnection(PDO $pdo): void
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$pdo = $pdo;
    }

    /**
     * @param array{dsn?:string,user?:string,password?:string,options?:array<int,mixed>} $config
     */
    public static function getConnection(array $config = []): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = $config['dsn'] ?? getenv('DB_DSN');
        $user = $config['user'] ?? getenv('DB_USER');
        $password = $config['password'] ?? getenv('DB_PASSWORD');

        if (!is_string($dsn) || $dsn === '') {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $name = getenv('DB_NAME') ?: 'employee_evaluation_system';
            $charset = getenv('DB_CHARSET') ?: 'utf8mb4';
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);
        }

        $options = $config['options'] ?? [];
        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
        $options[PDO::ATTR_EMULATE_PREPARES] = false;

        try {
            self::$pdo = new PDO($dsn, $user ?: null, $password ?: null, $options);
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to connect to the database: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }
}
