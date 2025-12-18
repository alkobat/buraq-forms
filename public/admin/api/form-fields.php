<?php

declare(strict_types=1);

// تضمين الإعدادات
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../src/Core/Services/FormFieldService.php';

use BuraqForms\Core\Services\FormFieldService;

// إعداد headers للـ JSON API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// معالجة preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// بدء الجلسة
session_start();

// التحقق من الصلاحيات (مؤقتاً)
$isAdmin = true; // يجب التحقق من تسجيل الدخول في التطبيق الحقيقي

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مسموح بالوصول']);
    exit;
}

// إنشاء خدمة الحقول
$formFieldService = new FormFieldService($pdo);

// دالة للإجابة بنجاح
function successResponse($data = [], $message = 'تمت العملية بنجاح') {
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// دالة للإجابة مع خطأ
function errorResponse($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

// دالة للتحقق من CSRF token
function verifyCSRF() {
    $token = $_SERVER['REQUEST_METHOD'] === 'POST' ? 
        ($_POST['csrf_token'] ?? '') : 
        ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    
    if (!$token || $token !== ($_SESSION['csrf_token'] ?? '')) {
        errorResponse('رمز الأمان غير صحيح', 403);
    }
}

// التحقق من method والمسار
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '';
$path = trim($path, '/');

try {
    // إنشاء CSRF token جديد
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Router بسيط
    switch ($method) {
        case 'POST':
            verifyCSRF();
            
            if (preg_match('/^fields\/(\d+)$/', $path, $matches)) {
                $formId = (int)$matches[1];
                
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
                    $data['source_type'] = $_POST['source_type'] ?? 'static';
                    
                    if ($data['source_type'] !== 'departments') {
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
                }

                $field = $formFieldService->addField($formId, $data);
                successResponse($field, 'تم إضافة الحقل بنجاح');
                
            } elseif (preg_match('/^fields\/(\d+)\/reorder$/', $path, $matches)) {
                $formId = (int)$matches[1];
                $fieldIds = array_map('intval', $_POST['field_ids'] ?? []);
                
                if (empty($fieldIds)) {
                    errorResponse('معرفات الحقول مطلوبة');
                }
                
                $formFieldService->reorderFields($formId, $fieldIds);
                successResponse([], 'تم إعادة ترتيب الحقول بنجاح');
                
            } else {
                errorResponse('المسار غير صحيح', 404);
            }
            break;

        case 'PUT':
            verifyCSRF();
            
            if (preg_match('/^fields\/(\d+)$/', $path, $matches)) {
                $fieldId = (int)$matches[1];
                
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
                $fieldType = $_POST['field_type'] ?? 'text';
                if (in_array($fieldType, ['select', 'radio', 'checkbox'])) {
                    $data['source_type'] = $_POST['source_type'] ?? 'static';
                    
                    if ($data['source_type'] !== 'departments') {
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
                }

                $field = $formFieldService->updateField($fieldId, $data);
                successResponse($field, 'تم تحديث الحقل بنجاح');
                
            } else {
                errorResponse('المسار غير صحيح', 404);
            }
            break;

        case 'DELETE':
            verifyCSRF();
            
            if (preg_match('/^fields\/(\d+)$/', $path, $matches)) {
                $fieldId = (int)$matches[1];
                $formFieldService->deleteField($fieldId);
                successResponse([], 'تم حذف الحقل بنجاح');
                
            } else {
                errorResponse('المسار غير صحيح', 404);
            }
            break;

        case 'GET':
            if (preg_match('/^fields\/(\d+)$/', $path, $matches)) {
                $formId = (int)$matches[1];
                $fields = $formFieldService->getFieldsForForm($formId, true);
                successResponse($fields);
                
            } elseif (preg_match('/^fields\/(\d+)\/(\d+)$/', $path, $matches)) {
                $fieldId = (int)$matches[2];
                $field = $formFieldService->getFieldById($fieldId);
                successResponse($field);
                
            } else {
                errorResponse('المسار غير صحيح', 404);
            }
            break;

        default:
            errorResponse('Method غير مدعوم', 405);
    }

} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}