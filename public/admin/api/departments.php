<?php

declare(strict_types=1);

// تضمين الإعدادات
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../src/Core/Services/DepartmentService.php';

use BuraqForms\Core\Services\DepartmentService;

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

// إنشاء خدمة الإدارات
$departmentService = new DepartmentService($pdo);

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
            
            if ($path === 'departments') {
                $data = [
                    'name' => $_POST['name'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'manager_id' => !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null,
                    'is_active' => isset($_POST['is_active'])
                ];
                
                $department = $departmentService->create($data);
                successResponse($department, 'تم إنشاء الإدارة بنجاح');
                
            } elseif (preg_match('/^departments\/(\d+)$/', $path, $matches)) {
                $departmentId = (int)$matches[1];
                
                $data = [
                    'name' => $_POST['name'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'manager_id' => !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null,
                    'is_active' => isset($_POST['is_active'])
                ];
                
                $department = $departmentService->update($departmentId, $data);
                successResponse($department, 'تم تحديث الإدارة بنجاح');
                
            } elseif (preg_match('/^departments\/(\d+)\/status$/', $path, $matches)) {
                $departmentId = (int)$matches[1];
                $isActive = (bool)($_POST['is_active'] ?? false);
                
                $departmentService->setStatus($departmentId, $isActive);
                $message = $isActive ? 'تم تفعيل الإدارة بنجاح' : 'تم تعطيل الإدارة بنجاح';
                successResponse([], $message);
                
            } else {
                errorResponse('المسار غير صحيح', 404);
            }
            break;

        case 'PUT':
            verifyCSRF();
            
            if (preg_match('/^departments\/(\d+)$/', $path, $matches)) {
                $departmentId = (int)$matches[1];
                
                $data = [
                    'name' => $_POST['name'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'manager_id' => !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null,
                    'is_active' => isset($_POST['is_active'])
                ];
                
                $department = $departmentService->update($departmentId, $data);
                successResponse($department, 'تم تحديث الإدارة بنجاح');
                
            } else {
                errorResponse('المسار غير صحيح', 404);
            }
            break;

        case 'DELETE':
            verifyCSRF();
            
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
                $isActive = null;
                if (isset($_GET['is_active'])) {
                    $isActive = $_GET['is_active'] === 'true';
                }
                
                $departments = $departmentService->list($isActive);
                successResponse($departments);
                
            } elseif ($path === 'departments/active') {
                $departments = $departmentService->getActiveDepartments();
                successResponse($departments);
                
            } elseif ($path === 'departments/managers') {
                $managers = $departmentService->getManagersList();
                successResponse($managers);
                
            } elseif (preg_match('/^departments\/(\d+)$/', $path, $matches)) {
                $departmentId = (int)$matches[1];
                $department = $departmentService->getById($departmentId);
                successResponse($department);
                
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