<?php
if (!defined('CONFIG_PATH')) {
    require_once __DIR__ . '/../../config/constants.php';
}
// صفحة خطأ 500
$pageTitle = 'خطأ في الخادم';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - خطأ في الخادم</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            text-align: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
        }
        h1 {
            font-size: 80px;
            margin: 0;
            color: #f39c12;
        }
        h2 {
            margin-top: 10px;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>500</h1>
        <h2>خطأ في الخادم</h2>
        <p>حدث خطأ غير متوقع في الخادم. يرجى المحاولة مرة أخرى لاحقاً.</p>
        <a href="<?php echo defined('APP_URL') ? APP_URL : '/'; ?>" class="btn">العودة للرئيسية</a>
    </div>
</body>
</html>
