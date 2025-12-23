<?php

declare(strict_types=1);

require_once CONFIG_PATH . '/database.php';
require_once SRC_PATH . '/Core/Services/FormService.php';
require_once SRC_PATH . '/Core/Services/DepartmentService.php';

session_start();

$formService = new BuraqForms\Core\Services\FormService($pdo);
$departmentService = new BuraqForms\Core\Services\DepartmentService($pdo);

$selectedDepartment = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $allForms = $formService->list('active');
    $departments = $departmentService->list(true);

    $filteredForms = array_filter($allForms, function ($form) use ($selectedDepartment, $searchQuery) {
        $matchesDepartment = true;
        $matchesSearch = true;

        if ($selectedDepartment > 0) {
            $matchesDepartment = false;
            foreach ($form['departments'] as $dept) {
                if ((int)$dept['id'] === $selectedDepartment) {
                    $matchesDepartment = true;
                    break;
                }
            }
            if (empty($form['departments'])) {
                $matchesDepartment = true;
            }
        }

        if ($searchQuery !== '') {
            $title = mb_strtolower((string)$form['title']);
            $description = mb_strtolower((string)($form['description'] ?? ''));
            $search = mb_strtolower($searchQuery);
            $matchesSearch = (mb_strpos($title, $search) !== false || mb_strpos($description, $search) !== false);
        }

        return $matchesDepartment && $matchesSearch;
    });
} catch (Exception $e) {
    $error = $e->getMessage();
    $filteredForms = [];
    $departments = [];
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الاستمارات المتاحة</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/forms.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1>
                    <i class="fas fa-clipboard-list"></i>
                    الاستمارات المتاحة
                </h1>
                <div>
                    <a href="../admin/dashboard.php" class="btn btn-light">
                        <i class="fas fa-home"></i>
                        لوحة التحكم
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="container mt-4">
        <?php if (isset($error)) : ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <strong>خطأ:</strong> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section fade-in">
            <form method="GET" action="index.php">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">
                            <i class="fas fa-search"></i>
                            البحث في الاستمارات
                        </label>
                        <input type="text" 
                               name="search" 
                               class="form-control" 
                               placeholder="ابحث بالعنوان أو الوصف..."
                               value="<?= htmlspecialchars($searchQuery) ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">
                            <i class="fas fa-building"></i>
                            تصفية حسب الإدارة
                        </label>
                        <select name="department" class="form-select">
                            <option value="0">جميع الإدارات</option>
                            <?php foreach ($departments as $dept) : ?>
                                <option value="<?= (int)$dept['id'] ?>" 
                                        <?= $selectedDepartment === (int)$dept['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i>
                            تصفية
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Forms List -->
        <div class="row mt-4">
            <?php if (empty($filteredForms)) : ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                        <h4>لا توجد استمارات متاحة</h4>
                        <p>لم يتم العثور على استمارات نشطة في الوقت الحالي.</p>
                    </div>
                </div>
            <?php else : ?>
                <?php foreach ($filteredForms as $form) : ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="form-card fade-in">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-file-alt"></i>
                                    <?= htmlspecialchars($form['title']) ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($form['description'])) : ?>
                                    <p class="text-muted mb-3">
                                        <?= nl2br(htmlspecialchars($form['description'])) ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i>
                                        تم الإنشاء: <?= date('Y-m-d', strtotime($form['created_at'])) ?>
                                    </small>
                                </div>

                                <?php if (!empty($form['departments'])) : ?>
                                    <div class="mb-3">
                                        <small class="text-muted d-block mb-2">
                                            <i class="fas fa-building"></i>
                                            الإدارات:
                                        </small>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($form['departments'] as $dept) : ?>
                                                <span class="badge bg-secondary">
                                                    <?= htmlspecialchars($dept['name']) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <span class="badge badge-active">
                                        <i class="fas fa-check-circle"></i>
                                        نشط
                                    </span>
                                    <a href="fill.php?slug=<?= urlencode($form['slug']) ?>" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-pen"></i>
                                        ملء الاستمارة
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Statistics -->
        <?php if (!empty($filteredForms)) : ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="alert alert-light text-center">
                        <i class="fas fa-info-circle"></i>
                        عدد الاستمارات المتاحة: <strong><?= count($filteredForms) ?></strong>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <footer class="text-center mt-5 mb-4">
        <p class="text-muted">
            &copy; <?= date('Y') ?> نظام تقييم الموظفين. جميع الحقوق محفوظة.
        </p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
