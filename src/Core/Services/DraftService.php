<?php

declare(strict_types=1);

namespace EmployeeEvaluationSystem\Core\Services;

use EmployeeEvaluationSystem\Core\Database;
use PDO;
use Exception;

/**
 * خدمة إدارة المسودات (Draft Management)
 */
class DraftService
{
    private PDO $db;

    public function __construct(Database $database = null)
    {
        $this->db = $database?->getConnection() ?? Database::getConnection();
    }

    /**
     * حفظ مسودة الاستمارة
     */
    public function saveDraft(int $formId, string $sessionId, string $userIp, array $formData, ?string $expiresAt = null): bool
    {
        try {
            $sql = "INSERT INTO drafts (form_id, user_session_id, user_ip, form_data, expires_at) 
                    VALUES (?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    form_data = VALUES(form_data), 
                    updated_at = CURRENT_TIMESTAMP,
                    expires_at = VALUES(expires_at)";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $formId,
                $sessionId,
                $userIp,
                json_encode($formData, JSON_UNESCAPED_UNICODE),
                $expiresAt
            ]);
        } catch (Exception $e) {
            error_log("Error saving draft: " . $e->getMessage());
            return false;
        }
    }

    /**
     * استرجاع مسودة الاستمارة
     */
    public function getDraft(int $formId, string $sessionId): ?array
    {
        try {
            $sql = "SELECT * FROM drafts 
                    WHERE form_id = ? AND user_session_id = ? 
                    AND (expires_at IS NULL OR expires_at > NOW()) 
                    ORDER BY updated_at DESC LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$formId, $sessionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return null;
            }

            $result['form_data'] = json_decode($result['form_data'], true);
            return $result;
        } catch (Exception $e) {
            error_log("Error getting draft: " . $e->getMessage());
            return null;
        }
    }

    /**
     * حذف المسودة
     */
    public function deleteDraft(int $formId, string $sessionId): bool
    {
        try {
            $sql = "DELETE FROM drafts WHERE form_id = ? AND user_session_id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$formId, $sessionId]);
        } catch (Exception $e) {
            error_log("Error deleting draft: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تنظيف المسودات المنتهية الصلاحية
     */
    public function cleanExpiredDrafts(): int
    {
        try {
            $sql = "DELETE FROM drafts WHERE expires_at IS NOT NULL AND expires_at <= NOW()";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Error cleaning expired drafts: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * إحصائيات المسودات
     */
    public function getDraftStats(): array
    {
        try {
            $stats = [];

            // إجمالي المسودات
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM drafts");
            $stats['total'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // مسودات اليوم
            $stmt = $this->db->query("SELECT COUNT(*) as today FROM drafts WHERE DATE(created_at) = CURDATE()");
            $stats['today'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['today'];

            // مسودات الاستمارات المختلفة
            $stmt = $this->db->query("
                SELECT f.title, COUNT(d.id) as draft_count 
                FROM drafts d 
                JOIN forms f ON d.form_id = f.id 
                GROUP BY d.form_id 
                ORDER BY draft_count DESC 
                LIMIT 5
            ");
            $stats['by_form'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $stats;
        } catch (Exception $e) {
            error_log("Error getting draft stats: " . $e->getMessage());
            return [];
        }
    }
}