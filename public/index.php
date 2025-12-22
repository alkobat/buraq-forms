<?php
declare(strict_types=1);

session_start();

// Load constants
require_once CONFIG_PATH . '/constants.php';

// Load router
require_once __DIR__ . '/router.php';

// Load routes
$routes = require_once CONFIG_PATH . '/routes.php';

// Dispatch
$router = new Router($routes);
$router->dispatch($_SERVER['REQUEST_URI']);
