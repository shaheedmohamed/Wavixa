<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ isRTL ? 'إحصائيات واتساب' : 'WhatsApp Overview' }}</h2>
          <p class="text-gray-500 text-sm mt-1">{{ isRTL ? 'مراقبة حالة الاتصال وأداء الرسائل الفوري' : 'Monitor live connection health and real-time message telemetry' }}</p>
        </div>
        <div class="flex items-center gap-3">
          <div :class="['px-4 py-2 rounded-2xl text-xs font-black uppercase tracking-widest flex items-center gap-2 border shadow-sm transition-all', whatsappStatus === 'connected' ? 'bg-green-50 text-green-600 border-green-100' : (whatsappStatus === 'pending' ? 'bg-yellow-50 text-yellow-600 border-yellow-100 animate-pulse' : 'bg-red-50 text-red-600 border-red-100')]">
            <div :class="['w-2 h-2 rounded-full', whatsappStatus === 'connected' ? 'bg-green-600 shadow-[0_0_8px_rgba(22,163,74,0.5)]' : (whatsappStatus === 'pending' ? 'bg-yellow-600' : 'bg-red-600')]"></div>
            <span>{{ statusLabel }}</span>
          </div>
        </div>
      </div>

      <!-- Quick Connection Card (If not connected) -->
      <div v-if="whatsappStatus !== 'connected'" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-8 text-center space-y-6">
        <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
        </div>
        <div class="max-w-md mx-auto space-y-2">
          <h3 class="text-lg font-black text-gray-900">{{ isRTL ? 'ربط واتساب مطلوب' : 'WhatsApp Connection Required' }}</h3>
          <p class="text-sm text-gray-500 font-medium">{{ isRTL ? 'تحتاج إلى ربط جهازك لتتمكن من رؤية الإحصائيات وإرسال الرسائل.' : 'You need to connect your WhatsApp device to view analytics and send messages.' }}</p>
        </div>
        <router-link to="/whatsapp-connect" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-2xl font-black text-sm transition-all shadow-lg shadow-indigo-100 active:scale-95">
          <span>{{ isRTL ? 'انتقل لصفحة الربط' : 'Go to Pairing Page' }}</span>
          <svg class="w-4 h-4" :class="isRTL ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
        </router-link>
      </div>

      <!-- Stats Grid -->
      <div v-if="whatsappStatus === 'connected'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 flex items-center gap-5 group hover:border-indigo-100 transition-all">
          <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" /></svg>
          </div>
          <div>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">{{ isRTL ? 'رسائل اليوم' : 'Today\'s Messages' }}</p>
            <h4 class="text-2xl font-black text-gray-900">{{ todayMessages }}</h4>
          </div>
        </div>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 flex items-center gap-5 group hover:border-indigo-100 transition-all">
          <div class="w-14 h-14 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
          </div>
          <div>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">{{ isRTL ? 'رسائل الشهر' : 'Monthly Messages' }}</p>
            <h4 class="text-2xl font-black text-gray-900">{{ monthMessages }}</h4>
          </div>
        </div>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 flex items-center gap-5 group hover:border-indigo-100 transition-all">
          <div class="w-14 h-14 bg-green-50 text-green-600 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          </div>
          <div>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">{{ isRTL ? 'مدة الاتصال' : 'Uptime' }}</p>
            <h4 class="text-2xl font-black text-gray-900">{{ connectedDays }} <span class="text-xs font-bold text-gray-400">{{ t('common.days') }}</span></h4>
          </div>
        </div>
      </div>

      <div v-if="whatsappStatus === 'connected'" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Connection Info -->
        <div class="lg:col-span-1 space-y-6">
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-50 bg-gray-50/30">
              <h3 class="text-sm font-black text-gray-900">{{ isRTL ? 'تفاصيل الاتصال' : 'Connection Details' }}</h3>
            </div>
            <div class="p-6 space-y-6">
              <div class="space-y-4">
                <div class="flex justify-between items-center">
                  <span class="text-[10px] font-bold text-gray-400 uppercase">{{ t('devices_page.phone_number') }}</span>
                  <span class="text-sm font-black text-gray-900 font-mono" dir="ltr">{{ phoneNumber }}</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-[10px] font-bold text-gray-400 uppercase">{{ t('devices_page.connected_at') }}</span>
                  <span class="text-sm font-bold text-gray-600">{{ formatDate(connectedAt) }}</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-[10px] font-bold text-gray-400 uppercase">{{ t('devices_page.last_activity') }}</span>
                  <span class="text-sm font-bold text-gray-600">{{ formatDate(lastActivity) }}</span>
                </div>
              </div>

              <div class="pt-4 space-y-3">
                <button @click="disconnectWhatsApp" :disabled="isDisconnecting" class="w-full bg-red-50 text-red-600 px-6 py-3 rounded-2xl font-black text-xs hover:bg-red-100 transition-all border border-red-100">
                  {{ isDisconnecting ? t('common.loading') : (isRTL ? 'قطع الاتصال مؤقتاً' : 'Disconnect Session') }}
                </button>
                <button @click="deleteSession" :disabled="isDeleting" class="w-full bg-white text-gray-400 px-6 py-3 rounded-2xl font-black text-[10px] hover:text-red-500 transition-all">
                  {{ isDeleting ? t('common.loading') : (isRTL ? 'حذف الجلسة نهائياً' : 'Delete Session Permanently') }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Messages -->
        <div class="lg:col-span-2 space-y-6">
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-50 bg-gray-50/30 flex items-center justify-between">
              <h3 class="text-sm font-black text-gray-900">{{ isRTL ? 'آخر الرسائل' : 'Recent Messages' }}</h3>
              <router-link to="/messages" class="text-[10px] font-black text-indigo-600 hover:underline">{{ isRTL ? 'عرض الكل' : 'View All' }}</router-link>
            </div>
            <div class="p-6">
              <div v-if="recentMessages.length === 0" class="py-12 text-center">
                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                  <svg class="w-8 h-8 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
                </div>
                <p class="text-sm font-bold text-gray-400">{{ isRTL ? 'لا يوجد رسائل حديثة' : 'No recent messages' }}</p>
              </div>
              <div v-else class="space-y-4">
                <div v-for="msg in recentMessages" :key="msg.id" class="p-4 rounded-2xl bg-gray-50 border border-gray-50 hover:bg-white hover:border-indigo-100 transition-all group">
                  <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                      <span :class="['w-2 h-2 rounded-full', msg.direction === 'incoming' ? 'bg-green-500' : 'bg-indigo-500']"></span>
                      <span class="text-xs font-black text-gray-900 font-mono" dir="ltr">{{ msg.from || msg.to }}</span>
                    </div>
                    <span class="text-[10px] font-bold text-gray-400">{{ formatTime(msg.created_at) }}</span>
                  </div>
                  <p class="text-xs text-gray-600 leading-relaxed line-clamp-2">{{ msg.message }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Alerts -->
      <div v-if="error" class="fixed bottom-8 z-50 bg-red-600 text-white px-6 py-3 rounded-2xl font-black text-sm shadow-2xl animate-in" :class="isRTL ? 'right-8 slide-in-from-right-4' : 'left-8 slide-in-from-left-4'">
        {{ error }}
      </div>
      <div v-if="message" class="fixed bottom-8 z-50 bg-green-600 text-white px-6 py-3 rounded-2xl font-black text-sm shadow-2xl animate-in" :class="isRTL ? 'right-8 slide-in-from-right-4' : 'left-8 slide-in-from-left-4'">
        {{ message }}
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import auth from '@/services/auth';
import { t, isRTL } from '@/i18n';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

export default {
  name: 'WhatsAppDashboard',
  components: { DashboardLayout },
  setup() {
    const router = useRouter();
    const user = ref(null);
    const whatsappStatus = ref('disconnected');
    const phoneNumber = ref('');
    const connectedAt = ref(null);
    const lastActivity = ref(null);
    const qrCode = ref('');
    const todayMessages = ref(0);
    const monthMessages = ref(0);
    const recentMessages = ref([]);
    const isConnecting = ref(false);
    const isDisconnecting = ref(false);
    const isDeleting = ref(false);
    const error = ref('');
    const message = ref('');

    const statusLabel = computed(() => {
      const labels = {
        connected: isRTL.value ? 'متصل' : 'Connected',
        pending: isRTL.value ? 'في انتظار المسح' : 'Waiting for scan',
        disconnected: isRTL.value ? 'غير متصل' : 'Disconnected',
      };
      return labels[whatsappStatus.value] || whatsappStatus.value;
    });

    const connectedDays = computed(() => {
      if (!connectedAt.value) return 0;
      const connected = new Date(connectedAt.value);
      const now = new Date();
      return Math.floor((now - connected) / (1000 * 60 * 60 * 24));
    });

    onMounted(async () => {
      const userData = auth.getUser();
      if (!userData) {
        router.push('/login');
        return;
      }
      user.value = userData;
      await Promise.all([loadWhatsAppStatus(), loadDashboardStats()]);
      setInterval(loadWhatsAppStatus, 5000);
    });

    const loadWhatsAppStatus = async () => {
      try {
        const response = await api.get('/whatsapp/status');
        if (response.success) {
          const data = response.data;
          whatsappStatus.value = data.status;
          phoneNumber.value = data.phoneNumber || '';
          connectedAt.value = data.connectedAt;
          lastActivity.value = data.lastActivity;
          if (data.qr) qrCode.value = data.qr;
        }
      } catch (err) {
        console.error('Error loading WhatsApp status:', err);
      }
    };

    const loadDashboardStats = async () => {
      try {
        const response = await api.get('/whatsapp/dashboard-stats');
        if (response.success) {
          const data = response.data;
          todayMessages.value = data.todayMessages || 0;
          monthMessages.value = data.monthMessages || 0;
          recentMessages.value = data.recentMessages || [];
        }
      } catch (err) {
        console.error('Error loading dashboard stats:', err);
      }
    };

    const disconnectWhatsApp = async () => {
      if (!confirm(isRTL.value ? 'هل أنت متأكد من رغبتك في قطع الاتصال؟' : 'Are you sure you want to disconnect?')) return;
      try {
        isDisconnecting.value = true;
        error.value = '';
        message.value = '';
        const response = await api.post('/whatsapp/disconnect', {});
        if (response.success) {
          message.value = isRTL.value ? 'تم قطع الاتصال بنجاح' : 'Disconnected successfully';
          await loadWhatsAppStatus();
        }
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل قطع الاتصال' : 'Failed to disconnect');
      } finally {
        isDisconnecting.value = false;
      }
    };

    const deleteSession = async () => {
      if (!confirm(isRTL.value ? 'سيتم حذف كافة بيانات الجلسة، هل أنت متأكد؟' : 'All session credentials will be permanently erased. Proceed?')) return;
      try {
        isDeleting.value = true;
        error.value = '';
        message.value = '';
        const response = await api.post('/whatsapp/delete-session', {});
        if (response.success) {
          message.value = isRTL.value ? 'تم حذف الجلسة بنجاح' : 'Session deleted successfully';
          await loadWhatsAppStatus();
        }
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل حذف الجلسة' : 'Failed to delete session');
      } finally {
        isDeleting.value = false;
      }
    };

    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleDateString(isRTL.value ? 'ar-EG' : 'en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    };

    const formatTime = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleTimeString(isRTL.value ? 'ar-EG' : 'en-US', { hour: '2-digit', minute: '2-digit' });
    };

    return {
      whatsappStatus,
      statusLabel,
      phoneNumber,
      connectedAt,
      lastActivity,
      todayMessages,
      monthMessages,
      recentMessages,
      connectedDays,
      isDisconnecting,
      isDeleting,
      error,
      message,
      disconnectWhatsApp,
      deleteSession,
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
