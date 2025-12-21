<?php
declare(strict_types=1);

namespace BuraqForms\Core;

class Logger
{
    private static string $logDir = __DIR__ . '/../../storage/logs';
    
    public static function log(string $message, string $level = 'INFO', array $context = []): void
    {
        // إنشاء مجلد logs إذا لم يكن موجوداً
        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0755, true);
        }
        
        $logFile = self::$logDir . '/app.log';
        $timestamp = date('Y-m-d H:i:s');
        
        // إضافة context إذا وجد
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        $logMessage = "[$timestamp] [$level] $message$contextStr\n";
        
        // كتابة آمنة مع FILE_APPEND
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    public static function info(string $message, array $context = []): void
    {
        self::log($message, 'INFO', $context);
    }
    
    public static function error(string $message, array $context = []): void
    {
        self::log($message, 'ERROR', $context);
    }
    
    public static function warning(string $message, array $context = []): void
    {
        self::log($message, 'WARNING', $context);
    }
    
    public static function debug(string $message, array $context = []): void
    {
        self::log($message, 'DEBUG', $context);
    }
}
