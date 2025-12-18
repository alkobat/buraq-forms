<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once __DIR__ . '/../../../vendor/autoload.php';

use EmployeeEvaluationSystem\Core\Database;
use EmployeeEvaluationSystem\Core\Services\DraftService;
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
$draftService = new DraftService($database);
$permissionService = new PermissionService($database);
$auditService = new AuditService($database);

$adminId = $_SESSION['admin_id'] ?? 0;

try {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'save_draft':
            // حفظ مسودة الاستمارة
            $formData = json_decode(file_get_contents('php://input'), true);
            
            $formId = (int) ($formData['form_id'] ?? 0);
            $sessionId = session_id();
            $userIp = $_SERVER['REMOTE_ADDR'] ?? '';
            $expiresAt = $formData['expires_at'] ?? null;
            
            // التحقق من الصلاحية للوصول للاستمارة
            if (!$permissionService->canAccessForm($adminId, $formId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية للوصول لهذه الاستمارة']);
                break;
            }
            
            $result = $draftService->saveDraft($formId, $sessionId, $userIp, $formData['form_data'], $expiresAt);
            
            if ($result) {
                $auditService->logActivity($adminId, 'save_draft', 'form', $formId, null, [
                    'form_id' => $formId,
                    'session_id' => $sessionId,
                    'expires_at' => $expiresAt
                ]);
                echo json_encode(['success' => true, 'message' => 'تم حفظ المسودة بنجاح']);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل في حفظ المسودة']);
            }
            break;
            
        case 'get_draft':
            // استرجاع مسودة الاستمارة
            $formId = (int) ($_GET['form_id'] ?? 0);
            $sessionId = session_id();
            
            if (!$permissionService->canAccessForm($adminId, $formId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية للوصول لهذه الاستمارة']);
                break;
            }
            
            $draft = $draftService->getDraft($formId, $sessionId);
            
            if ($draft) {
                $auditService->logActivity($adminId, 'get_draft', 'form', $formId, null, ['draft_id' => $draft['id']]);
                echo json_encode([
                    'success' => true, 
                    'data' => [
                        'id' => $draft['id'],
                        'form_data' => $draft['form_data'],
                        'updated_at' => $draft['updated_at'],
                        'expires_at' => $draft['expires_at']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'لا توجد مسودة محفوظة']);
            }
            break;
            
        case 'delete_draft':
            // حذف المسودة
            $formId = (int) ($_POST['form_id'] ?? 0);
            $sessionId = session_id();
            
            if (!$permissionService->canAccessForm($adminId, $formId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية للوصول لهذه الاستمارة']);
                break;
            }
            
            $result = $draftService->deleteDraft($formId, $sessionId);
            
            if ($result) {
                $auditService->logActivity($adminId, 'delete_draft', 'form', $formId, null, [
                    'form_id' => $formId,
                    'session_id' => $sessionId
                ]);
                echo json_encode(['success' => true, 'message' => 'تم حذف المسودة بنجاح']);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل في حذف المسودة']);
            }
            break;
            
        case 'clean_expired':
            // تنظيف المسودات المنتهية الصلاحية (يتطلب صلاحية عالية)
            if (!$permissionService->hasPermission($adminId, 'system.settings')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية']);
                break;
            }
            
            $count = $draftService->cleanExpiredDrafts();
            $auditService->logActivity($adminId, 'clean_expired_drafts', 'draft', 0, null, ['deleted_count' => $count]);
            
            echo json_encode(['success' => true, 'deleted_count' => $count]);
            break;
            
        case 'get_stats':
            // إحصائيات المسودات
            if (!$permissionService->hasPermission($adminId, 'forms.view')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية']);
                break;
            }
            
            $stats = $draftService->getDraftStats();
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'has_unsaved_changes':
            // التحقق من وجود تغييرات غير محفوظة
            $formId = (int) ($_GET['form_id'] ?? 0);
            $sessionId = session_id();
            
            $draft = $draftService->getDraft($formId, $sessionId);
            echo json_encode([
                'success' => true,
                'has_draft' => $draft !== null,
                'last_saved' => $draft['updated_at'] ?? null
            ]);
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