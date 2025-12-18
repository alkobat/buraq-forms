<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once __DIR__ . '/../../../vendor/autoload.php';

use EmployeeEvaluationSystem\Core\Database;
use EmployeeEvaluationSystem\Core\Services\NotificationService;
use EmployeeEvaluationSystem\Core\Services\AuditService;
use EmployeeEvaluationSystem\Core\Services\CommentService;
use EmployeeEvaluationSystem\Core\Services\BackupService;
use EmployeeEvaluationSystem\Core\Services\SystemSettingsService;

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$database = Database::getConnection();
$notificationService = new NotificationService($database);
$auditService = new AuditService($database);
$commentService = new CommentService($database);
$backupService = new BackupService($database);
$systemSettingsService = new SystemSettingsService($database);

$adminId = $_SESSION['admin_id'] ?? 0;

try {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        // إدارة الإشعارات
        case 'send_notification':
            $notificationId = (int) ($_GET['id'] ?? 0);
            
            if ($notificationId > 0) {
                $result = $notificationService->sendNotification($notificationId);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'معرف الإشعار غير صحيح']);
            }
            break;
            
        case 'mark_notification_read':
            $notificationId = (int) ($_POST['notification_id'] ?? 0);
            
            if ($notificationId > 0) {
                $result = $notificationService->markAsRead($notificationId);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'معرف الإشعار غير صحيح']);
            }
            break;
            
        case 'get_unread_notifications':
            $notifications = $notificationService->getUnreadNotifications($adminId);
            echo json_encode(['success' => true, 'data' => $notifications]);
            break;
            
        // إدارة التعليقات
        case 'add_comment':
            $commentData = json_decode(file_get_contents('php://input'), true);
            
            if ($commentData) {
                $commentId = $commentService->addComment(
                    $commentData['submission_id'],
                    $adminId,
                    $commentData['comment'],
                    $commentData['comment_type'] ?? 'general',
                    $commentData['is_internal'] ?? false,
                    $commentData['parent_id'] ?? null
                );
                
                if ($commentId) {
                    $auditService->logCreate($adminId, 'comment', $commentId, $commentData);
                    echo json_encode(['success' => true, 'comment_id' => $commentId]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'فشل في إضافة التعليق']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
            }
            break;
            
        case 'get_comments':
            $submissionId = (int) ($_GET['submission_id'] ?? 0);
            
            if ($submissionId > 0) {
                $comments = $commentService->getSubmissionComments($submissionId);
                echo json_encode(['success' => true, 'data' => $comments]);
            } else {
                echo json_encode(['success' => false, 'message' => 'معرف الإجابة غير صحيح']);
            }
            break;
            
        case 'update_comment':
            $commentId = (int) ($_POST['comment_id'] ?? 0);
            $newComment = $_POST['new_comment'] ?? '';
            
            if ($commentId > 0 && !empty($newComment)) {
                $result = $commentService->updateComment($commentId, $adminId, $newComment);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
            }
            break;
            
        case 'delete_comment':
            $commentId = (int) ($_POST['comment_id'] ?? 0);
            
            if ($commentId > 0) {
                $result = $commentService->deleteComment($commentId, $adminId);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'معرف التعليق غير صحيح']);
            }
            break;
            
        // النسخ الاحتياطية
        case 'create_backup':
            $backupType = $_POST['backup_type'] ?? 'full';
            
            if ($backupType === 'full') {
                $backupId = $backupService->createFullBackup($adminId);
            } else {
                $backupId = $backupService->createDataBackup($adminId);
            }
            
            if ($backupId) {
                echo json_encode(['success' => true, 'backup_id' => $backupId]);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل في إنشاء النسخة الاحتياطية']);
            }
            break;
            
        case 'get_backups':
            $backups = $backupService->getAllBackups();
            echo json_encode(['success' => true, 'data' => $backups]);
            break;
            
        case 'restore_backup':
            $backupId = (int) ($_POST['backup_id'] ?? 0);
            
            if ($backupId > 0) {
                $result = $backupService->restoreBackup($backupId, $adminId);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'معرف النسخة الاحتياطية غير صحيح']);
            }
            break;
            
        case 'delete_backup':
            $backupId = (int) ($_POST['backup_id'] ?? 0);
            
            if ($backupId > 0) {
                $result = $backupService->deleteBackup($backupId, $adminId);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'معرف النسخة الاحتياطية غير صحيح']);
            }
            break;
            
        case 'get_backup_stats':
            $stats = $backupService->getBackupStats();
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        // إعدادات النظام
        case 'get_setting':
            $category = $_GET['category'] ?? '';
            $key = $_GET['key'] ?? '';
            
            if ($category && $key) {
                $value = $systemSettingsService->getSetting($category, $key);
                echo json_encode(['success' => true, 'value' => $value]);
            } else {
                echo json_encode(['success' => false, 'message' => 'معلمات غير صحيحة']);
            }
            break;
            
        case 'update_setting':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($data && isset($data['category'], $data['key'], $data['value'])) {
                $result = $systemSettingsService->updateSetting(
                    $data['category'],
                    $data['key'],
                    $data['value'],
                    $data['type'] ?? 'string'
                );
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
            }
            break;
            
        case 'get_all_settings':
            $category = $_GET['category'] ?? '';
            $settings = $systemSettingsService->getAllSettings($category);
            echo json_encode(['success' => true, 'data' => $settings]);
            break;
            
        // سجل الأنشطة
        case 'get_audit_logs':
            $limit = (int) ($_GET['limit'] ?? 50);
            $entityType = $_GET['entity_type'] ?? '';
            $entityId = (int) ($_GET['entity_id'] ?? 0);
            
            $logs = $auditService->getAuditLogs(0, null, $entityType, $entityId, $limit);
            echo json_encode(['success' => true, 'data' => $logs]);
            break;
            
        case 'get_audit_stats':
            $days = (int) ($_GET['days'] ?? 30);
            $stats = $auditService->getAuditStats($days);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        // إحصائيات شاملة للنظام
        case 'get_system_stats':
            $stats = [];
            
            // إحصائيات الإشعارات
            $stats['notifications'] = [
                'unread' => count($notificationService->getUnreadNotifications($adminId))
            ];
            
            // إحصائيات التعليقات
            $stats['comments'] = $commentService->getCommentStats();
            
            // إحصائيات النسخ الاحتياطية
            $stats['backups'] = $backupService->getBackupStats();
            
            // إحصائيات سجل الأنشطة
            $stats['audit'] = $auditService->getAuditStats(7);
            
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        // تنظيف البيانات
        case 'clean_old_data':
            $dataType = $_POST['data_type'] ?? '';
            $daysOld = (int) ($_POST['days_old'] ?? 30);
            
            $result = false;
            $deletedCount = 0;
            
            switch ($dataType) {
                case 'notifications':
                    $deletedCount = $notificationService->cleanOldNotifications($daysOld);
                    $result = true;
                    break;
                    
                case 'audit_logs':
                    $deletedCount = $auditService->cleanOldLogs($daysOld);
                    $result = true;
                    break;
                    
                case 'drafts':
                    // تنفيذ تنظيف المسودات (يتطلب DraftService)
                    $result = true;
                    break;
                    
                case 'backups':
                    $deletedCount = $backupService->cleanOldBackups($daysOld);
                    $result = true;
                    break;
            }
            
            if ($result) {
                $auditService->logActivity($adminId, 'clean_data', $dataType, 0, null, [
                    'data_type' => $dataType,
                    'days_old' => $daysOld,
                    'deleted_count' => $deletedCount
                ]);
                
                echo json_encode([
                    'success' => true,
                    'deleted_count' => $deletedCount
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'نوع بيانات غير صحيح']);
            }
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