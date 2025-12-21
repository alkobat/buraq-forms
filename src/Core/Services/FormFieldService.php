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

use function ees_normalize_field_options;
use function ees_slugify;
use function ees_transform_field_definitions;

/**
 * Manages form fields (add/update/delete/reorder) and rendering transformations.
 */
class FormFieldService
{
    private PDO $pdo;
    private Logger $logger;

    public function __construct(PDO $pdo, ?Logger $logger = null)
    {
        $this->pdo = $pdo;
        $this->logger = $logger ?? new Logger();
    }

    /**
     * @param array{field_type:string,label:string,placeholder?:string|null,is_required?:bool|int,is_active?:bool|int,field_options?:mixed,source_type?:string,parent_field_id?:int|null,field_key?:string|null,order_index?:int|null,validation_rules?:mixed,helper_text?:string|null} $data
     * @return array<string, mixed>
     */
    public function addField(int $formId, array $data): array
    {
        $fieldType = (string) ($data['field_type'] ?? '');
        $label = trim((string) ($data['label'] ?? ''));

        if ($fieldType === '' || $label === '') {
            throw new ServiceException('field_type and label are required.');
        }

        $parentFieldId = array_key_exists('parent_field_id', $data) ? (int) ($data['parent_field_id'] ?? 0) : 0;
        $parentFieldId = $parentFieldId > 0 ? $parentFieldId : null;

        $fieldKey = trim((string) ($data['field_key'] ?? ''));
        if ($fieldKey === '') {
            $fieldKey = str_replace('-', '_', ees_slugify($label));
        }
        $fieldKey = $this->generateUniqueFieldKey($formId, $fieldKey);

        $orderIndex = $data['order_index'] ?? null;
        if ($orderIndex === null) {
            $orderIndex = $this->nextOrderIndex($formId, $parentFieldId);
        }

        $optionsJson = null;
        $sourceType = (string) ($data['source_type'] ?? 'static');
        if (in_array($fieldType, ['select', 'radio', 'checkbox'], true)) {
            $normalized = ees_normalize_field_options($data['field_options'] ?? []);
            $optionsJson = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $validationRulesJson = null;
        if (array_key_exists('validation_rules', $data)) {
            $validationRulesJson = json_encode($data['validation_rules'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO form_fields (form_id, field_type, label, placeholder, is_required, is_active, field_options, source_type, parent_field_id, field_key, order_index, validation_rules, helper_text, created_at, updated_at)\n'
                . 'VALUES (:form_id, :field_type, :label, :placeholder, :is_required, :is_active, :field_options, :source_type, :parent_field_id, :field_key, :order_index, :validation_rules, :helper_text, :created_at, :updated_at)'
            );

            $stmt->execute([
                'form_id' => $formId,
                'field_type' => $fieldType,
                'label' => $label,
                'placeholder' => $data['placeholder'] ?? null,
                'is_required' => (int) (bool) ($data['is_required'] ?? false),
                'is_active' => (int) (bool) ($data['is_active'] ?? true),
                'field_options' => $optionsJson,
                'source_type' => $sourceType,
                'parent_field_id' => $parentFieldId,
                'field_key' => $fieldKey,
                'order_index' => (int) $orderIndex,
                'validation_rules' => $validationRulesJson,
                'helper_text' => $data['helper_text'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $fieldId = (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to add form field: ' . $e->getMessage(), 0, $e);
        }

        $this->logger->info('Form field created', ['form_id' => $formId, 'field_id' => $fieldId, 'field_key' => $fieldKey]);

        return $this->getFieldById($fieldId);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateField(int $fieldId, array $data): array
    {
        $field = $this->getFieldById($fieldId);

        $label = array_key_exists('label', $data) ? trim((string) $data['label']) : (string) $field['label'];
        if ($label === '') {
            throw new ServiceException('Field label cannot be empty.');
        }

        $fieldType = array_key_exists('field_type', $data) ? (string) $data['field_type'] : (string) $field['field_type'];

        $optionsJson = $field['field_options'];
        if (in_array($fieldType, ['select', 'radio', 'checkbox'], true) && array_key_exists('field_options', $data)) {
            $normalized = ees_normalize_field_options($data['field_options'] ?? []);
            $optionsJson = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $validationRulesJson = $field['validation_rules'];
        if (array_key_exists('validation_rules', $data)) {
            $validationRulesJson = json_encode($data['validation_rules'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE form_fields SET field_type = :field_type, label = :label, placeholder = :placeholder, is_required = :is_required, is_active = :is_active, field_options = :field_options, source_type = :source_type, parent_field_id = :parent_field_id, order_index = :order_index, validation_rules = :validation_rules, helper_text = :helper_text, updated_at = :updated_at\n'
                . 'WHERE id = :id'
            );

            $stmt->execute([
                'id' => $fieldId,
                'field_type' => $fieldType,
                'label' => $label,
                'placeholder' => array_key_exists('placeholder', $data) ? $data['placeholder'] : $field['placeholder'],
                'is_required' => array_key_exists('is_required', $data) ? (int) (bool) $data['is_required'] : (int) $field['is_required'],
                'is_active' => array_key_exists('is_active', $data) ? (int) (bool) $data['is_active'] : (int) $field['is_active'],
                'field_options' => $optionsJson,
                'source_type' => array_key_exists('source_type', $data) ? (string) $data['source_type'] : (string) $field['source_type'],
                'parent_field_id' => array_key_exists('parent_field_id', $data) ? ($data['parent_field_id'] === null ? null : (int) $data['parent_field_id']) : $field['parent_field_id'],
                'order_index' => array_key_exists('order_index', $data) ? (int) $data['order_index'] : (int) $field['order_index'],
                'validation_rules' => $validationRulesJson,
                'helper_text' => array_key_exists('helper_text', $data) ? $data['helper_text'] : $field['helper_text'],
                'updated_at' => $now,
            ]);
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to update form field: ' . $fieldId, 0, $e);
        }

        $this->logger->info('Form field updated', ['field_id' => $fieldId]);

        return $this->getFieldById($fieldId);
    }

    public function deleteField(int $fieldId): void
    {
        $field = $this->getFieldById($fieldId);

        try {
            $stmt = $this->pdo->prepare('DELETE FROM form_fields WHERE id = :id');
            $stmt->execute(['id' => $fieldId]);
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to delete form field: ' . $fieldId, 0, $e);
        }

        $this->logger->info('Form field deleted', ['field_id' => $fieldId, 'form_id' => (int) $field['form_id']]);
    }

    /**
     * Reorder fields by a given ordered list of IDs.
     *
     * @param list<int> $orderedFieldIds
     */
    public function reorderFields(int $formId, array $orderedFieldIds, ?int $parentFieldId = null): void
    {
        $orderedFieldIds = array_values(array_unique(array_map('intval', $orderedFieldIds)));
        if ($orderedFieldIds === []) {
            return;
        }

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare('UPDATE form_fields SET order_index = :order_index WHERE id = :id AND form_id = :form_id AND parent_field_id ' . ($parentFieldId === null ? 'IS NULL' : '= :parent_field_id'));

            foreach ($orderedFieldIds as $index => $fieldId) {
                $params = [
                    'order_index' => $index,
                    'id' => $fieldId,
                    'form_id' => $formId,
                ];
                if ($parentFieldId !== null) {
                    $params['parent_field_id'] = $parentFieldId;
                }

                $stmt->execute($params);
            }

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new DatabaseException('Failed to reorder fields for form: ' . $formId, 0, $e);
        }

        $this->logger->info('Form fields reordered', ['form_id' => $formId, 'parent_field_id' => $parentFieldId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getFieldsForForm(int $formId, bool $includeInactive = false): array
    {
        $query = 'SELECT * FROM form_fields WHERE form_id = :form_id';
        if (!$includeInactive) {
            $query .= ' AND is_active = 1';
        }

        $query .= ' ORDER BY parent_field_id ASC, order_index ASC, id ASC';

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute(['form_id' => $formId]);
            $rows = $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to load form fields.', 0, $e);
        }

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFieldById(int $fieldId): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM form_fields WHERE id = :id');
            $stmt->execute(['id' => $fieldId]);
            $field = $stmt->fetch();
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to load field: ' . $fieldId, 0, $e);
        }

        if (!is_array($field)) {
            throw new NotFoundException('Form field not found: ' . $fieldId);
        }

        return $field;
    }

    /**
     * Convert raw DB field definitions into a render-friendly structure.
     *
     * @return list<array<string, mixed>>
     */
    public function getFieldDefinitionsForRendering(int $formId): array
    {
        $fields = $this->getFieldsForForm($formId, false);

        $departmentOptions = $this->getDepartmentOptions();

        return ees_transform_field_definitions($fields, [
            'department_options' => $departmentOptions,
        ]);
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    private function getDepartmentOptions(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC');
            $rows = $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to load departments for dynamic options.', 0, $e);
        }

        $options = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $options[] = ['value' => (string) $row['id'], 'label' => (string) $row['name']];
        }

        return $options;
    }

    private function nextOrderIndex(int $formId, ?int $parentFieldId): int
    {
        try {
            $sql = 'SELECT COALESCE(MAX(order_index), -1) AS max_order FROM form_fields WHERE form_id = :form_id AND parent_field_id ' . ($parentFieldId === null ? 'IS NULL' : '= :parent_field_id');
            $stmt = $this->pdo->prepare($sql);
            $params = ['form_id' => $formId];
            if ($parentFieldId !== null) {
                $params['parent_field_id'] = $parentFieldId;
            }
            $stmt->execute($params);
            $max = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to compute next field order index.', 0, $e);
        }

        return $max + 1;
    }

    private function generateUniqueFieldKey(int $formId, string $base): string
    {
        $base = trim($base);
        if ($base === '') {
            $base = 'field';
        }

        $candidate = $base;
        $suffix = 2;

        while ($this->fieldKeyExists($formId, $candidate)) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function fieldKeyExists(int $formId, string $fieldKey): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM form_fields WHERE form_id = :form_id AND field_key = :field_key LIMIT 1');
            $stmt->execute(['form_id' => $formId, 'field_key' => $fieldKey]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to check field_key uniqueness.', 0, $e);
        }
    }
}
