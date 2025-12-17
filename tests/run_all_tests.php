#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Complete Test Suite Runner
 * 
 * Runs all comprehensive tests for the Employee Evaluation System
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/test_base.php';

// تحديد ألوان الإخراج
class Colors
{
    const RED = "\033[0;31m";
    const GREEN = "\033[0;32m";
    const YELLOW = "\033[1;33m";
    const BLUE = "\033[0;34m";
    const PURPLE = "\033[0;35m";
    const CYAN = "\033[0;36m";
    const WHITE = "\033[1;37m";
    const RESET = "\033[0m";
    const BOLD = "\033[1m";
}

class TestSuiteRunner
{
    private array $testClasses = [];
    private int $totalTests = 0;
    private int $totalPassed = 0;
    private int $totalFailed = 0;
    private float $totalStartTime;
    private array $results = [];

    public function __construct()
    {
        $this->totalStartTime = microtime(true);
        echo "\n" . Colors::CYAN . str_repeat('=', 80) . Colors::RESET . "\n";
        echo Colors::BOLD . Colors::WHITE . "🎯 SUITE الاختبار الشامل لنظام الاستمارات الديناميكية" . Colors::RESET . "\n";
        echo Colors::WHITE . "Employee Evaluation System - Complete Test Suite" . Colors::RESET . "\n";
        echo Colors::CYAN . str_repeat('=', 80) . Colors::RESET . "\n";
    }

    /**
     * تسجيل ملفات الاختبار
     */
    private function registerTestClasses(): void
    {
        $testFiles = [
            'test_database_connection.php' => 'DatabaseConnectionTests',
            'test_departments.php' => 'DepartmentTests',
            'test_forms_builder.php' => 'FormBuilderTests',
            'test_form_submission.php' => 'FormSubmissionTests',
            'test_file_handling.php' => 'FileHandlingTests',
            'test_submissions_queries.php' => 'SubmissionsManagementTests',
            'test_security.php' => 'SecurityTests',
            'test_performance.php' => 'PerformanceTests'
        ];

        foreach ($testFiles as $file => $className) {
            $filePath = __DIR__ . '/' . $file;
            if (file_exists($filePath)) {
                require_once $filePath;
                if (class_exists($className)) {
                    $this->testClasses[] = [
                        'file' => $file,
                        'class' => $className,
                        'path' => $filePath
                    ];
                }
            }
        }
    }

    /**
     * تشغيل اختبار واحد
     */
    private function runTest(string $className, string $file): void
    {
        echo "\n" . Colors::PURPLE . str_repeat('-', 80) . Colors::RESET . "\n";
        echo Colors::BOLD . Colors::BLUE . "📋 تشغيل اختبار: $className" . Colors::RESET . "\n";
        echo Colors::BLUE . "الملف: $file" . Colors::RESET . "\n";
        echo Colors::PURPLE . str_repeat('-', 80) . Colors::RESET . "\n";

        try {
            $testInstance = new $className();
            
            if (method_exists($testInstance, 'runAllTests')) {
                $testStartTime = microtime(true);
                $testInstance->runAllTests();
                $testEndTime = microtime(true);
                $testDuration = $testEndTime - $testStartTime;

                // جمع النتائج
                if (isset($testInstance->testCount)) {
                    $this->totalTests += $testInstance->testCount;
                    $this->totalPassed += $testInstance->passCount;
                    $this->totalFailed += $testInstance->failCount;

                    $this->results[] = [
                        'class' => $className,
                        'duration' => $testDuration,
                        'tests' => $testInstance->testCount,
                        'passed' => $testInstance->passCount,
                        'failed' => $testInstance->failCount,
                        'success_rate' => $testInstance->testCount > 0 ? 
                            round(($testInstance->passCount / $testInstance->testCount) * 100, 2) : 0
                    ];

                    echo "\n" . Colors::YELLOW . "📊 ملخص اختبار $className:" . Colors::RESET . "\n";
                    echo "   ⏱️  الوقت: " . round($testDuration, 2) . " ثانية\n";
                    echo "   📝 الاختبارات: " . $testInstance->testCount . "\n";
                    echo "   ✅ نجح: " . Colors::GREEN . $testInstance->passCount . Colors::RESET . "\n";
                    echo "   ❌ فشل: " . ($testInstance->failCount > 0 ? Colors::RED : Colors::YELLOW) . $testInstance->failCount . Colors::RESET . "\n";
                    echo "   📈 معدل النجاح: " . round(($testInstance->passCount / max($testInstance->testCount, 1)) * 100, 2) . "%\n";
                }
            } else {
                throw new Exception("Method runAllTests not found in class $className");
            }
        } catch (Exception $e) {
            $this->totalFailed++;
            echo "\n" . Colors::RED . "❌ خطأ في تشغيل اختبار $className:" . Colors::RESET . "\n";
            echo "   🔍 الخطأ: " . $e->getMessage() . "\n";
            echo "   📂 المسار: " . $e->getFile() . ":" . $e->getLine() . "\n";
        }
    }

    /**
     * تشغيل جميع الاختبارات
     */
    public function runAllTests(): void
    {
        $this->registerTestClasses();

        if (empty($this->testClasses)) {
            echo Colors::RED . "❌ لم يتم العثور على ملفات اختبار!" . Colors::RESET . "\n";
            return;
        }

        echo Colors::CYAN . "\n📋 سيتم تشغيل " . count($this->testClasses) . " مجموعة اختبار:" . Colors::RESET . "\n";
        foreach ($this->testClasses as $index => $test) {
            echo "   " . ($index + 1) . ". " . $test['class'] . " (" . basename($test['file']) . ")\n";
        }

        // تشغيل كل اختبار
        foreach ($this->testClasses as $test) {
            $this->runTest($test['class'], $test['file']);
        }

        // طباعة التقرير النهائي
        $this->printFinalReport();
    }

    /**
     * طباعة التقرير النهائي
     */
    private function printFinalReport(): void
    {
        $totalEndTime = microtime(true);
        $totalDuration = $totalEndTime - $this->totalStartTime;

        echo "\n" . Colors::CYAN . str_repeat('=', 80) . Colors::RESET . "\n";
        echo Colors::BOLD . Colors::WHITE . "🎉 التقرير النهائي للاختبارات الشاملة" . Colors::RESET . "\n";
        echo Colors::CYAN . str_repeat('=', 80) . Colors::RESET . "\n";

        // الإحصائيات العامة
        echo "\n" . Colors::YELLOW . "📊 الإحصائيات العامة:" . Colors::RESET . "\n";
        echo "   ⏱️  إجمالي الوقت: " . round($totalDuration, 2) . " ثانية\n";
        echo "   📝 إجمالي الاختبارات: " . $this->totalTests . "\n";
        echo "   ✅ إجمالي الناجح: " . Colors::GREEN . $this->totalPassed . Colors::RESET . "\n";
        echo "   ❌ إجمالي الفاشل: " . ($this->totalFailed > 0 ? Colors::RED : Colors::YELLOW) . $this->totalFailed . Colors::RESET . "\n";
        echo "   📈 معدل النجاح الإجمالي: " . round(($this->totalPassed / max($this->totalTests, 1)) * 100, 2) . "%\n";

        // تفاصيل كل مجموعة اختبار
        echo "\n" . Colors::YELLOW . "📋 تفاصيل كل مجموعة اختبار:" . Colors::RESET . "\n";
        foreach ($this->results as $result) {
            $statusColor = $result['failed'] > 0 ? Colors::YELLOW : Colors::GREEN;
            echo sprintf(
                "   %-30s | %6.2fs | %3d tests | %3d ✅ | %3d ❌ | %5.1f%%\n",
                $result['class'],
                $result['duration'],
                $result['tests'],
                $result['passed'],
                $result['failed'],
                $result['success_rate']
            );
        }

        // توصيات
        echo "\n" . Colors::YELLOW . "💡 التوصيات:" . Colors::RESET . "\n";
        
        if ($this->totalFailed === 0) {
            echo "   " . Colors::GREEN . "🎉 ممتاز! جميع الاختبارات نجحت. النظام جاهز للإنتاج." . Colors::RESET . "\n";
        } else {
            $failureRate = ($this->totalFailed / $this->totalTests) * 100;
            if ($failureRate > 10) {
                echo "   " . Colors::RED . "⚠️  نسبة الفشل عالية (" . round($failureRate, 1) . "%). يُنصح بفحص وإصلاح المشاكل." . Colors::RESET . "\n";
            } elseif ($failureRate > 5) {
                echo "   " . Colors::YELLOW . "⚠️  نسبة الفشل متوسطة (" . round($failureRate, 1) . "%). يُنصح بمراجعة الفاشلات." . Colors::RESET . "\n";
            } else {
                echo "   " . Colors::GREEN . "✅ نسبة الفشل منخفضة (" . round($failureRate, 1) . "%). النظام يعمل بشكل جيد." . Colors::RESET . "\n";
            }
        }

        // إحصائيات الأداء
        if ($this->totalTests > 0) {
            $avgTestTime = $totalDuration / $this->totalTests;
            if ($avgTestTime > 0.1) {
                echo "   " . Colors::YELLOW . "⚡ متوسط وقت الاختبار: " . round($avgTestTime * 1000, 2) . " مللي ثانية (يمكن تحسينه)" . Colors::RESET . "\n";
            } else {
                echo "   " . Colors::GREEN . "⚡ متوسط وقت الاختبار: " . round($avgTestTime * 1000, 2) . " مللي ثانية (ممتاز)" . Colors::RESET . "\n";
            }
        }

        // النتيجة النهائية
        echo "\n" . Colors::CYAN . str_repeat('=', 80) . Colors::RESET . "\n";
        if ($this->totalFailed === 0) {
            echo Colors::BOLD . Colors::GREEN . "🎉 تم بنجاح - جميع الاختبارات نجحت! 🎉" . Colors::RESET . "\n";
        } else {
            echo Colors::BOLD . Colors::YELLOW . "⚠️ تم الانتهاء - يوجد " . $this->totalFailed . " اختبار فاشل ⚠️" . Colors::RESET . "\n";
        }
        echo Colors::CYAN . str_repeat('=', 80) . Colors::RESET . "\n";
    }
}

// تشغيل الـ Test Suite
function main(): void
{
    $runner = new TestSuiteRunner();
    $runner->runAllTests();
}

// التحقق من التشغيل من CLI
if (php_sapi_name() === 'cli') {
    main();
} else {
    echo Colors::RED . "❌ يجب تشغيل هذا الملف من سطر الأوامر!" . Colors::RESET . "\n";
    echo "الاستخدام: php " . basename(__FILE__) . "\n";
    exit(1);
}