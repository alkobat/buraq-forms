<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Assuming this is included from a file in public/admin/
    // So we go up one level to find login.php
    header('Location: ../login.php');
    exit;
}

// التحقق من صلاحية الجلسة (اختياري)
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > (2 * 60 * 60)) {
    // انتهت صلاحية الجلسة بعد ساعتين
    session_unset();
    session_destroy();
    header('Location: ../login.php?expired=1');
    exit;
}
