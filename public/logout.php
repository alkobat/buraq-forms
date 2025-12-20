<?php
declare(strict_types=1);

session_start();

// إنهاء الجلسة
$_SESSION = [];

// حذف session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// تدمير الجلسة
session_destroy();

// إعادة التوجيه للصفحة الرئيسية
header('Location: home.php');
exit;