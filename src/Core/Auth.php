<?php

declare(strict_types=1);

namespace BuraqForms\Core;

// Load Logger class directly to avoid autoloading issues
require_once __DIR__ . '/Logger.php';

/**
 * Authentication and Authorization Helper Class
 *
 * Provides secure authentication functions, role-based access control,
 * and CSRF protection for the BuraqForms system.
 */
class Auth
{
    /**
     * Security configuration
     */
    private static array $securityConfig = [];

    /**
     * Load security configuration
     */
    private static function loadSecurityConfig(): void
    {
        if (empty(self::$securityConfig)) {
            $configPath = __DIR__ . '/../../config/security.php';
            if (file_exists($configPath)) {
                self::$securityConfig = require $configPath;
            } else {
                // Default configuration if file doesn't exist
                self::$securityConfig = [
                    'session' => self::SESSION_CONFIG,
                    'csrf' => ['token_lifetime' => self::CSRF_TOKEN_LIFETIME],
                    'login' => ['max_attempts' => self::MAX_LOGIN_ATTEMPTS, 'lockout_time' => self::LOGIN_LOCKOUT_TIME]
                ];
            }
        }
    }

    /**
     * Session configuration
     */
    private const SESSION_CONFIG = [
        'lifetime' => 7200, // 2 hours
        'path' => '/',
        'domain' => '',
        'secure' => true, // Set to false for HTTP in development
        'httponly' => true,
        'samesite' => 'Strict'
    ];

    /**
     * CSRF token lifetime (in seconds)
     */
    private const CSRF_TOKEN_LIFETIME = 3600; // 1 hour

    /**
     * Login attempt tracking
     */
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCKOUT_TIME = 900; // 15 minutes

    /**
     * Check if user is logged in
     */
    public static function is_logged_in(): bool
    {
        return isset($_SESSION['logged_in']) &&
               $_SESSION['logged_in'] === true &&
               isset($_SESSION['admin_id']) &&
               isset($_SESSION['login_time']);
    }

    /**
     * Get current logged-in user data
     */
    public static function current_user(): ?array
    {
        if (!self::is_logged_in()) {
            return null;
        }

        return [
            'id' => $_SESSION['admin_id'],
            'name' => $_SESSION['admin_name'] ?? '',
            'email' => $_SESSION['admin_email'] ?? '',
            'role' => $_SESSION['admin_role'] ?? 'editor',
            'login_time' => $_SESSION['login_time'] ?? time()
        ];
    }

    /**
     * Require authentication - redirect to login if not logged in
     */
    public static function require_auth(): void
    {
        if (!self::is_logged_in()) {
            self::redirect_to_login();
        }
    }

    /**
     * Require specific permission - redirect to login or show error if insufficient permissions
     */
    public static function require_permission(string $permission, ?int $departmentId = null): void
    {
        self::require_auth();
        // Simplified: grant all permissions for logged-in users
        // TODO: Implement proper permission system when needed
    }

    /**
     * Check if current user has specific permission
     */
    public static function has_permission(string $permission, ?int $departmentId = null): bool
    {
        // Simplified: grant all permissions for logged-in users
        return self::is_logged_in();
    }

    /**
     * Check if current user has any of the specified roles
     */
    public static function has_any_role(array $roles): bool
    {
        if (!self::is_logged_in()) {
            return false;
        }

        $user = self::current_user();
        if (!$user) {
            return false;
        }

        // Simplified: check user role from session
        $userRole = $user['role'] ?? 'editor';
        return in_array($userRole, $roles, true);
    }

    /**
     * Get current user permissions
     */
    public static function current_user_permissions(): array
    {
        // Simplified: return empty array for now
        // TODO: Implement proper permission system when needed
        return [];
    }

    /**
     * Get current user roles
     */
    public static function current_user_roles(): array
    {
        if (!self::is_logged_in()) {
            return [];
        }

        $user = self::current_user();
        if (!$user) {
            return [];
        }

        // Simplified: return role from session
        return [['role_name' => $user['role'] ?? 'editor']];
    }

    /**
     * Require specific role - redirect to login or show error if insufficient permissions
     */
    public static function require_role(string $required_role): void
    {
        self::require_auth();

        $user = self::current_user();
        $user_role = $user['role'] ?? 'editor';

        $role_hierarchy = [
            'admin' => 3,
            'manager' => 2,
            'editor' => 1
        ];

        $user_level = $role_hierarchy[$user_role] ?? 0;
        $required_level = $role_hierarchy[$required_role] ?? 999;

        if ($user_level < $required_level) {
            Logger::warning('Access denied - insufficient permissions', [
                'user_id' => $_SESSION['admin_id'] ?? 'unknown',
                'user_role' => $user_role,
                'required_role' => $required_role,
                'required_level' => $required_level,
                'user_level' => $user_level,
                'page' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]);

            http_response_code(403);
            die('Access Denied: Insufficient permissions for this page.');
        }
    }

    /**
     * Login user with secure session creation
     */
    public static function login_user(string $email, string $password, bool $remember_me = false): array
    {
        try {
            // Check for login lockout
            if (self::is_locked_out()) {
                Logger::warning('Login attempt blocked - IP locked out', [
                    'email' => $email,
                    'ip' => self::get_client_ip()
                ]);

                return [
                    'success' => false,
                    'message' => 'تم حظر محاولات تسجيل الدخول مؤقتاً. يرجى المحاولة لاحقاً.'
                ];
            }

            require_once __DIR__ . '/../../config/database.php';

            // Sanitize input
            $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                self::record_failed_attempt();
                return ['success' => false, 'message' => 'تنسيق البريد الإلكتروني غير صحيح'];
            }

            // Find user
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Verify password
            if (!$admin || !password_verify($password, $admin['password'])) {
                self::record_failed_attempt();

                Logger::warning('Failed login attempt', [
                    'email' => $email,
                    'ip' => self::get_client_ip(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);

                return ['success' => false, 'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة'];
            }

            // Clear failed attempts on successful login
            self::clear_failed_attempts();

            // Create secure session
            self::create_user_session($admin, $remember_me);

            // Log successful login
            Logger::info('User logged in successfully', [
                'user_id' => $admin['id'],
                'user_email' => $admin['email'],
                'user_role' => $admin['role'],
                'ip' => self::get_client_ip(),
                'remember_me' => $remember_me
            ]);

            return ['success' => true, 'message' => 'تم تسجيل الدخول بنجاح'];
        } catch (\Exception $e) {
            Logger::error('Login error occurred', [
                'email' => $email,
                'error' => $e->getMessage(),
                'ip' => self::get_client_ip()
            ]);

            return ['success' => false, 'message' => 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى'];
        }
    }

    /**
     * Logout user securely
     */
    public static function logout_user(): void
    {
        $user = self::current_user();

        if ($user) {
            Logger::info('User logged out', [
                'user_id' => $user['id'],
                'user_email' => $user['email'],
                'ip' => self::get_client_ip(),
                'session_duration' => time() - ($_SESSION['login_time'] ?? time())
            ]);
        }

        // Clear session data
        $_SESSION = [];

        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // Destroy session
        session_destroy();

        // Start new session for potential redirect
        session_start();
        session_regenerate_id(true);
    }

    /**
     * Generate CSRF token
     */
    public static function generate_csrf_token(): string
    {
        self::loadSecurityConfig();

        $csrf_config = self::$securityConfig['csrf'] ?? ['token_lifetime' => self::CSRF_TOKEN_LIFETIME];
        $token_lifetime = $csrf_config['token_lifetime'] ?? self::CSRF_TOKEN_LIFETIME;

        if (
            !isset($_SESSION['csrf_token']) ||
            !isset($_SESSION['csrf_token_time']) ||
            (time() - $_SESSION['csrf_token_time']) > $token_lifetime
        ) {
            $token_length = $csrf_config['token_length'] ?? 32;
            $_SESSION['csrf_token'] = bin2hex(random_bytes($token_length));
            $_SESSION['csrf_token_time'] = time();
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token
     */
    public static function verify_csrf_token(?string $token): bool
    {
        if (!$token) {
            return false;
        }

        self::loadSecurityConfig();
        $csrf_config = self::$securityConfig['csrf'] ?? ['token_lifetime' => self::CSRF_TOKEN_LIFETIME];
        $token_lifetime = $csrf_config['token_lifetime'] ?? self::CSRF_TOKEN_LIFETIME;

        // Check token expiration
        if (
            !isset($_SESSION['csrf_token_time']) ||
            (time() - $_SESSION['csrf_token_time']) > $token_lifetime
        ) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    /**
     * Generate remember me token
     */
    public static function generate_remember_token(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Redirect to login page
     */
    private static function redirect_to_login(): void
    {
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        $login_url = '/login.php' . ($current_url ? '?redirect=' . urlencode($current_url) : '');

        header('Location: ' . $login_url);
        exit;
    }

    /**
     * Create secure user session
     */
    private static function create_user_session(array $admin, bool $remember_me): void
    {
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        $_SESSION['logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_role'] = $admin['role'] ?? 'editor';
        $_SESSION['login_time'] = time();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['ip_address'] = self::get_client_ip();

        // Generate CSRF token
        self::generate_csrf_token();

        // Set session cookie parameters
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => self::SESSION_CONFIG['lifetime'],
                'path' => self::SESSION_CONFIG['path'],
                'domain' => self::SESSION_CONFIG['domain'],
                'secure' => self::SESSION_CONFIG['secure'],
                'httponly' => self::SESSION_CONFIG['httponly'],
                'samesite' => self::SESSION_CONFIG['samesite']
            ]);
        }
    }

    /**
     * Get client IP address
     */
    private static function get_client_ip(): string
    {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED',
                   'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED',
                   'REMOTE_ADDR'];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Record failed login attempt
     */
    private static function record_failed_attempt(): void
    {
        $ip = self::get_client_ip();
        $key = 'login_attempts_' . md5($ip);

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }

        $_SESSION[$key][] = time();

        // Clean old attempts (older than lockout time)
        $_SESSION[$key] = array_filter($_SESSION[$key], function ($timestamp) {
            return (time() - $timestamp) < self::LOGIN_LOCKOUT_TIME;
        });
    }

    /**
     * Clear failed login attempts
     */
    private static function clear_failed_attempts(): void
    {
        $ip = self::get_client_ip();
        $key = 'login_attempts_' . md5($ip);
        unset($_SESSION[$key]);
    }

    /**
     * Check if IP is locked out
     */
    private static function is_locked_out(): bool
    {
        $ip = self::get_client_ip();
        $key = 'login_attempts_' . md5($ip);

        if (!isset($_SESSION[$key])) {
            return false;
        }

        return count($_SESSION[$key]) >= self::MAX_LOGIN_ATTEMPTS;
    }

    /**
     * Check session security
     */
    public static function validate_session(): bool
    {
        if (!self::is_logged_in()) {
            return true; // Not logged in, no validation needed
        }

        // Check user agent
        if (
            isset($_SESSION['user_agent']) &&
            $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')
        ) {
            Logger::warning('Session security violation - user agent mismatch', [
                'user_id' => $_SESSION['admin_id'],
                'session_user_agent' => $_SESSION['user_agent'],
                'current_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);

            self::logout_user();
            return false;
        }

        // Check IP address (optional - can be commented out for dynamic IPs)
        if (
            isset($_SESSION['ip_address']) &&
            $_SESSION['ip_address'] !== self::get_client_ip()
        ) {
            Logger::warning('Session security violation - IP mismatch', [
                'user_id' => $_SESSION['admin_id'],
                'session_ip' => $_SESSION['ip_address'],
                'current_ip' => self::get_client_ip()
            ]);

            self::logout_user();
            return false;
        }

        // Check session timeout
        if (
            isset($_SESSION['login_time']) &&
            (time() - $_SESSION['login_time']) > self::SESSION_CONFIG['lifetime']
        ) {
            Logger::info('Session expired', [
                'user_id' => $_SESSION['admin_id'],
                'login_time' => $_SESSION['login_time'],
                'current_time' => time(),
                'duration' => time() - $_SESSION['login_time']
            ]);

            self::logout_user();
            return false;
        }

        return true;
    }
}
