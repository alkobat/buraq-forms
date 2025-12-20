<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use BuraqForms\Core\Database;
use BuraqForms\Core\Services\TemplateService;
use BuraqForms\Core\Services\PermissionService;
use BuraqForms\Core\Services\AuditService;
use BuraqForms\Core\Services\FormService;

// التحقق من المصادقة
require_once __DIR__ . '/../auth-check.php';

$database = Database::getConnection();
$templateService = new TemplateService($database);
$permissionService = new PermissionService($database);
$auditService = new AuditService($database);
$formService = new FormService($database);

$adminId = $_SESSION['user']['id'] ?? 0;

// التحقق من الصلاحية
if (!$permissionService->hasPermission($adminId, 'templates.manage')) {
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
            case 'create_template':
                $formId = (int) ($_POST['form_id'] ?? 0);
                $templateName = $_POST['template_name'] ?? '';
                $templateDescription = $_POST['template_description'] ?? '';
                $isPublic = isset($_POST['is_public']);
                
                if ($formId > 0 && !empty($templateName)) {
                    $templateId = $templateService->createTemplateFromForm($formId, $templateName, $templateDescription, $adminId, $isPublic);
                    if ($templateId) {
                        $auditService->logCreate($adminId, 'form_template', $templateId, [
                            'template_name' => $templateName,
                            'source_form_id' => $formId,
                            'is_public' => $isPublic
                        ]);
                        $message = 'تم إنشاء القالب بنجاح';
                        $messageType = 'success';
                    } else {
                        $message = 'فشل في إنشاء القالب';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'create_form_from_template':
                $templateId = (int) ($_POST['template_id'] ?? 0);
                $formTitle = $_POST['form_title'] ?? '';
                $departmentId = (int) ($_POST['department_id'] ?? 0);
                
                if ($templateId > 0 && !empty($formTitle) && $departmentId > 0) {
                    $newFormId = $templateService->createFormFromTemplate($templateId, $formTitle, $departmentId, $adminId);
                    if ($newFormId) {
                        $auditService->logCreate($adminId, 'form', $newFormId, [
                            'created_from_template' => $templateId,
                            'title' => $formTitle,
                            'department_id' => $departmentId
                        ]);
                        $message = 'تم إنشاء الاستمارة من القالب بنجاح';
                        $messageType = 'success';
                    } else {
                        $message = 'فشل في إنشاء الاستمارة';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'update_template':
                $templateId = (int) ($_POST['template_id'] ?? 0);
                $templateData = [
                    'template_name' => $_POST['template_name'] ?? '',
                    'template_description' => $_POST['template_description'] ?? '',
                    'category' => $_POST['category'] ?? '',
                    'is_public' => isset($_POST['is_public'])
                ];
                
                $result = $templateService->updateTemplate($templateId, $templateData, $adminId);
                if ($result) {
                    $auditService->logUpdate($adminId, 'form_template', $templateId, [], $templateData);
                    $message = 'تم تحديث القالب بنجاح';
                    $messageType = 'success';
                } else {
                    $message = 'فشل في تحديث القالب';
                    $messageType = 'error';
                }
                break;
                
            case 'delete_template':
                $templateId = (int) ($_POST['template_id'] ?? 0);
                
                $result = $templateService->deleteTemplate($templateId, $adminId);
                if ($result) {
                    $auditService->logDelete($adminId, 'form_template', $templateId, []);
                    $message = 'تم حذف القالب بنجاح';
                    $messageType = 'success';
                } else {
                    $message = 'فشل في حذف القالب';
                    $messageType = 'error';
                }
                break;
                
            case 'duplicate_template':
                $templateId = (int) ($_POST['template_id'] ?? 0);
                
                $duplicateId = $templateService->duplicateTemplate($templateId, $adminId);
                if ($duplicateId) {
                    $auditService->logCreate($adminId, 'form_template', $duplicateId, [
                        'duplicated_from' => $templateId
                    ]);
                    $message = 'تم نسخ القالب بنجاح';
                    $messageType = 'success';
                } else {
                    $message = 'فشل في نسخ القالب';
                    $messageType = 'error';
                }
                break;
                
            case 'export_template':
                $templateId = (int) ($_POST['template_id'] ?? 0);
                
                $exportData = $templateService->exportTemplate($templateId);
                if ($exportData) {
                    $auditService->logExport($adminId, 'form_template', $templateId, 'json');
                    
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="template_' . $templateId . '.json"');
                    echo $exportData;
                    exit;
                } else {
                    $message = 'فشل في تصدير القالب';
                    $messageType = 'error';
                }
                break;
                
            case 'import_template':
                if (isset($_FILES['template_file']) && $_FILES['template_file']['error'] === UPLOAD_ERR_OK) {
                    $templateJson = file_get_contents($_FILES['template_file']['tmp_name']);
                    
                    $templateId = $templateService->importTemplate($templateJson, $adminId);
                    if ($templateId) {
                        $auditService->logCreate($adminId, 'form_template', $templateId, [
                            'imported' => true
                        ]);
                        $message = 'تم استيراد القالب بنجاح';
                        $messageType = 'success';
                    } else {
                        $message = 'فشل في استيراد القالب';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'لم يتم رفع أي ملف';
                    $messageType = 'error';
                }
                break;
        }
    } catch (Exception $e) {
        $message = 'حدث خطأ: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// جلب البيانات
$allTemplates = $templateService->getAllTemplates($adminId);
$templateCategories = $templateService->getTemplateCategories();
$templateStats = $templateService->getTemplateStats();

// جلب جميع الاستمارات لإنشاء قوالب منها
$allForms = $formService->getAllForms();

// جلب الإدارات
$departmentService = new \BuraqForms\Core\Services\DepartmentService($database);
$departments = $departmentService->getAllDepartments();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة قوالب الاستمارات - نظام تقييم الموظفين</title>
    
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
        
        .template-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            border: 2px solid transparent;
        }
        
        .template-card:hover {
            transform: translateY(-5px);
        }
        
        .template-card.public {
            border-color: #28a745;
            background: linear-gradient(135deg, #f8fff9 0%, #e8f5e8 100%);
        }
        
        .template-usage-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
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
            padding: 10px 25px;
            border-radius: 25px;
            transition: all 0.3s ease;
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
            margin-bottom: 30px;
        }
        
        .category-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.75em;
            margin: 2px;
        }
        
        .dark-mode {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
        }
        
        .dark-mode .template-card,
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
        
        .file-upload-area {
            border: 2px dashed #667eea;
            border-radius: 15px;
            padding: 40px 20px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .file-upload-area:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        
        .file-upload-area.dragover {
            background: rgba(102, 126, 234, 0.1);
            border-color: #764ba2;
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
                        <i class="fas fa-layer-group"></i>
                        قوالب الاستمارات
                    </h4>
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="#templates" data-bs-toggle="tab">
                            <i class="fas fa-list"></i> القوالب
                        </a>
                        <a class="nav-link" href="#create-template" data-bs-toggle="tab">
                            <i class="fas fa-plus"></i> إنشاء قالب
                        </a>
                        <a class="nav-link" href="#import-export" data-bs-toggle="tab">
                            <i class="fas fa-exchange-alt"></i> استيراد/تصدير
                        </a>
                        <a class="nav-link" href="#stats" data-bs-toggle="tab">
                            <i class="fas fa-chart-bar"></i> الإحصائيات
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
                                    <i class="fas fa-layer-group"></i>
                                </div>
                                <h4><?= $templateStats['total'] ?? 0 ?></h4>
                                <p class="text-muted mb-0">إجمالي القوالب</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon bg-success">
                                    <i class="fas fa-globe"></i>
                                </div>
                                <h4><?= $templateStats['public'] ?? 0 ?></h4>
                                <p class="text-muted mb-0">قوالب عامة</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-card">
                                <h6>أكثر القوالب استخداماً</h6>
                                <?php if (!empty($templateStats['top_templates'])): ?>
                                    <?php foreach (array_slice($templateStats['top_templates'], 0, 3) as $template): ?>
                                        <div class="d-flex justify-content-between">
                                            <span><?= htmlspecialchars($template['template_name']) ?></span>
                                            <span class="badge bg-secondary"><?= $template['usage_count'] ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-content">
                        <!-- قائمة القوالب -->
                        <div class="tab-pane fade show active" id="templates">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h3>
                                    <i class="fas fa-list"></i>
                                    القوالب المتاحة
                                </h3>
                                <div>
                                    <select class="form-select" style="width: 200px;" onchange="filterTemplates(this.value)">
                                        <option value="">جميع الفئات</option>
                                        <?php foreach ($templateCategories as $category): ?>
                                            <option value="<?= htmlspecialchars($category['category']) ?>">
                                                <?= htmlspecialchars($category['category']) ?> (<?= $category['template_count'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <?php if (empty($allTemplates)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-layer-group fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">لا توجد قوالب محفوظة</h5>
                                    <p class="text-muted">ابدأ بإنشاء قالبك الأول</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($allTemplates as $template): ?>
                                        <div class="col-md-6">
                                            <div class="template-card <?= $template['is_public'] ? 'public' : '' ?>">
                                                <?php if ($template['is_public']): ?>
                                                    <div class="template-usage-badge">
                                                        <i class="fas fa-globe"></i> عام
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div class="flex-grow-1">
                                                        <h5 class="mb-1"><?= htmlspecialchars($template['template_name']) ?></h5>
                                                        <p class="text-muted mb-2"><?= htmlspecialchars($template['template_description'] ?? '') ?></p>
                                                        <div class="mb-2">
                                                            <?php if ($template['category']): ?>
                                                                <span class="category-badge"><?= htmlspecialchars($template['category']) ?></span>
                                                            <?php endif; ?>
                                                            <span class="badge bg-light text-dark">
                                                                <i class="fas fa-eye"></i> <?= $template['usage_count'] ?>
                                                            </span>
                                                        </div>
                                                        <small class="text-muted">
                                                            <i class="fas fa-user"></i> <?= htmlspecialchars($template['creator_name']) ?>
                                                            <br>
                                                            <i class="fas fa-calendar"></i> <?= date('Y-m-d', strtotime($template['created_at'])) ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            onclick="showCreateFormModal(<?= $template['id'] ?>, '<?= addslashes($template['template_name']) ?>')">
                                                        <i class="fas fa-plus"></i> إنشاء استمارة
                                                    </button>
                                                    <?php if ($template['created_by'] == $adminId): ?>
                                                        <button type="button" class="btn btn-sm btn-primary" 
                                                                onclick="editTemplate(<?= $template['id'] ?>, '<?= addslashes($template['template_name']) ?>', '<?= addslashes($template['template_description'] ?? '') ?>', '<?= addslashes($template['category'] ?? '') ?>', <?= $template['is_public'] ? 'true' : 'false' ?>)">
                                                            <i class="fas fa-edit"></i> تحرير
                                                        </button>
                                                    <?php endif; ?>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-info dropdown-toggle" data-bs-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="action" value="duplicate_template">
                                                                    <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                                                    <button type="submit" class="dropdown-item">
                                                                        <i class="fas fa-copy"></i> نسخ
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <li>
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="action" value="export_template">
                                                                    <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                                                    <button type="submit" class="dropdown-item">
                                                                        <i class="fas fa-download"></i> تصدير
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <?php if ($template['created_by'] == $adminId): ?>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li>
                                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا القالب؟')">
                                                                        <input type="hidden" name="action" value="delete_template">
                                                                        <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                                                        <button type="submit" class="dropdown-item text-danger">
                                                                            <i class="fas fa-trash"></i> حذف
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- إنشاء قالب جديد -->
                        <div class="tab-pane fade" id="create-template">
                            <h3 class="mb-4">
                                <i class="fas fa-plus"></i>
                                إنشاء قالب جديد
                            </h3>
                            
                            <form method="POST" class="form-section">
                                <input type="hidden" name="action" value="create_template">
                                
                                <div class="mb-3">
                                    <label class="form-label">الاستمارة المصدر</label>
                                    <select class="form-select" name="form_id" required>
                                        <option value="">-- اختر الاستمارة --</option>
                                        <?php foreach ($allForms as $form): ?>
                                            <option value="<?= $form['id'] ?>"><?= htmlspecialchars($form['title']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">سيتم إنشاء قالب من بنية هذه الاستمارة وحقولها</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">اسم القالب</label>
                                    <input type="text" class="form-control" name="template_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">وصف القالب</label>
                                    <textarea class="form-control" name="template_description" rows="3"></textarea>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input class="form-check-input" type="checkbox" name="is_public" id="is_public">
                                    <label class="form-check-label" for="is_public">
                                        قالب عام (يمكن للمشرفين الآخرين استخدامه)
                                    </label>
                                </div>
                                
                                <button type="submit" class="btn btn-gradient">
                                    <i class="fas fa-save"></i> إنشاء القالب
                                </button>
                            </form>
                        </div>
                        
                        <!-- استيراد/تصدير -->
                        <div class="tab-pane fade" id="import-export">
                            <h3 class="mb-4">
                                <i class="fas fa-exchange-alt"></i>
                                استيراد وتصدير القوالب
                            </h3>
                            
                            <div class="row">
                                <!-- استيراد -->
                                <div class="col-md-6">
                                    <div class="form-section">
                                        <h5><i class="fas fa-upload"></i> استيراد قالب</h5>
                                        
                                        <form method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="action" value="import_template">
                                            
                                            <div class="file-upload-area mb-3" id="dropZone">
                                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                                <p>اسحب ملف JSON هنا أو انقر للاختيار</p>
                                                <input type="file" name="template_file" accept=".json" style="display: none;" id="fileInput" required>
                                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('fileInput').click()">
                                                    اختر ملف
                                                </button>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-gradient">
                                                <i class="fas fa-upload"></i> استيراد القالب
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- تصدير -->
                                <div class="col-md-6">
                                    <div class="form-section">
                                        <h5><i class="fas fa-download"></i> تصدير قالب</h5>
                                        
                                        <?php if (empty($allTemplates)): ?>
                                            <p class="text-muted">لا توجد قوالب للتصدير</p>
                                        <?php else: ?>
                                            <div class="mb-3">
                                                <label class="form-label">اختر القالب للتصدير</label>
                                                <div class="list-group">
                                                    <?php foreach ($allTemplates as $template): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="export_template">
                                                            <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                                            <button type="submit" class="list-group-item list-group-item-action">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <h6 class="mb-1"><?= htmlspecialchars($template['template_name']) ?></h6>
                                                                        <small class="text-muted"><?= htmlspecialchars($template['template_description'] ?? '') ?></small>
                                                                    </div>
                                                                    <i class="fas fa-download"></i>
                                                                </div>
                                                            </button>
                                                        </form>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- الإحصائيات -->
                        <div class="tab-pane fade" id="stats">
                            <h3 class="mb-4">
                                <i class="fas fa-chart-bar"></i>
                                إحصائيات القوالب
                            </h3>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="stat-card">
                                        <h5>القوالب حسب الفئة</h5>
                                        <?php if (!empty($templateStats['by_category'])): ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>الفئة</th>
                                                            <th>العدد</th>
                                                            <th>الاستخدام</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($templateStats['by_category'] as $category): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($category['category']) ?></td>
                                                                <td><span class="badge bg-primary"><?= $category['count'] ?></span></td>
                                                                <td><span class="badge bg-secondary"><?= $category['total_usage'] ?></span></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">لا توجد بيانات</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="stat-card">
                                        <h5>أحدث القوالب</h5>
                                        <?php if (!empty($templateStats['recent'])): ?>
                                            <div class="list-group list-group-flush">
                                                <?php foreach ($templateStats['recent'] as $template): ?>
                                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <h6 class="mb-1"><?= htmlspecialchars($template['template_name']) ?></h6>
                                                            <small class="text-muted">
                                                                <?= $template['category'] ? htmlspecialchars($template['category']) . ' • ' : '' ?>
                                                                <?= date('Y-m-d', strtotime($template['created_at'])) ?>
                                                            </small>
                                                        </div>
                                                        <span class="badge bg-light"><?= $template['usage_count'] ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">لا توجد قوالب</p>
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
    
    <!-- Modal لإنشاء استمارة من قالب -->
    <div class="modal fade" id="createFormModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إنشاء استمارة من قالب</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_form_from_template">
                        <input type="hidden" name="template_id" id="create_form_template_id">
                        
                        <div class="mb-3">
                            <label class="form-label">القالب المحدد</label>
                            <input type="text" class="form-control" id="create_form_template_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">عنوان الاستمارة الجديدة</label>
                            <input type="text" class="form-control" name="form_title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">الإدارة</label>
                            <select class="form-select" name="department_id" required>
                                <option value="">-- اختر الإدارة --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">إنشاء الاستمارة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal لتحرير القالب -->
    <div class="modal fade" id="editTemplateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تحرير القالب</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_template">
                        <input type="hidden" name="template_id" id="edit_template_id">
                        
                        <div class="mb-3">
                            <label class="form-label">اسم القالب</label>
                            <input type="text" class="form-control" name="template_name" id="edit_template_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">وصف القالب</label>
                            <textarea class="form-control" name="template_description" id="edit_template_description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">الفئة</label>
                            <input type="text" class="form-control" name="category" id="edit_template_category">
                            <small class="text-muted">مثل: hr, finance, it, general</small>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input class="form-check-input" type="checkbox" name="is_public" id="edit_template_public">
                            <label class="form-check-label" for="edit_template_public">
                                قالب عام
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">حفظ التعديل</button>
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
        
        // تصفية القوالب
        function filterTemplates(category) {
            const templates = document.querySelectorAll('.template-card');
            templates.forEach(template => {
                if (!category || template.dataset.category === category) {
                    template.style.display = 'block';
                } else {
                    template.style.display = 'none';
                }
            });
        }
        
        // إظهار modal إنشاء استمارة
        function showCreateFormModal(templateId, templateName) {
            document.getElementById('create_form_template_id').value = templateId;
            document.getElementById('create_form_template_name').value = templateName;
            
            var modal = new bootstrap.Modal(document.getElementById('createFormModal'));
            modal.show();
        }
        
        // إظهار modal تحرير القالب
        function editTemplate(templateId, name, description, category, isPublic) {
            document.getElementById('edit_template_id').value = templateId;
            document.getElementById('edit_template_name').value = name;
            document.getElementById('edit_template_description').value = description;
            document.getElementById('edit_template_category').value = category;
            document.getElementById('edit_template_public').checked = isPublic;
            
            var modal = new bootstrap.Modal(document.getElementById('editTemplateModal'));
            modal.show();
        }
        
        // Drag & Drop للملفات
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight(e) {
            dropZone.classList.add('dragover');
        }
        
        function unhighlight(e) {
            dropZone.classList.remove('dragover');
        }
        
        dropZone.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                fileInput.files = files;
                dropZone.querySelector('p').textContent = 'تم اختيار الملف: ' + files[0].name;
            }
        }
        
        // تحديث النقر
        dropZone.addEventListener('click', () => fileInput.click());
        
        // معالجة اختيار الملف
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                dropZone.querySelector('p').textContent = 'تم اختيار الملف: ' + e.target.files[0].name;
            }
        });
    </script>
</body>
</html>