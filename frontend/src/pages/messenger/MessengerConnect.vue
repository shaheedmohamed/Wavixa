<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500 max-w-4xl mx-auto">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('messenger_page.connect') }}</h2>
          <p class="text-gray-500 text-sm mt-1">سجّل دخولك عبر Facebook واختر الصفحة التي تريد ربطها بـ Messenger.</p>
        </div>
        <router-link to="/messenger" class="text-xs font-bold text-gray-500 hover:text-[#0084FF] transition-colors">← العودة</router-link>
      </div>

      <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-8 space-y-8">
        <div v-if="!config.app_id" class="p-4 bg-amber-50 border border-amber-100 rounded-2xl text-amber-800 text-xs font-bold">
          لم يتم تكوين معرّف تطبيق Messenger. يرجى مراجعة إعدادات النظام.
        </div>

        <div v-if="step === 'login'" class="text-center space-y-6 py-8">
          <div class="w-20 h-20 bg-blue-50 text-[#0084FF] rounded-full flex items-center justify-center mx-auto">
            <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.04C6.5 2.04 2 6.24 2 11.5c0 3.12 1.66 5.96 4.28 7.7L5 22l3.28-1.7c.94.26 1.94.4 2.96.42.3-.57.56-1.15.78-1.74-.52.15-1.07.24-1.63.24-3.7 0-6.7-2.84-6.7-6.34 0-3.5 3-6.34 6.7-6.34 3.7 0 6.7 2.84 6.7 6.34 0 1.97-.91 3.73-2.32 4.9.34-1.73.15-3.57-.56-5.23-1.36-3.2-4.6-5.27-8.16-5.27H8.9c.55-.77 1.24-1.45 2.04-2C12.86 4.9 13.43 5 14 5c4.42 0 8 3.58 8 8s-3.58 8-8 8c-.88 0-1.73-.14-2.53-.41L6 23.5l1.34-3.78C4.78 18.18 3 15.15 3 12c0-5.25 4.04-9.5 9-9.5s9 4.25 9 9.5-4.04 9.5-9 9.5c-.6 0-1.18-.06-1.75-.17L8 23.5l1.8-3.2C4.92 19.15 2 15.54 2 11.5 2 6.24 6.5 2.04 12 2.04z"/></svg>
          </div>
          <div class="space-y-1">
            <h3 class="text-xl font-black text-gray-900">سجّل الدخول باستخدام Facebook</h3>
            <p class="text-xs text-gray-500 max-w-md mx-auto">ستنتقل إلى نافذة Meta الرسمية للمصادقة. لن نحفظ كلمة المرور أبداً.</p>
          </div>
          <button
            @click="startOAuth"
            :disabled="!config.app_id || isLoading"
            class="px-8 py-3.5 bg-[#1877F2] hover:bg-[#166fe5] text-white text-sm font-black rounded-2xl transition-all shadow-lg shadow-blue-100 active:scale-95 disabled:opacity-50"
          >
            {{ isLoading ? 'جاري التحميل...' : 'تسجيل الدخول عبر Facebook' }}
          </button>
        </div>

        <div v-if="step === 'pages'" class="space-y-6">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-black text-gray-900">اختر الصفحة / الصفحات</h3>
            <button @click="step = 'login'" class="text-xs font-bold text-gray-500 hover:text-gray-700">تغيير الحساب</button>
          </div>
          <div v-if="availablePages.length === 0" class="p-6 bg-amber-50 border border-amber-100 rounded-2xl text-amber-800 text-xs font-bold">
            لا توجد صفحات تديرها مرتبطة بهذا الحساب. تأكد من أن لديك صلاحية Admin Editor أو Admin على الصفحة.
          </div>
          <div v-else class="space-y-3">
            <div v-for="page in availablePages" :key="page.id" class="flex items-center gap-4 p-4 rounded-2xl border border-gray-100 hover:bg-gray-50/50 transition-all">
              <input
                :id="`page-${page.id}`"
                v-model="selectedPages"
                :value="page"
                type="checkbox"
                class="w-5 h-5 rounded border-gray-300 text-[#0084FF] focus:ring-[#0084FF]"
              />
              <label :for="`page-${page.id}`" class="flex-1 cursor-pointer">
                <p class="text-sm font-black text-gray-900">{{ page.name }}</p>
                <p class="text-[10px] font-bold text-gray-400 font-mono" dir="ltr">{{ page.id }}</p>
              </label>
            </div>
          </div>
          <button
            @click="connectPages"
            :disabled="selectedPages.length === 0 || isLoading"
            class="w-full py-3.5 bg-[#0084FF] hover:bg-[#0073E6] disabled:opacity-50 text-white text-sm font-black rounded-2xl transition-all shadow-md active:scale-95"
          >
            {{ isLoading ? 'جاري الربط...' : `ربط ${selectedPages.length} صفحة` }}
          </button>
        </div>

        <div v-if="step === 'done'" class="text-center space-y-6 py-8">
          <div class="w-20 h-20 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mx-auto">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
          </div>
          <div class="space-y-1">
            <h3 class="text-xl font-black text-gray-900">تم ربط الصفحة بنجاح!</h3>
            <p class="text-xs text-gray-500">يمكنك الآن استقبال وإرسال رسائل Messenger.</p>
          </div>
          <router-link to="/messenger" class="inline-block px-8 py-3.5 bg-[#0084FF] hover:bg-[#0073E6] text-white text-sm font-black rounded-2xl transition-all shadow-md">الذهاب لـ Messenger</router-link>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import api from '@/services/api';
import { t } from '@/i18n';

export default {
  name: 'MessengerConnect',
  components: { DashboardLayout },
  setup() {
    const config = ref({ app_id: '', api_version: 'v21.0', scopes: [] });
    const step = ref('login');
    const isLoading = ref(false);
    const availablePages = ref([]);
    const selectedPages = ref([]);
    const accessToken = ref('');
    let popup = null;

    const loadConfig = async () => {
      try {
        const res = await api.get('/messenger/config');
        if (res.success) config.value = res.data;
      } catch (err) {
        console.error('Failed to load Messenger config:', err);
      }
    };

    const startOAuth = async () => {
      if (!config.value.app_id) return;
      try {
        isLoading.value = true;
        const res = await api.get('/messenger/auth');
        if (!res.success) {
          alert(res.message || 'فشل بدء OAuth.');
          return;
        }
        const { url } = res.data;
        const width = 600;
        const height = 700;
        const left = (window.innerWidth - width) / 2;
        const top = (window.innerHeight - height) / 2;
        popup = window.open(url, 'messengerOAuth', `width=${width},height=${height},top=${top},left=${left},scrollbars=yes`);
      } catch (err) {
        console.error('OAuth start failed:', err);
        alert(err.data?.message || 'فشل فتح نافذة Facebook.');
      } finally {
        isLoading.value = false;
      }
    };

    const handleMessage = (event) => {
      if (event.origin !== window.location.origin) return;
      if (!event.data || event.data.type !== 'messenger-oauth') return;

      const { payload } = event.data;
      if (payload && payload.success) {
        accessToken.value = payload.data.access_token;
        availablePages.value = payload.data.pages || [];
        step.value = 'pages';
      } else {
        alert(payload?.message || 'فشل تسجيل الدخول عبر Facebook.');
      }
      isLoading.value = false;
    };

    const connectPages = async () => {
      try {
        isLoading.value = true;
        const pages = selectedPages.value.map(p => ({
          id: p.id,
          name: p.name,
          access_token: p.access_token,
        }));
        await api.post('/messenger/connect', {
          access_token: accessToken.value,
          pages,
        });
        step.value = 'done';
      } catch (err) {
        console.error('Failed to connect pages:', err);
        alert(err.data?.message || 'فشل ربط الصفحات.');
      } finally {
        isLoading.value = false;
      }
    };

    onMounted(() => {
      loadConfig();
      window.addEventListener('message', handleMessage);
    });

    onUnmounted(() => {
      window.removeEventListener('message', handleMessage);
    });

    return { config, step, isLoading, availablePages, selectedPages, startOAuth, connectPages, t };
  },
};
</script>
