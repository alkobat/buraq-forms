<?php

declare(strict_types=1);

// تضمين الإعدادات
require_once __DIR__ . '/../../config/database.php';

// بدء الجلسة
session_start();

// التحقق من الصلاحيات
$isAdmin = true;

if (!$isAdmin) {
    http_response_code(403);
    die('غير مسموح بالوصول');
}

// جلب معرف الإجابة
$answerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($answerId <= 0) {
    http_response_code(400);
    die('معرف غير صحيح');
}

try {
    // جلب بيانات الملف من قاعدة البيانات
    $stmt = $pdo->prepare('
        SELECT 
            sa.file_path,
            sa.file_name,
            sa.file_size,
            fs.id as submission_id
        FROM submission_answers sa
        INNER JOIN form_submissions fs ON sa.submission_id = fs.id
        WHERE sa.id = :id AND sa.file_path IS NOT NULL
    ');
    $stmt->execute(['id' => $answerId]);
    $fileData = $stmt->fetch();
    
    if (!$fileData) {
        http_response_code(404);
        die('الملف غير موجود');
    }
    
    // بناء المسار الكامل للملف
    $filePath = __DIR__ . '/../../' . $fileData['file_path'];
    
    // التحقق من وجود الملف
    if (!file_exists($filePath)) {
        http_response_code(404);
        die('الملف غير موجود على الخادم');
    }
    
    // التحقق من أن المسار آمن (لا يحتوي على .. أو محاولات للوصول لملفات خارج المجلد المسموح)
    $realPath = realpath($filePath);
    $allowedBasePath = realpath(__DIR__ . '/../../storage/forms');
    
    if (!$realPath || !str_starts_with($realPath, $allowedBasePath)) {
        http_response_code(403);
        die('غير مسموح بالوصول لهذا الملف');
    }
    
    // تسجيل عملية التحميل
    try {
        $logStmt = $pdo->prepare('
            INSERT INTO file_download_logs 
            (answer_id, submission_id, downloaded_by, downloaded_at, ip_address) 
            VALUES (:answer_id, :submission_id, :downloaded_by, NOW(), :ip_address)
        ');
        $logStmt->execute([
            'answer_id' => $answerId,
            'submission_id' => $fileData['submission_id'],
            'downloaded_by' => 'admin', // يمكن استبداله بمعرف المستخدم الفعلي
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (PDOException $e) {
        // إذا فشل التسجيل، نتجاهل الخطأ ونكمل التحميل
        // يمكن إضافة لوجينج هنا إذا لزم الأمر
    }
    
    // تحديد نوع MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    
    // إعداد headers للتحميل
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $fileData['file_name'] . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // مسح أي output buffer موجود
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // قراءة وإرسال الملف
    $handle = fopen($filePath, 'rb');
    if ($handle) {
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
    } else {
        http_response_code(500);
        die('فشل في قراءة الملف');
    }
    
    exit;
    
} catch (PDOException $e) {
    http_response_code(500);
    die('خطأ في قاعدة البيانات');
} catch (Exception $e) {
    http_response_code(500);
    die('خطأ في النظام');
}
