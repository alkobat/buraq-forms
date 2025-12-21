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
require_once __DIR__ . '/../../src/Core/Services/FormService.php';
require_once __DIR__ . '/../../src/Core/Services/FormFieldService.php';

// Get current user
$current_user = current_user();

// Get user role for conditional access
$user_role = $current_user['role'] ?? 'editor';

// التحقق من الدور للوصول لصفحة منشئ الاستمارات (editor+)
if (!can_access('forms')) {
    http_response_code(403);
    die('غير مسموح بالوصول لهذه الصفحة');
}

// إنشاء خدمات إدارة الاستمارات والحقول
$formService = new BuraqForms\Core\Services\FormService($pdo);
$formFieldService = new BuraqForms\Core\Services\FormFieldService($pdo);

$error = null;
$success = null;
$formId = (int)($_GET['id'] ?? 0);

if ($formId <= 0) {
    header('Location: forms.php');
    exit;
}

// جلب بيانات الاستمارة
try {
    $form = $formService->getById($formId);
} catch (Exception $e) {
    header('Location: forms.php');
    exit;
}

// جلب الحقول الحالية
$fields = $formFieldService->getFieldsForForm($formId, true);

// معالجة العمليات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // التحقق من CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            throw new Exception('رمز الأمان غير صحيح');
        }

        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_field':
                $data = [
                    'field_type' => $_POST['field_type'] ?? '',
                    'label' => $_POST['label'] ?? '',
                    'placeholder' => $_POST['placeholder'] ?? '',
                    'is_required' => isset($_POST['is_required']),
                    'helper_text' => $_POST['helper_text'] ?? '',
                    'validation_rules' => [
                        'min_length' => !empty($_POST['min_length']) ? (int)$_POST['min_length'] : null,
                        'max_length' => !empty($_POST['max_length']) ? (int)$_POST['max_length'] : null,
                        'min' => !empty($_POST['min']) ? (float)$_POST['min'] : null,
                        'max' => !empty($_POST['max']) ? (float)$_POST['max'] : null,
                        'regex' => $_POST['regex'] ?? null
                    ]
                ];

                // إضافة خيارات للحقول التي تحتاجها
                if (in_array($data['field_type'], ['select', 'radio', 'checkbox'])) {
                    $options = [];
                    if (!empty($_POST['field_options'])) {
                        $optionsRaw = explode("\n", trim($_POST['field_options']));
                        foreach ($optionsRaw as $option) {
                            $option = trim($option);
                            if (!empty($option)) {
                                $options[] = ['value' => $option, 'label' => $option];
                            }
                        }
                    }
                    $data['field_options'] = $options;
                }

                // تحديد مصدر الخيارات
                $data['source_type'] = $_POST['source_type'] ?? 'static';
                if ($data['source_type'] === 'departments') {
                    $data['source_type'] = 'departments';
                }

                $formFieldService->addField($formId, $data);
                $success = 'تم إضافة الحقل بنجاح';
                break;

            case 'update_field':
                $fieldId = (int)($_POST['field_id'] ?? 0);
                $data = [
                    'label' => $_POST['label'] ?? '',
                    'placeholder' => $_POST['placeholder'] ?? '',
                    'is_required' => isset($_POST['is_required']),
                    'helper_text' => $_POST['helper_text'] ?? '',
                    'validation_rules' => [
                        'min_length' => !empty($_POST['min_length']) ? (int)$_POST['min_length'] : null,
                        'max_length' => !empty($_POST['max_length']) ? (int)$_POST['max_length'] : null,
                        'min' => !empty($_POST['min']) ? (float)$_POST['min'] : null,
                        'max' => !empty($_POST['max']) ? (float)$_POST['max'] : null,
                        'regex' => $_POST['regex'] ?? null
                    ]
                ];

                // إضافة خيارات للحقول التي تحتاجها
                if (in_array($_POST['field_type'], ['select', 'radio', 'checkbox'])) {
                    $options = [];
                    if (!empty($_POST['field_options'])) {
                        $optionsRaw = explode("\n", trim($_POST['field_options']));
                        foreach ($optionsRaw as $option) {
                            $option = trim($option);
                            if (!empty($option)) {
                                $options[] = ['value' => $option, 'label' => $option];
                            }
                        }
                    }
                    $data['field_options'] = $options;
                }

                $data['source_type'] = $_POST['source_type'] ?? 'static';
                if ($data['source_type'] === 'departments') {
                    $data['source_type'] = 'departments';
                }

                $formFieldService->updateField($fieldId, $data);
                $success = 'تم تحديث الحقل بنجاح';
                break;

            case 'delete_field':
                $fieldId = (int)($_POST['field_id'] ?? 0);
                $formFieldService->deleteField($fieldId);
                $success = 'تم حذف الحقل بنجاح';
                break;

            case 'reorder_fields':
                $orderedIds = array_map('intval', $_POST['field_ids'] ?? []);
                if (!empty($orderedIds)) {
                    $formFieldService->reorderFields($formId, $orderedIds);
                    $success = 'تم إعادة ترتيب الحقول بنجاح';
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    
    // إعادة تحميل الحقول بعد أي عملية
    $fields = $formFieldService->getFieldsForForm($formId, true);
}

// إنشاء CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// أنواع الحقول المتاحة
$fieldTypes = [
    'text' => [
        'label' => 'نص قصير',
        'icon' => 'fas fa-font',
        'description' => 'حقل نص عادي لكتابة النصوص القصيرة'
    ],
    'textarea' => [
        'label' => 'نص طويل',
        'icon' => 'fas fa-align-left',
        'description' => 'منطقة نص متعددة الأسطر'
    ],
    'email' => [
        'label' => 'بريد إلكتروني',
        'icon' => 'fas fa-envelope',
        'description' => 'حقل مخصص للبريد الإلكتروني مع التحقق من صحة التنسيق'
    ],
    'number' => [
        'label' => 'رقم',
        'icon' => 'fas fa-hashtag',
        'description' => 'حقل رقمي مع إمكانية تحديد الحد الأدنى والأعلى'
    ],
    'date' => [
        'label' => 'تاريخ',
        'icon' => 'fas fa-calendar',
        'description' => 'محدد التاريخ'
    ],
    'time' => [
        'label' => 'وقت',
        'icon' => 'fas fa-clock',
        'description' => 'محدد الوقت'
    ],
    'select' => [
        'label' => 'قائمة منسدلة',
        'icon' => 'fas fa-chevron-down',
        'description' => 'قائمة منسدلة للاختيار من بينها'
    ],
    'radio' => [
        'label' => 'اختيار واحد',
        'icon' => 'fas fa-check-circle',
        'description' => 'أزرار اختيار واحد فقط'
    ],
    'checkbox' => [
        'label' => 'مربعات اختيار',
        'icon' => 'fas fa-square',
        'description' => 'مربعات اختيار متعدد'
    ],
    'file' => [
        'label' => 'رفع ملف',
        'icon' => 'fas fa-upload',
        'description' => 'حقل لرفع الملفات'
    ],
    'repeater' => [
        'label' => 'مجموعة متكررة',
        'icon' => 'fas fa-copy',
        'description' => 'مجموعة من الحقول يمكن تكريرها'
    ]
];

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>محرر الاستمارة - <?= htmlspecialchars($form['title']) ?></title>
    
    <!-- Bootstrap RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- SortableJS for drag and drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../../assets/css/admin.css" rel="stylesheet">
    
    <style>
        .field-builder-sidebar {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .field-types-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .field-type-btn {
            border: 2px dashed #e9ecef;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        
        .field-type-btn:hover {
            border-color: #667eea;
            background-color: rgba(102, 126, 234, 0.05);
            transform: translateY(-2px);
        }
        
        .field-type-btn.active {
            border-color: #667eea;
            background-color: rgba(102, 126, 234, 0.1);
        }
        
        .fields-container {
            min-height: 200px;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            border: 2px dashed #dee2e6;
        }
        
        .field-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            position: relative;
            cursor: move;
            transition: all 0.3s ease;
        }
        
        .field-item:hover {
            border-color: #667eea;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .field-item.dragging {
            opacity: 0.5;
            transform: rotate(5deg);
        }
        
        .field-item .field-controls {
            position: absolute;
            left: 10px;
            top: 10px;
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .field-item:hover .field-controls {
            opacity: 1;
        }
        
        .field-type-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .field-type-text { background: #e3f2fd; color: #1976d2; }
        .field-type-email { background: #f3e5f5; color: #7b1fa2; }
        .field-type-number { background: #e8f5e8; color: #388e3c; }
        .field-type-date { background: #fff3e0; color: #f57c00; }
        .field-type-select { background: #fce4ec; color: #c2185b; }
        .field-type-radio { background: #e0f2f1; color: #00796b; }
        .field-type-checkbox { background: #f1f8e9; color: #689f38; }
        .field-type-file { background: #fafafa; color: #616161; }
        .field-type-repeater { background: #ede7f6; color: #512da8; }
        
        .builder-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .validation-rules {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            background: #f8f9fa;
        }
        
        .drag-placeholder {
            border: 2px dashed #667eea;
            border-radius: 8px;
            height: 60px;
            margin: 10px 0;
            background: rgba(102, 126, 234, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <main class="col-12 px-md-4 main-content">
                <!-- Builder Header -->
                <div class="builder-header fade-in">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item">
                                        <a href="forms.php" class="text-white-50">الاستمارات</a>
                                    </li>
                                    <li class="breadcrumb-item active text-white" aria-current="page">
                                        <?= htmlspecialchars($form['title']) ?>
                                    </li>
                                </ol>
                            </nav>
                            <h1 class="mb-0">
                                <i class="fas fa-pencil-ruler"></i>
                                محرر الاستمارة
                            </h1>
                            <p class="mb-0 opacity-75">إنشاء وتصميم حقول الاستمارة</p>
                        </div>
                        <div>
                            <a href="forms.php" class="btn btn-light">
                                <i class="fas fa-arrow-right"></i>
                                العودة للاستمارات
                            </a>
                            <a href="preview-form.php?slug=<?= $form['slug'] ?>" 
                               class="btn btn-warning" target="_blank">
                                <i class="fas fa-eye"></i>
                                معاينة
                            </a>
                        </div>
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

                <div class="row">
                    <!-- Field Types Sidebar -->
                    <div class="col-lg-3">
                        <div class="field-builder-sidebar fade-in">
                            <h5 class="mb-3">
                                <i class="fas fa-plus-circle"></i>
                                إضافة حقل جديد
                            </h5>
                            
                            <div class="field-types-grid">
                                <?php foreach ($fieldTypes as $type => $config): ?>
                                <div class="field-type-btn" data-type="<?= $type ?>" onclick="selectFieldType('<?= $type ?>')">
                                    <div class="mb-2">
                                        <i class="<?= $config['icon'] ?> fa-lg"></i>
                                    </div>
                                    <div class="fw-bold small"><?= $config['label'] ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Field Properties Panel -->
                        <div class="field-builder-sidebar fade-in" id="fieldPropertiesPanel" style="display: none;">
                            <h5 class="mb-3" id="fieldPropertiesTitle">
                                <i class="fas fa-cog"></i>
                                إعدادات الحقل
                            </h5>
                            
                            <form id="fieldForm" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" id="fieldAction" value="add_field">
                                <input type="hidden" name="field_id" id="fieldId">
                                <input type="hidden" name="field_type" id="fieldType">
                                
                                <div class="mb-3">
                                    <label class="form-label">نوع الحقل</label>
                                    <div class="form-control-plaintext fw-bold" id="fieldTypeDisplay">-</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="label" class="form-label">عنوان الحقل *</label>
                                    <input type="text" class="form-control" id="label" name="label" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="placeholder" class="form-label">النص التوضيحي</label>
                                    <input type="text" class="form-control" id="placeholder" name="placeholder">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="helper_text" class="form-label">النص المساعد</label>
                                    <textarea class="form-control" id="helper_text" name="helper_text" rows="2"></textarea>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_required" name="is_required">
                                    <label class="form-check-label" for="is_required">
                                        حقل مطلوب
                                    </label>
                                </div>
                                
                                <!-- Dynamic options for select/radio/checkbox -->
                                <div id="fieldOptionsSection" style="display: none;">
                                    <div class="mb-3">
                                        <label class="form-label">الخيارات</label>
                                        <div class="mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="source_type" id="source_static" value="static" checked>
                                                <label class="form-check-label" for="source_static">
                                                    خيارات ثابتة
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="source_type" id="source_departments" value="departments">
                                                <label class="form-check-label" for="source_departments">
                                                    تحميل من الإدارات
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div id="staticOptionsSection">
                                        <label class="form-label">الخيارات (سطر واحد لكل خيار)</label>
                                        <textarea class="form-control" id="field_options" name="field_options" rows="4" placeholder="الخيار الأول
الخيار الثاني
الخيار الثالث"></textarea>
                                    </div>
                                </div>
                                
                                <!-- Validation Rules -->
                                <div class="validation-rules">
                                    <h6 class="mb-2">
                                        <i class="fas fa-shield-alt"></i>
                                        قواعد التحقق
                                    </h6>
                                    
                                    <div class="row">
                                        <div class="col-6">
                                            <label class="form-label small">الحد الأدنى للطول</label>
                                            <input type="number" class="form-control form-control-sm" id="min_length" name="min_length" min="0">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small">الحد الأقصى للطول</label>
                                            <input type="number" class="form-control form-control-sm" id="max_length" name="max_length" min="0">
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-2">
                                        <div class="col-6">
                                            <label class="form-label small">أدنى قيمة</label>
                                            <input type="number" class="form-control form-control-sm" id="min" name="min">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small">أعلى قيمة</label>
                                            <input type="number" class="form-control form-control-sm" id="max" name="max">
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <label class="form-label small">نمط التحقق (Regex)</label>
                                        <input type="text" class="form-control form-control-sm" id="regex" name="regex" placeholder="مثل: ^[A-Za-z]+$">
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i>
                                        حفظ الحقل
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="cancelFieldEdit()">
                                        <i class="fas fa-times"></i>
                                        إلغاء
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Fields Container -->
                    <div class="col-lg-9">
                        <div class="card fade-in">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-list"></i>
                                    حقول الاستمارة
                                    <span class="badge bg-primary ms-2"><?= count($fields) ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="fields-container" id="fieldsContainer">
                                    <?php if (empty($fields)): ?>
                                    <div class="text-center text-muted py-5" id="emptyState">
                                        <i class="fas fa-plus-circle fa-3x mb-3"></i>
                                        <h5>لا توجد حقول بعد</h5>
                                        <p>اختر نوع الحقل من الجانب لإضافة حقل جديد</p>
                                    </div>
                                    <?php else: ?>
                                    <?php foreach ($fields as $index => $field): ?>
                                    <div class="field-item" data-field-id="<?= $field['id'] ?>">
                                        <div class="field-controls">
                                            <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                                    onclick="editField(<?= $field['id'] ?>)" title="تعديل">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteField(<?= $field['id'] ?>)" title="حذف">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        
                                        <div class="field-type-badge field-type-<?= $field['field_type'] ?>">
                                            <?= $fieldTypes[$field['field_type']]['label'] ?? $field['field_type'] ?>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($field['label']) ?></h6>
                                                <?php if ($field['placeholder']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle"></i>
                                                    <?= htmlspecialchars($field['placeholder']) ?>
                                                </small>
                                                <?php endif; ?>
                                                
                                                <?php if ($field['helper_text']): ?>
                                                <div class="mt-1">
                                                    <small class="text-info">
                                                        <i class="fas fa-question-circle"></i>
                                                        <?= htmlspecialchars($field['helper_text']) ?>
                                                    </small>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="text-end">
                                                <?php if ($field['is_required']): ?>
                                                <span class="badge bg-danger">مطلوب</span>
                                                <?php endif; ?>
                                                
                                                <span class="badge bg-<?= $field['is_active'] ? 'success' : 'secondary' ?>">
                                                    <?= $field['is_active'] ? 'نشط' : 'غير نشط' ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($field['field_options'])): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-list"></i>
                                                خيارات: <?= count(json_decode($field['field_options'], true) ?: []) ?>
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($fields)): ?>
                                <div class="mt-3 text-center">
                                    <form id="reorderForm" method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="action" value="reorder_fields">
                                        <input type="hidden" name="field_ids" id="fieldIdsInput">
                                        <button type="submit" class="btn btn-outline-primary" id="saveOrderBtn" style="display: none;">
                                            <i class="fas fa-save"></i>
                                            حفظ الترتيب
                                        </button>
                                    </form>
                                </div>
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
        // Global variables
        let selectedFieldType = null;
        const fieldTypes = <?= json_encode($fieldTypes) ?>;
        const fields = <?= json_encode($fields) ?>;
        
        // Field type selection
        function selectFieldType(type) {
            // Update button states
            document.querySelectorAll('.field-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-type="${type}"]`).classList.add('active');
            
            selectedFieldType = type;
            
            // Show field properties panel
            document.getElementById('fieldPropertiesPanel').style.display = 'block';
            document.getElementById('fieldType').value = type;
            document.getElementById('fieldTypeDisplay').textContent = fieldTypes[type].label;
            
            // Show/hide options section for select/radio/checkbox
            const optionsSection = document.getElementById('fieldOptionsSection');
            if (['select', 'radio', 'checkbox'].includes(type)) {
                optionsSection.style.display = 'block';
            } else {
                optionsSection.style.display = 'none';
            }
            
            // Scroll to properties panel
            document.getElementById('fieldPropertiesPanel').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        }
        
        // Edit field
        function editField(fieldId) {
            const field = fields.find(f => f.id === fieldId);
            if (!field) return;
            
            // Show field properties panel
            document.getElementById('fieldPropertiesPanel').style.display = 'block';
            
            // Populate form
            document.getElementById('fieldAction').value = 'update_field';
            document.getElementById('fieldId').value = fieldId;
            document.getElementById('fieldType').value = field.field_type;
            document.getElementById('fieldTypeDisplay').textContent = fieldTypes[field.field_type].label;
            document.getElementById('label').value = field.label;
            document.getElementById('placeholder').value = field.placeholder || '';
            document.getElementById('helper_text').value = field.helper_text || '';
            document.getElementById('is_required').checked = field.is_required == 1;
            
            // Update field type button state
            document.querySelectorAll('.field-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-type="${field.field_type}"]`).classList.add('active');
            
            // Show/hide options section
            const optionsSection = document.getElementById('fieldOptionsSection');
            if (['select', 'radio', 'checkbox'].includes(field.field_type)) {
                optionsSection.style.display = 'block';
                
                // Set source type
                if (field.source_type === 'departments') {
                    document.getElementById('source_departments').checked = true;
                    document.getElementById('staticOptionsSection').style.display = 'none';
                } else {
                    document.getElementById('source_static').checked = true;
                    document.getElementById('staticOptionsSection').style.display = 'block';
                    
                    // Parse field options
                    let optionsText = '';
                    if (field.field_options) {
                        const options = JSON.parse(field.field_options);
                        optionsText = options.map(opt => opt.label || opt.value).join('\n');
                    }
                    document.getElementById('field_options').value = optionsText;
                }
            } else {
                optionsSection.style.display = 'none';
            }
            
            // Parse validation rules
            if (field.validation_rules) {
                const rules = JSON.parse(field.validation_rules);
                document.getElementById('min_length').value = rules.min_length || '';
                document.getElementById('max_length').value = rules.max_length || '';
                document.getElementById('min').value = rules.min || '';
                document.getElementById('max').value = rules.max || '';
                document.getElementById('regex').value = rules.regex || '';
            }
            
            // Scroll to properties panel
            document.getElementById('fieldPropertiesPanel').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        }
        
        // Cancel field edit
        function cancelFieldEdit() {
            document.getElementById('fieldForm').reset();
            document.getElementById('fieldAction').value = 'add_field';
            document.getElementById('fieldId').value = '';
            document.getElementById('fieldPropertiesPanel').style.display = 'none';
            document.querySelectorAll('.field-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            selectedFieldType = null;
        }
        
        // Delete field
        function deleteField(fieldId) {
            if (!confirm('هل أنت متأكد من حذف هذا الحقل؟')) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="delete_field">
                <input type="hidden" name="field_id" value="${fieldId}">
            `;
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Initialize sortable
        function initSortable() {
            if (document.getElementById('fieldsContainer').children.length === 0) return;
            
            const sortable = Sortable.create(document.getElementById('fieldsContainer'), {
                animation: 150,
                ghostClass: 'drag-placeholder',
                chosenClass: 'dragging',
                onEnd: function(evt) {
                    updateFieldOrder();
                }
            });
        }
        
        // Update field order
        function updateFieldOrder() {
            const fieldIds = [];
            document.querySelectorAll('.field-item').forEach(item => {
                fieldIds.push(parseInt(item.dataset.fieldId));
            });
            
            document.getElementById('fieldIdsInput').value = fieldIds.join(',');
            document.getElementById('saveOrderBtn').style.display = 'inline-block';
        }
        
        // Source type change handler
        document.addEventListener('change', function(e) {
            if (e.target.name === 'source_type') {
                const staticSection = document.getElementById('staticOptionsSection');
                if (e.target.value === 'departments') {
                    staticSection.style.display = 'none';
                } else {
                    staticSection.style.display = 'block';
                }
            }
        });
        
        // Form validation
        document.getElementById('fieldForm').addEventListener('submit', function(e) {
            const label = document.getElementById('label').value.trim();
            const fieldType = document.getElementById('fieldType').value;
            
            if (!label) {
                e.preventDefault();
                document.getElementById('label').classList.add('is-invalid');
                return false;
            }
            
            // Validate options for select/radio/checkbox
            if (['select', 'radio', 'checkbox'].includes(fieldType)) {
                const sourceType = document.querySelector('input[name="source_type"]:checked').value;
                if (sourceType === 'static') {
                    const options = document.getElementById('field_options').value.trim();
                    if (!options) {
                        e.preventDefault();
                        alert('يرجى إضافة خيارات للحقل أو اختيار "تحميل من الإدارات"');
                        return false;
                    }
                }
            }
            
            document.getElementById('label').classList.remove('is-invalid');
        });
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initSortable();
            
            // Auto dismiss alerts
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.classList.contains('alert-success')) {
                        new bootstrap.Alert(alert).close();
                    }
                });
            }, 5000);
        });
        
        // Real-time validation
        document.getElementById('label').addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('is-invalid');
            }
        });
    </script>
</body>
</html>