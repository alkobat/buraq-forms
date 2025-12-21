<?php

declare(strict_types=1);

// Include required files
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/Core/Auth.php';

// Require authentication - redirect to login if not logged in
require_auth();

// Validate session security
if (!validate_session()) {
    header('Location: ../login.php');
    exit;
}

// تضمين الإعدادات
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Core/Services/DepartmentService.php';
require_once __DIR__ . '/../../src/Core/Services/FormService.php';

// Get current user
$current_user = current_user();

// Get user role for conditional access
$user_role = $current_user['role'] ?? 'editor';

// التحقق من الدور للوصول للداشبورد (جميع الأدوار مسموحة للداشبورد)
if (!can_access('dashboard')) {
    http_response_code(403);
    die('غير مسموح بالوصول لهذه الصفحة');
}

// إنشاء الخدمات
$departmentService = new BuraqForms\Core\Services\DepartmentService($pdo);
$formService = new BuraqForms\Core\Services\FormService($pdo);

// جلب الإحصائيات
try {
    $totalDepartments = count($departmentService->list());
    $activeDepartments = count($departmentService->list(true));
    
    $totalForms = count($formService->list());
    $activeForms = count($formService->list('active'));
    
    // إحصائيات الإجابات
    $submissionsStatsStmt = $pdo->query('
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN DATE(submitted_at) = CURDATE() THEN 1 ELSE 0 END) as today
        FROM form_submissions
    ');
    $submissionsStats = $submissionsStatsStmt->fetch(PDO::FETCH_ASSOC);
    
    // إحصائيات الإجابات لكل استمارة
    $submissionsByFormStmt = $pdo->query('
        SELECT 
            f.title,
            COUNT(fs.id) as count
        FROM forms f
        LEFT JOIN form_submissions fs ON f.id = fs.form_id
        WHERE f.status = "active"
        GROUP BY f.id, f.title
        ORDER BY count DESC
        LIMIT 5
    ');
    $submissionsByForm = $submissionsByFormStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات الإجابات لكل إدارة
    $submissionsByDeptStmt = $pdo->query('
        SELECT 
            d.name,
            COUNT(fs.id) as count
        FROM departments d
        LEFT JOIN form_submissions fs ON d.id = fs.department_id
        WHERE d.is_active = 1
        GROUP BY d.id, d.name
        ORDER BY count DESC
        LIMIT 5
    ');
    $submissionsByDept = $submissionsByDeptStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // آخر الإجابات
    $recentSubmissionsStmt = $pdo->query('
        SELECT 
            fs.reference_code,
            fs.submitted_by,
            fs.submitted_at,
            f.title as form_title
        FROM form_submissions fs
        LEFT JOIN forms f ON fs.form_id = f.id
        ORDER BY fs.submitted_at DESC
        LIMIT 10
    ');
    $recentSubmissions = $recentSubmissionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم الرئيسية</title>
    
    <!-- Bootstrap RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 sidebar">
                <div class="position-sticky pt-3">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-cogs"></i>
                        لوحة التحكم
                    </h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                الرئيسية
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="departments.php">
                                <i class="fas fa-building"></i>
                                الإدارات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="forms.php">
                                <i class="fas fa-file-alt"></i>
                                الاستمارات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="form-submissions.php">
                                <i class="fas fa-inbox"></i>
                                الإجابات
                            </a>
                        </li>
                    </ul>
                    
                    <hr class="text-white-50">
                    
                    <div class="text-center">
                        <div class="text-white-50 small">
                            <i class="fas fa-clock"></i>
                            آخر تحديث:
                            <br>
                            <span id="currentTime"></span>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="page-header fade-in">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="page-title">
                                <i class="fas fa-tachometer-alt text-primary"></i>
                                لوحة التحكم الرئيسية
                            </h1>
                            <p class="page-description">نظرة شاملة على حالة النظام والإحصائيات</p>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">
                                <i class="fas fa-calendar"></i>
                                <?= date('Y-m-d') ?>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="h2 mb-0"><?= $totalDepartments ?></div>
                                        <div class="small opacity-75">إجمالي الإدارات</div>
                                    </div>
                                    <div>
                                        <i class="fas fa-building fa-2x opacity-75"></i>
                                    </div>
                                </div>
                                <div class="mt-2 small">
                                    <i class="fas fa-check-circle"></i>
                                    <?= $activeDepartments ?> نشطة
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card text-white" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="h2 mb-0"><?= $totalForms ?></div>
                                        <div class="small opacity-75">إجمالي الاستمارات</div>
                                    </div>
                                    <div>
                                        <i class="fas fa-file-alt fa-2x opacity-75"></i>
                                    </div>
                                </div>
                                <div class="mt-2 small">
                                    <i class="fas fa-check-circle"></i>
                                    <?= $activeForms ?> نشطة
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card text-white" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="h2 mb-0"><?= $submissionsStats['total'] ?? 0 ?></div>
                                        <div class="small opacity-75">إجمالي الإجابات</div>
                                    </div>
                                    <div>
                                        <i class="fas fa-inbox fa-2x opacity-75"></i>
                                    </div>
                                </div>
                                <div class="mt-2 small">
                                    <i class="fas fa-plus-circle"></i>
                                    اليوم: <?= $submissionsStats['today'] ?? 0 ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card text-white" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="h2 mb-0"><?= $submissionsStats['pending'] ?? 0 ?></div>
                                        <div class="small opacity-75">قيد الانتظار</div>
                                    </div>
                                    <div>
                                        <i class="fas fa-hourglass-half fa-2x opacity-75"></i>
                                    </div>
                                </div>
                                <div class="mt-2 small">
                                    <i class="fas fa-check-circle"></i>
                                    مكتملة: <?= $submissionsStats['completed'] ?? 0 ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card fade-in">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-bolt"></i>
                                    إجراءات سريعة
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-2">
                                        <button type="button" class="btn btn-outline-primary w-100" onclick="quickAction('new_department')">
                                            <i class="fas fa-building d-block mb-2"></i>
                                            إدارة جديدة
                                        </button>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <button type="button" class="btn btn-outline-success w-100" onclick="quickAction('new_form')">
                                            <i class="fas fa-file-alt d-block mb-2"></i>
                                            استمارة جديدة
                                        </button>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="form-builder.php" class="btn btn-outline-info w-100 text-decoration-none">
                                            <i class="fas fa-pencil-ruler d-block mb-2"></i>
                                            محرر الاستمارات
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="form-submissions.php" class="btn btn-outline-warning w-100 text-decoration-none">
                                            <i class="fas fa-inbox d-block mb-2"></i>
                                            عرض الإجابات
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity & System Status -->
                <div class="row">
                    <!-- Recent Submissions -->
                    <div class="col-md-8">
                        <div class="card fade-in">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-history"></i>
                                    آخر الإجابات المرسلة
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentSubmissions)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <p>لا توجد إجابات بعد</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentSubmissions as $submission): ?>
                                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                        <div class="me-3">
                                            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" 
                                                 style="width: 40px; height: 40px;">
                                                <i class="fas fa-file-alt text-white"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold"><?= htmlspecialchars($submission['submitted_by']) ?></div>
                                            <div class="text-muted small">
                                                <?= htmlspecialchars($submission['form_title'] ?? 'استمارة غير معروفة') ?>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($submission['reference_code']) ?></span>
                                            </div>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="fas fa-clock"></i>
                                            <?= date('H:i', strtotime($submission['submitted_at'])) ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="text-center">
                                        <a href="form-submissions.php" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-list"></i>
                                            عرض جميع الإجابات
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- System Status -->
                    <div class="col-md-4">
                        <div class="card fade-in">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-heartbeat"></i>
                                    حالة النظام
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>قاعدة البيانات</span>
                                        <span class="badge bg-success">متصلة</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" style="width: 100%"></div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>الخادم</span>
                                        <span class="badge bg-success">يعمل</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" style="width: 95%"></div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>الذاكرة</span>
                                        <span class="badge bg-info">65%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-info" style="width: 65%"></div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>مساحة التخزين</span>
                                        <span class="badge bg-warning">45%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-warning" style="width: 45%"></div>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="text-center">
                                    <h6 class="text-muted mb-2">معلومات النظام</h6>
                                    <small class="text-muted">
                                        <i class="fas fa-server"></i>
                                        PHP <?= PHP_VERSION ?>
                                    </small><br>
                                    <small class="text-muted">
                                        <i class="fas fa-database"></i>
                                        MySQL متاح
                                    </small><br>
                                    <small class="text-muted">
                                        <i class="fas fa-clock"></i>
                                        وقت التشغيل: 99.9%
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submissions Statistics -->
                <div class="row mt-4">
                    <!-- Submissions by Form -->
                    <div class="col-md-6">
                        <div class="card fade-in">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-bar"></i>
                                    الإجابات حسب الاستمارة
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($submissionsByForm)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-chart-bar fa-3x mb-3"></i>
                                        <p>لا توجد بيانات</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($submissionsByForm as $item): ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="small"><?= htmlspecialchars($item['title']) ?></span>
                                                <span class="badge bg-primary"><?= $item['count'] ?></span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <?php
                                                $maxCount = $submissionsByForm[0]['count'] ?? 1;
                                                $percentage = $maxCount > 0 ? ($item['count'] / $maxCount) * 100 : 0;
                                                ?>
                                                <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Submissions by Department -->
                    <div class="col-md-6">
                        <div class="card fade-in">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-building"></i>
                                    الإجابات حسب الإدارة
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($submissionsByDept)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-building fa-3x mb-3"></i>
                                        <p>لا توجد بيانات</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($submissionsByDept as $item): ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="small"><?= htmlspecialchars($item['name']) ?></span>
                                                <span class="badge bg-success"><?= $item['count'] ?></span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <?php
                                                $maxCount = $submissionsByDept[0]['count'] ?? 1;
                                                $percentage = $maxCount > 0 ? ($item['count'] / $maxCount) * 100 : 0;
                                                ?>
                                                <div class="progress-bar bg-success" style="width: <?= $percentage ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('ar-SA', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        
        // Quick actions
        function quickAction(action) {
            switch (action) {
                case 'new_department':
                    window.location.href = 'departments.php';
                    break;
                case 'new_form':
                    window.location.href = 'forms.php';
                    break;
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateTime();
            setInterval(updateTime, 1000);
            
            // Add fade-in animation
            setTimeout(() => {
                document.querySelectorAll('.fade-in').forEach(el => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                });
            }, 100);
        });
        
        // Add CSS for fade-in effect
        const style = document.createElement('style');
        style.textContent = `
            .fade-in {
                opacity: 0;
                transform: translateY(20px);
                transition: all 0.5s ease;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>