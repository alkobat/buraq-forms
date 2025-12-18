<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use BuraqForms\Core\Cache\FileCache;
use BuraqForms\Core\Database;
use BuraqForms\Core\Logger;
use BuraqForms\Core\Services\FormFieldService;
use BuraqForms\Core\Services\FormService;
use BuraqForms\Core\Services\FormSubmissionService;
use BuraqForms\Core\Services\SystemSettingsService;

$logger = new Logger();
$cache = new FileCache(__DIR__ . '/cache');

$pdo = Database::getConnection();

$formService = new FormService($pdo, $logger, $cache);
$fieldService = new FormFieldService($pdo, $logger);
$settings = new SystemSettingsService($pdo, $cache);
$submissionService = new FormSubmissionService($pdo, $formService, $fieldService, null, $settings, $logger, $cache);

try {
    echo "Creating a form...\n";

    $form = $formService->create([
        'title' => 'نموذج اختبار الخدمات',
        'description' => 'تم إنشاؤه من test_form_services.php',
        'created_by' => 1,
        'status' => 'active',
        'allow_multiple_submissions' => true,
        'show_department_field' => true,
    ], [1, 2]);

    echo "Form created: ID={$form['id']} slug={$form['slug']}\n";

    echo "Adding fields...\n";

    $fieldService->addField((int) $form['id'], [
        'field_type' => 'text',
        'label' => 'الاسم',
        'field_key' => 'name',
        'is_required' => true,
        'order_index' => 0,
        'validation_rules' => ['min_length' => 2, 'max_length' => 100],
    ]);

    $fieldService->addField((int) $form['id'], [
        'field_type' => 'select',
        'label' => 'القسم',
        'field_key' => 'department',
        'is_required' => true,
        'source_type' => 'departments',
        'order_index' => 1,
    ]);

    $repeater = $fieldService->addField((int) $form['id'], [
        'field_type' => 'repeater',
        'label' => 'الإنجازات',
        'field_key' => 'achievements',
        'is_required' => false,
        'order_index' => 2,
    ]);

    $fieldService->addField((int) $form['id'], [
        'field_type' => 'text',
        'label' => 'عنوان الإنجاز',
        'field_key' => 'title',
        'is_required' => true,
        'parent_field_id' => (int) $repeater['id'],
        'order_index' => 0,
    ]);

    $fileChild = $fieldService->addField((int) $form['id'], [
        'field_type' => 'file',
        'label' => 'مرفق',
        'field_key' => 'attachment',
        'is_required' => false,
        'parent_field_id' => (int) $repeater['id'],
        'order_index' => 1,
    ]);

    echo "Submitting...\n";

    $temp = tempnam(sys_get_temp_dir(), 'ees_');
    file_put_contents($temp, "Hello from test script\n");

    $submission = $submissionService->submit((int) $form['id'], [
        'submitted_by' => 'tester@example.com',
        'department_id' => 1,
        'ip_address' => '127.0.0.1',
    ], [
        'name' => 'Tester',
        'department' => '1',
        'achievements' => [
            ['title' => 'إنجاز 1'],
        ],
    ], [
        'achievements.0.attachment' => [
            'name' => 'example.txt',
            'tmp_name' => $temp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($temp),
            'type' => 'text/plain',
        ],
    ]);

    echo "Submission created: ID={$submission['id']} reference={$submission['reference_code']}\n";
    echo "Answers rows: " . count($submission['answers']) . "\n";

    echo "Done.\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
