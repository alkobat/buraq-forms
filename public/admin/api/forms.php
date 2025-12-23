<?php

declare(strict_types=1);

// تضمين الإعدادات
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../src/Core/Auth.php';
require_once __DIR__ . '/../../../src/Core/Services/FormService.php';
require_once __DIR__ . '/../../../src/Core/Services/FormFieldService.php';

use BuraqForms\Core\Services\FormService;
use BuraqForms\Core\Services\FormFieldService;
use BuraqForms\Core\Auth;

// إعداد headers للـ JSON API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');

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

// إنشاء الخدمات
$formService = new FormService($pdo);
$formFieldService = new FormFieldService($pdo);

// دالة للإجابة بنجاح
function successResponse($data = [], string $message = 'تم بنجاح'): void {
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// دالة للإجابة بالخطأ
function errorResponse(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// دالة للحصول على البيانات
function getRequestData(string $method): array {
    switch ($method) {
        case 'POST':
            return $_POST;
        case 'PUT':
        case 'PATCH':
            parse_str(file_get_contents('php://input'), $input);
            return $input ?: [];
        default:
            return $_REQUEST;
    }
}

// دالة للتحقق من CSRF
function verifyCSRF(array $data = []): void {
    $token = $data['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    if (!$token || !Auth::verify_csrf_token($token)) {
        errorResponse('رمز CSRF غير صحيح', 419);
    }
}

// دالة للتحقق من الصلاحيات
function ensurePermission(string $permission, string $fallback = ''): void {
    // TODO: تطبيق نظام الصلاحيات الحقيقي
    // هذا مؤقت للاختبار فقط
}

// الحصول على الطريقة والمسار
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// Router بسيط
switch ($method) {
    case 'POST':
        verifyCSRF();
        $data = getRequestData('POST');
        ensurePermission('forms.manage', 'forms.create');

        if ($path === 'forms') {
            $formData = [
                'title' => $data['title'] ?? '',
                'description' => $data['description'] ?? '',
                'created_by' => $_SESSION['admin_id'] ?? 0,
                'status' => isset($data['status']) ? 'active' : 'inactive',
                'allow_multiple_submissions' => !empty($data['allow_multiple_submissions']),
                'show_department_field' => !empty($data['show_department_field']),
            ];

            $form = $formService->create($formData);
            successResponse($form, 'تم إنشاء الاستمارة بنجاح');

        } elseif (preg_match('/^forms\/(\d+)\/fields$/', $path, $matches)) {
            $formId = (int)$matches[1];

            $fieldData = [
                'form_id' => $formId,
                'field_name' => $data['field_name'] ?? '',
                'field_type' => $data['field_type'] ?? 'text',
                'field_label' => $data['field_label'] ?? '',
                'is_required' => !empty($data['is_required']),
                'field_options' => $data['field_options'] ?? '',
                'field_order' => (int)($data['field_order'] ?? 0),
            ];

            $field = $formFieldService->create($fieldData);
            successResponse($field, 'تم إضافة الحقل بنجاح');

        } else {
            errorResponse('المسار غير صحيح', 404);
        }
        break;

    case 'PUT':
        verifyCSRF();
        $data = getRequestData('PUT');
        ensurePermission('forms.manage', 'forms.edit');

        if (preg_match('/^forms\/(\d+)$/', $path, $matches)) {
            $formId = (int)$matches[1];

            $formData = [
                'title' => $data['title'] ?? '',
                'description' => $data['description'] ?? '',
                'status' => isset($data['status']) ? 'active' : 'inactive',
                'allow_multiple_submissions' => !empty($data['allow_multiple_submissions']),
                'show_department_field' => !empty($data['show_department_field']),
            ];

            $form = $formService->update($formId, $formData);
            successResponse($form, 'تم تحديث الاستمارة بنجاح');

        } else {
            errorResponse('المسار غير صحيح', 404);
        }
        break;

    case 'DELETE':
        verifyCSRF();
        ensurePermission('forms.manage', 'forms.delete');

        if (preg_match('/^forms\/(\d+)$/', $path, $matches)) {
            $formId = (int)$matches[1];

            $formService->delete($formId);
            successResponse([], 'تم حذف الاستمارة بنجاح');

        } else {
            errorResponse('المسار غير صحيح', 404);
        }
        break;

    case 'GET':
        if ($path === 'forms') {
            $forms = $formService->getAll();
            successResponse($forms);

        } elseif (preg_match('/^forms\/(\d+)$/', $path, $matches)) {
            $formId = (int)$matches[1];
            $form = $formService->getById($formId);

            if ($form) {
                successResponse($form);
            } else {
                errorResponse('الاستمارة غير موجودة', 404);
            }

        } elseif (preg_match('/^forms\/(\d+)\/fields$/', $path, $matches)) {
            $formId = (int)$matches[1];
            $fields = $formFieldService->getByFormId($formId);
            successResponse($fields);

        } else {
            errorResponse('المسار غير صحيح', 404);
        }
        break;

    default:
        errorResponse('الطريقة غير مدعومة', 405);
}