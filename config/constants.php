<?php

/**
 * Global Path Constants
 *
 * Define project-wide path constants to avoid using relative paths.
 * These constants are loaded before any other file in the application.
 */

declare(strict_types=1);

// Base project path (one level up from config/)
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Public directory path
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', BASE_PATH . '/public');
}

// Config directory path
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', BASE_PATH . '/config');
}

// Source code directory path
if (!defined('SRC_PATH')) {
    define('SRC_PATH', BASE_PATH . '/src');
}

// Storage directory path
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', BASE_PATH . '/storage');
}

// Vendor directory path (Composer)
if (!defined('VENDOR_PATH')) {
    define('VENDOR_PATH', BASE_PATH . '/vendor');
}

// Application URL (can be overridden by environment variable)
if (!defined('APP_URL')) {
    define('APP_URL', getenv('APP_URL') ?: 'http://localhost/buraq-forms/public');
}
