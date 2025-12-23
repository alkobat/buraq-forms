<?php

declare(strict_types=1);

// تضمين الإعدادات
require_once __DIR__ . '/../../config/database.php';
require_once SRC_PATH . '/helpers.php';
require_once SRC_PATH . '/Core/Services/FormService.php';
require_once SRC_PATH . '/Core/Services/FormFieldService.php';
require_once SRC_PATH . '/Core/Services/DepartmentService.php';

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (empty($slug)) {
    header('Location: /buraq-forms/public/index.php');
    exit;
}

$formService = new BuraqForms\Core\Services\FormService($pdo);
$fieldService = new BuraqForms\Core\Services\FormFieldService($pdo);
$departmentService = new BuraqForms\Core\Services\DepartmentService($pdo);

try {
    $form = $formService->getBySlug($slug);
    
    if ($form['status'] !== 'active') {
        throw new Exception('هذه الاستمارة غير نشطة حالياً');
    }
    
    $fieldDefinitions = $fieldService->getFieldDefinitionsForRendering((int)$form['id']);
    $departments = $departmentService->list(true);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

function renderField(array $field, int $repeatIndex = 0, string $parentKey = ''): string {
    $fieldKey = $field['field_key'];
    if ($parentKey !== '') {
        $fieldId = 'field_' . $parentKey . '_' . $repeatIndex . '_' . $fieldKey;
        $fieldName = $parentKey . '[' . $repeatIndex . '][' . $fieldKey . ']';
    } else {
        $fieldId = 'field_' . $fieldKey . ($repeatIndex > 0 ? '_' . $repeatIndex : '');
        $fieldName = $fieldKey . ($repeatIndex > 0 ? '[' . $repeatIndex . ']' : '');
    }
    $required = $field['is_required'] ? 'required' : '';
    $requiredClass = $field['is_required'] ? 'required' : '';
    
    $html = '<div class="mb-4">';
    $html .= '<label for="' . htmlspecialchars($fieldId) . '" class="form-label ' . $requiredClass . '">';
    $html .= htmlspecialchars($field['label']);
    $html .= '</label>';
    
    switch ($field['field_type']) {
        case 'text':
        case 'email':
        case 'number':
        case 'date':
        case 'time':
            $type = $field['field_type'];
            $placeholder = !empty($field['placeholder']) ? htmlspecialchars($field['placeholder']) : '';
            $html .= '<input type="' . $type . '" ';
            $html .= 'class="form-control" ';
            $html .= 'id="' . htmlspecialchars($fieldId) . '" ';
            $html .= 'name="' . htmlspecialchars($fieldName) . '" ';
            $html .= 'placeholder="' . $placeholder . '" ';
            $html .= $required . ' ';
            
            if (isset($field['validation_rules'])) {
                if (isset($field['validation_rules']['min'])) {
                    $html .= 'min="' . htmlspecialchars((string)$field['validation_rules']['min']) . '" ';
                }
                if (isset($field['validation_rules']['max'])) {
                    $html .= 'max="' . htmlspecialchars((string)$field['validation_rules']['max']) . '" ';
                }
                if (isset($field['validation_rules']['minlength'])) {
                    $html .= 'minlength="' . htmlspecialchars((string)$field['validation_rules']['minlength']) . '" ';
                }
                if (isset($field['validation_rules']['maxlength'])) {
                    $html .= 'maxlength="' . htmlspecialchars((string)$field['validation_rules']['maxlength']) . '" ';
                }
            }
            
            $html .= '>';
            break;
            
        case 'textarea':
            $placeholder = !empty($field['placeholder']) ? htmlspecialchars($field['placeholder']) : '';
            $html .= '<textarea ';
            $html .= 'class="form-control" ';
            $html .= 'id="' . htmlspecialchars($fieldId) . '" ';
            $html .= 'name="' . htmlspecialchars($fieldName) . '" ';
            $html .= 'placeholder="' . $placeholder . '" ';
            $html .= 'rows="5" ';
            $html .= $required . ' ';
            
            if (isset($field['validation_rules'])) {
                if (isset($field['validation_rules']['minlength'])) {
                    $html .= 'minlength="' . htmlspecialchars((string)$field['validation_rules']['minlength']) . '" ';
                }
                if (isset($field['validation_rules']['maxlength'])) {
                    $html .= 'maxlength="' . htmlspecialchars((string)$field['validation_rules']['maxlength']) . '" ';
                }
            }
            
            $html .= '></textarea>';
            break;
            
        case 'select':
            $html .= '<select ';
            $html .= 'class="form-select" ';
            $html .= 'id="' . htmlspecialchars($fieldId) . '" ';
            $html .= 'name="' . htmlspecialchars($fieldName) . '" ';
            $html .= $required . '>';
            $html .= '<option value="">-- اختر --</option>';
            
            if (!empty($field['options'])) {
                foreach ($field['options'] as $option) {
                    $html .= '<option value="' . htmlspecialchars($option['value']) . '">';
                    $html .= htmlspecialchars($option['label']);
                    $html .= '</option>';
                }
            }
            
            $html .= '</select>';
            break;
            
        case 'radio':
            if (!empty($field['options'])) {
                foreach ($field['options'] as $idx => $option) {
                    $optionId = $fieldId . '_' . $idx;
                    $html .= '<div class="form-check">';
                    $html .= '<input class="form-check-input" type="radio" ';
                    $html .= 'id="' . htmlspecialchars($optionId) . '" ';
                    $html .= 'name="' . htmlspecialchars($fieldName) . '" ';
                    $html .= 'value="' . htmlspecialchars($option['value']) . '" ';
                    $html .= $required . '>';
                    $html .= '<label class="form-check-label" for="' . htmlspecialchars($optionId) . '">';
                    $html .= htmlspecialchars($option['label']);
                    $html .= '</label>';
                    $html .= '</div>';
                }
            }
            break;
            
        case 'checkbox':
            if (!empty($field['options'])) {
                foreach ($field['options'] as $idx => $option) {
                    $optionId = $fieldId . '_' . $idx;
                    $optionName = $fieldName . '[]';
                    $html .= '<div class="form-check">';
                    $html .= '<input class="form-check-input" type="checkbox" ';
                    $html .= 'id="' . htmlspecialchars($optionId) . '" ';
                    $html .= 'name="' . htmlspecialchars($optionName) . '" ';
                    $html .= 'value="' . htmlspecialchars($option['value']) . '">';
                    $html .= '<label class="form-check-label" for="' . htmlspecialchars($optionId) . '">';
                    $html .= htmlspecialchars($option['label']);
                    $html .= '</label>';
                    $html .= '</div>';
                }
            }
            break;
            
        case 'file':
            $html .= '<div class="file-upload-container" onclick="document.getElementById(\'' . htmlspecialchars($fieldId) . '\').click();">';
            $html .= '<i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>';
            $html .= '<p>انقر هنا لاختيار الملف أو اسحبه وأفلته</p>';
            $html .= '<input type="file" ';
            $html .= 'class="d-none" ';
            $html .= 'id="' . htmlspecialchars($fieldId) . '" ';
            $html .= 'name="' . htmlspecialchars($fieldName) . '" ';
            $html .= $required . '>';
            $html .= '<div class="file-preview"></div>';
            $html .= '</div>';
            break;
    }
    
    if (!empty($field['helper_text'])) {
        $html .= '<div class="form-text">' . htmlspecialchars($field['helper_text']) . '</div>';
    }
    
    $html .= '<div class="invalid-feedback"></div>';
    $html .= '</div>';
    
    return $html;
}

function renderRepeater(array $field): string {
    $html = '<div class="repeater-container mb-4" data-repeater-key="' . htmlspecialchars($field['field_key']) . '">';
    $html .= '<label class="form-label' . ($field['is_required'] ? ' required' : '') . '">';
    $html .= htmlspecialchars($field['label']);
    $html .= '</label>';
    
    if (!empty($field['helper_text'])) {
        $html .= '<div class="form-text mb-3">' . htmlspecialchars($field['helper_text']) . '</div>';
    }
    
    $html .= '<div class="repeater-group">';
    $html .= '<div class="repeater-group-header">';
    $html .= '<span class="repeater-group-title">المجموعة 1</span>';
    $html .= '<button type="button" class="btn-remove-group">';
    $html .= '<i class="fas fa-times"></i> حذف';
    $html .= '</button>';
    $html .= '</div>';
    
    if (!empty($field['children'])) {
        foreach ($field['children'] as $child) {
            $html .= renderField($child, 0, $field['field_key']);
        }
    }
    
    $html .= '</div>';
    
    $html .= '<button type="button" class="btn-add-group mt-3">';
    $html .= '<i class="fas fa-plus"></i> إضافة مجموعة جديدة';
    $html .= '</button>';
    
    $html .= '</div>';
    
    return $html;
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($form['title'] ?? 'ملء الاستمارة') ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/forms.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1>
                    <i class="fas fa-pen"></i>
                    <?= htmlspecialchars($form['title'] ?? 'ملء الاستمارة') ?>
                </h1>
                <div>
                    <a href="index.php" class="btn btn-light">
                        <i class="fas fa-arrow-right"></i>
                        العودة للقائمة
                    </a>
                </div>
            </div>
        </div>
    </header>

    <?php if (isset($error)): ?>
        <div class="container mt-4">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <strong>خطأ:</strong> <?= htmlspecialchars($error) ?>
            </div>
            <div class="text-center">
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    العودة للقائمة
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Progress Bar -->
        <div class="progress-container">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold">تقدم ملء الاستمارة</span>
                    <span id="progressText">0% مكتمل</span>
                </div>
                <div class="progress">
                    <div id="formProgress" 
                         class="progress-bar" 
                         role="progressbar" 
                         style="width: 0%" 
                         aria-valuenow="0" 
                         aria-valuemin="0" 
                         aria-valuemax="100"></div>
                </div>
            </div>
        </div>

        <div class="container mt-4">
            <?php if (!empty($form['description'])): ?>
                <div class="alert alert-info fade-in">
                    <i class="fas fa-info-circle"></i>
                    <?= nl2br(htmlspecialchars($form['description'])) ?>
                </div>
            <?php endif; ?>

            <div class="card form-card fade-in">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-edit"></i>
                        بيانات الاستمارة
                    </h5>
                </div>
                <div class="card-body">
                    <form id="submissionForm" 
                          method="POST" 
                          action="submit.php" 
                          enctype="multipart/form-data"
                          data-form-slug="<?= htmlspecialchars($form['slug']) ?>">
                        
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="form_id" value="<?= (int)$form['id'] ?>">
                        
                        <!-- Basic Fields -->
                        <div class="mb-4">
                            <label for="submitted_by" class="form-label required">البريد الإلكتروني</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="submitted_by" 
                                   name="submitted_by" 
                                   placeholder="أدخل بريدك الإلكتروني"
                                   required>
                            <div class="form-text">سيتم استخدامه للتواصل معك</div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <?php if ((int)$form['show_department_field'] === 1): ?>
                            <div class="mb-4">
                                <label for="department_id" class="form-label required">الإدارة</label>
                                <select class="form-select" id="department_id" name="department_id" required>
                                    <option value="">-- اختر الإدارة --</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= (int)$dept['id'] ?>">
                                            <?= htmlspecialchars($dept['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>
                        <?php endif; ?>

                        <hr class="my-4">

                        <!-- Dynamic Fields -->
                        <?php foreach ($fieldDefinitions as $field): ?>
                            <?php if ($field['field_type'] === 'repeater'): ?>
                                <?= renderRepeater($field) ?>
                            <?php else: ?>
                                <?= renderField($field) ?>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <!-- Action Buttons -->
                        <div class="d-flex gap-3 mt-4">
                            <button type="button" 
                                    id="previewBtn" 
                                    class="btn btn-secondary flex-fill">
                                <i class="fas fa-eye"></i>
                                معاينة قبل الإرسال
                            </button>
                            <button type="submit" class="btn btn-success flex-fill">
                                <i class="fas fa-paper-plane"></i>
                                إرسال الاستمارة
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye"></i>
                        معاينة الاستمارة
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="previewContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i>
                        إغلاق
                    </button>
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="document.getElementById('submissionForm').dispatchEvent(new Event('submit', {cancelable: true}));">
                        <i class="fas fa-paper-plane"></i>
                        تأكيد وإرسال
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="text-center">
            <div class="spinner-border text-light" role="status"></div>
            <p class="text-light mt-3">جاري الإرسال...</p>
        </div>
    </div>

    <!-- Draft Saved Indicator -->
    <div id="draftIndicator" class="draft-indicator">
        <i class="fas fa-check-circle"></i>
        تم حفظ المسودة
    </div>

    <footer class="text-center mt-5 mb-4">
        <p class="text-muted">
            &copy; <?= date('Y') ?> نظام تقييم الموظفين. جميع الحقوق محفوظة.
        </p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/forms.js"></script>
</body>
</html>
