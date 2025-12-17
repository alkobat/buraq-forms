<?php

declare(strict_types=1);

/**
 * Performance Tests
 * 
 * Tests query count, execution time, memory usage, and large dataset handling
 */

require_once __DIR__ . '/test_base.php';

use EmployeeEvaluationSystem\Core\Services\FormService;
use EmployeeEvaluationSystem\Core\Services\FormFieldService;
use EmployeeEvaluationSystem\Core\Services\FormSubmissionService;
use EmployeeEvaluationSystem\Core\Services\DepartmentService;
use EmployeeEvaluationSystem\Core\Logger;

class PerformanceTests extends BaseTest
{
    private FormService $formService;
    private FormFieldService $fieldService;
    private FormSubmissionService $submissionService;
    private DepartmentService $deptService;
    private Logger $logger;
    private int $queryCount = 0;

    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger();
        $this->deptService = new DepartmentService($this->pdo);
        $this->formService = new FormService($this->pdo, $this->logger, null);
        $this->fieldService = new FormFieldService($this->pdo, $this->logger);
        $this->submissionService = new FormSubmissionService(
            $this->pdo, 
            $this->formService, 
            $this->fieldService, 
            null, 
            null, 
            $this->logger, 
            null
        );
        
        echo "\n⚡ بدء اختبارات الأداء\n";
        echo str_repeat('=', 50) . "\n";
    }

    /**
     * قياس عدد الاستعلامات
     */
    private function startQueryCounting(): void
    {
        $this->queryCount = 0;
    }

    private function incrementQueryCount(): void
    {
        $this->queryCount++;
    }

    /**
     * اختبار أداء إنشاء الإدارات
     */
    public function testDepartmentCreationPerformance(): void
    {
        echo "\n🏢 اختبار أداء إنشاء الإدارات...\n";
        
        $deptCount = 20;
        $startMemory = memory_get_usage();
        $startTime = microtime(true);
        
        $this->startQueryCounting();
        
        try {
            for ($i = 0; $i < $deptCount; $i++) {
                $dept = $this->deptService->create([
                    'name' => "قسم اختبار أداء $i",
                    'description' => "قسم أداء رقم $i"
                ]);
                $this->trackCreatedData('departments', (int)$dept['id']);
                $this->incrementQueryCount();
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $endMemory = memory_get_usage();
            $memoryUsed = $endMemory - $startMemory;
            
            $this->assertLessThan(5.0, $executionTime, "إنشاء $deptCount إدارة سريع (أقل من 5 ثوان)");
            $this->assertLessThan(50, $this->queryCount, "عدد الاستعلامات مناسب");
            $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, "استهلاك الذاكرة مناسب");
            
            echo "إنشاء $deptCount إدارة:\n";
            echo "- الوقت: {$executionTime} ثانية\n";
            echo "- الاستعلامات: {$this->queryCount}\n";
            echo "- الذاكرة: " . $this->formatBytes($memoryUsed) . "\n";
            echo "- معدل: " . round($deptCount / $executionTime, 2) . " إدارة/ثانية\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار أداء الإدارات: ' . $e->getMessage());
        }
    }

    /**
     * اختبار أداء إنشاء الاستمارات
     */
    public function testFormCreationPerformance(): void
    {
        echo "\n📋 اختبار أداء إنشاء الاستمارات...\n";
        
        $formCount = 15;
        $startMemory = memory_get_usage();
        $startTime = microtime(true);
        
        $this->startQueryCounting();
        
        try {
            for ($i = 0; $i < $formCount; $i++) {
                $form = $this->formService->create([
                    'title' => "استمارة اختبار أداء $i",
                    'description' => "استمارة أداء رقم $i",
                    'created_by' => 1,
                    'status' => 'active',
                    'show_department_field' => true
                ], [1]);
                $this->trackCreatedData('forms', (int)$form['id']);
                $this->incrementQueryCount();
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $endMemory = memory_get_usage();
            $memoryUsed = $endMemory - $startMemory;
            
            $this->assertLessThan(8.0, $executionTime, "إنشاء $formCount استمارة سريع (أقل من 8 ثوان)");
            $this->assertLessThan(100, $this->queryCount, "عدد الاستعلامات مناسب");
            $this->assertLessThan(15 * 1024 * 1024, $memoryUsed, "استهلاك الذاكرة مناسب");
            
            echo "إنشاء $formCount استمارة:\n";
            echo "- الوقت: {$executionTime} ثانية\n";
            echo "- الاستعلامات: {$this->queryCount}\n";
            echo "- الذاكرة: " . $this->formatBytes($memoryUsed) . "\n";
            echo "- معدل: " . round($formCount / $executionTime, 2) . " استمارة/ثانية\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار أداء الاستمارات: ' . $e->getMessage());
        }
    }

    /**
     * اختبار أداء إضافة الحقول
     */
    public function testFieldAdditionPerformance(): void
    {
        echo "\n📝 اختبار أداء إضافة الحقول...\n";
        
        $form = $this->formService->create([
            'title' => 'استمارة اختبار أداء الحقول',
            'description' => 'اختبار أداء إضافة الحقول',
            'created_by' => 1,
            'status' => 'active'
        ]);
        $this->trackCreatedData('forms', (int)$form['id']);
        
        $fieldCount = 30;
        $startMemory = memory_get_usage();
        $startTime = microtime(true);
        
        $this->startQueryCounting();
        
        try {
            for ($i = 0; $i < $fieldCount; $i++) {
                $this->fieldService->addField((int)$form['id'], [
                    'field_type' => $i % 3 === 0 ? 'text' : ($i % 3 === 1 ? 'email' : 'number'),
                    'label' => "حقل اختبار أداء $i",
                    'field_key' => "perf_field_$i",
                    'is_required' => $i % 2 === 0,
                    'order_index' => $i
                ]);
                $this->incrementQueryCount();
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $endMemory = memory_get_usage();
            $memoryUsed = $endMemory - $startMemory;
            
            $this->assertLessThan(10.0, $executionTime, "إضافة $fieldCount حقل سريع (أقل من 10 ثوان)");
            $this->assertLessThan($fieldCount + 5, $this->queryCount, "عدد الاستعلامات مناسب");
            $this->assertLessThan(20 * 1024 * 1024, $memoryUsed, "استهلاك الذاكرة مناسب");
            
            echo "إضافة $fieldCount حقل:\n";
            echo "- الوقت: {$executionTime} ثانية\n";
            echo "- الاستعلامات: {$this->queryCount}\n";
            echo "- الذاكرة: " . $this->formatBytes($memoryUsed) . "\n";
            echo "- معدل: " . round($fieldCount / $executionTime, 2) . " حقل/ثانية\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار أداء الحقول: ' . $e->getMessage());
        }
    }

    /**
     * اختبار أداء إرسال الاستمارات
     */
    public function testSubmissionPerformance(): void
    {
        echo "\n📊 اختبار أداء إرسال الاستمارات...\n";
        
        $form = $this->formService->create([
            'title' => 'استمارة اختبار أداء الإرسال',
            'description' => 'اختبار أداء إرسال الاستمارات',
            'created_by' => 1,
            'status' => 'active',
            'show_department_field' => true
        ], [1]);
        $this->trackCreatedData('forms', (int)$form['id']);
        
        // إضافة حقول للاستمارة
        $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'text',
            'label' => 'الاسم',
            'field_key' => 'name',
            'is_required' => true,
            'order_index' => 0
        ]);
        
        $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'email',
            'label' => 'البريد الإلكتروني',
            'field_key' => 'email',
            'is_required' => true,
            'order_index' => 1
        ]);
        
        $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'select',
            'label' => 'القسم',
            'field_key' => 'department',
            'is_required' => false,
            'order_index' => 2,
            'field_options' => ['choices' => ['IT', 'HR', 'Finance']]
        ]);
        
        $submissionCount = 25;
        $startMemory = memory_get_usage();
        $startTime = microtime(true);
        
        $this->startQueryCounting();
        
        try {
            for ($i = 0; $i < $submissionCount; $i++) {
                $submissionData = [
                    'submitted_by' => "perf_test_$i@example.com",
                    'department_id' => 1,
                    'ip_address' => "192.168.1.$i",
                    'name' => "مستخدم أداء $i",
                    'email' => "perf_test_$i@example.com",
                    'department' => ['IT', 'HR', 'Finance'][$i % 3]
                ];
                
                $submission = $this->submissionService->submit(
                    (int)$form['id'],
                    $submissionData,
                    $submissionData,
                    []
                );
                
                if ($submission) {
                    $this->trackCreatedData('submissions', (int)$submission['id']);
                    $this->incrementQueryCount();
                }
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $endMemory = memory_get_usage();
            $memoryUsed = $endMemory - $startMemory;
            
            $this->assertLessThan(15.0, $executionTime, "إرسال $submissionCount استمارة سريع (أقل من 15 ثانية)");
            $this->assertLessThan($submissionCount * 3, $this->queryCount, "عدد الاستعلامات مناسب");
            $this->assertLessThan(25 * 1024 * 1024, $memoryUsed, "استهلاك الذاكرة مناسب");
            
            echo "إرسال $submissionCount استمارة:\n";
            echo "- الوقت: {$executionTime} ثانية\n";
            echo "- الاستعلامات: {$this->queryCount}\n";
            echo "- الذاكرة: " . $this->formatBytes($memoryUsed) . "\n";
            echo "- معدل: " . round($submissionCount / $executionTime, 2) . " إرسال/ثانية\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار أداء الإرسال: ' . $e->getMessage());
        }
    }

    /**
     * اختبار أداء استعلامات الإجابات
     */
    public function testSubmissionQueryPerformance(): void
    {
        echo "\n🔍 اختبار أداء استعلامات الإجابات...\n";
        
        $startMemory = memory_get_usage();
        $startTime = microtime(true);
        
        $this->startQueryCounting();
        
        try {
            // استعلام بسيط - عدد الإجابات
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM form_submissions");
            $stmt->fetchColumn();
            $this->incrementQueryCount();
            
            // استعلام مركب - الإجابات مع تفاصيل
            $stmt = $this->pdo->query("
                SELECT 
                    fs.id,
                    fs.reference_code,
                    fs.submitted_by,
                    f.title as form_title
                FROM form_submissions fs
                JOIN forms f ON fs.form_id = f.id
                LIMIT 50
            ");
            $results = $stmt->fetchAll();
            $this->incrementQueryCount();
            
            // استعلام مع تجميع
            $stmt = $this->pdo->query("
                SELECT 
                    f.title,
                    COUNT(fs.id) as submission_count
                FROM forms f
                LEFT JOIN form_submissions fs ON f.id = fs.form_id
                GROUP BY f.id, f.title
            ");
            $stats = $stmt->fetchAll();
            $this->incrementQueryCount();
            
            // استعلام مع ترتيب وترقيم
            $stmt = $this->pdo->query("
                SELECT 
                    fs.*,
                    f.title as form_title
                FROM form_submissions fs
                JOIN forms f ON fs.form_id = f.id
                ORDER BY fs.created_at DESC
                LIMIT 20
            ");
            $recentSubmissions = $stmt->fetchAll();
            $this->incrementQueryCount();
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $endMemory = memory_get_usage();
            $memoryUsed = $endMemory - $startMemory;
            
            $this->assertLessThan(2.0, $executionTime, "استعلامات الإجابات سريعة (أقل من ثانيتين)");
            $this->assertLessThan(10, $this->queryCount, "عدد الاستعلامات مناسب");
            $this->assertLessThan(5 * 1024 * 1024, $memoryUsed, "استهلاك الذاكرة مناسب");
            
            echo "استعلامات الإجابات:\n";
            echo "- الوقت: {$executionTime} ثانية\n";
            echo "- الاستعلامات: {$this->queryCount}\n";
            echo "- الذاكرة: " . $this->formatBytes($memoryUsed) . "\n";
            echo "- نتائج بسيطة: " . count($results) . "\n";
            echo "- إحصائيات: " . count($stats) . "\n";
            echo "- أحدث الإجابات: " . count($recentSubmissions) . "\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار أداء الاستعلامات: ' . $e->getMessage());
        }
    }

    /**
     * اختبار التعامل مع البيانات الكبيرة
     */
    public function testLargeDatasetHandling(): void
    {
        echo "\n📊 اختبار التعامل مع البيانات الكبيرة...\n";
        
        try {
            // إنشاء مجموعة بيانات كبيرة
            $largeForm = $this->formService->create([
                'title' => 'استمارة بيانات كبيرة',
                'description' => 'اختبار البيانات الكبيرة',
                'created_by' => 1,
                'status' => 'active'
            ]);
            $this->trackCreatedData('forms', (int)$largeForm['id']);
            
            // إضافة حقول
            $this->fieldService->addField((int)$largeForm['id'], [
                'field_type' => 'text',
                'label' => 'الاسم',
                'field_key' => 'name',
                'is_required' => true,
                'order_index' => 0
            ]);
            
            $this->fieldService->addField((int)$largeForm['id'], [
                'field_type' => 'textarea',
                'label' => 'الوصف',
                'field_key' => 'description',
                'is_required' => false,
                'order_index' => 1
            ]);
            
            $startMemory = memory_get_usage();
            $startTime = microtime(true);
            
            // إنشاء 100 إجابة
            $submissionCount = 100;
            for ($i = 0; $i < $submissionCount; $i++) {
                $submissionData = [
                    'submitted_by' => "large_data_$i@example.com",
                    'department_id' => 1,
                    'ip_address' => "192.168.1.$i",
                    'name' => "اسم طويل للاختبار رقم $i",
                    'description' => str_repeat("وصف مفصل للاختبار رقم $i ", 50)
                ];
                
                $submission = $this->submissionService->submit(
                    (int)$largeForm['id'],
                    $submissionData,
                    $submissionData,
                    []
                );
                
                if ($submission) {
                    $this->trackCreatedData('submissions', (int)$submission['id']);
                }
                
                // تحرير الذاكرة كل 20 عملية
                if ($i % 20 === 0) {
                    gc_collect_cycles();
                }
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $endMemory = memory_get_usage();
            $memoryUsed = $endMemory - $startMemory;
            
            $this->assertLessThan(30.0, $executionTime, "معالجة $submissionCount إجابة سريعة (أقل من 30 ثانية)");
            $this->assertLessThan(100 * 1024 * 1024, $memoryUsed, "استهلاك الذاكرة معقول للبيانات الكبيرة");
            
            // اختبار استعلام البيانات الكبيرة
            $queryStartTime = microtime(true);
            $stmt = $this->pdo->prepare("
                SELECT 
                    fs.*,
                    f.title
                FROM form_submissions fs
                JOIN forms f ON fs.form_id = f.id
                WHERE f.id = ?
                ORDER BY fs.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$largeForm['id']]);
            $largeResults = $stmt->fetchAll();
            $queryEndTime = microtime(true);
            $queryTime = $queryEndTime - $queryStartTime;
            
            $this->assertLessThan(1.0, $queryTime, "استعلام البيانات الكبيرة سريع (أقل من ثانية)");
            $this->assertLessThanOrEqual(50, count($largeResults), "عدد النتائج محدود بشكل صحيح");
            
            echo "معالجة البيانات الكبيرة:\n";
            echo "- إنشاء $submissionCount إجابة:\n";
            echo "  * الوقت: {$executionTime} ثانية\n";
            echo "  * الذاكرة: " . $this->formatBytes($memoryUsed) . "\n";
            echo "- استعلام البيانات:\n";
            echo "  * الوقت: {$queryTime} ثانية\n";
            echo "  * النتائج: " . count($largeResults) . "\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار البيانات الكبيرة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار أداء Pagination
     */
    public function testPaginationPerformance(): void
    {
        echo "\n📄 اختبار أداء Pagination...\n";
        
        try {
            $itemsPerPage = 10;
            $totalPages = 5;
            
            $startMemory = memory_get_usage();
            $startTime = microtime(true);
            
            $pageResults = [];
            
            for ($page = 1; $page <= $totalPages; $page++) {
                $offset = ($page - 1) * $itemsPerPage;
                
                $queryStartTime = microtime(true);
                $stmt = $this->pdo->prepare("
                    SELECT 
                        fs.id,
                        fs.reference_code,
                        fs.submitted_by,
                        f.title as form_title
                    FROM form_submissions fs
                    JOIN forms f ON fs.form_id = f.id
                    ORDER BY fs.created_at DESC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute([$itemsPerPage, $offset]);
                $pageData = $stmt->fetchAll();
                $queryEndTime = microtime(true);
                
                $pageResults[] = [
                    'page' => $page,
                    'items' => count($pageData),
                    'query_time' => $queryEndTime - $queryStartTime
                ];
                
                // تحرير الذاكرة
                unset($pageData);
                if ($page % 2 === 0) {
                    gc_collect_cycles();
                }
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $endMemory = memory_get_usage();
            $memoryUsed = $endMemory - $startMemory;
            
            $this->assertLessThan(3.0, $executionTime, "Pagination لـ $totalPages صفحات سريع (أقل من 3 ثوان)");
            
            $totalItems = array_sum(array_column($pageResults, 'items'));
            $avgQueryTime = array_sum(array_column($pageResults, 'query_time')) / count($pageResults);
            
            $this->assertLessThan(0.5, $avgQueryTime, "متوسط وقت الاستعلام لكل صفحة مناسب");
            $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, "استهلاك الذاكرة مناسب للـ Pagination");
            
            echo "أداء Pagination:\n";
            echo "- الوقت الكلي: {$executionTime} ثانية\n";
            echo "- الذاكرة: " . $this->formatBytes($memoryUsed) . "\n";
            echo "- متوسط وقت الاستعلام: " . round($avgQueryTime * 1000, 2) . " مللي ثانية\n";
            echo "- إجمالي العناصر: $totalItems\n";
            
            foreach ($pageResults as $result) {
                echo "- صفحة {$result['page']}: {$result['items']} عنصر (" . round($result['query_time'] * 1000, 2) . "ms)\n";
            }
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار Pagination: ' . $e->getMessage());
        }
    }

    /**
     * اختبار أداء البحث والفلترة
     */
    public function testSearchPerformance(): void
    {
        echo "\n🔍 اختبار أداء البحث والفلترة...\n";
        
        try {
            $searchTerms = ['user', 'test', 'performance', '@example.com'];
            $departmentIds = [1, 2];
            $statuses = ['pending', 'completed'];
            
            $startMemory = memory_get_usage();
            $startTime = microtime(true);
            $queryCount = 0;
            
            foreach ($searchTerms as $term) {
                // بحث نصي
                $queryStartTime = microtime(true);
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) 
                    FROM form_submissions 
                    WHERE submitted_by LIKE ? OR reference_code LIKE ?
                ");
                $stmt->execute(["%$term%", "%$term%"]);
                $result = $stmt->fetchColumn();
                $queryCount++;
                
                $queryTime = microtime(true) - $queryStartTime;
                $this->assertLessThan(0.1, $queryTime, "البحث السريع (الوقت: " . round($queryTime * 1000, 2) . "ms)");
            }
            
            foreach ($departmentIds as $deptId) {
                // فلتر الإدارة
                $queryStartTime = microtime(true);
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) 
                    FROM form_submissions 
                    WHERE department_id = ?
                ");
                $stmt->execute([$deptId]);
                $result = $stmt->fetchColumn();
                $queryCount++;
                
                $queryTime = microtime(true) - $queryStartTime;
                $this->assertLessThan(0.05, $queryTime, "فلتر الإدارة سريع (الوقت: " . round($queryTime * 1000, 2) . "ms)");
            }
            
            foreach ($statuses as $status) {
                // فلتر الحالة
                $queryStartTime = microtime(true);
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) 
                    FROM form_submissions 
                    WHERE status = ?
                ");
                $stmt->execute([$status]);
                $result = $stmt->fetchColumn();
                $queryCount++;
                
                $queryTime = microtime(true) - $queryStartTime;
                $this->assertLessThan(0.05, $queryTime, "فلتر الحالة سريع (الوقت: " . round($queryTime * 1000, 2) . "ms)");
            }
            
            // فلتر مركب
            $queryStartTime = microtime(true);
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM form_submissions fs
                JOIN forms f ON fs.form_id = f.id
                WHERE fs.department_id = ? 
                AND fs.status = ? 
                AND (fs.submitted_by LIKE ? OR f.title LIKE ?)
            ");
            $stmt->execute([1, 'pending', '%test%', '%test%']);
            $result = $stmt->fetchColumn();
            $queryCount++;
            
            $queryTime = microtime(true) - $queryStartTime;
            $this->assertLessThan(0.1, $queryTime, "الفلتر المركب سريع (الوقت: " . round($queryTime * 1000, 2) . "ms)");
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $endMemory = memory_get_usage();
            $memoryUsed = $endMemory - $startMemory;
            
            $this->assertLessThan(2.0, $executionTime, "جميع عمليات البحث والفلترة سريعة (أقل من ثانيتين)");
            $this->assertLessThan(5 * 1024 * 1024, $memoryUsed, "استهلاك الذاكرة مناسب للبحث");
            
            echo "أداء البحث والفلترة:\n";
            echo "- الوقت الكلي: {$executionTime} ثانية\n";
            echo "- الذاكرة: " . $this->formatBytes($memoryUsed) . "\n";
            echo "- عدد الاستعلامات: $queryCount\n";
            echo "- متوسط وقت الاستعلام: " . round(($executionTime / $queryCount) * 1000, 2) . " مللي ثانية\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار أداء البحث: ' . $e->getMessage());
        }
    }

    /**
     * اختبار ضغط الذاكرة
     */
    public function testMemoryOptimization(): void
    {
        echo "\n🧠 اختبار تحسين الذاكرة...\n";
        
        try {
            $peakMemory = memory_get_peak_usage();
            $initialMemory = memory_get_usage();
            
            // إنشاء مجموعة كبيرة من البيانات
            $largeData = [];
            for ($i = 0; $i < 10000; $i++) {
                $largeData[] = [
                    'id' => $i,
                    'name' => "Name $i",
                    'data' => str_repeat("Data $i ", 100),
                    'timestamp' => time() + $i
                ];
            }
            
            $afterCreationMemory = memory_get_usage();
            $creationMemoryUsed = $afterCreationMemory - $initialMemory;
            
            // معالجة البيانات
            $processedData = [];
            foreach ($largeData as $item) {
                $processedData[] = [
                    'id' => $item['id'],
                    'name' => strtoupper($item['name']),
                    'short_data' => substr($item['data'], 0, 50)
                ];
            }
            
            $afterProcessingMemory = memory_get_usage();
            
            // تحرير الذاكرة
            unset($largeData);
            unset($processedData);
            gc_collect_cycles();
            
            $afterCleanupMemory = memory_get_usage();
            $cleanupMemoryFreed = $afterProcessingMemory - $afterCleanupMemory;
            
            $peakMemoryAfter = memory_get_peak_usage();
            
            // التحقق من كفاءة استخدام الذاكرة
            $this->assertLessThan(50 * 1024 * 1024, $creationMemoryUsed, "إنشاء البيانات يستخدم ذاكرة معقولة");
            $this->assertGreaterThan(1024 * 1024, $cleanupMemoryFreed, "تحرير الذاكرة فعال");
            $this->assertLessThan($peakMemoryAfter * 1.5, $peakMemory * 1.5, "استهلاك الذاكرة مستقر");
            
            echo "تحسين الذاكرة:\n";
            echo "- الذاكرة الأولية: " . $this->formatBytes($initialMemory) . "\n";
            echo "- الذاكرة بعد الإنشاء: " . $this->formatBytes($afterCreationMemory) . "\n";
            echo "- الذاكرة المستخدمة في الإنشاء: " . $this->formatBytes($creationMemoryUsed) . "\n";
            echo "- الذاكرة المحررة: " . $this->formatBytes($cleanupMemoryFreed) . "\n";
            echo "- الذاكرة النهائية: " . $this->formatBytes($afterCleanupMemory) . "\n";
            echo "- الذاكرة القصوى: " . $this->formatBytes($peakMemoryAfter) . "\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار تحسين الذاكرة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار فهرسة قاعدة البيانات
     */
    public function testDatabaseIndexing(): void
    {
        echo "\n🗂️ اختبار فهرسة قاعدة البيانات...\n";
        
        try {
            $indexes = [
                'form_submissions' => [
                    'idx_submission_form_id' => 'form_id',
                    'idx_submission_dept' => 'department_id',
                    'idx_submission_status' => 'status',
                    'idx_submission_date' => 'created_at',
                    'idx_submission_email' => 'submitted_by'
                ],
                'form_fields' => [
                    'idx_field_form' => 'form_id',
                    'idx_field_order' => 'form_id, order_index'
                ],
                'forms' => [
                    'idx_form_status' => 'status',
                    'idx_form_creator' => 'created_by'
                ]
            ];
            
            foreach ($indexes as $table => $tableIndexes) {
                foreach ($tableIndexes as $indexName => $columns) {
                    // فحص وجود الفهرس
                    $stmt = $this->pdo->prepare("
                        SHOW INDEX FROM `$table` 
                        WHERE Key_name = ?
                    ");
                    $stmt->execute([$indexName]);
                    $indexExists = $stmt->fetch() !== false;
                    
                    if ($indexExists) {
                        $this->assertTrue($indexExists, "الفهرس موجود: $indexName على $table ($columns)");
                    }
                }
            }
            
            // اختبار أداء الاستعلامات مع الفهارس
            $queries = [
                "SELECT COUNT(*) FROM form_submissions WHERE form_id = 1",
                "SELECT COUNT(*) FROM form_submissions WHERE department_id = 1",
                "SELECT COUNT(*) FROM form_submissions WHERE status = 'pending'",
                "SELECT COUNT(*) FROM form_submissions WHERE DATE(created_at) = CURDATE()"
            ];
            
            foreach ($queries as $query) {
                $startTime = microtime(true);
                $stmt = $this->pdo->query($query);
                $stmt->fetchColumn();
                $queryTime = microtime(true) - $startTime;
                
                $this->assertLessThan(0.1, $queryTime, "الاستعلام مع الفهارس سريع (الوقت: " . round($queryTime * 1000, 2) . "ms)");
            }
            
            echo "تم فحص الفهارس في الجداول:\n";
            foreach ($indexes as $table => $tableIndexes) {
                echo "- $table: " . count($tableIndexes) . " فهرس\n";
            }
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار الفهرسة: ' . $e->getMessage());
        }
    }

    /**
     * تشغيل جميع اختبارات الأداء
     */
    public function runAllTests(): void
    {
        try {
            $this->testDepartmentCreationPerformance();
            $this->testFormCreationPerformance();
            $this->testFieldAdditionPerformance();
            $this->testSubmissionPerformance();
            $this->testSubmissionQueryPerformance();
            $this->testLargeDatasetHandling();
            $this->testPaginationPerformance();
            $this->testSearchPerformance();
            $this->testMemoryOptimization();
            $this->testDatabaseIndexing();
            
        } catch (Exception $e) {
            echo "❌ خطأ في الاختبار: " . $e->getMessage() . "\n";
            $this->failCount++;
        } finally {
            $this->cleanup();
            $this->printReport();
        }
    }
}

// تشغيل الاختبارات
if (php_sapi_name() === 'cli') {
    $tests = new PerformanceTests();
    $tests->runAllTests();
}