<?php
declare(strict_types=1);

session_start();

// إذا كان مسجل دخول بالفعل، أعد التوجيه للداشبورد
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: admin/dashboard.php');
    exit;
}

$error = '';

// معالجة نموذج تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // التحقق من وجود الإيميل وكلمة المرور
    if (!empty($email) && !empty($password)) {
        try {
            require_once __DIR__ . '/../../config/database.php';
            
            // البحث عن المستخدم
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin && password_verify($password, $admin['password'])) {
                // تسجيل الدخول بنجاح
                $_SESSION['logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_role'] = $admin['role'];
                
                // إعادة التوجيه للداشبورد
                header('Location: admin/dashboard.php');
                exit;
            } else {
                $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
            }
        } catch (Exception $e) {
            $error = 'خطأ في الاتصال بقاعدة البيانات';
        }
    } else {
        $error = 'يرجى إدخال جميع البيانات المطلوبة';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام الاستمارات الديناميكية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
        }
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header i {
            font-size: 60px;
            color: var(--primary);
            margin-bottom: 15px;
        }
        .login-header h2 {
            color: var(--secondary);
            margin-bottom: 5px;
        }
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        .form-floating {
            margin-bottom: 15px;
        }
        .btn-login {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            padding: 12px;
            font-weight: bold;
            width: 100%;
            border-radius: 8px;
            color: white;
            transition: transform 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .alert {
            border-radius: 8px;
            font-size: 14px;
        }
        .back-home {
            text-align: center;
            margin-top: 20px;
        }
        .back-home a {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
        }
        .back-home a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-lock"></i>
            <h2>تسجيل الدخول</h2>
            <p>أدخل بياناتك للوصول للنظام</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-floating">
                <input type="email" class="form-control" id="email" name="email" 
                       placeholder="name@example.com" required>
                <label for="email">
                    <i class="fas fa-envelope me-2"></i>البريد الإلكتروني
                </label>
            </div>

            <div class="form-floating">
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="كلمة المرور" required>
                <label for="password">
                    <i class="fas fa-lock me-2"></i>كلمة المرور
                </label>
            </div>

            <button type="submit" class="btn btn-login">
                <i class="fas fa-sign-in-alt me-2"></i>
                تسجيل الدخول
            </button>
        </form>

        <div class="back-home">
            <a href="home.php">
                <i class="fas fa-home me-2"></i>
                العودة للصفحة الرئيسية
            </a>
        </div>

        <div class="text-center mt-3" style="font-size: 12px; color: #999;">
            <p>للاختبار: admin@buraqforms.com / password123</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>