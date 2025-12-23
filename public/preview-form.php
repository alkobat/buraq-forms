<?php

declare(strict_types=1);

if (!defined('CONFIG_PATH')) {
    require_once __DIR__ . '/../config/constants.php';
}

// تضمين الإعدادات
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Core/Services/FormService.php';
require_once __DIR__ . '/../src/Core/Services/FormFieldService.php';
require_once __DIR__ . '/../src/helpers.php';

// بدء الجلسة
session_start();

// إنشاء خدمات الاستمارات
$formService = new BuraqForms\Core\Services\FormService($pdo);
$formFieldService = new BuraqForms\Core\Services\FormFieldService($pdo);

$error = null;
$success = null;

// جلب الاستمارة عن طريق الـ slug
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    die('لم يتم تحديد الاستمارة');
}

try {
    $form = $formService->getBySlug($slug);

    // التحقق من أن الاستمارة نشطة
    if ($form['status'] !== 'active') {
        die('الاستمارة غير متاحة حالياً');
    }

    // جلب الحقول
    $fieldDefinitions = $formFieldService->getFieldDefinitionsForRendering($form['id']);
} catch (Exception $e) {
    die('الاستمارة غير موجودة');
}

// معالجة إرسال الاستمارة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_form'])) {
    try {
        // التحقق من CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            throw new Exception('رمز الأمان غير صحيح');
        }

        // التحقق من الاستمارة
        $answers = $_POST['answers'] ?? [];
        $validation = ees_validate_submission_data($answers, $fieldDefinitions);

        if (!$validation['valid']) {
            $error = 'يرجى تصحيح الأخطاء أدناه';
        } else {
            // حفظ الإجابة (مؤقتاً - يمكن تطوير FormSubmissionService لاحقاً)
            $success = 'تم إرسال الاستمارة بنجاح! شكراً لك.';

            // إعادة تعيين النموذج
            $_POST = [];
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// إنشاء CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($form['title']) ?></title>
    
    <!-- Bootstrap RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        .form-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .form-body {
            padding: 40px;
        }
        
        .field-group {
            margin-bottom: 25px;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .field-group:hover {
            border-color: #667eea;
            box-shadow: 0 2px 10px rgba(102, 126, 234, 0.1);
        }
        
        .field-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            display: block;
        }
        
        .field-required {
            color: #dc3545;
        }
        
        .helper-text {
            color: #6c757d;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 15px 40px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 18px;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .checkbox-group,
        .radio-group {
            display: grid;
            gap: 10px;
            margin-top: 10px;
        }
        
        .option-item {
            padding: 10px 15px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .option-item:hover {
            border-color: #667eea;
            background-color: rgba(102, 126, 234, 0.05);
        }
        
        .error-message {
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 5px;
        }
        
        .is-invalid {
            border-color: #dc3545 !important;
        }
        
        .form-footer {
            text-align: center;
            padding: 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }
        
        @media (max-width: 768px) {
            .form-body {
                padding: 20px;
            }
            
            .form-header {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <!-- Form Header -->
            <div class="form-header">
                <h1 class="mb-2">
                    <i class="fas fa-file-alt"></i>
                    <?= htmlspecialchars($form['title']) ?>
                </h1>
                <?php if ($form['description']) : ?>
                <p class="mb-0 opacity-75"><?= htmlspecialchars($form['description']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Form Body -->
            <div class="form-body">
                <!-- Alerts -->
                <?php if ($error) : ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <?php if ($success) : ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
                <?php endif; ?>

                <!-- Form -->
                <?php if (!$success) : ?>
                <form method="POST" id="previewForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="submit_form" value="1">
                    
                    <?php foreach ($fieldDefinitions as $field) : ?>
                    <div class="field-group">
                        <?php
                        $fieldName = 'answers[' . $field['field_key'] . ']';
                        $fieldId = 'field_' . $field['id'];
                        $hasError = isset($validation['errors'][$field['field_key']]);
                        $errorClass = $hasError ? 'is-invalid' : '';
                        ?>
                        
                        <label class="field-label" for="<?= $fieldId ?>">
                            <?= htmlspecialchars($field['label']) ?>
                            <?php if ($field['is_required']) : ?>
                            <span class="field-required">*</span>
                            <?php endif; ?>
                        </label>
                        
                        <?php if ($field['helper_text']) : ?>
                        <div class="helper-text">
                            <i class="fas fa-info-circle"></i>
                            <?= htmlspecialchars($field['helper_text']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($field['field_type'] === 'text') : ?>
                            <input type="text" 
                                   class="form-control <?= $errorClass ?>" 
                                   id="<?= $fieldId ?>" 
                                   name="<?= $fieldName ?>"
                                   placeholder="<?= htmlspecialchars($field['placeholder'] ?? '') ?>"
                                   value="<?= htmlspecialchars($_POST['answers'][$field['field_key']] ?? '') ?>">
                        
                        <?php elseif ($field['field_type'] === 'textarea') : ?>
                            <textarea class="form-control <?= $errorClass ?>" 
                                      id="<?= $fieldId ?>" 
                                      name="<?= $fieldName ?>"
                                      placeholder="<?= htmlspecialchars($field['placeholder'] ?? '') ?>"
                                      rows="4"><?= htmlspecialchars($_POST['answers'][$field['field_key']] ?? '') ?></textarea>
                        
                        <?php elseif ($field['field_type'] === 'email') : ?>
                            <input type="email" 
                                   class="form-control <?= $errorClass ?>" 
                                   id="<?= $fieldId ?>" 
                                   name="<?= $fieldName ?>"
                                   placeholder="<?= htmlspecialchars($field['placeholder'] ?? '') ?>"
                                   value="<?= htmlspecialchars($_POST['answers'][$field['field_key']] ?? '') ?>">
                        
                        <?php elseif ($field['field_type'] === 'number') : ?>
                            <input type="number" 
                                   class="form-control <?= $errorClass ?>" 
                                   id="<?= $fieldId ?>" 
                                   name="<?= $fieldName ?>"
                                   placeholder="<?= htmlspecialchars($field['placeholder'] ?? '') ?>"
                                   value="<?= htmlspecialchars($_POST['answers'][$field['field_key']] ?? '') ?>"
                                   <?php if (isset($field['validation_rules']['min'])) :
                                        ?>min="<?= $field['validation_rules']['min'] ?>"<?php
                                   endif; ?>
                                   <?php if (isset($field['validation_rules']['max'])) :
                                        ?>max="<?= $field['validation_rules']['max'] ?>"<?php
                                   endif; ?>>
                        
                        <?php elseif ($field['field_type'] === 'date') : ?>
                            <input type="date" 
                                   class="form-control <?= $errorClass ?>" 
                                   id="<?= $fieldId ?>" 
                                   name="<?= $fieldName ?>"
                                   value="<?= htmlspecialchars($_POST['answers'][$field['field_key']] ?? '') ?>">
                        
                        <?php elseif ($field['field_type'] === 'time') : ?>
                            <input type="time" 
                                   class="form-control <?= $errorClass ?>" 
                                   id="<?= $fieldId ?>" 
                                   name="<?= $fieldName ?>"
                                   value="<?= htmlspecialchars($_POST['answers'][$field['field_key']] ?? '') ?>">
                        
                        <?php elseif ($field['field_type'] === 'select') : ?>
                            <select class="form-select <?= $errorClass ?>" 
                                    id="<?= $fieldId ?>" 
                                    name="<?= $fieldName ?>">
                                <option value="">اختر...</option>
                                <?php foreach ($field['options'] ?? [] as $option) : ?>
                                <option value="<?= htmlspecialchars($option['value']) ?>" 
                                        <?= (($_POST['answers'][$field['field_key']] ?? '') === $option['value']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($option['label']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        
                        <?php elseif ($field['field_type'] === 'radio') : ?>
                            <div class="radio-group">
                                <?php foreach ($field['options'] ?? [] as $option) : ?>
                                <label class="option-item">
                                    <input type="radio" 
                                           class="form-check-input <?= $errorClass ?>" 
                                           name="<?= $fieldName ?>" 
                                           value="<?= htmlspecialchars($option['value']) ?>"
                                           <?= (($_POST['answers'][$field['field_key']] ?? '') === $option['value']) ? 'checked' : '' ?>>
                                    <span class="ms-2"><?= htmlspecialchars($option['label']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        
                        <?php elseif ($field['field_type'] === 'checkbox') : ?>
                            <div class="checkbox-group">
                                <?php
                                $selectedValues = is_array($_POST['answers'][$field['field_key']] ?? null)
                                    ? $_POST['answers'][$field['field_key']]
                                    : [];
                                foreach ($field['options'] ?? [] as $option) :
                                    ?>
                                <label class="option-item">
                                    <input type="checkbox" 
                                           class="form-check-input <?= $errorClass ?>" 
                                           name="<?= $fieldName ?>[]" 
                                           value="<?= htmlspecialchars($option['value']) ?>"
                                           <?= in_array($option['value'], $selectedValues) ? 'checked' : '' ?>>
                                    <span class="ms-2"><?= htmlspecialchars($option['label']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        
                        <?php elseif ($field['field_type'] === 'file') : ?>
                            <input type="file" 
                                   class="form-control <?= $errorClass ?>" 
                                   id="<?= $fieldId ?>" 
                                   name="<?= $fieldName ?>">
                            <small class="form-text text-muted">
                                <i class="fas fa-upload"></i>
                                يرجى اختيار ملف للتحميل
                            </small>
                        
                        <?php endif; ?>
                        
                        <?php if ($hasError) : ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= implode('، ', $validation['errors'][$field['field_key']]) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-submit text-white">
                            <i class="fas fa-paper-plane"></i>
                            إرسال الاستمارة
                        </button>
                    </div>
                </form>
                <?php else : ?>
                <div class="text-center">
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="text-success">تم بنجاح!</h3>
                    <p class="text-muted">
                        شكراً لك على ملء الاستمارة. تم استلام إجابتك بنجاح.
                    </p>
                    <a href="?slug=<?= urlencode($slug) ?>" class="btn btn-primary">
                        <i class="fas fa-redo"></i>
                        ملء استمارة أخرى
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Form Footer -->
            <div class="form-footer">
                <small class="text-muted">
                    <i class="fas fa-clock"></i>
                    تم الإنشاء في: <?= date('Y-m-d H:i', strtotime($form['created_at'])) ?>
                     | 
                    <i class="fas fa-shield-alt"></i>
                    هذه الاستمارة محمية بأعلى معايير الأمان
                </small>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation
        document.getElementById('previewForm').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('يرجى ملء جميع الحقول المطلوبة');
            }
        });
        
        // Real-time validation
        document.querySelectorAll('input[required], textarea[required], select[required]').forEach(field => {
            field.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.classList.remove('is-invalid');
                }
            });
        });
    </script>
</body>
</html>