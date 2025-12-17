<?php

declare(strict_types=1);

/**
 * Base Test Class
 * 
 * Provides common functionality for all test classes
 */

require __DIR__ . '/../vendor/autoload.php';

use EmployeeEvaluationSystem\Core\Database;
use EmployeeEvaluationSystem\Core\Logger;

class BaseTest
{
    protected PDO $pdo;
    protected Logger $logger;
    protected array $testResults = [];
    protected array $createdData = [];
    protected int $testCount = 0;
    protected int $passCount = 0;
    protected int $failCount = 0;

    public function __construct()
    {
        try {
            $this->pdo = Database::getConnection();
            $this->logger = new Logger();
        } catch (Exception $e) {
            die("❌ فشل الاتصال بقاعدة البيانات: " . $e->getMessage() . "\n");
        }
    }

    /**
     * تسجيل نتيجة اختبار
     */
    protected function assert($condition, string $testName, string $message = ''): bool
    {
        $this->testCount++;
        $status = $condition ? 'PASS' : 'FAIL';
        
        if ($condition) {
            $this->passCount++;
        } else {
            $this->failCount++;
        }

        $result = [
            'name' => $testName,
            'status' => $status,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->testResults[] = $result;
        
        echo sprintf("[%s] %s: %s\n", $status, $testName, $message);
        
        return $condition;
    }

    /**
     * تسجيل بيانات الاختبار للتنظيف لاحقاً
     */
    protected function trackCreatedData(string $type, int $id): void
    {
        if (!isset($this->createdData[$type])) {
            $this->createdData[$type] = [];
        }
        $this->createdData[$type][] = $id;
    }

    /**
     * تنظيف البيانات المُنشأة أثناء الاختبار
     */
    protected function cleanup(): void
    {
        foreach ($this->createdData as $type => $ids) {
            foreach ($ids as $id) {
                try {
                    switch ($type) {
                        case 'forms':
                            $this->pdo->exec("DELETE FROM forms WHERE id = $id");
                            break;
                        case 'departments':
                            $this->pdo->exec("DELETE FROM departments WHERE id = $id");
                            break;
                        case 'submissions':
                            $this->pdo->exec("DELETE FROM form_submissions WHERE id = $id");
                            break;
                        case 'fields':
                            $this->pdo->exec("DELETE FROM form_fields WHERE id = $id");
                            break;
                    }
                } catch (Exception $e) {
                    // تجاهل أخطاء الحذف أثناء التنظيف
                }
            }
        }
        $this->createdData = [];
    }

    /**
     * طباعة تقرير الاختبار
     */
    protected function printReport(): void
    {
        echo "\n=== تقرير نتائج الاختبار ===\n";
        echo "إجمالي الاختبارات: {$this->testCount}\n";
        echo "نجح: {$this->passCount}\n";
        echo "فشل: {$this->failCount}\n";
        echo "معدل النجاح: " . round(($this->passCount / max($this->testCount, 1)) * 100, 2) . "%\n";
        
        if ($this->failCount > 0) {
            echo "\n🔴 الاختبارات الفاشلة:\n";
            foreach ($this->testResults as $result) {
                if ($result['status'] === 'FAIL') {
                    echo "- {$result['name']}: {$result['message']}\n";
                }
            }
        }
        
        if ($this->passCount === $this->testCount) {
            echo "\n🎉 جميع الاختبارات نجحت!\n";
        }
    }

    /**
     * إنشاء department للاختبار
     */
    protected function createTestDepartment(string $name = 'قسم اختبار'): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO departments (name, description, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$name, 'قسم اختبار تم إنشاؤه تلقائياً']);
        $id = $this->pdo->lastInsertId();
        $this->trackCreatedData('departments', $id);
        return (int)$id;
    }

    /**
     * إنشاء form للاختبار
     */
    protected function createTestForm(string $title = 'استمارة اختبار'): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO forms (title, slug, description, created_by, status, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $slug = strtolower(str_replace(' ', '-', $title)) . '-' . time();
        $stmt->execute([$title, $slug, 'استمارة اختبار', 1, 'active']);
        $id = $this->pdo->lastInsertId();
        $this->trackCreatedData('forms', $id);
        return (int)$id;
    }

    /**
     * إضافة field للاختبار
     */
    protected function createTestField(int $formId, array $fieldData = []): int
    {
        $defaults = [
            'form_id' => $formId,
            'field_type' => 'text',
            'label' => 'حقل اختبار',
            'field_key' => 'test_field_' . time(),
            'is_required' => false,
            'order_index' => 0,
            'validation_rules' => null,
            'field_options' => null
        ];
        
        $data = array_merge($defaults, $fieldData);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO form_fields (form_id, field_type, label, field_key, is_required, order_index, validation_rules, field_options)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['form_id'],
            $data['field_type'],
            $data['label'],
            $data['field_key'],
            $data['is_required'],
            $data['order_index'],
            $data['validation_rules'] ? json_encode($data['validation_rules']) : null,
            $data['field_options'] ? json_encode($data['field_options']) : null
        ]);
        
        $id = $this->pdo->lastInsertId();
        $this->trackCreatedData('fields', $id);
        return (int)$id;
    }

    /**
     * إنشاء submission للاختبار
     */
    protected function createTestSubmission(int $formId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO form_submissions (form_id, reference_code, submitted_by, department_id, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $reference = 'REF-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $stmt->execute([$formId, $reference, 'test@example.com', 1, '127.0.0.1']);
        $id = $this->pdo->lastInsertId();
        $this->trackCreatedData('submissions', $id);
        return (int)$id;
    }

    /**
     * اختبار صحة البيانات
     */
    protected function assertEquals($expected, $actual, string $message = ''): bool
    {
        return $this->assert($expected === $actual, 'assertEquals', $message . " (متوقع: $expected, فعلي: $actual)");
    }

    protected function assertTrue($condition, string $message = ''): bool
    {
        return $this->assert($condition === true, 'assertTrue', $message);
    }

    protected function assertFalse($condition, string $message = ''): bool
    {
        return $this->assert($condition === false, 'assertFalse', $message);
    }

    protected function assertNotNull($value, string $message = ''): bool
    {
        return $this->assert($value !== null, 'assertNotNull', $message);
    }

    protected function assertGreaterThan($expected, $actual, string $message = ''): bool
    {
        return $this->assert($actual > $expected, 'assertGreaterThan', $message . " ($actual > $expected)");
    }

    protected function assertLessThanOrEqual($expected, $actual, string $message = ''): bool
    {
        return $this->assert($actual <= $expected, 'assertLessThanOrEqual', $message . " ($actual <= $expected)");
    }

    protected function assertLessThan($expected, $actual, string $message = ''): bool
    {
        return $this->assert($actual < $expected, 'assertLessThan', $message . " ($actual < $expected)");
    }

    /**
     * قياس وقت التنفيذ
     */
    protected function measureTime(callable $callback): float
    {
        $start = microtime(true);
        $callback();
        return microtime(true) - $start;
    }

    /**
     * قياس استهلاك الذاكرة
     */
    protected function measureMemory(): string
    {
        $memory = memory_get_peak_usage(true);
        return $this->formatBytes($memory);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}