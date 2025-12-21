<?php

declare(strict_types=1);

namespace BuraqForms\Core\Services;

// Load Logger class directly to avoid autoloading issues
require_once __DIR__ . '/../Logger.php';

use BuraqForms\Core\Exceptions\DatabaseException;
use BuraqForms\Core\Exceptions\NotFoundException;
use BuraqForms\Core\Exceptions\ServiceException;
use BuraqForms\Core\Logger;
use PDO;
use PDOException;

/**
 * Service for managing departments
 */
class DepartmentService
{
    private PDO $pdo;
    private Logger $logger;

    public function __construct(PDO $pdo, ?Logger $logger = null)
    {
        $this->pdo = $pdo;
        $this->logger = $logger ?? new Logger();
    }

    /**
     * Create a new department
     */
    public function create(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $managerId = (int) ($data['manager_id'] ?? 0);
        $isActive = (int) (bool) ($data['is_active'] ?? true);

        if ($name === '') {
            throw new ServiceException('اسم الإدارة مطلوب.');
        }

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO departments (name, description, manager_id, is_active, created_at, updated_at) 
                 VALUES (:name, :description, :manager_id, :is_active, :created_at, :updated_at)'
            );
            
            $stmt->execute([
                'name' => $name,
                'description' => $description ?: null,
                'manager_id' => $managerId ?: null,
                'is_active' => $isActive,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $departmentId = (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw new DatabaseException('فشل في إنشاء الإدارة: ' . $e->getMessage(), 0, $e);
        }

        $this->logger->info('تم إنشاء إدارة جديدة', [
            'department_id' => $departmentId, 
            'name' => $name
        ]);

        return $this->getById($departmentId);
    }

    /**
     * Get department by ID
     */
    public function getById(int $id): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM departments WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $department = $stmt->fetch();
        } catch (PDOException $e) {
            throw new DatabaseException('فشل في تحميل الإدارة: ' . $id, 0, $e);
        }

        if (!$department) {
            throw new NotFoundException('الإدارة غير موجودة: ' . $id);
        }

        // Get manager info if exists
        if ($department['manager_id']) {
            $department['manager'] = $this->getManagerInfo((int) $department['manager_id']);
        }

        return $department;
    }

    /**
     * Update department
     */
    public function update(int $id, array $data): array
    {
        $department = $this->getById($id);

        $name = array_key_exists('name', $data) ? trim((string) $data['name']) : (string) $department['name'];
        $description = array_key_exists('description', $data) ? trim((string) $data['description']) : (string) $department['description'];
        $managerId = array_key_exists('manager_id', $data) ? (int) $data['manager_id'] : (int) $department['manager_id'];
        $isActive = array_key_exists('is_active', $data) ? (int) (bool) $data['is_active'] : (int) $department['is_active'];

        if ($name === '') {
            throw new ServiceException('اسم الإدارة لا يمكن أن يكون فارغاً.');
        }

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE departments SET 
                 name = :name, 
                 description = :description, 
                 manager_id = :manager_id, 
                 is_active = :is_active, 
                 updated_at = :updated_at 
                 WHERE id = :id'
            );

            $stmt->execute([
                'id' => $id,
                'name' => $name,
                'description' => $description ?: null,
                'manager_id' => $managerId ?: null,
                'is_active' => $isActive,
                'updated_at' => $now,
            ]);
        } catch (PDOException $e) {
            throw new DatabaseException('فشل في تحديث الإدارة: ' . $id, 0, $e);
        }

        $this->logger->info('تم تحديث الإدارة', [
            'department_id' => $id, 
            'name' => $name
        ]);

        return $this->getById($id);
    }

    /**
     * Set department status
     */
    public function setStatus(int $id, bool $isActive): void
    {
        if (!$this->departmentExists($id)) {
            throw new NotFoundException('الإدارة غير موجودة: ' . $id);
        }

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE departments SET is_active = :is_active, updated_at = :updated_at WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'is_active' => (int) $isActive,
                'updated_at' => $now,
            ]);
        } catch (PDOException $e) {
            throw new DatabaseException('فشل في تحديث حالة الإدارة: ' . $id, 0, $e);
        }

        $this->logger->info('تم تغيير حالة الإدارة', [
            'department_id' => $id, 
            'is_active' => $isActive
        ]);
    }

    /**
     * Delete department (safe delete with warning if has related data)
     */
    public function delete(int $id): void
    {
        $department = $this->getById($id);

        // Check for related data
        $relatedData = $this->checkRelatedData($id);
        if ($relatedData['has_data']) {
            throw new ServiceException(
                'لا يمكن حذف الإدارة "' . $department['name'] . '" لأنها تحتوي على بيانات مرتبطة: ' .
                $relatedData['message']
            );
        }

        try {
            $stmt = $this->pdo->prepare('DELETE FROM departments WHERE id = :id');
            $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            throw new DatabaseException('فشل في حذف الإدارة: ' . $id, 0, $e);
        }

        $this->logger->info('تم حذف الإدارة', ['department_id' => $id, 'name' => $department['name']]);
    }

    /**
     * List all departments
     */
    public function list(?bool $isActive = null): array
    {
        $query = 'SELECT d.*, 
                         u.name as manager_name,
                         u.email as manager_email
                  FROM departments d
                  LEFT JOIN users u ON d.manager_id = u.id';
        
        $params = [];
        if ($isActive !== null) {
            $query .= ' WHERE d.is_active = :is_active';
            $params['is_active'] = (int) $isActive;
        }
        
        $query .= ' ORDER BY d.name ASC';

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $departments = $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new DatabaseException('فشل في قائمة الإدارات.', 0, $e);
        }

        return is_array($departments) ? $departments : [];
    }

    /**
     * Get active departments for dropdown
     */
    public function getActiveDepartments(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC'
            );
            $stmt->execute();
            $departments = $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new DatabaseException('فشل في تحميل الإدارات النشطة.', 0, $e);
        }

        return is_array($departments) ? $departments : [];
    }

    /**
     * Get managers list (users who can be managers)
     */
    public function getManagersList(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, name, email FROM users WHERE role IN ("admin", "manager") AND is_active = 1 ORDER BY name ASC'
            );
            $stmt->execute();
            $managers = $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new DatabaseException('فشل في تحميل قائمة المدراء.', 0, $e);
        }

        return is_array($managers) ? $managers : [];
    }

    /**
     * Check if department exists
     */
    private function departmentExists(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM departments WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Check for related data before deletion
     */
    private function checkRelatedData(int $id): array
    {
        $hasData = false;
        $messages = [];

        // Check forms
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM form_departments WHERE department_id = :id');
            $stmt->execute(['id' => $id]);
            $formsCount = (int) $stmt->fetchColumn();
            
            if ($formsCount > 0) {
                $hasData = true;
                $messages[] = $formsCount . ' استمارات مرتبطة';
            }
        } catch (PDOException $e) {
            // Ignore error, forms table might not exist
        }

        // Check submissions
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM form_submissions WHERE department_id = :id');
            $stmt->execute(['id' => $id]);
            $submissionsCount = (int) $stmt->fetchColumn();
            
            if ($submissionsCount > 0) {
                $hasData = true;
                $messages[] = $submissionsCount . ' إجابات استمارات';
            }
        } catch (PDOException $e) {
            // Ignore error, submissions table might not exist
        }

        // Check users
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE department_id = :id');
            $stmt->execute(['id' => $id]);
            $usersCount = (int) $stmt->fetchColumn();
            
            if ($usersCount > 0) {
                $hasData = true;
                $messages[] = $usersCount . ' موظفين';
            }
        } catch (PDOException $e) {
            // Ignore error, users table might not exist
        }

        return [
            'has_data' => $hasData,
            'message' => implode('، ', $messages)
        ];
    }

    /**
     * Get manager information
     */
    private function getManagerInfo(int $managerId): ?array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT id, name, email FROM users WHERE id = :id');
            $stmt->execute(['id' => $managerId]);
            $manager = $stmt->fetch();
            
            return $manager ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }
}