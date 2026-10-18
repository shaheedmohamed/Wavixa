<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('send_message_page.title') }}</h2>
          <p class="text-gray-500 text-sm mt-1">{{ t('send_message_page.subtitle') }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Form Area -->
        <div class="lg:col-span-2">
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="p-8">
              <form @submit.prevent="sendMessage" class="space-y-6">
                <!-- Out of Credits Warning -->
                <div v-if="outOfCredits" class="bg-red-50 border border-red-100 rounded-2xl p-6 flex items-start gap-4 animate-in slide-in-from-top-4 duration-300">
                  <div class="w-10 h-10 bg-white text-red-600 rounded-xl flex items-center justify-center shadow-sm flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                  </div>
                  <div>
                    <h4 class="text-red-900 font-black mb-1">{{ t('send_message_page.out_of_credits_title') }}</h4>
                    <p class="text-red-600 text-sm font-medium">{{ t('send_message_page.out_of_credits_desc') }}</p>
                    <router-link to="/subscription" class="inline-block mt-3 text-red-700 text-xs font-black underline decoration-red-200 hover:decoration-red-400">{{ t('send_message_page.buy_credits_link') }}</router-link>
                  </div>
                </div>

                <!-- Status Check -->
                <div v-if="whatsappStatus !== 'connected'" class="bg-red-50 border border-red-100 rounded-2xl p-6 flex items-start gap-4 animate-in slide-in-from-top-4 duration-300">
                  <div class="w-10 h-10 bg-white text-red-600 rounded-xl flex items-center justify-center shadow-sm flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                  </div>
                  <div>
                    <h4 class="text-red-900 font-black mb-1">{{ t('send_message_page.not_connected_title') }}</h4>
                    <p class="text-red-600 text-sm font-medium">{{ t('send_message_page.not_connected_desc') }}</p>
                    <router-link to="/whatsapp-connect" class="inline-block mt-3 text-red-700 text-xs font-black underline decoration-red-200 hover:decoration-red-400">{{ t('send_message_page.connect_link') }}</router-link>
                  </div>
                </div>

                <!-- Recipient -->
                <div class="space-y-2">
                  <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('send_message_page.recipient_label') }}</label>
                  <div class="relative group">
                    <div class="absolute inset-y-0 flex items-center pointer-events-none text-gray-400 group-focus-within:text-indigo-500 transition-colors" :class="isRTL ? 'right-0 pr-4' : 'left-0 pl-4'">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                    </div>
                    <input
                      v-model="form.to"
                      type="text"
                      :placeholder="t('send_message_page.recipient_placeholder')"
                      class="w-full bg-gray-50 border border-gray-100 rounded-2xl py-4 text-gray-900 font-bold placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none font-mono"
                      :class="isRTL ? 'pr-12 pl-4' : 'pl-12 pr-4'"
                      dir="ltr"
                      :disabled="whatsappStatus !== 'connected' || isSending || outOfCredits"
                    />
                  </div>
                  <p class="text-[10px] text-gray-400 font-bold uppercase tracking-tighter">{{ t('send_message_page.recipient_hint') }}</p>
                </div>

                <!-- Message -->
                <div class="space-y-2">
                  <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('send_message_page.message_label') }}</label>
                  <textarea
                    v-model="form.message"
                    :placeholder="t('send_message_page.message_placeholder')"
                    rows="6"
                    class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-4 text-gray-900 font-medium placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none resize-none"
                    :disabled="whatsappStatus !== 'connected' || isSending || outOfCredits"
                  />
                  <div class="flex justify-between items-center px-1">
                    <p class="text-[10px] font-bold" :class="form.message.length > 1600 ? 'text-red-500' : 'text-gray-400'">
                      {{ isRTL ? `${form.message.length} حرف من أصل 1600` : `${form.message.length} / 1600 characters` }}
                    </p>
                    <p v-if="form.message.length > 1600" class="text-[10px] text-red-500 font-black uppercase">{{ t('send_message_page.message_split_warning') }}</p>
                  </div>
                </div>

                <!-- Action Button -->
                <button
                  type="submit"
                  :disabled="!form.to || !form.message || whatsappStatus !== 'connected' || isSending || outOfCredits"
                  class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:bg-gray-200 disabled:cursor-not-allowed text-white px-8 py-4 rounded-2xl font-black text-lg transition-all shadow-lg shadow-indigo-100 active:scale-95 flex items-center justify-center gap-3"
                >
                  <svg v-if="isSending" class="w-6 h-6 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                  <span>{{ isSending ? t('send_message_page.sending') : t('send_message_page.send_now') }}</span>
                </button>

                <!-- Feedback -->
                <div v-if="error" class="bg-red-50 border border-red-100 rounded-2xl p-4 flex items-center gap-3 text-red-600 animate-in slide-in-from-bottom-2">
                  <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  <p class="text-sm font-bold">{{ error }}</p>
                </div>
                <div v-if="message" class="bg-green-50 border border-green-100 rounded-2xl p-4 flex items-center gap-3 text-green-600 animate-in slide-in-from-bottom-2">
                  <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                  <p class="text-sm font-bold">{{ message }}</p>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Sidebar Actions/Info -->
        <div class="space-y-6">
          <!-- Connection Status Card -->
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6">
            <h3 class="text-sm font-black text-gray-900 mb-4 flex items-center gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
              {{ isRTL ? 'حالة الاتصال' : 'Connection Status' }}
            </h3>
            <div class="space-y-4">
              <div class="flex items-center justify-between p-3 rounded-2xl bg-gray-50 border border-gray-50">
                <span class="text-xs font-bold text-gray-400">{{ t('common.status') }}</span>
                <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest', whatsappStatus === 'connected' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">
                  {{ whatsappStatus === 'connected' ? t('common.connected') : t('common.disconnected') }}
                </span>
              </div>
              <div v-if="whatsappStatus === 'connected'" class="flex flex-col gap-1 p-3 rounded-2xl bg-gray-50 border border-gray-50">
                <span class="text-xs font-bold text-gray-400">{{ isRTL ? 'الرقم المتصل' : 'Connected Number' }}</span>
                <span class="text-sm font-black text-gray-900 font-mono tracking-wider" dir="ltr">{{ phoneNumber || 'N/A' }}</span>
              </div>
            </div>
            <router-link
              v-if="whatsappStatus !== 'connected'"
              to="/whatsapp-connect"
              class="block mt-4 w-full bg-gray-900 text-white px-4 py-3 rounded-xl font-bold text-center text-xs hover:bg-black transition-all active:scale-95"
            >
              {{ isRTL ? 'ربط واتساب الآن' : 'Connect WhatsApp Now' }}
            </router-link>
          </div>

          <!-- Send Tips Card -->
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6">
            <h3 class="text-sm font-black text-gray-900 mb-4 flex items-center gap-2">
              <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              {{ isRTL ? 'نصائح للإرسال' : 'Best Practices' }}
            </h3>
            <ul class="space-y-3">
              <li v-for="tip in tips" :key="tip" class="flex items-start gap-3">
                <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                <p class="text-xs text-gray-600 font-medium leading-relaxed">{{ tip }}</p>
              </li>
            </ul>
          </div>

          <!-- Recent Activity Card -->
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between bg-gray-50/30">
              <h3 class="text-sm font-black text-gray-900">{{ isRTL ? 'آخر المرسلين' : 'Recent Sent' }}</h3>
              <router-link to="/messages" class="text-[10px] font-bold text-indigo-600 hover:underline">{{ t('common.all') }}</router-link>
            </div>
            <div class="p-2">
              <div v-if="recentSent.length > 0" class="space-y-1">
                <div v-for="msg in recentSent" :key="msg.id" class="p-3 rounded-2xl hover:bg-gray-50 transition-colors flex flex-col gap-0.5">
                  <p class="text-xs font-black text-gray-900 font-mono" dir="ltr">{{ msg.to }}</p>
                  <p class="text-[10px] text-gray-400 font-medium truncate">{{ msg.message }}</p>
                </div>
              </div>
              <div v-else class="py-12 text-center">
                <div class="w-10 h-10 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-200">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                </div>
                <p class="text-[10px] font-bold text-gray-300 uppercase tracking-widest">{{ isRTL ? 'لا يوجد سجل إرسال' : 'No sent history' }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import auth from '@/services/auth';
import { t, isRTL } from '@/i18n';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

export default {
  name: 'SendMessage',
  components: { DashboardLayout },
  setup() {
    const router = useRouter();
    const form = ref({ to: '', message: '' });
    const whatsappStatus = ref('disconnected');
    const phoneNumber = ref('');
    const isSending = ref(false);
    const error = ref('');
    const message = ref('');
    const recentSent = ref([]);
    const subscription = ref(null);

    const tips = computed(() => isRTL.value ? [
      'تأكد من كتابة مفتاح الدولة بشكل صحيح (مثلاً 966 للسعودية).',
      'الحد الأقصى للرسالة الواحدة هو 1600 حرف لضمان وصولها بنجاح.',
      'تأكد من أن الرقم المستهدف لديه حساب واتساب مفعل.',
      'تجنب إرسال رسائل عشوائية (سبام) لتقليل خطر تقييد حسابك.'
    ] : [
      'Ensure country code is entered correctly (e.g. 1 for US, 44 for UK).',
      'Maximum message length is 1,600 characters for optimal delivery.',
      'Ensure the recipient phone number has an active WhatsApp account.',
      'Avoid unsolicited spam messaging to reduce the risk of account restriction.'
    ]);

    onMounted(async () => {
      const userData = auth.getUser();
      if (!userData) {
        router.push('/login');
        return;
      }

      await loadSubscription();
      await checkStatus();
      await loadRecentMessages();
    });

    const outOfCredits = computed(() => {
      if (!subscription.value) return false;
      return Number(subscription.value.messages_remaining ?? 0) <= 0 || subscription.value.status !== 'active';
    });

    const loadSubscription = async () => {
      try {
        const response = await api.get('/subscription');
        if (response.success) {
          subscription.value = response.data;
        }
      } catch (err) {
        console.error('Failed to load subscription:', err);
      }
    };

    const checkStatus = async () => {
      try {
        const response = await api.get('/whatsapp/status');
        if (response.success) {
          whatsappStatus.value = response.data.status;
          phoneNumber.value = response.data.phoneNumber || '';
        }
      } catch (err) {
        console.error('Error checking status:', err);
      }
    };

    const loadRecentMessages = async () => {
      try {
        const response = await api.get('/whatsapp/dashboard-stats');
        if (response.success) {
          recentSent.value = (response.data.recentMessages || [])
            .filter(msg => msg.direction === 'outgoing')
            .slice(0, 5);
        }
      } catch (err) {
        console.error('Error loading messages:', err);
      }
    };

    const sendMessage = async () => {
      try {
        error.value = '';
        message.value = '';
        isSending.value = true;

        if (outOfCredits.value) {
          error.value = isRTL.value ? 'رصيد رسائلك انتهى. قم بشراء رصيد للإرسال.' : 'Your message credits have run out. Please buy credits to send.';
          return;
        }

        if (!form.value.to || !form.value.message) {
          error.value = isRTL.value ? 'يرجى ملء جميع الحقول المطلوبة' : 'Please fill all required fields';
          return;
        }

        // Format phone number
        const formattedPhone = form.value.to.replace(/[^0-9]/g, '');
        if (formattedPhone.length < 7) {
          error.value = isRTL.value ? 'يرجى إدخال رقم هاتف صحيح مع كود الدولة' : 'Please enter a valid phone number with country code';
          return;
        }

        const response = await api.post('/whatsapp/send-message', {
          to: formattedPhone,
          message: form.value.message,
        });

        if (response.success) {
          message.value = isRTL.value ? 'تم وضع الرسالة في قائمة الإرسال بنجاح!' : 'Message queued for delivery successfully!';
          form.value = { to: '', message: '' };
          await loadRecentMessages();
          setTimeout(() => message.value = '', 4000);
        } else {
          error.value = response.message || (isRTL.value ? 'فشل في إرسال الرسالة' : 'Failed to send message');
        }
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل في إرسال الرسالة' : 'Failed to send message');
      } finally {
        isSending.value = false;
      }
    };

    return {
      form,
      whatsappStatus,
      phoneNumber,
      isSending,
      error,
      message,
      recentSent,
      tips,
      sendMessage,
      outOfCredits,
      subscription,
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
