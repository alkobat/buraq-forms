<?php

declare(strict_types=1);

session_start();

// Include required files
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Core/Auth.php';

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
