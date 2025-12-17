# توثيق قاعدة البيانات - نظام الاستمارات الديناميكية

## نظرة عامة

تم إنشاء هيكل قاعدة البيانات الكامل لنظام الاستمارات الديناميكية للموظفين. يتضمن النظام 7 جداول رئيسية مع علاقات محسّنة وأمان عالي.

## هيكل الجداول

### 1. جدول المديرين (admins)

**الغرض:** تخزين معلومات المديرين والمشرفين على النظام

| العمود | النوع | الوصف | الإضافات |
|---------|-------|---------|------------|
| id | bigint(20) UNSIGNED | المعرف الأساسي | AUTO_INCREMENT, PRIMARY KEY |
| name | varchar(255) | اسم المدير الكامل | NOT NULL |
| email | varchar(255) | البريد الإلكتروني | NOT NULL, UNIQUE |
| password | varchar(255) | كلمة المرور المشفرة | NOT NULL |
| role | enum | دور المدير (super_admin/admin/manager) | DEFAULT 'manager' |
| created_at | timestamp | تاريخ الإنشاء | NULL DEFAULT |
| updated_at | timestamp | تاريخ التحديث | NULL DEFAULT |

**الفهارس:**
- `admins_email_unique` على email
- `admins_role_index` على role
- `admins_created_at_index` على created_at

### 2. جدول الإدارات (departments)

**الغرض:** تخزين معلومات الإدارات والأقسام التنظيمية

| العمود | النوع | الوصف | الإضافات |
|---------|-------|---------|------------|
| id | bigint(20) UNSIGNED | المعرف الأساسي | AUTO_INCREMENT, PRIMARY KEY |
| name | varchar(255) | اسم الإدارة | NOT NULL, UNIQUE |
| description | text | وصف الإدارة | NULL DEFAULT |
| manager_id | bigint(20) UNSIGNED | معرف مدير الإدارة | FOREIGN KEY → admins.id |
| is_active | tinyint(1) | حالة النشاط | DEFAULT 1 |
| created_at | timestamp | تاريخ الإنشاء | NULL DEFAULT |
| updated_at | timestamp | تاريخ التحديث | NULL DEFAULT |

**العلاقات:**
- `departments_manager_id_foreign`: العلاقة مع جدول admins

**الفهارس:**
- `departments_name_unique` على name
- `departments_manager_id_foreign` على manager_id
- `departments_is_active_index` على is_active
- `departments_active_manager` مركب على (is_active, manager_id)

### 3. جدول الاستمارات (forms)

**الغرض:** تعريف الاستمارات الديناميكية

| العمود | النوع | الوصف | الإضافات |
|---------|-------|---------|------------|
| id | bigint(20) UNSIGNED | المعرف الأساسي | AUTO_INCREMENT, PRIMARY KEY |
| title | varchar(255) | عنوان الاستمارة | NOT NULL |
| description | text | وصف الاستمارة | NULL DEFAULT |
| slug | varchar(255) | معرف رابط الاستمارة | NOT NULL, UNIQUE |
| created_by | bigint(20) UNSIGNED | معرف منشئ الاستمارة | FOREIGN KEY → admins.id |
| status | enum | حالة الاستمارة (active/inactive) | DEFAULT 'active' |
| allow_multiple_submissions | tinyint(1) | السماح بإرسال متعدد | DEFAULT 1 |
| show_department_field | tinyint(1) | إظهار حقل الإدارة | DEFAULT 1 |
| created_at | timestamp | تاريخ الإنشاء | NULL DEFAULT |
| updated_at | timestamp | تاريخ التحديث | NULL DEFAULT |

**العلاقات:**
- `forms_created_by_foreign`: العلاقة مع جدول admins

**الفهارس:**
- `forms_slug_unique` على slug
- `forms_created_by_foreign` على created_by
- `forms_status_index` على status
- `forms_form_status` مركب على (form_id, status)

### 4. جدول حقول الاستمارات (form_fields)

**الغرض:** تحديد تكوين حقول الاستمارات

| العمود | النوع | الوصف | الإضافات |
|---------|-------|---------|------------|
| id | bigint(20) UNSIGNED | المعرف الأساسي | AUTO_INCREMENT, PRIMARY KEY |
| form_id | bigint(20) UNSIGNED | معرف الاستمارة الأم | FOREIGN KEY → forms.id |
| field_type | enum | نوع الحقل | NOT NULL |
| label | varchar(255) | تسمية الحقل | NOT NULL |
| placeholder | varchar(255) | نص مساعد | NULL DEFAULT |
| is_required | tinyint(1) | مطلوب أم لا | DEFAULT 0 |
| is_active | tinyint(1) | نشط أم لا | DEFAULT 1 |
| field_options | json | خيارات الحقل | NULL DEFAULT |
| source_type | enum | نوع مصدر الخيارات | DEFAULT 'static' |
| parent_field_id | bigint(20) UNSIGNED | الحقل الأم (للـ repeater) | FOREIGN KEY → form_fields.id |
| field_key | varchar(255) | مفتاح الحقل الفريد | NOT NULL |
| order_index | int(11) | ترتيب العرض | DEFAULT 0 |
| validation_rules | json | قواعد التحقق | NULL DEFAULT |
| helper_text | text | نص مساعد إضافي | NULL DEFAULT |
| created_at | timestamp | تاريخ الإنشاء | NULL DEFAULT |
| updated_at | timestamp | تاريخ التحديث | NULL DEFAULT |

**أنواع الحقول المدعومة:**
- text, email, number, date, textarea
- select, radio, checkbox, file
- repeater, date_range

**العلاقات:**
- `form_fields_form_id_foreign`: العلاقة مع جدول forms
- `form_fields_parent_field_id_foreign`: العلاقة مع الجدول نفسه (لحقول repeater)

**الفهارس:**
- `form_fields_form_id_foreign` على form_id
- `form_fields_parent_field_id_foreign` على parent_field_id
- `form_fields_field_key_index` على field_key
- `form_fields_order_index` على order_index
- `form_fields_form_order` مركب على (form_id, order_index)
- `form_fields_form_id_field_key_unique` مركب فريد على (form_id, field_key)

### 5. جدول الإجابات (form_submissions)

**الغرض:** رأسية الإجابات المرسلة

| العمود | النوع | الوصف | الإضافات |
|---------|-------|---------|------------|
| id | bigint(20) UNSIGNED | المعرف الأساسي | AUTO_INCREMENT, PRIMARY KEY |
| form_id | bigint(20) UNSIGNED | معرف الاستمارة | FOREIGN KEY → forms.id |
| submitted_by | varchar(255) | اسم المرسل | NOT NULL |
| department_id | bigint(20) UNSIGNED | معرف الإدارة | FOREIGN KEY → departments.id |
| status | enum | حالة الإجابة | DEFAULT 'pending' |
| submitted_at | timestamp | تاريخ الإرسال | DEFAULT CURRENT_TIMESTAMP |
| ip_address | varchar(45) | عنوان IP | NULL DEFAULT |
| reference_code | varchar(50) | كود مرجعي فريد | NOT NULL, UNIQUE |

**الحالات المدعومة:**
- pending (في الانتظار)
- completed (مكتمل)
- archived (مؤرشف)

**العلاقات:**
- `form_submissions_form_id_foreign`: العلاقة مع جدول forms
- `form_submissions_department_id_foreign`: العلاقة مع جدول departments

**الفهارس:**
- `form_submissions_reference_code_unique` على reference_code
- `form_submissions_form_id_foreign` على form_id
- `form_submissions_department_id_foreign` على department_id
- `form_submissions_status_index` على status
- `form_submissions_submitted_at_index` على submitted_at

### 6. جدول الإجابات التفصيلية (submission_answers)

**الغرض:** الإجابات التفصيلية لكل حقل

| العمود | النوع | الوصف | الإضافات |
|---------|-------|---------|------------|
| id | bigint(20) UNSIGNED | المعرف الأساسي | AUTO_INCREMENT, PRIMARY KEY |
| submission_id | bigint(20) UNSIGNED | معرف الإجابة | FOREIGN KEY → form_submissions.id |
| field_id | bigint(20) UNSIGNED | معرف الحقل | FOREIGN KEY → form_fields.id |
| answer | text | إجابة الحقل | NULL DEFAULT |
| file_path | varchar(500) | مسار الملف المرفوع | NULL DEFAULT |
| file_name | varchar(255) | اسم الملف | NULL DEFAULT |
| file_size | bigint(20) | حجم الملف بالبايت | NULL DEFAULT |
| repeat_index | int(11) | مؤشر التكرار | DEFAULT 0 |

**العلاقات:**
- `submission_answers_submission_id_foreign`: العلاقة مع جدول form_submissions
- `submission_answers_field_id_foreign`: العلاقة مع جدول form_fields

**الفهارس:**
- `submission_answers_submission_id_foreign` على submission_id
- `submission_answers_field_id_foreign` على field_id
- `submission_answers_repeat_index` على repeat_index
- `submission_answers_submission_field` مركب على (submission_id, field_id)
- `submission_answers_field_repeat_index` مركب على (field_id, repeat_index)

### 7. جدول إعدادات النظام (system_settings)

**الغرض:** إعدادات النظام العامة

| العمود | النوع | الوصف | الإضافات |
|---------|-------|---------|------------|
| setting_key | varchar(100) | مفتاح الإعداد | PRIMARY KEY |
| setting_value | longtext | قيمة الإعداد (JSON) | NULL DEFAULT |
| created_at | timestamp | تاريخ الإنشاء | NULL DEFAULT |
| updated_at | timestamp | تاريخ التحديث | NULL DEFAULT |

**الإعدادات الافتراضية:**
- `forms_allowed_mime`: أنواع الملفات المسموحة
- `forms_max_upload_mb`: الحد الأقصى لحجم الرفع (MB)
- `forms_upload_path`: مسار الرفع
- `reference_code_prefix`: بادئة الكود المرجعي
- `reference_code_length`: طول الكود المرجعي
- `forms_date_format`: تنسيق التاريخ
- `forms_datetime_format`: تنسيق التاريخ والوقت
- `forms_timezone`: المنطقة الزمنية

## الخصائص الأمنية

### 1. حماية الملفات
- ملف `.htaccess` في مجلد `storage/forms/`
- منع تصفح المجلدات
- تقييد أنواع الملفات المسموحة
- رؤوس أمان إضافية

### 2. فهارس الأداء
- فهارس مركبة للاستعلامات المعقدة
- فهارس على المفاتيح الأجنبية
- فهارس على الحقول المستخدمة في البحث

### 3. التكامل المرجعي
-_foreign keys_ محسّنة مع ON DELETE/UPDATE rules
- قيود UNIQUE على البيانات الحساسة
- تعريف أنواع البيانات المناسب

## البيانات الأولية

### المدير الافتراضي
- **الاسم:** System Administrator
- **البريد الإلكتروني:** admin@example.com
- **كلمة المرور:** admin123 (يجب تغييرها في الإنتاج)
- **الدور:** super_admin

### الإدارات النموذجية
1. إدارة الموارد البشرية
2. إدارة تقنية المعلومات
3. إدارة المالية
4. إدارة التسويق

## التعليمات التشغيلية

### تشغيل Migration
```sql
source /path/to/database/migrations/2024_01_01_000000_create_employee_evaluation_system_tables.sql;
```

### إنشاء مجلدات التخزين
```bash
mkdir -p storage/forms/{uploads,temp,backup}
chmod 755 storage/forms
chmod 644 storage/forms/.htaccess
```

### التحقق من الجداول
```sql
SHOW TABLES;
DESCRIBE admins;
DESCRIBE departments;
-- إلخ...
```

## ملاحظات مهمة

1. **الأمان:** يجب تغيير كلمة المرور الافتراضية فوراً
2. **النسخ الاحتياطي:** إعداد نظام نسخ احتياطي منتظم
3. **المراقبة:** مراقبة أداء الفهارس والاستعلامات
4. **التحديث:** تحديث إعدادات النظام حسب الحاجة
5. **الصلاحيات:** إعداد صلاحيات قاعدة البيانات المناسبة

## اختبارات Acceptance

- ✅ جميع الجداول تُنشأ بنجاح
- ✅ العلاقات Foreign Key صحيحة
- ✅ الفهارس تعمل بشكل صحيح
- ✅ لا توجد أخطاء في SQL
- ✅ البيانات الأولية تُدخل بنجاح
- ✅ حماية الملفات مُطبقة
- ✅ التوثيق شامل ومفصل