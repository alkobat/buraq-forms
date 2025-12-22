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
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}

// معالجة preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// بدء الجلسة
session_start();

// إنشاء الخدمات
$formService = new FormService($pdo);
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

function ensureAuthenticated(): array {
    $user = Auth::current_user();

    if (!$user || !Auth::is_logged_in()) {
        errorResponse('يتطلب تسجيل الدخول', 401);
    }

    return $user;
}

function ensurePermission(string ...$permissions): void {
    ensureAuthenticated();

    foreach ($permissions as $permission) {
        if (Auth::has_permission($permission)) {
            return;
        }
    }

    errorResponse('غير مسموح بالوصول', 403);
}

function verifyCSRF(array $data): void {
    $token = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (!Auth::verify_csrf_token($token)) {
        errorResponse('رمز الأمان غير صحيح', 403);
    }
}

function getRequestData(string $method): array {
    if ($method === 'GET') {
        return $_GET;
    }

    if ($method === 'POST') {
        return $_POST;
    }

    $raw = file_get_contents('php://input') ?: '';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false) {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    parse_str($raw, $parsed);
    return is_array($parsed) ? $parsed : [];
}

// التحقق من method والمسار
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '';
$path = trim($path, '/');

try {
    // إنشاء CSRF token جديد
    Auth::generate_csrf_token();

    // Router بسيط
    switch ($method) {
        case 'POST':
            $data = getRequestData('POST');
            verifyCSRF($data);
            ensurePermission('forms.create', 'forms.edit');
            
            if ($path === 'forms') {
                $formData = [
                    'title' => $data['title'] ?? '',
                    'description' => $data['description'] ?? '',
                    'created_by' => $_SESSION['admin_id'] ?? 0,
                    'status' => isset($data['status']) ? 'active' : 'inactive',
                    'allow_multiple_submissions' => !empty($data['allow_multiple_submissions']),
                    'show_department_field' => !empty($data['show_department_field'])
                ];
                
                $form = $formService->create($formData);
                successResponse($form, 'تم إنشاء الاستمارة بنجاح');
                
            } elseif (preg_match('/^forms\/(\d+)$/', $path, $matches)) {
                $formId = (int)$matches[1];
                
                $formData = [
                    'title' => $data['title'] ?? '',
                    'description' => $data['description'] ?? '',
                    'status' => isset($data['status']) ? 'active' : 'inactive',
                    'allow_multiple_submissions' => !empty($data['allow_multiple_submissions']),
                    'show_department_field' => !empty($data['show_department_field'])
                ];
                
                $form = $formService->update($formId, $formData);
                successResponse($form, 'تم تحديث الاستمارة بنجاح');
                
            } elseif (preg_match('/^forms\/(\d+)\/status$/', $path, $matches)) {
                $formId = (int)$matches[1];
                $status = $data['status'] ?? 'inactive';
                
                $formService->setStatus($formId, $status);
                successResponse([], 'تم تغيير حالة الاستمارة بنجاح');
                
            } else {
                errorResponse('المسار غير صحيح', 404);
            }
            break;

        case 'PUT':
            $data = getRequestData('PUT');
            verifyCSRF($data);
            ensurePermission('forms.edit');
            
            if (preg_match('/^forms\/(\d+)$/', $path, $matches)) {
                $formId = (int)$matches[1];
                
                $formData = [
                    'title' => $data['title'] ?? '',
                    'description' => $data['description'] ?? '',
                    'status' => isset($data['status']) ? 'active' : 'inactive',
                    'allow_multiple_submissions' => !empty($data['allow_multiple_submissions']),
                    'show_department_field' => !empty($data['show_department_field'])
                ];
                
                $form = $formService->update($formId, $formData);
                successResponse($form, 'تم تحديث الاستمارة بنجاح');
                
            } else {
                errorResponse('المسار غير صحيح', 404);
            }
            break;

        case 'DELETE':
            $data = getRequestData('DELETE');
            verifyCSRF($data);
            ensurePermission('forms.edit');
            
            if (preg_match('/^forms\/(\d+)$/', $path, $matches)) {
                $formId = (int)$matches[1];
                $formService->delete($formId);
                successResponse([], 'تم حذف الاستمارة بنجاح');
                
            } else {
                errorResponse('المسار غير صحيح', 404);
            }
            break;

        case 'GET':
            ensurePermission('forms.view');

            if ($path === 'forms') {
                $status = $_GET['status'] ?? null;
                $forms = $formService->list($status);
                successResponse($forms);
                
            } elseif (preg_match('/^forms\/(\d+)$/', $path, $matches)) {
                $formId = (int)$matches[1];
                $form = $formService->getById($formId);
                successResponse($form);
                
            } elseif (preg_match('/^forms\/slug\/([^\/]+)$/', $path, $matches)) {
                $slug = $matches[1];
                $form = $formService->getBySlug($slug);
                successResponse($form);
                
            } elseif (preg_match('/^forms\/(\d+)\/fields$/', $path, $matches)) {
                $formId = (int)$matches[1];
                $includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === 'true';
                $fields = $formFieldService->getFieldsForForm($formId, $includeInactive);
                successResponse($fields);
                
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
