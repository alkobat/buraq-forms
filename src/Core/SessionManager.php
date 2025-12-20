<?php
declare(strict_types=1);

namespace BuraqForms\Core;

class SessionManager
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function setUser(array $user): void
    {
        self::startSession();
        $_SESSION['user'] = $user;
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
    }

    public static function getUser(): ?array
    {
        self::startSession();
        return $_SESSION['user'] ?? null;
    }

    public static function isLoggedIn(): bool
    {
        self::startSession();
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public static function logout(): void
    {
        self::startSession();
        session_unset();
        session_destroy();
    }

    public static function generateCSRFToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCSRFToken(string $token): bool
    {
        self::startSession();
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}
