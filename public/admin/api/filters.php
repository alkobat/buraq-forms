<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once __DIR__ . '/../../../vendor/autoload.php';

use EmployeeEvaluationSystem\Core\Database;
use EmployeeEvaluationSystem\Core\Services\FilterService;
use EmployeeEvaluationSystem\Core\Services\PermissionService;
use EmployeeEvaluationSystem\Core\Services\AuditService;

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$database = Database::getConnection();
$filterService = new FilterService($database);
$permissionService = new PermissionService($database);
$auditService = new AuditService($database);

$adminId = $_SESSION['admin_id'] ?? 0;

try {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'save_filter':
            // حفظ تصفية جديدة
            $filterData = json_decode(file_get_contents('php://input'), true);
            
            if (!$permissionService->hasPermission($adminId, 'submissions.view')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية']);
                break;
            }
            
            $filterData['admin_id'] = $adminId;
            $filterId = $filterService->saveFilter($filterData);
            
            if ($filterId) {
                $auditService->logCreate($adminId, 'saved_filter', $filterId, $filterData);
                echo json_encode(['success' => true, 'filter_id' => $filterId]);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل في حفظ التصفية']);
            }
            break;
            
        case 'get_filters':
            // جلب التصفيات المحفوظة
            if (!$permissionService->hasPermission($adminId, 'submissions.view')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية']);
                break;
            }
            
            $filterType = $_GET['type'] ?? 'submissions';
            $filters = $filterService->getSavedFilters($adminId, $filterType);
            
            echo json_encode(['success' => true, 'data' => $filters]);
            break;
            
        case 'apply_filter':
            // تطبيق تصفية محفوظة
            $filterId = (int) ($_GET['filter_id'] ?? 0);
            $baseQuery = $_POST['base_query'] ?? 'SELECT * FROM form_submissions fs';
            
            $filter = $filterService->getSavedFilter($filterId);
            if (!$filter) {
                echo json_encode(['success' => false, 'message' => 'التصفية غير موجودة']);
                break;
            }
            
            if (!$permissionService->hasPermission($adminId, 'submissions.view')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية']);
                break;
            }
            
            $query = $filterService->applySavedFilter($filter, $baseQuery);
            
            $auditService->logActivity($adminId, 'apply_filter', 'saved_filter', $filterId, null, [
                'filter_id' => $filterId,
                'query' => $query
            ]);
            
            echo json_encode(['success' => true, 'query' => $query]);
            break;
            
        case 'update_filter':
            // تحديث تصفية محفوظة
            $filterId = (int) ($_POST['filter_id'] ?? 0);
            $filterData = json_decode(file_get_contents('php://input'), true);
            
            $result = $filterService->updateSavedFilter($filterId, $filterData);
            
            if ($result) {
                $auditService->logUpdate($adminId, 'saved_filter', $filterId, [], $filterData);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل في تحديث التصفية']);
            }
            break;
            
        case 'delete_filter':
            // حذف تصفية محفوظة
            $filterId = (int) ($_POST['filter_id'] ?? 0);
            
            $result = $filterService->deleteSavedFilter($filterId, $adminId);
            
            if ($result) {
                $auditService->logDelete($adminId, 'saved_filter', $filterId, []);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل في حذف التصفية']);
            }
            break;
            
        case 'set_default_filter':
            // تعيين تصفية افتراضية
            $filterId = (int) ($_POST['filter_id'] ?? 0);
            $filterType = $_POST['filter_type'] ?? 'submissions';
            
            $result = $filterService->setDefaultFilter($adminId, $filterType, $filterId);
            
            if ($result) {
                $auditService->logActivity($adminId, 'set_default_filter', 'saved_filter', $filterId, null, [
                    'filter_type' => $filterType
                ]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل في تعيين التصفية الافتراضية']);
            }
            break;
            
        case 'get_default_filter':
            // جلب التصفية الافتراضية
            $filterType = $_GET['filter_type'] ?? 'submissions';
            $filter = $filterService->getDefaultFilter($adminId, $filterType);
            
            echo json_encode(['success' => true, 'data' => $filter]);
            break;
            
        case 'export_filter':
            // تصدير تصفية
            $filterId = (int) ($_GET['filter_id'] ?? 0);
            
            $exportData = $filterService->exportFilter($filterId);
            if ($exportData) {
                $auditService->logExport($adminId, 'saved_filter', $filterId, 'json');
                
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="filter_' . $filterId . '.json"');
                echo $exportData;
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل في تصدير التصفية']);
            }
            break;
            
        case 'import_filter':
            // استيراد تصفية
            if (!$permissionService->hasPermission($adminId, 'submissions.view')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية']);
                break;
            }
            
            $filterJson = file_get_contents('php://input');
            $filterData = json_decode($filterJson, true);
            
            if (!$filterData) {
                echo json_encode(['success' => false, 'message' => 'ملف التصفية غير صحيح']);
                break;
            }
            
            $filterId = $filterService->importFilter($filterData, $adminId);
            
            if ($filterId) {
                $auditService->logActivity($adminId, 'import_filter', 'saved_filter', $filterId, null, [
                    'imported_data' => $filterData
                ]);
                echo json_encode(['success' => true, 'filter_id' => $filterId]);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل في استيراد التصفية']);
            }
            break;
            
        case 'get_filter_stats':
            // إحصائيات التصفيات
            if (!$permissionService->hasPermission($adminId, 'submissions.view')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية']);
                break;
            }
            
            $stats = $filterService->getFilterStats($adminId);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'quick_filters':
            // إنشاء تصفية سريعة
            $criteria = [
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'form_id' => $_GET['form_id'] ?? null,
                'department_id' => $_GET['department_id'] ?? null,
                'status' => $_GET['status'] ?? null,
                'search' => $_GET['search'] ?? null
            ];
            
            // إزالة القيم الفارغة
            $criteria = array_filter($criteria, fn($value) => !empty($value));
            
            echo json_encode(['success' => true, 'data' => $criteria]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'إجراء غير صحيح']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'خطأ في الخادم: ' . $e->getMessage()
    ]);
}
?>