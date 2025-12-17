<?php

declare(strict_types=1);

// تضمين الإعدادات
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../src/Core/Services/FormService.php';
require_once __DIR__ . '/../../../src/Core/Services/FormFieldService.php';

use EmployeeEvaluationSystem\Core\Services\FormService;
use EmployeeEvaluationSystem\Core\Services\FormFieldService;

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
            
            if ($path === 'forms') {
                $data = [
                    'title' => $_POST['title'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'created_by' => 1, // مؤقتاً
                    'status' => isset($_POST['status']) ? 'active' : 'inactive',
                    'allow_multiple_submissions' => isset($_POST['allow_multiple_submissions']),
                    'show_department_field' => isset($_POST['show_department_field'])
                ];
                
                $form = $formService->create($data);
                successResponse($form, 'تم إنشاء الاستمارة بنجاح');
                
            } elseif (preg_match('/^forms\/(\d+)$/', $path, $matches)) {
                $formId = (int)$matches[1];
                
                $data = [
                    'title' => $_POST['title'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'status' => isset($_POST['status']) ? 'active' : 'inactive',
                    'allow_multiple_submissions' => isset($_POST['allow_multiple_submissions']),
                    'show_department_field' => isset($_POST['show_department_field'])
                ];
                
                $form = $formService->update($formId, $data);
                successResponse($form, 'تم تحديث الاستمارة بنجاح');
                
            } elseif (preg_match('/^forms\/(\d+)\/status$/', $path, $matches)) {
                $formId = (int)$matches[1];
                $status = $_POST['status'] ?? 'inactive';
                
                $formService->setStatus($formId, $status);
                successResponse([], 'تم تغيير حالة الاستمارة بنجاح');
                
            } else {
                errorResponse('المسار غير صحيح', 404);
            }
            break;

        case 'PUT':
            verifyCSRF();
            
            if (preg_match('/^forms\/(\d+)$/', $path, $matches)) {
                $formId = (int)$matches[1];
                
                $data = [
                    'title' => $_POST['title'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'status' => isset($_POST['status']) ? 'active' : 'inactive',
                    'allow_multiple_submissions' => isset($_POST['allow_multiple_submissions']),
                    'show_department_field' => isset($_POST['show_department_field'])
                ];
                
                $form = $formService->update($formId, $data);
                successResponse($form, 'تم تحديث الاستمارة بنجاح');
                
            } else {
                errorResponse('المسار غير صحيح', 404);
            }
            break;

        case 'DELETE':
            verifyCSRF();
            
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