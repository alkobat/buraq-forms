<?php

declare(strict_types=1);

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
require_once SRC_PATH . '/Core/Services/DepartmentService.php';

// Get current user
$current_user = current_user();

// Get user role for conditional access
$user_role = $current_user['role'] ?? 'editor';

// التحقق من الدور للوصول لصفحة الإدارات (manager+)
if (!can_access('departments')) {
    http_response_code(403);
    die('غير مسموح بالوصول لهذه الصفحة - مطلوب صلاحية إدارة الإدارات');
}

// إنشاء خدمة إدارة الإدارات
$departmentService = new BuraqForms\Core\Services\DepartmentService($pdo);

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
                    'name' => $_POST['name'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'manager_id' => !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null,
                    'is_active' => isset($_POST['is_active'])
                ];
                $departmentService->create($data);
                $success = 'تم إنشاء الإدارة بنجاح';
                break;

            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $data = [
                    'name' => $_POST['name'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'manager_id' => !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null,
                    'is_active' => isset($_POST['is_active'])
                ];
                $departmentService->update($id, $data);
                $success = 'تم تحديث الإدارة بنجاح';
                break;

            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                $departmentService->delete($id);
                $success = 'تم حذف الإدارة بنجاح';
                break;

            case 'toggle_status':
                $id = (int)($_POST['id'] ?? 0);
                $isActive = (bool)($_POST['is_active'] ?? false);
                $departmentService->setStatus($id, $isActive);
                $success = $isActive ? 'تم تفعيل الإدارة بنجاح' : 'تم تعطيل الإدارة بنجاح';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// إنشاء CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// جلب قائمة الإدارات
$departments = $departmentService->list();
$managers = $departmentService->getManagersList();

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الإدارات</title>
    
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
                            <a class="nav-link active" href="departments.php">
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
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="page-header fade-in">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="page-title">
                                <i class="fas fa-building text-primary"></i>
                                إدارة الإدارات
                            </h1>
                            <p class="page-description">إدارة ومتابعة إدارات المؤسسة</p>
                        </div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#departmentModal" onclick="resetForm()">
                            <i class="fas fa-plus"></i>
                            إضافة إدارة جديدة
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

                <!-- Departments Table -->
                <div class="card fade-in">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i>
                            قائمة الإدارات
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($departments)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-building fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">لا توجد إدارات مسجلة</h5>
                            <p class="text-muted">ابدأ بإضافة إدارة جديدة</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>الاسم</th>
                                        <th>الوصف</th>
                                        <th>المدير</th>
                                        <th>الحالة</th>
                                        <th>تاريخ الإنشاء</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($dept['name']) ?></strong>
                                        </td>
                                        <td>
                                            <?= $dept['description'] ? htmlspecialchars($dept['description']) : '<span class="text-muted">غير محدد</span>' ?>
                                        </td>
                                        <td>
                                            <?php if ($dept['manager_name']): ?>
                                                <div>
                                                    <i class="fas fa-user-tie text-primary"></i>
                                                    <strong><?= htmlspecialchars($dept['manager_name']) ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?= htmlspecialchars($dept['manager_email']) ?></small>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">غير محدد</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($dept['is_active']): ?>
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
                                            <small class="text-muted">
                                                <?= date('Y-m-d', strtotime($dept['created_at'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="editDepartment(<?= $dept['id'] ?>)"
                                                        title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <button type="button" class="btn btn-sm btn-outline-warning" 
                                                        onclick="toggleStatus(<?= $dept['id'] ?>, <?= $dept['is_active'] ? 'false' : 'true' ?>)"
                                                        title="<?= $dept['is_active'] ? 'تعطيل' : 'تفعيل' ?>">
                                                    <i class="fas <?= $dept['is_active'] ? 'fa-pause' : 'fa-play' ?>"></i>
                                                </button>
                                                
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteDepartment(<?= $dept['id'] ?>, '<?= htmlspecialchars($dept['name'], ENT_QUOTES) ?>')"
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

    <!-- Department Modal -->
    <div class="modal fade" id="departmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="departmentForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" id="modalAction" value="create">
                    <input type="hidden" name="id" id="departmentId">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">
                            <i class="fas fa-plus"></i>
                            إضافة إدارة جديدة
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">اسم الإدارة *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="invalid-feedback">يرجى إدخال اسم الإدارة</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">الوصف</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="manager_id" class="form-label">المدير</label>
                            <select class="form-select" id="manager_id" name="manager_id">
                                <option value="">اختر المدير</option>
                                <?php foreach ($managers as $manager): ?>
                                <option value="<?= $manager['id'] ?>"><?= htmlspecialchars($manager['name']) ?> (<?= htmlspecialchars($manager['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                تفعيل الإدارة
                            </label>
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
                    <p>هل أنت متأكد من حذف الإدارة "<strong id="deleteDepartmentName"></strong>"؟</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-warning"></i>
                        <strong>تنبيه:</strong> لا يمكن حذف الإدارة إذا كانت تحتوي على بيانات مرتبطة (موظفين، استمارات، أو إجابات).
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
                        <input type="hidden" name="id" id="deleteDepartmentId">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i>
                            حذف
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Department data for JavaScript
        const departments = <?= json_encode($departments) ?>;
        
        function resetForm() {
            document.getElementById('departmentForm').reset();
            document.getElementById('modalAction').value = 'create';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus"></i> إضافة إدارة جديدة';
            document.getElementById('departmentId').value = '';
            document.getElementById('is_active').checked = true;
        }
        
        function editDepartment(id) {
            const department = departments.find(d => d.id === id);
            if (!department) return;
            
            // Populate form
            document.getElementById('departmentId').value = department.id;
            document.getElementById('name').value = department.name;
            document.getElementById('description').value = department.description || '';
            document.getElementById('manager_id').value = department.manager_id || '';
            document.getElementById('is_active').checked = department.is_active == 1;
            
            // Update modal
            document.getElementById('modalAction').value = 'update';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> تعديل الإدارة';
            
            // Show modal
            new bootstrap.Modal(document.getElementById('departmentModal')).show();
        }
        
        function deleteDepartment(id, name) {
            document.getElementById('deleteDepartmentId').value = id;
            document.getElementById('deleteDepartmentName').textContent = name;
            
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
        
        function toggleStatus(id, isActive) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="is_active" value="${isActive}">
            `;
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Form validation
        document.getElementById('departmentForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            
            if (!name) {
                e.preventDefault();
                document.getElementById('name').classList.add('is-invalid');
                return false;
            }
            
            document.getElementById('name').classList.remove('is-invalid');
        });
        
        // Real-time validation
        document.getElementById('name').addEventListener('input', function() {
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