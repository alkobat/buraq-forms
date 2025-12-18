<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use EmployeeEvaluationSystem\Core\Database;
use EmployeeEvaluationSystem\Core\Services\CommentService;
use EmployeeEvaluationSystem\Core\Services\PermissionService;
use EmployeeEvaluationSystem\Core\Services\AuditService;

// إعداد الجلسة والتحقق من الصلاحية
session_start();

$database = Database::getConnection();
$commentService = new CommentService($database);
$permissionService = new PermissionService($database);
$auditService = new AuditService($database);

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$adminId = $_SESSION['admin_id'] ?? 0;

// التحقق من الصلاحية
if (!$permissionService->hasPermission($adminId, 'submissions.view')) {
    header('HTTP/1.0 403 Forbidden');
    exit('ليس لديك صلاحية للوصول لهذه الصفحة');
}

$message = '';
$messageType = '';

// معالجة العمليات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_comment':
                $submissionId = (int) ($_POST['submission_id'] ?? 0);
                $comment = $_POST['comment'] ?? '';
                $commentType = $_POST['comment_type'] ?? 'general';
                $isInternal = isset($_POST['is_internal']);
                $parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
                
                if ($submissionId > 0 && !empty($comment)) {
                    $commentId = $commentService->addComment($submissionId, $adminId, $comment, $commentType, $isInternal, $parentId);
                    if ($commentId) {
                        $auditService->logCreate($adminId, 'comment', $commentId, [
                            'submission_id' => $submissionId,
                            'comment_type' => $commentType,
                            'is_internal' => $isInternal,
                            'parent_id' => $parentId
                        ]);
                        $message = 'تم إضافة التعليق بنجاح';
                        $messageType = 'success';
                    } else {
                        $message = 'فشل في إضافة التعليق';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'update_comment':
                $commentId = (int) ($_POST['comment_id'] ?? 0);
                $newComment = $_POST['new_comment'] ?? '';
                
                if ($commentId > 0 && !empty($newComment)) {
                    $result = $commentService->updateComment($commentId, $adminId, $newComment);
                    if ($result) {
                        $auditService->logUpdate($adminId, 'comment', $commentId, [], ['new_comment' => $newComment]);
                        $message = 'تم تحديث التعليق بنجاح';
                        $messageType = 'success';
                    } else {
                        $message = 'فشل في تحديث التعليق';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'delete_comment':
                $commentId = (int) ($_POST['comment_id'] ?? 0);
                
                if ($commentId > 0) {
                    $result = $commentService->deleteComment($commentId, $adminId);
                    if ($result) {
                        $auditService->logDelete($adminId, 'comment', $commentId, []);
                        $message = 'تم حذف التعليق بنجاح';
                        $messageType = 'success';
                    } else {
                        $message = 'فشل في حذف التعليق';
                        $messageType = 'error';
                    }
                }
                break;
        }
    } catch (Exception $e) {
        $message = 'حدث خطأ: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// جلب البيانات
$selectedSubmissionId = (int) ($_GET['submission_id'] ?? 0);
$submissionComments = [];
$submissionInfo = null;

if ($selectedSubmissionId > 0) {
    // جلب معلومات الإجابة
    $sql = "SELECT fs.*, f.title as form_title, d.name as department_name
            FROM form_submissions fs
            JOIN forms f ON fs.form_id = f.id
            LEFT JOIN departments d ON fs.department_id = d.id
            WHERE fs.id = ?";
    
    $stmt = $database->prepare($sql);
    $stmt->execute([$selectedSubmissionId]);
    $submissionInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($submissionInfo) {
        $submissionComments = $commentService->getSubmissionComments($selectedSubmissionId);
    }
}

// جلب قائمة الإجابات للاختيار
$submissions = [];
$sql = "SELECT fs.id, fs.reference_code, fs.submitter_name, f.title as form_title, d.name as department_name, fs.submitted_at
        FROM form_submissions fs
        JOIN forms f ON fs.form_id = f.id
        LEFT JOIN departments d ON fs.department_id = d.id
        ORDER BY fs.submitted_at DESC
        LIMIT 50";
        
$stmt = $database->prepare($sql);
$stmt->execute();
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات التعليقات
$commentStats = $commentService->getCommentStats();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة التعليقات والتعاون - نظام تقييم الموظفين</title>
    
    <!-- Bootstrap 5 RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Cairo Font -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            margin: 20px;
            padding: 0;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            border-radius: 15px 0 0 15px;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 25px;
            margin: 5px 15px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .content {
            padding: 30px;
        }
        
        .submission-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .submission-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .submission-card.selected {
            border: 2px solid #667eea;
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
        }
        
        .comment-thread {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .comment {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        
        .comment:last-child {
            border-bottom: none;
        }
        
        .comment.internal {
            background: linear-gradient(135deg, #fff3e0 0%, #fce4ec 100%);
            border-left: 4px solid #ff9800;
            padding-left: 20px;
        }
        
        .comment-type-badge {
            font-size: 0.7em;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            margin-bottom: 10px;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .reply-form {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .dark-mode {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
        }
        
        .dark-mode .submission-card,
        .dark-mode .comment-thread,
        .dark-mode .stat-card {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .dark-mode .form-control,
        .dark-mode .form-select {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="main-container row">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="sidebar">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-comments"></i>
                        التعليقات والتعاون
                    </h4>
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="#submissions" data-bs-toggle="tab">
                            <i class="fas fa-list"></i> الإجابات
                        </a>
                        <a class="nav-link" href="#stats" data-bs-toggle="tab">
                            <i class="fas fa-chart-bar"></i> الإحصائيات
                        </a>
                        <a class="nav-link" href="#search" data-bs-toggle="tab">
                            <i class="fas fa-search"></i> البحث
                        </a>
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-arrow-right"></i> العودة للوحة التحكم
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Content -->
            <div class="col-lg-9">
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?= $messageType === 'error' ? 'exclamation-triangle' : 'check-circle' ?>"></i>
                            <?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="tab-content">
                        <!-- قائمة الإجابات -->
                        <div class="tab-pane fade show active" id="submissions">
                            <div class="row">
                                <!-- قائمة الإجابات -->
                                <div class="col-md-6">
                                    <h3 class="mb-4">
                                        <i class="fas fa-list"></i>
                                        الإجابات
                                    </h3>
                                    
                                    <?php if (empty($submissions)): ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">لا توجد إجابات</h5>
                                        </div>
                                    <?php else: ?>
                                        <div class="list-group">
                                            <?php foreach ($submissions as $submission): ?>
                                                <a href="?submission_id=<?= $submission['id'] ?>" 
                                                   class="list-group-item list-group-item-action submission-card <?= $selectedSubmissionId === $submission['id'] ? 'selected' : '' ?>">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-1"><?= htmlspecialchars($submission['reference_code']) ?></h6>
                                                        <small><?= date('Y-m-d H:i', strtotime($submission['submitted_at'])) ?></small>
                                                    </div>
                                                    <p class="mb-1"><?= htmlspecialchars($submission['form_title']) ?></p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user"></i> <?= htmlspecialchars($submission['submitter_name']) ?>
                                                        <?php if ($submission['department_name']): ?>
                                                            <i class="fas fa-building ms-2"></i> <?= htmlspecialchars($submission['department_name']) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- تفاصيل الإجابة والتعليقات -->
                                <div class="col-md-6">
                                    <?php if ($submissionInfo): ?>
                                        <h3 class="mb-4">
                                            <i class="fas fa-comments"></i>
                                            التعليقات على الإجابة
                                        </h3>
                                        
                                        <!-- معلومات الإجابة -->
                                        <div class="card mb-4">
                                            <div class="card-body">
                                                <h5 class="card-title"><?= htmlspecialchars($submissionInfo['reference_code']) ?></h5>
                                                <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($submissionInfo['form_title']) ?></h6>
                                                <p class="card-text">
                                                    <i class="fas fa-user"></i> <?= htmlspecialchars($submissionInfo['submitter_name']) ?><br>
                                                    <i class="fas fa-envelope"></i> <?= htmlspecialchars($submissionInfo['submitter_email']) ?><br>
                                                    <i class="fas fa-building"></i> <?= htmlspecialchars($submissionInfo['department_name'] ?? 'غير محدد') ?><br>
                                                    <i class="fas fa-calendar"></i> <?= date('Y-m-d H:i', strtotime($submissionInfo['submitted_at'])) ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <!-- إضافة تعليق جديد -->
                                        <div class="comment-thread mb-4">
                                            <h5>إضافة تعليق جديد</h5>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="add_comment">
                                                <input type="hidden" name="submission_id" value="<?= $submissionInfo['id'] ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">نوع التعليق</label>
                                                    <select class="form-select" name="comment_type">
                                                        <option value="general">عام</option>
                                                        <option value="note">ملاحظة</option>
                                                        <option value="flag">تحديد</option>
                                                        <option value="internal">داخلي</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">التعليق</label>
                                                    <textarea class="form-control" name="comment" rows="4" required></textarea>
                                                </div>
                                                
                                                <div class="mb-3 form-check">
                                                    <input class="form-check-input" type="checkbox" name="is_internal" id="is_internal">
                                                    <label class="form-check-label" for="is_internal">
                                                        تعليق داخلي (مرئي للمشرفين فقط)
                                                    </label>
                                                </div>
                                                
                                                <button type="submit" class="btn btn-gradient">
                                                    <i class="fas fa-plus"></i> إضافة التعليق
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <!-- قائمة التعليقات -->
                                        <div class="comment-thread">
                                            <h5>التعليقات (<?= count($submissionComments) ?>)</h5>
                                            
                                            <?php if (empty($submissionComments)): ?>
                                                <div class="text-center py-4">
                                                    <i class="fas fa-comment-slash fa-2x text-muted mb-2"></i>
                                                    <p class="text-muted">لا توجد تعليقات بعد</p>
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($submissionComments as $comment): ?>
                                                    <div class="comment <?= $comment['is_internal'] ? 'internal' : '' ?>">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div class="flex-grow-1">
                                                                <div class="d-flex align-items-center mb-2">
                                                                    <strong><?= htmlspecialchars($comment['admin_name']) ?></strong>
                                                                    <span class="badge bg-<?= $comment['comment_type'] === 'internal' ? 'warning' : 'primary' ?> comment-type-badge ms-2">
                                                                        <?= htmlspecialchars($comment['comment_type']) ?>
                                                                    </span>
                                                                    <?php if ($comment['is_internal']): ?>
                                                                        <span class="badge bg-warning ms-1">
                                                                            <i class="fas fa-lock"></i> داخلي
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <p class="mb-2"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-clock"></i>
                                                                    <?= date('Y-m-d H:i', strtotime($comment['created_at'])) ?>
                                                                </small>
                                                            </div>
                                                            <div class="ms-3">
                                                                <?php if ($comment['admin_id'] == $adminId): ?>
                                                                    <div class="btn-group">
                                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                                onclick="editComment(<?= $comment['id'] ?>, '<?= addslashes($comment['comment']) ?>')">
                                                                            <i class="fas fa-edit"></i>
                                                                        </button>
                                                                        <form method="POST" style="display: inline;">
                                                                            <input type="hidden" name="action" value="delete_comment">
                                                                            <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                                                            <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                                                    onclick="return confirm('هل أنت متأكد من حذف هذا التعليق؟')">
                                                                                <i class="fas fa-trash"></i>
                                                                            </button>
                                                                        </form>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- الردود -->
                                                        <?php if (!empty($comment['replies'])): ?>
                                                            <div class="ms-4 mt-3">
                                                                <?php foreach ($comment['replies'] as $reply): ?>
                                                                    <div class="comment <?= $reply['is_internal'] ? 'internal' : '' ?> border-start border-2 border-light ps-3">
                                                                        <div class="d-flex justify-content-between align-items-start">
                                                                            <div class="flex-grow-1">
                                                                                <div class="d-flex align-items-center mb-1">
                                                                                    <strong><?= htmlspecialchars($reply['admin_name']) ?></strong>
                                                                                    <small class="text-muted ms-2">رد</small>
                                                                                </div>
                                                                                <p class="mb-1"><?= nl2br(htmlspecialchars($reply['comment'])) ?></p>
                                                                                <small class="text-muted">
                                                                                    <?= date('Y-m-d H:i', strtotime($reply['created_at'])) ?>
                                                                                </small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-hand-pointer fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">اختر إجابة لعرض التعليقات</h5>
                                            <p class="text-muted">انقر على إحدى الإجابات من القائمة لعرض أو إضافة التعليقات</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- الإحصائيات -->
                        <div class="tab-pane fade" id="stats">
                            <h3 class="mb-4">
                                <i class="fas fa-chart-bar"></i>
                                إحصائيات التعليقات
                            </h3>
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-primary">
                                            <i class="fas fa-comments"></i>
                                        </div>
                                        <h4><?= $commentStats['total'] ?? 0 ?></h4>
                                        <p class="text-muted mb-0">إجمالي التعليقات</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-warning">
                                            <i class="fas fa-eye-slash"></i>
                                        </div>
                                        <h4><?= $commentStats['internal'] ?? 0 ?></h4>
                                        <p class="text-muted mb-0">تعليقات داخلية</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="stat-card">
                                        <h6>التعليقات حسب النوع</h6>
                                        <?php if (!empty($commentStats['by_type'])): ?>
                                            <?php foreach ($commentStats['by_type'] as $type): ?>
                                                <div class="d-flex justify-content-between">
                                                    <span><?= htmlspecialchars($type['comment_type']) ?></span>
                                                    <span class="badge bg-secondary"><?= $type['count'] ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($commentStats['top_commented'])): ?>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="stat-card">
                                            <h5>الإجابات الأكثر تعليقاً</h5>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>رقم المرجع</th>
                                                            <th>عنوان الاستمارة</th>
                                                            <th>عدد التعليقات</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($commentStats['top_commented'] as $item): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($item['reference_code']) ?></td>
                                                                <td><?= htmlspecialchars($item['title']) ?></td>
                                                                <td><span class="badge bg-primary"><?= $item['comment_count'] ?></span></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- البحث -->
                        <div class="tab-pane fade" id="search">
                            <h3 class="mb-4">
                                <i class="fas fa-search"></i>
                                البحث في التعليقات
                            </h3>
                            
                            <form method="GET" class="mb-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" name="search_query" 
                                               placeholder="ابحث في التعليقات..." value="<?= htmlspecialchars($_GET['search_query'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select" name="comment_type">
                                            <option value="">جميع الأنواع</option>
                                            <option value="general">عام</option>
                                            <option value="note">ملاحظة</option>
                                            <option value="flag">تحديد</option>
                                            <option value="internal">داخلي</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-gradient">
                                            <i class="fas fa-search"></i> بحث
                                        </button>
                                    </div>
                                </div>
                            </form>
                            
                            <?php if (isset($_GET['search_query'])): ?>
                                <?php
                                $searchResults = $commentService->searchComments(
                                    $_GET['search_query'],
                                    null,
                                    $_GET['comment_type'] ?? null
                                );
                                ?>
                                
                                <h5>نتائج البحث (<?= count($searchResults) ?>)</h5>
                                
                                <?php if (empty($searchResults)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        لم يتم العثور على نتائج مطابقة
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>التعليق</th>
                                                    <th>المعلق</th>
                                                    <th>النوع</th>
                                                    <th>الإجابة</th>
                                                    <th>التاريخ</th>
                                                    <th>الإجراءات</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($searchResults as $result): ?>
                                                    <tr>
                                                        <td>
                                                            <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                                                <?= htmlspecialchars(substr($result['comment'], 0, 100)) ?>...
                                                            </div>
                                                        </td>
                                                        <td><?= htmlspecialchars($result['admin_name']) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $result['comment_type'] === 'internal' ? 'warning' : 'primary' ?>">
                                                                <?= htmlspecialchars($result['comment_type']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?= htmlspecialchars($result['reference_code']) ?>
                                                            <br><small class="text-muted"><?= htmlspecialchars($result['title']) ?></small>
                                                        </td>
                                                        <td>
                                                            <small><?= date('Y-m-d H:i', strtotime($result['created_at'])) ?></small>
                                                        </td>
                                                        <td>
                                                            <a href="?submission_id=<?= $result['submission_id'] ?>" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-eye"></i> عرض
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Modal for editing comments -->
    <div class="modal fade" id="editCommentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تعديل التعليق</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_comment">
                        <input type="hidden" name="comment_id" id="edit_comment_id">
                        <div class="mb-3">
                            <label class="form-label">التعليق</label>
                            <textarea class="form-control" name="new_comment" id="edit_comment_text" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">حفظ التعديل</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // تشغيل/إيقاف الوضع الليلي
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
        }
        
        // تحميل تفضيل الوضع الليلي
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
        }
        
        // تعديل تعليق
        function editComment(commentId, commentText) {
            document.getElementById('edit_comment_id').value = commentId;
            document.getElementById('edit_comment_text').value = commentText;
            
            var modal = new bootstrap.Modal(document.getElementById('editCommentModal'));
            modal.show();
        }
        
        // تحديد الإجابة عند النقر عليها
        document.addEventListener('DOMContentLoaded', function() {
            const submissionCards = document.querySelectorAll('.submission-card');
            submissionCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    // إزالة التحديد من الكل
                    submissionCards.forEach(c => c.classList.remove('selected'));
                    // تحديد العنصر الحالي
                    this.classList.add('selected');
                });
            });
        });
    </script>
</body>
</html>