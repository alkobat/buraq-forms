<?php
declare(strict_types=1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define BASE_PATH to locate config
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Load constants
require_once BASE_PATH . '/config/constants.php';

// Load router
require_once __DIR__ . '/router.php';

// Load routes
$routes = require_once CONFIG_PATH . '/routes.php';

// Dispatch
$router = new Router($routes);
$router->dispatch($_SERVER['REQUEST_URI']);
