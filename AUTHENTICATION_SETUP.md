# تعليمات تشغيل نظام المصادقة - Wavixa

دليل شامل لتشغيل نظام المصادقة الكامل.

## 📋 المتطلبات

### Backend
- PHP 8.2+
- MySQL 5.7+ (مشغل)
- Composer
- Laravel 12

### Frontend
- Node.js 18+
- npm أو yarn
- Vue.js 3
- Vue Router 4

## 🚀 خطوات التشغيل

### 1️⃣ تشغيل Backend

#### الخطوة 1: التنقل إلى مجلد Backend
```bash
cd backend
```

#### الخطوة 2: تثبيت الـ Dependencies
```bash
composer install
```

#### الخطوة 3: إعداد ملف .env
```bash
# تأكد من أن .env يحتوي على:
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=Wavixa
DB_USERNAME=root
DB_PASSWORD=
```

#### الخطوة 4: إنشاء مفتاح التطبيق
```bash
php artisan key:generate
```

#### الخطوة 5: تشغيل الـ Migrations
```bash
php artisan migrate
```

#### الخطوة 6: بدء خادم Laravel
```bash
php artisan serve
```

**النتيجة**: Backend يعمل على `http://localhost:8000`

---

### 2️⃣ تشغيل Frontend

#### الخطوة 1: التنقل إلى مجلد Frontend
```bash
cd frontend
```

#### الخطوة 2: تثبيت الـ Dependencies
```bash
npm install
```

#### الخطوة 3: إضافة Vue Router و Axios
```bash
npm install vue-router axios
```

#### الخطوة 4: بدء خادم التطوير
```bash
npm run dev
```

**النتيجة**: Frontend يعمل على `http://localhost:5173`

---

## ✅ اختبار النظام

### 1. فتح المتصفح
اذهب إلى: `http://localhost:5173`

### 2. اختبار التسجيل
1. انقر على "Create Account"
2. أدخل البيانات:
   - الاسم: أحمد محمد
   - البريد: ahmed@example.com
   - الهاتف: +201234567890 (اختياري)
   - كلمة المرور: SecurePassword123
   - تأكيد كلمة المرور: SecurePassword123
3. انقر على "Create Account"

**النتيجة المتوقعة**:
- رسالة نجاح
- إعادة توجيه إلى صفحة التحقق من البريد

### 3. اختبار تسجيل الدخول
1. انقر على "Login"
2. أدخل البيانات:
   - البريد: ahmed@example.com
   - كلمة المرور: SecurePassword123
3. انقر على "Login"

**النتيجة المتوقعة**:
- رسالة نجاح
- إعادة توجيه إلى Dashboard

### 4. اختبار Dashboard
- يجب أن ترى:
  - اسمك في الزاوية العلوية اليمنى
  - إحصائيات (Active Sessions, Total Messages, إلخ)
  - قائمة جانبية بالخيارات

### 5. اختبار تسجيل الخروج
1. انقر على زر "Logout"
2. يجب أن تعود إلى صفحة تسجيل الدخول

---

## 🔧 اختبار API مباشرة

### اختبار التسجيل
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "أحمد محمد",
    "email": "test@example.com",
    "password": "SecurePassword123",
    "password_confirmation": "SecurePassword123"
  }'
```

### اختبار تسجيل الدخول
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecurePassword123"
  }'
```

### اختبار الـ Protected Endpoint
```bash
curl -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer {token}"
```

---

## 📊 هيكل قاعدة البيانات

### جدول Users
```sql
CREATE TABLE users (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  phone_number VARCHAR(20),
  email_verified_at TIMESTAMP NULL,
  password VARCHAR(255) NOT NULL,
  subscription_plan VARCHAR(255) DEFAULT 'free',
  subscription_status VARCHAR(255) DEFAULT 'active',
  last_login_at TIMESTAMP NULL,
  remember_token VARCHAR(100),
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

### جدول Password Reset Tokens
```sql
CREATE TABLE password_reset_tokens (
  email VARCHAR(255) PRIMARY KEY,
  token VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL
);
```

### جدول Personal Access Tokens (Sanctum)
```sql
CREATE TABLE personal_access_tokens (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tokenable_type VARCHAR(255) NOT NULL,
  tokenable_id BIGINT NOT NULL,
  name VARCHAR(255) NOT NULL,
  token VARCHAR(64) UNIQUE NOT NULL,
  abilities LONGTEXT,
  last_used_at TIMESTAMP NULL,
  expires_at TIMESTAMP NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

---

## 🔐 ملاحظات الأمان

### كلمات المرور
- يتم تشفيرها باستخدام bcrypt
- الحد الأدنى 8 أحرف
- لا تُخزن بصيغة نصية

### Tokens
- توليد آمنة باستخدام Sanctum
- تُخزن في localStorage (Frontend)
- تُرسل في رأس Authorization

### Email Verification
- رابط التحقق يحتوي على hash
- انتهاء صلاحية الرابط
- عدم السماح بتسجيل الدخول بدون تحقق

### CORS
- مسموح فقط من localhost:5173
- يمكن تعديل في config/sanctum.php

---

## 🐛 استكشاف الأخطاء

### خطأ: "Connection refused" عند تشغيل Migrations
**الحل**: تأكد من أن MySQL مشغل
```bash
# Windows
# تشغيل MySQL من Services أو:
mysql -u root -p
```

### خطأ: "CORS error" في Frontend
**الحل**: تأكد من أن CORS مفعل في backend
```php
// في config/sanctum.php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 
  'localhost,localhost:3000,localhost:5173,127.0.0.1,127.0.0.1:8000,127.0.0.1:5173,::1'
)),
```

### خطأ: "Token not found" عند تسجيل الدخول
**الحل**: تأكد من أن الـ token يُحفظ في localStorage
```javascript
// في services/api.js
setToken(token) {
  this.token = token;
  localStorage.setItem('auth_token', token);
}
```

### خطأ: "Email verification required"
**الحل**: تحقق من البريد الإلكتروني وانقر على الرابط

---

## 📱 الصفحات المتاحة

| الصفحة | الرابط | الوصف |
|--------|--------|-------|
| تسجيل الدخول | `/login` | صفحة تسجيل الدخول |
| التسجيل | `/register` | صفحة إنشاء حساب جديد |
| Dashboard | `/dashboard` | لوحة التحكم الرئيسية |

---

## 🔄 تدفق المصادقة الكامل

```
1. المستخدم يفتح http://localhost:5173
   ↓
2. يتم التحقق من وجود token في localStorage
   ↓
3. إذا لم يكن هناك token → إعادة توجيه إلى /login
   ↓
4. المستخدم ينقر على "Create Account"
   ↓
5. ملء نموذج التسجيل وإرسال البيانات
   ↓
6. Backend يتحقق من البيانات وينشئ مستخدم جديد
   ↓
7. إرسال بريد التحقق (في الـ logs حالياً)
   ↓
8. المستخدم ينقر على رابط التحقق
   ↓
9. تفعيل البريد الإلكتروني
   ↓
10. المستخدم يسجل الدخول
    ↓
11. Backend ينشئ JWT Token
    ↓
12. Frontend يحفظ Token في localStorage
    ↓
13. إعادة توجيه إلى Dashboard
    ↓
14. Dashboard يعرض بيانات المستخدم
```

---

## 📚 ملفات مهمة

### Backend
- `app/Modules/Auth/Controllers/AuthController.php` - منطق المصادقة
- `app/Models/User.php` - نموذج المستخدم
- `routes/api.php` - تعريف الـ API routes
- `config/sanctum.php` - إعدادات Sanctum

### Frontend
- `src/pages/Login.vue` - صفحة تسجيل الدخول
- `src/pages/Register.vue` - صفحة التسجيل
- `src/pages/Dashboard.vue` - لوحة التحكم
- `src/services/auth.js` - خدمة المصادقة
- `src/services/api.js` - عميل HTTP
- `src/router/index.js` - تعريف الـ routes

---

## 🎯 الخطوات التالية

بعد اختبار النظام بنجاح:

1. **إضافة صفحات إضافية**:
   - صفحة نسيان كلمة المرور
   - صفحة إعادة تعيين كلمة المرور
   - صفحة إعدادات الملف الشخصي

2. **تحسينات الأمان**:
   - إضافة Rate Limiting
   - تفعيل HTTPS
   - إضافة 2FA

3. **تحسينات UX**:
   - إضافة Loading States
   - تحسين رسائل الأخطاء
   - إضافة Notifications

4. **الاختبار**:
   - كتابة Unit Tests
   - كتابة Integration Tests
   - اختبار الأداء

---

## 📞 الدعم

للمزيد من المعلومات:
- اقرأ `docs/AUTHENTICATION.md`
- اقرأ `docs/ARCHITECTURE.md`
- اقرأ `docs/API.md`

---

**آخر تحديث**: 3 يوليو 2026
**الإصدار**: 1.0.0
