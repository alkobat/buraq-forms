<?php

declare(strict_types=1);

/**
 * Database Connection Tests
 * 
 * Tests database connectivity, table existence, and query functionality
 */

require_once __DIR__ . '/test_base.php';

class DatabaseConnectionTests extends BaseTest
{
    public function __construct()
    {
        parent::__construct();
        echo "\n🔍 بدء اختبارات قاعدة البيانات والاتصال\n";
        echo str_repeat('=', 50) . "\n";
    }

    /**
     * اختبار الاتصال بقاعدة البيانات
     */
    public function testPDOConnection(): void
    {
        echo "\n📡 اختبار اتصال PDO...\n";
        
        $this->assertNotNull($this->pdo, 'اتصال PDO موجود');
        
        try {
            $stmt = $this->pdo->query("SELECT 1 as test");
            $result = $stmt->fetch();
            $this->assertEquals(1, $result['test'], 'استعلام بسيط يعمل');
        } catch (Exception $e) {
            $this->assert(false, 'خطأ في تنفيذ الاستعلام: ' . $e->getMessage());
        }
    }

    /**
     * اختبار معلومات قاعدة البيانات
     */
    public function testDatabaseInfo(): void
    {
        echo "\nℹ️ اختبار معلومات قاعدة البيانات...\n";
        
        // إصدار MySQL
        $stmt = $this->pdo->query("SELECT VERSION() as version");
        $result = $stmt->fetch();
        $this->assertNotNull($result['version'], 'يمكن الحصول على إصدار MySQL');
        
        // الترميز
        $stmt = $this->pdo->query("SELECT @@character_set_connection as charset");
        $result = $stmt->fetch();
        $this->assertNotNull($result['charset'], 'يمكن الحصول على ترميز الاتصال');
        
        // قاعدة البيانات الحالية
        $stmt = $this->pdo->query("SELECT DATABASE() as db_name");
        $result = $stmt->fetch();
        $this->assertNotNull($result['db_name'], 'يمكن الحصول على اسم قاعدة البيانات');
        
        echo "معلومات قاعدة البيانات:\n";
        echo "- الإصدار: {$result['version']}\n";
        echo "- الترميز: {$result['charset']}\n";
        echo "- الاسم: {$result['db_name']}\n";
    }

    /**
     * اختبار وجود الجداول الأساسية
     */
    public function testTablesExist(): void
    {
        echo "\n📋 اختبار وجود الجداول...\n";
        
        $requiredTables = [
            'admins',
            'departments', 
            'forms',
            'form_fields',
            'form_submissions',
            'submission_answers',
            'system_settings',
            'file_download_logs'
        ];
        
        foreach ($requiredTables as $table) {
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            $exists = $stmt->fetch() !== false;
            $this->assert($exists, "الجدول {$table} موجود");
        }
    }

    /**
     * اختبار بنية الجداول
     */
    public function testTableStructure(): void
    {
        echo "\n🏗️ اختبار بنية الجداول...\n";
        
        // اختبار جدول departments
        $stmt = $this->pdo->query("DESCRIBE departments");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $requiredColumns = ['id', 'name', 'description', 'status', 'created_at'];
        
        foreach ($requiredColumns as $column) {
            $this->assert(in_array($column, $columns), "عمود {$column} موجود في جدول departments");
        }
        
        // اختبار جدول forms
        $stmt = $this->pdo->query("DESCRIBE forms");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $requiredColumns = ['id', 'title', 'slug', 'description', 'status', 'created_by'];
        
        foreach ($requiredColumns as $column) {
            $this->assert(in_array($column, $columns), "عمود {$column} موجود في جدول forms");
        }
        
        // اختبار جدول form_fields
        $stmt = $this->pdo->query("DESCRIBE form_fields");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $requiredColumns = ['id', 'form_id', 'field_type', 'label', 'field_key', 'is_required'];
        
        foreach ($requiredColumns as $column) {
            $this->assert(in_array($column, $columns), "عمود {$column} موجود في جدول form_fields");
        }
    }

    /**
     * اختبار العلاقات بين الجداول
     */
    public function testTableRelationships(): void
    {
        echo "\n🔗 اختبار العلاقات بين الجداول...\n";
        
        // إنشاء بيانات اختبار
        $deptId = $this->createTestDepartment('قسم اختبار العلاقات');
        $formId = $this->createTestForm('استمارة اختبار العلاقات');
        $fieldId = $this->createTestField($formId, ['field_key' => 'test_field']);
        
        // اختبار علاقة form_fields -> forms
        $stmt = $this->pdo->prepare("
            SELECT f.title, ff.label 
            FROM forms f 
            JOIN form_fields ff ON f.id = ff.form_id 
            WHERE ff.id = ?
        ");
        $stmt->execute([$fieldId]);
        $result = $stmt->fetch();
        
        $this->assertNotNull($result, 'علاقة form_fields -> forms تعمل');
        $this->assertEquals('استمارة اختبار العلاقات', $result['title'], 'بيانات الاستمارة صحيحة');
        $this->assertEquals('حقل اختبار', $result['label'], 'بيانات الحقل صحيحة');
        
        // اختبار علاقة forms -> departments (عبر form_departments)
        $stmt = $this->pdo->prepare("
            SELECT d.name 
            FROM forms f 
            JOIN form_departments fd ON f.id = fd.form_id 
            JOIN departments d ON fd.department_id = d.id 
            WHERE f.id = ?
        ");
        $stmt->execute([$formId]);
        $result = $stmt->fetch();
        
        $this->assertNotNull($result, 'علاقة forms -> departments تعمل');
    }

    /**
     * اختبار استعلامات معقدة
     */
    public function testComplexQueries(): void
    {
        echo "\n🔍 اختبار الاستعلامات المعقدة...\n";
        
        // إنشاء بيانات اختبار
        $deptId = $this->createTestDepartment('قسم اختبار الاستعلامات');
        $formId = $this->createTestForm('استمارة اختبار الاستعلامات');
        
        // إضافة عدة حقول
        $this->createTestField($formId, ['field_type' => 'text', 'field_key' => 'name']);
        $this->createTestField($formId, ['field_type' => 'email', 'field_key' => 'email']);
        $this->createTestField($formId, ['field_type' => 'select', 'field_key' => 'department']);
        
        // اختبار استعلام عد الحقول
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as field_count 
            FROM form_fields 
            WHERE form_id = ?
        ");
        $stmt->execute([$formId]);
        $result = $stmt->fetch();
        
        $this->assertEquals(3, $result['field_count'], 'عدد الحقول صحيح');
        
        // اختبار استعلام مع GROUP BY
        $stmt = $this->pdo->query("
            SELECT field_type, COUNT(*) as count 
            FROM form_fields 
            GROUP BY field_type
        ");
        $results = $stmt->fetchAll();
        
        $this->assertGreaterThan(0, count($results), 'استعلام GROUP BY يعمل');
        
        // اختبار استعلام مع JOIN معقد
        $stmt = $this->pdo->query("
            SELECT 
                f.title as form_title,
                COUNT(ff.id) as field_count,
                COUNT(DISTINCT fd.department_id) as department_count
            FROM forms f
            LEFT JOIN form_fields ff ON f.id = ff.form_id
            LEFT JOIN form_departments fd ON f.id = fd.form_id
            WHERE f.id = ?
            GROUP BY f.id, f.title
        ");
        $stmt->execute([$formId]);
        $result = $stmt->fetch();
        
        $this->assertNotNull($result, 'استعلام معقد مع JOIN يعمل');
    }

    /**
     * اختبار الفلاتر
     */
    public function testQueryFilters(): void
    {
        echo "\n🔍 اختبار الفلاتر...\n";
        
        // إنشاء بيانات اختبار متعددة
        $deptId1 = $this->createTestDepartment('قسم اختبار الفلاتر 1');
        $deptId2 = $this->createTestDepartment('قسم اختبار الفلاتر 2');
        
        $form1 = $this->createTestForm('استمارة اختبار الفلاتر A');
        $form2 = $this->createTestForm('استمارة اختبار الفلاتر B');
        $form3 = $this->createTestForm('استمارة اختبار الفلاتر C');
        
        // اختبار فلتر النصوص
        $stmt = $this->pdo->prepare("
            SELECT * FROM forms 
            WHERE title LIKE ? 
            ORDER BY id
        ");
        $stmt->execute(['%اختبار%']);
        $results = $stmt->fetchAll();
        
        $this->assertGreaterThanOrEqual(3, count($results), 'فلتر النصوص يعمل');
        
        // اختبار فلتر التاريخ
        $today = date('Y-m-d');
        $stmt = $this->pdo->prepare("
            SELECT * FROM forms 
            WHERE DATE(created_at) = ? 
            ORDER BY id
        ");
        $stmt->execute([$today]);
        $results = $stmt->fetchAll();
        
        $this->assertGreaterThanOrEqual(3, count($results), 'فلتر التاريخ يعمل');
    }

    /**
     * اختبار الأداء
     */
    public function testPerformance(): void
    {
        echo "\n⚡ اختبار الأداء...\n";
        
        $executionTime = $this->measureTime(function() {
            // إنشاء 100 إدخال سريع
            for ($i = 0; $i < 100; $i++) {
                $this->createTestForm("استمارة أداء اختبار $i");
            }
        });
        
        $this->assertLessThan(5.0, $executionTime, "إنشاء 100 استمارة يستغرق أقل من 5 ثوان (الوقت الفعلي: {$executionTime}s)");
        
        echo "وقت إنشاء 100 استمارة: {$executionTime} ثانية\n";
        
        // اختبار استعلام سريع
        $queryTime = $this->measureTime(function() {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM forms");
            $stmt->fetch();
        });
        
        $this->assertLessThan(0.1, $queryTime, "استعلام بسيط يستغرق أقل من 0.1 ثانية (الوقت الفعلي: {$queryTime}s)");
        echo "وقت استعلام بسيط: {$queryTime} ثانية\n";
    }

    /**
     * تشغيل جميع الاختبارات
     */
    public function runAllTests(): void
    {
        try {
            $this->testPDOConnection();
            $this->testDatabaseInfo();
            $this->testTablesExist();
            $this->testTableStructure();
            $this->testTableRelationships();
            $this->testComplexQueries();
            $this->testQueryFilters();
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
    $tests = new DatabaseConnectionTests();
    $tests->runAllTests();
}