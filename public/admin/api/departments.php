 <?php
 
 declare(strict_types=1);
 
 // تضمين الإعدادات
 require_once __DIR__ . '/../../../config/database.php';
+require_once __DIR__ . '/../../../src/Core/Auth.php';
 require_once __DIR__ . '/../../../src/Core/Services/DepartmentService.php';
 
 use BuraqForms\Core\Services\DepartmentService;
+use BuraqForms\Core\Auth;
 
 // إعداد headers للـ JSON API
 header('Content-Type: application/json; charset=utf-8');
-header('Access-Control-Allow-Origin: *');
 header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
-header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
+header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
+
+$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
+if ($origin !== '') {
+    header('Access-Control-Allow-Origin: ' . $origin);
+    header('Access-Control-Allow-Credentials: true');
+} else {
+    header('Access-Control-Allow-Origin: *');
+}
 
 // معالجة preflight requests
 if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
     http_response_code(200);
     exit;
 }
 
 // بدء الجلسة
 session_start();
 
-// التحقق من الصلاحيات (مؤقتاً)
-$isAdmin = true; // يجب التحقق من تسجيل الدخول في التطبيق الحقيقي
-
-if (!$isAdmin) {
-    http_response_code(403);
-    echo json_encode(['error' => 'غير مسموح بالوصول']);
-    exit;
-}
-
 // إنشاء خدمة الإدارات
 $departmentService = new DepartmentService($pdo);
 
 // دالة للإجابة بنجاح
 function successResponse($data = [], $message = 'تمت العملية بنجاح') {
     echo json_encode([
         'success' => true,
         'message' => $message,
         'data' => $data
     ]);
     exit;
 }
 
 // دالة للإجابة مع خطأ
 function errorResponse($message, $code = 400) {
     http_response_code($code);
     echo json_encode([
         'success' => false,
         'error' => $message
     ]);
     exit;
 }
 
-// دالة للتحقق من CSRF token
-function verifyCSRF() {
-    $token = $_SERVER['REQUEST_METHOD'] === 'POST' ? 
-        ($_POST['csrf_token'] ?? '') : 
-        ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
-    
-    if (!$token || $token !== ($_SESSION['csrf_token'] ?? '')) {
+function ensureAuthenticated(): array {
+    $user = Auth::current_user();
+
+    if (!$user || !Auth::is_logged_in()) {
+        errorResponse('يتطلب تسجيل الدخول', 401);
+    }
+
+    return $user;
+}
+
+function ensurePermission(string ...$permissions): void {
+    ensureAuthenticated();
+
+    foreach ($permissions as $permission) {
+        if (Auth::has_permission($permission)) {
+            return;
+        }
+    }
+
+    errorResponse('غير مسموح بالوصول', 403);
+}
+
+function verifyCSRF(array $data): void {
+    $token = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
+
+    if (!Auth::verify_csrf_token($token)) {
         errorResponse('رمز الأمان غير صحيح', 403);
     }
 }
 
+function getRequestData(string $method): array {
+    if ($method === 'GET') {
+        return $_GET;
+    }
+
+    if ($method === 'POST') {
+        return $_POST;
+    }
+
+    $raw = file_get_contents('php://input') ?: '';
+    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
+
+    if (stripos($contentType, 'application/json') !== false) {
+        $decoded = json_decode($raw, true);
+        return is_array($decoded) ? $decoded : [];
+    }
+
+    parse_str($raw, $parsed);
+    return is_array($parsed) ? $parsed : [];
+}
+
 // التحقق من method والمسار
 $method = $_SERVER['REQUEST_METHOD'];
 $path = $_SERVER['PATH_INFO'] ?? '';
 $path = trim($path, '/');
 
 try {
     // إنشاء CSRF token جديد
-    if (!isset($_SESSION['csrf_token'])) {
-        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
-    }
+    Auth::generate_csrf_token();
 
     // Router بسيط
     switch ($method) {
         case 'POST':
-            verifyCSRF();
+            $data = getRequestData('POST');
+            verifyCSRF($data);
+            ensurePermission('departments.manage', 'departments.edit');
             
             if ($path === 'departments') {
-                $data = [
-                    'name' => $_POST['name'] ?? '',
-                    'description' => $_POST['description'] ?? '',
-                    'manager_id' => !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null,
-                    'is_active' => isset($_POST['is_active'])
+                $departmentData = [
+                    'name' => $data['name'] ?? '',
+                    'description' => $data['description'] ?? '',
+                    'manager_id' => !empty($data['manager_id']) ? (int)$data['manager_id'] : null,
+                    'is_active' => !empty($data['is_active'])
                 ];
                 
-                $department = $departmentService->create($data);
+                $department = $departmentService->create($departmentData);
                 successResponse($department, 'تم إنشاء الإدارة بنجاح');
                 
             } elseif (preg_match('/^departments\/(\d+)$/', $path, $matches)) {
                 $departmentId = (int)$matches[1];
                 
-                $data = [
-                    'name' => $_POST['name'] ?? '',
-                    'description' => $_POST['description'] ?? '',
-                    'manager_id' => !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null,
-                    'is_active' => isset($_POST['is_active'])
+                $departmentData = [
+                    'name' => $data['name'] ?? '',
+                    'description' => $data['description'] ?? '',
+                    'manager_id' => !empty($data['manager_id']) ? (int)$data['manager_id'] : null,
+                    'is_active' => !empty($data['is_active'])
                 ];
                 
-                $department = $departmentService->update($departmentId, $data);
+                $department = $departmentService->update($departmentId, $departmentData);
                 successResponse($department, 'تم تحديث الإدارة بنجاح');
                 
             } elseif (preg_match('/^departments\/(\d+)\/status$/', $path, $matches)) {
                 $departmentId = (int)$matches[1];
-                $isActive = (bool)($_POST['is_active'] ?? false);
+                $isActive = (bool)($data['is_active'] ?? false);
                 
                 $departmentService->setStatus($departmentId, $isActive);
                 $message = $isActive ? 'تم تفعيل الإدارة بنجاح' : 'تم تعطيل الإدارة بنجاح';
                 successResponse([], $message);
                 
             } else {
                 errorResponse('المسار غير صحيح', 404);
             }
             break;
 
         case 'PUT':
-            verifyCSRF();
+            $data = getRequestData('PUT');
+            verifyCSRF($data);
+            ensurePermission('departments.edit');
             
             if (preg_match('/^departments\/(\d+)$/', $path, $matches)) {
                 $departmentId = (int)$matches[1];
                 
-                $data = [
-                    'name' => $_POST['name'] ?? '',
-                    'description' => $_POST['description'] ?? '',
-                    'manager_id' => !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null,
-                    'is_active' => isset($_POST['is_active'])
+                $departmentData = [
+                    'name' => $data['name'] ?? '',
+                    'description' => $data['description'] ?? '',
+                    'manager_id' => !empty($data['manager_id']) ? (int)$data['manager_id'] : null,
+                    'is_active' => !empty($data['is_active'])
                 ];
                 
-                $department = $departmentService->update($departmentId, $data);
+                $department = $departmentService->update($departmentId, $departmentData);
                 successResponse($department, 'تم تحديث الإدارة بنجاح');
                 
             } else {
                 errorResponse('المسار غير صحيح', 404);
             }
             break;
 
         case 'DELETE':
-            verifyCSRF();
+            $data = getRequestData('DELETE');
+            verifyCSRF($data);
+            ensurePermission('departments.edit');
             
             if (preg_match('/^departments\/(\d+)$/', $path, $matches)) {
                 $departmentId = (int)$matches[1];
                 $departmentService->delete($departmentId);
                 successResponse([], 'تم حذف الإدارة بنجاح');
                 
             } else {
                 errorResponse('المسار غير صحيح', 404);
             }
             break;
 
         case 'GET':
+            ensurePermission('departments.view');
+
             if ($path === 'departments') {
                 $isActive = null;
                 if (isset($_GET['is_active'])) {
                     $isActive = $_GET['is_active'] === 'true';
                 }
                 
                 $departments = $departmentService->list($isActive);
                 successResponse($departments);
                 
             } elseif ($path === 'departments/active') {
                 $departments = $departmentService->getActiveDepartments();
                 successResponse($departments);
                 
             } elseif ($path === 'departments/managers') {
                 $managers = $departmentService->getManagersList();
                 successResponse($managers);
                 
             } elseif (preg_match('/^departments\/(\d+)$/', $path, $matches)) {
                 $departmentId = (int)$matches[1];
                 $department = $departmentService->getById($departmentId);
                 successResponse($department);
                 
             } else {
                 errorResponse('المسار غير صحيح', 404);
             }
             break;
 
         default:
             errorResponse('Method غير مدعوم', 405);
     }
 
 } catch (Exception $e) {
     errorResponse($e->getMessage(), 500);
-}
\ No newline at end of file
+}
 
EOF
)
