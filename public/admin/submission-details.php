<?php

declare(strict_types=1);

// تضمين الإعدادات
require_once __DIR__ . '/../../config/database.php';
require_once SRC_PATH . '/Core/Services/FormService.php';
require_once SRC_PATH . '/Core/Services/FormFieldService.php';
require_once SRC_PATH . '/Core/Services/FormSubmissionService.php';

// بدء الجلسة
session_start();

// التحقق من الصلاحيات
$isAdmin = true;

if (!$isAdmin) {
    http_response_code(403);
    die('غير مسموح بالوصول');
}

// إنشاء الخدمات
$formService = new BuraqForms\Core\Services\FormService($pdo);
$formFieldService = new BuraqForms\Core\Services\FormFieldService($pdo);
$submissionService = new BuraqForms\Core\Services\FormSubmissionService($pdo);

// جلب معرف الإجابة
$submissionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($submissionId <= 0) {
    header('Location: form-submissions.php');
    exit;
}

try {
    // جلب بيانات الإجابة
    $submission = $submissionService->getSubmissionById($submissionId);
    
    // جلب بيانات الاستمارة
    $form = $formService->getById((int)$submission['form_id']);
    
    // جلب حقول الاستمارة
    $fields = $formFieldService->getFieldsForForm((int)$submission['form_id'], true);
    
    // جلب بيانات الإدارة إذا وجدت
    $department = null;
    if ($submission['department_id']) {
        $stmt = $pdo->prepare('SELECT * FROM departments WHERE id = :id');
        $stmt->execute(['id' => $submission['department_id']]);
        $department = $stmt->fetch();
    }
    
    // تنظيم الإجابات حسب field_id
    $answersMap = [];
    foreach ($submission['answers'] as $answer) {
        $fieldId = (int)$answer['field_id'];
        $repeatIndex = (int)$answer['repeat_index'];
        
        if (!isset($answersMap[$fieldId])) {
            $answersMap[$fieldId] = [];
        }
        
        if ($repeatIndex > 0) {
            if (!isset($answersMap[$fieldId][$repeatIndex])) {
                $answersMap[$fieldId][$repeatIndex] = [];
            }
            $answersMap[$fieldId][$repeatIndex][] = $answer;
        } else {
            $answersMap[$fieldId][] = $answer;
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

/**
 * دالة لعرض قيمة الإجابة بشكل مقروء
 */
function formatAnswer($answer, $fieldType) {
    if ($answer['file_path']) {
        return [
            'type' => 'file',
            'name' => $answer['file_name'],
            'path' => $answer['file_path'],
            'size' => $answer['file_size']
        ];
    }
    
    $value = $answer['answer'];
    
    if ($value === null || $value === '') {
        return '-';
    }
    
    // محاولة فك JSON للحقول التي تحتوي على قيم متعددة
    if (in_array($fieldType, ['checkbox', 'select']) && str_starts_with($value, '[')) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return implode(', ', $decoded);
        }
    }
    
    return $value;
}

/**
 * دالة لعرض حجم الملف بشكل مقروء
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الإجابة - <?= htmlspecialchars($submission['reference_code'] ?? '') ?></title>
    
    <!-- Bootstrap RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    
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
        .details-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .info-row {
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }
        .info-value {
            color: #212529;
        }
        .field-group {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .repeater-group {
            background: white;
            border: 2px solid #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .file-preview {
            display: inline-block;
            padding: 10px 15px;
            background: #e7f3ff;
            border-radius: 5px;
            margin: 5px 0;
        }
        .file-preview i {
            margin-left: 5px;
        }
        .badge-pending { background-color: #ffc107; }
        .badge-completed { background-color: #28a745; }
        .badge-archived { background-color: #6c757d; }
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
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                    <a href="form-submissions.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i> العودة للقائمة
                    </a>
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>
                            <i class="fas fa-file-alt"></i>
                            تفاصيل الإجابة
                        </h2>
                        <div>
                            <a href="form-submissions.php" class="btn btn-secondary me-2">
                                <i class="fas fa-arrow-right"></i> العودة
                            </a>
                            <button onclick="window.print()" class="btn btn-primary">
                                <i class="fas fa-print"></i> طباعة
                            </button>
                        </div>
                    </div>

                    <!-- معلومات الإجابة الأساسية -->
                    <div class="details-card">
                        <h5 class="mb-3">
                            <i class="fas fa-info-circle"></i>
                            معلومات الإرسال
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-row">
                                    <div class="info-label">رقم المرجع</div>
                                    <div class="info-value">
                                        <code style="font-size: 1.1em;"><?= htmlspecialchars($submission['reference_code']) ?></code>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">الاستمارة</div>
                                    <div class="info-value"><?= htmlspecialchars($form['title']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">المرسل</div>
                                    <div class="info-value"><?= htmlspecialchars($submission['submitted_by']) ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-row">
                                    <div class="info-label">الإدارة</div>
                                    <div class="info-value">
                                        <?= $department ? htmlspecialchars($department['name']) : '-' ?>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">الحالة</div>
                                    <div class="info-value">
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
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">تاريخ ووقت الإرسال</div>
                                    <div class="info-value">
                                        <?= date('Y-m-d H:i:s', strtotime($submission['submitted_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if ($submission['ip_address']): ?>
                            <div class="info-row mt-3">
                                <div class="info-label">عنوان IP</div>
                                <div class="info-value">
                                    <code><?= htmlspecialchars($submission['ip_address']) ?></code>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- الإجابات التفصيلية -->
                    <div class="details-card">
                        <h5 class="mb-4">
                            <i class="fas fa-list"></i>
                            الإجابات التفصيلية
                        </h5>

                        <?php foreach ($fields as $field): ?>
                            <?php
                            $fieldId = (int)$field['id'];
                            $fieldType = $field['field_type'];
                            $isRepeater = $fieldType === 'repeater';
                            
                            // تخطي الحقول الفرعية (children of repeater)
                            if ($field['parent_field_id'] !== null) {
                                continue;
                            }
                            ?>

                            <div class="field-group">
                                <h6 class="mb-3">
                                    <?= htmlspecialchars($field['label']) ?>
                                    <?php if ($field['is_required']): ?>
                                        <span class="text-danger">*</span>
                                    <?php endif; ?>
                                </h6>

                                <?php if ($isRepeater): ?>
                                    <?php
                                    // جلب الحقول الفرعية للـ repeater
                                    $childFields = array_filter($fields, function($f) use ($fieldId) {
                                        return (int)$f['parent_field_id'] === $fieldId;
                                    });
                                    
                                    // جمع جميع repeat_index الموجودة
                                    $repeatIndices = [];
                                    foreach ($childFields as $childField) {
                                        $childFieldId = (int)$childField['id'];
                                        if (isset($answersMap[$childFieldId])) {
                                            foreach (array_keys($answersMap[$childFieldId]) as $idx) {
                                                if ($idx > 0) {
                                                    $repeatIndices[$idx] = true;
                                                }
                                            }
                                        }
                                    }
                                    ksort($repeatIndices);
                                    ?>

                                    <?php if (empty($repeatIndices)): ?>
                                        <p class="text-muted">لا توجد إدخالات</p>
                                    <?php else: ?>
                                        <?php foreach (array_keys($repeatIndices) as $repeatIndex): ?>
                                            <div class="repeater-group">
                                                <h6 class="text-primary mb-3">
                                                    <i class="fas fa-layer-group"></i>
                                                    المجموعة #<?= $repeatIndex ?>
                                                </h6>
                                                <?php foreach ($childFields as $childField): ?>
                                                    <?php
                                                    $childFieldId = (int)$childField['id'];
                                                    $childAnswers = $answersMap[$childFieldId][$repeatIndex] ?? [];
                                                    ?>
                                                    <div class="mb-3">
                                                        <strong><?= htmlspecialchars($childField['label']) ?>:</strong>
                                                        <div class="mt-1">
                                                            <?php if (empty($childAnswers)): ?>
                                                                <span class="text-muted">-</span>
                                                            <?php else: ?>
                                                                <?php foreach ($childAnswers as $childAnswer): ?>
                                                                    <?php
                                                                    $formattedValue = formatAnswer($childAnswer, $childField['field_type']);
                                                                    ?>
                                                                    <?php if (is_array($formattedValue) && $formattedValue['type'] === 'file'): ?>
                                                                        <div class="file-preview">
                                                                            <i class="fas fa-file"></i>
                                                                            <a href="download-form-file.php?id=<?= $childAnswer['id'] ?>" target="_blank">
                                                                                <?= htmlspecialchars($formattedValue['name']) ?>
                                                                            </a>
                                                                            <small class="text-muted">
                                                                                (<?= formatFileSize($formattedValue['size']) ?>)
                                                                            </small>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <?= htmlspecialchars($formattedValue) ?>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <?php
                                    $answers = $answersMap[$fieldId] ?? [];
                                    ?>
                                    <?php if (empty($answers)): ?>
                                        <p class="text-muted">لم يتم الإجابة</p>
                                    <?php else: ?>
                                        <?php foreach ($answers as $answer): ?>
                                            <?php
                                            $formattedValue = formatAnswer($answer, $fieldType);
                                            ?>
                                            <?php if (is_array($formattedValue) && $formattedValue['type'] === 'file'): ?>
                                                <div class="file-preview">
                                                    <i class="fas fa-file"></i>
                                                    <a href="download-form-file.php?id=<?= $answer['id'] ?>" target="_blank">
                                                        <?= htmlspecialchars($formattedValue['name']) ?>
                                                    </a>
                                                    <small class="text-muted">
                                                        (<?= formatFileSize($formattedValue['size']) ?>)
                                                    </small>
                                                </div>
                                            <?php else: ?>
                                                <div><?= nl2br(htmlspecialchars($formattedValue)) ?></div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
