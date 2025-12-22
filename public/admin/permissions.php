<?php
declare(strict_types=1);

if (!defined('CONFIG_PATH')) {
    require_once __DIR__ . '/../../config/constants.php';
}

// Include required files
require_once SRC_PATH . '/helpers.php';
require_once SRC_PATH . '/Core/Auth.php';

// Require admin authentication only
require_role('admin');

// Validate session security
if (!validate_session()) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';

use BuraqForms\Core\Database;
use BuraqForms\Core\Services\PermissionService;
use BuraqForms\Core\Services\AuditService;
use BuraqForms\Core\Services\DepartmentService;

$database = Database::getConnection();
$permissionService = new PermissionService($database);
$auditService = new AuditService($database);
$departmentService = new DepartmentService($database);

$adminId = $_SESSION['admin_id'] ?? 0;

// Get current user info
$current_user = current_user();

// التحقق من الصلاحية (يتطلب صلاحية إدارة المستخدمين)
if (!$permissionService->hasPermission($adminId, 'users.manage')) {
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
            case 'assign_role':
                $targetAdminId = (int) ($_POST['admin_id'] ?? 0);
                $roleId = (int) ($_POST['role_id'] ?? 0);
                $departmentId = !empty($_POST['department_id']) ? (int) $_POST['department_id'] : null;
                
                if ($targetAdminId > 0 && $roleId > 0) {
                    $result = $permissionService->assignRole($targetAdminId, $roleId, $departmentId);
                    if ($result) {
                        $auditService->logCreate($adminId, 'admin_role_assignment', 0, [
                            'target_admin_id' => $targetAdminId,
                            'role_id' => $roleId,
                            'department_id' => $departmentId
                        ]);
                        $message = 'تم تعيين الدور بنجاح';
                        $messageType = 'success';
                    } else {
                        $message = 'فشل في تعيين الدور';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'remove_role':
                $targetAdminId = (int) ($_POST['admin_id'] ?? 0);
                $roleId = (int) ($_POST['role_id'] ?? 0);
                $departmentId = !empty($_POST['department_id']) ? (int) $_POST['department_id'] : null;
                
                if ($targetAdminId > 0 && $roleId > 0) {
                    $result = $permissionService->removeRole($targetAdminId, $roleId, $departmentId);
                    if ($result) {
                        $auditService->logDelete($adminId, 'admin_role_assignment', 0, [
                            'target_admin_id' => $targetAdminId,
                            'role_id' => $roleId,
                            'department_id' => $departmentId
                        ]);
                        $message = 'تم إزالة الدور بنجاح';
                        $messageType = 'success';
                    } else {
                        $message = 'فشل في إزالة الدور';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'create_role':
                $roleName = $_POST['role_name'] ?? '';
                $roleDescription = $_POST['role_description'] ?? '';
                
                if (!empty($roleName)) {
                    $roleId = $permissionService->createRole($roleName, $roleDescription, false);
                    if ($roleId) {
                        $auditService->logCreate($adminId, 'admin_role', $roleId, [
                            'role_name' => $roleName,
                            'description' => $roleDescription
                        ]);
                        $message = 'تم إنشاء الدور بنجاح';
                        $messageType = 'success';
                    } else {
                        $message = 'فشل في إنشاء الدور';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'set_role_permissions':
                $roleId = (int) ($_POST['role_id'] ?? 0);
                $permissionIds = $_POST['permission_ids'] ?? [];
                
                if ($roleId > 0) {
                    $result = $permissionService->setRolePermissions($roleId, array_map('intval', $permissionIds));
                    if ($result) {
                        $auditService->logUpdate($adminId, 'admin_role_permissions', $roleId, [], [
                            'permission_count' => count($permissionIds)
                        ]);
                        $message = 'تم تحديث صلاحيات الدور بنجاح';
                        $messageType = 'success';
                    } else {
                        $message = 'فشل في تحديث صلاحيات الدور';
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
$allRoles = $permissionService->getAllRolesWithPermissions();
$allPermissions = $permissionService->getAllPermissionsGrouped();

// جلب جميع المشرفين مع أدوارهم
$admins = [];
$sql = "SELECT a.id, a.name, a.email, a.status, a.created_at
        FROM admins a 
        ORDER BY a.name";
$stmt = $database->prepare($sql);
$stmt->execute();
$adminRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($adminRecords as $admin) {
    $admin['roles'] = $permissionService->getAdminRoles($admin['id']);
    $admin['permissions'] = $permissionService->getAdminPermissions($admin['id']);
    $admins[] = $admin;
}

// جلب الإدارات
$departments = $departmentService->getAllDepartments();

// جلب إحصائيات الصلاحيات
$stats = [
    'total_admins' => count($admins),
    'active_admins' => count(array_filter($admins, fn($a) => $a['status'] === 'active')),
    'total_roles' => count($allRoles),
    'total_permissions' => array_sum(array_map(fn($group) => count($group), $allPermissions))
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الصلاحيات والأدوار - نظام تقييم الموظفين</title>
    
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
        
        .admin-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .admin-card:hover {
            transform: translateY(-2px);
        }
        
        .role-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            margin: 2px;
            display: inline-block;
        }
        
        .permission-group {
            border: 1px solid #ddd;
            border-radius: 10px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .permission-group-header {
            background: #f8f9fa;
            padding: 10px 15px;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
        }
        
        .permission-item {
            padding: 8px 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .permission-item:last-child {
            border-bottom: none;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            margin-bottom: 10px;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            transition: all 0.3s ease;
            font-size: 0.9em;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .checkbox-permission {
            margin-left: 10px;
        }
        
        .dark-mode {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
        }
        
        .dark-mode .admin-card,
        .dark-mode .stat-card,
        .dark-mode .form-section {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .dark-mode .form-control,
        .dark-mode .form-select {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
        
        .dark-mode .permission-group {
            border-color: rgba(255,255,255,0.2);
        }
        
        .dark-mode .permission-group-header {
            background: rgba(255,255,255,0.1);
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
                        <i class="fas fa-shield-alt"></i>
                        إدارة الصلاحيات
                    </h4>
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="#admins" data-bs-toggle="tab">
                            <i class="fas fa-users"></i> المشرفين
                        </a>
                        <a class="nav-link" href="#roles" data-bs-toggle="tab">
                            <i class="fas fa-user-tag"></i> الأدوار
                        </a>
                        <a class="nav-link" href="#permissions" data-bs-toggle="tab">
                            <i class="fas fa-key"></i> الصلاحيات
                        </a>
                        <a class="nav-link" href="#create-role" data-bs-toggle="tab">
                            <i class="fas fa-plus"></i> إنشاء دور
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
                        <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?= $messageType === 'error' ? 'exclamation-triangle' : 'check-circle' ?>"></i>
                            <?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- إحصائيات سريعة -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon bg-primary">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h4><?= $stats['total_admins'] ?></h4>
                                <p class="text-muted mb-0">إجمالي المشرفين</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon bg-success">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <h4><?= $stats['active_admins'] ?></h4>
                                <p class="text-muted mb-0">مشرف نشط</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon bg-warning">
                                    <i class="fas fa-user-tag"></i>
                                </div>
                                <h4><?= $stats['total_roles'] ?></h4>
                                <p class="text-muted mb-0">الأدوار</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon bg-info">
                                    <i class="fas fa-key"></i>
                                </div>
                                <h4><?= $stats['total_permissions'] ?></h4>
                                <p class="text-muted mb-0">الصلاحيات</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-content">
                        <!-- إدارة المشرفين -->
                        <div class="tab-pane fade show active" id="admins">
                            <h3 class="mb-4">
                                <i class="fas fa-users"></i>
                                إدارة المشرفين
                            </h3>
                            
                            <div class="row">
                                <?php foreach ($admins as $admin): ?>
                                    <div class="col-md-6">
                                        <div class="admin-card">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h5 class="mb-1"><?= htmlspecialchars($admin['name']) ?></h5>
                                                    <p class="text-muted mb-1"><?= htmlspecialchars($admin['email']) ?></p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar"></i>
                                                        تاريخ الإنشاء: <?= date('Y-m-d', strtotime($admin['created_at'])) ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <span class="badge bg-<?= $admin['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                        <?= $admin['status'] === 'active' ? 'نشط' : 'غير نشط' ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <!-- الأدوار -->
                                            <div class="mb-3">
                                                <h6><i class="fas fa-user-tag"></i> الأدوار:</h6>
                                                <?php if (empty($admin['roles'])): ?>
                                                    <p class="text-muted">لا توجد أدوار محددة</p>
                                                <?php else: ?>
                                                    <?php foreach ($admin['roles'] as $role): ?>
                                                        <span class="role-badge">
                                                            <?= htmlspecialchars($role['role_name']) ?>
                                                            <?php if ($role['department_id']): ?>
                                                                <small>(<?= htmlspecialchars($role['department_name'] ?? 'إدارة محددة') ?>)</small>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- الإجراءات -->
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-primary" 
                                                        onclick="showAssignRoleModal(<?= $admin['id'] ?>, '<?= addslashes($admin['name']) ?>')">
                                                    <i class="fas fa-plus"></i> إضافة دور
                                                </button>
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        onclick="showAdminPermissions(<?= $admin['id'] ?>, '<?= addslashes($admin['name']) ?>')">
                                                    <i class="fas fa-key"></i> الصلاحيات
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- الأدوار -->
                        <div class="tab-pane fade" id="roles">
                            <h3 class="mb-4">
                                <i class="fas fa-user-tag"></i>
                                الأدوار والصلاحيات
                            </h3>
                            
                            <div class="row">
                                <?php foreach ($allRoles as $role): ?>
                                    <div class="col-md-6">
                                        <div class="admin-card">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h5 class="mb-1">
                                                        <?= htmlspecialchars($role['role_name']) ?>
                                                        <?php if ($role['is_system_role']): ?>
                                                            <span class="badge bg-primary">افتراضي</span>
                                                        <?php endif; ?>
                                                    </h5>
                                                    <?php if ($role['role_description']): ?>
                                                        <p class="text-muted mb-0"><?= htmlspecialchars($role['role_description']) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-warning" 
                                                        onclick="showRolePermissions(<?= $role['id'] ?>, '<?= addslashes($role['role_name']) ?>')">
                                                    <i class="fas fa-edit"></i> تحرير
                                                </button>
                                            </div>
                                            
                                            <!-- الصلاحيات -->
                                            <div>
                                                <h6><i class="fas fa-key"></i> الصلاحيات:</h6>
                                                <?php if (empty($role['permissions'])): ?>
                                                    <p class="text-muted">لا توجد صلاحيات محددة</p>
                                                <?php else: ?>
                                                    <div class="d-flex flex-wrap">
                                                        <?php foreach ($role['permissions'] as $permission): ?>
                                                            <span class="badge bg-light text-dark m-1"><?= htmlspecialchars($permission) ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- الصلاحيات -->
                        <div class="tab-pane fade" id="permissions">
                            <h3 class="mb-4">
                                <i class="fas fa-key"></i>
                                الصلاحيات المتاحة
                            </h3>
                            
                            <?php foreach ($allPermissions as $group => $permissions): ?>
                                <div class="permission-group">
                                    <div class="permission-group-header">
                                        <i class="fas fa-folder"></i>
                                        <?= htmlspecialchars(ucfirst($group)) ?>
                                    </div>
                                    <?php foreach ($permissions as $permission): ?>
                                        <div class="permission-item">
                                            <div>
                                                <strong><?= htmlspecialchars($permission['name']) ?></strong>
                                                <?php if ($permission['description']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($permission['description']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- إنشاء دور جديد -->
                        <div class="tab-pane fade" id="create-role">
                            <h3 class="mb-4">
                                <i class="fas fa-plus"></i>
                                إنشاء دور جديد
                            </h3>
                            
                            <form method="POST" class="form-section">
                                <input type="hidden" name="action" value="create_role">
                                
                                <div class="mb-3">
                                    <label class="form-label">اسم الدور</label>
                                    <input type="text" class="form-control" name="role_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">وصف الدور</label>
                                    <textarea class="form-control" name="role_description" rows="3"></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-gradient">
                                    <i class="fas fa-save"></i> إنشاء الدور
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal لإضافة دور لمشرف -->
    <div class="modal fade" id="assignRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة دور للمشرف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_role">
                        <input type="hidden" name="admin_id" id="assign_role_admin_id">
                        
                        <div class="mb-3">
                            <label class="form-label">المشرف</label>
                            <input type="text" class="form-control" id="assign_role_admin_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">الدور</label>
                            <select class="form-select" name="role_id" required>
                                <option value="">-- اختر الدور --</option>
                                <?php foreach ($allRoles as $role): ?>
                                    <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">الإدارة (اختياري)</label>
                            <select class="form-select" name="department_id">
                                <option value="">جميع الإدارات</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">اتركه فارغاً للوصول لجميع الإدارات</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">إضافة الدور</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal لصلاحيات الدور -->
    <div class="modal fade" id="rolePermissionsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">صلاحيات الدور</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="set_role_permissions">
                        <input type="hidden" name="role_id" id="permissions_role_id">
                        
                        <?php foreach ($allPermissions as $group => $permissions): ?>
                            <div class="permission-group">
                                <div class="permission-group-header">
                                    <i class="fas fa-folder"></i>
                                    <?= htmlspecialchars(ucfirst($group)) ?>
                                </div>
                                <?php foreach ($permissions as $permission): ?>
                                    <div class="permission-item">
                                        <div class="form-check">
                                            <input class="form-check-input permission-checkbox" 
                                                   type="checkbox" 
                                                   name="permission_ids[]" 
                                                   value="<?= $permission['id'] ?>"
                                                   id="perm_<?= $permission['id'] ?>">
                                            <label class="form-check-label" for="perm_<?= $permission['id'] ?>">
                                                <strong><?= htmlspecialchars($permission['name']) ?></strong>
                                                <?php if ($permission['description']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($permission['description']) ?></small>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">حفظ الصلاحيات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
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
        
        // إظهار modal إضافة دور
        function showAssignRoleModal(adminId, adminName) {
            document.getElementById('assign_role_admin_id').value = adminId;
            document.getElementById('assign_role_admin_name').value = adminName;
            
            var modal = new bootstrap.Modal(document.getElementById('assignRoleModal'));
            modal.show();
        }
        
        // إظهار modal صلاحيات الدور
        function showRolePermissions(roleId, roleName) {
            document.getElementById('permissions_role_id').value = roleId;
            
            // إعادة تعيين جميع الـ checkboxes
            document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // تحديد الصلاحيات الحالية للدور
            const role = <?= json_encode($allRoles) ?>.find(r => r.id === roleId);
            if (role && role.permissions) {
                role.permissions.forEach(permission => {
                    const checkbox = document.querySelector(`input[value="${permission}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
            }
            
            var modal = new bootstrap.Modal(document.getElementById('rolePermissionsModal'));
            modal.show();
        }
        
        // إظهار صلاحيات المشرف
        function showAdminPermissions(adminId, adminName) {
            // يمكن إضافة modal لعرض صلاحيات المشرف
            alert('صلاحيات المشرف: ' + adminName);
        }
    </script>
</body>
</html>