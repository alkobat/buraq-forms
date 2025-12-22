<?php

// توجيه الصفحة الرئيسية
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: /buraq-forms/public/admin/dashboard.php');
} else {
    header('Location: /buraq-forms/public/home.php');
}
exit;
