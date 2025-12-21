<?php

declare(strict_types=1);

namespace BuraqForms\Core\Services;

// Load Logger class directly to avoid autoloading issues
require_once __DIR__ . '/../Logger.php';

use BuraqForms\Core\Database;
use PDO;
use Exception;

/**
 * خدمة إدارة التعليقات والتعاون
 */
class CommentService
{
    private PDO $db;
    private Logger $logger;

    public function __construct(Database $database = null, Logger $logger = null)
    {
        $this->db = $database?->getConnection() ?? Database::getConnection();
        $this->logger = $logger ?? new Logger();
    }

    /**
     * إضافة تعليق جديد
     */
    public function addComment(int $submissionId, int $adminId, string $comment, string $type = 'general', bool $isInternal = false, ?int $parentId = null): ?int
    {
        try {
            $sql = "INSERT INTO comments (
                submission_id, admin_id, comment_type, comment, is_internal, parent_id
            ) VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $submissionId,
                $adminId,
                $type,
                $comment,
                $isInternal ? 1 : 0,
                $parentId
            ]);

            return (int) $this->db->lastInsertId();
        } catch (Exception $e) {
            $this->logger->error("Error adding comment: " . $e->getMessage());
            return null;
        }
    }

    /**
     * جلب تعليقات الإجابة
     */
    public function getSubmissionComments(int $submissionId): array
    {
        try {
            $sql = "SELECT c.*, a.name as admin_name, a.email as admin_email
                    FROM comments c
                    JOIN admins a ON c.admin_id = a.id
                    WHERE c.submission_id = ?
                    ORDER BY c.created_at ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$submissionId]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // تنظيم التعليقات في هيكل متدرج
            return $this->organizeComments($comments);
        } catch (Exception $e) {
            $this->logger->error("Error getting submission comments: " . $e->getMessage());
            return [];
        }
    }

    /**
     * تحديث تعليق
     */
    public function updateComment(int $commentId, int $adminId, string $newComment): bool
    {
        try {
            $sql = "UPDATE comments 
                    SET comment = ?, updated_at = NOW() 
                    WHERE id = ? AND admin_id = ?";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$newComment, $commentId, $adminId]);
        } catch (Exception $e) {
            $this->logger->error("Error updating comment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف تعليق
     */
    public function deleteComment(int $commentId, int $adminId): bool
    {
        try {
            $sql = "DELETE FROM comments WHERE id = ? AND admin_id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$commentId, $adminId]);
        } catch (Exception $e) {
            $this->logger->error("Error deleting comment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تنظيم التعليقات في هيكل متدرج
     */
    private function organizeComments(array $comments): array
    {
        $commentMap = [];
        $rootComments = [];

        // إنشاء خريطة التعليقات
        foreach ($comments as $comment) {
            $commentMap[$comment['id']] = $comment;
            $commentMap[$comment['id']]['replies'] = [];
        }

        // ترتيب التعليقات
        foreach ($commentMap as $comment) {
            if ($comment['parent_id'] === null) {
                $rootComments[] = &$commentMap[$comment['id']];
            } else {
                if (isset($commentMap[$comment['parent_id']])) {
                    $commentMap[$comment['parent_id']]['replies'][] = &$commentMap[$comment['id']];
                }
            }
        }

        return $rootComments;
    }

    /**
     * جلب إحصائيات التعليقات
     */
    public function getCommentStats(): array
    {
        try {
            $stats = [];

            // إجمالي التعليقات
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM comments");
            $stats['total'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // التعليقات حسب النوع
            $stmt = $this->db->query("
                SELECT comment_type, COUNT(*) as count 
                FROM comments 
                GROUP BY comment_type
            ");
            $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // التعليقات الداخلية
            $stmt = $this->db->query("SELECT COUNT(*) as internal FROM comments WHERE is_internal = 1");
            $stats['internal'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['internal'];

            // أكثر الإجابات تعليقاً
            $stmt = $this->db->query("
                SELECT fs.id, fs.reference_code, f.title, COUNT(c.id) as comment_count
                FROM comments c
                JOIN form_submissions fs ON c.submission_id = fs.id
                JOIN forms f ON fs.form_id = f.id
                GROUP BY c.submission_id
                ORDER BY comment_count DESC
                LIMIT 10
            ");
            $stats['top_commented'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $stats;
        } catch (Exception $e) {
            $this->logger->error("Error getting comment stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * البحث في التعليقات
     */
    public function searchComments(string $query, ?int $adminId = null, ?string $type = null): array
    {
        try {
            $whereConditions = ["c.comment LIKE ?"];
            $params = ["%{$query}%"];

            if ($adminId) {
                $whereConditions[] = "c.admin_id = ?";
                $params[] = $adminId;
            }

            if ($type) {
                $whereConditions[] = "c.comment_type = ?";
                $params[] = $type;
            }

            $whereClause = "WHERE " . implode(" AND ", $whereConditions);

            $sql = "SELECT c.*, a.name as admin_name, fs.reference_code, f.title
                    FROM comments c
                    JOIN admins a ON c.admin_id = a.id
                    JOIN form_submissions fs ON c.submission_id = fs.id
                    JOIN forms f ON fs.form_id = f.id
                    {$whereClause}
                    ORDER BY c.created_at DESC
                    LIMIT 50";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error searching comments: " . $e->getMessage());
            return [];
        }
    }
}