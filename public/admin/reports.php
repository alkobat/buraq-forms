<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use EmployeeEvaluationSystem\Core\Database;
use EmployeeEvaluationSystem\Core\Services\ReportService;
use EmployeeEvaluationSystem\Core\Services\PermissionService;
use EmployeeEvaluationSystem\Core\Services\AuditService;

// إعداد الجلسة والتحقق من الصلاحية
session_start();

$database = Database::getConnection();
$reportService = new ReportService($database);
$permissionService = new PermissionService($database);
$auditService = new AuditService($database);

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$adminId = $_SESSION['admin_id'] ?? 0;

// التحقق من الصلاحية
if (!$permissionService->hasPermission($adminId, 'reports.generate')) {
    header('HTTP/1.0 403 Forbidden');
    exit('ليس لديك صلاحية للوصول لهذه الصفحة');
}

$message = '';
$messageType = '';

// معالجة العمليات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_report':
                $reportData = [
                    'name' => $_POST['report_name'] ?? '',
                    'description' => $_POST['report_description'] ?? '',
                    'type' => $_POST['report_type'] ?? 'submissions',
                    'config' => [
                        'form_id' => $_POST['form_id'] ?? null,
                        'department_id' => $_POST['department_id'] ?? null,
                        'status' => $_POST['status'] ?? null,
                        'date_from' => $_POST['date_from'] ?? null,
                        'date_to' => $_POST['date_to'] ?? null
                    ],
                    'created_by' => $adminId,
                    'is_shared' => isset($_POST['is_shared']) ? 1 : 0,
                    'schedule_type' => $_POST['schedule_type'] ?? 'none'
                ];

                $reportId = $reportService->createCustomReport($reportData);
                if ($reportId) {
                    $auditService->logCreate($adminId, 'custom_report', $reportId, $reportData);
                    $message = 'تم إنشاء التقرير بنجاح';
                    $messageType = 'success';
                } else {
                    $message = 'فشل في إنشاء التقرير';
                    $messageType = 'error';
                }
                break;

            case 'delete_report':
                $reportId = (int) ($_POST['report_id'] ?? 0);
                if ($reportId > 0) {
                    $sql = "DELETE FROM custom_reports WHERE id = ? AND created_by = ?";
                    $stmt = $database->prepare($sql);
                    $result = $stmt->execute([$reportId, $adminId]);
                    
                    if ($result) {
                        $auditService->logDelete($adminId, 'custom_report', $reportId, []);
                        $message = 'تم حذف التقرير بنجاح';
                        $messageType = 'success';
                    } else {
                        $message = 'فشل في حذف التقرير';
                        $messageType = 'error';
                    }
                }
                break;
        }
    } catch (Exception $e) {
        $message = 'حدث خطأ: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// جلب البيانات
$allReports = $reportService->getAllCustomReports($adminId);
$departmentService = new \EmployeeEvaluationSystem\Core\Services\DepartmentService($database);
$departments = $departmentService->getAllDepartments();
$formService = new \EmployeeEvaluationSystem\Core\Services\FormService($database);
$forms = $formService->getAllForms();

// جلب التقارير السابقة للتنفيذ
$executedReports = [];
if (isset($_GET['execute_report'])) {
    $reportId = (int) $_GET['execute_report'];
    $report = $reportService->getCustomReport($reportId);
    if ($report) {
        $executedReports = $reportService->executeCustomReport($report['report_config']);
    }
}

// إحصائيات شاملة
$stats = [
    'total_reports' => count($allReports),
    'shared_reports' => count(array_filter($allReports, fn($r) => $r['is_shared'])),
    'scheduled_reports' => count(array_filter($allReports, fn($r) => $r['schedule_type'] !== 'none'))
];

// إحصائيات التقارير العامة
$analytics = $reportService->executeCustomReport(['type' => 'analytics']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة التقارير المتقدمة - نظام تقييم الموظفين</title>
    
    <!-- Bootstrap 5 RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Cairo Font -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            margin: 20px;
            padding: 0;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            border-radius: 15px 0 0 15px;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 25px;
            margin: 5px 15px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .content {
            padding: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .report-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px 30px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .report-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .alert-custom {
            border-radius: 10px;
            border: none;
            font-weight: 600;
        }
        
        .dark-mode {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
        }
        
        .dark-mode .stat-card,
        .dark-mode .report-form,
        .dark-mode .report-table,
        .dark-mode .chart-container {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .dark-mode .form-control,
        .dark-mode .form-select {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
        
        .dark-mode .modal-content {
            background: #2c3e50;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="main-container row">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="sidebar">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-chart-line"></i>
                        التقارير المتقدمة
                    </h4>
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="#overview" data-bs-toggle="tab">
                            <i class="fas fa-tachometer-alt"></i> نظرة عامة
                        </a>
                        <a class="nav-link" href="#create-report" data-bs-toggle="tab">
                            <i class="fas fa-plus"></i> إنشاء تقرير
                        </a>
                        <a class="nav-link" href="#saved-reports" data-bs-toggle="tab">
                            <i class="fas fa-file-alt"></i> التقارير المحفوظة
                        </a>
                        <a class="nav-link" href="#analytics" data-bs-toggle="tab">
                            <i class="fas fa-chart-bar"></i> التحليلات
                        </a>
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-arrow-right"></i> العودة للوحة التحكم
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Content -->
            <div class="col-lg-9">
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'success' ?> alert-custom alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?= $messageType === 'error' ? 'exclamation-triangle' : 'check-circle' ?>"></i>
                            <?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="tab-content">
                        <!-- نظرة عامة -->
                        <div class="tab-pane fade show active" id="overview">
                            <h2 class="mb-4">
                                <i class="fas fa-tachometer-alt"></i>
                                نظرة عامة على التقارير
                            </h2>
                            
                            <!-- إحصائيات سريعة -->
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="stat-card text-center">
                                        <div class="stat-icon bg-primary mx-auto mb-3">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <h3><?= $stats['total_reports'] ?></h3>
                                        <p class="text-muted">إجمالي التقارير</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card text-center">
                                        <div class="stat-icon bg-success mx-auto mb-3">
                                            <i class="fas fa-share"></i>
                                        </div>
                                        <h3><?= $stats['shared_reports'] ?></h3>
                                        <p class="text-muted">تقارير مشتركة</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card text-center">
                                        <div class="stat-icon bg-warning mx-auto mb-3">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <h3><?= $stats['scheduled_reports'] ?></h3>
                                        <p class="text-muted">تقارير مجدولة</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- إحصائيات النظام -->
                            <div class="row">
                                <div class="col-12">
                                    <div class="chart-container">
                                        <h4 class="mb-4">
                                            <i class="fas fa-chart-bar"></i>
                                            إحصائيات النظام
                                        </h4>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>إجمالي الاستمارات:</strong> <?= $analytics['general']['total_forms'] ?? 0 ?></p>
                                                <p><strong>إجمالي الإجابات:</strong> <?= $analytics['general']['total_submissions'] ?? 0 ?></p>
                                                <p><strong>الإجابات المعلقة:</strong> <?= $analytics['general']['pending_submissions'] ?? 0 ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>الإجابات المكتملة:</strong> <?= $analytics['general']['completed_submissions'] ?? 0 ?></p>
                                                <p><strong>عدد الإدارات:</strong> <?= $analytics['general']['total_departments'] ?? 0 ?></p>
                                                <p><strong>عدد المشرفين:</strong> <?= $analytics['general']['total_admins'] ?? 0 ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- إنشاء تقرير -->
                        <div class="tab-pane fade" id="create-report">
                            <h2 class="mb-4">
                                <i class="fas fa-plus"></i>
                                إنشاء تقرير مخصص
                            </h2>
                            
                            <form method="POST" class="report-form">
                                <input type="hidden" name="action" value="create_report">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">اسم التقرير</label>
                                            <input type="text" class="form-control" name="report_name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">نوع التقرير</label>
                                            <select class="form-select" name="report_type" required>
                                                <option value="submissions">إجابات الاستمارات</option>
                                                <option value="departments">التقارير حسب الإدارات</option>
                                                <option value="forms">تقارير الاستمارات</option>
                                                <option value="analytics">تحليلات شاملة</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">وصف التقرير</label>
                                    <textarea class="form-control" name="report_description" rows="3"></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">الاستمارة</label>
                                            <select class="form-select" name="form_id">
                                                <option value="">جميع الاستمارات</option>
                                                <?php foreach ($forms as $form): ?>
                                                    <option value="<?= $form['id'] ?>"><?= htmlspecialchars($form['title']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">الإدارة</label>
                                            <select class="form-select" name="department_id">
                                                <option value="">جميع الإدارات</option>
                                                <?php foreach ($departments as $dept): ?>
                                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">الحالة</label>
                                            <select class="form-select" name="status">
                                                <option value="">جميع الحالات</option>
                                                <option value="pending">معلق</option>
                                                <option value="completed">مكتمل</option>
                                                <option value="archived">مؤرشف</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">من تاريخ</label>
                                            <input type="date" class="form-control" name="date_from">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">إلى تاريخ</label>
                                            <input type="date" class="form-control" name="date_to">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">جدولة التقرير</label>
                                            <select class="form-select" name="schedule_type">
                                                <option value="none">بدون جدولة</option>
                                                <option value="daily">يومي</option>
                                                <option value="weekly">أسبوعي</option>
                                                <option value="monthly">شهري</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <div class="form-check mt-4">
                                                <input class="form-check-input" type="checkbox" name="is_shared" id="is_shared">
                                                <label class="form-check-label" for="is_shared">
                                                    تقرير مشترك (يمكن للمشرفين الآخرين الوصول إليه)
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-gradient">
                                    <i class="fas fa-save"></i> حفظ التقرير
                                </button>
                            </form>
                        </div>
                        
                        <!-- التقارير المحفوظة -->
                        <div class="tab-pane fade" id="saved-reports">
                            <h2 class="mb-4">
                                <i class="fas fa-file-alt"></i>
                                التقارير المحفوظة
                            </h2>
                            
                            <?php if (empty($allReports)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">لا توجد تقارير محفوظة</h5>
                                    <p class="text-muted">ابدأ بإنشاء تقريرك الأول</p>
                                </div>
                            <?php else: ?>
                                <div class="report-table">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>اسم التقرير</th>
                                                    <th>النوع</th>
                                                    <th>المؤلف</th>
                                                    <th>المشترك</th>
                                                    <th>الجدولة</th>
                                                    <th>الإجراءات</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($allReports as $report): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?= htmlspecialchars($report['name']) ?></strong>
                                                            <?php if ($report['description']): ?>
                                                                <br><small class="text-muted"><?= htmlspecialchars($report['description']) ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info"><?= htmlspecialchars($report['report_type']) ?></span>
                                                        </td>
                                                        <td><?= htmlspecialchars($report['creator_name']) ?></td>
                                                        <td>
                                                            <?php if ($report['is_shared']): ?>
                                                                <i class="fas fa-check text-success"></i>
                                                            <?php else: ?>
                                                                <i class="fas fa-times text-muted"></i>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($report['schedule_type'] !== 'none'): ?>
                                                                <span class="badge bg-warning"><?= $report['schedule_type'] ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <a href="?execute_report=<?= $report['id'] ?>" class="btn btn-sm btn-primary">
                                                                    <i class="fas fa-play"></i>
                                                                </a>
                                                                <button type="button" class="btn btn-sm btn-success" 
                                                                        onclick="exportReport(<?= $report['id'] ?>, 'excel')">
                                                                    <i class="fas fa-file-excel"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-info"
                                                                        onclick="exportReport(<?= $report['id'] ?>, 'pdf')">
                                                                    <i class="fas fa-file-pdf"></i>
                                                                </button>
                                                                <?php if ($report['created_by'] == $adminId): ?>
                                                                    <form method="POST" style="display: inline;" 
                                                                          onsubmit="return confirm('هل أنت متأكد من حذف هذا التقرير؟')">
                                                                        <input type="hidden" name="action" value="delete_report">
                                                                        <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                                            <i class="fas fa-trash"></i>
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- التحليلات -->
                        <div class="tab-pane fade" id="analytics">
                            <h2 class="mb-4">
                                <i class="fas fa-chart-bar"></i>
                                التحليلات المتقدمة
                            </h2>
                            
                            <div class="row">
                                <!-- الاتجاهات اليومية -->
                                <div class="col-12">
                                    <div class="chart-container">
                                        <h4 class="mb-4">
                                            <i class="fas fa-chart-line"></i>
                                            الاتجاهات اليومية (آخر 30 يوم)
                                        </h4>
                                        <?php if (!empty($analytics['daily_trends'])): ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>التاريخ</th>
                                                            <th>الإجمالي</th>
                                                            <th>مكتملة</th>
                                                            <th>معلقة</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach (array_slice($analytics['daily_trends'], -10) as $trend): ?>
                                                            <tr>
                                                                <td><?= date('Y-m-d', strtotime($trend['date'])) ?></td>
                                                                <td><?= $trend['submissions'] ?></td>
                                                                <td><?= $trend['completed'] ?></td>
                                                                <td><?= $trend['pending'] ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">لا توجد بيانات كافية</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- أفضل الاستمارات -->
                                <div class="col-md-6">
                                    <div class="chart-container">
                                        <h4 class="mb-4">
                                            <i class="fas fa-star"></i>
                                            أفضل الاستمارات
                                        </h4>
                                        <?php if (!empty($analytics['top_forms'])): ?>
                                            <ul class="list-group">
                                                <?php foreach (array_slice($analytics['top_forms'], 0, 5) as $form): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <?= htmlspecialchars($form['title']) ?>
                                                        <span class="badge bg-primary rounded-pill"><?= $form['submission_count'] ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="text-muted">لا توجد بيانات</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- أفضل الإدارات -->
                                <div class="col-md-6">
                                    <div class="chart-container">
                                        <h4 class="mb-4">
                                            <i class="fas fa-building"></i>
                                            أفضل الإدارات
                                        </h4>
                                        <?php if (!empty($analytics['top_departments'])): ?>
                                            <ul class="list-group">
                                                <?php foreach (array_slice($analytics['top_departments'], 0, 5) as $dept): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <?= htmlspecialchars($dept['department_name']) ?>
                                                        <span class="badge bg-success rounded-pill"><?= $dept['submission_count'] ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="text-muted">لا توجد بيانات</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js للتحليلات -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // تشغيل/إيقاف الوضع الليلي
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
        }
        
        // تحميل تفضيل الوضع الليلي
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
        }
        
        // تصدير التقرير
        function exportReport(reportId, format) {
            window.open(`api/export-report.php?id=${reportId}&format=${format}`, '_blank');
        }
        
        // حفظ تلقائي للتصفية
        function autoSaveFilter() {
            const form = document.querySelector('#create-report form');
            const formData = new FormData(form);
            formData.append('action', 'auto_save');
            
            fetch('api/save-draft.php', {
                method: 'POST',
                body: formData
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      console.log('تم الحفظ التلقائي');
                  }
              });
        }
        
        // حفظ تلقائي كل 30 ثانية
        setInterval(autoSaveFilter, 30000);
        
        // تخطيط الرسوم البيانية
        function createCharts() {
            // رسم بياني للاتجاهات اليومية
            const ctx = document.getElementById('dailyTrendsChart');
            if (ctx && typeof Chart !== 'undefined') {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو'],
                        datasets: [{
                            label: 'الإجابات',
                            data: [65, 59, 80, 81, 56, 55],
                            borderColor: 'rgb(75, 192, 192)',
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            title: {
                                display: true,
                                text: 'إحصائيات شهرية'
                            }
                        }
                    }
                });
            }
        }
        
        // تشغيل الرسوم البيانية عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', createCharts);
    </script>
</body>
</html>