<?php

declare(strict_types=1);

if (!defined('CONFIG_PATH')) {
    require_once __DIR__ . '/../../config/constants.php';
}

// Include required files
require_once SRC_PATH . '/helpers.php';
require_once SRC_PATH . '/Core/Auth.php';

// Require authentication - redirect to login if not logged in
require_auth();

// Validate session security
if (!validate_session()) {
    header('Location: ../login.php');
    exit;
}

// تضمين الإعدادات
require_once CONFIG_PATH . '/database.php';
require_once SRC_PATH . '/Core/Services/FormService.php';

// Get current user
$current_user = current_user();

// Get user role for conditional access
$user_role = $current_user['role'] ?? 'editor';

// التحقق من الدور للوصول لصفحة الاستمارات
if (!can_access('forms')) {
    http_response_code(403);
    die('غير مسموح بالوصول لهذه الصفحة');
}

// إنشاء خدمة إدارة الاستمارات
$formService = new BuraqForms\Core\Services\FormService($pdo);

$error = null;
$success = null;

// معالجة العمليات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // التحقق من CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            throw new Exception('رمز الأمان غير صحيح');
        }

        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $data = [
                    'title' => $_POST['title'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'created_by' => 1, // مؤقتاً - يجب أن يكون من المستخدم المسجل دخوله
                    'status' => isset($_POST['status']) ? 'active' : 'inactive',
                    'allow_multiple_submissions' => isset($_POST['allow_multiple_submissions']),
                    'show_department_field' => isset($_POST['show_department_field'])
                ];
                $form = $formService->create($data);
                $success = 'تم إنشاء الاستمارة بنجاح';
                break;

            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $data = [
                    'title' => $_POST['title'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'status' => isset($_POST['status']) ? 'active' : 'inactive',
                    'allow_multiple_submissions' => isset($_POST['allow_multiple_submissions']),
                    'show_department_field' => isset($_POST['show_department_field'])
                ];
                $formService->update($id, $data);
                $success = 'تم تحديث الاستمارة بنجاح';
                break;

            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                $formService->delete($id);
                $success = 'تم حذف الاستمارة بنجاح';
                break;

            case 'toggle_status':
                $id = (int)($_POST['id'] ?? 0);
                $status = $_POST['status'] === 'active' ? 'active' : 'inactive';
                $formService->setStatus($id, $status);
                $success = $status === 'active' ? 'تم تفعيل الاستمارة بنجاح' : 'تم تعطيل الاستمارة بنجاح';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// إنشاء CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// جلب قائمة الاستمارات
$forms = $formService->list();

// جلب إحصائيات لكل استمارة
try {
    $stmt = $pdo->prepare('SELECT form_id, COUNT(*) as submission_count FROM form_submissions GROUP BY form_id');
    $stmt->execute();
    $submissionStats = [];
    while ($row = $stmt->fetch()) {
        $submissionStats[$row['form_id']] = (int)$row['submission_count'];
    }
} catch (PDOException $e) {
    $submissionStats = [];
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الاستمارات</title>
    
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
                            <a class="nav-link active" href="forms.php">
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
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="page-header fade-in">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="page-title">
                                <i class="fas fa-file-alt text-primary"></i>
                                إدارة الاستمارات
                            </h1>
                            <p class="page-description">إنشاء وإدارة استمارات تقييم الموظفين</p>
                        </div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#formModal" onclick="resetForm()">
                            <i class="fas fa-plus"></i>
                            إنشاء استمارة جديدة
                        </button>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
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

                <!-- Forms Table -->
                <div class="card fade-in">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i>
                            قائمة الاستمارات
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($forms)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">لا توجد استمارات</h5>
                            <p class="text-muted">ابدأ بإنشاء استمارة جديدة</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>العنوان</th>
                                        <th>الوصف</th>
                                        <th>الحالة</th>
                                        <th>عدد الإجابات</th>
                                        <th>تاريخ الإنشاء</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($forms as $form): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($form['title']) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($form['slug']) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?= $form['description'] ? htmlspecialchars(mb_substr($form['description'], 0, 50)) . (mb_strlen($form['description']) > 50 ? '...' : '') : '<span class="text-muted">لا يوجد وصف</span>' ?>
                                        </td>
                                        <td>
                                            <?php if ($form['status'] === 'active'): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check"></i>
                                                    نشطة
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-times"></i>
                                                    غير نشطة
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <i class="fas fa-inbox"></i>
                                                <?= $submissionStats[$form['id']] ?? 0 ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= date('Y-m-d', strtotime($form['created_at'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="editForm(<?= $form['id'] ?>)"
                                                        title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <a href="form-builder.php?id=<?= $form['id'] ?>" 
                                                   class="btn btn-sm btn-outline-info"
                                                   title="محرر الحقول">
                                                    <i class="fas fa-pencil-ruler"></i>
                                                </a>
                                                
                                                <a href="preview-form.php?slug=<?= $form['slug'] ?>" 
                                                   class="btn btn-sm btn-outline-success"
                                                   title="معاينة"
                                                   target="_blank">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <button type="button" class="btn btn-sm btn-outline-warning" 
                                                        onclick="toggleStatus(<?= $form['id'] ?>, '<?= $form['status'] === 'active' ? 'inactive' : 'active' ?>')"
                                                        title="<?= $form['status'] === 'active' ? 'تعطيل' : 'تفعيل' ?>">
                                                    <i class="fas <?= $form['status'] === 'active' ? 'fa-pause' : 'fa-play' ?>"></i>
                                                </button>
                                                
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteForm(<?= $form['id'] ?>, '<?= htmlspecialchars($form['title'], ENT_QUOTES) ?>')"
                                                        title="حذف">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Form Modal -->
    <div class="modal fade" id="formModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="formForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" id="modalAction" value="create">
                    <input type="hidden" name="id" id="formId">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">
                            <i class="fas fa-plus"></i>
                            إنشاء استمارة جديدة
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">عنوان الاستمارة *</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                    <div class="invalid-feedback">يرجى إدخال عنوان الاستمارة</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="status" class="form-label">الحالة</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="status" name="status" checked>
                                        <label class="form-check-label" for="status">
                                            نشطة
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">وصف الاستمارة</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="allow_multiple_submissions" name="allow_multiple_submissions" checked>
                                    <label class="form-check-label" for="allow_multiple_submissions">
                                        السماح بالإجابات المتعددة
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="show_department_field" name="show_department_field" checked>
                                    <label class="form-check-label" for="show_department_field">
                                        إظهار حقل الإدارة
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i>
                            إلغاء
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i>
                            حفظ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle"></i>
                        تأكيد الحذف
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <p>هل أنت متأكد من حذف الاستمارة "<strong id="deleteFormTitle"></strong>"؟</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-warning"></i>
                        <strong>تنبيه:</strong> سيتم حذف الاستمارة وجميع الإجابات المرتبطة بها نهائياً. هذا الإجراء لا يمكن التراجع عنه.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i>
                        إلغاء
                    </button>
                    <form id="deleteForm" method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteFormId">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i>
                            حذف نهائياً
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Forms data for JavaScript
        const forms = <?= json_encode($forms) ?>;
        
        function resetForm() {
            document.getElementById('formForm').reset();
            document.getElementById('modalAction').value = 'create';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus"></i> إنشاء استمارة جديدة';
            document.getElementById('formId').value = '';
            document.getElementById('status').checked = true;
            document.getElementById('allow_multiple_submissions').checked = true;
            document.getElementById('show_department_field').checked = true;
        }
        
        function editForm(id) {
            const form = forms.find(f => f.id === id);
            if (!form) return;
            
            // Populate form
            document.getElementById('formId').value = form.id;
            document.getElementById('title').value = form.title;
            document.getElementById('description').value = form.description || '';
            document.getElementById('status').checked = form.status === 'active';
            document.getElementById('allow_multiple_submissions').checked = form.allow_multiple_submissions == 1;
            document.getElementById('show_department_field').checked = form.show_department_field == 1;
            
            // Update modal
            document.getElementById('modalAction').value = 'update';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> تعديل الاستمارة';
            
            // Show modal
            new bootstrap.Modal(document.getElementById('formModal')).show();
        }
        
        function deleteForm(id, title) {
            document.getElementById('deleteFormId').value = id;
            document.getElementById('deleteFormTitle').textContent = title;
            
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
        
        function toggleStatus(id, status) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="status" value="${status}">
            `;
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Form validation
        document.getElementById('formForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            
            if (!title) {
                e.preventDefault();
                document.getElementById('title').classList.add('is-invalid');
                return false;
            }
            
            document.getElementById('title').classList.remove('is-invalid');
        });
        
        // Real-time validation
        document.getElementById('title').addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('is-invalid');
            }
        });
        
        // Auto dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success')) {
                    new bootstrap.Alert(alert).close();
                }
            });
        }, 5000);
    </script>
</body>
</html>