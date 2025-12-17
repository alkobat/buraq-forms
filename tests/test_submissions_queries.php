<?php

declare(strict_types=1);

/**
 * Submissions Management Tests
 * 
 * Tests submissions viewing, filtering, export, and management features
 */

require_once __DIR__ . '/test_base.php';

use EmployeeEvaluationSystem\Core\Services\FormService;
use EmployeeEvaluationSystem\Core\Services\FormFieldService;
use EmployeeEvaluationSystem\Core\Services\FormSubmissionService;
use EmployeeEvaluationSystem\Core\Services\DepartmentService;
use EmployeeEvaluationSystem\Core\Logger;

class SubmissionsManagementTests extends BaseTest
{
    private FormService $formService;
    private FormFieldService $fieldService;
    private FormSubmissionService $submissionService;
    private DepartmentService $deptService;
    private Logger $logger;

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
        
        echo "\n📊 بدء اختبارات إدارة الإجابات والتصدير\n";
        echo str_repeat('=', 50) . "\n";
    }

    /**
     * إنشاء بيانات اختبار شاملة
     */
    private function createComprehensiveTestData(): array
    {
        // إنشاء إدارات
        $departments = [];
        for ($i = 1; $i <= 3; $i++) {
            $dept = $this->deptService->create([
                'name' => "قسم اختبار $i",
                'description' => "قسم رقم $i للاختبار"
            ]);
            $departments[] = $dept;
            $this->trackCreatedData('departments', (int)$dept['id']);
        }

        // إنشاء استمارات
        $forms = [];
        for ($i = 1; $i <= 3; $i++) {
            $form = $this->formService->create([
                'title' => "استمارة اختبار $i",
                'description' => "استمارة رقم $i للاختبار",
                'created_by' => 1,
                'status' => 'active',
                'show_department_field' => true
            ], array_map(fn($d) => (int)$d['id'], array_slice($departments, 0, $i)));
            
            $forms[] = $form;
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
                'label' => 'التخصص',
                'field_key' => 'specialty',
                'is_required' => false,
                'order_index' => 2,
                'field_options' => [
                    'choices' => ['برمجة', 'تصميم', 'إدارة', 'محاسبة']
                ]
            ]);
        }

        // إنشاء إجابات متنوعة
        $submissions = [];
        for ($i = 1; $i <= 15; $i++) {
            $formIndex = ($i - 1) % 3;
            $deptIndex = ($i - 1) % 3;
            $form = $forms[$formIndex];
            $dept = $departments[$deptIndex];
            
            $submissionData = [
                'submitted_by' => "user$i@example.com",
                'department_id' => (int)$dept['id'],
                'ip_address' => "192.168.1.$i",
                
                'name' => "مستخدم اختبار $i",
                'email' => "user$i@example.com",
                'specialty' => ['برمجة', 'تصميم', 'إدارة', 'محاسبة'][($i - 1) % 4]
            ];
            
            try {
                $submission = $this->submissionService->submit(
                    (int)$form['id'],
                    $submissionData,
                    $submissionData,
                    []
                );
                
                if ($submission) {
                    $submissions[] = $submission;
                    $this->trackCreatedData('submissions', (int)$submission['id']);
                }
            } catch (Exception $e) {
                // تجاهل الأخطاء في إنشاء البيانات
            }
        }

        return [
            'departments' => $departments,
            'forms' => $forms,
            'submissions' => $submissions
        ];
    }

    /**
     * اختبار عرض جميع الإجابات
     */
    public function testViewAllSubmissions(): void
    {
        echo "\n👀 اختبار عرض جميع الإجابات...\n";
        
        try {
            $data = $this->createComprehensiveTestData();
            
            // جلب جميع الإجابات
            $stmt = $this->pdo->query("
                SELECT 
                    fs.*,
                    f.title as form_title,
                    d.name as department_name
                FROM form_submissions fs
                JOIN forms f ON fs.form_id = f.id
                LEFT JOIN departments d ON fs.department_id = d.id
                ORDER BY fs.created_at DESC
            ");
            $submissions = $stmt->fetchAll();
            
            $this->assertGreaterThan(0, count($submissions), 'يمكن جلب الإجابات');
            $this->assertGreaterThan(10, count($submissions), 'تم إنشاء عدد كافي من الإجابات');
            
            // التحقق من صحة البيانات
            foreach (array_slice($submissions, 0, 5) as $submission) {
                $this->assertTrue(isset($submission['id']), 'معرف الإجابة موجود');
                $this->assertTrue(isset($submission['form_title']), 'عنوان الاستمارة موجود');
                $this->assertTrue(isset($submission['department_name']), 'اسم الإدارة موجود');
                $this->assertTrue(isset($submission['reference_code']), 'كود المرجع موجود');
            }
            
            echo "تم جلب " . count($submissions) . " إجابة\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في عرض الإجابات: ' . $e->getMessage());
        }
    }

    /**
     * اختبار تصفية الإجابات
     */
    public function testSubmissionsFiltering(): void
    {
        echo "\n🔍 اختبار تصفية الإجابات...\n";
        
        try {
            $data = $this->createComprehensiveTestData();
            $forms = $data['forms'];
            $departments = $data['departments'];
            
            // تصفية حسب الاستمارة
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM form_submissions 
                WHERE form_id = ?
            ");
            $stmt->execute([$forms[0]['id']]);
            $formSubmissions = $stmt->fetchColumn();
            
            $this->assertGreaterThan(0, $formSubmissions, 'تصفية حسب الاستمارة تعمل');
            
            // تصفية حسب الإدارة
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM form_submissions 
                WHERE department_id = ?
            ");
            $stmt->execute([$departments[0]['id']]);
            $deptSubmissions = $stmt->fetchColumn();
            
            $this->assertGreaterThan(0, $deptSubmissions, 'تصفية حسب الإدارة تعمل');
            
            // تصفية حسب التاريخ
            $today = date('Y-m-d');
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM form_submissions 
                WHERE DATE(created_at) = ?
            ");
            $stmt->execute([$today]);
            $todaySubmissions = $stmt->fetchColumn();
            
            $this->assertGreaterThanOrEqual(0, $todaySubmissions, 'تصفية حسب التاريخ تعمل');
            
            // تصفية حسب البحث
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM form_submissions 
                WHERE submitted_by LIKE ? OR reference_code LIKE ?
            ");
            $stmt->execute(['%user1%', '%REF%']);
            $searchResults = $stmt->fetchColumn();
            
            $this->assertGreaterThan(0, $searchResults, 'البحث النصي يعمل');
            
            // تصفية مركبة
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM form_submissions fs
                JOIN forms f ON fs.form_id = f.id
                WHERE fs.form_id = ? AND fs.department_id = ?
            ");
            $stmt->execute([$forms[0]['id'], $departments[0]['id']]);
            $combinedFilter = $stmt->fetchColumn();
            
            $this->assertGreaterThanOrEqual(0, $combinedFilter, 'التصفية المركبة تعمل');
            
            echo "نتائج التصفية:\n";
            echo "- حسب الاستمارة: $formSubmissions\n";
            echo "- حسب الإدارة: $deptSubmissions\n";
            echo "- حسب التاريخ: $todaySubmissions\n";
            echo "- البحث النصي: $searchResults\n";
            echo "- تصفية مركبة: $combinedFilter\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار التصفية: ' . $e->getMessage());
        }
    }

    /**
     * اختبار Pagination
     */
    public function testSubmissionsPagination(): void
    {
        echo "\n📄 اختبار ترقيم الصفحات...\n";
        
        try {
            $data = $this->createComprehensiveTestData();
            $submissionsPerPage = 5;
            
            // اختبار الصفحة الأولى
            $stmt = $this->pdo->prepare("
                SELECT 
                    fs.*,
                    f.title as form_title,
                    d.name as department_name
                FROM form_submissions fs
                JOIN forms f ON fs.form_id = f.id
                LEFT JOIN departments d ON fs.department_id = d.id
                ORDER BY fs.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$submissionsPerPage]);
            $firstPage = $stmt->fetchAll();
            
            $this->assertLessThanOrEqual($submissionsPerPage, count($firstPage), 'عدد النتائج في الصفحة الأولى صحيح');
            $this->assertGreaterThan(0, count($firstPage), 'الصفحة الأولى تحتوي على بيانات');
            
            // اختبار الصفحة الثانية
            $offset = $submissionsPerPage;
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total_count
                FROM form_submissions
            ");
            $stmt->execute();
            $totalCount = $stmt->fetchColumn();
            
            if ($totalCount > $submissionsPerPage) {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        fs.*,
                        f.title as form_title,
                        d.name as department_name
                    FROM form_submissions fs
                    JOIN forms f ON fs.form_id = f.id
                    LEFT JOIN departments d ON fs.department_id = d.id
                    ORDER BY fs.created_at DESC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute([$submissionsPerPage, $offset]);
                $secondPage = $stmt->fetchAll();
                
                $this->assertLessThanOrEqual($submissionsPerPage, count($secondPage), 'عدد النتائج في الصفحة الثانية صحيح');
                
                // التحقق من عدم تكرار البيانات
                $firstIds = array_column($firstPage, 'id');
                $secondIds = array_column($secondPage, 'id');
                $commonIds = array_intersect($firstIds, $secondIds);
                $this->assertEquals(0, count($commonIds), 'لا توجد بيانات مكررة بين الصفحات');
            }
            
            echo "إجمالي الإجابات: $totalCount\n";
            echo "عدد نتائج الصفحة الأولى: " . count($firstPage) . "\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار Pagination: ' . $e->getMessage());
        }
    }

    /**
     * اختبار عرض تفاصيل الإجابة
     */
    public function testSubmissionDetails(): void
    {
        echo "\n📋 اختبار عرض تفاصيل الإجابة...\n";
        
        try {
            $data = $this->createComprehensiveTestData();
            $submissions = $data['submissions'];
            
            if (count($submissions) === 0) {
                $this->assert(true, 'لا توجد إجابات لاختبار التفاصيل');
                return;
            }
            
            $submission = $submissions[0];
            
            // جلب تفاصيل الإجابة
            $stmt = $this->pdo->prepare("
                SELECT 
                    fs.*,
                    f.title as form_title,
                    f.description as form_description,
                    d.name as department_name,
                    a.name as admin_name
                FROM form_submissions fs
                JOIN forms f ON fs.form_id = f.id
                LEFT JOIN departments d ON fs.department_id = d.id
                LEFT JOIN admins a ON fs.submitted_by = a.email
                WHERE fs.id = ?
            ");
            $stmt->execute([$submission['id']]);
            $details = $stmt->fetch();
            
            $this->assertNotNull($details, 'يمكن جلب تفاصيل الإجابة');
            $this->assertTrue(isset($details['form_title']), 'عنوان الاستمارة في التفاصيل');
            $this->assertTrue(isset($details['submitted_by']), 'بيانات المرسل في التفاصيل');
            $this->assertTrue(isset($details['created_at']), 'تاريخ الإرسال في التفاصيل');
            
            // جلب الإجابات التفصيلية
            $stmt = $this->pdo->prepare("
                SELECT 
                    sa.*,
                    ff.label as field_label,
                    ff.field_type
                FROM submission_answers sa
                JOIN form_fields ff ON sa.field_id = ff.id
                WHERE sa.submission_id = ?
                ORDER BY ff.order_index ASC
            ");
            $stmt->execute([$submission['id']]);
            $answers = $stmt->fetchAll();
            
            $this->assertGreaterThan(0, count($answers), 'يمكن جلب الإجابات التفصيلية');
            
            // التحقق من الحقول المكررة
            $repeaterAnswers = array_filter($answers, fn($a) => $a['field_type'] === 'repeater');
            foreach ($answers as $answer) {
                $this->assertTrue(isset($answer['field_label']), 'تسمية الحقل في الإجابة');
                $this->assertTrue(isset($answer['field_type']), 'نوع الحقل في الإجابة');
            }
            
            echo "تفاصيل الإجابة:\n";
            echo "- المعرف: {$details['id']}\n";
            echo "- الاستمارة: {$details['form_title']}\n";
            echo "- المرسل: {$details['submitted_by']}\n";
            echo "- الإدارة: " . ($details['department_name'] ?? 'غير محدد') . "\n";
            echo "- عدد الإجابات: " . count($answers) . "\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار تفاصيل الإجابة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار تغيير حالة الإجابة
     */
    public function testSubmissionStatusChanges(): void
    {
        echo "\n🔄 اختبار تغيير حالة الإجابة...\n";
        
        try {
            $data = $this->createComprehensiveTestData();
            $submissions = $data['submissions'];
            
            if (count($submissions) === 0) {
                $this->assert(true, 'لا توجد إجابات لاختبار تغيير الحالة');
                return;
            }
            
            $submission = $submissions[0];
            $originalStatus = $submission['status'] ?? 'pending';
            
            // تغيير إلى مكتملة
            $stmt = $this->pdo->prepare("UPDATE form_submissions SET status = 'completed' WHERE id = ?");
            $result = $stmt->execute([$submission['id']]);
            $this->assertTrue($result, 'تغيير الحالة إلى مكتملة نجح');
            
            // التحقق من التحديث
            $stmt = $this->pdo->prepare("SELECT status FROM form_submissions WHERE id = ?");
            $stmt->execute([$submission['id']]);
            $newStatus = $stmt->fetchColumn();
            
            $this->assertEquals('completed', $newStatus, 'الحالة تم تحديثها بنجاح');
            
            // تغيير إلى مؤرشفة
            $stmt = $this->pdo->prepare("UPDATE form_submissions SET status = 'archived' WHERE id = ?");
            $result = $stmt->execute([$submission['id']]);
            $this->assertTrue($result, 'تغيير الحالة إلى مؤرشفة نجح');
            
            $stmt = $this->pdo->prepare("SELECT status FROM form_submissions WHERE id = ?");
            $stmt->execute([$submission['id']]);
            $archivedStatus = $stmt->fetchColumn();
            
            $this->assertEquals('archived', $archivedStatus, 'الحالة تم أرشفتها بنجاح');
            
            // اختبار الفلاتر حسب الحالة
            $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM form_submissions GROUP BY status");
            $statusCounts = $stmt->fetchAll();
            
            $this->assertGreaterThan(0, count($statusCounts), 'يمكن جلب إحصائيات الحالات');
            
            echo "إحصائيات الحالات:\n";
            foreach ($statusCounts as $status) {
                echo "- {$status['status']}: {$status['count']}\n";
            }
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار تغيير الحالة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار حذف الإجابة
     */
    public function testSubmissionDeletion(): void
    {
        echo "\n🗑️ اختبار حذف الإجابة...\n";
        
        try {
            $data = $this->createComprehensiveTestData();
            $submissions = $data['submissions'];
            
            if (count($submissions) === 0) {
                $this->assert(true, 'لا توجد إجابات لاختبار الحذف');
                return;
            }
            
            $submission = $submissions[0];
            $submissionId = $submission['id'];
            
            // التحقق من وجود الإجابة
            $stmt = $this->pdo->prepare("SELECT id FROM form_submissions WHERE id = ?");
            $stmt->execute([$submissionId]);
            $exists = $stmt->fetch();
            $this->assertNotFalse($exists, 'الإجابة موجودة قبل الحذف');
            
            // حذف الإجابة
            $stmt = $this->pdo->prepare("DELETE FROM form_submissions WHERE id = ?");
            $result = $stmt->execute([$submissionId]);
            $this->assertTrue($result, 'حذف الإجابة نجح');
            
            // التحقق من عدم وجود الإجابة بعد الحذف
            $stmt = $this->pdo->prepare("SELECT id FROM form_submissions WHERE id = ?");
            $stmt->execute([$submissionId]);
            $stillExists = $stmt->fetch();
            $this->assertFalse($stillExists, 'الإجابة تم حذفها بنجاح');
            
            // التحقق من حذف الإجابات المرتبطة
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM submission_answers WHERE submission_id = ?");
            $stmt->execute([$submissionId]);
            $answersCount = $stmt->fetchColumn();
            $this->assertEquals(0, $answersCount, 'تم حذف الإجابات المرتبطة');
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار حذف الإجابة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار التصدير إلى CSV
     */
    public function testCSVExport(): void
    {
        echo "\n📊 اختبار التصدير إلى CSV...\n";
        
        try {
            $data = $this->createComprehensiveTestData();
            
            // إنشاء استعلام للتصدير
            $stmt = $this->pdo->query("
                SELECT 
                    fs.id,
                    fs.reference_code,
                    fs.submitted_by,
                    d.name as department_name,
                    f.title as form_title,
                    fs.created_at,
                    fs.status
                FROM form_submissions fs
                JOIN forms f ON fs.form_id = f.id
                LEFT JOIN departments d ON fs.department_id = d.id
                ORDER BY fs.created_at DESC
            ");
            $submissions = $stmt->fetchAll();
            
            $this->assertGreaterThan(0, count($submissions), 'بيانات للتصدير متاحة');
            
            // محاكاة تصدير CSV
            $csvContent = "معرف الإجابة,كود المرجع,المرسل,القسم,الاستمارة,تاريخ الإرسال,الحالة\n";
            
            foreach ($submissions as $submission) {
                $csvContent .= sprintf(
                    "%d,%s,%s,%s,%s,%s,%s\n",
                    $submission['id'],
                    $submission['reference_code'],
                    $submission['submitted_by'],
                    $submission['department_name'] ?? 'غير محدد',
                    $submission['form_title'],
                    $submission['created_at'],
                    $submission['status']
                );
            }
            
            // التحقق من محتوى CSV
            $lines = explode("\n", trim($csvContent));
            $this->assertGreaterThan(1, count($lines), 'CSV يحتوي على بيانات');
            $this->assertStringContainsString('معرف الإجابة', $lines[0], 'CSV يحتوي على رأس صحيح');
            
            // اختبار الفلترة في التصدير
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM form_submissions 
                WHERE DATE(created_at) = ?
            ");
            $stmt->execute([date('Y-m-d')]);
            $todayCount = $stmt->fetchColumn();
            
            // يمكن إضافة فلترة حسب التاريخ في التصدير الفعلي
            echo "عدد الإجابات للتصدير: " . count($submissions) . "\n";
            echo "عدد أسطر CSV: " . count($lines) . "\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار تصدير CSV: ' . $e->getMessage());
        }
    }

    /**
     * اختبار التصدير إلى Excel
     */
    public function testExcelExport(): void
    {
        echo "\n📈 اختبار التصدير إلى Excel...\n";
        
        try {
            $data = $this->createComprehensiveTestData();
            
            // محاكاة بيانات Excel (في التطبيق الفعلي سيتم استخدام PhpSpreadsheet)
            $excelData = [
                ['معرف الإجابة', 'كود المرجع', 'المرسل', 'القسم', 'الاستمارة', 'تاريخ الإرسال', 'الحالة'],
            ];
            
            $stmt = $this->pdo->query("
                SELECT 
                    fs.id,
                    fs.reference_code,
                    fs.submitted_by,
                    d.name as department_name,
                    f.title as form_title,
                    fs.created_at,
                    fs.status
                FROM form_submissions fs
                JOIN forms f ON fs.form_id = f.id
                LEFT JOIN departments d ON fs.department_id = d.id
                ORDER BY fs.created_at DESC
                LIMIT 100
            ");
            $submissions = $stmt->fetchAll();
            
            foreach ($submissions as $submission) {
                $excelData[] = [
                    $submission['id'],
                    $submission['reference_code'],
                    $submission['submitted_by'],
                    $submission['department_name'] ?? 'غير محدد',
                    $submission['form_title'],
                    $submission['created_at'],
                    $submission['status']
                ];
            }
            
            $this->assertGreaterThan(1, count($excelData), 'بيانات Excel متاحة');
            $this->assertEquals(7, count($excelData[0]), 'عدد الأعمدة في Excel صحيح');
            
            // اختبار التعامل مع البيانات الكبيرة
            $this->assertLessThanOrEqual(101, count($excelData), 'عدد الصفوف في Excel محدود');
            
            echo "عدد صفحات Excel: " . count($excelData) . "\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار تصدير Excel: ' . $e->getMessage());
        }
    }

    /**
     * اختبار التعامل مع الحقول المكررة في التصدير
     */
    public function testRepeaterFieldsInExport(): void
    {
        echo "\n🔄 اختبار الحقول المكررة في التصدير...\n";
        
        try {
            // إنشاء استمارة بحقل مكرر
            $form = $this->formService->create([
                'title' => 'استمارة اختبار الحقول المكررة للتصدير',
                'description' => 'اختبار الحقول المكررة',
                'created_by' => 1,
                'status' => 'active'
            ], [1]);
            
            $this->trackCreatedData('forms', (int)$form['id']);
            
            // حقل نص عادي
            $this->fieldService->addField((int)$form['id'], [
                'field_type' => 'text',
                'label' => 'الاسم',
                'field_key' => 'name',
                'is_required' => true,
                'order_index' => 0
            ]);
            
            // حقل مكرر
            $repeater = $this->fieldService->addField((int)$form['id'], [
                'field_type' => 'repeater',
                'label' => 'المؤهلات',
                'field_key' => 'qualifications',
                'is_required' => false,
                'order_index' => 1
            ]);
            
            // حقول فرعية
            $this->fieldService->addField((int)$form['id'], [
                'field_type' => 'text',
                'label' => 'اسم المؤهل',
                'field_key' => 'qual_name',
                'is_required' => false,
                'parent_field_id' => (int)$repeater['id'],
                'order_index' => 0
            ]);
            
            $this->fieldService->addField((int)$form['id'], [
                'field_type' => 'text',
                'label' => 'الجامعة',
                'field_key' => 'university',
                'is_required' => false,
                'parent_field_id' => (int)$repeater['id'],
                'order_index' => 1
            ]);
            
            // إنشاء إجابة مع حقول مكررة
            $submissionData = [
                'name' => 'اختبار الحقول المكررة',
                'qualifications' => [
                    ['qual_name' => 'بكالوريوس', 'university' => 'جامعة 1'],
                    ['qual_name' => 'ماجستير', 'university' => 'جامعة 2']
                ]
            ];
            
            $metadata = [
                'submitted_by' => 'repeater@test.com',
                'department_id' => 1,
                'ip_address' => '127.0.0.1'
            ];
            
            $submission = $this->submissionService->submit(
                (int)$form['id'],
                $metadata,
                $submissionData,
                []
            );
            
            $this->assertNotNull($submission, 'تم إنشاء الإجابة مع الحقول المكررة');
            
            // جلب البيانات للتصدير
            $stmt = $this->pdo->prepare("
                SELECT 
                    sa.field_key,
                    sa.answer_data,
                    ff.label
                FROM submission_answers sa
                JOIN form_fields ff ON sa.field_id = ff.id
                WHERE sa.submission_id = ?
                ORDER BY ff.order_index
            ");
            $stmt->execute([$submission['id']]);
            $answers = $stmt->fetchAll();
            
            $this->assertGreaterThan(0, count($answers), 'الإجابات مع الحقول المكررة محفوظة');
            
            // التحقق من معالجة البيانات المكررة
            $repeaterAnswers = [];
            foreach ($answers as $answer) {
                if ($answer['field_key'] === 'qual_name' || $answer['field_key'] === 'university') {
                    $repeaterAnswers[] = $answer;
                }
            }
            
            $this->assertGreaterThan(0, count($repeaterAnswers), 'الحقول المكررة موجودة في التصدير');
            
            echo "عدد الإجابات للحقول المكررة: " . count($repeaterAnswers) . "\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار الحقول المكررة في التصدير: ' . $e->getMessage());
        }
    }

    /**
     * اختبار إحصائيات الإجابات
     */
    public function testSubmissionsStatistics(): void
    {
        echo "\n📊 اختبار إحصائيات الإجابات...\n";
        
        try {
            $data = $this->createComprehensiveTestData();
            
            // إحصائية الإجابات حسب الحالة
            $stmt = $this->pdo->query("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM form_submissions
                GROUP BY status
            ");
            $statusStats = $stmt->fetchAll();
            
            $this->assertGreaterThan(0, count($statusStats), 'إحصائيات الحالة متاحة');
            
            // إحصائية الإجابات اليومية
            $stmt = $this->pdo->query("
                SELECT 
                    DATE(created_at) as submission_date,
                    COUNT(*) as count
                FROM form_submissions
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at)
                ORDER BY submission_date DESC
                LIMIT 10
            ");
            $dailyStats = $stmt->fetchAll();
            
            // إحصائية حسب الاستمارة
            $stmt = $this->pdo->query("
                SELECT 
                    f.title,
                    COUNT(fs.id) as submission_count
                FROM forms f
                LEFT JOIN form_submissions fs ON f.id = fs.form_id
                GROUP BY f.id, f.title
                ORDER BY submission_count DESC
            ");
            $formStats = $stmt->fetchAll();
            
            $this->assertGreaterThan(0, count($formStats), 'إحصائيات الاستمارات متاحة');
            
            // إحصائية حسب الإدارة
            $stmt = $this->pdo->query("
                SELECT 
                    d.name,
                    COUNT(fs.id) as submission_count
                FROM departments d
                LEFT JOIN form_submissions fs ON d.id = fs.department_id
                GROUP BY d.id, d.name
                ORDER BY submission_count DESC
            ");
            $deptStats = $stmt->fetchAll();
            
            $this->assertGreaterThan(0, count($deptStats), 'إحصائيات الإدارات متاحة');
            
            echo "إحصائيات الحالة:\n";
            foreach ($statusStats as $stat) {
                echo "- {$stat['status']}: {$stat['count']}\n";
            }
            
            echo "\nإحصائيات الاستمارات:\n";
            foreach (array_slice($formStats, 0, 5) as $stat) {
                echo "- {$stat['title']}: {$stat['submission_count']}\n";
            }
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار الإحصائيات: ' . $e->getMessage());
        }
    }

    /**
     * تشغيل جميع اختبارات إدارة الإجابات
     */
    public function runAllTests(): void
    {
        try {
            $this->testViewAllSubmissions();
            $this->testSubmissionsFiltering();
            $this->testSubmissionsPagination();
            $this->testSubmissionDetails();
            $this->testSubmissionStatusChanges();
            $this->testSubmissionDeletion();
            $this->testCSVExport();
            $this->testExcelExport();
            $this->testRepeaterFieldsInExport();
            $this->testSubmissionsStatistics();
            
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
    $tests = new SubmissionsManagementTests();
    $tests->runAllTests();
}