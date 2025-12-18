<?php

declare(strict_types=1);

namespace BuraqForms\Core\Cache;

/**
 * Simple file-based cache with TTL.
 */
class FileCache
{
    private string $cacheDir;

    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? dirname(__DIR__, 3) . '/cache';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    public function get(string $key): mixed
    {
        $path = $this->pathFor($key);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $payload = @unserialize($raw);
        if (!is_array($payload) || !isset($payload['expires_at'])) {
            return null;
        }

        if ($payload['expires_at'] !== null && time() > (int) $payload['expires_at']) {
            @unlink($path);
            return null;
        }

        return $payload['value'] ?? null;
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 300): void
    {
        $path = $this->pathFor($key);
        $expiresAt = $ttlSeconds > 0 ? time() + $ttlSeconds : null;

        $payload = [
            'expires_at' => $expiresAt,
            'value' => $value,
        ];

        @file_put_contents($path, serialize($payload), LOCK_EX);
    }

    public function delete(string $key): void
    {
        $path = $this->pathFor($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function pathFor(string $key): string
    {
        return rtrim($this->cacheDir, '/') . '/' . hash('sha256', $key) . '.cache';
    }
}
