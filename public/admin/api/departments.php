<?php

declare(strict_types=1);

// تضمين الإعدادات
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../src/Core/Auth.php';
require_once __DIR__ . '/../../../src/Core/Services/DepartmentService.php';

use BuraqForms\Core\Services\DepartmentService;
use BuraqForms\Core\Auth;

// إعداد headers للـ JSON API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
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

// التحقق من الصلاحيات (مؤقتاً)
$isAdmin = true; // يجب التحقق من تسجيل الدخول في التطبيق الحقيقي

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مسموح بالوصول']);
    exit;
}

// إنشاء خدمة الإدارات
$departmentService = new DepartmentService($pdo);

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
        ensurePermission('departments.manage', 'departments.edit');

        if ($path === 'departments') {
            $departmentData = [
                'name' => $data['name'] ?? '',
                'description' => $data['description'] ?? '',
                'manager_id' => !empty($data['manager_id']) ? (int)$data['manager_id'] : null,
                'is_active' => !empty($data['is_active']),
            ];

            $department = $departmentService->create($departmentData);
            successResponse($department, 'تم إنشاء الإدارة بنجاح');

        } elseif (preg_match('/^departments\/(\d+)\/status$/', $path, $matches)) {
            $departmentId = (int)$matches[1];
            $isActive = (bool)($data['is_active'] ?? false);

            $departmentService->setStatus($departmentId, $isActive);
            $message = $isActive ? 'تم تفعيل الإدارة بنجاح' : 'تم تعطيل الإدارة بنجاح';
            successResponse([], $message);

        } else {
            errorResponse('المسار غير صحيح', 404);
        }
        break;

    case 'PUT':
        verifyCSRF();
        $data = getRequestData('PUT');
        ensurePermission('departments.manage', 'departments.edit');

        if (preg_match('/^departments\/(\d+)$/', $path, $matches)) {
            $departmentId = (int)$matches[1];

            $departmentData = [
                'name' => $data['name'] ?? '',
                'description' => $data['description'] ?? '',
                'manager_id' => !empty($data['manager_id']) ? (int)$data['manager_id'] : null,
                'is_active' => !empty($data['is_active']),
            ];

            $department = $departmentService->update($departmentId, $departmentData);
            successResponse($department, 'تم تحديث الإدارة بنجاح');

        } else {
            errorResponse('المسار غير صحيح', 404);
        }
        break;

    case 'DELETE':
        verifyCSRF();
        ensurePermission('departments.manage', 'departments.delete');

        if (preg_match('/^departments\/(\d+)$/', $path, $matches)) {
            $departmentId = (int)$matches[1];

            $departmentService->delete($departmentId);
            successResponse([], 'تم حذف الإدارة بنجاح');

        } else {
            errorResponse('المسار غير صحيح', 404);
        }
        break;

    case 'GET':
        if ($path === 'departments') {
            $departments = $departmentService->getAll();
            successResponse($departments);

        } elseif (preg_match('/^departments\/(\d+)$/', $path, $matches)) {
            $departmentId = (int)$matches[1];
            $department = $departmentService->getById($departmentId);

            if ($department) {
                successResponse($department);
            } else {
                errorResponse('الإدارة غير موجودة', 404);
            }

        } else {
            errorResponse('المسار غير صحيح', 404);
        }
        break;

    default:
        errorResponse('الطريقة غير مدعومة', 405);
}

// إضافة endpoint خاص لاختبار وظيفة CSRF
if ($path === 'csrf-token') {
    $token = Auth::generate_csrf_token();
    successResponse(['csrf_token' => $token], 'تم إنشاء رمز CSRF');
}