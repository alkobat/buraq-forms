<?php

declare(strict_types=1);

namespace EmployeeEvaluationSystem\Core\Services;

use EmployeeEvaluationSystem\Core\Cache\FileCache;
use EmployeeEvaluationSystem\Core\Exceptions\DatabaseException;
use PDO;
use PDOException;

/**
 * Read-only access to system_settings.
 */
class SystemSettingsService
{
    private PDO $pdo;
    private ?FileCache $cache;

    public function __construct(PDO $pdo, ?FileCache $cache = null)
    {
        $this->pdo = $pdo;
        $this->cache = $cache;
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        $value = $this->getRaw($key);
        if ($value === null) {
            return $default;
        }

        return (string) $value;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->getRaw($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return (int) $value;
    }

    /**
     * @return list<string>
     */
    public function getJsonList(string $key, array $default = []): array
    {
        $value = $this->getRaw($key);
        if ($value === null) {
            return $default;
        }

        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }

        $decoded = json_decode((string) $value, true);
        if (is_array($decoded)) {
            return array_values(array_map('strval', $decoded));
        }

        return $default;
    }

    public function invalidate(string $key): void
    {
        if ($this->cache === null) {
            return;
        }

        $this->cache->delete('system_setting:' . $key);
    }

    private function getRaw(string $key): mixed
    {
        $cacheKey = 'system_setting:' . $key;
        if ($this->cache !== null) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            $stmt = $this->pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = :key');
            $stmt->execute(['key' => $key]);
            $row = $stmt->fetch();
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to read system setting: ' . $key, 0, $e);
        }

        if (!is_array($row) || !array_key_exists('setting_value', $row)) {
            return null;
        }

        $value = $row['setting_value'];

        if ($this->cache !== null) {
            $this->cache->set($cacheKey, $value, 300);
        }

        return $value;
    }
}
