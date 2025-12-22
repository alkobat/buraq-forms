<?php
declare(strict_types=1);

session_start();

// الحصول على المسار الأساسي
$base_url = '/buraq-forms/public/';

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: ' . $base_url . 'admin/dashboard.php');
    exit;
}

header('Location: ' . $base_url . 'home.php');
exit;
