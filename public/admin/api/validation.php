<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once __DIR__ . '/../../../vendor/autoload.php';

use EmployeeEvaluationSystem\Core\Database;
use EmployeeEvaluationSystem\Core\Services\ValidationService;
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
$validationService = new ValidationService($database);
$permissionService = new PermissionService($database);
$auditService = new AuditService($database);

$adminId = $_SESSION['admin_id'] ?? 0;

try {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'validate_field':
            // تحقق فوري من الحقل
            $fieldData = [
                'field_name' => $_POST['field_name'] ?? '',
                'field_value' => $_POST['field_value'] ?? '',
                'field_type' => $_POST['field_type'] ?? 'text',
                'is_required' => isset($_POST['is_required']),
                'validation_rules' => json_decode($_POST['validation_rules'] ?? '{}', true)
            ];
            
            $result = $validationService->validateFieldAjax($fieldData);
            echo json_encode($result);
            break;
            
        case 'validate_form':
            // تحقق شامل من الاستمارة
            $formData = json_decode(file_get_contents('php://input'), true);
            $fieldDefinitions = json_decode($_POST['field_definitions'] ?? '[]', true);
            
            $result = $validationService->validateFormSubmission($formData, $fieldDefinitions);
            echo json_encode($result);
            break;
            
        case 'sanitize_input':
            // تنظيف المدخلات
            $input = $_POST['input'] ?? '';
            $type = $_POST['type'] ?? 'string';
            
            $result = $validationService->sanitizeInput($input, $type);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'sanitize_array':
            // تنظيف مصفوفة المدخلات
            $data = json_decode(file_get_contents('php://input'), true);
            $fieldTypes = json_decode($_POST['field_types'] ?? '{}', true);
            
            $result = $validationService->sanitizeArray($data, $fieldTypes);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'validate_file':
            // تحقق من الملف المرفوع
            if (!isset($_FILES['file'])) {
                echo json_encode(['success' => false, 'errors' => ['لم يتم رفع أي ملف']]);
                break;
            }
            
            $rules = json_decode($_POST['rules'] ?? '{}', true);
            $file = $_FILES['file'];
            
            $result = $validationService->validateUploadedFile($file, $rules);
            echo json_encode($result);
            break;
            
        case 'add_custom_rule':
            // إضافة قاعدة تحقق مخصصة (يتطلب صلاحية عالية)
            if (!$permissionService->hasPermission($adminId, 'system.settings')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية']);
                break;
            }
            
            $ruleName = $_POST['rule_name'] ?? '';
            $errorMessage = $_POST['error_message'] ?? '';
            
            // حفظ القاعدة في ذاكرة التخزين المؤقت أو قاعدة البيانات
            $result = $validationService->addCustomValidationRule($ruleName, function() {
                // callback function
            }, $errorMessage);
            
            echo json_encode(['success' => $result]);
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