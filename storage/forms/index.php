<?php
/**
 * Index file for forms storage directory
 * Prevents directory browsing and shows access denied message
 */

// Set response headers
header('HTTP/1.0 403 Forbidden');
header('Content-Type: text/html; charset=utf-8');

// Disable error reporting for security
error_reporting(0);
ini_set('display_errors', 0);

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الوصول مرفوض</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: white;
        }
        .container {
            text-align: center;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        h1 {
            margin: 0;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        p {
            font-size: 1.2rem;
            margin: 0;
            opacity: 0.9;
        }
        .timestamp {
            margin-top: 2rem;
            font-size: 0.9rem;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🚫</div>
        <h1>الوصول مرفوض</h1>
        <p>ليس لديك صلاحية للوصول إلى هذا المجلد</p>
        <p>جميع الملفات محمية ومؤمنة</p>
        <div class="timestamp">
            <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>
</body>
</html>