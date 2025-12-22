<?php
declare(strict_types=1);

if (!defined('CONFIG_PATH')) {
    require_once __DIR__ . '/../config/constants.php';
}

session_start();

// Include required files
require_once SRC_PATH . '/helpers.php';
require_once SRC_PATH . '/Core/Auth.php';

use BuraqForms\Core\Auth;

// Validate session before logout
if (!validate_session()) {
    // Session is invalid, redirect to login
    header('Location: /buraq-forms/public/login.php');
    exit;
}

// Logout user securely using Auth class
Auth::logout_user();

// After logout, redirect to login page with success message
header('Location: /buraq-forms/public/login.php?message=logout_success');
exit;