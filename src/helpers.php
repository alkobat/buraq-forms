<?php

declare(strict_types=1);

// تعريف الثوابت الأساسية إذا لم تكن معرفة
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
if (!defined('SRC_PATH')) {
    define('SRC_PATH', __DIR__);
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', BASE_PATH . '/config');
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', BASE_PATH . '/public');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', BASE_PATH . '/storage');
}
if (!defined('APP_URL')) {
    define('APP_URL', getenv('APP_URL') ?: 'http://localhost');
}

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

/**
 * Generate a secure random token for CSRF protection.
 */
function ees_generate_csrf_token(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Validate CSRF token.
 */
function ees_validate_csrf_token(string $token, ?string $sessionToken = null): bool
{
    if ($sessionToken === null) {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
    }

    return hash_equals($sessionToken, $token);
}

/**
 * Sanitize HTML content while preserving basic formatting.
 */
function ees_sanitize_html(string $html): string
{
    $allowedTags = '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a>';
    return strip_tags($html, $allowedTags);
}

/**
 * Format file size in human readable format.
 */
function ees_format_file_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Generate reference code for submissions.
 */
function ees_generate_reference_code(int $submissionId): string
{
    return 'REF-' . str_pad((string) $submissionId, 6, '0', STR_PAD_LEFT);
}

/**
 * Convert Arabic numerals to English numerals.
 */
function ees_arabic_to_english_numerals(string $text): string
{
    $arabicNumerals = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $englishNumerals = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    return str_replace($arabicNumerals, $englishNumerals, $text);
}

/**
 * Convert English numerals to Arabic numerals.
 */
function ees_english_to_arabic_numerals(string $text): string
{
    $englishNumerals = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $arabicNumerals = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    return str_replace($englishNumerals, $arabicNumerals, $text);
}

/**
 * Validate Saudi phone number.
 */
function ees_validate_saudi_phone(string $phone): bool
{
    $phone = preg_replace('/[^0-9+]/', '', $phone);

    // Saudi phone patterns
    $patterns = [
        '/^(\+966|966)?5[0-9]{8}$/',      // Mobile
        '/^(\+966|966)?1[1-9][0-9]{7}$/', // Landline
        '/^(\+966|966)?11[1-9][0-9]{6}$/' // Landline (Riyadh)
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $phone)) {
            return true;
        }
    }

    return false;
}

/**
 * Format Saudi phone number for display.
 */
function ees_format_saudi_phone(string $phone): string
{
    $phone = preg_replace('/[^0-9+]/', '', $phone);

    if (preg_match('/^(\+966|966)?5([0-9]{8})$/', $phone, $matches)) {
        return '+966 5' . substr($matches[2], 0, 3) . ' ' . substr($matches[2], 3);
    }

    if (preg_match('/^(\+966|966)?1([1-9][0-9]{7})$/', $phone, $matches)) {
        return '+966 1' . substr($matches[2], 0, 3) . ' ' . substr($matches[2], 3);
    }

    return $phone;
}

/**
 * Generate a unique temporary file path.
 */
function ees_generate_temp_file_path(string $prefix = 'temp_'): string
{
    return sys_get_temp_dir() . '/' . $prefix . uniqid() . '.tmp';
}

/**
 * Check if current user has permission for specific action.
 */
function ees_has_permission(string $permission, ?int $adminId = null, ?int $departmentId = null): bool
{
    if ($adminId === null) {
        $adminId = $_SESSION['admin_id'] ?? 0;
    }

    if ($adminId === 0) {
        return false;
    }

    try {
        $database = BuraqForms\Core\Database::getConnection();
        $permissionService = new BuraqForms\Core\Services\PermissionService($database);

        return $permissionService->hasPermission($adminId, $permission, $departmentId);
    } catch (Exception $e) {
        error_log("Error checking permission: " . $e->getMessage());
        return false;
    }
}

/**
 * Log user activity for audit purposes.
 */
function ees_log_activity(string $action, string $entityType, ?int $entityId = null, ?array $oldValues = null, ?array $newValues = null): bool
{
    try {
        $adminId = $_SESSION['admin_id'] ?? 0;

        if ($adminId === 0) {
            return false;
        }

        $database = BuraqForms\Core\Database::getConnection();
        $auditService = new BuraqForms\Core\Services\AuditService($database);

        return $auditService->logActivity($adminId, $action, $entityType, $entityId, $oldValues, $newValues);
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification to users.
 */
function ees_send_notification(string $type, string $title, string $message, ?int $recipientId = null, array $options = []): ?int
{
    try {
        $database = BuraqForms\Core\Database::getConnection();
        $notificationService = new BuraqForms\Core\Services\NotificationService($database);

        $notificationData = array_merge([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'recipient_id' => $recipientId,
            'recipient_type' => 'admin',
            'priority' => 'normal'
        ], $options);

        return $notificationService->createNotification($notificationData);
    } catch (Exception $e) {
        error_log("Error sending notification: " . $e->getMessage());
        return null;
    }
}

/**
 * Cache data with expiration.
 */
function ees_cache_set(string $key, mixed $value, int $ttl = 3600): bool
{
    try {
        $cacheDir = __DIR__ . '/../storage/cache/';

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cacheFile = $cacheDir . md5($key) . '.cache';
        $cacheData = [
            'expires' => time() + $ttl,
            'data' => $value
        ];

        return file_put_contents($cacheFile, serialize($cacheData)) !== false;
    } catch (Exception $e) {
        error_log("Error setting cache: " . $e->getMessage());
        return false;
    }
}

/**
 * Get cached data.
 */
function ees_cache_get(string $key): mixed
{
    try {
        $cacheDir = __DIR__ . '/../storage/cache/';
        $cacheFile = $cacheDir . md5($key) . '.cache';

        if (!file_exists($cacheFile)) {
            return null;
        }

        $cacheData = unserialize(file_get_contents($cacheFile));

        if (!$cacheData || !isset($cacheData['expires']) || $cacheData['expires'] < time()) {
            unlink($cacheFile);
            return null;
        }

        return $cacheData['data'];
    } catch (Exception $e) {
        error_log("Error getting cache: " . $e->getMessage());
        return null;
    }
}

/**
 * Delete cached data.
 */
function ees_cache_delete(string $key): bool
{
    try {
        $cacheDir = __DIR__ . '/../storage/cache/';
        $cacheFile = $cacheDir . md5($key) . '.cache';

        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }

        return true;
    } catch (Exception $e) {
        error_log("Error deleting cache: " . $e->getMessage());
        return false;
    }
}

/**
 * Clean expired cache files.
 */
function ees_cache_clean(): int
{
    try {
        $cacheDir = __DIR__ . '/../storage/cache/';

        if (!is_dir($cacheDir)) {
            return 0;
        }

        $deletedCount = 0;
        $files = glob($cacheDir . '*.cache');

        foreach ($files as $file) {
            try {
                $cacheData = unserialize(file_get_contents($file));

                if (!$cacheData || !isset($cacheData['expires']) || $cacheData['expires'] < time()) {
                    if (unlink($file)) {
                        $deletedCount++;
                    }
                }
            } catch (Exception $e) {
                // Skip invalid cache files
                continue;
            }
        }

        return $deletedCount;
    } catch (Exception $e) {
        error_log("Error cleaning cache: " . $e->getMessage());
        return 0;
    }
}

/**
 * Rate limiting for API endpoints.
 */
function ees_rate_limit(string $identifier, int $maxAttempts = 60, int $timeWindow = 3600): bool
{
    try {
        $cacheKey = 'rate_limit_' . md5($identifier);
        $current = ees_cache_get($cacheKey);

        if ($current === null) {
            ees_cache_set($cacheKey, 1, $timeWindow);
            return true;
        }

        if ($current >= $maxAttempts) {
            return false;
        }

        ees_cache_set($cacheKey, $current + 1, $timeWindow);
        return true;
    } catch (Exception $e) {
        error_log("Error checking rate limit: " . $e->getMessage());
        return true; // Allow on error
    }
}

/**
 * Generate API response in standardized format.
 */
function ees_api_response(bool $success, string $message = '', mixed $data = null, int $statusCode = 200): void
{
    http_response_code($statusCode);

    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

/**
 * Validate email address with enhanced checks.
 */
function ees_validate_email_enhanced(string $email): bool
{
    // Basic email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Additional checks for common issues
    $email = strtolower(trim($email));

    // Check for consecutive dots
    if (strpos($email, '..') !== false) {
        return false;
    }

    // Check for dots at start or end
    if (str_starts_with($email, '.') || str_ends_with($email, '.')) {
        return false;
    }

    // Check for valid TLD length
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return false;
    }

    $domain = $parts[1];
    $domainParts = explode('.', $domain);

    if (count($domainParts) < 2) {
        return false;
    }

    $tld = end($domainParts);
    if (strlen($tld) < 2 || strlen($tld) > 63) {
        return false;
    }

    return true;
}

/**
 * Generate secure password hash.
 */
function ees_hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password against hash.
 */
function ees_verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Generate secure random password.
 */
function ees_generate_password(int $length = 12, bool $includeSymbols = true): string
{
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    if ($includeSymbols) {
        $characters .= '!@#$%^&*()_+-=[]{}|;:,.<>?';
    }

    $password = '';
    $max = strlen($characters) - 1;

    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[random_int(0, $max)];
    }

    return $password;
}

/**
 * Log error with context information.
 */
function ees_log_error(string $message, array $context = []): void
{
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'context' => $context,
        'user_id' => $_SESSION['admin_id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];

    error_log(json_encode($logEntry, JSON_UNESCAPED_UNICODE));
}

/**
 * Convert array to CSV format.
 */
function ees_array_to_csv(array $data, string $delimiter = ',', string $enclosure = '"'): string
{
    $output = fopen('php://temp', 'r+');

    // Add UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");

    foreach ($data as $row) {
        fputcsv($output, $row, $delimiter, $enclosure);
    }

    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);

    return $csv;
}

/**
 * Send HTTP response with appropriate headers.
 */
function ees_send_response(string $content, string $contentType = 'text/html', int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: ' . $contentType . '; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');

    echo $content;
}

/**
 * Check if request is AJAX.
 */
function ees_is_ajax(): bool
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get client IP address with proxy support.
 */
function ees_get_client_ip(): string
{
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];

            // Handle multiple IPs in X-Forwarded-For
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }

            // Validate IP format
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * ========================================
 * Authentication and Authorization Helpers
 * ========================================
 */

/**
 * Check if user is logged in
 */
function is_logged_in(): bool
{
    require_once __DIR__ . '/Core/Auth.php';
    return \BuraqForms\Core\Auth::is_logged_in();
}

/**
 * Get current logged-in user data
 */
function current_user(): ?array
{
    require_once __DIR__ . '/Core/Auth.php';
    return \BuraqForms\Core\Auth::current_user();
}

/**
 * Require authentication - redirect to login if not logged in
 */
function require_auth(): void
{
    require_once __DIR__ . '/Core/Auth.php';
    \BuraqForms\Core\Auth::require_auth();
}

/**
 * Require specific permission
 */
function require_permission(string $permission, ?int $departmentId = null): void
{
    require_once __DIR__ . '/Core/Auth.php';
    \BuraqForms\Core\Auth::require_permission($permission, $departmentId);
}

/**
 * Check if current user has specific permission
 */
function has_permission(string $permission, ?int $departmentId = null): bool
{
    require_once __DIR__ . '/Core/Auth.php';
    return \BuraqForms\Core\Auth::has_permission($permission, $departmentId);
}

/**
 * Require specific role
 */
function require_role(string $role): void
{
    require_once __DIR__ . '/Core/Auth.php';
    \BuraqForms\Core\Auth::require_role($role);
}

/**
 * Check if current user has any of the specified roles
 */
function has_any_role(array $roles): bool
{
    require_once __DIR__ . '/Core/Auth.php';
    return \BuraqForms\Core\Auth::has_any_role($roles);
}

/**
 * Get current user permissions
 */
function current_user_permissions(): array
{
    require_once __DIR__ . '/Core/Auth.php';
    return \BuraqForms\Core\Auth::current_user_permissions();
}

/**
 * Get current user roles
 */
function current_user_roles(): array
{
    require_once __DIR__ . '/Core/Auth.php';
    return \BuraqForms\Core\Auth::current_user_roles();
}

/**
 * Login user
 */
function login_user(string $email, string $password, bool $remember_me = false): array
{
    require_once __DIR__ . '/Core/Auth.php';
    return \BuraqForms\Core\Auth::login_user($email, $password, $remember_me);
}

/**
 * Logout user
 */
function logout_user(): void
{
    require_once __DIR__ . '/Core/Auth.php';
    \BuraqForms\Core\Auth::logout_user();
}

/**
 * Generate CSRF token
 */
function generate_csrf_token(): string
{
    require_once __DIR__ . '/Core/Auth.php';
    return \BuraqForms\Core\Auth::generate_csrf_token();
}

/**
 * Verify CSRF token
 */
function verify_csrf_token(?string $token): bool
{
    require_once __DIR__ . '/Core/Auth.php';
    return \BuraqForms\Core\Auth::verify_csrf_token($token);
}

/**
 * Validate session security
 */
function validate_session(): bool
{
    require_once __DIR__ . '/Core/Auth.php';
    return \BuraqForms\Core\Auth::validate_session();
}

/**
 * ========================================
 * Role and Permission Management Helpers
 * ========================================
 */

/**
 * Get all available roles
 */
function get_available_roles(): array
{
    try {
        require_once __DIR__ . '/Core/Services/RolePermissionService.php';
        $roleService = new \BuraqForms\Core\Services\RolePermissionService();
        return $roleService->getAllRoles();
    } catch (Exception $e) {
        error_log("Error getting available roles: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if user can access a module
 */
function can_access(string $module): bool
{
    $module_permissions = [
        'dashboard' => ['dashboard.view'],
        'forms' => ['forms.view', 'forms.create', 'forms.edit'],
        'submissions' => ['submissions.view', 'submissions.edit'],
        'departments' => ['departments.view', 'departments.manage'],
        'reports' => ['reports.view'],
        'settings' => ['settings.manage'],
        'permissions' => ['permissions.manage']
    ];

    if (!isset($module_permissions[$module])) {
        return false;
    }

    $required_permissions = $module_permissions[$module];
    foreach ($required_permissions as $permission) {
        if (has_permission($permission)) {
            return true;
        }
    }

    return false;
}

/**
 * Get role hierarchy level
 */
function get_role_level(string $role): int
{
    $role_hierarchy = [
        'admin' => 5,
        'manager' => 4,
        'editor' => 3,
        'viewer' => 2,
        'user' => 1
    ];

    return $role_hierarchy[$role] ?? 0;
}

/**
 * Check if user has higher or equal role level
 */
function has_role_level(string $required_role): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    $user_level = get_role_level($user['role'] ?? 'user');
    $required_level = get_role_level($required_role);

    return $user_level >= $required_level;
}

/**
 * Get user department-specific permissions
 */
function get_department_permissions(int $departmentId): array
{
    $user = current_user();
    if (!$user) {
        return [];
    }

    try {
        require_once __DIR__ . '/Core/Services/RolePermissionService.php';
        $roleService = new \BuraqForms\Core\Services\RolePermissionService();
        $permissions = [];

        $all_permissions = $roleService->getUserPermissions($user['id']);
        foreach ($all_permissions as $permission) {
            if ($roleService->hasPermission($user['id'], $permission, $departmentId)) {
                $permissions[] = $permission;
            }
        }

        return $permissions;
    } catch (Exception $e) {
        error_log("Error getting department permissions: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if user can perform action on specific department
 */
function can_perform_on_department(string $action, int $departmentId): bool
{
    $action_permissions = [
        'view' => 'departments.view',
        'manage' => 'departments.manage',
        'edit' => 'departments.edit'
    ];

    $required_permission = $action_permissions[$action] ?? null;
    if (!$required_permission) {
        return false;
    }

    return has_permission($required_permission, $departmentId);
}


/**
 * Generate remember me token
 */
function generate_remember_token(): string
{
    require_once __DIR__ . '/Core/Auth.php';
    return \BuraqForms\Core\Auth::generate_remember_token();
}
