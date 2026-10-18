# Wavixa Authentication System

نظام المصادقة الشامل لـ Wavixa مع دعم كامل للـ Email Verification و Password Reset.

## 📋 المحتويات

1. [نظرة عامة](#نظرة-عامة)
2. [المميزات](#المميزات)
3. [البنية التقنية](#البنية-التقنية)
4. [API Endpoints](#api-endpoints)
5. [Frontend Integration](#frontend-integration)
6. [تدفق المصادقة](#تدفق-المصادقة)
7. [الأمان](#الأمان)

## نظرة عامة

نظام مصادقة احترافي مبني على:
- **Backend**: Laravel 12 + Sanctum
- **Frontend**: Vue.js 3 + Vue Router
- **Database**: MySQL
- **Authentication**: JWT Tokens via Sanctum

## المميزات

✅ **التسجيل (Register)**
- التحقق من صحة البيانات
- تشفير كلمات المرور
- إرسال بريد التحقق من البريد الإلكتروني

✅ **تسجيل الدخول (Login)**
- التحقق من البريد والكلمة المرورية
- التحقق من تفعيل البريد الإلكتروني
- إنشاء JWT Token
- تحديث آخر وقت دخول

✅ **التحقق من البريد الإلكتروني (Email Verification)**
- إرسال رابط التحقق
- التحقق من الرابط
- إعادة إرسال البريد

✅ **استعادة كلمة المرور (Password Reset)**
- إرسال رابط إعادة تعيين
- التحقق من الرابط
- تعيين كلمة مرور جديدة

✅ **إدارة الملف الشخصي (Profile)**
- عرض بيانات المستخدم
- تحديث الملف الشخصي
- تحديث كلمة المرور

✅ **تسجيل الخروج (Logout)**
- حذف الـ Token
- مسح بيانات المستخدم

## البنية التقنية

### Backend Structure

```
app/Modules/Auth/
├── Controllers/
│   └── AuthController.php
├── Requests/
│   ├── RegisterRequest.php
│   ├── LoginRequest.php
│   ├── ForgotPasswordRequest.php
│   └── ResetPasswordRequest.php
└── Services/
    └── (Business logic)

database/migrations/
├── create_users_table.php
└── create_password_reset_tokens_table.php
```

### Frontend Structure

```
src/
├── pages/
│   ├── Login.vue
│   ├── Register.vue
│   ├── Dashboard.vue
│   ├── ForgotPassword.vue
│   └── ResetPassword.vue
├── services/
│   ├── api.js (HTTP Client)
│   └── auth.js (Auth Service)
├── router/
│   └── index.js (Route Guards)
└── App.vue
```

## API Endpoints

### Public Endpoints (بدون مصادقة)

#### 1. التسجيل
```http
POST /api/auth/register
Content-Type: application/json

{
  "name": "أحمد محمد",
  "email": "ahmed@example.com",
  "phone_number": "+201234567890",
  "password": "SecurePassword123",
  "password_confirmation": "SecurePassword123"
}
```

**Response (201 Created)**
```json
{
  "success": true,
  "message": "Registration successful. Please verify your email.",
  "data": {
    "user": {
      "id": 1,
      "name": "أحمد محمد",
      "email": "ahmed@example.com",
      "phone_number": "+201234567890",
      "email_verified_at": null,
      "created_at": "2026-07-03T10:00:00Z"
    },
    "email_verification_required": true
  }
}
```

#### 2. تسجيل الدخول
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "ahmed@example.com",
  "password": "SecurePassword123"
}
```

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "أحمد محمد",
      "email": "ahmed@example.com",
      "email_verified_at": "2026-07-03T10:05:00Z",
      "last_login_at": "2026-07-03T10:10:00Z"
    },
    "token": "1|abcdef123456...",
    "token_type": "Bearer"
  }
}
```

#### 3. نسيان كلمة المرور
```http
POST /api/auth/forgot-password
Content-Type: application/json

{
  "email": "ahmed@example.com"
}
```

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Password reset link sent to your email"
}
```

#### 4. إعادة تعيين كلمة المرور
```http
POST /api/auth/reset-password
Content-Type: application/json

{
  "token": "reset_token_from_email",
  "email": "ahmed@example.com",
  "password": "NewPassword123",
  "password_confirmation": "NewPassword123"
}
```

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Password reset successfully"
}
```

#### 5. إعادة إرسال بريد التحقق
```http
POST /api/auth/resend-verification-email
Content-Type: application/json

{
  "email": "ahmed@example.com"
}
```

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Verification email sent"
}
```

#### 6. التحقق من البريد الإلكتروني
```http
GET /api/auth/verify-email/{id}/{hash}
```

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Email verified successfully",
  "data": {
    "id": 1,
    "email": "ahmed@example.com",
    "email_verified_at": "2026-07-03T10:15:00Z"
  }
}
```

### Protected Endpoints (تتطلب مصادقة)

جميع الـ endpoints التالية تتطلب رأس `Authorization`:
```http
Authorization: Bearer {token}
```

#### 1. الحصول على بيانات المستخدم الحالي
```http
GET /api/auth/me
Authorization: Bearer {token}
```

**Response (200 OK)**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "أحمد محمد",
    "email": "ahmed@example.com",
    "phone_number": "+201234567890",
    "subscription_plan": "free",
    "subscription_status": "active"
  }
}
```

#### 2. تحديث الملف الشخصي
```http
PUT /api/auth/profile
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "أحمد علي محمد",
  "phone_number": "+201234567891"
}
```

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": {
    "id": 1,
    "name": "أحمد علي محمد",
    "phone_number": "+201234567891"
  }
}
```

#### 3. تحديث كلمة المرور
```http
POST /api/auth/change-password
Authorization: Bearer {token}
Content-Type: application/json

{
  "current_password": "OldPassword123",
  "password": "NewPassword123",
  "password_confirmation": "NewPassword123"
}
```

#### 4. تحديث الـ Token
```http
POST /api/auth/refresh-token
Authorization: Bearer {token}
```

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Token refreshed",
  "data": {
    "token": "2|newtoken123456...",
    "token_type": "Bearer"
  }
}
```

#### 5. تسجيل الخروج
```http
POST /api/auth/logout
Authorization: Bearer {token}
```

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Logout successful"
}
```

## Frontend Integration

### استخدام Auth Service

```javascript
import auth from '@/services/auth';

// التسجيل
const registerResponse = await auth.register({
  name: 'أحمد محمد',
  email: 'ahmed@example.com',
  password: 'SecurePassword123',
  password_confirmation: 'SecurePassword123'
});

// تسجيل الدخول
const loginResponse = await auth.login({
  email: 'ahmed@example.com',
  password: 'SecurePassword123'
});

// الحصول على المستخدم الحالي
const user = auth.getUser();

// التحقق من المصادقة
if (auth.isAuthenticated()) {
  // المستخدم مسجل دخول
}

// تسجيل الخروج
await auth.logout();

// نسيان كلمة المرور
await auth.forgotPassword('ahmed@example.com');

// إعادة تعيين كلمة المرور
await auth.resetPassword({
  token: 'reset_token',
  email: 'ahmed@example.com',
  password: 'NewPassword123',
  password_confirmation: 'NewPassword123'
});
```

### Route Guards

```javascript
// في router/index.js
router.beforeEach((to, from, next) => {
  const isAuthenticated = auth.isAuthenticated();
  const requiresAuth = to.meta.requiresAuth;

  if (requiresAuth && !isAuthenticated) {
    next('/login');
  } else if (!requiresAuth && isAuthenticated && to.path === '/login') {
    next('/dashboard');
  } else {
    next();
  }
});
```

### استخدام في Vue Components

```vue
<template>
  <div>
    <p v-if="isAuthenticated">مرحبا {{ user?.name }}</p>
    <button @click="logout">تسجيل الخروج</button>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import auth from '@/services/auth';

export default {
  setup() {
    const isAuthenticated = ref(false);
    const user = ref(null);

    onMounted(() => {
      isAuthenticated.value = auth.isAuthenticated();
      user.value = auth.getUser();
    });

    const logout = async () => {
      await auth.logout();
      // إعادة التوجيه إلى صفحة تسجيل الدخول
    };

    return { isAuthenticated, user, logout };
  }
};
</script>
```

## تدفق المصادقة

### تدفق التسجيل والتحقق من البريد

```
1. المستخدم يملأ نموذج التسجيل
   ↓
2. إرسال البيانات إلى /api/auth/register
   ↓
3. التحقق من صحة البيانات
   ↓
4. إنشاء مستخدم جديد
   ↓
5. إرسال بريد التحقق
   ↓
6. المستخدم يفتح الرابط من البريد
   ↓
7. التحقق من الرابط
   ↓
8. تفعيل البريد الإلكتروني
   ↓
9. إعادة التوجيه إلى صفحة تسجيل الدخول
```

### تدفق تسجيل الدخول

```
1. المستخدم يدخل البريد وكلمة المرور
   ↓
2. إرسال البيانات إلى /api/auth/login
   ↓
3. التحقق من البريد والكلمة المرورية
   ↓
4. التحقق من تفعيل البريد الإلكتروني
   ↓
5. إنشاء JWT Token
   ↓
6. تحديث آخر وقت دخول
   ↓
7. إرجاع Token والمستخدم
   ↓
8. حفظ Token في localStorage
   ↓
9. إعادة التوجيه إلى Dashboard
```

### تدفق استعادة كلمة المرور

```
1. المستخدم ينقر على "نسيت كلمة المرور"
   ↓
2. إدخال البريد الإلكتروني
   ↓
3. إرسال طلب إلى /api/auth/forgot-password
   ↓
4. إنشاء رابط إعادة التعيين
   ↓
5. إرسال البريد برابط إعادة التعيين
   ↓
6. المستخدم يفتح الرابط
   ↓
7. إدخال كلمة مرور جديدة
   ↓
8. إرسال طلب إلى /api/auth/reset-password
   ↓
9. التحقق من الرابط
   ↓
10. تحديث كلمة المرور
    ↓
11. إعادة التوجيه إلى صفحة تسجيل الدخول
```

## الأمان

### تشفير كلمات المرور
- استخدام bcrypt مع 12 جولة
- عدم تخزين كلمات المرور بصيغة نصية

### JWT Tokens
- توليد tokens آمنة
- انتهاء صلاحية الـ tokens
- عدم تخزين sensitive data في الـ token

### CORS
- تقييد الـ origins المسموحة
- التحقق من الـ headers

### Email Verification
- رابط التحقق يحتوي على hash
- انتهاء صلاحية الرابط
- عدم السماح بتسجيل الدخول بدون تحقق

### Password Reset
- رابط إعادة التعيين يحتوي على token عشوائي
- انتهاء صلاحية الرابط
- التحقق من البريد الإلكتروني

### API Protection
- جميع الـ endpoints المحمية تتطلب token
- التحقق من صحة الـ token
- حذف الـ token عند تسجيل الخروج

## متطلبات التشغيل

### Backend
- PHP 8.2+
- Laravel 12
- MySQL 5.7+
- Composer

### Frontend
- Node.js 18+
- npm أو yarn
- Vue.js 3
- Vue Router 4

## خطوات التثبيت

### Backend

```bash
cd backend

# تثبيت الـ dependencies
composer install

# إنشاء ملف .env
cp .env.example .env

# إنشاء مفتاح التطبيق
php artisan key:generate

# تشغيل الـ migrations
php artisan migrate

# بدء الخادم
php artisan serve
```

### Frontend

```bash
cd frontend

# تثبيت الـ dependencies
npm install

# إضافة vue-router و axios
npm install vue-router axios

# بدء خادم التطوير
npm run dev
```

## الاختبار

### اختبار التسجيل
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "أحمد محمد",
    "email": "ahmed@example.com",
    "password": "SecurePassword123",
    "password_confirmation": "SecurePassword123"
  }'
```

### اختبار تسجيل الدخول
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "ahmed@example.com",
    "password": "SecurePassword123"
  }'
```

### اختبار الـ Protected Endpoint
```bash
curl -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer {token}"
```

---

**آخر تحديث**: 3 يوليو 2026
**الإصدار**: 1.0.0
