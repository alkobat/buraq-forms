<?php

declare(strict_types=1);

namespace BuraqForms\Core\Services;

// Load Logger class directly to avoid autoloading issues
require_once __DIR__ . '/../Logger.php';

use BuraqForms\Core\Cache\FileCache;
use BuraqForms\Core\Exceptions\DatabaseException;
use BuraqForms\Core\Exceptions\NotFoundException;
use BuraqForms\Core\Exceptions\ServiceException;
use BuraqForms\Core\Logger;
use PDO;
use PDOException;

use function ees_slugify;

/**
 * CRUD operations for forms.
 */
class FormService
{
    private PDO $pdo;
    private Logger $logger;
    private ?FileCache $cache;

    private ?bool $supportsDepartmentLinking = null;

    public function __construct(PDO $pdo, ?Logger $logger = null, ?FileCache $cache = null)
    {
        $this->pdo = $pdo;
        $this->logger = $logger ?? new Logger();
        $this->cache = $cache;
    }

    /**
     * @param array{title:string,description?:string|null,created_by:int,status?:'active'|'inactive',allow_multiple_submissions?:bool|int,show_department_field?:bool|int,slug?:string|null} $data
     * @param list<int> $departmentIds
     * @return array<string, mixed>
     */
    public function create(array $data, array $departmentIds = []): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new ServiceException('Form title is required.');
        }

        $createdBy = (int) ($data['created_by'] ?? 0);
        if ($createdBy <= 0) {
            throw new ServiceException('created_by is required.');
        }

        $slugBase = (string) ($data['slug'] ?? '');
        $slugBase = $slugBase !== '' ? $slugBase : ees_slugify($title);
        $slug = $this->generateUniqueSlug($slugBase);

        $status = $data['status'] ?? 'active';
        $allowMultiple = (int) (bool) ($data['allow_multiple_submissions'] ?? true);
        $showDepartmentField = (int) (bool) ($data['show_department_field'] ?? true);

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO forms (title, description, slug, created_by, status, allow_multiple_submissions, show_department_field, created_at, updated_at)\n'
                . 'VALUES (:title, :description, :slug, :created_by, :status, :allow_multiple_submissions, :show_department_field, :created_at, :updated_at)'
            );
            $stmt->execute([
                'title' => $title,
                'description' => $data['description'] ?? null,
                'slug' => $slug,
                'created_by' => $createdBy,
                'status' => $status,
                'allow_multiple_submissions' => $allowMultiple,
                'show_department_field' => $showDepartmentField,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $formId = (int) $this->pdo->lastInsertId();

            if ($departmentIds !== []) {
                $this->assignDepartments($formId, $departmentIds);
            }
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to create form: ' . $e->getMessage(), 0, $e);
        }

        $this->logger->info('Form created', ['form_id' => $formId, 'slug' => $slug]);

        $form = $this->getById($formId);
        $this->invalidateCacheForForm($form);

        return $form;
    }

    /**
     * @return array<string, mixed>
     */
    public function getById(int $id): array
    {
        $cacheKey = 'form:id:' . $id;
        if ($this->cache !== null) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM forms WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $form = $stmt->fetch();
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to load form by id: ' . $id, 0, $e);
        }

        if (!is_array($form)) {
            throw new NotFoundException('Form not found: ' . $id);
        }

        $form['departments'] = $this->supportsDepartmentLinking() ? $this->getDepartments($id) : [];

        if ($this->cache !== null) {
            $this->cache->set($cacheKey, $form, 300);
            $this->cache->set('form:slug:' . (string) $form['slug'], $form, 300);
        }

        return $form;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBySlug(string $slug): array
    {
        $slug = trim($slug);
        if ($slug === '') {
            throw new ServiceException('Slug is required.');
        }

        $cacheKey = 'form:slug:' . $slug;
        if ($this->cache !== null) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM forms WHERE slug = :slug');
            $stmt->execute(['slug' => $slug]);
            $form = $stmt->fetch();
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to load form by slug: ' . $slug, 0, $e);
        }

        if (!is_array($form)) {
            throw new NotFoundException('Form not found: ' . $slug);
        }

        $form['departments'] = $this->supportsDepartmentLinking() ? $this->getDepartments((int) $form['id']) : [];

        if ($this->cache !== null) {
            $this->cache->set($cacheKey, $form, 300);
            $this->cache->set('form:id:' . (int) $form['id'], $form, 300);
        }

        return $form;
    }

    /**
     * @param array{title?:string,description?:string|null,status?:'active'|'inactive',allow_multiple_submissions?:bool|int,show_department_field?:bool|int} $data
     * @param list<int>|null $departmentIds
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, ?array $departmentIds = null): array
    {
        $form = $this->getById($id);

        $title = array_key_exists('title', $data) ? trim((string) $data['title']) : (string) $form['title'];
        if ($title === '') {
            throw new ServiceException('Form title cannot be empty.');
        }

        $description = array_key_exists('description', $data) ? $data['description'] : $form['description'];
        $status = array_key_exists('status', $data) ? $data['status'] : $form['status'];
        $allowMultiple = array_key_exists('allow_multiple_submissions', $data) ? (int) (bool) $data['allow_multiple_submissions'] : (int) $form['allow_multiple_submissions'];
        $showDepartmentField = array_key_exists('show_department_field', $data) ? (int) (bool) $data['show_department_field'] : (int) $form['show_department_field'];

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE forms SET title = :title, description = :description, status = :status, allow_multiple_submissions = :allow_multiple_submissions, show_department_field = :show_department_field, updated_at = :updated_at WHERE id = :id'
            );

            $stmt->execute([
                'id' => $id,
                'title' => $title,
                'description' => $description,
                'status' => $status,
                'allow_multiple_submissions' => $allowMultiple,
                'show_department_field' => $showDepartmentField,
                'updated_at' => $now,
            ]);

            if ($departmentIds !== null) {
                $this->assignDepartments($id, $departmentIds);
            }
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to update form: ' . $id, 0, $e);
        }

        $this->logger->info('Form updated', ['form_id' => $id]);

        $updated = $this->getById($id);
        $this->invalidateCacheForForm($updated);

        return $updated;
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new ServiceException('Invalid status: ' . $status);
        }

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        try {
            $stmt = $this->pdo->prepare('UPDATE forms SET status = :status, updated_at = :updated_at WHERE id = :id');
            $stmt->execute(['id' => $id, 'status' => $status, 'updated_at' => $now]);

            if ($stmt->rowCount() === 0) {
                throw new NotFoundException('Form not found: ' . $id);
            }
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to update form status: ' . $id, 0, $e);
        }

        $this->logger->info('Form status changed', ['form_id' => $id, 'status' => $status]);
        $this->invalidateCacheForForm(['id' => $id, 'slug' => null]);
    }

    public function delete(int $id): void
    {
        $form = $this->getById($id);

        try {
            $stmt = $this->pdo->prepare('DELETE FROM forms WHERE id = :id');
            $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to delete form: ' . $id, 0, $e);
        }

        $this->logger->info('Form deleted', ['form_id' => $id]);
        $this->invalidateCacheForForm($form);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $status = null): array
    {
        $query = 'SELECT * FROM forms';
        $params = [];

        if ($status !== null) {
            $query .= ' WHERE status = :status';
            $params['status'] = $status;
        }

        $query .= ' ORDER BY created_at DESC, id DESC';

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to list forms.', 0, $e);
        }

        if (!$this->supportsDepartmentLinking()) {
            return array_map(static function ($row) {
                $row['departments'] = [];
                return $row;
            }, is_array($rows) ? $rows : []);
        }

        return array_map(function ($row) {
            $row['departments'] = $this->getDepartments((int) $row['id']);
            return $row;
        }, is_array($rows) ? $rows : []);
    }

    /**
     * @param list<int> $departmentIds
     */
    public function assignDepartments(int $formId, array $departmentIds): void
    {
        if (!$this->supportsDepartmentLinking()) {
            throw new ServiceException('Department linking is not supported because form_departments table is missing.');
        }

        $departmentIds = array_values(array_unique(array_map('intval', $departmentIds)));

        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare('DELETE FROM form_departments WHERE form_id = :form_id')->execute(['form_id' => $formId]);

            if ($departmentIds !== []) {
                $stmt = $this->pdo->prepare('INSERT INTO form_departments (form_id, department_id, created_at) VALUES (:form_id, :department_id, :created_at)');
                $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

                foreach ($departmentIds as $departmentId) {
                    $stmt->execute(['form_id' => $formId, 'department_id' => $departmentId, 'created_at' => $now]);
                }
            }

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new DatabaseException('Failed to assign departments to form: ' . $formId, 0, $e);
        }

        $this->logger->info('Form departments updated', ['form_id' => $formId, 'department_ids' => $departmentIds]);
        $this->invalidateCacheForForm(['id' => $formId, 'slug' => null]);
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    public function getDepartments(int $formId): array
    {
        if (!$this->supportsDepartmentLinking()) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT d.id, d.name\n'
                . 'FROM form_departments fd\n'
                . 'JOIN departments d ON d.id = fd.department_id\n'
                . 'WHERE fd.form_id = :form_id\n'
                . 'ORDER BY d.name ASC'
            );
            $stmt->execute(['form_id' => $formId]);
            $rows = $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to load departments for form: ' . $formId, 0, $e);
        }

        return array_map(static fn ($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name']], is_array($rows) ? $rows : []);
    }

    private function invalidateCacheForForm(array $form): void
    {
        if ($this->cache === null) {
            return;
        }

        if (isset($form['id'])) {
            $this->cache->delete('form:id:' . (int) $form['id']);
        }

        if (isset($form['slug']) && is_string($form['slug']) && $form['slug'] !== '') {
            $this->cache->delete('form:slug:' . $form['slug']);
        }
    }

    private function generateUniqueSlug(string $base): string
    {
        $base = trim($base);
        $base = $base !== '' ? $base : 'form';

        $candidate = $base;
        $suffix = 2;

        while ($this->slugExists($candidate)) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function slugExists(string $slug): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM forms WHERE slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $slug]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to check slug uniqueness.', 0, $e);
        }
    }

    private function supportsDepartmentLinking(): bool
    {
        if ($this->supportsDepartmentLinking !== null) {
            return $this->supportsDepartmentLinking;
        }

        try {
            $this->pdo->query('SELECT 1 FROM form_departments LIMIT 1');
            $this->supportsDepartmentLinking = true;
        } catch (PDOException) {
            $this->supportsDepartmentLinking = false;
        }

        return $this->supportsDepartmentLinking;
    }
}
