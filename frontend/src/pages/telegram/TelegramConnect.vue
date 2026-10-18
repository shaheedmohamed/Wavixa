<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="w-2.5 h-2.5 rounded-full bg-[#229ED9]"></span>
            <h2 class="text-2xl font-bold text-gray-900 tracking-tight">ربط حساب Telegram بالـ QR</h2>
          </div>
          <p class="text-gray-500 text-sm">امسح رمز الاستجابة السريعة (QR Code) من تطبيق Telegram على هاتفك لتسجيل الدخول وربط رقمك فوراً.</p>
        </div>
        <router-link to="/telegram/devices" class="text-xs font-bold text-gray-500 hover:text-[#229ED9] transition-colors">
          ← العودة للأجهزة
        </router-link>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
        <!-- QR Code Card -->
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden flex flex-col items-center justify-center p-8 min-h-[440px] text-center space-y-6">
          
          <!-- Already Connected Alert -->
          <div v-if="connectionStatus === 'connected'" class="space-y-4 py-8 animate-in zoom-in-95 duration-300">
            <div class="w-20 h-20 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mx-auto shadow-sm">
              <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
            </div>
            <div class="space-y-1">
              <h3 class="text-xl font-black text-gray-900">تم ربط حساب Telegram بنجاح!</h3>
              <p class="text-sm font-bold text-emerald-600 font-mono" dir="ltr">{{ phoneNumber }}</p>
            </div>
            <router-link
              to="/telegram/devices"
              class="inline-block px-8 py-3 bg-[#229ED9] hover:bg-[#1c8ec4] text-white text-xs font-black rounded-2xl shadow-md transition-all active:scale-95"
            >
              الانتقال للأجهزة
            </router-link>
          </div>

          <!-- Pending / QR Scan State -->
          <div v-else-if="qrCode" class="space-y-6 animate-in zoom-in-95 duration-300">
            <div class="relative inline-block p-4 bg-white rounded-3xl shadow-xl border border-gray-100">
              <img :src="qrCode" alt="Telegram QR Code" class="w-64 h-64 rounded-2xl mx-auto shadow-2xs" />
            </div>

            <div class="space-y-2">
              <div class="flex items-center justify-center gap-2.5 py-2 px-5 bg-sky-50 text-[#229ED9] rounded-full w-fit mx-auto border border-sky-100">
                <div class="w-2.5 h-2.5 bg-[#229ED9] rounded-full animate-ping"></div>
                <span class="text-xs font-black">في انتظار المسح من تطبيق Telegram...</span>
              </div>
              <p class="text-[11px] text-gray-400 font-bold">يتجدد رمز الـ QR تلقائياً لضمان الأمان</p>
            </div>

            <div class="pt-1">
              <button
                @click="requestQr(true)"
                :disabled="isLoading"
                class="px-4 py-2 bg-gray-50 hover:bg-gray-100 text-gray-600 text-xs font-bold rounded-xl border border-gray-200/80 transition-all flex items-center gap-1.5 mx-auto active:scale-95"
              >
                <svg class="w-4 h-4" :class="{ 'animate-spin': isLoading }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                <span>تحديث رمز الـ QR</span>
              </button>
            </div>
          </div>

          <!-- Initial Loading State -->
          <div v-else class="space-y-6 py-12">
            <div class="w-20 h-20 bg-sky-50 text-[#229ED9] rounded-3xl flex items-center justify-center mx-auto shadow-2xs">
              <svg class="w-10 h-10 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </div>
            <div class="space-y-2">
              <h3 class="text-base font-black text-gray-900">جاري إنشاء رمز الـ QR...</h3>
              <p class="text-xs text-gray-400 font-medium">يرجى الانتظار ثوانٍ معدودة للاتصال وتوليد الرمز</p>
            </div>
          </div>

        </div>

        <!-- Instructions Guide Card -->
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-6">
          <div class="flex items-center gap-3 pb-4 border-b border-gray-100">
            <div class="w-10 h-10 rounded-2xl bg-sky-50 text-[#229ED9] flex items-center justify-center font-black">
              <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.75-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
            </div>
            <div>
              <h3 class="text-base font-black text-gray-900">طريقة مسح الـ QR من تطبيق Telegram</h3>
              <p class="text-xs text-gray-400">اتبع الخطوات البسيطة التالية على هاتفك:</p>
            </div>
          </div>

          <div class="space-y-4">
            <div class="flex items-start gap-4 p-4 rounded-2xl bg-gray-50/80 border border-gray-100">
              <div class="w-8 h-8 rounded-xl bg-[#229ED9] text-white flex items-center justify-center text-xs font-black flex-shrink-0">
                1
              </div>
              <div class="space-y-1">
                <h4 class="text-xs font-black text-gray-900">افتح تطبيق Telegram على هاتفك</h4>
                <p class="text-xs text-gray-500 leading-relaxed">تأكد من فتح حساب Telegram الذي تود استخدامه في الإرسال.</p>
              </div>
            </div>

            <div class="flex items-start gap-4 p-4 rounded-2xl bg-gray-50/80 border border-gray-100">
              <div class="w-8 h-8 rounded-xl bg-[#229ED9] text-white flex items-center justify-center text-xs font-black flex-shrink-0">
                2
              </div>
              <div class="space-y-1">
                <h4 class="text-xs font-black text-gray-900">اذهب إلى الإعدادات > الأجهزة (Devices)</h4>
                <p class="text-xs text-gray-500 leading-relaxed">اضغط على تبويب "الأجهزة" (Devices) في قائمة الإعدادات.</p>
              </div>
            </div>

            <div class="flex items-start gap-4 p-4 rounded-2xl bg-gray-50/80 border border-gray-100">
              <div class="w-8 h-8 rounded-xl bg-[#229ED9] text-white flex items-center justify-center text-xs font-black flex-shrink-0">
                3
              </div>
              <div class="space-y-1">
                <h4 class="text-xs font-black text-gray-900">اضغط "ربط جهاز سطح المكتب" (Link Desktop Device)</h4>
                <p class="text-xs text-gray-500 leading-relaxed">ستفتح كاميرا هاتفك تلقائياً لمسح الرمز.</p>
              </div>
            </div>

            <div class="flex items-start gap-4 p-4 rounded-2xl bg-gray-50/80 border border-gray-100">
              <div class="w-8 h-8 rounded-xl bg-[#229ED9] text-white flex items-center justify-center text-xs font-black flex-shrink-0">
                4
              </div>
              <div class="space-y-1">
                <h4 class="text-xs font-black text-gray-900">وجّه الكاميرا نحو الرمز المقابل</h4>
                <p class="text-xs text-gray-500 leading-relaxed">سيتم الربط فوراً وتحديث الصفحة تلقائياً لتتمكن من الإرسال مباشرة!</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import api from '@/services/api';

export default {
  name: 'TelegramConnect',
  components: { DashboardLayout },
  setup() {
    const router = useRouter();
    const qrCode = ref(null);
    const connectionStatus = ref('disconnected');
    const phoneNumber = ref('');
    const isLoading = ref(false);
    let pollInterval = null;

    const requestQr = async (force = false) => {
      try {
        isLoading.value = true;
        const res = await api.post(`/telegram/connect${force ? '?force=true' : ''}`);
        if (res.success && res.data) {
          if (res.data.qr) {
            qrCode.value = res.data.qr;
          }
          connectionStatus.value = res.data.status || 'pairing';
          phoneNumber.value = res.data.phoneNumber || '';
        }
      } catch (err) {
        console.error('Failed to request Telegram QR:', err);
      } finally {
        isLoading.value = false;
      }
    };

    const pollStatus = async () => {
      try {
        const res = await api.get('/telegram/status');
        if (res.success && res.data) {
          connectionStatus.value = res.data.status || 'disconnected';
          phoneNumber.value = res.data.phoneNumber || '';
          if (res.data.qr) {
            qrCode.value = res.data.qr;
          } else if (connectionStatus.value === 'disconnected' && !qrCode.value) {
            // Re-trigger connection if lost
            requestQr();
          }

          if (connectionStatus.value === 'connected') {
            clearInterval(pollInterval);
            setTimeout(() => {
              router.push('/telegram/devices');
            }, 1500);
          }
        }
      } catch (err) {
        console.error('Failed to poll status:', err);
      }
    };

    onMounted(async () => {
      await requestQr();
      pollInterval = setInterval(pollStatus, 2000);
    });

    onUnmounted(() => {
      if (pollInterval) clearInterval(pollInterval);
    });

    return {
      qrCode,
      connectionStatus,
      phoneNumber,
      isLoading,
      requestQr,
    };
  },
};
</script>
