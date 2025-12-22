<?php
// توجيه الصفحة الرئيسية
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: ' . APP_URL . '/admin/dashboard');
} else {
    header('Location: ' . APP_URL . '/home');
}
exit;
