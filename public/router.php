<?php
declare(strict_types=1);

if (!defined('CONFIG_PATH')) {
    // Fallback if not loaded via index.php
    require_once __DIR__ . '/../config/constants.php';
}

class Router
{
    private array $routes;

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public function dispatch(string $uri): void
    {
        // تنظيف المسار
        $path = parse_url($uri, PHP_URL_PATH);
        
        // معالجة المسار إذا كان السكربت في مجلد فرعي
        $scriptName = $_SERVER['SCRIPT_NAME']; // e.g. /buraq-forms/public/index.php
        $scriptDir = dirname($scriptName); // e.g. /buraq-forms/public
        
        // إذا كان المسار يبدأ بمسار المجلد الفرعي، نحذفه لنحصل على المسار النسبي
        if ($scriptDir !== '/' && strpos($path, $scriptDir) === 0) {
            $path = substr($path, strlen($scriptDir));
        } elseif (strpos($path, '/index.php') === 0) {
             // Handle cases where index.php is in the URL explicitly
             $path = substr($path, strlen('/index.php'));
        }

        $path = '/' . trim($path, '/');
        
        if ($path === '/') {
            // Check specific logic for root
        }
        
        // Remove .php extension if present for matching
        $cleanPath = $path;
        if (str_ends_with($path, '.php')) {
            $cleanPath = substr($path, 0, -4);
        }

        // البحث عن المسار في القائمة (clean path)
        if (array_key_exists($cleanPath, $this->routes)) {
            $this->loadRoute($this->routes[$cleanPath]);
            return;
        }

        // البحث عن المسار كما هو (exact match)
        if (array_key_exists($path, $this->routes)) {
            $this->loadRoute($this->routes[$path]);
            return;
        }

        // محاولة البحث مع إضافة .php (للتوافق - إذا كان المسار المعرف في الراوت يحتوي على .php أو هو اسم ملف)
        if (array_key_exists($path . '.php', $this->routes)) {
            $this->loadRoute($this->routes[$path . '.php']);
            return;
        }

        // 404 Not Found
        $this->handleError(404);
    }

    private function loadRoute(string $file): void
    {
        // الملفات محددة نسبة إلى public folder في الـ routes configuration عادةً
        // ولكن حسب config/routes.php نحن حددنا المسار النسبي مثل admin/dashboard.php
        
        $filePath = PUBLIC_PATH . '/' . $file;

        if (file_exists($filePath)) {
            // نستخدم المتغيرات العالمية لجعلها متاحة داخل الملف المضمن
            // (رغم أن هذا ليس أفضل ممارسة، لكنه ضروري للكود القديم)
            require $filePath;
        } else {
            $this->handleError(500, "Route file not found: " . $file);
        }
    }

    public function handleError(int $code, string $message = ''): void
    {
        http_response_code($code);
        
        $errorFile = PUBLIC_PATH . "/errors/{$code}.php";
        
        if (file_exists($errorFile)) {
            require $errorFile;
        } else {
            // Fallback template
            echo "<div style='text-align:center; padding: 50px; font-family: sans-serif;'>";
            echo "<h1 style='color: #e74c3c'>Error {$code}</h1>";
            echo "<p>" . ($code === 404 ? 'Page Not Found' : 'Server Error') . "</p>";
            if ($message) {
                echo "<p style='color: #7f8c8d; font-size: 0.9em;'>Debug: " . htmlspecialchars($message) . "</p>";
            }
            echo "<a href='" . APP_URL . "'>Return Home</a>";
            echo "</div>";
        }
        exit;
    }
}
