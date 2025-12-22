<?php
declare(strict_types=1);

session_start();

// توجيه بسيط فقط - بدون require_once
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: admin/dashboard.php');
    exit;
}

header('Location: home.php');
exit;
