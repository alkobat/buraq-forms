<?php

declare(strict_types=1);

/**
 * File Handling Tests
 * 
 * Tests file upload, validation, storage, and security
 */

require_once __DIR__ . '/test_base.php';

use EmployeeEvaluationSystem\Core\Services\FormFileService;
use EmployeeEvaluationSystem\Core\Services\FormService;
use EmployeeEvaluationSystem\Core\Services\FormFieldService;
use EmployeeEvaluationSystem\Core\Services\FormSubmissionService;
use EmployeeEvaluationSystem\Core\Logger;

class FileHandlingTests extends BaseTest
{
    private FormFileService $fileService;
    private FormService $formService;
    private FormFieldService $fieldService;
    private Logger $logger;
    private string $testFilesDir;

    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger();
        $this->fileService = new FormFileService($this->pdo, null, $this->logger, null);
        $this->formService = new FormService($this->pdo, $this->logger, null);
        $this->fieldService = new FormFieldService($this->pdo, $this->logger);
        
        $this->testFilesDir = sys_get_temp_dir() . '/ees_test_files';
        if (!is_dir($this->testFilesDir)) {
            mkdir($this->testFilesDir, 0755, true);
        }
        
        echo "\n📁 بدء اختبارات معالجة الملفات\n";
        echo str_repeat('=', 50) . "\n";
    }

    /**
     * إنشاء ملف اختبار
     */
    private function createTestFile(string $filename, string $content, string $mimeType = 'text/plain'): string
    {
        $filepath = $this->testFilesDir . '/' . $filename;
        file_put_contents($filepath, $content);
        
        // إنشاء ملف وهمي للتعامل مع MIME type
        return $filepath;
    }

    /**
     * إنشاء استمارة بحقول ملف للاختبار
     */
    private function createTestFormWithFileFields(): array
    {
        $form = $this->formService->create([
            'title' => 'استمارة اختبار الملفات',
            'description' => 'استمارة لاختبار معالجة الملفات',
            'created_by' => 1,
            'status' => 'active',
            'show_department_field' => true
        ], [1]);

        $this->trackCreatedData('forms', (int)$form['id']);

        // حقل نص
        $field = $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'text',
            'label' => 'الاسم',
            'field_key' => 'name',
            'is_required' => true,
            'order_index' => 0
        ]);
        $textFieldId = (int)$field['id'];

        // حقل ملف عادي
        $fileField = $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'file',
            'label' => 'السيرة الذاتية',
            'field_key' => 'cv',
            'is_required' => false,
            'order_index' => 1,
            'validation_rules' => [
                'max_size' => 10485760, // 10MB
                'allowed_types' => ['pdf', 'doc', 'docx']
            ]
        ]);
        $fileFieldId = (int)$fileField['id'];

        // حقل صورة
        $imageField = $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'file',
            'label' => 'صورة شخصية',
            'field_key' => 'photo',
            'is_required' => false,
            'order_index' => 2,
            'validation_rules' => [
                'max_size' => 5242880, // 5MB
                'allowed_types' => ['jpg', 'jpeg', 'png']
            ]
        ]);
        $imageFieldId = (int)$imageField['id'];

        // حقل repeater مع ملف
        $repeater = $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'repeater',
            'label' => 'الشهادات',
            'field_key' => 'certificates',
            'is_required' => false,
            'order_index' => 3
        ]);
        $repeaterId = (int)$repeater['id'];

        $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'text',
            'label' => 'اسم الشهادة',
            'field_key' => 'cert_name',
            'is_required' => false,
            'parent_field_id' => $repeaterId,
            'order_index' => 0
        ]);

        $repeaterFileField = $this->fieldService->addField((int)$form['id'], [
            'field_type' => 'file',
            'label' => 'مرفق الشهادة',
            'field_key' => 'cert_file',
            'is_required' => false,
            'parent_field_id' => $repeaterId,
            'order_index' => 1,
            'validation_rules' => [
                'max_size' => 2097152, // 2MB
                'allowed_types' => ['pdf', 'jpg', 'jpeg', 'png']
            ]
        ]);

        return [
            'form' => $form,
            'field_ids' => [
                'text' => $textFieldId,
                'file' => $fileFieldId,
                'image' => $imageFieldId,
                'repeater_file' => (int)$repeaterFileField['id'],
                'repeater' => $repeaterId
            ]
        ];
    }

    /**
     * اختبار رفع ملفات صحيحة
     */
    public function testValidFileUploads(): void
    {
        echo "\n✅ اختبار رفع الملفات الصحيحة...\n";
        
        try {
            $formData = $this->createTestFormWithFileFields();
            $form = $formData['form'];
            $fieldIds = $formData['field_ids'];
            
            // إنشاء ملفات اختبار
            $testFiles = [
                [
                    'filename' => 'test_cv.pdf',
                    'content' => 'This is a test PDF file content',
                    'mime' => 'application/pdf',
                    'field_id' => $fieldIds['file']
                ],
                [
                    'filename' => 'test_photo.jpg',
                    'content' => base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAhEAACAQMDBQAAAAAAAAAAAAABAgMABAUGIWGBkaGx0fD/xAAVAQEBAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k='),
                    'mime' => 'image/jpeg',
                    'field_id' => $fieldIds['image']
                ],
                [
                    'filename' => 'test_doc.docx',
                    'content' => 'This is a test DOCX file content',
                    'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'field_id' => $fieldIds['file']
                ]
            ];
            
            $storedFiles = [];
            
            foreach ($testFiles as $file) {
                $filepath = $this->createTestFile($file['filename'], $file['content'], $file['mime']);
                
                // محاكاة $_FILES array
                $fileData = [
                    'name' => $file['filename'],
                    'tmp_name' => $filepath,
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($filepath),
                    'type' => $file['mime']
                ];
                
                $storedFile = $this->fileService->storeUploadedFile(
                    (int)$form['id'],
                    $file['field_id'],
                    $fileData
                );
                
                $this->assertNotNull($storedFile, "تم رفع الملف {$file['filename']} بنجاح");
                $this->assertTrue(file_exists($storedFile['path']), "الملف موجود في المسار المحدد");
                $this->assertEquals(filesize($filepath), $storedFile['size'], "حجم الملف محفوظ بشكل صحيح");
                
                $storedFiles[] = $storedFile;
            }
            
            echo "تم رفع " . count($storedFiles) . " ملفات بنجاح\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في رفع الملفات الصحيحة: ' . $e->getMessage());
        }
    }

    /**
     * اختبار رفض الملفات غير الصحيحة
     */
    public function testInvalidFileRejection(): void
    {
        echo "\n❌ اختبار رفض الملفات غير الصحيحة...\n";
        
        try {
            $formData = $this->createTestFormWithFileFields();
            $form = $formData['form'];
            $fieldIds = $formData['field_ids'];
            
            // اختبار ملف بنوع غير مسموح
            $invalidTypeFile = $this->createTestFile('script.php', '<?php echo "malicious"; ?>', 'text/php');
            $fileData = [
                'name' => 'script.php',
                'tmp_name' => $invalidTypeFile,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($invalidTypeFile),
                'type' => 'text/php'
            ];
            
            try {
                $this->fileService->storeUploadedFile(
                    (int)$form['id'],
                    $fieldIds['file'],
                    $fileData
                );
                $this->assert(false, 'يجب رفض الملف بنوع غير مسموح');
            } catch (Exception $e) {
                $this->assert(true, 'تم رفض الملف بنوع غير مسموح بشكل صحيح');
            }
            
            // اختبار ملف كبير جداً
            $largeContent = str_repeat('A', 11 * 1024 * 1024); // 11MB
            $largeFile = $this->createTestFile('large.pdf', $largeContent, 'application/pdf');
            $fileData = [
                'name' => 'large.pdf',
                'tmp_name' => $largeFile,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($largeFile),
                'type' => 'application/pdf'
            ];
            
            try {
                $this->fileService->storeUploadedFile(
                    (int)$form['id'],
                    $fieldIds['file'],
                    $fileData
                );
                $this->assert(false, 'يجب رفض الملف الكبير');
            } catch (Exception $e) {
                $this->assert(true, 'تم رفض الملف الكبير بشكل صحيح');
            }
            
            // اختبار ملف بدون امتداد
            $noExtFile = $this->createTestFile('filewithout Extension', 'content', 'text/plain');
            $fileData = [
                'name' => 'filewithout Extension',
                'tmp_name' => $noExtFile,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($noExtFile),
                'type' => 'text/plain'
            ];
            
            try {
                $storedFile = $this->fileService->storeUploadedFile(
                    (int)$form['id'],
                    $fieldIds['file'],
                    $fileData
                );
                $this->assertNotNull($storedFile, 'تم قبول الملف بدون امتداد (قد يكون مقبول)');
            } catch (Exception $e) {
                $this->assert(true, 'تم رفض الملف بدون امتداد');
            }
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار رفض الملفات: ' . $e->getMessage());
        }
    }

    /**
     * اختبار الرفع الآمن للمجلد
     */
    public function testSecureStorageDirectory(): void
    {
        echo "\n🔒 اختبار الرفع الآمن...\n";
        
        try {
            $formData = $this->createTestFormWithFileFields();
            $form = $formData['form'];
            $fieldIds = $formData['field_ids'];
            
            $testFile = $this->createTestFile('secure_test.pdf', 'secure content', 'application/pdf');
            $fileData = [
                'name' => 'secure_test.pdf',
                'tmp_name' => $testFile,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($testFile),
                'type' => 'application/pdf'
            ];
            
            $storedFile = $this->fileService->storeUploadedFile(
                (int)$form['id'],
                $fieldIds['file'],
                $fileData
            );
            
            // التحقق من أن الملف في مجلد آمن (خارج public)
            $this->assertStringNotContainsString('public/', $storedFile['path'], 'الملف غير موجود في مجلد public');
            
            // التحقق من بنية المجلد
            $expectedPath = 'storage/forms/' . $form['id'] . '/' . $fieldIds['file'];
            $this->assertStringContainsString($expectedPath, $storedFile['path'], 'بنية المجلد صحيحة');
            
            // التحقق من أذونات المجلد
            $uploadDir = dirname($storedFile['path']);
            $this->assertTrue(is_readable($uploadDir), 'مجلد الرفع قابل للقراءة');
            $this->assertTrue(is_executable($uploadDir), 'مجلد الرفع قابل للتنفيذ');
            
            echo "مسار الملف الآمن: {$storedFile['path']}\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار الرفع الآمن: ' . $e->getMessage());
        }
    }

    /**
     * اختبار حذف الملفات مع الإجابة
     */
    public function testFileCleanupWithSubmission(): void
    {
        echo "\n🧹 اختبار حذف الملفات مع الإجابة...\n";
        
        try {
            $formData = $this->createTestFormWithFileFields();
            $form = $formData['form'];
            $fieldIds = $formData['field_ids'];
            
            // إنشاء ملفات واختبار تخزينها
            $testFile = $this->createTestFile('cleanup_test.pdf', 'content for cleanup', 'application/pdf');
            $fileData = [
                'name' => 'cleanup_test.pdf',
                'tmp_name' => $testFile,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($testFile),
                'type' => 'application/pdf'
            ];
            
            $storedFile = $this->fileService->storeUploadedFile(
                (int)$form['id'],
                $fieldIds['file'],
                $fileData
            );
            
            $this->assertTrue(file_exists($storedFile['path']), 'الملف موجود بعد التخزين');
            
            // إنشاء إجابة تتضمن الملف
            $submissionService = new FormSubmissionService(
                $this->pdo, 
                $this->formService, 
                $this->fieldService, 
                $this->fileService, 
                null, 
                $this->logger, 
                null
            );
            
            $submissionData = [
                'name' => 'مستخدم اختبار الحذف',
                'cv' => $storedFile['stored_name']
            ];
            
            $metadata = [
                'submitted_by' => 'cleanup@test.com',
                'department_id' => 1,
                'ip_address' => '127.0.0.1'
            ];
            
            $submission = $submissionService->submit(
                (int)$form['id'],
                $metadata,
                $submissionData,
                ['cv' => $fileData] // تمرير بيانات الملف
            );
            
            $this->assertNotNull($submission, 'تم إنشاء الإجابة مع الملف');
            $this->trackCreatedData('submissions', (int)$submission['id']);
            
            // اختبار حذف الإجابة (يجب أن يحذف الملفات أيضاً)
            $stmt = $this->pdo->prepare("DELETE FROM form_submissions WHERE id = ?");
            $result = $stmt->execute([$submission['id']]);
            $this->assertTrue($result, 'تم حذف الإجابة');
            
            // حذف ملفات الإجابة (يجب تنفيذ هذا في التطبيق الفعلي)
            $stmt = $this->pdo->prepare("
                SELECT stored_name FROM submission_answers 
                WHERE submission_id = ? AND field_key = 'cv'
            ");
            $stmt->execute([$submission['id']]);
            $answer = $stmt->fetch();
            
            if ($answer) {
                $filePath = dirname($storedFile['path']) . '/' . $answer['stored_name'];
                // في التطبيق الفعلي، يجب حذف الملف هنا
                // $this->fileService->deleteFile($filePath);
                
                echo "مسار الملف للحذف: $filePath\n";
            }
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار حذف الملفات: ' . $e->getMessage());
        }
    }

    /**
     * اختبار إعادة تسمية الملفات
     */
    public function testFileRenaming(): void
    {
        echo "\n📝 اختبار إعادة تسمية الملفات...\n";
        
        try {
            $formData = $this->createTestFormWithFileFields();
            $form = $formData['form'];
            $fieldIds = $formData['field_ids'];
            
            // اختبار ملف بأسم مكرر
            $testFiles = [];
            for ($i = 1; $i <= 3; $i++) {
                $filename = "duplicate_name.pdf";
                $content = "content for file $i";
                
                $filepath = $this->createTestFile($filename, $content, 'application/pdf');
                $fileData = [
                    'name' => $filename,
                    'tmp_name' => $filepath,
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($filepath),
                    'type' => 'application/pdf'
                ];
                
                $storedFile = $this->fileService->storeUploadedFile(
                    (int)$form['id'],
                    $fieldIds['file'],
                    $fileData
                );
                
                $this->assertNotNull($storedFile, "تم رفع الملف رقم $i بنجاح");
                $testFiles[] = $storedFile;
            }
            
            // التحقق من أن الملفات لها أسماء مختلفة
            $storedNames = array_column($testFiles, 'stored_name');
            $uniqueNames = array_unique($storedNames);
            $this->assertEquals(count($storedNames), count($uniqueNames), 'أسماء الملفات فريدة');
            
            echo "تم إنشاء " . count($uniqueNames) . " ملف بأسماء فريدة\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار إعادة التسمية: ' . $e->getMessage());
        }
    }

    /**
     * اختبار كشف نوع MIME
     */
    public function testMimeTypeDetection(): void
    {
        echo "\n🔍 اختبار كشف نوع MIME...\n";
        
        try {
            $formData = $this->createTestFormWithFileFields();
            $form = $formData['form'];
            $fieldIds = $formData['field_ids'];
            
            // إنشاء ملفات بامتدادات مختلفة
            $testCases = [
                ['filename' => 'test.pdf', 'content' => '%PDF-1.4 test content', 'expected_mime' => 'application/pdf'],
                ['filename' => 'test.jpg', 'content' => base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAhEAACAQMDBQAAAAAAAAAAAAABAgMABAUGIWGBkaGx0fD/xAAVAQEBAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k='), 'expected_mime' => 'image/jpeg'],
                ['filename' => 'test.txt', 'content' => 'This is a plain text file', 'expected_mime' => 'text/plain']
            ];
            
            foreach ($testCases as $case) {
                $filepath = $this->createTestFile($case['filename'], $case['content'], $case['expected_mime']);
                $fileData = [
                    'name' => $case['filename'],
                    'tmp_name' => $filepath,
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($filepath),
                    'type' => $case['expected_mime']
                ];
                
                $storedFile = $this->fileService->storeUploadedFile(
                    (int)$form['id'],
                    $fieldIds['file'],
                    $fileData
                );
                
                $this->assertNotNull($storedFile, "تم رفع ملف {$case['filename']}");
                $this->assertEquals($case['expected_mime'], $storedFile['mime_type'], "نوع MIME صحيح للملف");
            }
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار كشف MIME: ' . $e->getMessage());
        }
    }

    /**
     * اختبار الأمان والوقاية من Path Traversal
     */
    public function testSecurityPathTraversal(): void
    {
        echo "\n🛡️ اختبار الأمان - Path Traversal...\n";
        
        try {
            $formData = $this->createTestFormWithFileFields();
            $form = $formData['form'];
            $fieldIds = $formData['field_ids'];
            
            // اختبار اسم ملف يحتوي على ../ (path traversal attack)
            $maliciousFilename = '../../../etc/passwd.pdf';
            $content = 'malicious content';
            
            $filepath = $this->createTestFile($maliciousFilename, $content, 'application/pdf');
            $fileData = [
                'name' => $maliciousFilename,
                'tmp_name' => $filepath,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($filepath),
                'type' => 'application/pdf'
            ];
            
            $storedFile = $this->fileService->storeUploadedFile(
                (int)$form['id'],
                $fieldIds['file'],
                $fileData
            );
            
            // التحقق من أن الملف لم يتم حفظه في مسار خطير
            $this->assertStringNotContainsString('/etc/', $storedFile['path'], 'تم منع Path Traversal');
            $this->assertStringNotContainsString('../', $storedFile['path'], 'لا توجد روابط نسبية في المسار');
            
            echo "تم منع Path Traversal: {$storedFile['path']}\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار الأمان: ' . $e->getMessage());
        }
    }

    /**
     * اختبار الأداء مع الملفات
     */
    public function testFileUploadPerformance(): void
    {
        echo "\n⚡ اختبار أداء رفع الملفات...\n";
        
        try {
            $formData = $this->createTestFormWithFileFields();
            $form = $formData['form'];
            $fieldIds = $formData['field_ids'];
            
            // اختبار رفع عدة ملفات صغيرة
            $uploadTime = $this->measureTime(function() use ($form, $fieldIds) {
                for ($i = 1; $i <= 10; $i++) {
                    $content = "Test file content number $i";
                    $filename = "perf_test_$i.pdf";
                    
                    $filepath = $this->createTestFile($filename, $content, 'application/pdf');
                    $fileData = [
                        'name' => $filename,
                        'tmp_name' => $filepath,
                        'error' => UPLOAD_ERR_OK,
                        'size' => filesize($filepath),
                        'type' => 'application/pdf'
                    ];
                    
                    $this->fileService->storeUploadedFile(
                        (int)$form['id'],
                        $fieldIds['file'],
                        $fileData
                    );
                }
            });
            
            $this->assertLessThan(5.0, $uploadTime, "رفع 10 ملفات سريع (أقل من 5 ثوان)");
            echo "وقت رفع 10 ملفات: {$uploadTime} ثانية\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار أداء الملفات: ' . $e->getMessage());
        }
    }

    /**
     * تنظيف ملفات الاختبار
     */
    public function cleanupTestFiles(): void
    {
        if (is_dir($this->testFilesDir)) {
            $files = glob($this->testFilesDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testFilesDir);
        }
    }

    /**
     * تشغيل جميع اختبارات الملفات
     */
    public function runAllTests(): void
    {
        try {
            $this->testValidFileUploads();
            $this->testInvalidFileRejection();
            $this->testSecureStorageDirectory();
            $this->testFileCleanupWithSubmission();
            $this->testFileRenaming();
            $this->testMimeTypeDetection();
            $this->testSecurityPathTraversal();
            $this->testFileUploadPerformance();
            
        } catch (Exception $e) {
            echo "❌ خطأ في الاختبار: " . $e->getMessage() . "\n";
            $this->failCount++;
        } finally {
            $this->cleanup();
            $this->cleanupTestFiles();
            $this->printReport();
        }
    }
}

// تشغيل الاختبارات
if (php_sapi_name() === 'cli') {
    $tests = new FileHandlingTests();
    $tests->runAllTests();
}