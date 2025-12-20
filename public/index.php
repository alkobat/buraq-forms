<?php
declare(strict_types=1);

session_start();

// إذا كان مسجل دخول، أعد التوجيه للداشبورد
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: admin/dashboard.php');
    exit;
}

// إذا كان غير مسجل دخول، أعد التوجيه لصفحة تسجيل الدخول
header('Location: home.php');
exit;