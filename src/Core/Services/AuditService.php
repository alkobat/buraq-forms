<?php

declare(strict_types=1);

namespace EmployeeEvaluationSystem\Core\Services;

use EmployeeEvaluationSystem\Core\Database;
use PDO;
use Exception;

/**
 * خدمة سجل الأنشطة (Audit Logging)
 */
class AuditService
{
    private PDO $db;

    public function __construct(Database $database = null)
    {
        $this->db = $database?->getConnection() ?? Database::getConnection();
    }

    /**
     * تسجيل نشاط جديد
     */
    public function logActivity(?int $adminId, string $action, string $entityType, ?int $entityId = null, ?array $oldValues = null, ?array $newValues = null, ?string $ipAddress = null, ?string $userAgent = null): bool
    {
        try {
            $sql = "INSERT INTO audit_logs (
                admin_id, action, entity_type, entity_id, 
                old_values, new_values, ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $adminId,
                $action,
                $entityType,
                $entityId,
                $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? null,
                $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null)
            ]);
        } catch (Exception $e) {
            error_log("Error logging audit activity: " . $e->getMessage());
            return false;
        }
    }

    /**
     * جلب سجل الأنشطة
     */
    public function getAuditLogs(int $adminId, ?string $action = null, ?string $entityType = null, ?int $entityId = null, int $limit = 50): array
    {
        try {
            $whereConditions = [];
            $params = [];

            if ($adminId !== 0) {
                $whereConditions[] = "al.admin_id = ?";
                $params[] = $adminId;
            }

            if ($action) {
                $whereConditions[] = "al.action = ?";
                $params[] = $action;
            }

            if ($entityType) {
                $whereConditions[] = "al.entity_type = ?";
                $params[] = $entityType;
            }

            if ($entityId) {
                $whereConditions[] = "al.entity_id = ?";
                $params[] = $entityId;
            }

            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

            $sql = "SELECT al.*, a.name as admin_name, a.email as admin_email
                    FROM audit_logs al
                    LEFT JOIN admins a ON al.admin_id = a.id
                    {$whereClause}
                    ORDER BY al.created_at DESC
                    LIMIT ?";

            $params[] = $limit;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($logs as &$log) {
                $log['old_values'] = $log['old_values'] ? json_decode($log['old_values'], true) : null;
                $log['new_values'] = $log['new_values'] ? json_decode($log['new_values'], true) : null;
            }

            return $logs;
        } catch (Exception $e) {
            error_log("Error getting audit logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * جلب سجل نشاط لعنصر معين
     */
    public function getEntityAuditLogs(string $entityType, int $entityId): array
    {
        try {
            $sql = "SELECT al.*, a.name as admin_name
                    FROM audit_logs al
                    LEFT JOIN admins a ON al.admin_id = a.id
                    WHERE al.entity_type = ? AND al.entity_id = ?
                    ORDER BY al.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$entityType, $entityId]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($logs as &$log) {
                $log['old_values'] = $log['old_values'] ? json_decode($log['old_values'], true) : null;
                $log['new_values'] = $log['new_values'] ? json_decode($log['new_values'], true) : null;
            }

            return $logs;
        } catch (Exception $e) {
            error_log("Error getting entity audit logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * تنظيف السجلات القديمة
     */
    public function cleanOldLogs(int $daysOld = 90): int
    {
        try {
            $sql = "DELETE FROM audit_logs 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$daysOld]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Error cleaning old audit logs: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * إحصائيات الأنشطة
     */
    public function getAuditStats(?int $days = 30): array
    {
        try {
            $stats = [];

            // إجمالي الأنشطة
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM audit_logs");
            $stats['total'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // الأنشطة في الفترة المحددة
            if ($days) {
                $stmt = $this->db->prepare("SELECT COUNT(*) as recent FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
                $stmt->execute([$days]);
                $stats['recent'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['recent'];
            }

            // الأنشطة حسب النوع
            $stmt = $this->db->query("
                SELECT entity_type, COUNT(*) as count 
                FROM audit_logs 
                GROUP BY entity_type 
                ORDER BY count DESC
            ");
            $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // الأنشطة حسب العمل
            $stmt = $this->db->query("
                SELECT action, COUNT(*) as count 
                FROM audit_logs 
                GROUP BY action 
                ORDER BY count DESC
            ");
            $stats['by_action'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // الأنشطة اليومية (آخر 7 أيام)
            $stmt = $this->db->query("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count
                FROM audit_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
            $stats['daily_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // أكثر المستخدمين نشاطاً
            $stmt = $this->db->query("
                SELECT a.name, a.email, COUNT(al.id) as activity_count
                FROM audit_logs al
                LEFT JOIN admins a ON al.admin_id = a.id
                WHERE al.admin_id IS NOT NULL
                GROUP BY al.admin_id
                ORDER BY activity_count DESC
                LIMIT 10
            ");
            $stats['top_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $stats;
        } catch (Exception $e) {
            error_log("Error getting audit stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * تسجيل إنشاء عنصر جديد
     */
    public function logCreate(int $adminId, string $entityType, int $entityId, array $values): bool
    {
        return $this->logActivity($adminId, 'create', $entityType, $entityId, null, $values);
    }

    /**
     * تسجيل تحديث عنصر
     */
    public function logUpdate(int $adminId, string $entityType, int $entityId, array $oldValues, array $newValues): bool
    {
        return $this->logActivity($adminId, 'update', $entityType, $entityId, $oldValues, $newValues);
    }

    /**
     * تسجيل حذف عنصر
     */
    public function logDelete(int $adminId, string $entityType, int $entityId, array $values): bool
    {
        return $this->logActivity($adminId, 'delete', $entityType, $entityId, $values, null);
    }

    /**
     * تسجيل تسجيل دخول
     */
    public function logLogin(int $adminId, ?string $ipAddress = null): bool
    {
        return $this->logActivity($adminId, 'login', 'admin', $adminId, null, ['timestamp' => date('Y-m-d H:i:s')], $ipAddress);
    }

    /**
     * تسجيل تسجيل خروج
     */
    public function logLogout(int $adminId, ?string $ipAddress = null): bool
    {
        return $this->logActivity($adminId, 'logout', 'admin', $adminId, null, ['timestamp' => date('Y-m-d H:i:s')], $ipAddress);
    }

    /**
     * تسجيل عرض صفحة
     */
    public function logView(int $adminId, string $entityType, int $entityId): bool
    {
        return $this->logActivity($adminId, 'view', $entityType, $entityId);
    }

    /**
     * تسجيل تصدير بيانات
     */
    public function logExport(int $adminId, string $entityType, int $entityId, string $format): bool
    {
        return $this->logActivity($adminId, 'export', $entityType, $entityId, null, ['format' => $format]);
    }

    /**
     * تسجيل إرسال إشعار
     */
    public function logNotification(int $adminId, string $action, int $notificationId, array $details): bool
    {
        return $this->logActivity($adminId, $action, 'notification', $notificationId, null, $details);
    }
}