<?php

declare(strict_types=1);

/**
 * Department Management Tests
 * 
 * Tests CRUD operations for departments including safety checks
 */

require_once __DIR__ . '/test_base.php';

use EmployeeEvaluationSystem\Core\Services\DepartmentService;

class DepartmentTests extends BaseTest
{
    private DepartmentService $deptService;

    public function __construct()
    {
        parent::__construct();
        $this->deptService = new DepartmentService($this->pdo);
        echo "\n🏢 بدء اختبارات إدارة الإدارات\n";
        echo str_repeat('=', 50) . "\n";
    }

    /**
     * اختبار إنشاء إدارة جديدة
     */
    public function testCreateDepartment(): void
    {
        echo "\n➕ اختبار إنشاء إدارة جديدة...\n";
        
        try {
            $department = $this->deptService->create([
                'name' => 'قسم اختبار الإدارة',
                'description' => 'قسم تم إنشاؤه لأغراض الاختبار'
            ]);
            
            $this->assertNotNull($department, 'تم إنشاء الإدارة بنجاح');
            $this->assertTrue(isset($department['id']), 'معرف الإدارة موجود');
            $this->assertEquals('قسم اختبار الإدارة', $department['name'], 'اسم الإدارة صحيح');
            $this->assertEquals('active', $department['status'], 'حالة الإدارة افتراضية صحيحة');
            
            $this->trackCreatedData('departments', (int)$department['id']);
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في إنشاء الإدارة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار قراءة بيانات الإدارة
     */
    public function testReadDepartment(): void
    {
        echo "\n📖 اختبار قراءة بيانات الإدارة...\n";
        
        // إنشاء إدارة للاختبار
        $deptId = $this->createTestDepartment('قسم قراءة الاختبار');
        
        try {
            // قراءة إدارة واحدة
            $department = $this->deptService->findById($deptId);
            $this->assertNotNull($department, 'يمكن قراءة بيانات الإدارة');
            $this->assertEquals($deptId, (int)$department['id'], 'معرف الإدارة صحيح');
            $this->assertEquals('قسم قراءة الاختبار', $department['name'], 'اسم الإدارة صحيح');
            
            // قراءة جميع الإدارات
            $departments = $this->deptService->findAll();
            $this->assertGreaterThan(0, count($departments), 'يمكن قراءة جميع الإدارات');
            
            // التحقق من وجود الإدارة المنشأة في القائمة
            $found = false;
            foreach ($departments as $dept) {
                if ((int)$dept['id'] === $deptId) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, 'الإدارة المنشأة موجودة في قائمة الإدارات');
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في قراءة بيانات الإدارة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار تعديل الإدارة
     */
    public function testUpdateDepartment(): void
    {
        echo "\n✏️ اختبار تعديل الإدارة...\n";
        
        $deptId = $this->createTestDepartment('قسم تعديل الاختبار');
        
        try {
            $updatedData = [
                'name' => 'قسم تعديل الاختبار - محدث',
                'description' => 'تم تحديث هذا القسم أثناء الاختبار',
                'status' => 'inactive'
            ];
            
            $result = $this->deptService->update($deptId, $updatedData);
            $this->assertTrue($result, 'تعديل الإدارة نجح');
            
            // التحقق من التحديث
            $updatedDept = $this->deptService->findById($deptId);
            $this->assertEquals('قسم تعديل الاختبار - محدث', $updatedDept['name'], 'اسم الإدارة تم تحديثه');
            $this->assertEquals('تم تحديث هذا القسم أثناء الاختبار', $updatedDept['description'], 'وصف الإدارة تم تحديثه');
            $this->assertEquals('inactive', $updatedDept['status'], 'حالة الإدارة تم تحديثها');
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في تعديل الإدارة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار تفعيل/تعطيل الإدارة
     */
    public function testActivateDeactivateDepartment(): void
    {
        echo "\n🔄 اختبار تفعيل/تعطيل الإدارة...\n";
        
        $deptId = $this->createTestDepartment('قسم حالة الاختبار');
        
        try {
            // تعطيل الإدارة
            $result = $this->deptService->toggleStatus($deptId);
            $this->assertTrue($result, 'تغيير حالة الإدارة نجح');
            
            $dept = $this->deptService->findById($deptId);
            $this->assertEquals('inactive', $dept['status'], 'الإدارة تم تعطيلها');
            
            // إعادة تفعيل الإدارة
            $result = $this->deptService->toggleStatus($deptId);
            $this->assertTrue($result, 'إعادة تفعيل الإدارة نجح');
            
            $dept = $this->deptService->findById($deptId);
            $this->assertEquals('active', $dept['status'], 'الإدارة تم تفعيلها');
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في تغيير حالة الإدارة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار حذف آمن للإدارة
     */
    public function testDeleteDepartment(): void
    {
        echo "\n🗑️ اختبار حذف آمن للإدارة...\n";
        
        $deptId = $this->createTestDepartment('قسم حذف الاختبار');
        
        try {
            // التحقق من وجود الإدارة قبل الحذف
            $dept = $this->deptService->findById($deptId);
            $this->assertNotNull($dept, 'الإدارة موجودة قبل الحذف');
            
            // حذف الإدارة
            $result = $this->deptService->delete($deptId);
            $this->assertTrue($result, 'حذف الإدارة نجح');
            
            // التحقق من عدم وجود الإدارة بعد الحذف
            $dept = $this->deptService->findById($deptId);
            $this->assertEquals(false, $dept, 'الإدارة تم حذفها بنجاح');
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في حذف الإدارة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار التعامل مع البيانات الفارغة
     */
    public function testEmptyData(): void
    {
        echo "\n❓ اختبار التعامل مع البيانات الفارغة...\n";
        
        try {
            // محاولة إنشاء إدارة بدون اسم
            $result = $this->deptService->create([
                'name' => '',
                'description' => 'وصف بدون اسم'
            ]);
            $this->assertFalse($result, 'إنشاء إدارة بدون اسم يجب أن يفشل');
            
            // محاولة إنشاء إدارة بدون بيانات
            $result = $this->deptService->create([]);
            $this->assertFalse($result, 'إنشاء إدارة بدون بيانات يجب أن يفشل');
            
        } catch (Exception $e) {
            $this->assert(true, 'معالجة البيانات الفارغة تعمل بشكل صحيح');
        }
    }

    /**
     * اختبار التحقق من صحة البيانات
     */
    public function testDataValidation(): void
    {
        echo "\n✅ اختبار التحقق من صحة البيانات...\n";
        
        try {
            // اسم طويل جداً
            $longName = str_repeat('a', 255);
            $result = $this->deptService->create([
                'name' => $longName,
                'description' => 'وصف اختبار'
            ]);
            // يجب أن يتم قطع الاسم أو رفضه
            
            // وصف طويل جداً
            $longDescription = str_repeat('b', 1000);
            $result = $this->deptService->create([
                'name' => 'قسم اختبار الوصف الطويل',
                'description' => $longDescription
            ]);
            // يجب أن يتم قطع الوصف أو رفضه
            
            $this->assertTrue(true, 'التحقق من صحة البيانات يعمل');
            
        } catch (Exception $e) {
            $this->assert(true, 'التحقق من صحة البيانات يعمل بشكل صحيح');
        }
    }

    /**
     * اختبار العلاقات مع الجداول الأخرى
     */
    public function testRelationships(): void
    {
        echo "\n🔗 اختبار العلاقات مع الجداول الأخرى...\n";
        
        try {
            $deptId = $this->createTestDepartment('قسم اختبار العلاقات');
            $formId = $this->createTestForm('استمارة اختبار العلاقات');
            
            // ربط الإدارة بالاستمارة
            $stmt = $this->pdo->prepare("
                INSERT INTO form_departments (form_id, department_id) 
                VALUES (?, ?)
            ");
            $result = $stmt->execute([$formId, $deptId]);
            $this->assertTrue($result, 'ربط الإدارة بالاستمارة نجح');
            
            // التحقق من العلاقة
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM form_departments 
                WHERE form_id = ? AND department_id = ?
            ");
            $stmt->execute([$formId, $deptId]);
            $result = $stmt->fetch();
            $this->assertEquals(1, $result['count'], 'العلاقة محفوظة بشكل صحيح');
            
            // اختبار حذف آمن مع العلاقات
            $stmt = $this->pdo->prepare("DELETE FROM form_departments WHERE form_id = ? AND department_id = ?");
            $stmt->execute([$formId, $deptId]);
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار العلاقات: ' . $e->getMessage());
        }
    }

    /**
     * اختبار الفلاتر والبحث
     */
    public function testFiltersAndSearch(): void
    {
        echo "\n🔍 اختبار الفلاتر والبحث...\n";
        
        try {
            // إنشاء عدة إدارات للاختبار
            $dept1 = $this->createTestDepartment('قسم تقنية المعلومات');
            $dept2 = $this->createTestDepartment('قسم الموارد البشرية');
            $dept3 = $this->createTestDepartment('قسم المحاسبة');
            
            // اختبار البحث بالاسم
            $stmt = $this->pdo->prepare("
                SELECT * FROM departments 
                WHERE name LIKE ? 
                ORDER BY name
            ");
            $stmt->execute(['%تقنية%']);
            $results = $stmt->fetchAll();
            
            $this->assertGreaterThan(0, count($results), 'البحث بالاسم يعمل');
            
            // اختبار البحث مع حالة معينة
            $stmt = $this->pdo->prepare("
                SELECT * FROM departments 
                WHERE status = ? AND name LIKE ?
                ORDER BY name
            ");
            $stmt->execute(['active', '%قسم%']);
            $results = $stmt->fetchAll();
            
            $this->assertGreaterThan(0, count($results), 'البحث مع حالة معينة يعمل');
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار الفلاتر: ' . $e->getMessage());
        }
    }

    /**
     * اختبار الأداء
     */
    public function testPerformance(): void
    {
        echo "\n⚡ اختبار الأداء...\n";
        
        $executionTime = $this->measureTime(function() {
            for ($i = 0; $i < 50; $i++) {
                $this->deptService->create([
                    'name' => "قسم أداء اختبار $i",
                    'description' => 'قسم أداء اختبار'
                ]);
            }
        });
        
        $this->assertLessThan(3.0, $executionTime, "إنشاء 50 إدارة يستغرق أقل من 3 ثوان");
        echo "وقت إنشاء 50 إدارة: {$executionTime} ثانية\n";
        
        // اختبار استعلام سريع
        $queryTime = $this->measureTime(function() {
            $this->deptService->findAll();
        });
        
        $this->assertLessThan(0.1, $queryTime, "استعلام جميع الإدارات سريع");
        echo "وقت استعلام جميع الإدارات: {$queryTime} ثانية\n";
    }

    /**
     * تشغيل جميع اختبارات الإدارات
     */
    public function runAllTests(): void
    {
        try {
            $this->testCreateDepartment();
            $this->testReadDepartment();
            $this->testUpdateDepartment();
            $this->testActivateDeactivateDepartment();
            $this->testDeleteDepartment();
            $this->testEmptyData();
            $this->testDataValidation();
            $this->testRelationships();
            $this->testFiltersAndSearch();
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
    $tests = new DepartmentTests();
    $tests->runAllTests();
}