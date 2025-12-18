<?php

declare(strict_types=1);

namespace BuraqForms\Core\Services;

use BuraqForms\Core\Database;
use PDO;
use Exception;

/**
 * خدمة إدارة التصفيات المحفوظة
 */
class FilterService
{
    private PDO $db;

    public function __construct(Database $database = null)
    {
        $this->db = $database?->getConnection() ?? Database::getConnection();
    }

    /**
     * حفظ تصفية جديدة
     */
    public function saveFilter(array $filterData): ?int
    {
        try {
            $sql = "INSERT INTO saved_filters (
                admin_id, filter_name, filter_type, filter_criteria, is_default, is_public
            ) VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $filterData['admin_id'],
                $filterData['filter_name'],
                $filterData['filter_type'],
                json_encode($filterData['criteria'], JSON_UNESCAPED_UNICODE),
                $filterData['is_default'] ?? 0,
                $filterData['is_public'] ?? 0
            ]);

            return (int) $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Error saving filter: " . $e->getMessage());
            return null;
        }
    }

    /**
     * جلب التصفيات المحفوظة للمدير
     */
    public function getSavedFilters(int $adminId, string $filterType = 'submissions'): array
    {
        try {
            $sql = "SELECT * FROM saved_filters 
                    WHERE (admin_id = ? OR is_public = 1) AND filter_type = ?
                    ORDER BY is_default DESC, filter_name ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$adminId, $filterType]);
            $filters = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($filters as &$filter) {
                $filter['filter_criteria'] = json_decode($filter['filter_criteria'], true);
            }

            return $filters;
        } catch (Exception $e) {
            error_log("Error getting saved filters: " . $e->getMessage());
            return [];
        }
    }

    /**
     * جلب تصفية محفوظة بالـ ID
     */
    public function getSavedFilter(int $filterId): ?array
    {
        try {
            $sql = "SELECT * FROM saved_filters WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$filterId]);
            $filter = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($filter) {
                $filter['filter_criteria'] = json_decode($filter['filter_criteria'], true);
            }

            return $filter;
        } catch (Exception $e) {
            error_log("Error getting saved filter: " . $e->getMessage());
            return null;
        }
    }

    /**
     * تطبيق تصفية محفوظة على الاستعلام
     */
    public function applySavedFilter(array $filter, string $baseQuery): string
    {
        $criteria = $filter['filter_criteria'] ?? [];
        $query = $baseQuery;
        $conditions = [];

        // بناء شروط التصفية
        if (!empty($criteria['form_id'])) {
            $conditions[] = "fs.form_id = " . (int) $criteria['form_id'];
        }

        if (!empty($criteria['department_id'])) {
            $conditions[] = "fs.department_id = " . (int) $criteria['department_id'];
        }

        if (!empty($criteria['status'])) {
            $conditions[] = "fs.status = '" . addslashes($criteria['status']) . "'";
        }

        if (!empty($criteria['date_from'])) {
            $conditions[] = "fs.submitted_at >= '" . addslashes($criteria['date_from']) . "'";
        }

        if (!empty($criteria['date_to'])) {
            $conditions[] = "fs.submitted_at <= '" . addslashes($criteria['date_to']) . "'";
        }

        if (!empty($criteria['search'])) {
            $searchTerm = addslashes($criteria['search']);
            $conditions[] = "(fs.submitter_name LIKE '%{$searchTerm}%' OR fs.submitter_email LIKE '%{$searchTerm}%' OR fs.reference_code LIKE '%{$searchTerm}%')";
        }

        // إضافة الشروط إلى الاستعلام
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        return $query;
    }

    /**
     * تحديث تصفية محفوظة
     */
    public function updateSavedFilter(int $filterId, array $filterData): bool
    {
        try {
            $sql = "UPDATE saved_filters 
                    SET filter_name = ?, filter_criteria = ?, is_default = ?, is_public = ?, updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $filterData['filter_name'],
                json_encode($filterData['criteria'], JSON_UNESCAPED_UNICODE),
                $filterData['is_default'] ?? 0,
                $filterData['is_public'] ?? 0,
                $filterId
            ]);
        } catch (Exception $e) {
            error_log("Error updating saved filter: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف تصفية محفوظة
     */
    public function deleteSavedFilter(int $filterId, int $adminId): bool
    {
        try {
            $sql = "DELETE FROM saved_filters WHERE id = ? AND admin_id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$filterId, $adminId]);
        } catch (Exception $e) {
            error_log("Error deleting saved filter: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تعيين تصفية افتراضية
     */
    public function setDefaultFilter(int $adminId, string $filterType, int $filterId): bool
    {
        try {
            $this->db->beginTransaction();

            // إزالة حالة الافتراضي من جميع التصفيات للمدير
            $sql = "UPDATE saved_filters SET is_default = 0 WHERE admin_id = ? AND filter_type = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$adminId, $filterType]);

            // تعيين التصفية المحددة كافتراضية
            $sql = "UPDATE saved_filters SET is_default = 1 WHERE id = ? AND admin_id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$filterId, $adminId]);

            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error setting default filter: " . $e->getMessage());
            return false;
        }
    }

    /**
     * جلب التصفية الافتراضية
     */
    public function getDefaultFilter(int $adminId, string $filterType): ?array
    {
        try {
            $sql = "SELECT * FROM saved_filters 
                    WHERE admin_id = ? AND filter_type = ? AND is_default = 1 
                    ORDER BY updated_at DESC LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$adminId, $filterType]);
            $filter = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($filter) {
                $filter['filter_criteria'] = json_decode($filter['filter_criteria'], true);
            }

            return $filter;
        } catch (Exception $e) {
            error_log("Error getting default filter: " . $e->getMessage());
            return null;
        }
    }

    /**
     * تصدير التصفية
     */
    public function exportFilter(int $filterId): ?string
    {
        try {
            $filter = $this->getSavedFilter($filterId);
            if (!$filter) {
                return null;
            }

            $exportData = [
                'filter_name' => $filter['filter_name'],
                'filter_type' => $filter['filter_type'],
                'filter_criteria' => $filter['filter_criteria'],
                'exported_at' => date('Y-m-d H:i:s'),
                'version' => '1.0'
            ];

            return json_encode($exportData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (Exception $e) {
            error_log("Error exporting filter: " . $e->getMessage());
            return null;
        }
    }

    /**
     * استيراد تصفية
     */
    public function importFilter(array $importData, int $adminId): ?int
    {
        try {
            $filterData = [
                'admin_id' => $adminId,
                'filter_name' => $importData['filter_name'] . ' (مستورد)',
                'filter_type' => $importData['filter_type'],
                'criteria' => $importData['filter_criteria'],
                'is_default' => 0,
                'is_public' => 0
            ];

            return $this->saveFilter($filterData);
        } catch (Exception $e) {
            error_log("Error importing filter: " . $e->getMessage());
            return null;
        }
    }

    /**
     * جلب إحصائيات التصفيات
     */
    public function getFilterStats(int $adminId): array
    {
        try {
            $stats = [];

            // إجمالي التصفيات
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM saved_filters");
            $stats['total'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // التصفيات المفضلة
            $stmt = $this->db->query("SELECT COUNT(*) as default_filters FROM saved_filters WHERE is_default = 1");
            $stats['default'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['default_filters'];

            // التصفيات العامة
            $stmt = $this->db->query("SELECT COUNT(*) as public_filters FROM saved_filters WHERE is_public = 1");
            $stats['public'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['public_filters'];

            // التصفيات حسب النوع
            $stmt = $this->db->query("
                SELECT filter_type, COUNT(*) as count 
                FROM saved_filters 
                GROUP BY filter_type
            ");
            $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // التصفيات الأكثر استخداماً (في المستقبل يمكن إضافة جدول استخدام)
            $stmt = $this->db->query("
                SELECT filter_name, filter_type, updated_at 
                FROM saved_filters 
                ORDER BY updated_at DESC 
                LIMIT 10
            ");
            $stats['recent'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $stats;
        } catch (Exception $e) {
            error_log("Error getting filter stats: " . $e->getMessage());
            return [];
        }
    }
}