<?php

declare(strict_types=1);

namespace BuraqForms\Core\Services;

// Load Logger class directly to avoid autoloading issues
require_once __DIR__ . '/../Logger.php';

use BuraqForms\Core\Database;
use PDO;
use Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

/**
 * خدمة إدارة التقارير المتقدمة
 */
class ReportService
{
    private PDO $db;
    private Logger $logger;

    public function __construct(Database $database = null, Logger $logger = null)
    {
        $this->db = $database?->getConnection() ?? Database::getConnection();
        $this->logger = $logger ?? new Logger();
    }

    /**
     * إنشاء تقرير مخصص
     */
    public function createCustomReport(array $reportData): ?int
    {
        try {
            $sql = "INSERT INTO custom_reports (name, description, report_config, report_type, created_by, is_shared, schedule_type) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $reportData['name'],
                $reportData['description'] ?? null,
                json_encode($reportData['config'], JSON_UNESCAPED_UNICODE),
                $reportData['type'],
                $reportData['created_by'],
                $reportData['is_shared'] ?? 0,
                $reportData['schedule_type'] ?? 'none'
            ]);

            return (int) $this->db->lastInsertId();
        } catch (Exception $e) {
            $this->logger->error("Error creating custom report: " . $e->getMessage());
            return null;
        }
    }

    /**
     * جلب تقرير مخصص بالـ ID
     */
    public function getCustomReport(int $reportId): ?array
    {
        try {
            $sql = "SELECT cr.*, a.name as creator_name 
                    FROM custom_reports cr 
                    JOIN admins a ON cr.created_by = a.id 
                    WHERE cr.id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$reportId]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($report) {
                $report['report_config'] = json_decode($report['report_config'], true);
            }

            return $report;
        } catch (Exception $e) {
            $this->logger->error("Error getting custom report: " . $e->getMessage());
            return null;
        }
    }

    /**
     * تنفيذ تقرير مخصص
     */
    public function executeCustomReport(array $config): array
    {
        try {
            $type = $config['type'] ?? 'submissions';
            
            switch ($type) {
                case 'submissions':
                    return $this->generateSubmissionsReport($config);
                case 'departments':
                    return $this->generateDepartmentsReport($config);
                case 'forms':
                    return $this->generateFormsReport($config);
                case 'analytics':
                    return $this->generateAnalyticsReport($config);
                default:
                    throw new Exception("Invalid report type: " . $type);
            }
        } catch (Exception $e) {
            $this->logger->error("Error executing custom report: " . $e->getMessage());
            return [];
        }
    }

    /**
     * إنشاء تقرير الإجابات
     */
    private function generateSubmissionsReport(array $config): array
    {
        $whereConditions = ['1=1'];
        $params = [];

        // إضافة الفلاتر
        if (!empty($config['form_id'])) {
            $whereConditions[] = "fs.form_id = ?";
            $params[] = $config['form_id'];
        }

        if (!empty($config['department_id'])) {
            $whereConditions[] = "fs.department_id = ?";
            $params[] = $config['department_id'];
        }

        if (!empty($config['status'])) {
            $whereConditions[] = "fs.status = ?";
            $params[] = $config['status'];
        }

        if (!empty($config['date_from'])) {
            $whereConditions[] = "fs.submitted_at >= ?";
            $params[] = $config['date_from'];
        }

        if (!empty($config['date_to'])) {
            $whereConditions[] = "fs.submitted_at <= ?";
            $params[] = $config['date_to'];
        }

        $sql = "SELECT 
                    f.title as form_title,
                    d.name as department_name,
                    fs.reference_code,
                    fs.submitter_name,
                    fs.submitter_email,
                    fs.status,
                    fs.submitted_at,
                    fs.updated_at,
                    COUNT(sa.id) as total_answers,
                    COUNT(CASE WHEN sa.file_path IS NOT NULL THEN 1 END) as files_count
                FROM form_submissions fs
                JOIN forms f ON fs.form_id = f.id
                LEFT JOIN departments d ON fs.department_id = d.id
                LEFT JOIN submission_answers sa ON fs.id = sa.submission_id
                WHERE " . implode(' AND ', $whereConditions) . "
                GROUP BY fs.id
                ORDER BY fs.submitted_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * إنشاء تقرير الإدارات
     */
    private function generateDepartmentsReport(array $config): array
    {
        $whereConditions = ['1=1'];
        $params = [];

        if (!empty($config['date_from'])) {
            $whereConditions[] = "fs.submitted_at >= ?";
            $params[] = $config['date_from'];
        }

        if (!empty($config['date_to'])) {
            $whereConditions[] = "fs.submitted_at <= ?";
            $params[] = $config['date_to'];
        }

        $sql = "SELECT 
                    d.name as department_name,
                    d.description,
                    COUNT(fs.id) as total_submissions,
                    COUNT(CASE WHEN fs.status = 'completed' THEN 1 END) as completed_submissions,
                    COUNT(CASE WHEN fs.status = 'pending' THEN 1 END) as pending_submissions,
                    COUNT(DISTINCT fs.form_id) as forms_used,
                    MIN(fs.submitted_at) as first_submission,
                    MAX(fs.submitted_at) as last_submission,
                    AVG(DATEDIFF(fs.updated_at, fs.submitted_at)) as avg_processing_days
                FROM departments d
                LEFT JOIN form_submissions fs ON d.id = fs.department_id AND " . implode(' AND ', $whereConditions) . "
                GROUP BY d.id
                ORDER BY total_submissions DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * إنشاء تقرير الاستمارات
     */
    private function generateFormsReport(array $config): array
    {
        $whereConditions = ['1=1'];
        $params = [];

        if (!empty($config['department_id'])) {
            $whereConditions[] = "f.department_id = ?";
            $params[] = $config['department_id'];
        }

        if (!empty($config['status'])) {
            $whereConditions[] = "f.status = ?";
            $params[] = $config['status'];
        }

        $sql = "SELECT 
                    f.title,
                    f.description,
                    d.name as department_name,
                    f.status,
                    f.created_at,
                    COUNT(fs.id) as total_submissions,
                    COUNT(CASE WHEN fs.status = 'completed' THEN 1 END) as completed_submissions,
                    COUNT(CASE WHEN fs.status = 'pending' THEN 1 END) as pending_submissions,
                    COUNT(DISTINCT sa.field_id) as fields_count,
                    AVG(CHAR_LENGTH(GROUP_CONCAT(sa.answer SEPARATOR ''))) as avg_content_length
                FROM forms f
                LEFT JOIN departments d ON f.department_id = d.id
                LEFT JOIN form_submissions fs ON f.id = fs.form_id
                LEFT JOIN submission_answers sa ON fs.id = sa.submission_id
                WHERE " . implode(' AND ', $whereConditions) . "
                GROUP BY f.id
                ORDER BY total_submissions DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * إنشاء تقرير التحليلات
     */
    private function generateAnalyticsReport(array $config): array
    {
        $analytics = [];

        // إحصائيات عامة
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM forms) as total_forms,
                    (SELECT COUNT(*) FROM form_submissions) as total_submissions,
                    (SELECT COUNT(*) FROM form_submissions WHERE status = 'pending') as pending_submissions,
                    (SELECT COUNT(*) FROM form_submissions WHERE status = 'completed') as completed_submissions,
                    (SELECT COUNT(*) FROM departments) as total_departments,
                    (SELECT COUNT(*) FROM admins) as total_admins";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analytics['general'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // الإجابات حسب اليوم (آخر 30 يوم)
        $sql = "SELECT 
                    DATE(submitted_at) as date,
                    COUNT(*) as submissions,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending
                FROM form_submissions 
                WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(submitted_at)
                ORDER BY date";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analytics['daily_trends'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // أفضل الاستمارات
        $sql = "SELECT 
                    f.title,
                    COUNT(fs.id) as submission_count,
                    AVG(TIMESTAMPDIFF(DAY, fs.submitted_at, fs.updated_at)) as avg_processing_days
                FROM forms f
                LEFT JOIN form_submissions fs ON f.id = fs.form_id
                GROUP BY f.id
                ORDER BY submission_count DESC
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analytics['top_forms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // أفضل الإدارات
        $sql = "SELECT 
                    d.name as department_name,
                    COUNT(fs.id) as submission_count,
                    COUNT(DISTINCT f.id) as forms_count
                FROM departments d
                LEFT JOIN forms f ON d.id = f.department_id
                LEFT JOIN form_submissions fs ON f.id = fs.form_id
                GROUP BY d.id
                ORDER BY submission_count DESC
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analytics['top_departments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // توزيع الحالات
        $sql = "SELECT 
                    status,
                    COUNT(*) as count,
                    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM form_submissions), 2) as percentage
                FROM form_submissions
                GROUP BY status";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $analytics['status_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $analytics;
    }

    /**
     * تصدير التقرير إلى Excel
     */
    public function exportToExcel(array $data, array $headers, string $filename): ?string
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Report');

            // إضافة العناوين
            $sheet->fromArray($headers, null, 'A1');

            // إضافة البيانات
            $sheet->fromArray($data, null, 'A2');

            // تنسيق الجدول
            $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
            $sheet->getStyle('A1:' . $sheet->getHighestColumn() . $sheet->getHighestRow())->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]
                ]
            ]);

            // حفظ الملف
            $filename = $filename . '_' . date('Y-m-d_H-i-s') . '.xlsx';
            $filepath = __DIR__ . '/../../../storage/exports/' . $filename;

            $writer = new Xlsx($spreadsheet);
            $writer->save($filepath);

            return $filepath;
        } catch (Exception $e) {
            $this->logger->error("Error exporting to Excel: " . $e->getMessage());
            return null;
        }
    }

    /**
     * تصدير التقرير إلى CSV
     */
    public function exportToCsv(array $data, array $headers, string $filename): ?string
    {
        try {
            $filename = $filename . '_' . date('Y-m-d_H-i-s') . '.csv';
            $filepath = __DIR__ . '/../../../storage/exports/' . $filename;

            $file = fopen($filepath, 'w');
            
            // إضافة UTF-8 BOM
            fwrite($file, "\xEF\xBB\xBF");
            
            // إضافة العناوين
            fputcsv($file, $headers);
            
            // إضافة البيانات
            foreach ($data as $row) {
                fputcsv($file, array_values($row));
            }
            
            fclose($file);

            return $filepath;
        } catch (Exception $e) {
            $this->logger->error("Error exporting to CSV: " . $e->getMessage());
            return null;
        }
    }

    /**
     * إنشاء تقرير PDF (يحتاج TCPDF)
     */
    public function exportToPdf(array $data, array $headers, string $filename): ?string
    {
        try {
            // هنا يمكن إضافة تنفيذ TCPDF لإنشاء PDF
            // حالياً نعيد null كعلامة على عدم التنفيذ
            $this->logger->warning("PDF export not implemented yet");
            return null;
        } catch (Exception $e) {
            $this->logger->error("Error exporting to PDF: " . $e->getMessage());
            return null;
        }
    }

    /**
     * جدولة التقارير التلقائية
     */
    public function scheduleReport(int $reportId, string $scheduleType): bool
    {
        try {
            $sql = "UPDATE custom_reports SET schedule_type = ?, last_generated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$scheduleType, $reportId]);
        } catch (Exception $e) {
            $this->logger->error("Error scheduling report: " . $e->getMessage());
            return false;
        }
    }

    /**
     * جلب التقارير المجدولة
     */
    public function getScheduledReports(): array
    {
        try {
            $sql = "SELECT cr.*, a.name as creator_name 
                    FROM custom_reports cr 
                    JOIN admins a ON cr.created_by = a.id 
                    WHERE cr.schedule_type != 'none'
                    ORDER BY cr.last_generated_at ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error getting scheduled reports: " . $e->getMessage());
            return [];
        }
    }

    /**
     * جلب جميع التقارير المخصصة
     */
    public function getAllCustomReports(int $adminId): array
    {
        try {
            $sql = "SELECT cr.*, a.name as creator_name,
                           CASE WHEN cr.created_by = ? THEN 1 ELSE cr.is_shared END as can_access
                    FROM custom_reports cr 
                    JOIN admins a ON cr.created_by = a.id 
                    WHERE cr.created_by = ? OR cr.is_shared = 1
                    ORDER BY cr.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$adminId, $adminId]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($reports as &$report) {
                $report['report_config'] = json_decode($report['report_config'], true);
            }

            return $reports;
        } catch (Exception $e) {
            $this->logger->error("Error getting all custom reports: " . $e->getMessage());
            return [];
        }
    }
}