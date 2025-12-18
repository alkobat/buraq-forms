<?php

declare(strict_types=1);

namespace BuraqForms\Core\Services;

use BuraqForms\Core\Cache\FileCache;
use BuraqForms\Core\Exceptions\DatabaseException;
use BuraqForms\Core\Exceptions\NotFoundException;
use BuraqForms\Core\Exceptions\ServiceException;
use BuraqForms\Core\Exceptions\ValidationException;
use BuraqForms\Core\Logger;
use PDO;
use PDOException;

use function ees_validate_submission_data;

/**
 * Handles form submissions: validation, persistence, and reference codes.
 */
class FormSubmissionService
{
    private PDO $pdo;
    private FormService $forms;
    private FormFieldService $fields;
    private FormFileService $files;
    private SystemSettingsService $settings;
    private Logger $logger;

    public function __construct(
        PDO $pdo,
        ?FormService $forms = null,
        ?FormFieldService $fields = null,
        ?FormFileService $files = null,
        ?SystemSettingsService $settings = null,
        ?Logger $logger = null,
        ?FileCache $cache = null
    ) {
        $this->pdo = $pdo;
        $this->logger = $logger ?? new Logger();
        $this->settings = $settings ?? new SystemSettingsService($pdo, $cache);
        $this->forms = $forms ?? new FormService($pdo, $this->logger, $cache);
        $this->fields = $fields ?? new FormFieldService($pdo, $this->logger);
        $this->files = $files ?? new FormFileService($pdo, $this->settings, $this->logger, $cache);
    }

    /**
     * @param array{submitted_by:string,department_id?:int|null,ip_address?:string|null,status?:string} $submissionData
     * @param array<string, mixed> $answers
     * @param array<string, array{name:string,type?:string,tmp_name:string,error:int,size:int}> $files
     * @return array<string, mixed>
     */
    public function submit(int $formId, array $submissionData, array $answers, array $files = []): array
    {
        $form = $this->forms->getById($formId);
        if (($form['status'] ?? 'inactive') !== 'active') {
            throw new ServiceException('Form is not active.');
        }

        $submittedBy = trim((string) ($submissionData['submitted_by'] ?? ''));
        if ($submittedBy === '') {
            throw new ValidationException('submitted_by is required.', ['submitted_by' => ['Required.']]);
        }

        $departmentId = array_key_exists('department_id', $submissionData) ? (int) ($submissionData['department_id'] ?? 0) : 0;
        $departmentId = $departmentId > 0 ? $departmentId : null;

        if (((int) ($form['show_department_field'] ?? 0)) === 1 && $departmentId === null) {
            throw new ValidationException('department_id is required for this form.', ['department_id' => ['Required.']]);
        }

        if (((int) ($form['allow_multiple_submissions'] ?? 1)) === 0 && $this->hasExistingSubmission($formId, $submittedBy)) {
            throw new ServiceException('Multiple submissions are not allowed for this form.');
        }

        $fieldRows = $this->fields->getFieldsForForm($formId, false);
        $renderDefs = $this->fields->getFieldDefinitionsForRendering($formId);

        $validation = ees_validate_submission_data($answers, $renderDefs, [
            'files' => $files,
        ]);

        if (!$validation['valid']) {
            throw new ValidationException('Submission validation failed.', $validation['errors']);
        }

        $reference = $this->generateUniqueReferenceCode();
        $submittedAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'INSERT INTO form_submissions (form_id, submitted_by, department_id, status, submitted_at, ip_address, reference_code)\n'
                . 'VALUES (:form_id, :submitted_by, :department_id, :status, :submitted_at, :ip_address, :reference_code)'
            );

            $stmt->execute([
                'form_id' => $formId,
                'submitted_by' => $submittedBy,
                'department_id' => $departmentId,
                'status' => $submissionData['status'] ?? 'pending',
                'submitted_at' => $submittedAt,
                'ip_address' => $submissionData['ip_address'] ?? null,
                'reference_code' => $reference,
            ]);

            $submissionId = (int) $this->pdo->lastInsertId();

            $this->persistAnswers($submissionId, $formId, $fieldRows, $answers, $files);

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new DatabaseException('Failed to save submission: ' . $e->getMessage(), 0, $e);
        }

        $this->logger->info('Submission created', ['submission_id' => $submissionId, 'form_id' => $formId, 'reference_code' => $reference]);

        return $this->getSubmissionById($submissionId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubmissionById(int $submissionId): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM form_submissions WHERE id = :id');
            $stmt->execute(['id' => $submissionId]);
            $submission = $stmt->fetch();

            $stmt2 = $this->pdo->prepare('SELECT * FROM submission_answers WHERE submission_id = :id ORDER BY field_id ASC, repeat_index ASC, id ASC');
            $stmt2->execute(['id' => $submissionId]);
            $answers = $stmt2->fetchAll();
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to load submission: ' . $submissionId, 0, $e);
        }

        if (!is_array($submission)) {
            throw new NotFoundException('Submission not found: ' . $submissionId);
        }

        $submission['answers'] = is_array($answers) ? $answers : [];

        return $submission;
    }

    private function hasExistingSubmission(int $formId, string $submittedBy): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM form_submissions WHERE form_id = :form_id AND submitted_by = :submitted_by LIMIT 1');
            $stmt->execute(['form_id' => $formId, 'submitted_by' => $submittedBy]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to check existing submissions.', 0, $e);
        }
    }

    /**
     * @param list<array<string, mixed>> $fieldRows
     * @param array<string, mixed> $answers
     * @param array<string, array{name:string,type?:string,tmp_name:string,error:int,size:int}> $files
     */
    private function persistAnswers(int $submissionId, int $formId, array $fieldRows, array $answers, array $files): void
    {
        $fieldsById = [];
        $childrenByParentId = [];

        foreach ($fieldRows as $row) {
            $fieldId = (int) $row['id'];
            $fieldsById[$fieldId] = $row;

            $parent = $row['parent_field_id'] !== null ? (int) $row['parent_field_id'] : null;
            if ($parent !== null) {
                $childrenByParentId[$parent] ??= [];
                $childrenByParentId[$parent][] = $row;
            }
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO submission_answers (submission_id, field_id, answer, file_path, file_name, file_size, repeat_index)\n'
            . 'VALUES (:submission_id, :field_id, :answer, :file_path, :file_name, :file_size, :repeat_index)'
        );

        foreach ($fieldRows as $row) {
            if ($row['parent_field_id'] !== null) {
                continue;
            }

            $fieldType = (string) $row['field_type'];
            $fieldKey = (string) $row['field_key'];
            $fieldId = (int) $row['id'];

            if ($fieldType === 'repeater') {
                $groups = $answers[$fieldKey] ?? [];
                if (!is_array($groups)) {
                    continue;
                }

                $children = $childrenByParentId[$fieldId] ?? [];

                foreach (array_values($groups) as $repeatIndex => $groupAnswers) {
                    if (!is_array($groupAnswers)) {
                        continue;
                    }

                    foreach ($children as $child) {
                        $childId = (int) $child['id'];
                        $childKey = (string) $child['field_key'];
                        $childType = (string) $child['field_type'];

                        if ($childType === 'file') {
                            $fileKey = $fieldKey . '.' . $repeatIndex . '.' . $childKey;
                            if (!isset($files[$fileKey])) {
                                continue;
                            }

                            $meta = $this->files->storeUploadedFile($formId, $childId, $files[$fileKey]);
                            $insert->execute([
                                'submission_id' => $submissionId,
                                'field_id' => $childId,
                                'answer' => null,
                                'file_path' => $meta['path'],
                                'file_name' => $meta['original_name'],
                                'file_size' => $meta['size'],
                                'repeat_index' => $repeatIndex,
                            ]);
                            continue;
                        }

                        $value = $groupAnswers[$childKey] ?? null;
                        $answerText = $this->stringifyAnswer($value);

                        $insert->execute([
                            'submission_id' => $submissionId,
                            'field_id' => $childId,
                            'answer' => $answerText,
                            'file_path' => null,
                            'file_name' => null,
                            'file_size' => null,
                            'repeat_index' => $repeatIndex,
                        ]);
                    }
                }

                continue;
            }

            if ($fieldType === 'file') {
                if (!isset($files[$fieldKey])) {
                    continue;
                }

                $meta = $this->files->storeUploadedFile($formId, $fieldId, $files[$fieldKey]);

                $insert->execute([
                    'submission_id' => $submissionId,
                    'field_id' => $fieldId,
                    'answer' => null,
                    'file_path' => $meta['path'],
                    'file_name' => $meta['original_name'],
                    'file_size' => $meta['size'],
                    'repeat_index' => 0,
                ]);

                continue;
            }

            $value = $answers[$fieldKey] ?? null;
            $answerText = $this->stringifyAnswer($value);

            $insert->execute([
                'submission_id' => $submissionId,
                'field_id' => $fieldId,
                'answer' => $answerText,
                'file_path' => null,
                'file_name' => null,
                'file_size' => null,
                'repeat_index' => 0,
            ]);
        }
    }

    private function stringifyAnswer(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return trim((string) $value);
    }

    private function generateUniqueReferenceCode(): string
    {
        $prefix = $this->settings->getString('reference_code_prefix', 'REF-') ?? 'REF-';
        $length = $this->settings->getInt('reference_code_length', 8);
        $length = max(4, $length);

        for ($i = 0; $i < 10; $i++) {
            $candidate = $prefix . $this->randomAlphaNum($length);

            if (!$this->referenceExists($candidate)) {
                return $candidate;
            }
        }

        throw new ServiceException('Failed to generate a unique reference code.');
    }

    private function randomAlphaNum(int $length): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $out;
    }

    private function referenceExists(string $referenceCode): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM form_submissions WHERE reference_code = :ref LIMIT 1');
            $stmt->execute(['ref' => $referenceCode]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw new DatabaseException('Failed to check reference code uniqueness.', 0, $e);
        }
    }
}
