<?php

declare(strict_types=1);

// تضمين الإعدادات
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Core/Services/DepartmentService.php';
require_once __DIR__ . '/../../src/Core/Services/FormService.php';

// بدء الجلسة
session_start();

// التحقق من الصلاحيات (مؤقتاً)
$isAdmin = true; // يمكن تغييره حسب نظام المصادقة

if (!$isAdmin) {
    http_response_code(403);
    die('غير مسموح بالوصول');
}

// إنشاء الخدمات
$departmentService = new EmployeeEvaluationSystem\Core\Services\DepartmentService($pdo);
$formService = new EmployeeEvaluationSystem\Core\Services\FormService($pdo);

// جلب الإحصائيات
try {
    $totalDepartments = count($departmentService->list());
    $activeDepartments = count($departmentService->list(true));
    
    $totalForms = count($formService->list());
    $activeForms = count($formService->list('active'));
    
    // جلب آخر العمليات (activity log)
    $activityLog = [
        [
            'action' => 'تم إنشاء إدارة جديدة',
            'details' => 'إدارة الموارد البشرية',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'icon' => 'fas fa-building',
            'color' => 'primary'
        ],
        [
            'action' => 'تم إنشاء استمارة جديدة',
            'details' => 'استمارة تقييم الأداء السنوي',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-4 hours')),
            'icon' => 'fas fa-file-alt',
            'color' => 'success'
        ],
        [
            'action' => 'تم تحديث إعدادات النظام',
            'details' => 'تفعيل الإشعارات البريدية',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'icon' => 'fas fa-cog',
            'color' => 'warning'
        ],
        [
            'action' => 'تم حذف استمارة قديمة',
            'details' => 'استمارة تقييم الربع الأول 2023',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'icon' => 'fas fa-trash',
            'color' => 'danger'
        ]
    ];
    
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
                            <a class="nav-link" href="submissions.php">
                                <i class="fas fa-inbox"></i>
                                الإجابات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i>
                                المستخدمين
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
                                        <div class="h2 mb-0">0</div>
                                        <div class="small opacity-75">إجمالي الإجابات</div>
                                    </div>
                                    <div>
                                        <i class="fas fa-inbox fa-2x opacity-75"></i>
                                    </div>
                                </div>
                                <div class="mt-2 small">
                                    <i class="fas fa-plus-circle"></i>
                                    اليوم: 0
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card text-white" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="h2 mb-0">1</div>
                                        <div class="small opacity-75">المستخدمين</div>
                                    </div>
                                    <div>
                                        <i class="fas fa-users fa-2x opacity-75"></i>
                                    </div>
                                </div>
                                <div class="mt-2 small">
                                    <i class="fas fa-user-shield"></i>
                                    مشرف واحد
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
                                        <a href="submissions.php" class="btn btn-outline-warning w-100 text-decoration-none">
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
                    <!-- Recent Activity -->
                    <div class="col-md-8">
                        <div class="card fade-in">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-history"></i>
                                    آخر النشاطات
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($activityLog as $activity): ?>
                                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                    <div class="me-3">
                                        <div class="rounded-circle bg-<?= $activity['color'] ?> d-flex align-items-center justify-content-center" 
                                             style="width: 40px; height: 40px;">
                                            <i class="<?= $activity['icon'] ?> text-white"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?= $activity['action'] ?></div>
                                        <div class="text-muted small"><?= $activity['details'] ?></div>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="fas fa-clock"></i>
                                        <?= date('H:i', strtotime($activity['timestamp'])) ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <div class="text-center">
                                    <a href="#" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-list"></i>
                                        عرض جميع النشاطات
                                    </a>
                                </div>
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