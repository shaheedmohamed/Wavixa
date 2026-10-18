<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 text-center" :class="isRTL ? 'md:text-right' : 'md:text-left'">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('whatsapp_connect.title') }}</h2>
          <p class="text-gray-500 text-sm mt-1">{{ t('whatsapp_connect.subtitle') }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- QR Code Section -->
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden flex flex-col items-center justify-center p-8 min-h-[400px]">
          <!-- Disconnected State -->
          <div v-if="connectionStatus === 'disconnected'" class="w-full text-center space-y-6">
            <div class="w-20 h-20 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mx-auto mb-6">
              <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 00 5.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
            </div>
            <div class="space-y-2">
              <h3 class="text-xl font-black text-gray-900">{{ t('whatsapp_connect.ready_title') }}</h3>
              <p class="text-sm text-gray-500 font-medium">{{ t('whatsapp_connect.ready_desc') }}</p>
            </div>
            <div class="flex items-center justify-center gap-2 bg-gray-50 p-1 rounded-2xl w-fit mx-auto">
              <button
                @click="connectionMode = 'qr'"
                :class="connectionMode === 'qr' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500'"
                class="px-6 py-2 rounded-xl text-sm font-black transition-all"
              >
                QR
              </button>
            </div>
            <div v-if="connectionMode === 'phone'" class="space-y-3 max-w-xs mx-auto">
              <input
                v-model="inputPhone"
                type="tel"
                placeholder="e.g. 201011234567"
                class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-gray-900 font-bold text-center focus:outline-none focus:border-indigo-300 font-mono"
                dir="ltr"
              />
              <p class="text-xs text-gray-400 font-medium">{{ t('send_message_page.recipient_hint') }}</p>
            </div>
            <button
              @click="startConnection"
              :disabled="isConnecting"
              class="w-full max-w-xs bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-8 py-4 rounded-2xl font-black text-lg transition-all shadow-lg shadow-indigo-100 active:scale-95 flex items-center justify-center gap-3"
            >
              <svg v-if="isConnecting" class="w-6 h-6 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
              <span>{{ isConnecting ? t('whatsapp_connect.preparing') : t('whatsapp_connect.start_connecting') }}</span>
            </button>
          </div>

          <!-- Pending/QR State -->
          <div v-if="connectionStatus === 'pending'" class="w-full text-center space-y-8">
            <!-- Phone Pairing Code -->
            <div v-if="connectionMode === 'phone'" class="space-y-6">
              <div class="inline-block p-6 bg-white rounded-3xl shadow-2xl border border-gray-100 animate-in zoom-in-95 duration-500">
                <p class="text-sm text-gray-500 mb-3 font-medium">{{ t('whatsapp_connect.pairing_code_title') }}</p>
                <div v-if="pairingCode" class="text-4xl font-black text-indigo-600 tracking-[0.2em] font-mono select-all">
                  {{ pairingCode }}
                </div>
                <div v-else class="w-64 h-16 bg-gray-50 rounded-xl flex items-center justify-center">
                  <svg class="w-8 h-8 text-indigo-100 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </div>
              </div>
              <div class="space-y-4">
                <div class="space-y-2">
                  <h3 class="text-xl font-black text-gray-900">{{ t('whatsapp_connect.enter_code_phone') }}</h3>
                  <p class="text-sm text-gray-500 font-medium">{{ t('whatsapp_connect.enter_code_desc') }}</p>
                </div>
                <div class="flex items-center justify-center gap-3 py-2 px-4 bg-indigo-50 text-indigo-600 rounded-full w-fit mx-auto">
                  <div class="w-2 h-2 bg-indigo-600 rounded-full animate-ping"></div>
                  <span class="text-[10px] font-black uppercase tracking-widest">{{ t('whatsapp_connect.waiting_pairing') }}</span>
                </div>
              </div>
            </div>

            <!-- QR Code -->
            <div v-else>
              <div class="relative inline-block p-4 bg-white rounded-3xl shadow-2xl border border-gray-100 animate-in zoom-in-95 duration-500">
                <img v-if="qrCode" :src="qrCode" alt="WhatsApp QR Code" class="w-64 h-64 rounded-xl" />
                <div v-else class="w-64 h-64 bg-gray-50 rounded-xl flex items-center justify-center">
                  <svg class="w-12 h-12 text-indigo-100 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </div>
              </div>
              <div class="space-y-4 mt-6">
                <div class="space-y-2">
                  <h3 class="text-xl font-black text-gray-900">{{ t('whatsapp_connect.scan_qr_title') }}</h3>
                  <p class="text-sm text-gray-500 font-medium">{{ t('whatsapp_connect.scan_qr_desc') }}</p>
                </div>
                <div class="flex items-center justify-center gap-3 py-2 px-4 bg-indigo-50 text-indigo-600 rounded-full w-fit mx-auto">
                  <div class="w-2 h-2 bg-indigo-600 rounded-full animate-ping"></div>
                  <span class="text-[10px] font-black uppercase tracking-widest">{{ isRTL ? 'في انتظار المسح...' : 'Waiting for scan...' }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Connected State -->
          <div v-if="connectionStatus === 'connected'" class="w-full text-center space-y-8">
            <div class="w-20 h-20 bg-green-50 text-green-600 rounded-full flex items-center justify-center mx-auto shadow-sm border border-green-100 animate-in zoom-in duration-300">
              <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </div>
            <div class="space-y-2">
              <h3 class="text-2xl font-black text-gray-900">{{ t('whatsapp_connect.connected_success') }}</h3>
              <p class="text-sm text-gray-500 font-medium">{{ t('whatsapp_connect.connected_success_desc') }}</p>
            </div>
            
            <div class="max-w-xs mx-auto space-y-3 p-6 bg-gray-50 rounded-2xl border border-gray-50">
              <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ t('devices_page.phone_number') }}</span>
                <span class="text-sm font-black text-gray-900 font-mono" dir="ltr">{{ phoneNumber }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ t('devices_page.connected_at') }}</span>
                <span class="text-sm font-black text-gray-900">{{ formatDate(connectedAt) }}</span>
              </div>
            </div>

            <button
              @click="disconnectDevice"
              :disabled="isDisconnecting"
              class="w-full max-w-xs bg-red-50 text-red-600 px-6 py-4 rounded-2xl font-black text-sm hover:bg-red-100 transition-all active:scale-95 disabled:opacity-50 flex items-center justify-center gap-2 border border-red-100"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              <span>{{ isDisconnecting ? t('common.loading') : t('devices_page.disconnect') }}</span>
            </button>
          </div>
        </div>

        <!-- Instructions & Features -->
        <div class="space-y-6">
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-8">
            <h3 class="text-lg font-black text-gray-900 mb-8 flex items-center gap-3">
              <div class="w-8 h-8 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              </div>
              {{ t('whatsapp_connect.how_to_connect') }}
            </h3>
            <div class="space-y-8 relative">
              <!-- Connecting Line -->
              <div class="absolute top-4 bottom-4 w-0.5 bg-gray-50" :class="isRTL ? 'right-4' : 'left-4'"></div>
              
              <div v-for="(step, i) in steps" :key="i" class="flex items-start gap-6 relative">
                <div class="w-8 h-8 bg-white border-2 border-gray-50 text-indigo-600 rounded-full flex items-center justify-center text-xs font-black shrink-0 z-10 group-hover:border-indigo-600 transition-colors">{{ i + 1 }}</div>
                <div>
                  <h4 class="text-sm font-black text-gray-900 mb-1">{{ step.title }}</h4>
                  <p class="text-xs text-gray-500 font-medium">{{ step.desc }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-indigo-600 rounded-3xl p-8 text-white shadow-xl shadow-indigo-100 overflow-hidden relative group">
            <!-- Background Decoration -->
            <div class="absolute -top-12 w-48 h-48 bg-white/10 rounded-full blur-3xl group-hover:bg-white/20 transition-all duration-700" :class="isRTL ? '-right-12' : '-left-12'"></div>
            
            <h3 class="text-lg font-black mb-6 flex items-center gap-3">
              <svg class="w-6 h-6 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-7.714 2.143L11 21l-2.286-6.857L1 12l7.714-2.143L11 3z" /></svg>
              {{ isRTL ? 'مزايا الربط' : 'Key Features' }}
            </h3>
            <ul class="space-y-4">
              <li v-for="feat in features" :key="feat" class="flex items-center gap-3">
                <div class="w-5 h-5 bg-white/10 rounded-full flex items-center justify-center shrink-0">
                  <svg class="w-3 h-3 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                </div>
                <span class="text-xs font-bold text-indigo-50">{{ feat }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Alerts -->
      <div v-if="error" class="fixed bottom-8 z-50 bg-red-600 text-white px-6 py-3 rounded-2xl font-black text-sm shadow-2xl animate-in" :class="isRTL ? 'right-8 slide-in-from-right-4' : 'left-8 slide-in-from-left-4'">
        {{ error }}
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import auth from '@/services/auth';
import { t, isRTL } from '@/i18n';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

export default {
  name: 'WhatsAppConnect',
  components: { DashboardLayout },
  setup() {
    const router = useRouter();
    const connectionStatus = ref('disconnected');
    const connectionMode = ref('qr');
    const qrCode = ref('');
    const phoneNumber = ref('');
    const inputPhone = ref('');
    const pairingCode = ref('');
    const pairingPhoneNumber = ref('');
    const connectedAt = ref(null);
    const isConnecting = ref(false);
    const isDisconnecting = ref(false);
    const isWaitingForQr = ref(false);
    const error = ref('');
    const message = ref('');
    let pollInterval = null;

    const steps = computed(() =>
      connectionMode.value === 'phone'
        ? [
            { title: isRTL.value ? 'افتح تطبيق واتساب' : 'Open WhatsApp App', desc: isRTL.value ? 'على هاتفك المحمول الشخصي' : 'On your mobile phone' },
            { title: isRTL.value ? 'انتقل للإعدادات' : 'Go to Settings', desc: isRTL.value ? 'اضغط على القائمة ثم الأجهزة المرتبطة' : 'Tap Menu and select Linked Devices' },
            { title: isRTL.value ? 'ربط جهاز جديد' : 'Link New Device', desc: isRTL.value ? 'اضغط على زر ربط جهاز' : 'Tap Link a Device' },
            { title: isRTL.value ? 'أدخل الكود' : 'Enter Pairing Code', desc: isRTL.value ? `اختر Link with phone number واكتب الكود: ${pairingCode.value || '...'}` : `Select Link with phone number and enter: ${pairingCode.value || '...'}` },
          ]
        : [
            { title: isRTL.value ? 'افتح تطبيق واتساب' : 'Open WhatsApp App', desc: isRTL.value ? 'على هاتفك المحمول الشخصي' : 'On your mobile phone' },
            { title: isRTL.value ? 'انتقل للإعدادات' : 'Go to Settings', desc: isRTL.value ? 'اضغط على القائمة ثم الأجهزة المرتبطة' : 'Tap Menu and select Linked Devices' },
            { title: isRTL.value ? 'ربط جهاز جديد' : 'Link New Device', desc: isRTL.value ? 'اضغط على زر ربط جهاز لبدء المسح' : 'Tap Link a Device to start scanner' },
            { title: isRTL.value ? 'امسح رمز QR' : 'Scan QR Code', desc: isRTL.value ? 'وجه الكاميرا نحو الرمز المعروض على الشاشة' : 'Point your camera at this QR code' },
          ]
    );

    const features = computed(() => isRTL.value ? [
      'إرسال واستقبال الرسائل برمجياً',
      'دعم الأجهزة المتعددة (Multi-device)',
      'إشعارات فورية بكافة الأحداث',
      'سجل كامل للمحادثات والرسائل',
      'اتصال آمن ومشفر بالكامل',
    ] : [
      'Automated message sending and receiving',
      'Multi-device WhatsApp protocol support',
      'Instant real-time webhook events',
      'Full conversation and audit history',
      'End-to-end encrypted reliable connection',
    ]);

    onMounted(async () => {
      const userData = auth.getUser();
      if (!userData) {
        router.push('/login');
        return;
      }
      await checkConnectionStatus();
      pollInterval = setInterval(checkConnectionStatus, 4000);
    });

    onUnmounted(() => {
      if (pollInterval) clearInterval(pollInterval);
    });

    const checkConnectionStatus = async () => {
      try {
        const response = await api.get('/whatsapp/status');
        if (response.success) {
          const data = response.data;

          if (data.qr) qrCode.value = data.qr;
          if (data.pairingCode) pairingCode.value = data.pairingCode;
          if (data.phoneNumber) phoneNumber.value = data.phoneNumber;
          if (data.pairingPhoneNumber) pairingPhoneNumber.value = data.pairingPhoneNumber;
          if (data.mode) connectionMode.value = data.mode;
          if (data.connectedAt) connectedAt.value = data.connectedAt;

          if (data.status === 'connected') {
            connectionStatus.value = 'connected';
            isWaitingForQr.value = false;
          } else if (data.status === 'pending') {
            connectionStatus.value = 'pending';
            isWaitingForQr.value = false;
          } else if (!isWaitingForQr.value) {
            connectionStatus.value = 'disconnected';
          }
        }
      } catch (err) {
        console.error('Error checking status:', err);
      }
    };

    const startConnection = async () => {
      try {
        isConnecting.value = true;
        isWaitingForQr.value = true;
        error.value = '';
        const payload = {};
        if (connectionMode.value === 'phone') {
          const fullPhone = inputPhone.value.replace(/\D/g, '');
          if (!fullPhone || fullPhone.length < 10) {
            error.value = isRTL.value ? 'ادخل رقم واتساب صحيح مع كود الدولة' : 'Enter valid phone number with country code';
            isWaitingForQr.value = false;
            isConnecting.value = false;
            return;
          }
          payload.phone_number = fullPhone;
        }
        const response = await api.post('/whatsapp/connect', payload);
        if (response.success) {
          if (response.message === 'Already connected') {
            connectionStatus.value = 'connected';
            isWaitingForQr.value = false;
          } else {
            connectionStatus.value = 'pending';
            if (response.data?.pairingCode) {
              pairingCode.value = response.data.pairingCode;
              pairingPhoneNumber.value = inputPhone.value;
            }
          }
          await checkConnectionStatus();
        } else {
          error.value = response.message || (isRTL.value ? 'فشل في بدء عملية الربط' : 'Failed to start pairing');
          isWaitingForQr.value = false;
        }
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل في بدء عملية الربط' : 'Failed to start pairing');
        isWaitingForQr.value = false;
      } finally {
        isConnecting.value = false;
      }
    };

    const disconnectDevice = async () => {
      if (!confirm(isRTL.value ? 'هل أنت متأكد من رغبتك في قطع الاتصال؟' : 'Are you sure you want to disconnect?')) return;
      try {
        isDisconnecting.value = true;
        error.value = '';
        const response = await api.post('/whatsapp/disconnect', {});
        if (response.success) {
          connectionStatus.value = 'disconnected';
          phoneNumber.value = '';
          connectedAt.value = null;
        } else {
          error.value = response.message || (isRTL.value ? 'فشل قطع الاتصال' : 'Failed to disconnect');
        }
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل قطع الاتصال' : 'Failed to disconnect');
      } finally {
        isDisconnecting.value = false;
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

    return {
      connectionStatus,
      connectionMode,
      qrCode,
      phoneNumber,
      inputPhone,
      pairingCode,
      pairingPhoneNumber,
      connectedAt,
      isConnecting,
      isDisconnecting,
      error,
      message,
      steps,
      features,
      startConnection,
      disconnectDevice,
      formatDate,
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
