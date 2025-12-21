# نظام التحقق من الهوية - BuraqForms

## نظرة عامة
تم بناء نظام تحقق من الهوية شامل وآمن لنظام BuraqForms مع جميع الميزات الأمنية المطلوبة.

## الميزات المُنفذة

### 🔐 1. Auth Helper Class (`src/Core/Auth.php`)
- **is_logged_in()** - التحقق من تسجيل الدخول
- **require_auth()** - حماية الصفحات (redirect إذا لم يكن مسجل دخول)
- **require_role($role)** - التحقق من الدور
- **current_user()** - الحصول على بيانات المستخدم
- **logout_user()** - تسجيل الخروج الآمن
- **generate_csrf_token()** - توليد رمز CSRF
- **verify_csrf_token($token)** - التحقق من رمز CSRF

### 🛡️ 2. أمان متقدم
- **CSRF Protection** - حماية من هجمات Cross-Site Request Forgery
- **Session Security** - التحقق من User Agent و IP Address
- **Login Attempt Limiting** - منع هجمات Brute Force
- **Secure Password Hashing** - تشفير آمن لكلمات المرور
- **Session Timeout** - انتهاء صلاحية الجلسة تلقائياً
- **HTTP-only Cookies** - حماية الـ Cookies من JavaScript

### 👥 3. نظام الأدوار والصلاحيات
**الأدوار المدعومة:**
- **admin** - مدير النظام (جميع الصلاحيات)
- **manager** - مدير (إدارة الإدارات والمحتوى)
- **editor** - محرر (إنشاء وتعديل الاستمارات)

**نظام الصلاحيات:**
- Role-based Access Control (RBAC)
- Module-based permissions
- Granular permission system

### 🔒 4. حماية الصفحات
**الصفحات المحمية:**
- `/admin/dashboard.php` - جميع الأدوار
- `/admin/forms.php` - editor+
- `/admin/form-submissions.php` - editor+
- `/admin/departments.php` - manager+
- `/admin/permissions.php` - admin فقط
- `/admin/form-builder.php` - editor+

### 📝 5. تحسينات على login.php
- ✅ إضافة CSRF token وتحقق منه
- ✅ تحسين الـ validation والـ sanitization
- ✅ تسجيل محاولات تسجيل الدخول في Logger
- ✅ معالجة أفضل للأخطاء
- ✅ Secure session configuration
- ✅ إضافة remember me اختياري
- ✅ تحسين UI/UX مع رسائل خطأ واضحة

### 🚪 6. تحسينات على logout.php
- ✅ استدعاء logout_user() من Auth
- ✅ تسجيل عملية الخروج في Logger
- ✅ مسح الجلسة بشكل آمن
- ✅ Session validation قبل logout

## ملفات النظام

### الملفات الجديدة:
- `src/Core/Auth.php` - نظام التحقق من الهوية
- `config/security.php` - إعدادات الأمان
- `test_authentication_system.php` - اختبار شامل للنظام

### الملفات المُحدثة:
- `src/helpers.php` - إضافة دوال Auth helper
- `public/login.php` - تحسينات أمنية شاملة
- `public/logout.php` - نظام logout آمن
- `public/admin/dashboard.php` - إضافة authentication
- `public/admin/forms.php` - إضافة authentication
- `public/admin/form-submissions.php` - إضافة authentication
- `public/admin/departments.php` - إضافة authentication
- `public/admin/permissions.php` - إضافة admin-only access
- `public/admin/form-builder.php` - إضافة authentication

## كيفية الاستخدام

### للمطورين:

#### حماية صفحة جديدة:
```php
<?php
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/Core/Auth.php';

// حماية أساسية
require_auth();

// أو حماية حسب الدور
require_role('admin');

// أو التحقق من الصلاحية
if (!has_permission('forms.create')) {
    die('ليس لديك صلاحية');
}
```

#### استخدام CSRF في النماذج:
```php
<?php
// توليد token
$csrf_token = generate_csrf_token();
?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <!-- باقي النموذج -->
</form>

<?php
// التحقق من token
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    die('رمز الأمان غير صحيح');
}
```

#### التحقق من المستخدم الحالي:
```php
// التحقق من تسجيل الدخول
if (is_logged_in()) {
    $user = current_user();
    echo "مرحباً " . $user['name'];
    echo "دورك: " . $user['role'];
}

// التحقق من الصلاحية
if (has_permission('forms.delete')) {
    // عرض زر الحذف
}
```

### للمديرين:

#### بيانات الاختبار:
- **البريد الإلكتروني:** admin@buraqforms.com
- **كلمة المرور:** password123
- **الدور:** admin

#### تشغيل اختبار النظام:
```
http://your-domain/test_authentication_system.php
```

## إعدادات الأمان

### ملف `config/security.php` يحتوي على:
- Session configuration
- CSRF settings
- Password hashing options
- Login attempt limits
- Security headers
- Rate limiting settings

## Logging والأمان

### يتم تسجيل:
- محاولات تسجيل الدخول الناجحة والفاشلة
- عمليات الخروج
- محاولات الوصول غير المصرح بها
- انتهاكات الأمان
- انتهاء صلاحية الجلسات

## معايير القبول - ✅ مكتملة

- [x] تسجيل دخول آمن مع CSRF
- [x] حماية الصفحات المحمية من الوصول غير المصرح
- [x] نظام roles يعمل بشكل صحيح
- [x] تسجيل دخول وخروج آمن
- [x] Logging للعمليات الأمنية
- [x] اختبار النظام بالكامل

## الاختبارات

### تشغيل اختبار شامل:
```bash
php test_authentication_system.php
```

### اختبار يدوي:
1. انتقل إلى `/login.php`
2. جرب بيانات خاطئة (يجب رفضها)
3. استخدم البيانات الصحيحة للدخول
4. جرب الوصول لصفحات بدون صلاحيات
5. جرب انتهاء صلاحية الجلسة

## التحديثات المستقبلية

### ميزات مقترحة:
- [ ] Two-Factor Authentication (2FA)
- [ ] Social Login integration
- [ ] Password reset functionality
- [ ] Account lockout mechanisms
- [ ] Audit trail enhancements
- [ ] IP whitelisting
- [ ] Advanced session management

## الدعم الفني

للمساعدة أو الإبلاغ عن مشاكل أمنية، يرجى مراجعة:
- ملفات logs في `storage/logs/`
- اختبار النظام عبر `test_authentication_system.php`
- مراجعة إعدادات الأمان في `config/security.php`

---

**تم بناء هذا النظام وفقاً لأفضل معايير الأمان والحماية** 🔐