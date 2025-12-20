<?php
declare(strict_types=1);

session_start();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام الاستمارات الديناميكية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
        }
        body {
            font-family: 'Cairo', sans-serif;
            overflow-x: hidden;
        }
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar a {
            color: white !important;
        }
        .hero-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 100px 0;
            text-align: center;
        }
        .hero-section h1 {
            font-size: 48px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .hero-section p {
            font-size: 20px;
            margin-bottom: 30px;
        }
        .btn-primary-custom {
            background: white;
            color: var(--primary);
            padding: 12px 40px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 50px;
            border: none;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            color: var(--primary);
        }
        .features {
            padding: 60px 0;
            background: #f8f9fa;
        }
        .feature-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .feature-card i {
            font-size: 40px;
            color: var(--primary);
            margin-bottom: 15px;
        }
        .feature-card h3 {
            color: var(--secondary);
            margin-bottom: 10px;
        }
        .feature-card p {
            color: #666;
            font-size: 14px;
        }
        .footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 30px 0;
            margin-top: 60px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-forms"></i> نظام الاستمارات الديناميكية
            </a>
            <div class="ms-auto">
                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                    <a href="admin/dashboard.php" class="btn btn-light btn-sm me-2">لوحة التحكم</a>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">تسجيل الخروج</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-light btn-sm">تسجيل الدخول</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1>🌟 نظام الاستمارات الديناميكية</h1>
            <p>أنشئ استمارات احترافية بسهولة وأدرها بكفاءة عالية</p>
            <a href="<?php echo isset($_SESSION['logged_in']) ? 'admin/dashboard.php' : 'login.php'; ?>" 
               class="btn btn-primary-custom">
                <?php echo isset($_SESSION['logged_in']) ? 'انتقل للداشبورد' : 'ابدأ الآن'; ?>
            </a>
        </div>
    </section>

    <!-- Features -->
    <section class="features">
        <div class="container">
            <h2 style="text-align: center; margin-bottom: 50px; color: var(--secondary);">
                المميزات الرئيسية
            </h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-pencil-alt"></i>
                        <h3>استمارات ديناميكية</h3>
                        <p>أنشئ استمارات مخصصة بأنواع حقول مختلفة حسب احتياجاتك</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-chart-bar"></i>
                        <h3>تقارير وإحصائيات</h3>
                        <p>احصل على تقارير مفصلة وإحصائيات شاملة عن الإجابات</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-lock"></i>
                        <h3>آمان عالي</h3>
                        <p>حماية كاملة للبيانات مع تشفير قوي والتحقق من الهوية</p>
                    </div>
                </div>
            </div>
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-file-export"></i>
                        <h3>تصدير سهل</h3>
                        <p>صدّر النتائج إلى CSV و Excel بضغطة زر واحدة</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-mobile-alt"></i>
                        <h3>تصميم متجاوب</h3>
                        <p>يعمل بشكل مثالي على جميع الأجهزة والشاشات</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-globe"></i>
                        <h3>دعم العربية</h3>
                        <p>دعم كامل للغة العربية مع واجهة سهلة الاستخدام</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2024 نظام الاستمارات الديناميكية | جميع الحقوق محفوظة</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>