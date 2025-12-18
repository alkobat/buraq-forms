<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/Core/Services/FormService.php';
require_once __DIR__ . '/../../src/Core/Services/FormFieldService.php';
require_once __DIR__ . '/../../src/Core/Services/FormSubmissionService.php';
require_once __DIR__ . '/../../src/Core/Services/FormFileService.php';
require_once __DIR__ . '/../../src/Core/Services/SystemSettingsService.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

function sendJsonResponse(bool $success, string $message, array $data = []): void {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'طريقة الطلب غير صحيحة');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($csrfToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    sendJsonResponse(false, 'رمز الأمان غير صحيح');
}

$formId = isset($_POST['form_id']) ? (int)$_POST['form_id'] : 0;
if ($formId <= 0) {
    sendJsonResponse(false, 'معرف الاستمارة غير صحيح');
}

$submittedBy = isset($_POST['submitted_by']) ? trim($_POST['submitted_by']) : '';
if (empty($submittedBy)) {
    sendJsonResponse(false, 'البريد الإلكتروني مطلوب');
}

if (!filter_var($submittedBy, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(false, 'البريد الإلكتروني غير صحيح');
}

$departmentId = isset($_POST['department_id']) && !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

try {
    $submissionService = new BuraqForms\Core\Services\FormSubmissionService($pdo);
    $formService = new BuraqForms\Core\Services\FormService($pdo);
    
    $form = $formService->getById($formId);
    
    if ($form['status'] !== 'active') {
        sendJsonResponse(false, 'هذه الاستمارة غير نشطة حالياً');
    }
    
    $answers = [];
    foreach ($_POST as $key => $value) {
        if (!in_array($key, ['csrf_token', 'form_id', 'submitted_by', 'department_id'])) {
            $answers[$key] = $value;
        }
    }
    
    $files = [];
    foreach ($_FILES as $key => $file) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            $files[$key] = $file;
        } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
            sendJsonResponse(false, 'حدث خطأ في رفع الملف: ' . $file['name']);
        }
    }
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    
    $submissionData = [
        'submitted_by' => $submittedBy,
        'department_id' => $departmentId,
        'ip_address' => $ipAddress,
        'status' => 'pending'
    ];
    
    $submission = $submissionService->submit($formId, $submissionData, $answers, $files);
    
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    sendJsonResponse(true, 'تم إرسال الاستمارة بنجاح', [
        'reference_code' => $submission['reference_code'],
        'submission_id' => $submission['id']
    ]);
    
} catch (BuraqForms\Core\Exceptions\ValidationException $e) {
    $errors = $e->getErrors();
    $errorMessages = [];
    foreach ($errors as $field => $fieldErrors) {
        $errorMessages[] = implode(', ', $fieldErrors);
    }
    sendJsonResponse(false, 'فشل التحقق من البيانات: ' . implode('; ', $errorMessages));
    
} catch (BuraqForms\Core\Exceptions\ServiceException $e) {
    sendJsonResponse(false, $e->getMessage());
    
} catch (Exception $e) {
    error_log('Submission error: ' . $e->getMessage());
    sendJsonResponse(false, 'حدث خطأ أثناء معالجة الطلب. يرجى المحاولة مرة أخرى.');
}
