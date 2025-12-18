<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use EmployeeEvaluationSystem\Core\Database;
use EmployeeEvaluationSystem\Core\Services\NotificationService;
use EmployeeEvaluationSystem\Core\Services\PermissionService;
use EmployeeEvaluationSystem\Core\Services\AuditService;

// إعداد الجلسة والتحقق من الصلاحية
session_start();

$database = Database::getConnection();
$notificationService = new NotificationService($database);
$permissionService = new PermissionService($database);
$auditService = new AuditService($database);

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$adminId = $_SESSION['admin_id'] ?? 0;

// التحقق من الصلاحية
if (!$permissionService->hasPermission($adminId, 'notifications.manage')) {
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
            case 'mark_as_read':
                $notificationId = (int) ($_POST['notification_id'] ?? 0);
                if ($notificationId > 0) {
                    $result = $notificationService->markAsRead($notificationId);
                    if ($result) {
                        $auditService->logActivity($adminId, 'mark_notification_read', 'notification', $notificationId);
                        $message = 'تم تحديد الإشعار كمقروء';
                        $messageType = 'success';
                    }
                }
                break;
                
            case 'send_notification':
                $notificationData = [
                    'type' => $_POST['notification_type'] ?? 'system',
                    'title' => $_POST['title'] ?? '',
                    'message' => $_POST['message'] ?? '',
                    'recipient_id' => $_POST['recipient_id'] ?? null,
                    'recipient_type' => $_POST['recipient_type'] ?? 'admin',
                    'priority' => $_POST['priority'] ?? 'normal'
                ];
                
                $notificationId = $notificationService->createNotification($notificationData);
                if ($notificationId) {
                    $auditService->logCreate($adminId, 'notification', $notificationId, $notificationData);
                    
                    // إرسال الإشعار فوراً
                    $notificationService->sendNotification($notificationId);
                    
                    $message = 'تم إرسال الإشعار بنجاح';
                    $messageType = 'success';
                } else {
                    $message = 'فشل في إرسال الإشعار';
                    $messageType = 'error';
                }
                break;
        }
    } catch (Exception $e) {
        $message = 'حدث خطأ: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// جلب الإشعارات
$unreadNotifications = $notificationService->getUnreadNotifications($adminId, 50);
$allNotifications = [];

// جلب جميع الإشعارات (للمشرفين ذوي الصلاحية العالية)
if ($permissionService->hasPermission($adminId, 'system.settings')) {
    $sql = "SELECT n.*, a.name as admin_name 
            FROM notifications n
            LEFT JOIN admins a ON n.recipient_id = a.id AND n.recipient_type = 'admin'
            ORDER BY n.created_at DESC 
            LIMIT 100";
    
    $stmt = $database->prepare($sql);
    $stmt->execute();
    $allNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// جلب جميع المشرفين للإرسال
$admins = [];
$sql = "SELECT id, name, email FROM admins WHERE status = 'active' ORDER BY name";
$stmt = $database->prepare($sql);
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات الإشعارات
$stats = [
    'unread_count' => count($unreadNotifications),
    'total_count' => count($allNotifications),
    'pending_count' => 0,
    'sent_count' => 0,
    'failed_count' => 0
];

foreach ($allNotifications as $notification) {
    $stats[$notification['status'] . '_count']++;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الإشعارات - نظام تقييم الموظفين</title>
    
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
        
        .notification-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border-left: 4px solid;
            transition: transform 0.3s ease;
        }
        
        .notification-card:hover {
            transform: translateY(-2px);
        }
        
        .notification-card.unread {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border-left-color: #2196f3;
        }
        
        .notification-card.priority-urgent {
            border-left-color: #f44336;
        }
        
        .notification-card.priority-high {
            border-left-color: #ff9800;
        }
        
        .notification-card.priority-normal {
            border-left-color: #4caf50;
        }
        
        .notification-card.priority-low {
            border-left-color: #9e9e9e;
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
        
        .priority-badge {
            font-size: 0.8em;
        }
        
        .notification-form {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .dark-mode {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
        }
        
        .dark-mode .notification-card,
        .dark-mode .stat-card,
        .dark-mode .notification-form {
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
                        <i class="fas fa-bell"></i>
                        إدارة الإشعارات
                    </h4>
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="#unread" data-bs-toggle="tab">
                            <i class="fas fa-envelope"></i>
                            الإشعارات غير المقروءة
                            <?php if ($stats['unread_count'] > 0): ?>
                                <span class="badge bg-danger ms-2"><?= $stats['unread_count'] ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link" href="#all" data-bs-toggle="tab">
                            <i class="fas fa-list"></i> جميع الإشعارات
                        </a>
                        <a class="nav-link" href="#send" data-bs-toggle="tab">
                            <i class="fas fa-paper-plane"></i> إرسال إشعار
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
                    
                    <!-- إحصائيات سريعة -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon bg-primary">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <h4><?= $stats['total_count'] ?></h4>
                                <p class="text-muted mb-0">إجمالي الإشعارات</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon bg-danger">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <h4><?= $stats['unread_count'] ?></h4>
                                <p class="text-muted mb-0">غير مقروءة</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon bg-success">
                                    <i class="fas fa-check"></i>
                                </div>
                                <h4><?= $stats['sent_count'] ?></h4>
                                <p class="text-muted mb-0">مرسلة</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon bg-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <h4><?= $stats['failed_count'] ?></h4>
                                <p class="text-muted mb-0">فاشلة</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-content">
                        <!-- الإشعارات غير المقروءة -->
                        <div class="tab-pane fade show active" id="unread">
                            <h3 class="mb-4">
                                <i class="fas fa-envelope"></i>
                                الإشعارات غير المقروءة
                            </h3>
                            
                            <?php if (empty($unreadNotifications)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                    <h5 class="text-muted">لا توجد إشعارات غير مقروءة</h5>
                                    <p class="text-muted">جميع إشعاراتك مقروءة</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($unreadNotifications as $notification): ?>
                                    <div class="notification-card unread priority-<?= $notification['priority'] ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-2">
                                                    <h6 class="mb-0 me-2"><?= htmlspecialchars($notification['title']) ?></h6>
                                                    <span class="badge bg-<?= $notification['priority'] === 'urgent' ? 'danger' : ($notification['priority'] === 'high' ? 'warning' : 'primary') ?> priority-badge">
                                                        <?= $notification['priority'] ?>
                                                    </span>
                                                </div>
                                                <p class="mb-2"><?= nl2br(htmlspecialchars($notification['message'])) ?></p>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock"></i>
                                                    <?= date('Y-m-d H:i', strtotime($notification['created_at'])) ?>
                                                </small>
                                            </div>
                                            <div class="ms-3">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="mark_as_read">
                                                    <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- جميع الإشعارات -->
                        <div class="tab-pane fade" id="all">
                            <h3 class="mb-4">
                                <i class="fas fa-list"></i>
                                جميع الإشعارات
                            </h3>
                            
                            <?php if (empty($allNotifications)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">لا توجد إشعارات</h5>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>العنوان</th>
                                                <th>النوع</th>
                                                <th>المستقبل</th>
                                                <th>الحالة</th>
                                                <th>الأولوية</th>
                                                <th>التاريخ</th>
                                                <th>الإجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($allNotifications as $notification): ?>
                                                <tr class="<?= $notification['status'] === 'pending' ? 'table-warning' : '' ?>">
                                                    <td>
                                                        <strong><?= htmlspecialchars($notification['title']) ?></strong>
                                                        <br><small><?= htmlspecialchars(substr($notification['message'], 0, 100)) ?>...</small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?= htmlspecialchars($notification['type']) ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($notification['recipient_type'] === 'admin'): ?>
                                                            <?= htmlspecialchars($notification['admin_name'] ?? 'غير محدد') ?>
                                                        <?php else: ?>
                                                            <i class="fas fa-globe"></i> عام
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $notification['status'] === 'sent' ? 'success' : ($notification['status'] === 'failed' ? 'danger' : 'warning') ?>">
                                                            <?= htmlspecialchars($notification['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $notification['priority'] === 'urgent' ? 'danger' : ($notification['priority'] === 'high' ? 'warning' : 'primary') ?>">
                                                            <?= htmlspecialchars($notification['priority']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small><?= date('Y-m-d H:i', strtotime($notification['created_at'])) ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if ($notification['status'] === 'pending'): ?>
                                                            <button class="btn btn-sm btn-success" onclick="sendNotification(<?= $notification['id'] ?>)">
                                                                <i class="fas fa-paper-plane"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($notification['status'] === 'pending'): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="mark_as_read">
                                                                <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- إرسال إشعار -->
                        <div class="tab-pane fade" id="send">
                            <h3 class="mb-4">
                                <i class="fas fa-paper-plane"></i>
                                إرسال إشعار جديد
                            </h3>
                            
                            <form method="POST" class="notification-form">
                                <input type="hidden" name="action" value="send_notification">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">نوع الإشعار</label>
                                            <select class="form-select" name="notification_type" required>
                                                <option value="system">النظام</option>
                                                <option value="new_submission">استمارة جديدة</option>
                                                <option value="form_completed">اكتمال استمارة</option>
                                                <option value="admin_alert">تنبيه إداري</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">الأولوية</label>
                                            <select class="form-select" name="priority" required>
                                                <option value="low">منخفضة</option>
                                                <option value="normal" selected>عادية</option>
                                                <option value="high">عالية</option>
                                                <option value="urgent">عاجلة</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">عنوان الإشعار</label>
                                    <input type="text" class="form-control" name="title" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">رسالة الإشعار</label>
                                    <textarea class="form-control" name="message" rows="5" required></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">المستقبل</label>
                                            <select class="form-select" name="recipient_type" required>
                                                <option value="admin">مدير محدد</option>
                                                <option value="email">بريد إلكتروني</option>
                                                <option value="sms">رقم هاتف</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">معرف المستقبل</label>
                                            <?php if ($permissionService->hasPermission($adminId, 'system.settings')): ?>
                                                <select class="form-select" name="recipient_id">
                                                    <option value="">-- اختر المدير --</option>
                                                    <?php foreach ($admins as $admin): ?>
                                                        <option value="<?= $admin['id'] ?>"><?= htmlspecialchars($admin['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php else: ?>
                                                <input type="text" class="form-control" name="recipient_contact" placeholder="بريد إلكتروني أو رقم هاتف">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-gradient">
                                    <i class="fas fa-paper-plane"></i>
                                    إرسال الإشعار
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
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
        
        // إرسال إشعار
        function sendNotification(notificationId) {
            fetch('api/notifications.php?action=send&id=' + notificationId, {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('فشل في إرسال الإشعار: ' + (data.message || 'خطأ غير محدد'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ في الشبكة');
            });
        }
        
        // تحديث الإشعارات كل 30 ثانية
        function refreshNotifications() {
            // يمكن إضافة AJAX call هنا لتحديث الإشعارات
            console.log('Refreshing notifications...');
        }
        
        setInterval(refreshNotifications, 30000);
        
        // إشعار عند الحاجة
        function showNotification(title, message) {
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(title, {
                    body: message,
                    icon: '/assets/images/logo.png'
                });
            }
        }
        
        // طلب إذن الإشعارات
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    console.log('إذن الإشعارات تم الحصول عليه');
                }
            });
        }
    </script>
</body>
</html>