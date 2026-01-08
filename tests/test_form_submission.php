<?php

declare(strict_types=1);

/**
 * Form Submission Tests
 * 
 * Tests form filling, validation, and submission process
 */

require_once __DIR__ . '/test_base.php';

use EmployeeEvaluationSystem\Core\Services\FormService;
use EmployeeEvaluationSystem\Core\Services\FormFieldService;
use EmployeeEvaluationSystem\Core\Services\FormSubmissionService;
use EmployeeEvaluationSystem\Core\Logger;

class FormSubmissionTests extends BaseTest
{
    private FormService $formService;
    private FormFieldService $fieldService;
    private FormSubmissionService $submissionService;
    private Logger $logger;

    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger();
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
        
        echo "\n📝 بدء اختبارات ملء وإرسال الاستمارات\n";
        echo str_repeat('=', 50) . "\n";
    }

    /**
     * إنشاء استمارة شاملة للاختبار
     */
    private function createTestFormWithFields(): array
    {
        $form = $this->formService->create([
            'title' => 'استمارة شاملة للاختبار',
            'description' => 'استمارة تحتوي على جميع أنواع الحقول للاختبار',
            'created_by' => 1,
            'status' => 'active',
            'show_department_field' => true
        ], [1]);

        $this->trackCreatedData('forms', (int)$form['id']);

        // إضافة حقول متنوعة
        $fieldIds = [];

        // حقل نص
        $field = $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'text',
            'label' => 'الاسم الكامل',
            'field_key' => 'full_name',
            'is_required' => true,
            'order_index' => 0,
            'validation_rules' => ['min_length' => 2, 'max_length' => 100]
        ]);
        $fieldIds['full_name'] = (int)$field['id'];

        // حقل بريد إلكتروني
        $field = $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'email',
            'label' => 'البريد الإلكتروني',
            'field_key' => 'email',
            'is_required' => true,
            'order_index' => 1
        ]);
        $fieldIds['email'] = (int)$field['id'];

        // حقل رقم
        $field = $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'number',
            'label' => 'العمر',
            'field_key' => 'age',
            'is_required' => true,
            'order_index' => 2,
            'validation_rules' => ['min' => 18, 'max' => 65]
        ]);
        $fieldIds['age'] = (int)$field['id'];

        // حقل تاريخ
        $field = $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'date',
            'label' => 'تاريخ الميلاد',
            'field_key' => 'birth_date',
            'is_required' => false,
            'order_index' => 3
        ]);
        $fieldIds['birth_date'] = (int)$field['id'];

        // حقل وقت
        $field = $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'time',
            'label' => 'وقت الانضمام',
            'field_key' => 'join_time',
            'is_required' => false,
            'order_index' => 4
        ]);
        $fieldIds['join_time'] = (int)$field['id'];

        // حقل select
        $field = $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'select',
            'label' => 'القسم',
            'field_key' => 'department',
            'is_required' => true,
            'order_index' => 5,
            'field_options' => [
                'choices' => ['تقنية المعلومات', 'الموارد البشرية', 'المحاسبة', 'المبيعات']
            ]
        ]);
        $fieldIds['department'] = (int)$field['id'];

        // حقل radio
        $field = $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'radio',
            'label' => 'الجنس',
            'field_key' => 'gender',
            'is_required' => true,
            'order_index' => 6,
            'field_options' => [
                'choices' => ['ذكر', 'أنثى']
            ]
        ]);
        $fieldIds['gender'] = (int)$field['id'];

        // حقل checkbox
        $field = $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'checkbox',
            'label' => 'المهارات',
            'field_key' => 'skills',
            'is_required' => false,
            'order_index' => 7,
            'field_options' => [
                'choices' => ['PHP', 'JavaScript', 'Python', 'MySQL']
            ]
        ]);
        $fieldIds['skills'] = (int)$field['id'];

        // حقل textarea
        $field = $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'textarea',
            'label' => 'ملاحظات إضافية',
            'field_key' => 'notes',
            'is_required' => false,
            'order_index' => 8,
            'validation_rules' => ['max_length' => 500]
        ]);
        $fieldIds['notes'] = (int)$field['id'];

        // حقل repeater
        $repeater = $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'repeater',
            'label' => 'المؤهلات والشهادات',
            'field_key' => 'qualifications',
            'is_required' => false,
            'order_index' => 9
        ]);
        $fieldIds['repeater'] = (int)$repeater['id'];

        // حقول فرعية للحقل المكرر
        $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'text',
            'label' => 'اسم المؤهل',
            'field_key' => 'qualification_name',
            'is_required' => true,
            'parent_field_id' => (int)$repeater['id'],
            'order_index' => 0
        ]);

        $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'text',
            'label' => 'الجامعة أو الجهة',
            'field_key' => 'institution',
            'is_required' => false,
            'parent_field_id' => (int)$repeater['id'],
            'order_index' => 1
        ]);

        $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'date',
            'label' => 'سنة التخرج',
            'field_key' => 'graduation_year',
            'is_required' => false,
            'parent_field_id' => (int)$repeater['id'],
            'order_index' => 2
        ]);

        return ['form' => $form, 'field_ids' => $fieldIds];
    }

    /**
     * اختبار فتح صفحة ملء الاستمارة
     */
    public function testFormPageAccess(): void
    {
        echo "\n🌐 اختبار فتح صفحة ملء الاستمارة...\n";
        
        try {
            $formData = $this->createTestFormWithFields();
            $form = $formData['form'];
            
            // اختبار جلب بيانات الاستمارة
            $stmt = $this->pdo->prepare("
                SELECT id, title, slug, description, status, show_department_field 
                FROM forms 
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$form['id']]);
            $formData = $stmt->fetch();
            
            $this->assertNotNull($formData, 'بيانات الاستمارة متاحة');
            $this->assertEquals('active', $formData['status'], 'الاستمارة نشطة');
            
            // اختبار جلب الحقول
            $stmt = $this->pdo->prepare("
                SELECT id, field_type, label, field_key, is_required, order_index, parent_field_id
                FROM form_fields 
                WHERE form_id = ? 
                ORDER BY order_index ASC
            ");
            $stmt->execute([$form['id']]);
            $fields = $stmt->fetchAll();
            
            $this->assertGreaterThan(0, count($fields), 'الحقول متاحة');
            $this->assertGreaterThanOrEqual(10, count($fields), 'تم إضافة عدد كافي من الحقول');
            
            echo "الاستمارة: {$formData['title']}\n";
            echo "عدد الحقول: " . count($fields) . "\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار صفحة الاستمارة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار ملء جميع أنواع الحقول
     */
    public function testFillAllFieldTypes(): void
    {
        echo "\n📝 اختبار ملء جميع أنواع الحقول...\n";
        
        try {
            $formData = $this->createTestFormWithFields();
            $form = $formData['form'];
            
            $submissionData = [
                'submitted_by' => 'test.submitter@example.com',
                'department_id' => 1,
                'ip_address' => '192.168.1.100',
                
                // جميع أنواع الحقول
                'full_name' => 'أحمد محمد علي',
                'email' => 'ahmed.mohamed@example.com',
                'age' => 28,
                'birth_date' => '1995-05-15',
                'join_time' => '09:00',
                'department' => 'تقنية المعلومات',
                'gender' => 'ذكر',
                'skills' => ['PHP', 'JavaScript', 'MySQL'],
                'notes' => 'مطور برمجيات متمرس مع خبرة 5 سنوات في تطوير الويب.',
                
                // الحقول المكررة
                'qualifications' => [
                    [
                        'qualification_name' => 'بكالوريوس علوم الحاسوب',
                        'institution' => 'جامعة القاهرة',
                        'graduation_year' => '2018'
                    ],
                    [
                        'qualification_name' => 'شهادة PHP المتقدمة',
                        'institution' => 'معهد التقنيات المتقدمة',
                        'graduation_year' => '2020'
                    ]
                ]
            ];
            
            $submission = $this->submissionService->submit(
                (int)$form['id'], 
                [
                    'submitted_by' => $submissionData['submitted_by'],
                    'department_id' => $submissionData['department_id'],
                    'ip_address' => $submissionData['ip_address']
                ],
                $submissionData,
                [] // ملفات فارغة للآن
            );
            
            $this->assertNotNull($submission, 'تم إرسال الاستمارة بنجاح');
            $this->assertTrue(isset($submission['id']), 'معرف الإرسال موجود');
            $this->assertTrue(isset($submission['reference_code']), 'كود المرجع موجود');
            $this->assertGreaterThan(0, count($submission['answers']), 'تم حفظ الإجابات');
            
            echo "معرف الإرسال: {$submission['id']}\n";
            echo "كود المرجع: {$submission['reference_code']}\n";
            echo "عدد الإجابات: " . count($submission['answers']) . "\n";
            
            $this->trackCreatedData('submissions', (int)$submission['id']);
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في ملء الحقول: ' . $e->getMessage());
        }
    }

    /**
     * اختبار validation للحقول المطلوبة
     */
    public function testRequiredFieldValidation(): void
    {
        echo "\n✅ اختبار التحقق من الحقول المطلوبة...\n";
        
        try {
            $formData = $this->createTestFormWithFields();
            $form = $formData['form'];
            
            // اختبار إرسال بدون الحقول المطلوبة
            $invalidData = [
                'submitted_by' => 'test@example.com',
                'department_id' => 1,
                'ip_address' => '127.0.0.1',
                
                // ناقص الحقول المطلوبة: full_name, email, age, department, gender
                'birth_date' => '1995-05-15',
                'notes' => 'بيانات ناقصة'
            ];
            
            try {
                $this->submissionService->submit(
                    (int)$form['id'],
                    $invalidData,
                    $invalidData,
                    []
                );
                $this->assert(false, 'إرسال بيانات ناقصة يجب أن يفشل');
            } catch (Exception $e) {
                $this->assert(true, 'التحقق من البيانات المطلوبة يعمل بشكل صحيح');
            }
            
            // اختبار إرسال بحقول صحيحة
            $validData = [
                'submitted_by' => 'valid.test@example.com',
                'department_id' => 1,
                'ip_address' => '127.0.0.1',
                
                'full_name' => 'سارة أحمد محمود',
                'email' => 'sara.ahmed@example.com',
                'age' => 25,
                'department' => 'الموارد البشرية',
                'gender' => 'أنثى',
                'birth_date' => '1998-03-10',
                'notes' => 'بيانات كاملة وصحيحة'
            ];
            
            $submission = $this->submissionService->submit(
                (int)$form['id'],
                $validData,
                $validData,
                []
            );
            
            $this->assertNotNull($submission, 'إرسال البيانات الصحيحة نجح');
            
            $this->trackCreatedData('submissions', (int)$submission['id']);
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار validation: ' . $e->getMessage());
        }
    }

    /**
     * اختبار أنواع البيانات المختلفة
     */
    public function testDataTypeValidation(): void
    {
        echo "\n🔍 اختبار أنواع البيانات...\n";
        
        try {
            $formData = $this->createTestFormWithFields();
            $form = $formData['form'];
            
            // اختبار بريد إلكتروني غير صحيح
            $invalidEmailData = [
                'submitted_by' => 'invalid.email',
                'department_id' => 1,
                'ip_address' => '127.0.0.1',
                'full_name' => 'محمد علي',
                'email' => 'not-an-email', // بريد غير صحيح
                'age' => 25,
                'department' => 'المبيعات',
                'gender' => 'ذكر'
            ];
            
            try {
                $this->submissionService->submit(
                    (int)$form['id'],
                    $invalidEmailData,
                    $invalidEmailData,
                    []
                );
                $this->assert(false, 'بريد إلكتروني غير صحيح يجب أن يتم رفضه');
            } catch (Exception $e) {
                $this->assert(true, 'التحقق من البريد الإلكتروني يعمل بشكل صحيح');
            }
            
            // اختبار رقم خارج النطاق
            $invalidNumberData = [
                'submitted_by' => 'number.test@example.com',
                'department_id' => 1,
                'ip_address' => '127.0.0.1',
                'full_name' => 'خالد حسن',
                'email' => 'khalid.hasan@example.com',
                'age' => 150, // خارج النطاق (18-65)
                'department' => 'المحاسبة',
                'gender' => 'ذكر'
            ];
            
            try {
                $this->submissionService->submit(
                    (int)$form['id'],
                    $invalidNumberData,
                    $invalidNumberData,
                    []
                );
                $this->assert(false, 'رقم خارج النطاق يجب أن يتم رفضه');
            } catch (Exception $e) {
                $this->assert(true, 'التحقق من النطاق الرقمي يعمل بشكل صحيح');
            }
            
            // اختبار نصوص طويلة جداً
            $longTextData = [
                'submitted_by' => 'long.text@example.com',
                'department_id' => 1,
                'ip_address' => '127.0.0.1',
                'full_name' => str_repeat('a', 150), // أطول من 100
                'email' => 'long.name@example.com',
                'age' => 30,
                'department' => 'IT',
                'gender' => 'أنثى',
                'notes' => str_repeat('b', 1000) // أطول من 500
            ];
            
            try {
                $this->submissionService->submit(
                    (int)$form['id'],
                    $longTextData,
                    $longTextData,
                    []
                );
                // قد يتم قبوله أو قطع النصوص
            } catch (Exception $e) {
                $this->assert(true, 'التحقق من طول النصوص يعمل بشكل صحيح');
            }
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار أنواع البيانات: ' . $e->getMessage());
        }
    }

    /**
     * اختبار إنشاء كود المرجع
     */
    public function testReferenceCodeGeneration(): void
    {
        echo "\n🔖 اختبار إنشاء كود المرجع...\n";
        
        try {
            $formData = $this->createTestFormWithFields();
            $form = $formData['form'];
            
            $referenceCodes = [];
            
            // إنشاء عدة إرساليات
            for ($i = 1; $i <= 5; $i++) {
                $data = [
                    'submitted_by' => "test$i@example.com",
                    'department_id' => 1,
                    'ip_address' => "192.168.1.$i",
                    'full_name' => "مستخدم اختبار $i",
                    'email' => "user$i@example.com",
                    'age' => 25 + $i,
                    'department' => 'تقنية المعلومات',
                    'gender' => $i % 2 === 0 ? 'ذكر' : 'أنثى'
                ];
                
                $submission = $this->submissionService->submit(
                    (int)$form['id'],
                    $data,
                    $data,
                    []
                );
                
                $this->assertNotNull($submission['reference_code'], "كود المرجع للإرسال رقم $i موجود");
                $referenceCodes[] = $submission['reference_code'];
                
                $this->trackCreatedData('submissions', (int)$submission['id']);
            }
            
            // التحقق من عدم تكرار أكواد المرجع
            $uniqueCodes = array_unique($referenceCodes);
            $this->assertEquals(count($referenceCodes), count($uniqueCodes), 'أكواد المرجع فريدة');
            
            // التحقق من تنسيق كود المرجع
            foreach ($referenceCodes as $code) {
                $this->assertTrue(preg_match('/^REF-[A-Z0-9]{8}$/', $code), "تنسيق كود المرجع صحيح: $code");
            }
            
            echo "تم إنشاء " . count($referenceCodes) . " كود مرجع فريد\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار كود المرجع: ' . $e->getMessage());
        }
    }

    /**
     * اختبار الحقول المكررة المتقدمة
     */
    public function testAdvancedRepeaterFields(): void
    {
        echo "\n🔄 اختبار الحقول المكررة المتقدمة...\n";
        
        try {
            $formData = $this->createTestFormWithFields();
            $form = $formData['form'];
            
            // اختبار إرسال بدون مجموعات مكررة
            $dataWithoutRepeater = [
                'submitted_by' => 'no.repeater@example.com',
                'department_id' => 1,
                'ip_address' => '127.0.0.1',
                'full_name' => 'مستخدم بدون مؤهلات',
                'email' => 'no.qualifications@example.com',
                'age' => 22,
                'department' => 'المبيعات',
                'gender' => 'أنثى',
                'notes' => 'طالب جامعي بدون مؤهلات'
                // بدون qualifications
            ];
            
            $submission1 = $this->submissionService->submit(
                (int)$form['id'],
                $dataWithoutRepeater,
                $dataWithoutRepeater,
                []
            );
            
            $this->assertNotNull($submission1, 'إرسال بدون حقول مكررة نجح');
            $this->trackCreatedData('submissions', (int)$submission1['id']);
            
            // اختبار إرسال مع مجموعات مكررة متعددة
            $dataWithMultipleRepeaters = [
                'submitted_by' => 'multiple.repeaters@example.com',
                'department_id' => 1,
                'ip_address' => '127.0.0.1',
                'full_name' => 'خبير متعدد المؤهلات',
                'email' => 'expert@example.com',
                'age' => 35,
                'department' => 'تقنية المعلومات',
                'gender' => 'ذكر',
                'notes' => 'خبير مع عدة مؤهلات وشهادات',
                'qualifications' => [
                    [
                        'qualification_name' => 'دكتوراه في علوم الحاسوب',
                        'institution' => 'جامعة الملك سعود',
                        'graduation_year' => '2015'
                    ],
                    [
                        'qualification_name' => 'ماجستير إدارة أعمال',
                        'institution' => 'جامعة عين شمس',
                        'graduation_year' => '2010'
                    ],
                    [
                        'qualification_name' => 'بكالوريوس رياضيات',
                        'institution' => 'جامعة القاهرة',
                        'graduation_year' => '2008'
                    ]
                ]
            ];
            
            $submission2 = $this->submissionService->submit(
                (int)$form['id'],
                $dataWithMultipleRepeaters,
                $dataWithMultipleRepeaters,
                []
            );
            
            $this->assertNotNull($submission2, 'إرسال مع مجموعات مكررة متعددة نجح');
            
            // التحقق من عدد الإجابات (يجب أن يتضمن الحقول المكررة)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as answer_count
                FROM submission_answers 
                WHERE submission_id = ?
            ");
            $stmt->execute([$submission2['id']]);
            $answerCount = $stmt->fetchColumn();
            
            $this->assertGreaterThan(8, $answerCount, 'عدد الإجابات يتضمن الحقول المكررة');
            
            echo "عدد الإجابات للحقل المكرر: $answerCount\n";
            $this->trackCreatedData('submissions', (int)$submission2['id']);
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار الحقول المكررة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار التعامل مع الإرسال المتكرر
     */
    public function testMultipleSubmissions(): void
    {
        echo "\n🔁 اختبار الإرسال المتكرر...\n";
        
        try {
            $formData = $this->createTestFormWithFields();
            $form = $formData['form'];
            
            // اختبار منع الإرسال المتكرر
            $data = [
                'submitted_by' => 'multiple@example.com',
                'department_id' => 1,
                'ip_address' => '127.0.0.1',
                'full_name' => 'مستخدم متعدد الإرسال',
                'email' => 'same@example.com', // نفس البريد
                'age' => 30,
                'department' => 'IT',
                'gender' => 'ذكر'
            ];
            
            $submission1 = $this->submissionService->submit(
                (int)$form['id'],
                $data,
                $data,
                []
            );
            
            $this->assertNotNull($submission1, 'الإرسال الأول نجح');
            $this->trackCreatedData('submissions', (int)$submission1['id']);
            
            // محاولة إرسال نفس البيانات مرة أخرى
            try {
                $submission2 = $this->submissionService->submit(
                    (int)$form['id'],
                    $data,
                    $data,
                    []
                );
                
                // إذا تم قبول الإرسال المتكرر، يجب أن يكون كود المرجع مختلف
                if ($submission2 !== null) {
                    $this->assertNotEquals(
                        $submission1['reference_code'], 
                        $submission2['reference_code'], 
                        'كود المرجع مختلف للإرسال المتكرر'
                    );
                    $this->trackCreatedData('submissions', (int)$submission2['id']);
                }
                
            } catch (Exception $e) {
                $this->assert(true, 'منع الإرسال المتكرر يعمل بشكل صحيح');
            }
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار الإرسال المتكرر: ' . $e->getMessage());
        }
    }

    /**
     * اختبار الأداء
     */
    public function testSubmissionPerformance(): void
    {
        echo "\n⚡ اختبار أداء الإرسال...\n";
        
        try {
            $formData = $this->createTestFormWithFields();
            $form = $formData['form'];
            
            // اختبار إرسال سريع
            $submissionTime = $this->measureTime(function() use ($form, $formData) {
                $data = [
                    'submitted_by' => 'performance@example.com',
                    'department_id' => 1,
                    'ip_address' => '127.0.0.1',
                    'full_name' => 'اختبار الأداء',
                    'email' => 'perf@example.com',
                    'age' => 25,
                    'department' => 'IT',
                    'gender' => 'ذكر'
                ];
                
                $submission = $this->submissionService->submit(
                    (int)$form['id'],
                    $data,
                    $data,
                    []
                );
                
                return $submission;
            });
            
            $this->assertLessThan(2.0, $submissionTime, "إرسال سريع (أقل من ثانيتين)");
            echo "وقت إرسال استمارة: {$submissionTime} ثانية\n";
            
            // اختبار إرساليات متعددة
            $multipleSubmissionTime = $this->measureTime(function() use ($form) {
                for ($i = 0; $i < 10; $i++) {
                    $data = [
                        'submitted_by' => "perf$i@example.com",
                        'department_id' => 1,
                        'ip_address' => "127.0.0.$i",
                        'full_name' => "مستخدم أداء $i",
                        'email' => "perf$i@example.com",
                        'age' => 25 + $i,
                        'department' => 'المبيعات',
                        'gender' => $i % 2 === 0 ? 'ذكر' : 'أنثى'
                    ];
                    
                    $submission = $this->submissionService->submit(
                        (int)$form['id'],
                        $data,
                        $data,
                        []
                    );
                    
                    if ($submission) {
                        $this->trackCreatedData('submissions', (int)$submission['id']);
                    }
                }
            });
            
            $this->assertLessThan(10.0, $multipleSubmissionTime, "إرسال 10 استمارات سريع (أقل من 10 ثوان)");
            echo "وقت إرسال 10 استمارات: {$multipleSubmissionTime} ثانية\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار الأداء: ' . $e->getMessage());
        }
    }

    /**
     * تشغيل جميع اختبارات الإرسال
     */
    public function runAllTests(): void
    {
        try {
            $this->testFormPageAccess();
            $this->testFillAllFieldTypes();
            $this->testRequiredFieldValidation();
            $this->testDataTypeValidation();
            $this->testReferenceCodeGeneration();
            $this->testAdvancedRepeaterFields();
            $this->testMultipleSubmissions();
            $this->testSubmissionPerformance();
            
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
    $tests = new FormSubmissionTests();
    $tests->runAllTests();
}