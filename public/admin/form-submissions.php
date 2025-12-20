<?php

declare(strict_types=1);

// تضمين الإعدادات
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Core/Services/FormService.php';
require_once __DIR__ . '/../../src/Core/Services/FormSubmissionService.php';
require_once __DIR__ . '/../../src/Core/Services/DepartmentService.php';

// التحقق من المصادقة
require_once __DIR__ . '/../auth-check.php';

// إنشاء الخدمات
$formService = new BuraqForms\Core\Services\FormService($pdo);
$departmentService = new BuraqForms\Core\Services\DepartmentService($pdo);

$error = null;
$success = null;

// معالجة الحذف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        // التحقق من CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            throw new Exception('رمز الأمان غير صحيح');
        }
        
        $submissionId = (int)($_POST['submission_id'] ?? 0);
        if ($submissionId > 0) {
            // جلب الملفات المرتبطة بالإجابة
            $stmt = $pdo->prepare('SELECT file_path FROM submission_answers WHERE submission_id = :id AND file_path IS NOT NULL');
            $stmt->execute(['id' => $submissionId]);
            $files = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // حذف الإجابة من قاعدة البيانات
            $stmt = $pdo->prepare('DELETE FROM form_submissions WHERE id = :id');
            $stmt->execute(['id' => $submissionId]);
            
            // حذف الملفات من النظام
            foreach ($files as $filePath) {
                $fullPath = __DIR__ . '/../../' . $filePath;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
            
            $success = 'تم حذف الإجابة بنجاح';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// معالجة تحديث الحالة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    try {
        // التحقق من CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            throw new Exception('رمز الأمان غير صحيح');
        }
        
        $submissionId = (int)($_POST['submission_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        
        if ($submissionId > 0 && in_array($status, ['pending', 'completed', 'archived'])) {
            $stmt = $pdo->prepare('UPDATE form_submissions SET status = :status WHERE id = :id');
            $stmt->execute(['status' => $status, 'id' => $submissionId]);
            $success = 'تم تحديث حالة الإجابة بنجاح';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// إنشاء CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// معالجة الفلاتر والبحث
$filters = [
    'form_id' => isset($_GET['form_id']) && $_GET['form_id'] !== '' ? (int)$_GET['form_id'] : null,
    'department_id' => isset($_GET['department_id']) && $_GET['department_id'] !== '' ? (int)$_GET['department_id'] : null,
    'status' => isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null,
    'date_from' => isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : null,
    'date_to' => isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : null,
    'keyword' => isset($_GET['keyword']) && $_GET['keyword'] !== '' ? trim($_GET['keyword']) : null,
];

// Pagination
$perPage = 20;
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// بناء استعلام جلب الإجابات مع الفلاتر
$whereClauses = [];
$params = [];

if ($filters['form_id']) {
    $whereClauses[] = 'fs.form_id = :form_id';
    $params['form_id'] = $filters['form_id'];
}

if ($filters['department_id']) {
    $whereClauses[] = 'fs.department_id = :department_id';
    $params['department_id'] = $filters['department_id'];
}

if ($filters['status']) {
    $whereClauses[] = 'fs.status = :status';
    $params['status'] = $filters['status'];
}

if ($filters['date_from']) {
    $whereClauses[] = 'DATE(fs.submitted_at) >= :date_from';
    $params['date_from'] = $filters['date_from'];
}

if ($filters['date_to']) {
    $whereClauses[] = 'DATE(fs.submitted_at) <= :date_to';
    $params['date_to'] = $filters['date_to'];
}

if ($filters['keyword']) {
    $whereClauses[] = '(fs.submitted_by LIKE :keyword OR fs.reference_code LIKE :keyword)';
    $params['keyword'] = '%' . $filters['keyword'] . '%';
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// استعلام العد الكلي
$countSQL = "SELECT COUNT(*) FROM form_submissions fs $whereSQL";
$countStmt = $pdo->prepare($countSQL);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// استعلام جلب البيانات
$sql = "SELECT 
    fs.id,
    fs.form_id,
    fs.submitted_by,
    fs.department_id,
    fs.status,
    fs.submitted_at,
    fs.reference_code,
    f.title as form_title,
    d.name as department_name
FROM form_submissions fs
LEFT JOIN forms f ON fs.form_id = f.id
LEFT JOIN departments d ON fs.department_id = d.id
$whereSQL
ORDER BY fs.submitted_at DESC
LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$submissions = $stmt->fetchAll();

// جلب قائمة الاستمارات والإدارات للفلاتر
$forms = $formService->list();
$departments = $departmentService->list();

// إحصائيات
$statsSQL = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived
FROM form_submissions";
$statsStmt = $pdo->query($statsSQL);
$stats = $statsStmt->fetch();

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الإجابات المرسلة</title>
    
    <!-- Bootstrap RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/admin.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f8f9fa;
        }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
            padding: 20px 0;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        .main-content {
            padding: 30px;
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .filters-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .table-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .badge-pending { background-color: #ffc107; }
        .badge-completed { background-color: #28a745; }
        .badge-archived { background-color: #6c757d; }
        .action-btn {
            margin: 0 2px;
        }
    </style>
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
                            <a class="nav-link" href="dashboard.php">
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
                            <a class="nav-link active" href="form-submissions.php">
                                <i class="fas fa-inbox"></i>
                                الإجابات
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="fas fa-inbox"></i>
                        إدارة الإجابات المرسلة
                    </h2>
                    <div>
                        <a href="api/export-submissions.php?format=csv<?php 
                            echo $filters['form_id'] ? '&form_id=' . $filters['form_id'] : '';
                            echo $filters['department_id'] ? '&department_id=' . $filters['department_id'] : '';
                            echo $filters['status'] ? '&status=' . $filters['status'] : '';
                            echo $filters['date_from'] ? '&date_from=' . $filters['date_from'] : '';
                            echo $filters['date_to'] ? '&date_to=' . $filters['date_to'] : '';
                            echo $filters['keyword'] ? '&keyword=' . urlencode($filters['keyword']) : '';
                        ?>" class="btn btn-success me-2">
                            <i class="fas fa-file-csv"></i> تصدير CSV
                        </a>
                        <a href="api/export-submissions.php?format=excel<?php 
                            echo $filters['form_id'] ? '&form_id=' . $filters['form_id'] : '';
                            echo $filters['department_id'] ? '&department_id=' . $filters['department_id'] : '';
                            echo $filters['status'] ? '&status=' . $filters['status'] : '';
                            echo $filters['date_from'] ? '&date_from=' . $filters['date_from'] : '';
                            echo $filters['date_to'] ? '&date_to=' . $filters['date_to'] : '';
                            echo $filters['keyword'] ? '&keyword=' . urlencode($filters['keyword']) : '';
                        ?>" class="btn btn-primary">
                            <i class="fas fa-file-excel"></i> تصدير Excel
                        </a>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- إحصائيات -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <h3 class="text-primary"><?= $stats['total'] ?? 0 ?></h3>
                            <p class="mb-0">إجمالي الإجابات</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <h3 class="text-warning"><?= $stats['pending'] ?? 0 ?></h3>
                            <p class="mb-0">قيد الانتظار</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <h3 class="text-success"><?= $stats['completed'] ?? 0 ?></h3>
                            <p class="mb-0">مكتملة</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <h3 class="text-secondary"><?= $stats['archived'] ?? 0 ?></h3>
                            <p class="mb-0">مؤرشفة</p>
                        </div>
                    </div>
                </div>

                <!-- الفلاتر -->
                <div class="filters-card">
                    <h5 class="mb-3">
                        <i class="fas fa-filter"></i>
                        تصفية النتائج
                    </h5>
                    <form method="GET" action="">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">الاستمارة</label>
                                <select name="form_id" class="form-select">
                                    <option value="">جميع الاستمارات</option>
                                    <?php foreach ($forms as $form): ?>
                                        <option value="<?= $form['id'] ?>" <?= $filters['form_id'] == $form['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($form['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">الإدارة</label>
                                <select name="department_id" class="form-select">
                                    <option value="">جميع الإدارات</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['id'] ?>" <?= $filters['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">الحالة</label>
                                <select name="status" class="form-select">
                                    <option value="">جميع الحالات</option>
                                    <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>قيد الانتظار</option>
                                    <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>مكتملة</option>
                                    <option value="archived" <?= $filters['status'] === 'archived' ? 'selected' : '' ?>>مؤرشفة</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">من تاريخ</label>
                                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">إلى تاريخ</label>
                                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">البحث</label>
                                <input type="text" name="keyword" class="form-control" 
                                       placeholder="ابحث برقم المرجع أو اسم المرسل..." 
                                       value="<?= htmlspecialchars($filters['keyword'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> بحث
                                </button>
                                <a href="form-submissions.php" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> إعادة تعيين
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- جدول الإجابات -->
                <div class="table-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            قائمة الإجابات
                            <span class="badge bg-secondary"><?= $totalRecords ?> نتيجة</span>
                        </h5>
                    </div>

                    <?php if (empty($submissions)): ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i>
                            لا توجد إجابات مطابقة للفلاتر المحددة
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>رقم المرجع</th>
                                        <th>الاستمارة</th>
                                        <th>المرسل</th>
                                        <th>الإدارة</th>
                                        <th>الحالة</th>
                                        <th>تاريخ الإرسال</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($submissions as $index => $submission): ?>
                                        <tr>
                                            <td><?= $offset + $index + 1 ?></td>
                                            <td>
                                                <code><?= htmlspecialchars($submission['reference_code']) ?></code>
                                            </td>
                                            <td><?= htmlspecialchars($submission['form_title'] ?? 'غير معروف') ?></td>
                                            <td><?= htmlspecialchars($submission['submitted_by']) ?></td>
                                            <td><?= htmlspecialchars($submission['department_name'] ?? '-') ?></td>
                                            <td>
                                                <?php
                                                $statusClass = match($submission['status']) {
                                                    'pending' => 'badge-pending',
                                                    'completed' => 'badge-completed',
                                                    'archived' => 'badge-archived',
                                                    default => 'bg-secondary'
                                                };
                                                $statusText = match($submission['status']) {
                                                    'pending' => 'قيد الانتظار',
                                                    'completed' => 'مكتملة',
                                                    'archived' => 'مؤرشفة',
                                                    default => $submission['status']
                                                };
                                                ?>
                                                <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                                            </td>
                                            <td><?= date('Y-m-d H:i', strtotime($submission['submitted_at'])) ?></td>
                                            <td>
                                                <a href="submission-details.php?id=<?= $submission['id'] ?>" 
                                                   class="btn btn-sm btn-info action-btn" 
                                                   title="عرض التفاصيل">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <button type="button" 
                                                        class="btn btn-sm btn-warning action-btn" 
                                                        onclick="changeStatus(<?= $submission['id'] ?>, '<?= $submission['status'] ?>')"
                                                        title="تغيير الحالة">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger action-btn" 
                                                        onclick="confirmDelete(<?= $submission['id'] ?>, '<?= htmlspecialchars($submission['reference_code'], ENT_QUOTES) ?>')"
                                                        title="حذف">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page - 1 ?><?= http_build_query(array_filter($filters), '', '&') ? '&' . http_build_query(array_filter($filters)) : '' ?>">
                                                السابق
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?><?= http_build_query(array_filter($filters), '', '&') ? '&' . http_build_query(array_filter($filters)) : '' ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page + 1 ?><?= http_build_query(array_filter($filters), '', '&') ? '&' . http_build_query(array_filter($filters)) : '' ?>">
                                                التالي
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal تغيير الحالة -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">تغيير حالة الإجابة</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="submission_id" id="status_submission_id">
                        
                        <div class="mb-3">
                            <label class="form-label">الحالة الجديدة</label>
                            <select name="status" id="status_select" class="form-select">
                                <option value="pending">قيد الانتظار</option>
                                <option value="completed">مكتملة</option>
                                <option value="archived">مؤرشفة</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal الحذف -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">تأكيد الحذف</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="submission_id" id="delete_submission_id">
                        
                        <p>هل أنت متأكد من حذف الإجابة رقم <strong id="delete_reference"></strong>?</p>
                        <p class="text-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            سيتم حذف جميع البيانات والملفات المرتبطة بشكل نهائي ولا يمكن التراجع عن هذا الإجراء.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-danger">حذف نهائي</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function changeStatus(submissionId, currentStatus) {
            document.getElementById('status_submission_id').value = submissionId;
            document.getElementById('status_select').value = currentStatus;
            
            var modal = new bootstrap.Modal(document.getElementById('statusModal'));
            modal.show();
        }
        
        function confirmDelete(submissionId, referenceCode) {
            document.getElementById('delete_submission_id').value = submissionId;
            document.getElementById('delete_reference').textContent = referenceCode;
            
            var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>
