<?php

declare(strict_types=1);

namespace EmployeeEvaluationSystem\Core;

use PDO;

/**
 * Database connection class
 */
class Database
{
    public static function createConnection(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'],
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
}