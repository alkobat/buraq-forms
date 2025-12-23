<?php

declare(strict_types=1);

if (!defined('CONFIG_PATH')) {
    require_once __DIR__ . '/../config/constants.php';
}

session_start();

// Include required files
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Core/Auth.php';

use BuraqForms\Core\Auth;

// إذا كان مسجل دخول بالفعل، أعد التوجيه للداشبورد
if (is_logged_in()) {
    header('Location: /buraq-forms/public/admin/dashboard.php');
    exit;
}

$error = '';
$success = '';
$remember_me = false;

// معالجة نموذج تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token Verification
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'رمز الأمان غير صحيح. يرجى إعادة تحميل الصفحة والمحاولة مرة أخرى.';
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);

        // التحقق من وجود الإيميل وكلمة المرور
        if (!empty($email) && !empty($password)) {
            // Validate and sanitize input
            $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'تنسيق البريد الإلكتروني غير صحيح';
            } else {
                // Use Auth class for login
                $result = Auth::login_user($email, $password, $remember_me);

                if ($result['success']) {
                    // Get redirect URL if provided
                    $redirect = $_GET['redirect'] ?? '/buraq-forms/public/admin/dashboard.php';

                    // Validate redirect URL to prevent open redirect
                    if (!preg_match('/^[a-zA-Z0-9_\-\/\.?&=]*$/', $redirect)) {
                        $redirect = '/buraq-forms/public/admin/dashboard.php';
                    }

                    header('Location: ' . $redirect);
                    exit;
                } else {
                    $error = $result['message'];
                }
            }
        } else {
            $error = 'يرجى إدخال جميع البيانات المطلوبة';
        }
    }
}

// Generate CSRF token for the form
$csrf_token = generate_csrf_token();
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
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
        }
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 450px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
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
            font-weight: 700;
        }
        .login-header p {
            color: #666;
            font-size: 14px;
            margin-bottom: 0;
        }
        .form-floating {
            margin-bottom: 20px;
        }
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .form-check {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .form-check-input {
            margin-top: 0.3em;
        }
        .form-check-label {
            font-size: 14px;
            color: #666;
        }
        .btn-login {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            padding: 15px;
            font-weight: bold;
            width: 100%;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .alert {
            border-radius: 10px;
            font-size: 14px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
        }
        .alert-success {
            background: linear-gradient(135deg, #51cf66, #40c057);
            color: white;
        }
        .security-notice {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 10px 15px;
            margin: 20px 0;
            font-size: 12px;
            color: #1565c0;
        }
        .security-notice i {
            margin-left: 5px;
        }
        .back-home {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .back-home a {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }
        .back-home a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }
        .test-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 13px;
            color: #856404;
        }
        .test-info strong {
            color: #533f03;
        }
        .loading {
            display: none;
        }
        .btn-login.loading .spinner-border {
            display: inline-block !important;
        }
        @media (max-width: 576px) {
            .login-card {
                padding: 30px 20px;
                margin: 10px;
            }
            .login-header i {
                font-size: 50px;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-shield-alt"></i>
            <h2>تسجيل الدخول الآمن</h2>
            <p>أدخل بياناتك للوصول للنظام</p>
        </div>

        <?php if (!empty($error)) : ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)) : ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="security-notice">
            <i class="fas fa-info-circle"></i>
            <strong>حماية متقدمة:</strong> هذا النظام محمي بـ CSRF وSession Security
        </div>

        <form method="POST" action="" id="loginForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="form-floating">
                <input type="email" 
                       class="form-control" 
                       id="email" 
                       name="email" 
                       placeholder="name@example.com" 
                       required
                       autocomplete="email"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                <label for="email">
                    <i class="fas fa-envelope me-2"></i>البريد الإلكتروني
                </label>
            </div>

            <div class="form-floating">
                <input type="password" 
                       class="form-control" 
                       id="password" 
                       name="password" 
                       placeholder="كلمة المرور" 
                       required
                       autocomplete="current-password">
                <label for="password">
                    <i class="fas fa-lock me-2"></i>كلمة المرور
                </label>
            </div>

            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me" <?php echo $remember_me ? 'checked' : ''; ?>>
                <label class="form-check-label" for="remember_me">
                    <i class="fas fa-heart me-1"></i>
                    تذكرني لمدة 30 يوم
                </label>
            </div>

            <button type="submit" class="btn btn-login" id="loginBtn">
                <span class="spinner-border spinner-border-sm me-2" style="display: none;" role="status" aria-hidden="true"></span>
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

        <div class="test-info">
            <strong>للاختبار:</strong><br>
            <strong>الإيميل:</strong> admin@buraqforms.com<br>
            <strong>كلمة المرور:</strong> password123<br>
            <strong>الدور:</strong> مدير النظام
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const spinner = btn.querySelector('.spinner-border');
            
            // Show loading state
            btn.classList.add('loading');
            spinner.style.display = 'inline-block';
            btn.disabled = true;
            
            // Basic validation
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!email || !password) {
                e.preventDefault();
                alert('يرجى إدخال جميع البيانات المطلوبة');
                
                btn.classList.remove('loading');
                spinner.style.display = 'none';
                btn.disabled = false;
                return false;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('يرجى إدخال بريد إلكتروني صحيح');
                
                btn.classList.remove('loading');
                spinner.style.display = 'none';
                btn.disabled = false;
                return false;
            }
        });

        // Auto-focus on email field
        document.getElementById('email').focus();

        // Clear any previous errors when user starts typing
        document.getElementById('email').addEventListener('input', function() {
            clearError();
        });

        document.getElementById('password').addEventListener('input', function() {
            clearError();
        });

        function clearError() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-danger')) {
                    alert.style.opacity = '0.5';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }
            });
        }

        // Security: Clear form data on page unload
        window.addEventListener('beforeunload', function() {
            document.getElementById('password').value = '';
        });
    </script>
</body>
</html>