<?php

declare(strict_types=1);

/**
 * Create a URL-friendly slug (unicode-safe).
 */
function ees_slugify(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? $text;
    $text = trim($text, '-');
    $text = preg_replace('/-+/', '-', $text) ?? $text;

    return $text;
}

/**
 * Normalize options into a list of {value,label}.
 *
 * @return list<array{value:string,label:string}>
 */
function ees_normalize_field_options(mixed $options): array
{
    if ($options === null || $options === '') {
        return [];
    }

    if (is_string($options) || is_int($options) || is_float($options) || is_bool($options)) {
        $value = (string) $options;
        return [['value' => $value, 'label' => $value]];
    }

    if (!is_array($options)) {
        return [];
    }

    $normalized = [];

    $isList = array_keys($options) === range(0, count($options) - 1);

    if (!$isList) {
        foreach ($options as $value => $label) {
            $value = trim((string) $value);
            $label = trim((string) $label);
            if ($value === '' || $label === '') {
                continue;
            }
            $normalized[] = ['value' => $value, 'label' => $label];
        }

        return $normalized;
    }

    foreach ($options as $item) {
        if (is_string($item) || is_int($item) || is_float($item) || is_bool($item)) {
            $value = trim((string) $item);
            if ($value === '') {
                continue;
            }
            $normalized[] = ['value' => $value, 'label' => $value];
            continue;
        }

        if (is_array($item)) {
            $value = array_key_exists('value', $item) ? trim((string) $item['value']) : '';
            $label = array_key_exists('label', $item) ? trim((string) $item['label']) : '';

            if ($value === '' && $label !== '') {
                $value = $label;
            }
            if ($label === '' && $value !== '') {
                $label = $value;
            }

            if ($value === '' || $label === '') {
                continue;
            }

            $normalized[] = ['value' => $value, 'label' => $label];
        }
    }

    return $normalized;
}

/**
 * Transform raw field rows into a render-friendly structure (supports repeater children).
 *
 * @param list<array<string, mixed>> $fields
 * @param array{department_options?:list<array{value:string,label:string}>} $context
 * @return list<array<string, mixed>>
 */
function ees_transform_field_definitions(array $fields, array $context = []): array
{
    $departmentOptions = $context['department_options'] ?? [];

    $byId = [];
    $children = [];

    foreach ($fields as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $options = null;
        if (!empty($row['field_options'])) {
            $decoded = json_decode((string) $row['field_options'], true);
            if (is_array($decoded)) {
                $options = ees_normalize_field_options($decoded);
            }
        }

        $validationRules = null;
        if (!empty($row['validation_rules'])) {
            $decoded = json_decode((string) $row['validation_rules'], true);
            if (is_array($decoded)) {
                $validationRules = $decoded;
            }
        }

        $def = [
            'id' => $id,
            'form_id' => (int) ($row['form_id'] ?? 0),
            'field_type' => (string) ($row['field_type'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'placeholder' => $row['placeholder'] ?? null,
            'is_required' => ((int) ($row['is_required'] ?? 0)) === 1,
            'is_active' => ((int) ($row['is_active'] ?? 0)) === 1,
            'source_type' => (string) ($row['source_type'] ?? 'static'),
            'parent_field_id' => $row['parent_field_id'] !== null ? (int) $row['parent_field_id'] : null,
            'field_key' => (string) ($row['field_key'] ?? ''),
            'order_index' => (int) ($row['order_index'] ?? 0),
            'options' => $options,
            'validation_rules' => $validationRules,
            'helper_text' => $row['helper_text'] ?? null,
            'children' => [],
        ];

        if ($def['source_type'] === 'departments') {
            $def['options'] = $departmentOptions;
        }

        $byId[$id] = $def;

        $parent = $def['parent_field_id'];
        if ($parent !== null) {
            $children[$parent] ??= [];
            $children[$parent][] = $id;
        }
    }

    foreach ($children as $parentId => $childIds) {
        if (!isset($byId[$parentId])) {
            continue;
        }

        usort($childIds, static function (int $a, int $b) use ($byId): int {
            return ($byId[$a]['order_index'] <=> $byId[$b]['order_index']) ?: ($a <=> $b);
        });

        foreach ($childIds as $childId) {
            $byId[$parentId]['children'][] = $byId[$childId];
        }
    }

    $top = [];
    foreach ($byId as $id => $def) {
        if ($def['parent_field_id'] === null) {
            $top[] = $def;
        }
    }

    usort($top, static function (array $a, array $b): int {
        return ($a['order_index'] <=> $b['order_index']) ?: ($a['id'] <=> $b['id']);
    });

    return $top;
}

/**
 * Validate submission answers using render definitions.
 *
 * @param array<string, mixed> $answers
 * @param list<array<string, mixed>> $fieldDefinitions Output of ees_transform_field_definitions
 * @param array{files?:array<string, array{name:string,type?:string,tmp_name:string,error:int,size:int}>} $context
 * @return array{valid:bool,errors:array<string, list<string>>}
 */
function ees_validate_submission_data(array $answers, array $fieldDefinitions, array $context = []): array
{
    $files = $context['files'] ?? [];

    $errors = [];

    $addError = static function (string $key, string $message) use (&$errors): void {
        $errors[$key] ??= [];
        $errors[$key][] = $message;
    };

    $validateValue = static function (array $field, mixed $value, string $key) use ($addError): void {
        $type = (string) ($field['field_type'] ?? '');
        $required = (bool) ($field['is_required'] ?? false);
        $options = $field['options'] ?? null;

        $isEmpty = $value === null || $value === '' || (is_array($value) && $value === []);
        if ($required && $isEmpty) {
            $addError($key, 'Required.');
            return;
        }

        if ($isEmpty) {
            return;
        }

        if ($type === 'email' && !filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
            $addError($key, 'Invalid email.');
        }

        if ($type === 'number' && !is_numeric($value)) {
            $addError($key, 'Invalid number.');
        }

        if (in_array($type, ['select', 'radio'], true) && is_array($options)) {
            $allowed = array_map(static fn ($o) => (string) $o['value'], $options);
            if (!in_array((string) $value, $allowed, true)) {
                $addError($key, 'Invalid option.');
            }
        }

        if ($type === 'checkbox' && is_array($options) && is_array($value)) {
            $allowed = array_map(static fn ($o) => (string) $o['value'], $options);
            foreach ($value as $v) {
                if (!in_array((string) $v, $allowed, true)) {
                    $addError($key, 'Invalid option.');
                    break;
                }
            }
        }

        $rules = $field['validation_rules'] ?? null;
        if (!is_array($rules)) {
            return;
        }

        $str = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $value;

        $minLength = $rules['min_length'] ?? $rules['minLength'] ?? null;
        if ($minLength !== null && mb_strlen($str, 'UTF-8') < (int) $minLength) {
            $addError($key, 'Too short.');
        }

        $maxLength = $rules['max_length'] ?? $rules['maxLength'] ?? null;
        if ($maxLength !== null && mb_strlen($str, 'UTF-8') > (int) $maxLength) {
            $addError($key, 'Too long.');
        }

        if ($type === 'number' && is_numeric($value)) {
            $min = $rules['min'] ?? null;
            $max = $rules['max'] ?? null;
            if ($min !== null && (float) $value < (float) $min) {
                $addError($key, 'Too small.');
            }
            if ($max !== null && (float) $value > (float) $max) {
                $addError($key, 'Too large.');
            }
        }

        $regex = $rules['regex'] ?? null;
        if (is_string($regex) && $regex !== '' && @preg_match($regex, '') !== false) {
            if (!preg_match($regex, $str)) {
                $addError($key, 'Invalid format.');
            }
        }
    };

    $validateFile = static function (array $field, string $key) use ($files, $addError): void {
        $required = (bool) ($field['is_required'] ?? false);

        if (!isset($files[$key])) {
            if ($required) {
                $addError($key, 'File is required.');
            }
            return;
        }

        $file = $files[$key];
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $addError($key, 'Upload error.');
        }
    };

    foreach ($fieldDefinitions as $field) {
        $key = (string) ($field['field_key'] ?? '');
        if ($key === '') {
            continue;
        }

        $type = (string) ($field['field_type'] ?? '');

        if ($type === 'repeater') {
            $required = (bool) ($field['is_required'] ?? false);
            $groups = $answers[$key] ?? null;

            if ($groups === null || $groups === '') {
                if ($required) {
                    $addError($key, 'At least one item is required.');
                }
                continue;
            }

            if (!is_array($groups)) {
                $addError($key, 'Invalid repeater value.');
                continue;
            }

            $groups = array_values($groups);
            foreach ($groups as $i => $group) {
                if (!is_array($group)) {
                    $addError($key . '.' . $i, 'Invalid item.');
                    continue;
                }

                foreach (($field['children'] ?? []) as $child) {
                    $childKey = (string) ($child['field_key'] ?? '');
                    if ($childKey === '') {
                        continue;
                    }

                    $compositeKey = $key . '.' . $i . '.' . $childKey;

                    if (($child['field_type'] ?? '') === 'file') {
                        $validateFile($child, $compositeKey);
                        continue;
                    }

                    $validateValue($child, $group[$childKey] ?? null, $compositeKey);
                }
            }

            continue;
        }

        if ($type === 'file') {
            $validateFile($field, $key);
            continue;
        }

        $validateValue($field, $answers[$key] ?? null, $key);
    }

    return ['valid' => $errors === [], 'errors' => $errors];
}

/**
 * Prepare submission answers for export (flattened map label => value).
 *
 * @param array{answers:list<array<string,mixed>>} $submission The output of FormSubmissionService::getSubmissionById()
 * @param list<array<string,mixed>> $fieldDefinitions Render definitions
 * @return array<string, string|null>
 */
function ees_prepare_data_for_export(array $submission, array $fieldDefinitions): array
{
    $labelsById = [];

    $walk = static function (array $fields) use (&$walk, &$labelsById): void {
        foreach ($fields as $f) {
            if (isset($f['id'], $f['label'])) {
                $labelsById[(int) $f['id']] = (string) $f['label'];
            }
            if (!empty($f['children']) && is_array($f['children'])) {
                $walk($f['children']);
            }
        }
    };

    $walk($fieldDefinitions);

    $export = [];

    foreach (($submission['answers'] ?? []) as $row) {
        $fieldId = (int) ($row['field_id'] ?? 0);
        $label = $labelsById[$fieldId] ?? ('field_' . $fieldId);
        $repeatIndex = (int) ($row['repeat_index'] ?? 0);

        $key = $repeatIndex > 0 ? sprintf('%s[%d]', $label, $repeatIndex) : $label;

        if (!empty($row['file_path'])) {
            $export[$key] = (string) $row['file_path'];
            continue;
        }

        $export[$key] = $row['answer'] !== null ? (string) $row['answer'] : null;
    }

    return $export;
}
