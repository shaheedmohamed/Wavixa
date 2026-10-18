<template>
  <DashboardLayout>
    <div class="space-y-6" :dir="isRTL ? 'rtl' : 'ltr'">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ isRTL ? 'دليل تكامل n8n' : 'n8n Integration Guide' }}</h2>
          <p class="text-gray-500 text-sm mt-1">{{ isRTL ? 'خطوات ربط Wavixa مع n8n واستخدام API لإرسال واستقبال رسائل WhatsApp.' : 'Learn how to connect Wavixa with n8n nodes and triggers for advanced WhatsApp workflows.' }}</p>
        </div>
        <router-link
          to="/n8n"
          class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-50 transition-all shadow-sm active:scale-95"
        >
          <svg class="w-4 h-4" :class="isRTL ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          <span>{{ isRTL ? 'رجوع لـ n8n' : 'Back to n8n' }}</span>
        </router-link>
      </div>

      <!-- Prerequisites -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h3 class="text-lg font-black text-gray-900 mb-4">{{ isRTL ? 'المتطلبات قبل البدء' : 'Prerequisites' }}</h3>
        <p class="text-sm text-gray-600 font-medium mb-4">{{ isRTL ? 'عشان تكامل n8n يشتغل على حسابك، لازم يتوفر التالي:' : 'To enable n8n automation workflows, ensure the following requirements are met:' }}</p>
        <ul class="list-disc list-inside space-y-3 text-sm text-gray-600 font-medium">
          <li><strong>{{ isRTL ? 'جهاز WhatsApp متصل:' : 'Connected WhatsApp Session:' }}</strong> {{ isRTL ? 'لازم يكون عندك جلسة WhatsApp متصلة على Wavixa من صفحة الأجهزة.' : 'Ensure you have an active connected WhatsApp session from the Devices page.' }}</li>
          <li><strong>{{ isRTL ? 'رصيد رسائل كافي:' : 'Sufficient Message Credits:' }}</strong> {{ isRTL ? 'تأكد إن عندك رصيد متبقي في الاشتراك.' : 'Make sure your account has message credits available.' }}</li>
          <li><strong>{{ isRTL ? 'مفتاح API صالح:' : 'Valid API Key:' }}</strong> {{ isRTL ? 'أنشئ مفتاح API من صفحة مفاتيح API.' : 'Create an API key with appropriate permissions from the API Keys page.' }}</li>
          <li><strong>{{ isRTL ? 'حساب n8n:' : 'n8n Instance:' }}</strong> {{ isRTL ? 'لازم يكون عندك n8n شغال (سحابي أو محلي).' : 'A running n8n instance (Cloud or Self-Hosted).' }}</li>
        </ul>
      </div>

      <!-- Credentials -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h3 class="text-lg font-black text-gray-900 mb-4">{{ isRTL ? '١. إعداد مفاتيح API' : '1. API Credentials Setup' }}</h3>
        <ol class="list-decimal list-inside space-y-3 text-sm text-gray-600 font-medium">
          <li>{{ isRTL ? 'انتقل إلى مفاتيح API وقم بإنشاء مفتاح جديد.' : 'Navigate to API Keys and generate a new key.' }}</li>
          <li>{{ isRTL ? 'اختر الصلاحيات المطلوبة: إرسال رسائل، قراءة الحالة، الجلسات.' : 'Select required scopes: send messages, check status, read sessions.' }}</li>
          <li>{{ isRTL ? 'انسخ المفتاح واحفظه في مكان آمن.' : 'Copy and store your API key securely.' }}</li>
          <li>{{ isRTL ? 'في n8n، أضف Credential جديد من نوع Header Auth وضع المفتاح.' : 'In n8n, create a Header Auth credential with your API key.' }}</li>
        </ol>
      </div>

      <!-- Base URL -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h3 class="text-lg font-black text-gray-900 mb-4">{{ isRTL ? '٢. عنوان الأساس (Base URL)' : '2. API Base URL' }}</h3>
        <div class="bg-gray-50 border border-gray-100 rounded-2xl p-4 font-mono text-indigo-600 text-sm font-bold" dir="ltr">
          {{ baseUrl }}
        </div>
      </div>

      <!-- Authentication -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h3 class="text-lg font-black text-gray-900 mb-4">{{ isRTL ? '٣. المصادقة' : '3. Authentication Header' }}</h3>
        <p class="text-sm text-gray-600 font-medium mb-4">{{ isRTL ? 'أرسل المفتاح في ترويسة X-API-Key مع كل طلب.' : 'Pass your API key in the X-API-Key request header.' }}</p>
        <div class="bg-gray-900 rounded-2xl p-4 overflow-x-auto">
          <code class="text-sm text-green-400 font-mono" dir="ltr">X-API-Key: wav_live_...</code>
        </div>
      </div>

      <!-- Endpoints -->
      <div v-for="endpoint in endpoints" :key="endpoint.id" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <div class="flex items-center gap-3 mb-4">
          <span class="px-3 py-1 rounded-lg text-xs font-bold text-white" :class="endpoint.method === 'GET' ? 'bg-blue-500' : 'bg-indigo-600'">{{ endpoint.method }}</span>
          <h3 class="text-lg font-black text-gray-900">{{ endpoint.title }}</h3>
        </div>
        <p class="text-sm text-gray-600 font-medium mb-4">{{ endpoint.description }}</p>

        <div class="bg-gray-50 border border-gray-100 rounded-2xl p-4 font-mono text-indigo-600 text-sm font-bold mb-4" dir="ltr">
          {{ endpoint.url }}
        </div>

        <div v-if="endpoint.parameters" class="mb-4">
          <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-2">{{ isRTL ? 'المعاملات' : 'Parameters' }}</h4>
          <table class="w-full text-sm border-collapse" :class="isRTL ? 'text-right' : 'text-left'">
            <thead>
              <tr class="border-b border-gray-100">
                <th class="py-2 text-gray-500">{{ isRTL ? 'الاسم' : 'Parameter' }}</th>
                <th class="py-2 text-gray-500">{{ isRTL ? 'النوع' : 'Type' }}</th>
                <th class="py-2 text-gray-500">{{ isRTL ? 'مطلوب' : 'Required' }}</th>
                <th class="py-2 text-gray-500">{{ isRTL ? 'الوصف' : 'Description' }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="param in endpoint.parameters" :key="param.name" class="border-b border-gray-50">
                <td class="py-2 font-bold text-gray-900 font-mono" dir="ltr">{{ param.name }}</td>
                <td class="py-2 text-gray-500 font-mono">{{ param.type }}</td>
                <td class="py-2 text-gray-500">{{ param.required ? (isRTL ? 'نعم' : 'Yes') : (isRTL ? 'لا' : 'No') }}</td>
                <td class="py-2 text-gray-500">{{ param.description }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="endpoint.example" class="bg-gray-900 rounded-2xl p-4 overflow-x-auto">
          <pre class="text-xs text-green-400 font-mono leading-relaxed" dir="ltr">{{ endpoint.example }}</pre>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { computed } from 'vue';
import { t, isRTL } from '@/i18n';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

export default {
  name: 'N8nDocs',
  components: { DashboardLayout },
  setup() {
    const baseUrl = window.location.origin;

    const endpoints = computed(() => isRTL.value ? [
      {
        id: 'send-message',
        method: 'POST',
        title: 'إرسال رسالة نصية',
        description: 'إرسال رسالة واتساب نصية إلى مستلم واحد.',
        url: `${baseUrl}/api/public/send-message`,
        parameters: [
          { name: 'to', type: 'string', required: true, description: 'رقم هاتف المستلم بصيغة دولية (مثلاً 201012345678)' },
          { name: 'message', type: 'string', required: true, description: 'نص الرسالة المطلوب إرسالها' },
        ],
        example: `curl -X POST "${baseUrl}/api/public/send-message" \\
  -H "X-API-Key: wav_live_..." \\
  -H "Content-Type: application/json" \\
  -d '{"to": "201012345678", "message": "مرحباً بك من n8n!"}'`,
      },
      {
        id: 'check-status',
        method: 'GET',
        title: 'فحص حالة الجلسة',
        description: 'التحقق من حالة اتصال WhatsApp والرصيد المتاح.',
        url: `${baseUrl}/api/public/status`,
        example: `curl "${baseUrl}/api/public/status" \\
  -H "X-API-Key: wav_live_..."`,
      },
    ] : [
      {
        id: 'send-message',
        method: 'POST',
        title: 'Send Text Message',
        description: 'Dispatch a WhatsApp text message to a single recipient.',
        url: `${baseUrl}/api/public/send-message`,
        parameters: [
          { name: 'to', type: 'string', required: true, description: 'Recipient phone number with country code (e.g. 14155552671)' },
          { name: 'message', type: 'string', required: true, description: 'Text message body to dispatch' },
        ],
        example: `curl -X POST "${baseUrl}/api/public/send-message" \\
  -H "X-API-Key: wav_live_..." \\
  -H "Content-Type: application/json" \\
  -d '{"to": "14155552671", "message": "Hello from n8n automation!"}'`,
      },
      {
        id: 'check-status',
        method: 'GET',
        title: 'Check Session Status',
        description: 'Check WhatsApp device connection health and remaining credits.',
        url: `${baseUrl}/api/public/status`,
        example: `curl "${baseUrl}/api/public/status" \\
  -H "X-API-Key: wav_live_..."`,
      },
    ]);

    return { baseUrl, endpoints, t, isRTL };
  },
};
</script>
