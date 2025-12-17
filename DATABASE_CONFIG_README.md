# ملف قاعدة البيانات - Database Configuration

## نظرة عامة
ملف `config/database.php` يوفر اتصالاً آمناً ومحسناً بقاعدة البيانات MySQL/MariaDB باستخدام PDO مع جميع ميزات الأمان المطلوبة.

## الميزات

### ✅ الأمان
- **Prepared Statements**: محمي من SQL Injection
- **PDO::ERRMODE_EXCEPTION**: معالجة شاملة للأخطاء
- **Emulated Prepares**: مُعطل لتحسين الأمان
- **Character Set**: دعم كامل للعربية (utf8mb4)
- **Timeout**: حماية من التعليق (30 ثانية)

### ✅ الأداء
- **Persistent Connections**: قابل للتفعيل حسب الحاجة
- **Connection Pooling**: متاح عبر Singleton pattern
- **Query Optimization**: إعدادات SQL_MODE محسنة

### ✅ المرونة
- **Environment Variables**: دعم متغيرات البيئة (.env)
- **Fallback Values**: قيم افتراضية آمنة
- **Config Summary**: معلومات الاتصال بدون بيانات حساسة
- **Connection Testing**: دوال اختبار الاتصال

## الإعدادات الافتراضية

```php
DB_HOST = localhost
DB_USER = root
DB_PASS = (فارغة)
DB_NAME = buraq_forms
DB_CHARSET = utf8mb4
DB_PORT = 3306
```

## طريقة الاستخدام

### 1. إدراج الملف
```php
require_once 'config/database.php';
```

### 2. استخدام الاتصال
```php
// الطريقة 1: استخدام المتغير العام
$stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetch();

// الطريقة 2: استخدام الدالة
$pdo = getDatabaseConnection();
$stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetch();
```

### 3. اختبار الاتصال
```php
if (testDatabaseConnection()) {
    echo "الاتصال يعمل بشكل طبيعي!";
}
```

### 4. معلومات الاتصال
```php
$config = getDatabaseConfig();
echo "قاعدة البيانات: " . $config['database'];
echo "المضيف: " . $config['host'];
echo "حالة الاتصال: " . ($config['connected'] ? 'متصل' : 'غير متصل');
```

## متغيرات البيئة

### إنشاء ملف .env
```bash
cp .env.example .env
```

### تحرير .env
```env
DB_HOST=localhost
DB_USER=your_username
DB_PASS=your_password
DB_NAME=your_database
DB_CHARSET=utf8mb4
DB_PORT=3306
```

**ملاحظة**: ملف .env مُدرج في .gitignore لأمان البيانات الحساسة.

## معالجة الأخطاء

### في بيئة التطوير
```php
// الأخطاء تظهر مع التفاصيل
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### في بيئة الإنتاج
```php
// الأخطاء تُسجل في الملف فقط
if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
    die("Service temporarily unavailable. Please try again later.");
}
```

## ملفات الأمان

### حماية الوصول المباشر
```php
if (!defined('EES_SYSTEM')) {
    define('EES_SYSTEM', true);
}
```

### .gitignore
- `config/` - يتجاهل المجلد بالكامل
- `!/config/database.php` - يسمح بالملف الرئيسي فقط
- `!/config/database.php.example` - يسمح بملف المثال

## اختبار الاتصال

### اختبار سطر الأوامر
```bash
php test_database_connection.php
```

### اختبار في المتصفح
```php
<?php
require_once 'config/database.php';
if (testDatabaseConnection()) {
    echo "✅ الاتصال يعمل!";
} else {
    echo "❌ فشل الاتصال!";
}
?>
```

## استكشاف الأخطاء

### مشاكل شائعة

#### 1. خطأ الاتصال
```php
PDOException: SQLSTATE[HY000] [2002] Connection refused
```
**الحل**: تأكد من تشغيل خدمة MySQL والتحقق من DB_HOST

#### 2. خطأ الصلاحيات
```php
PDOException: SQLSTATE[28000] [1045] Access denied
```
**الحل**: تأكد من صحة DB_USER و DB_PASS

#### 3. قاعدة البيانات غير موجودة
```php
PDOException: SQLSTATE[42000] [1049] Unknown database
```
**الحل**: تأكد من إنشاء قاعدة البيانات DB_NAME

### تسجيل الأخطاء
جميع الأخطاء تُسجل في:
```bash
tail -f /var/log/php_errors.log
```

## الميزات المتقدمة

### 1. إعدادات SQL مُحسنة
```php
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'
```

### 2. دعم Timezone
```php
SET time_zone = '+00:00'
```

### 3. Character Set مُعزز
```php
SET NAMES utf8mb4
SET CHARACTER SET utf8mb4
```

## الأداء والقياس

### مراقبة الأداء
```php
$start = microtime(true);
// استعلام قاعدة البيانات
$queryTime = microtime(true) - $start;
echo "وقت الاستعلام: " . round($queryTime, 4) . " ثانية";
```

### تحليل الاتصال
```php
// فحص حالة الاتصال
if ($pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS)) {
    echo "الاتصال نشط";
}
```

## التحديث والصيانة

### تحديث الإعدادات
1. تحرير `config/database.php` أو ملف `.env`
2. إعادة تشغيل التطبيق
3. اختبار الاتصال

### النسخ الاحتياطي
```bash
mysqldump -u root -p buraq_forms > backup.sql
```

## دعم إضافي

### التوافق
- **PHP**: 7.4+
- **MySQL**: 5.7+ / MariaDB 10.2+
- **PDO**: مطلوب

### المتطلبات
- امتداد PDO MySQL
- امتداد mbstring
- امتداد openssl

---

**ملاحظة**: هذا الملف جزء من نظام تقييم الموظفين Employee Evaluation System