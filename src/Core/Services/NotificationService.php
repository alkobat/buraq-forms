<?php

declare(strict_types=1);

namespace EmployeeEvaluationSystem\Core\Services;

use EmployeeEvaluationSystem\Core\Database;
use PDO;
use Exception;

/**
 * خدمة إدارة الإشعارات
 */
class NotificationService
{
    private PDO $db;

    public function __construct(Database $database = null)
    {
        $this->db = $database?->getConnection() ?? Database::getConnection();
    }

    /**
     * إنشاء إشعار جديد
     */
    public function createNotification(array $data): ?int
    {
        try {
            $sql = "INSERT INTO notifications (
                type, title, message, recipient_id, recipient_type, 
                recipient_contact, form_id, submission_id, priority
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['type'],
                $data['title'],
                $data['message'],
                $data['recipient_id'] ?? null,
                $data['recipient_type'] ?? 'admin',
                $data['recipient_contact'] ?? null,
                $data['form_id'] ?? null,
                $data['submission_id'] ?? null,
                $data['priority'] ?? 'normal'
            ]);

            return (int) $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return null;
        }
    }

    /**
     * إرسال إشعار فوري
     */
    public function sendNotification(int $notificationId): bool
    {
        try {
            // جلب بيانات الإشعار
            $sql = "SELECT * FROM notifications WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$notificationId]);
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$notification) {
                return false;
            }

            $success = true;
            $errorMessages = [];

            // إرسال البريد الإلكتروني إذا كان مفعلاً
            if ($notification['recipient_type'] === 'admin' || 
                ($notification['recipient_type'] === 'email' && !empty($notification['recipient_contact']))) {
                
                $emailSent = $this->sendEmailNotification($notification);
                if (!$emailSent) {
                    $success = false;
                    $errorMessages[] = "فشل في إرسال البريد الإلكتروني";
                }
            }

            // تحديث حالة الإشعار
            $status = $success ? 'sent' : 'failed';
            $errorMessage = $success ? null : implode('; ', $errorMessages);

            $sql = "UPDATE notifications 
                    SET status = ?, sent_at = NOW(), error_message = ? 
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$status, $errorMessage, $notificationId]);

            return $success;
        } catch (Exception $e) {
            error_log("Error sending notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * إرسال إشعار البريد الإلكتروني
     */
    private function sendEmailNotification(array $notification): bool
    {
        try {
            // هنا يمكن تنفيذ إرسال البريد الإلكتروني
            // مثال باستخدام PHPMailer أو mail() function
            $to = $this->getRecipientEmail($notification);
            if (!$to) {
                return false;
            }

            $subject = $notification['title'];
            $message = $notification['message'];
            $headers = "From: noreply@employee-evaluation-system.com\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

            return mail($to, $subject, $message, $headers);
        } catch (Exception $e) {
            error_log("Error sending email notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * جلب بريد المستقبل
     */
    private function getRecipientEmail(array $notification): ?string
    {
        if ($notification['recipient_type'] === 'email') {
            return $notification['recipient_contact'];
        }

        if ($notification['recipient_type'] === 'admin' && $notification['recipient_id']) {
            $sql = "SELECT email FROM admins WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$notification['recipient_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['email'] ?? null;
        }

        return null;
    }

    /**
     * إنشاء إشعارات تلقائية عند استقبال استمارة جديدة
     */
    public function notifyNewSubmission(int $formId, int $submissionId): void
    {
        try {
            // جلب معلومات الاستمارة
            $sql = "SELECT f.title as form_title, f.department_id, d.name as department_name
                    FROM forms f 
                    LEFT JOIN departments d ON f.department_id = d.id 
                    WHERE f.id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$formId]);
            $form = $stmt->fetch(PDO::FETCH_ASSOC);

            // جلب جميع المشرفين في الإدارة
            $sql = "SELECT DISTINCT a.id, a.email, a.name
                    FROM admin_role_assignments ara
                    JOIN admins a ON ara.admin_id = a.id
                    LEFT JOIN admin_roles ar ON ara.role_id = ar.id
                    WHERE ar.role_name IN ('Super Admin', 'Admin', 'Manager')
                    AND (ara.department_id = ? OR ara.department_id IS NULL)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$form['department_id']]);
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($admins as $admin) {
                $this->createNotification([
                    'type' => 'new_submission',
                    'title' => 'استمارة جديدة مستلمة',
                    'message' => "تم استلام استمارة جديدة: {$form['form_title']}\nالإدارة: {$form['department_name']}\nرقم المرجع: REF-" . str_pad($submissionId, 6, '0', STR_PAD_LEFT),
                    'recipient_id' => $admin['id'],
                    'recipient_type' => 'admin',
                    'form_id' => $formId,
                    'submission_id' => $submissionId,
                    'priority' => 'normal'
                ]);
            }
        } catch (Exception $e) {
            error_log("Error notifying new submission: " . $e->getMessage());
        }
    }

    /**
     * جلب الإشعارات غير المقروءة
     */
    public function getUnreadNotifications(int $adminId, int $limit = 20): array
    {
        try {
            $sql = "SELECT * FROM notifications 
                    WHERE recipient_id = ? AND status = 'pending' 
                    ORDER BY priority DESC, created_at DESC 
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$adminId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting unread notifications: " . $e->getMessage());
            return [];
        }
    }

    /**
     * تحديد إشعار كمقروء
     */
    public function markAsRead(int $notificationId): bool
    {
        try {
            $sql = "UPDATE notifications SET status = 'read', read_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$notificationId]);
        } catch (Exception $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف الإشعارات القديمة
     */
    public function cleanOldNotifications(int $daysOld = 30): int
    {
        try {
            $sql = "DELETE FROM notifications 
                    WHERE status IN ('sent', 'failed', 'read') 
                    AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$daysOld]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Error cleaning old notifications: " . $e->getMessage());
            return 0;
        }
    }
}