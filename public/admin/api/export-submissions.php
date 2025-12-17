<?php

declare(strict_types=1);

// تضمين الإعدادات
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../src/Core/Services/FormService.php';
require_once __DIR__ . '/../../../src/Core/Services/FormFieldService.php';

// بدء الجلسة
session_start();

// التحقق من الصلاحيات
$isAdmin = true;

if (!$isAdmin) {
    http_response_code(403);
    die('غير مسموح بالوصول');
}

// إنشاء الخدمات
$formService = new EmployeeEvaluationSystem\Core\Services\FormService($pdo);
$formFieldService = new EmployeeEvaluationSystem\Core\Services\FormFieldService($pdo);

// تحديد صيغة التصدير
$format = $_GET['format'] ?? 'csv';
if (!in_array($format, ['csv', 'excel'])) {
    http_response_code(400);
    die('صيغة التصدير غير صحيحة');
}

// معالجة الفلاتر
$filters = [
    'form_id' => isset($_GET['form_id']) && $_GET['form_id'] !== '' ? (int)$_GET['form_id'] : null,
    'department_id' => isset($_GET['department_id']) && $_GET['department_id'] !== '' ? (int)$_GET['department_id'] : null,
    'status' => isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null,
    'date_from' => isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : null,
    'date_to' => isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : null,
    'keyword' => isset($_GET['keyword']) && $_GET['keyword'] !== '' ? trim($_GET['keyword']) : null,
];

// بناء استعلام جلب الإجابات مع الفلاتر
$whereClauses = [];
$params = [];

if ($filters['form_id']) {
    $whereClauses[] = 'fs.form_id = :form_id';
    $params['form_id'] = $filters['form_id'];
}

if ($filters['department_id']) {
    $whereClauses[] = 'fs.department_id = :department_id';
    $params['department_id'] = $filters['department_id'];
}

if ($filters['status']) {
    $whereClauses[] = 'fs.status = :status';
    $params['status'] = $filters['status'];
}

if ($filters['date_from']) {
    $whereClauses[] = 'DATE(fs.submitted_at) >= :date_from';
    $params['date_from'] = $filters['date_from'];
}

if ($filters['date_to']) {
    $whereClauses[] = 'DATE(fs.submitted_at) <= :date_to';
    $params['date_to'] = $filters['date_to'];
}

if ($filters['keyword']) {
    $whereClauses[] = '(fs.submitted_by LIKE :keyword OR fs.reference_code LIKE :keyword)';
    $params['keyword'] = '%' . $filters['keyword'] . '%';
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// استعلام جلب البيانات
$sql = "SELECT 
    fs.id,
    fs.form_id,
    fs.submitted_by,
    fs.department_id,
    fs.status,
    fs.submitted_at,
    fs.reference_code,
    fs.ip_address,
    f.title as form_title,
    d.name as department_name
FROM form_submissions fs
LEFT JOIN forms f ON fs.form_id = f.id
LEFT JOIN departments d ON fs.department_id = d.id
$whereSQL
ORDER BY fs.submitted_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$submissions = $stmt->fetchAll();

if (empty($submissions)) {
    http_response_code(404);
    die('لا توجد بيانات للتصدير');
}

// جلب جميع الإجابات للـ submissions
$submissionIds = array_column($submissions, 'id');
$placeholders = implode(',', array_fill(0, count($submissionIds), '?'));

$answersSQL = "SELECT 
    sa.submission_id,
    sa.field_id,
    sa.answer,
    sa.file_name,
    sa.repeat_index,
    ff.label as field_label,
    ff.field_type,
    ff.parent_field_id
FROM submission_answers sa
INNER JOIN form_fields ff ON sa.field_id = ff.id
WHERE sa.submission_id IN ($placeholders)
ORDER BY sa.submission_id, ff.order_index, sa.repeat_index";

$answersStmt = $pdo->prepare($answersSQL);
$answersStmt->execute($submissionIds);
$allAnswers = $answersStmt->fetchAll();

// تنظيم الإجابات حسب submission_id
$answersMap = [];
foreach ($allAnswers as $answer) {
    $submissionId = (int)$answer['submission_id'];
    if (!isset($answersMap[$submissionId])) {
        $answersMap[$submissionId] = [];
    }
    $answersMap[$submissionId][] = $answer;
}

// جلب جميع حقول الاستمارات المختلفة
$formIds = array_unique(array_column($submissions, 'form_id'));
$formFieldsMap = [];

foreach ($formIds as $formId) {
    $fields = $formFieldService->getFieldsForForm((int)$formId, true);
    // تصفية الحقول الفرعية (repeater children)
    $topLevelFields = array_filter($fields, function($f) {
        return $f['parent_field_id'] === null;
    });
    $formFieldsMap[(int)$formId] = $topLevelFields;
}

/**
 * دالة لتحويل الإجابة إلى نص
 */
function formatAnswerForExport($answer, $fieldType) {
    if ($answer['file_name']) {
        return $answer['file_name'];
    }
    
    $value = $answer['answer'];
    
    if ($value === null || $value === '') {
        return '';
    }
    
    // فك JSON للحقول متعددة الاختيارات
    if (in_array($fieldType, ['checkbox', 'select']) && str_starts_with($value, '[')) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return implode(', ', $decoded);
        }
    }
    
    return $value;
}

/**
 * دالة لجمع الإجابات لحقل معين
 */
function collectFieldAnswers($answers, $fieldId, $fieldType) {
    $result = [];
    foreach ($answers as $answer) {
        if ((int)$answer['field_id'] === $fieldId && $answer['parent_field_id'] === null) {
            $formatted = formatAnswerForExport($answer, $fieldType);
            if ($formatted !== '') {
                $result[] = $formatted;
            }
        }
    }
    return implode(' | ', $result);
}

/**
 * دالة لجمع إجابات repeater
 */
function collectRepeaterAnswers($answers, $parentFieldId, $childFields) {
    $repeatGroups = [];
    
    foreach ($answers as $answer) {
        if ((int)$answer['parent_field_id'] === $parentFieldId) {
            $repeatIndex = (int)$answer['repeat_index'];
            if (!isset($repeatGroups[$repeatIndex])) {
                $repeatGroups[$repeatIndex] = [];
            }
            
            $fieldLabel = $answer['field_label'];
            $formatted = formatAnswerForExport($answer, $answer['field_type']);
            $repeatGroups[$repeatIndex][$fieldLabel] = $formatted;
        }
    }
    
    if (empty($repeatGroups)) {
        return '';
    }
    
    $result = [];
    foreach ($repeatGroups as $index => $group) {
        $groupText = "[$index]: ";
        $parts = [];
        foreach ($group as $label => $value) {
            $parts[] = "$label=$value";
        }
        $groupText .= implode(', ', $parts);
        $result[] = $groupText;
    }
    
    return implode(' || ', $result);
}

// تصدير CSV
if ($format === 'csv') {
    $filename = 'submissions_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // إضافة BOM لدعم UTF-8 في Excel
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // رؤوس الأعمدة الأساسية
    $headers = [
        'رقم المرجع',
        'الاستمارة',
        'المرسل',
        'الإدارة',
        'الحالة',
        'تاريخ الإرسال',
        'عنوان IP'
    ];
    
    // إضافة رؤوس الحقول (من أول استمارة كمثال)
    if (!empty($submissions)) {
        $firstFormId = (int)$submissions[0]['form_id'];
        if (isset($formFieldsMap[$firstFormId])) {
            foreach ($formFieldsMap[$firstFormId] as $field) {
                $headers[] = $field['label'];
            }
        }
    }
    
    fputcsv($output, $headers);
    
    // بيانات الصفوف
    foreach ($submissions as $submission) {
        $submissionId = (int)$submission['id'];
        $formId = (int)$submission['form_id'];
        $answers = $answersMap[$submissionId] ?? [];
        
        $row = [
            $submission['reference_code'],
            $submission['form_title'],
            $submission['submitted_by'],
            $submission['department_name'] ?? '',
            match($submission['status']) {
                'pending' => 'قيد الانتظار',
                'completed' => 'مكتملة',
                'archived' => 'مؤرشفة',
                default => $submission['status']
            },
            $submission['submitted_at'],
            $submission['ip_address'] ?? ''
        ];
        
        // إضافة الإجابات
        if (isset($formFieldsMap[$formId])) {
            foreach ($formFieldsMap[$formId] as $field) {
                $fieldId = (int)$field['id'];
                $fieldType = $field['field_type'];
                
                if ($fieldType === 'repeater') {
                    // جمع إجابات repeater
                    $childFields = array_filter($formFieldsMap[$formId], function($f) use ($fieldId) {
                        return (int)$f['parent_field_id'] === $fieldId;
                    });
                    $row[] = collectRepeaterAnswers($answers, $fieldId, $childFields);
                } else {
                    $row[] = collectFieldAnswers($answers, $fieldId, $fieldType);
                }
            }
        }
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// تصدير Excel
if ($format === 'excel') {
    // التحقق من وجود PhpSpreadsheet
    if (!file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
        http_response_code(500);
        die('مكتبة PhpSpreadsheet غير مثبتة. يرجى استخدام CSV بدلاً من ذلك.');
    }
    
    require_once __DIR__ . '/../../../vendor/autoload.php';
    
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Style\Fill;
    use PhpOffice\PhpSpreadsheet\Style\Alignment;
    use PhpOffice\PhpSpreadsheet\Style\Border;
    
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
    
    // إضافة رؤوس الحقول
    if (!empty($submissions)) {
        $firstFormId = (int)$submissions[0]['form_id'];
        if (isset($formFieldsMap[$firstFormId])) {
            foreach ($formFieldsMap[$firstFormId] as $field) {
                $headers[] = $field['label'];
            }
        }
    }
    
    // كتابة الرؤوس
    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col, 1, $header);
        $col++;
    }
    
    // تنسيق الرؤوس
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sheet->getStyle('A1:' . $sheet->getCellByColumnAndRow(count($headers), 1)->getColumn() . '1')->applyFromArray($headerStyle);
    
    // كتابة البيانات
    $row = 2;
    foreach ($submissions as $submission) {
        $submissionId = (int)$submission['id'];
        $formId = (int)$submission['form_id'];
        $answers = $answersMap[$submissionId] ?? [];
        
        $col = 1;
        
        // البيانات الأساسية
        $sheet->setCellValueByColumnAndRow($col++, $row, $submission['reference_code']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $submission['form_title']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $submission['submitted_by']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $submission['department_name'] ?? '');
        $sheet->setCellValueByColumnAndRow($col++, $row, match($submission['status']) {
            'pending' => 'قيد الانتظار',
            'completed' => 'مكتملة',
            'archived' => 'مؤرشفة',
            default => $submission['status']
        });
        $sheet->setCellValueByColumnAndRow($col++, $row, $submission['submitted_at']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $submission['ip_address'] ?? '');
        
        // الإجابات
        if (isset($formFieldsMap[$formId])) {
            foreach ($formFieldsMap[$formId] as $field) {
                $fieldId = (int)$field['id'];
                $fieldType = $field['field_type'];
                
                if ($fieldType === 'repeater') {
                    $childFields = array_filter($formFieldsMap[$formId], function($f) use ($fieldId) {
                        return (int)$f['parent_field_id'] === $fieldId;
                    });
                    $sheet->setCellValueByColumnAndRow($col++, $row, collectRepeaterAnswers($answers, $fieldId, $childFields));
                } else {
                    $sheet->setCellValueByColumnAndRow($col++, $row, collectFieldAnswers($answers, $fieldId, $fieldType));
                }
            }
        }
        
        // تنسيق الصف (alternating colors)
        if ($row % 2 === 0) {
            $sheet->getStyle('A' . $row . ':' . $sheet->getCellByColumnAndRow(count($headers), $row)->getColumn() . $row)
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F2F2F2');
        }
        
        $row++;
    }
    
    // Auto-size الأعمدة
    foreach (range(1, count($headers)) as $colNum) {
        $sheet->getColumnDimensionByColumn($colNum)->setAutoSize(true);
    }
    
    // إضافة borders لجميع الخلايا
    $sheet->getStyle('A1:' . $sheet->getCellByColumnAndRow(count($headers), $row - 1)->getColumn() . ($row - 1))
        ->getBorders()
        ->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);
    
    // إعداد التحميل
    $filename = 'submissions_export_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
