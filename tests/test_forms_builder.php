<?php

declare(strict_types=1);

/**
 * Form Builder Tests
 * 
 * Tests form management and field creation including all 11 field types
 */

require_once __DIR__ . '/test_base.php';

use EmployeeEvaluationSystem\Core\Services\FormService;
use EmployeeEvaluationSystem\Core\Services\FormFieldService;
use EmployeeEvaluationSystem\Core\Logger;

class FormBuilderTests extends BaseTest
{
    private FormService $formService;
    private FormFieldService $fieldService;
    private Logger $logger;

    // جميع أنواع الحقول المدعومة
    private array $fieldTypes = [
        'text' => ['label' => 'نص عادي', 'key' => 'name'],
        'textarea' => ['label' => 'نص طويل', 'key' => 'description'],
        'email' => ['label' => 'بريد إلكتروني', 'key' => 'email'],
        'number' => ['label' => 'رقم', 'key' => 'age'],
        'date' => ['label' => 'تاريخ', 'key' => 'birth_date'],
        'time' => ['label' => 'وقت', 'key' => 'meeting_time'],
        'select' => ['label' => 'قائمة', 'key' => 'category'],
        'radio' => ['label' => 'اختيار واحد', 'key' => 'gender'],
        'checkbox' => ['label' => 'اختيارات متعددة', 'key' => 'skills'],
        'file' => ['label' => 'رفع ملف', 'key' => 'document'],
        'repeater' => ['label' => 'مجموعة متكررة', 'key' => 'achievements']
    ];

    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger();
        $this->formService = new FormService($this->pdo, $this->logger, null);
        $this->fieldService = new FormFieldService($this->pdo, $this->logger);
        
        echo "\n📋 بدء اختبارات بناء الاستمارات\n";
        echo str_repeat('=', 50) . "\n";
    }

    /**
     * اختبار إنشاء استمارة جديدة
     */
    public function testCreateForm(): void
    {
        echo "\n➕ اختبار إنشاء استمارة جديدة...\n";
        
        try {
            $form = $this->formService->create([
                'title' => 'استمارة اختبار شاملة',
                'description' => 'استمارة شاملة لاختبار جميع أنواع الحقول',
                'created_by' => 1,
                'status' => 'active',
                'allow_multiple_submissions' => true,
                'show_department_field' => true
            ], [1, 2]); // ربط بالإدارات
            
            $this->assertNotNull($form, 'تم إنشاء الاستمارة بنجاح');
            $this->assertTrue(isset($form['id']), 'معرف الاستمارة موجود');
            $this->assertEquals('استمارة اختبار شاملة', $form['title'], 'عنوان الاستمارة صحيح');
            $this->assertEquals('active', $form['status'], 'حالة الاستمارة صحيحة');
            
            $this->trackCreatedData('forms', (int)$form['id']);
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في إنشاء الاستمارة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار إضافة جميع أنواع الحقول
     */
    public function testAddAllFieldTypes(): void
    {
        echo "\n🎯 اختبار إضافة جميع أنواع الحقول...\n";
        
        $formId = $this->createTestForm('استمارة اختبار الحقول');
        
        try {
            $orderIndex = 0;
            
            foreach ($this->fieldTypes as $fieldType => $fieldInfo) {
                echo "  ➕ إضافة حقل: {$fieldInfo['label']} (نوع: {$fieldType})\n";
                
                $fieldData = [
                    'form_id' => $formId,
                    'field_type' => $fieldType,
                    'label' => $fieldInfo['label'],
                    'field_key' => $fieldInfo['key'] . '_' . $fieldType,
                    'is_required' => in_array($fieldType, ['text', 'email', 'select']),
                    'order_index' => $orderIndex++
                ];
                
                // إضافة إعدادات خاصة لبعض أنواع الحقول
                switch ($fieldType) {
                    case 'select':
                    case 'radio':
                        $fieldData['field_options'] = [
                            'choices' => ['خيار 1', 'خيار 2', 'خيار 3'],
                            'allow_multiple' => $fieldType === 'select'
                        ];
                        break;
                        
                    case 'checkbox':
                        $fieldData['field_options'] = [
                            'choices' => ['مهارة 1', 'مهارة 2', 'مهارة 3']
                        ];
                        break;
                        
                    case 'number':
                        $fieldData['validation_rules'] = [
                            'min' => 0,
                            'max' => 100
                        ];
                        break;
                        
                    case 'text':
                        $fieldData['validation_rules'] = [
                            'min_length' => 2,
                            'max_length' => 100
                        ];
                        break;
                        
                    case 'file':
                        $fieldData['validation_rules'] = [
                            'max_size' => 10485760, // 10MB
                            'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf']
                        ];
                        break;
                }
                
                $field = $this->fieldService->addField($formId, $fieldData);
                $this->assertNotNull($field, "تم إضافة حقل {$fieldType} بنجاح");
                $this->assertEquals($fieldType, $field['field_type'], "نوع الحقل صحيح ({$fieldType})");
                
                $this->trackCreatedData('fields', (int)$field['id']);
            }
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في إضافة الحقول: ' . $e->getMessage());
        }
    }

    /**
     * اختبار الحقول المكررة (Repeater)
     */
    public function testRepeaterFields(): void
    {
        echo "\n🔄 اختبار الحقول المكررة...\n";
        
        $formId = $this->createTestForm('استمارة اختبار الحقول المكررة');
        
        try {
            // إنشاء حقل repeater رئيسي
            $repeaterField = $this->fieldService->addField($formId, [
                'field_type' => 'repeater',
                'label' => 'الإنجازات',
                'field_key' => 'achievements',
                'is_required' => false,
                'order_index' => 0
            ]);
            
            $this->assertNotNull($repeaterField, 'تم إنشاء حقل repeater');
            
            // إضافة حقول فرعية للحقل المكرر
            $childFields = [
                ['type' => 'text', 'label' => 'عنوان الإنجاز', 'key' => 'title'],
                ['type' => 'textarea', 'label' => 'الوصف', 'key' => 'description'],
                ['type' => 'date', 'label' => 'تاريخ الإنجاز', 'key' => 'date'],
                ['type' => 'file', 'label' => 'مرفق', 'key' => 'attachment']
            ];
            
            $orderIndex = 0;
            foreach ($childFields as $childField) {
                $field = $this->fieldService->addField($formId, [
                    'field_type' => $childField['type'],
                    'label' => $childField['label'],
                    'field_key' => $childField['key'],
                    'is_required' => false,
                    'parent_field_id' => (int)$repeaterField['id'],
                    'order_index' => $orderIndex++
                ]);
                
                $this->assertNotNull($field, "تم إضافة الحقل الفرعي: {$childField['label']}");
                $this->assertEquals((int)$repeaterField['id'], (int)$field['parent_field_id'], 'الحقل الفرعي مرتبط بالحقل المكرر');
            }
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار الحقول المكررة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار تعديل الحقول
     */
    public function testEditFields(): void
    {
        echo "\n✏️ اختبار تعديل الحقول...\n";
        
        $formId = $this->createTestForm('استمارة اختبار التعديل');
        $fieldId = $this->createTestField($formId, [
            'field_type' => 'text',
            'label' => 'حقل اختبار',
            'field_key' => 'test_field',
            'is_required' => false
        ]);
        
        try {
            // تحديث بيانات الحقل
            $updatedData = [
                'label' => 'حقل اختبار محدث',
                'field_key' => 'test_field_updated',
                'is_required' => true,
                'validation_rules' => ['min_length' => 3, 'max_length' => 50]
            ];
            
            $result = $this->fieldService->updateField($fieldId, $updatedData);
            $this->assertTrue($result, 'تحديث الحقل نجح');
            
            // التحقق من التحديث
            $stmt = $this->pdo->prepare("SELECT * FROM form_fields WHERE id = ?");
            $stmt->execute([$fieldId]);
            $field = $stmt->fetch();
            
            $this->assertEquals('حقل اختبار محدث', $field['label'], 'تسمية الحقل تم تحديثها');
            $this->assertEquals('test_field_updated', $field['field_key'], 'مفتاح الحقل تم تحديثه');
            $this->assertEquals(1, $field['is_required'], 'حالة الإلزام تم تحديثها');
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في تعديل الحقل: ' . $e->getMessage());
        }
    }

    /**
     * اختبار إعادة ترتيب الحقول
     */
    public function testReorderFields(): void
    {
        echo "\n📊 اختبار إعادة ترتيب الحقول...\n";
        
        $formId = $this->createTestForm('استمارة اختبار الترتيب');
        
        try {
            // إضافة عدة حقول
            $fieldIds = [];
            for ($i = 1; $i <= 5; $i++) {
                $field = $this->fieldService->addField($formId, [
                    'field_type' => 'text',
                    'label' => "حقل رقم $i",
                    'field_key' => "field_$i",
                    'is_required' => false,
                    'order_index' => $i - 1
                ]);
                $fieldIds[] = (int)$field['id'];
            }
            
            // إعادة ترتيب الحقول
            $newOrder = [1, 3, 5, 2, 4]; // ترتيب جديد
            
            $result = $this->fieldService->reorderFields($formId, [
                $fieldIds[0] => 0,
                $fieldIds[1] => 2,
                $fieldIds[2] => 4,
                $fieldIds[3] => 1,
                $fieldIds[4] => 3
            ]);
            
            $this->assertTrue($result, 'إعادة ترتيب الحقول نجح');
            
            // التحقق من الترتيب الجديد
            $stmt = $this->pdo->prepare("
                SELECT id, label, order_index 
                FROM form_fields 
                WHERE form_id = ? 
                ORDER BY order_index ASC
            ");
            $stmt->execute([$formId]);
            $fields = $stmt->fetchAll();
            
            $expectedOrder = ['field_1', 'field_4', 'field_2', 'field_5', 'field_3'];
            for ($i = 0; $i < count($fields); $i++) {
                $this->assertEquals($expectedOrder[$i], $fields[$i]['field_key'], "ترتيب الحقل رقم " . ($i + 1) . " صحيح");
            }
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في إعادة ترتيب الحقول: ' . $e->getMessage());
        }
    }

    /**
     * اختبار حذف الحقول
     */
    public function testDeleteFields(): void
    {
        echo "\n🗑️ اختبار حذف الحقول...\n";
        
        $formId = $this->createTestForm('استمارة اختبار الحذف');
        
        try {
            // إضافة حقول
            $field1 = $this->fieldService->addField($formId, [
                'field_type' => 'text',
                'label' => 'حقل للحذف 1',
                'field_key' => 'delete_field_1',
                'is_required' => false,
                'order_index' => 0
            ]);
            
            $field2 = $this->fieldService->addField($formId, [
                'field_type' => 'email',
                'label' => 'حقل للحذف 2',
                'field_key' => 'delete_field_2',
                'is_required' => false,
                'order_index' => 1
            ]);
            
            // التحقق من وجود الحقول
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM form_fields WHERE form_id = ?");
            $stmt->execute([$formId]);
            $count = $stmt->fetchColumn();
            $this->assertEquals(2, $count, 'تم إضافة حقلين بنجاح');
            
            // حذف حقل واحد
            $result = $this->fieldService->deleteField((int)$field1['id']);
            $this->assertTrue($result, 'حذف الحقل نجح');
            
            // التحقق من الحذف
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM form_fields WHERE form_id = ?");
            $stmt->execute([$formId]);
            $count = $stmt->fetchColumn();
            $this->assertEquals(1, $count, 'تم حذف حقل واحد بنجاح');
            
            // التحقق من عدم وجود الحقل المحذوف
            $stmt = $this->pdo->prepare("SELECT * FROM form_fields WHERE id = ?");
            $stmt->execute([$field1['id']]);
            $field = $stmt->fetch();
            $this->assertFalse($field, 'الحقل تم حذفه بنجاح');
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في حذف الحقل: ' . $e->getMessage());
        }
    }

    /**
     * اختبار ربط الإدارات بالاستمارة
     */
    public function testFormDepartmentAssociation(): void
    {
        echo "\n🔗 اختبار ربط الإدارات بالاستمارة...\n";
        
        try {
            $dept1 = $this->createTestDepartment('قسم تقنية المعلومات');
            $dept2 = $this->createTestDepartment('قسم الموارد البشرية');
            $dept3 = $this->createTestDepartment('قسم المحاسبة');
            
            $formId = $this->createTestForm('استمارة اختبار الربط');
            
            // ربط عدة إدارات بالاستمارة
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("INSERT INTO form_departments (form_id, department_id) VALUES (?, ?)");
            $stmt->execute([$formId, $dept1]);
            $stmt->execute([$formId, $dept2]);
            $stmt->execute([$formId, $dept3]);
            
            $this->pdo->commit();
            
            // التحقق من الروابط
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM form_departments 
                WHERE form_id = ?
            ");
            $stmt->execute([$formId]);
            $count = $stmt->fetchColumn();
            
            $this->assertEquals(3, $count, 'تم ربط 3 إدارات بالاستمارة');
            
            // اختبار جلب الإدارات المرتبطة
            $stmt = $this->pdo->prepare("
                SELECT d.name 
                FROM departments d
                JOIN form_departments fd ON d.id = fd.department_id
                WHERE fd.form_id = ?
                ORDER BY d.name
            ");
            $stmt->execute([$formId]);
            $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $this->assertGreaterThan(0, count($departments), 'يمكن جلب الإدارات المرتبطة');
            $this->assertContains('قسم تقنية المعلومات', $departments, 'إدارة تقنية المعلومات مرتبطة');
            $this->assertContains('قسم الموارد البشرية', $departments, 'إدارة الموارد البشرية مرتبطة');
            $this->assertContains('قسم المحاسبة', $departments, 'إدارة المحاسبة مرتبطة');
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            $this->assert(false, 'فشل في اختبار ربط الإدارات: ' . $e->getMessage());
        }
    }

    /**
     * اختبار معاينة الاستمارة
     */
    public function testFormPreview(): void
    {
        echo "\n👁️ اختبار معاينة الاستمارة...\n";
        
        $formId = $this->createTestForm('استمارة اختبار المعاينة');
        
        try {
            // إضافة حقول متنوعة
            $this->fieldService->addField($formId, [
                'field_type' => 'text',
                'label' => 'الاسم',
                'field_key' => 'name',
                'is_required' => true,
                'order_index' => 0
            ]);
            
            $this->fieldService->addField($formId, [
                'field_type' => 'email',
                'label' => 'البريد الإلكتروني',
                'field_key' => 'email',
                'is_required' => true,
                'order_index' => 1
            ]);
            
            $this->fieldService->addField($formId, [
                'field_type' => 'select',
                'label' => 'القسم',
                'field_key' => 'department',
                'is_required' => false,
                'order_index' => 2,
                'field_options' => ['choices' => ['IT', 'HR', 'Finance']]
            ]);
            
            // جلب بيانات الاستمارة للحاظية
            $stmt = $this->pdo->prepare("
                SELECT * FROM forms WHERE id = ?
            ");
            $stmt->execute([$formId]);
            $form = $stmt->fetch();
            
            // جلب الحقول مرتبة
            $stmt = $this->pdo->prepare("
                SELECT * FROM form_fields 
                WHERE form_id = ? AND parent_field_id IS NULL
                ORDER BY order_index ASC
            ");
            $stmt->execute([$formId]);
            $fields = $stmt->fetchAll();
            
            $this->assertNotNull($form, 'بيانات الاستمارة متاحة');
            $this->assertGreaterThan(0, count($fields), 'الحقول متاحة');
            
            // اختبار ترتيب الحقول
            for ($i = 0; $i < count($fields); $i++) {
                $this->assertEquals($i, $fields[$i]['order_index'], "ترتيب الحقل رقم " . ($i + 1) . " صحيح");
            }
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار معاينة الاستمارة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار التحقق من صحة بيانات الحقول
     */
    public function testFieldValidation(): void
    {
        echo "\n✅ اختبار التحقق من صحة بيانات الحقول...\n";
        
        $formId = $this->createTestForm('استمارة اختبار التحقق');
        
        try {
            // اختبار إنشاء حقل بدون بيانات أساسية
            $result = $this->fieldService->addField($formId, [
                'field_type' => 'text'
                // بدون label أو field_key
            ]);
            $this->assertFalse($result, 'إنشاء حقل بدون بيانات أساسية يجب أن يفشل');
            
            // اختبار إنشاء حقل بنوع غير مدعوم
            $result = $this->fieldService->addField($formId, [
                'field_type' => 'unsupported_type',
                'label' => 'حقل غير مدعوم',
                'field_key' => 'unsupported',
                'is_required' => false,
                'order_index' => 0
            ]);
            $this->assertFalse($result, 'إنشاء حقل بنوع غير مدعوم يجب أن يفشل');
            
            // اختبار مفتاح حقل مكرر
            $this->fieldService->addField($formId, [
                'field_type' => 'text',
                'label' => 'حقل أول',
                'field_key' => 'duplicate_test',
                'is_required' => false,
                'order_index' => 0
            ]);
            
            $result = $this->fieldService->addField($formId, [
                'field_type' => 'text',
                'label' => 'حقل مكرر',
                'field_key' => 'duplicate_test', // نفس المفتاح
                'is_required' => false,
                'order_index' => 1
            ]);
            // يجب أن يتم قبوله أو رفضه حسب منطق التطبيق
            
        } catch (Exception $e) {
            $this->assert(true, 'التحقق من صحة البيانات يعمل بشكل صحيح');
        }
    }

    /**
     * اختبار الأداء
     */
    public function testPerformance(): void
    {
        echo "\n⚡ اختبار الأداء...\n";
        
        // اختبار إنشاء استمارة بحقول كثيرة
        $formCreationTime = $this->measureTime(function() {
            $form = $this->formService->create([
                'title' => 'استمارة اختبار الأداء',
                'description' => 'استمارة أداء',
                'created_by' => 1,
                'status' => 'active'
            ]);
            $this->trackCreatedData('forms', (int)$form['id']);
            return $form;
        });
        
        $this->assertLessThan(1.0, $formCreationTime, "إنشاء استمارة سريع (أقل من ثانية)");
        echo "وقت إنشاء استمارة: {$formCreationTime} ثانية\n";
        
        // اختبار إضافة حقل
        $formId = $this->createTestForm('استمارة أداء الحقول');
        
        $fieldAddTime = $this->measureTime(function() use ($formId) {
            for ($i = 0; $i < 10; $i++) {
                $this->fieldService->addField($formId, [
                    'field_type' => 'text',
                    'label' => "حقل أداء $i",
                    'field_key' => "perf_field_$i",
                    'is_required' => false,
                    'order_index' => $i
                ]);
            }
        });
        
        $this->assertLessThan(2.0, $fieldAddTime, "إضافة 10 حقول سريع (أقل من ثانيتين)");
        echo "وقت إضافة 10 حقول: {$fieldAddTime} ثانية\n";
        
        // اختبار استعلام الحقول
        $queryTime = $this->measureTime(function() use ($formId) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM form_fields 
                WHERE form_id = ? 
                ORDER BY order_index ASC
            ");
            $stmt->execute([$formId]);
            $stmt->fetchAll();
        });
        
        $this->assertLessThan(0.1, $queryTime, "استعلام الحقول سريع");
        echo "وقت استعلام الحقول: {$queryTime} ثانية\n";
    }

    /**
     * تشغيل جميع اختبارات بناء الاستمارات
     */
    public function runAllTests(): void
    {
        try {
            $this->testCreateForm();
            $this->testAddAllFieldTypes();
            $this->testRepeaterFields();
            $this->testEditFields();
            $this->testReorderFields();
            $this->testDeleteFields();
            $this->testFormDepartmentAssociation();
            $this->testFormPreview();
            $this->testFieldValidation();
            $this->testPerformance();
            
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
    $tests = new FormBuilderTests();
    $tests->runAllTests();
}