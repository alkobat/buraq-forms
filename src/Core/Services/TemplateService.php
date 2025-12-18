<?php

declare(strict_types=1);

namespace BuraqForms\Core\Services;

use BuraqForms\Core\Database;
use BuraqForms\Core\Logger;
use PDO;
use Exception;

/**
 * خدمة إدارة قوالب الاستمارات
 */
class TemplateService
{
    private PDO $db;
    private Logger $logger;

    public function __construct(Database $database = null, Logger $logger = null)
    {
        $this->db = $database?->getConnection() ?? Database::getConnection();
        $this->logger = $logger ?? new Logger();
    }

    /**
     * إنشاء قالب جديد من استمارة موجودة
     */
    public function createTemplateFromForm(int $formId, string $templateName, ?string $description = null, int $createdBy, bool $isPublic = false): ?int
    {
        try {
            $this->db->beginTransaction();

            // جلب بيانات الاستمارة
            $sql = "SELECT * FROM forms WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$formId]);
            $form = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$form) {
                throw new Exception("Form not found");
            }

            // جلب حقول الاستمارة
            $sql = "SELECT * FROM form_fields WHERE form_id = ? ORDER BY order_index";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$formId]);
            $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // إعداد بيانات القالب
            $templateData = [
                'form' => [
                    'title' => $form['title'],
                    'description' => $form['description'],
                    'status' => 'template'
                ],
                'fields' => $fields,
                'created_at' => date('Y-m-d H:i:s'),
                'source_form_id' => $formId
            ];

            // حفظ القالب
            $sql = "INSERT INTO form_templates (
                template_name, template_description, form_config, category, is_public, created_by
            ) VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $templateName,
                $description,
                json_encode($templateData, JSON_UNESCAPED_UNICODE),
                $this->detectCategory($form['title'], $form['description']),
                $isPublic ? 1 : 0,
                $createdBy
            ]);

            $templateId = (int) $this->db->lastInsertId();

            $this->db->commit();
            return $templateId;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Error creating template: " . $e->getMessage());
            return null;
        }
    }

    /**
     * إنشاء استمارة من قالب
     */
    public function createFormFromTemplate(int $templateId, string $formTitle, int $departmentId, int $createdBy): ?int
    {
        try {
            $this->db->beginTransaction();

            // جلب بيانات القالب
            $template = $this->getTemplate($templateId);
            if (!$template) {
                throw new Exception("Template not found");
            }

            $formConfig = $template['form_config'];
            $formData = $formConfig['form'];
            $fields = $formConfig['fields'];

            // إنشاء الاستمارة الجديدة
            $sql = "INSERT INTO forms (title, description, department_id, status, created_by, created_at) 
                    VALUES (?, ?, ?, 'active', ?, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $formTitle,
                $formData['description'],
                $departmentId,
                $createdBy
            ]);

            $newFormId = (int) $this->db->lastInsertId();

            // إنشاء حقول الاستمارة
            foreach ($fields as $field) {
                $sql = "INSERT INTO form_fields (
                    form_id, field_type, label, placeholder, is_required, is_active, 
                    source_type, field_key, order_index, field_options, validation_rules
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $newFormId,
                    $field['field_type'],
                    $field['label'],
                    $field['placeholder'],
                    $field['is_required'],
                    $field['is_active'],
                    $field['source_type'],
                    $field['field_key'],
                    $field['order_index'],
                    $field['field_options'],
                    $field['validation_rules']
                ]);
            }

            // تحديث عدد الاستخدامات
            $sql = "UPDATE form_templates SET usage_count = usage_count + 1 WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$templateId]);

            $this->db->commit();
            return $newFormId;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Error creating form from template: " . $e->getMessage());
            return null;
        }
    }

    /**
     * جلب قالب بالـ ID
     */
    public function getTemplate(int $templateId): ?array
    {
        try {
            $sql = "SELECT ft.*, a.name as creator_name 
                    FROM form_templates ft 
                    JOIN admins a ON ft.created_by = a.id 
                    WHERE ft.id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($template) {
                $template['form_config'] = json_decode($template['form_config'], true);
            }

            return $template;
        } catch (Exception $e) {
            $this->logger->error("Error getting template: " . $e->getMessage());
            return null;
        }
    }

    /**
     * جلب جميع القوالب
     */
    public function getAllTemplates(?int $adminId = null, ?string $category = null, bool $publicOnly = false): array
    {
        try {
            $whereConditions = [];
            $params = [];

            if ($publicOnly) {
                $whereConditions[] = "ft.is_public = 1";
            } elseif ($adminId) {
                $whereConditions[] = "(ft.is_public = 1 OR ft.created_by = ?)";
                $params[] = $adminId;
            }

            if ($category) {
                $whereConditions[] = "ft.category = ?";
                $params[] = $category;
            }

            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

            $sql = "SELECT ft.*, a.name as creator_name
                    FROM form_templates ft 
                    JOIN admins a ON ft.created_by = a.id 
                    {$whereClause}
                    ORDER BY ft.usage_count DESC, ft.created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($templates as &$template) {
                $template['form_config'] = json_decode($template['form_config'], true);
            }

            return $templates;
        } catch (Exception $e) {
            $this->logger->error("Error getting all templates: " . $e->getMessage());
            return [];
        }
    }

    /**
     * جلب الفئات المتاحة
     */
    public function getTemplateCategories(): array
    {
        try {
            $sql = "SELECT category, COUNT(*) as template_count 
                    FROM form_templates 
                    WHERE category IS NOT NULL 
                    GROUP BY category 
                    ORDER BY template_count DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error getting template categories: " . $e->getMessage());
            return [];
        }
    }

    /**
     * تحديث قالب
     */
    public function updateTemplate(int $templateId, array $templateData, int $adminId): bool
    {
        try {
            $sql = "UPDATE form_templates 
                    SET template_name = ?, template_description = ?, category = ?, is_public = ?, updated_at = NOW()
                    WHERE id = ? AND created_by = ?";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $templateData['template_name'],
                $templateData['template_description'],
                $templateData['category'],
                $templateData['is_public'] ? 1 : 0,
                $templateId,
                $adminId
            ]);
        } catch (Exception $e) {
            $this->logger->error("Error updating template: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف قالب
     */
    public function deleteTemplate(int $templateId, int $adminId): bool
    {
        try {
            $sql = "DELETE FROM form_templates WHERE id = ? AND created_by = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$templateId, $adminId]);
        } catch (Exception $e) {
            $this->logger->error("Error deleting template: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تصدير قالب
     */
    public function exportTemplate(int $templateId): ?string
    {
        try {
            $template = $this->getTemplate($templateId);
            if (!$template) {
                return null;
            }

            $exportData = [
                'template_name' => $template['template_name'],
                'template_description' => $template['template_description'],
                'category' => $template['category'],
                'form_config' => $template['form_config'],
                'exported_at' => date('Y-m-d H:i:s'),
                'version' => '1.0',
                'system' => 'Employee Evaluation System'
            ];

            return json_encode($exportData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (Exception $e) {
            $this->logger->error("Error exporting template: " . $e->getMessage());
            return null;
        }
    }

    /**
     * استيراد قالب
     */
    public function importTemplate(string $templateJson, int $adminId): ?int
    {
        try {
            $templateData = json_decode($templateJson, true);
            if (!$templateData) {
                throw new Exception("Invalid template JSON");
            }

            $sql = "INSERT INTO form_templates (
                template_name, template_description, form_config, category, is_public, created_by
            ) VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $templateData['template_name'] . ' (مستورد)',
                $templateData['template_description'],
                json_encode($templateData['form_config'], JSON_UNESCAPED_UNICODE),
                $templateData['category'] ?? null,
                0, // استيراد كخاص
                $adminId
            ]);

            return (int) $this->db->lastInsertId();
        } catch (Exception $e) {
            $this->logger->error("Error importing template: " . $e->getMessage());
            return null;
        }
    }

    /**
     * البحث في القوالب
     */
    public function searchTemplates(string $query, ?int $adminId = null, ?string $category = null): array
    {
        try {
            $whereConditions = [];
            $params = [];

            // البحث في الاسم والوصف
            $whereConditions[] = "(ft.template_name LIKE ? OR ft.template_description LIKE ?)";
            $searchTerm = "%{$query}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;

            // فلترة حسب الوصول
            if ($adminId) {
                $whereConditions[] = "(ft.is_public = 1 OR ft.created_by = ?)";
                $params[] = $adminId;
            }

            // فلترة حسب الفئة
            if ($category) {
                $whereConditions[] = "ft.category = ?";
                $params[] = $category;
            }

            $whereClause = "WHERE " . implode(" AND ", $whereConditions);

            $sql = "SELECT ft.*, a.name as creator_name
                    FROM form_templates ft 
                    JOIN admins a ON ft.created_by = a.id 
                    {$whereClause}
                    ORDER BY ft.usage_count DESC, ft.created_at DESC
                    LIMIT 20";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($templates as &$template) {
                $template['form_config'] = json_decode($template['form_config'], true);
            }

            return $templates;
        } catch (Exception $e) {
            $this->logger->error("Error searching templates: " . $e->getMessage());
            return [];
        }
    }

    /**
     * إحصائيات القوالب
     */
    public function getTemplateStats(): array
    {
        try {
            $stats = [];

            // إجمالي القوالب
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM form_templates");
            $stats['total'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // القوالب العامة
            $stmt = $this->db->query("SELECT COUNT(*) as public_count FROM form_templates WHERE is_public = 1");
            $stats['public'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['public_count'];

            // القوالب الأكثر استخداماً
            $stmt = $this->db->query("
                SELECT template_name, category, usage_count 
                FROM form_templates 
                ORDER BY usage_count DESC 
                LIMIT 5
            ");
            $stats['top_templates'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // القوالب حسب الفئة
            $stmt = $this->db->query("
                SELECT category, COUNT(*) as count, SUM(usage_count) as total_usage
                FROM form_templates 
                WHERE category IS NOT NULL 
                GROUP BY category 
                ORDER BY count DESC
            ");
            $stats['by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // القوالب الحديثة
            $stmt = $this->db->query("
                SELECT template_name, category, created_at, usage_count
                FROM form_templates 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stats['recent'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $stats;
        } catch (Exception $e) {
            $this->logger->error("Error getting template stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * تحديد فئة تلقائياً بناءً على عنوان الاستمارة ووصفها
     */
    private function detectCategory(string $title, ?string $description = null): ?string
    {
        $text = strtolower($title . ' ' . ($description ?? ''));
        
        $categories = [
            'hr' => ['موظف', 'شخصي', 'تقييم', 'إنجاز', 'مهام'],
            'finance' => ['مالية', 'ميزانية', 'تكلفة', 'استثمار', 'أرباح'],
            'it' => ['تقنية', 'نظام', 'برمجة', 'كمبيوتر', 'شبكة'],
            'marketing' => ['تسويق', 'عميل', 'مبيعات', 'حملة', 'إعلان'],
            'operations' => ['تشغيلية', 'إنتاج', 'عملية', 'تصنيع'],
            'legal' => ['قانونية', 'عقد', 'اتفاقية', 'ترخيص'],
            'training' => ['تدريب', 'دورة', 'ورشة', 'تطوير'],
            'health' => ['صحة', 'طوارئ', 'سلامة', 'إسعاف']
        ];

        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($text, $keyword) !== false) {
                    return $category;
                }
            }
        }

        return 'general';
    }

    /**
     * نسخ قالب
     */
    public function duplicateTemplate(int $templateId, int $adminId): ?int
    {
        try {
            $template = $this->getTemplate($templateId);
            if (!$template) {
                return null;
            }

            $sql = "INSERT INTO form_templates (
                template_name, template_description, form_config, category, is_public, created_by
            ) VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $template['template_name'] . ' (نسخة)',
                $template['template_description'],
                json_encode($template['form_config'], JSON_UNESCAPED_UNICODE),
                $template['category'],
                0, // النسخة تكون خاصة
                $adminId
            ]);

            return (int) $this->db->lastInsertId();
        } catch (Exception $e) {
            $this->logger->error("Error duplicating template: " . $e->getMessage());
            return null;
        }
    }
}