<?php

declare(strict_types=1);

namespace BuraqForms\Core;

/**
 * Hybrid logger that supports both static and instance usage.
 */
class Logger
{
    private static string $defaultLogFile = __DIR__ . '/../../storage/logs/app.log';
    private string $logFile;

    public function __construct(?string $logFile = null)
    {
        $this->logFile = $logFile ?? self::$defaultLogFile;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        self::writeToFile($this->logFile, 'INFO', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        self::writeToFile($this->logFile, 'WARN', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        self::writeToFile($this->logFile, 'ERROR', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        self::writeToFile($this->logFile, 'DEBUG', $message, $context);
    }

    // Static methods for Auth.php compatibility

    /**
     * @param array<string, mixed> $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::writeToFile(self::$defaultLogFile, 'INFO', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::writeToFile(self::$defaultLogFile, 'WARN', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::writeToFile(self::$defaultLogFile, 'ERROR', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function debug(string $message, array $context = []): void
    {
        self::writeToFile(self::$defaultLogFile, 'DEBUG', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function writeToFile(string $logFile, string $level, string $message, array $context): void
    {
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $line = sprintf('[%s] %s: %s', $timestamp, $level, $message);

        if ($context !== []) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $line .= PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
