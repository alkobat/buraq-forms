<?php

declare(strict_types=1);

// تضمين الإعدادات
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../src/Core/Services/FormService.php';
require_once __DIR__ . '/../../../src/Core/Services/FormFieldService.php';

// التحقق من وجود PhpSpreadsheet لتصدير Excel
$hasPhpSpreadsheet = file_exists(__DIR__ . '/../../../vendor/autoload.php');

// use statements (يجب أن تكون في بداية الملف)
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

if ($hasPhpSpreadsheet) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
}

// بدء الجلسة
session_start();

// التحقق من الصلاحيات
$isAdmin = true;

if (!$isAdmin) {
    http_response_code(403);
    die('غير مسموح بالوصول');
}

// إنشاء الخدمات
$formService = new BuraqForms\Core\Services\FormService($pdo);
$formFieldService = new BuraqForms\Core\Services\FormFieldService($pdo);

// تحديد صيغة التصدير
$format = $_GET['format'] ?? 'csv';

// دالة للتحقق من الصلاحيات
function ensurePermission(string $permission, string $fallback = ''): void {
    // TODO: تطبيق نظام الصلاحيات الحقيقي
    // هذا مؤقت للاختبار فقط
}

// التحقق من الصلاحية
ensurePermission('submissions.view', 'submissions.export');

// التحقق من وجود معرف الاستمارة
$formId = (int)($_GET['form_id'] ?? 0);
if (!$formId) {
    http_response_code(400);
    die('معرف الاستمارية مطلوب');
}

// الحصول على الاستمارة
$form = $formService->getById($formId);
if (!$form) {
    http_response_code(404);
    die('الاستمارية غير موجودة');
}

// الحصول على الحقول
$fields = $formFieldService->getByFormId($formId);

// تصدير CSV
if ($format === 'csv') {
    $filename = 'submissions_' . $formId . '_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    $output = fopen('php://output', 'w');
    
    // إضافة BOM لحل مشكلة Unicode في Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // رؤوس الأعمدة
    $headers = ['رقم المرجع', 'الاستمارة', 'المرسل', 'الإدارة', 'الحالة', 'تاريخ الإرسال', 'عنوان IP'];
    foreach ($fields as $field) {
        $headers[] = $field['field_label'];
    }
    fputcsv($output, $headers);
    
    // البيانات
    $submissions = $formService->getSubmissions($formId);
    
    foreach ($submissions as $submission) {
        $row = [
            $submission['id'],
            $form['title'],
            $submission['submitter_name'] ?? 'غير محدد',
            $submission['department_name'] ?? 'غير محدد',
            $submission['status'],
            $submission['created_at'],
            $submission['ip_address']
        ];
        
        // إضافة قيم الحقول المخصصة
        $submissionData = json_decode($submission['submission_data'], true) ?: [];
        foreach ($fields as $field) {
            $row[] = $submissionData[$field['field_name']] ?? '';
        }
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// تصدير Excel
if ($format === 'excel') {
    // التحقق من وجود PhpSpreadsheet
    if (!$hasPhpSpreadsheet) {
        http_response_code(500);
        die('مكتبة PhpSpreadsheet غير مثبتة. يرجى استخدام CSV بدلاً من ذلك.');
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('الإجابات');

    // تعيين RTL
    $sheet->setRightToLeft(true);

    // رؤوس الأعمدة
    $headers = [
        'رقم المرجع',
        'الاستمارة',
        'المرسل',
        'الإدارة',
        'الحالة',
        'تاريخ الإرسال',
        'عنوان IP'
    ];

    foreach ($fields as $field) {
        $headers[] = $field['field_label'];
    }

    // إضافة الرؤوس
    foreach ($headers as $index => $header) {
        $col = chr(65 + $index); // A, B, C...
        $sheet->setCellValue($col . '1', $header);
        
        // تنسيق الرأس
        $sheet->getStyle($col . '1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4472C4');
        
        $sheet->getStyle($col . '1')->getFont()
            ->setBold(true)
            ->getColor()->setRGB('FFFFFF');
        
        $sheet->getStyle($col . '1')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // البيانات
    $submissions = $formService->getSubmissions($formId);
    $row = 2;

    foreach ($submissions as $submission) {
        $col = 0;
        
        // البيانات الأساسية
        $sheet->setCellValueByColumnAndRow($col++, $row, $submission['id']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $form['title']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $submission['submitter_name'] ?? 'غير محدد');
        $sheet->setCellValueByColumnAndRow($col++, $row, $submission['department_name'] ?? 'غير محدد');
        $sheet->setCellValueByColumnAndRow($col++, $row, $submission['status']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $submission['created_at']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $submission['ip_address']);
        
        // قيم الحقول المخصصة
        $submissionData = json_decode($submission['submission_data'], true) ?: [];
        foreach ($fields as $field) {
            $sheet->setCellValueByColumnAndRow($col++, $row, $submissionData[$field['field_name']] ?? '');
        }
        
        $row++;
    }

    // تنسيق البيانات
    $dataRange = 'A1:' . chr(65 + count($headers) - 1) . ($row - 1);
    $sheet->getStyle($dataRange)->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);

    // حفظ الملف
    $filename = 'submissions_' . $formId . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// تنسيق غير مدعوم
http_response_code(400);
die('صيغة التصدير غير مدعومة. استخدم "csv" أو "excel"');