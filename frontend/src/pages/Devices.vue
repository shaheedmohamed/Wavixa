<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('devices_page.title') }}</h2>
          <p class="text-gray-500 text-sm mt-1">{{ t('devices_page.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
          <router-link to="/whatsapp-connect" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-sm active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>{{ t('devices_page.connect_new') }}</span>
          </router-link>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
          </div>
          <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ t('devices_page.total_devices') }}</p>
            <p class="text-2xl font-black text-gray-900 leading-none">{{ totalDevices }}</p>
          </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          </div>
          <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ t('devices_page.active_devices') }}</p>
            <p class="text-2xl font-black text-green-600 leading-none">{{ activeDevices }}</p>
          </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
          </div>
          <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ t('devices_page.sent_messages') }}</p>
            <p class="text-2xl font-black text-purple-600 leading-none">{{ messagesSent }}</p>
          </div>
        </div>
      </div>

      <!-- Device Card -->
      <div v-if="whatsappStatus === 'connected'" class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-8">
          <div class="flex flex-col md:flex-row md:items-start justify-between gap-6">
            <div class="flex items-start gap-6">
              <div class="w-16 h-16 bg-green-50 text-green-600 rounded-2xl flex items-center justify-center text-3xl shadow-sm border border-green-100">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
              </div>
              <div class="space-y-4">
                <div>
                  <div class="flex items-center gap-3 mb-1">
                    <h3 class="text-xl font-black text-gray-900">{{ t('devices_page.connected_card_title') }}</h3>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-600 uppercase tracking-widest animate-pulse">{{ t('devices_page.active_now') }}</span>
                  </div>
                  <p class="text-sm text-gray-500 font-medium">{{ t('devices_page.primary_device') }}</p>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-12 gap-y-3">
                  <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ t('devices_page.phone_number') }}</span>
                    <span class="text-sm font-black text-gray-900 font-mono" dir="ltr">{{ phoneNumber }}</span>
                  </div>
                  <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ t('devices_page.connected_at') }}</span>
                    <span class="text-sm font-black text-gray-900">{{ formatDate(connectedAt) }}</span>
                  </div>
                  <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ t('devices_page.last_activity') }}</span>
                    <span class="text-sm font-black text-gray-900">{{ formatTime(lastActivity) }}</span>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="flex items-center gap-3">
              <button
                @click="showDeleteConfirm = true"
                class="px-4 py-2 bg-red-50 text-red-600 text-sm font-bold rounded-xl hover:bg-red-100 transition-all active:scale-95 border border-red-100"
              >
                {{ t('devices_page.disconnect') }}
              </button>
            </div>
          </div>

          <!-- Delete Confirmation -->
          <div v-if="showDeleteConfirm" class="mt-8 p-6 bg-red-50 rounded-2xl border border-red-100 animate-in slide-in-from-top-4 duration-300" :dir="isRTL ? 'rtl' : 'ltr'">
            <div class="flex items-center gap-3 mb-4 text-red-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
              <h4 class="font-black">{{ t('devices_page.disconnect_confirm_title') }}</h4>
            </div>
            <p class="text-sm text-red-700 font-medium mb-6">{{ t('devices_page.disconnect_confirm_desc') }}</p>
            <div class="flex gap-3">
              <button
                @click="deleteSession"
                :disabled="isDeleting"
                class="px-6 py-2.5 bg-red-600 text-white text-sm font-bold rounded-xl hover:bg-red-700 transition-all active:scale-95 disabled:opacity-50"
              >
                {{ isDeleting ? t('common.loading') : t('devices_page.disconnect_now') }}
              </button>
              <button
                @click="showDeleteConfirm = false"
                class="px-6 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-50 transition-all active:scale-95"
              >
                {{ t('devices_page.disconnect_cancel') }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- No Device Connected -->
      <div v-else class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden p-12 text-center">
        <div class="w-20 h-20 bg-gray-50 text-gray-300 rounded-full flex items-center justify-center mx-auto mb-6">
          <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
        </div>
        <h3 class="text-2xl font-black text-gray-900 mb-2">{{ t('devices_page.disconnected_card_title') }}</h3>
        <p class="text-gray-500 font-medium mb-8 max-w-md mx-auto">{{ t('devices_page.disconnected_card_desc') }}</p>
        <router-link
          to="/whatsapp-connect"
          class="inline-flex items-center gap-2 px-8 py-3 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200 active:scale-95"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
          <span>{{ t('devices_page.connect_now') }}</span>
        </router-link>
      </div>



      <!-- Quick Actions Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <router-link v-for="action in quickActions" :key="action.path" :to="action.path" class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm hover:border-indigo-200 hover:shadow-md transition-all group">
          <div :class="['w-10 h-10 rounded-xl flex items-center justify-center mb-4 transition-colors', action.bg, action.color]">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="action.icon"></svg>
          </div>
          <h4 class="text-sm font-black text-gray-900 mb-1">{{ action.title }}</h4>
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter">{{ action.desc }}</p>
        </router-link>
      </div>

      <!-- Alerts -->
      <div v-if="error" class="bg-red-50 border border-red-100 rounded-2xl p-4 flex items-center gap-3 text-red-600 animate-in slide-in-from-bottom-2 duration-300">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <p class="text-sm font-bold">{{ error }}</p>
      </div>
      <div v-if="message" class="bg-green-50 border border-green-100 rounded-2xl p-4 flex items-center gap-3 text-green-600 animate-in slide-in-from-bottom-2 duration-300">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
        <p class="text-sm font-bold">{{ message }}</p>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import metaTest from '@/services/metaTest';
import { openMetaEmbeddedSignup } from '@/services/metaEmbeddedSignup';
import auth from '@/services/auth';
import { t, isRTL } from '@/i18n';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

export default {
  name: 'Devices',
  components: { DashboardLayout },
  setup() {
    const router = useRouter();
    const whatsappStatus = ref('disconnected');
    const phoneNumber = ref('');
    const connectedAt = ref(null);
    const lastActivity = ref(null);
    const totalDevices = ref(0);
    const activeDevices = ref(0);
    const messagesSent = ref(0);
    const showDeleteConfirm = ref(false);
    const isDeleting = ref(false);
    const isMetaLoading = ref(false);
    const error = ref('');
    const message = ref('');
    const metaNumbers = ref([]);

    const quickActions = computed(() => [
      { 
        title: isRTL.value ? 'ربط جديد' : 'Connect Device', 
        desc: isRTL.value ? 'إضافة حساب واتساب آخر' : 'Add another WhatsApp account', 
        path: '/whatsapp-connect', 
        bg: 'bg-indigo-50', 
        color: 'text-indigo-600',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 00 5.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />'
      },
      { 
        title: isRTL.value ? 'إرسال رسالة' : 'Send Message', 
        desc: isRTL.value ? 'إرسال سريع لجهة اتصال' : 'Quick send to contact', 
        path: '/send-message', 
        bg: 'bg-blue-50', 
        color: 'text-blue-600',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />'
      },
      { 
        title: isRTL.value ? 'سجل الرسائل' : 'Message Logs', 
        desc: isRTL.value ? 'عرض كافة الرسائل' : 'Inspect message records', 
        path: '/messages', 
        bg: 'bg-green-50', 
        color: 'text-green-600',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />'
      },
      { 
        title: isRTL.value ? 'الإحصائيات' : 'Analytics', 
        desc: isRTL.value ? 'تحليل الأداء العام' : 'Overall performance overview', 
        path: '/dashboard', 
        bg: 'bg-orange-50', 
        color: 'text-orange-600',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />'
      },
    ]);

    onMounted(async () => {
      const userData = auth.getUser();
      if (!userData) {
        router.push('/login');
        return;
      }

      const query = new URLSearchParams(window.location.search);
      if (query.get('meta_whatsapp') === 'connected') {
        message.value = isRTL.value ? 'تم ربط WhatsApp الرسمي بنجاح' : 'Official WhatsApp connected successfully.';
      } else if (query.get('meta_whatsapp') === 'error') {
        error.value = query.get('message') || (isRTL.value ? 'فشل ربط WhatsApp الرسمي' : 'Failed to connect Official WhatsApp.');
      } else if (query.get('meta_whatsapp') === 'denied') {
        error.value = isRTL.value ? 'تم رفض صلاحيات Meta' : 'Meta permissions denied.';
      }

      await loadDeviceData();
      setInterval(loadDeviceData, 15000);
    });

    const connectMeta = async () => {
      try {
        isMetaLoading.value = true;
        error.value = '';
        message.value = '';

        const cfgRes = await metaTest.embeddedSignupConfig().catch(() => null);
        const cfg = cfgRes?.data;

        if (cfg?.embedded_signup_available) {
          const result = await openMetaEmbeddedSignup({
            appId: cfg.app_id,
            apiVersion: cfg.api_version,
            configId: cfg.config_id,
            featureType: cfg.feature_type,
            sessionInfoVersion: cfg.session_info_version,
          });

          const res = await metaTest.embeddedSignupCallback({
            code: result.code,
            waba_id: result.sessionInfo?.waba_id || null,
            phone_number_id: result.sessionInfo?.phone_number_id || null,
            event: result.event || null,
            session_info: result.sessionInfo || null,
          });

          if (res.success && res.data?.status === 'connected') {
            message.value = isRTL.value ? 'تم ربط WhatsApp الرسمي بنجاح' : 'Official WhatsApp connected successfully.';
            await loadMetaNumbers();
          } else {
            error.value = res.data?.error_message || res.message || (isRTL.value ? 'فشل ربط WhatsApp الرسمي' : 'Failed to connect Official WhatsApp.');
          }
          return;
        }

        const response = await api.get('/meta-test/connect');
        if (response.success && response.data?.url) {
          window.location.href = response.data.url;
        } else {
          error.value = response.message || (isRTL.value ? 'لم يتم الحصول على رابط الربط' : 'Failed to get auth URL.');
        }
      } catch (err) {
        if (err?.code === 'cancelled' || err?.code === 'popup_blocked') {
          message.value = '';
        } else {
          error.value = err.data?.message || (isRTL.value ? 'فشل بدء ربط WhatsApp الرسمي' : 'Failed to start Official WhatsApp connection.');
        }
      } finally {
        isMetaLoading.value = false;
      }
    };

    const loadMetaNumbers = async () => {
      try {
        const response = await api.get('/meta-test/phone-numbers');
        if (response.success) {
          metaNumbers.value = response.data || [];
        }
      } catch (err) {
        // Silent fail if not configured
      }
    };

    const loadDeviceData = async () => {
      try {
        const response = await api.get('/whatsapp/status');
        if (response.success) {
          whatsappStatus.value = response.data.status;
          phoneNumber.value = response.data.phoneNumber || '';
          connectedAt.value = response.data.connectedAt;
          lastActivity.value = response.data.lastActivity;

          totalDevices.value = whatsappStatus.value === 'connected' ? 1 : 0;
          activeDevices.value = whatsappStatus.value === 'connected' ? 1 : 0;
        }

        const statsResponse = await api.get('/whatsapp/dashboard-stats');
        if (statsResponse.success) {
          messagesSent.value = statsResponse.data.totalMessages || 0;
        }
      } catch (err) {
        console.error('Error loading device data:', err);
      }
    };

    const deleteSession = async () => {
      try {
        isDeleting.value = true;
        error.value = '';
        message.value = '';

        const response = await api.post('/whatsapp/delete-session', {});
        if (response.success) {
          message.value = isRTL.value ? 'تم قطع الاتصال وحذف الجلسة بنجاح' : 'Disconnected and deleted session successfully.';
          showDeleteConfirm.value = false;
          whatsappStatus.value = 'disconnected';
          phoneNumber.value = '';
          connectedAt.value = null;
          totalDevices.value = 0;
          activeDevices.value = 0;
        } else {
          error.value = response.message || (isRTL.value ? 'فشل قطع الاتصال' : 'Failed to disconnect');
        }
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل قطع الاتصال' : 'Failed to disconnect');
      } finally {
        isDeleting.value = false;
      }
    };

    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleDateString(isRTL.value ? 'ar-EG' : 'en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    };

    const formatTime = (date) => {
      if (!date) return isRTL.value ? 'لا يوجد نشاط' : 'No activity';
      const now = new Date();
      const then = new Date(date);
      const diff = Math.floor((now - then) / 1000);
      
      if (diff < 60) return isRTL.value ? 'الآن' : 'just now';
      if (diff < 3600) return isRTL.value ? `منذ ${Math.floor(diff / 60)} دقيقة` : `${Math.floor(diff / 60)} mins ago`;
      if (diff < 86400) return isRTL.value ? `منذ ${Math.floor(diff / 3600)} ساعة` : `${Math.floor(diff / 3600)} hrs ago`;
      return isRTL.value ? `منذ ${Math.floor(diff / 86400)} يوم` : `${Math.floor(diff / 86400)} days ago`;
    };

    return {
      whatsappStatus,
      phoneNumber,
      connectedAt,
      lastActivity,
      totalDevices,
      activeDevices,
      messagesSent,
      showDeleteConfirm,
      isDeleting,
      isMetaLoading,
      metaNumbers,
      error,
      message,
      quickActions,
      deleteSession,
      connectMeta,
      formatDate,
      formatTime,
      t,
      isRTL,
    };
  },
};
</script>

<style scoped>
.animate-in {
  animation: animate-in 0.3s ease-out;
}

@keyframes animate-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
