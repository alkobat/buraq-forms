<?php

declare(strict_types=1);

namespace EmployeeEvaluationSystem\Core\Services;

use EmployeeEvaluationSystem\Core\Database;
use EmployeeEvaluationSystem\Core\Logger;
use PDO;
use Exception;

/**
 * خدمة النسخ الاحتياطية وإدارة النظام
 */
class BackupService
{
    private PDO $db;
    private Logger $logger;
    private string $backupDir;

    public function __construct(Database $database = null, Logger $logger = null)
    {
        $this->db = $database?->getConnection() ?? Database::getConnection();
        $this->logger = $logger ?? new Logger();
        $this->backupDir = __DIR__ . '/../../../storage/backups/';
        
        // إنشاء مجلد النسخ الاحتياطية إذا لم يكن موجوداً
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * إنشاء نسخة احتياطية كاملة
     */
    public function createFullBackup(int $adminId): ?int
    {
        try {
            $this->db->beginTransaction();
            $backupName = 'full_backup_' . date('Y-m-d_H-i-s');
            
            // إنشاء سجل النسخة الاحتياطية
            $sql = "INSERT INTO system_backups (
                backup_name, backup_type, backup_file_path, file_size, record_count, status, created_by
            ) VALUES (?, ?, ?, ?, ?, 'in_progress', ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$backupName, 'full', '', 0, 0, $adminId]);
            $backupId = (int) $this->db->lastInsertId();

            // إنشاء ملف SQL للنسخة الاحتياطية
            $backupFile = $this->backupDir . $backupName . '.sql';
            $this->generateSqlBackup($backupFile);

            // حساب حجم الملف وعدد السجلات
            $fileSize = filesize($backupFile);
            $recordCount = $this->getTotalRecordCount();

            // تحديث بيانات النسخة الاحتياطية
            $sql = "UPDATE system_backups 
                    SET backup_file_path = ?, file_size = ?, record_count = ?, status = 'completed'
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$backupFile, $fileSize, $recordCount, $backupId]);

            $this->db->commit();
            $this->logger->info("Full backup created successfully: " . $backupName);
            
            return $backupId;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Error creating full backup: " . $e->getMessage());
            return null;
        }
    }

    /**
     * إنشاء نسخة احتياطية للبيانات فقط
     */
    public function createDataBackup(int $adminId, array $tables = []): ?int
    {
        try {
            $this->db->beginTransaction();
            $backupName = 'data_backup_' . date('Y-m-d_H-i-s');
            
            $sql = "INSERT INTO system_backups (
                backup_name, backup_type, backup_file_path, file_size, record_count, status, created_by
            ) VALUES (?, ?, ?, ?, ?, 'in_progress', ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$backupName, 'data', '', 0, 0, $adminId]);
            $backupId = (int) $this->db->lastInsertId();

            $backupFile = $this->backupDir . $backupName . '.sql';
            $this->generateDataOnlyBackup($backupFile, $tables);

            $fileSize = filesize($backupFile);
            $recordCount = $this->getTotalRecordCount($tables);

            $sql = "UPDATE system_backups 
                    SET backup_file_path = ?, file_size = ?, record_count = ?, status = 'completed'
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$backupFile, $fileSize, $recordCount, $backupId]);

            $this->db->commit();
            $this->logger->info("Data backup created successfully: " . $backupName);
            
            return $backupId;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Error creating data backup: " . $e->getMessage());
            return null;
        }
    }

    /**
     * استعادة نسخة احتياطية
     */
    public function restoreBackup(int $backupId, int $adminId): bool
    {
        try {
            // جلب بيانات النسخة الاحتياطية
            $sql = "SELECT * FROM system_backups WHERE id = ? AND status = 'completed'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$backupId]);
            $backup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$backup || !file_exists($backup['backup_file_path'])) {
                throw new Exception("Backup file not found");
            }

            // قراءة ملف النسخة الاحتياطية
            $sqlContent = file_get_contents($backup['backup_file_path']);
            if (!$sqlContent) {
                throw new Exception("Cannot read backup file");
            }

            $this->db->beginTransaction();

            // تنفيذ SQL
            $statements = explode(';', $sqlContent);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $this->db->exec($statement);
                }
            }

            $this->db->commit();

            // تسجيل عملية الاستعادة
            $this->logger->info("Backup restored successfully: " . $backup['backup_name']);

            // تسجيل في سجل الأنشطة
            $auditService = new AuditService($this->db, $this->logger);
            $auditService->logActivity($adminId, 'restore', 'backup', $backupId, null, [
                'backup_name' => $backup['backup_name'],
                'backup_type' => $backup['backup_type']
            ]);

            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logger->error("Error restoring backup: " . $e->getMessage());
            return false;
        }
    }

    /**
     * جلب جميع النسخ الاحتياطية
     */
    public function getAllBackups(): array
    {
        try {
            $sql = "SELECT sb.*, a.name as creator_name
                    FROM system_backups sb
                    JOIN admins a ON sb.created_by = a.id
                    ORDER BY sb.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error getting all backups: " . $e->getMessage());
            return [];
        }
    }

    /**
     * حذف نسخة احتياطية
     */
    public function deleteBackup(int $backupId, int $adminId): bool
    {
        try {
            // جلب بيانات النسخة
            $sql = "SELECT * FROM system_backups WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$backupId]);
            $backup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$backup) {
                return false;
            }

            // حذف الملف الفيزيائي
            if (file_exists($backup['backup_file_path'])) {
                unlink($backup['backup_file_path']);
            }

            // حذف من قاعدة البيانات
            $sql = "DELETE FROM system_backups WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$backupId]);

            // تسجيل العملية
            $auditService = new AuditService($this->db, $this->logger);
            $auditService->logActivity($adminId, 'delete', 'backup', $backupId, $backup);

            return $result;
        } catch (Exception $e) {
            $this->logger->error("Error deleting backup: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تنظيف النسخ القديمة
     */
    public function cleanOldBackups(int $retentionDays = 30): int
    {
        try {
            $sql = "SELECT backup_file_path FROM system_backups 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$retentionDays]);
            $oldBackups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $deletedCount = 0;

            foreach ($oldBackups as $backup) {
                // حذف الملف الفيزيائي
                if (file_exists($backup['backup_file_path'])) {
                    unlink($backup['backup_file_path']);
                }
            }

            // حذف من قاعدة البيانات
            $sql = "DELETE FROM system_backups 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$retentionDays]);
            $deletedCount = $stmt->rowCount();

            $this->logger->info("Cleaned old backups: " . $deletedCount);
            return $deletedCount;
        } catch (Exception $e) {
            $this->logger->error("Error cleaning old backups: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * إنشاء ملف SQL للنسخة الاحتياطية
     */
    private function generateSqlBackup(string $filePath): void
    {
        $tables = [
            'forms', 'form_fields', 'form_submissions', 'submission_answers',
            'departments', 'admins', 'saved_filters', 'notifications', 
            'comments', 'custom_reports', 'form_templates', 'system_backups'
        ];

        $sql = "-- Employee Evaluation System Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($tables as $table) {
            // DROP TABLE statement
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            
            // CREATE TABLE statement
            $createStmt = $this->db->query("SHOW CREATE TABLE `{$table}`");
            $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
            $sql .= $createRow['Create Table'] . ";\n\n";

            // INSERT statements
            $selectStmt = $this->db->query("SELECT * FROM `{$table}`");
            $rows = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $sql .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $rowValues = [];
                    foreach ($row as $value) {
                        $rowValues[] = $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                    }
                    $values[] = "(" . implode(', ', $rowValues) . ")";
                }
                
                $sql .= implode(",\n", $values) . ";\n\n";
            }
        }

        file_put_contents($filePath, $sql);
    }

    /**
     * إنشاء نسخة احتياطية للبيانات فقط
     */
    private function generateDataOnlyBackup(string $filePath, array $tables = []): void
    {
        if (empty($tables)) {
            $tables = [
                'forms', 'form_fields', 'form_submissions', 'submission_answers',
                'departments', 'admins', 'saved_filters', 'notifications', 
                'comments', 'custom_reports', 'form_templates'
            ];
        }

        $sql = "-- Employee Evaluation System Data Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($tables as $table) {
            $selectStmt = $this->db->query("SELECT * FROM `{$table}`");
            $rows = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $sql .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $rowValues = [];
                    foreach ($row as $value) {
                        $rowValues[] = $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                    }
                    $values[] = "(" . implode(', ', $rowValues) . ")";
                }
                
                $sql .= implode(",\n", $values) . ";\n\n";
            }
        }

        file_put_contents($filePath, $sql);
    }

    /**
     * جلب عدد السجلات في الجداول
     */
    private function getTotalRecordCount(array $tables = []): int
    {
        if (empty($tables)) {
            $tables = [
                'forms', 'form_fields', 'form_submissions', 'submission_answers',
                'departments', 'admins', 'saved_filters', 'notifications', 
                'comments', 'custom_reports', 'form_templates'
            ];
        }

        $total = 0;
        foreach ($tables as $table) {
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM `{$table}`");
            $total += (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        }

        return $total;
    }

    /**
     * جدولة نسخ احتياطية تلقائية
     */
    public function scheduleAutoBackup(string $type = 'daily'): bool
    {
        try {
            // حفظ الإعداد في جدول الإعدادات المتقدمة
            $sql = "INSERT INTO advanced_settings (setting_category, setting_key, setting_value, setting_type, description)
                    VALUES ('backup', 'auto_backup_schedule', ?, 'string', 'جدولة النسخ الاحتياطية التلقائية')
                    ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value), 
                    updated_at = NOW()";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$type]);
        } catch (Exception $e) {
            $this->logger->error("Error scheduling auto backup: " . $e->getMessage());
            return false;
        }
    }

    /**
     * جلب إحصائيات النسخ الاحتياطية
     */
    public function getBackupStats(): array
    {
        try {
            $stats = [];

            // إجمالي النسخ
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM system_backups");
            $stats['total'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // النسخ الناجحة
            $stmt = $this->db->query("SELECT COUNT(*) as successful FROM system_backups WHERE status = 'completed'");
            $stats['successful'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['successful'];

            // النسخ الفاشلة
            $stmt = $this->db->query("SELECT COUNT(*) as failed FROM system_backups WHERE status = 'failed'");
            $stats['failed'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['failed'];

            // إجمالي الحجم
            $stmt = $this->db->query("SELECT SUM(file_size) as total_size FROM system_backups WHERE status = 'completed'");
            $stats['total_size'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total_size'];

            // النسخ حسب النوع
            $stmt = $this->db->query("
                SELECT backup_type, COUNT(*) as count 
                FROM system_backups 
                GROUP BY backup_type
            ");
            $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // النسخ الحديثة
            $stmt = $this->db->query("
                SELECT backup_name, backup_type, file_size, created_at, status
                FROM system_backups 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stats['recent'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $stats;
        } catch (Exception $e) {
            $this->logger->error("Error getting backup stats: " . $e->getMessage());
            return [];
        }
    }
}