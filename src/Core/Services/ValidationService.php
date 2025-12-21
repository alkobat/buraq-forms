<?php

declare(strict_types=1);

namespace BuraqForms\Core\Services;

// Load Logger class directly to avoid autoloading issues
require_once __DIR__ . '/../Logger.php';

use BuraqForms\Core\Database;
use PDO;
use Exception;

/**
 * خدمة التحقق المتقدم من البيانات (Advanced Validation)
 */
class ValidationService
{
    private PDO $db;
    private Logger $logger;

    public function __construct(Database $database = null, Logger $logger = null)
    {
        $this->db = $database?->getConnection() ?? Database::getConnection();
        $this->logger = $logger ?? new Logger();
    }

    /**
     * تحقق فوري من حقل عبر AJAX
     */
    public function validateFieldAjax(array $fieldData): array
    {
        $fieldName = $fieldData['field_name'] ?? '';
        $fieldValue = $fieldData['field_value'] ?? '';
        $fieldType = $fieldData['field_type'] ?? 'text';
        $validationRules = $fieldData['validation_rules'] ?? [];

        $errors = [];
        $warnings = [];

        // التحقق من الحقول المطلوبة
        if (($fieldData['is_required'] ?? false) && empty($fieldValue)) {
            $errors[] = 'هذا الحقل مطلوب';
            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        // التحقق من نوع البيانات
        switch ($fieldType) {
            case 'email':
                if (!empty($fieldValue) && !filter_var($fieldValue, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'البريد الإلكتروني غير صحيح';
                }
                break;

            case 'number':
                if (!empty($fieldValue) && !is_numeric($fieldValue)) {
                    $errors[] = 'يجب أن يكون رقم';
                }
                break;

            case 'phone':
                if (!empty($fieldValue) && !$this->validatePhone($fieldValue)) {
                    $errors[] = 'رقم الهاتف غير صحيح';
                }
                break;

            case 'url':
                if (!empty($fieldValue) && !filter_var($fieldValue, FILTER_VALIDATE_URL)) {
                    $errors[] = 'رابط ويب غير صحيح';
                }
                break;
        }

        // التحقق من قواعد التحقق المخصصة
        if (!empty($validationRules)) {
            $ruleResults = $this->applyValidationRules($fieldName, $fieldValue, $validationRules);
            $errors = array_merge($errors, $ruleResults['errors']);
            $warnings = array_merge($warnings, $ruleResults['warnings']);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * تحقق شامل من بيانات الاستمارة
     */
    public function validateFormSubmission(array $formData, array $fieldDefinitions): array
    {
        $errors = [];
        $warnings = [];

        foreach ($fieldDefinitions as $field) {
            $fieldName = $field['field_key'] ?? '';
            $fieldValue = $formData[$fieldName] ?? null;

            // التحقق من الحقل إذا كان مطلوب
            if ($field['is_required'] && (is_null($fieldValue) || $fieldValue === '')) {
                $errors[$fieldName][] = 'هذا الحقل مطلوب';
                continue;
            }

            // التحقق من نوع البيانات
            $typeResult = $this->validateFieldByType($field, $fieldValue);
            if ($typeResult['errors']) {
                $errors[$fieldName] = array_merge($errors[$fieldName] ?? [], $typeResult['errors']);
            }
            if ($typeResult['warnings']) {
                $warnings[$fieldName] = array_merge($warnings[$fieldName] ?? [], $typeResult['warnings']);
            }

            // التحقق من قواعد التحقق المخصصة
            if (isset($field['validation_rules']) && !empty($field['validation_rules'])) {
                $rulesResult = $this->applyValidationRules($fieldName, $fieldValue, $field['validation_rules']);
                if ($rulesResult['errors']) {
                    $errors[$fieldName] = array_merge($errors[$fieldName] ?? [], $rulesResult['errors']);
                }
                if ($rulesResult['warnings']) {
                    $warnings[$fieldName] = array_merge($warnings[$fieldName] ?? [], $rulesResult['warnings']);
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * تنظيف المدخلات (Sanitization)
     */
    public function sanitizeInput(string $input, string $type = 'string'): string
    {
        switch ($type) {
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            
            case 'number':
                return filter_var(trim($input), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            
            case 'int':
                return filter_var(trim($input), FILTER_SANITIZE_NUMBER_INT);
            
            case 'text':
            case 'string':
            default:
                $sanitized = trim($input);
                $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
                return $sanitized;
        }
    }

    /**
     * تنظيف مصفوفة المدخلات
     */
    public function sanitizeArray(array $data, array $fieldTypes): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $fieldType = $fieldTypes[$key] ?? 'string';
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value, $fieldTypes);
            } else {
                $sanitized[$key] = $this->sanitizeInput((string) $value, $fieldType);
            }
        }
        return $sanitized;
    }

    /**
     * التحقق من صحة رقم الهاتف
     */
    private function validatePhone(string $phone): bool
    {
        // تحقق من رقم الهاتف السعودي بشكل أساسي
        $saudiPhonePattern = '/^(\+966|966|0)?[5][0-9]{8}$/';
        return preg_match($saudiPhonePattern, str_replace([' ', '-', '(', ')'], '', $phone)) === 1;
    }

    /**
     * التحقق من الحقل حسب نوعه
     */
    private function validateFieldByType(array $field, $value): array
    {
        $errors = [];
        $warnings = [];

        if (is_null($value) || $value === '') {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $fieldType = $field['field_type'] ?? 'text';

        switch ($fieldType) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'البريد الإلكتروني غير صحيح';
                }
                break;

            case 'number':
                if (!is_numeric($value)) {
                    $errors[] = 'يجب أن يكون رقم';
                }
                break;

            case 'phone':
                if (!$this->validatePhone($value)) {
                    $errors[] = 'رقم الهاتف غير صحيح';
                }
                break;

            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = 'رابط ويب غير صحيح';
                }
                break;

            case 'select':
            case 'radio':
                $validOptions = array_map(fn($option) => $option['value'], $field['options'] ?? []);
                if (!in_array($value, $validOptions)) {
                    $errors[] = 'خيار غير صحيح';
                }
                break;

            case 'checkbox':
                if (is_array($value)) {
                    $validOptions = array_map(fn($option) => $option['value'], $field['options'] ?? []);
                    foreach ($value as $selectedValue) {
                        if (!in_array($selectedValue, $validOptions)) {
                            $errors[] = 'خيار غير صحيح';
                            break;
                        }
                    }
                }
                break;

            case 'file':
                if (!empty($field['is_required']) && (!isset($field['files'][$field['field_key']]) || $field['files'][$field['field_key']]['error'] !== UPLOAD_ERR_OK)) {
                    $errors[] = 'يجب اختيار ملف';
                }
                break;
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * تطبيق قواعد التحقق المخصصة
     */
    private function applyValidationRules(string $fieldName, $value, array $rules): array
    {
        $errors = [];
        $warnings = [];

        if (is_null($value) || $value === '') {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $stringValue = is_array($value) ? json_encode($value) : (string) $value;

        // طول النص
        if (isset($rules['min_length']) && mb_strlen($stringValue, 'UTF-8') < (int) $rules['min_length']) {
            $errors[] = "يجب أن يكون النص أكثر من " . $rules['min_length'] . " أحرف";
        }

        if (isset($rules['max_length']) && mb_strlen($stringValue, 'UTF-8') > (int) $rules['max_length']) {
            $errors[] = "يجب أن يكون النص أقل من " . $rules['max_length'] . " أحرف";
        }

        // القيم الرقمية
        if (isset($rules['min']) && is_numeric($value) && (float) $value < (float) $rules['min']) {
            $errors[] = "يجب أن يكون أكبر من أو يساوي " . $rules['min'];
        }

        if (isset($rules['max']) && is_numeric($value) && (float) $value > (float) $rules['max']) {
            $errors[] = "يجب أن يكون أصغر من أو يساوي " . $rules['max'];
        }

        // التعبير النمطي (Regex)
        if (!empty($rules['regex'])) {
            $pattern = $rules['regex'];
            if (@preg_match($pattern, '') !== false) {
                if (!preg_match($pattern, $stringValue)) {
                    $errors[] = $rules['regex_message'] ?? 'تنسيق البيانات غير صحيح';
                }
            }
        }

        // التحقق من التكرار
        if (isset($rules['unique']) && $rules['unique']) {
            if ($this->isValueUnique($fieldName, $stringValue)) {
                $warnings[] = 'هذه القيمة مستخدمة من قبل';
            }
        }

        // التحقق من النطاق (للتواريخ)
        if (!empty($rules['date_range'])) {
            $range = $rules['date_range'];
            if (isset($range['min']) && !empty($range['min'])) {
                if (strtotime($stringValue) < strtotime($range['min'])) {
                    $errors[] = "التاريخ يجب أن يكون بعد " . $range['min'];
                }
            }
            if (isset($range['max']) && !empty($range['max'])) {
                if (strtotime($stringValue) > strtotime($range['max'])) {
                    $errors[] = "التاريخ يجب أن يكون قبل " . $range['max'];
                }
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * التحقق من تفرد القيمة
     */
    private function isValueUnique(string $fieldName, string $value): bool
    {
        try {
            // هذا مثال بسيط، في التطبيق الحقيقي يجب تحديد الجدول والحقل بدقة
            $sql = "SELECT COUNT(*) as count FROM form_submissions WHERE JSON_EXTRACT(form_data, ?) = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(["$.$fieldName", $value]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int) $result['count'] > 0;
        } catch (Exception $e) {
            $this->logger->error("Error checking unique value: " . $e->getMessage());
            return false;
        }
    }

    /**
     * التحقق من صحة الملف المرفوع
     */
    public function validateUploadedFile(array $file, array $rules = []): array
    {
        $errors = [];
        $warnings = [];

        // التحقق من وجود الملف
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'لم يتم رفع أي ملف';
            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        // التحقق من حجم الملف
        if (isset($rules['max_size']) && $file['size'] > $rules['max_size']) {
            $maxSizeMB = round($rules['max_size'] / 1024 / 1024, 2);
            $errors[] = "حجم الملف كبير جداً. الحد الأقصى هو {$maxSizeMB} MB";
        }

        // التحقق من نوع الملف
        if (!empty($rules['allowed_types'])) {
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExtension, $rules['allowed_types'])) {
                $errors[] = "نوع الملف غير مسموح. الأنواع المسموحة: " . implode(', ', $rules['allowed_types']);
            }
        }

        // التحقق من MIME Type
        if (isset($rules['allowed_mime_types'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $rules['allowed_mime_types'])) {
                $errors[] = 'نوع الملف غير صحيح';
            }
        }

        // فحص الملف للفيروسات (إذا كان مكون الحماية متوفر)
        if (function_exists('clamav_scan_file')) {
            $scanResult = clamav_scan_file($file['tmp_name']);
            if ($scanResult !== true) {
                $errors[] = 'الملف قد يحتوي على فيروس';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * إضافة قواعد تحقق مخصصة
     */
    public function addCustomValidationRule(string $name, callable $callback, string $errorMessage): bool
    {
        try {
            // حفظ قاعدة التحقق في قاعدة البيانات أو ذاكرة التخزين المؤقت
            // هذا مثال بسيط، في التطبيق الحقيقي يجب تخزينها في قاعدة البيانات
            return true;
        } catch (Exception $e) {
            $this->logger->error("Error adding custom validation rule: " . $e->getMessage());
            return false;
        }
    }
}