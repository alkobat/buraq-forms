<?php
declare(strict_types=1);

session_start();

// بدلاً من header redirect، استخدم require
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // المستخدم مسجل دخول - حمل لوحة التحكم
    require_once __DIR__ . '/admin/dashboard.php';
    exit;
}

// المستخدم غير مسجل دخول - حمل صفحة البداية
require_once __DIR__ . '/home.php';
exit;
