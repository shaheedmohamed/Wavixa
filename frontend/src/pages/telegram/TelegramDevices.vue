<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="w-2.5 h-2.5 rounded-full bg-[#229ED9]"></span>
            <h2 class="text-2xl font-bold text-gray-900 tracking-tight">أجهزة وجلسات Telegram</h2>
          </div>
          <p class="text-gray-500 text-sm">ربط رقم حسابك في Telegram عبر رمز الـ QR مثل WhatsApp Web وإدارته بالكامل.</p>
        </div>
        <div v-if="telegramStatus !== 'connected'" class="flex items-center gap-3">
          <router-link
            to="/telegram/connect"
            class="flex items-center gap-2 px-5 py-2.5 bg-[#229ED9] hover:bg-[#1c8ec4] text-white text-sm font-bold rounded-2xl transition-all shadow-md active:scale-95"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>ربط حساب Telegram بالـ QR</span>
          </router-link>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-sky-50 text-[#229ED9] rounded-2xl flex items-center justify-center">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.75-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
          </div>
          <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">حالة الحساب</p>
            <p class="text-xl font-black" :class="telegramStatus === 'connected' ? 'text-emerald-600' : 'text-slate-400'">
              {{ telegramStatus === 'connected' ? 'متصل ونشط' : 'غير متصل' }}
            </p>
          </div>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
          </div>
          <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">رقم الهاتف المرتبط</p>
            <p class="text-lg font-black text-gray-900 font-mono" dir="ltr">{{ phoneNumber || 'لا يوجد' }}</p>
          </div>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
          </div>
          <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">الرسائل الصادرة</p>
            <p class="text-2xl font-black text-indigo-600 leading-none">{{ messagesSent }}</p>
          </div>
        </div>
      </div>

      <!-- Connected Device Card -->
      <div v-if="telegramStatus === 'connected'" class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-8">
          <div class="flex flex-col md:flex-row md:items-start justify-between gap-6">
            <div class="flex items-start gap-6">
              <div class="w-16 h-16 bg-sky-50 text-[#229ED9] rounded-2xl flex items-center justify-center text-3xl shadow-sm border border-sky-100 flex-shrink-0">
                <svg class="w-9 h-9" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.75-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
              </div>
              <div class="space-y-4">
                <div>
                  <div class="flex items-center gap-3 mb-1">
                    <h3 class="text-xl font-black text-gray-900">{{ accountName || 'حساب Telegram' }}</h3>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 uppercase tracking-widest animate-pulse border border-emerald-200/60">
                      متصل ونشط الآن
                    </span>
                  </div>
                  <p class="text-xs font-bold text-sky-600" dir="ltr">{{ username ? '@' + username : 'حساب شخصي' }}</p>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-10 gap-y-3">
                  <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">رقم الهاتف</span>
                    <span class="text-sm font-black text-gray-900 font-mono" dir="ltr">{{ phoneNumber }}</span>
                  </div>
                  <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">تاريخ الربط</span>
                    <span class="text-sm font-black text-gray-900">{{ formatDate(connectedAt) }}</span>
                  </div>
                  <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">آخر نشاط</span>
                    <span class="text-sm font-black text-gray-900">{{ formatTime(lastActivity) }}</span>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="flex items-center gap-3">
              <router-link
                to="/telegram/send-message"
                class="px-4 py-2.5 bg-[#229ED9] hover:bg-[#1c8ec4] text-white text-xs font-bold rounded-xl transition-all shadow-xs"
              >
                إرسال رسالة
              </router-link>
              <button
                @click="showDeleteConfirm = true"
                class="px-4 py-2.5 bg-red-50 text-red-600 text-xs font-bold rounded-xl hover:bg-red-100 transition-all border border-red-100"
              >
                فصل الحساب
              </button>
            </div>
          </div>

          <!-- Disconnect Confirmation -->
          <div v-if="showDeleteConfirm" class="mt-8 p-6 bg-red-50 rounded-2xl border border-red-100 animate-in slide-in-from-top-4 duration-300">
            <div class="flex items-center gap-3 mb-2 text-red-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
              <h4 class="font-black text-sm">تأكيد فصل حساب Telegram</h4>
            </div>
            <p class="text-xs text-red-700 font-medium mb-4">هل أنت متأكد من رغبتك في فصل حساب Telegram؟ سيتوقف إرسال الرسائل والردود التلقائية حتى تعيد ربطه مجدداً بالـ QR.</p>
            <div class="flex gap-3">
              <button
                @click="disconnectAccount"
                :disabled="isDisconnecting"
                class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl transition-all shadow-xs"
              >
                {{ isDisconnecting ? 'جاري الفصل...' : 'نعم، افصل الحساب الآن' }}
              </button>
              <button
                @click="showDeleteConfirm = false"
                class="px-5 py-2 bg-white text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-50 border border-gray-200 transition-colors"
              >
                إلغاء
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Disconnected State -->
      <div v-else class="bg-white rounded-3xl border border-gray-100 p-12 text-center shadow-sm max-w-2xl mx-auto space-y-6">
        <div class="w-20 h-20 bg-sky-50 text-[#229ED9] rounded-3xl flex items-center justify-center mx-auto shadow-2xs">
          <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.75-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
        </div>
        <div class="space-y-2">
          <h3 class="text-xl font-black text-gray-900">حساب Telegram غير متصل</h3>
          <p class="text-xs sm:text-sm text-gray-500 max-w-md mx-auto leading-relaxed">
            اربط رقمك وحسابك في Telegram عبر مسح رمز الـ QR خلال ثوانٍ من تطبيق Telegram على هاتفك، دون الحاجة لإنشاء أو إعداد بوتات.
          </p>
        </div>
        <div>
          <router-link
            to="/telegram/connect"
            class="inline-flex items-center gap-2 px-8 py-3.5 bg-[#229ED9] hover:bg-[#1c8ec4] text-white text-sm font-black rounded-2xl transition-all shadow-lg shadow-sky-100 active:scale-95"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>مسح رمز الـ QR والربط الآن</span>
          </router-link>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import api from '@/services/api';

export default {
  name: 'TelegramDevices',
  components: { DashboardLayout },
  setup() {
    const telegramStatus = ref('disconnected');
    const phoneNumber = ref('');
    const accountName = ref('');
    const username = ref('');
    const connectedAt = ref(null);
    const lastActivity = ref(null);
    const messagesSent = ref(0);
    const showDeleteConfirm = ref(false);
    const isDisconnecting = ref(false);
    let pollTimer = null;

    const loadStatus = async () => {
      try {
        const res = await api.get('/telegram/status');
        if (res.success && res.data) {
          telegramStatus.value = res.data.status || 'disconnected';
          phoneNumber.value = res.data.phoneNumber || '';
          accountName.value = res.data.name || '';
          username.value = res.data.username || '';
          connectedAt.value = res.data.connectedAt;
          lastActivity.value = res.data.lastActivity;
        }

        const statsRes = await api.get('/telegram/dashboard-stats');
        if (statsRes.success && statsRes.data) {
          messagesSent.value = statsRes.data.messages_sent || 0;
        }
      } catch (err) {
        console.error('Failed to load Telegram status:', err);
      }
    };

    const disconnectAccount = async () => {
      try {
        isDisconnecting.value = true;
        await api.post('/telegram/disconnect');
        showDeleteConfirm.value = false;
        await loadStatus();
      } catch (err) {
        console.error('Failed to disconnect:', err);
      } finally {
        isDisconnecting.value = false;
      }
    };

    const formatDate = (d) => {
      if (!d) return '-';
      return new Date(d).toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric' });
    };

    const formatTime = (d) => {
      if (!d) return '-';
      return new Date(d).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
    };

    onMounted(() => {
      loadStatus();
      pollTimer = setInterval(loadStatus, 15000);
    });

    onUnmounted(() => {
      if (pollTimer) clearInterval(pollTimer);
    });

    return {
      telegramStatus,
      phoneNumber,
      accountName,
      username,
      connectedAt,
      lastActivity,
      messagesSent,
      showDeleteConfirm,
      isDisconnecting,
      disconnectAccount,
      formatDate,
      formatTime,
    };
  },
};
</script>
