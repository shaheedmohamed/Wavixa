<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="w-2.5 h-2.5 rounded-full bg-[#229ED9]"></span>
            <h2 class="text-2xl font-bold text-gray-900 tracking-tight">إرسال رسالة Telegram</h2>
          </div>
          <p class="text-gray-500 text-sm">إرسال رسائل مباشرة من رقمك وحسابك في Telegram إلى أي رقم أو مستخدم أو قناة.</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Form Area -->
        <div class="lg:col-span-2 space-y-6">
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden p-6 sm:p-8">
            <form @submit.prevent="sendMessage" class="space-y-6">
              
              <!-- Out of credits warning -->
              <div v-if="outOfCredits" class="bg-red-50 border border-red-100 rounded-2xl p-5 flex items-start gap-4">
                <div class="w-10 h-10 bg-white text-red-600 rounded-xl flex items-center justify-center shadow-xs flex-shrink-0">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
                <div>
                  <h4 class="text-red-900 font-black mb-1">رصيد الرسائل منتهي</h4>
                  <p class="text-red-600 text-xs font-medium">لا يمكنك إرسال رسائل جديدة لأن رصيدك قد نفد. يرجى شحن الرصيد للمتابعة.</p>
                  <router-link to="/subscription" class="inline-block mt-2 text-red-700 text-xs font-black underline">شراء رصيد الآن</router-link>
                </div>
              </div>

              <!-- Not Connected Warning -->
              <div v-if="telegramStatus !== 'connected'" class="bg-amber-50 border border-amber-200/80 rounded-2xl p-5 flex items-start gap-4">
                <div class="w-10 h-10 bg-white text-amber-600 rounded-xl flex items-center justify-center shadow-xs flex-shrink-0">
                  <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.75-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
                </div>
                <div>
                  <h4 class="text-amber-900 font-black mb-1">حساب Telegram غير متصل</h4>
                  <p class="text-amber-700 text-xs font-medium">يرجى مسح رمز الـ QR لربط رقمك وحسابك في Telegram أولاً لتتمكن من الإرسال.</p>
                  <router-link to="/telegram/connect" class="inline-block mt-2 text-amber-800 text-xs font-black underline">مسح رمز الـ QR والربط الآن</router-link>
                </div>
              </div>

              <!-- Connected Sender Info -->
              <div v-if="telegramStatus === 'connected'" class="p-4 bg-sky-50/70 border border-sky-100 rounded-2xl flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-xl bg-[#229ED9] text-white flex items-center justify-center font-black text-sm">
                    ✓
                  </div>
                  <div>
                    <span class="text-[10px] font-bold text-gray-400 block uppercase">الحساب المرسل المتصل</span>
                    <span class="text-xs font-black text-gray-900">{{ accountName || 'حسابي' }} ({{ phoneNumber }})</span>
                  </div>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-200/60">
                  متصل
                </span>
              </div>

              <!-- Recipient -->
              <div class="space-y-2">
                <label class="block text-xs font-black text-gray-500 uppercase tracking-widest">رقم هاتف المستلم أو المعرف (@username)</label>
                <div class="relative group">
                  <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-gray-400 group-focus-within:text-[#229ED9]">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.75-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
                  </div>
                  <input
                    v-model="form.to"
                    type="text"
                    placeholder="مثال: +201012345678 أو @username"
                    class="w-full bg-gray-50 border border-gray-200 rounded-2xl py-3.5 pr-12 pl-4 text-xs font-mono font-bold text-gray-900 focus:bg-white focus:border-[#229ED9] outline-none transition-all"
                    dir="ltr"
                    :disabled="telegramStatus !== 'connected' || isSending || outOfCredits"
                    required
                  />
                </div>
                <p class="text-[10px] text-gray-400 font-bold">يمكنك إدخال رقم الهاتف الدولي مع كود الدولة مثل +201... أو معرف تيليجرام مثل @username.</p>
              </div>

              <!-- Message Type Selector -->
              <div class="space-y-2">
                <label class="block text-xs font-black text-gray-500 uppercase tracking-widest">نوع الرسالة</label>
                <div class="grid grid-cols-3 gap-3">
                  <button
                    type="button"
                    @click="form.type = 'text'"
                    :class="[
                      'py-2.5 px-4 rounded-xl text-xs font-black border transition-all flex items-center justify-center gap-2',
                      form.type === 'text' ? 'bg-[#229ED9]/10 border-[#229ED9] text-[#229ED9]' : 'bg-gray-50 border-gray-200 text-gray-600 hover:bg-gray-100'
                    ]"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" /></svg>
                    <span>نصية</span>
                  </button>
                  <button
                    type="button"
                    @click="form.type = 'photo'"
                    :class="[
                      'py-2.5 px-4 rounded-xl text-xs font-black border transition-all flex items-center justify-center gap-2',
                      form.type === 'photo' ? 'bg-[#229ED9]/10 border-[#229ED9] text-[#229ED9]' : 'bg-gray-50 border-gray-200 text-gray-600 hover:bg-gray-100'
                    ]"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    <span>صورة</span>
                  </button>
                  <button
                    type="button"
                    @click="form.type = 'document'"
                    :class="[
                      'py-2.5 px-4 rounded-xl text-xs font-black border transition-all flex items-center justify-center gap-2',
                      form.type === 'document' ? 'bg-[#229ED9]/10 border-[#229ED9] text-[#229ED9]' : 'bg-gray-50 border-gray-200 text-gray-600 hover:bg-gray-100'
                    ]"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <span>ملف / مستند</span>
                  </button>
                </div>
              </div>

              <!-- Media Uploader / Media URL -->
              <div v-if="form.type !== 'text'" class="space-y-2 bg-gray-50/80 p-4 rounded-2xl border border-gray-200/80">
                <label class="block text-xs font-black text-gray-700 uppercase">
                  {{ form.type === 'photo' ? 'رفع صورة أو رابط الصورة' : 'رفع ملف أو رابط الملف' }}
                </label>
                <div class="flex flex-col sm:flex-row items-center gap-3">
                  <input
                    type="file"
                    ref="fileInput"
                    @change="handleFileUpload"
                    class="hidden"
                    :accept="form.type === 'photo' ? 'image/*' : '*/*'"
                  />
                  <button
                    type="button"
                    @click="$refs.fileInput.click()"
                    :disabled="isUploading"
                    class="w-full sm:w-auto px-4 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-bold rounded-xl flex items-center justify-center gap-2 transition-all shadow-2xs"
                  >
                    <svg v-if="isUploading" class="w-4 h-4 animate-spin text-[#229ED9]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <svg v-else class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                    <span>{{ isUploading ? 'جاري الرفع...' : 'اختر ملف من جهازك' }}</span>
                  </button>
                  <span class="text-xs text-gray-400 font-bold">أو</span>
                  <input
                    v-model="form.media_url"
                    type="url"
                    placeholder="https://example.com/image.jpg"
                    class="flex-1 w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-800 focus:border-[#229ED9] outline-none"
                    dir="ltr"
                  />
                </div>
              </div>

              <!-- Message Text -->
              <div class="space-y-2">
                <label class="block text-xs font-black text-gray-500 uppercase tracking-widest">
                  {{ form.type === 'text' ? 'نص الرسالة' : 'وصف الميديا (Caption)' }}
                </label>
                <textarea
                  v-model="form.message"
                  :placeholder="form.type === 'text' ? 'اكتب رسالتك هنا ليتم إرسالها فوراً من حسابك...' : 'اكتب وصفاً للصورة أو الملف (اختياري)...'"
                  rows="5"
                  class="w-full bg-gray-50 border border-gray-200 rounded-2xl p-4 text-xs font-medium text-gray-900 focus:bg-white focus:border-[#229ED9] outline-none resize-none transition-all"
                  :disabled="telegramStatus !== 'connected' || isSending || outOfCredits"
                />
                <div class="flex justify-between items-center text-[10px] text-gray-400 font-bold">
                  <span>{{ form.message.length }} حرف</span>
                </div>
              </div>

              <!-- Submit Button -->
              <button
                type="submit"
                :disabled="isSending || outOfCredits || telegramStatus !== 'connected' || !form.to"
                class="w-full bg-[#229ED9] hover:bg-[#1c8ec4] disabled:opacity-50 disabled:cursor-not-allowed text-white px-8 py-4 rounded-2xl font-black text-base transition-all shadow-lg shadow-sky-100 active:scale-95 flex items-center justify-center gap-3"
              >
                <svg v-if="isSending" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                <span>{{ isSending ? 'جاري الإرسال عبر Telegram...' : 'إرسال الرسالة الآن' }}</span>
              </button>

              <!-- Feedback Alerts -->
              <div v-if="error" class="p-4 bg-red-50 border border-red-200/80 rounded-2xl text-xs font-bold text-red-700 flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>{{ error }}</span>
              </div>
              <div v-if="successMsg" class="p-4 bg-emerald-50 border border-emerald-200/80 rounded-2xl text-xs font-bold text-emerald-700 flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                <span>{{ successMsg }}</span>
              </div>
            </form>
          </div>
        </div>

        <!-- Sidebar / Live Preview -->
        <div class="space-y-6">
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 overflow-hidden">
            <h3 class="text-xs font-black text-gray-900 uppercase tracking-wider mb-4 flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-[#229ED9]"></span>
              معاينة الرسالة
            </h3>

            <div class="bg-[#8ab4f8]/10 rounded-2xl p-4 border border-sky-100 flex flex-col justify-end min-h-[200px]">
              <div class="bg-white rounded-2xl p-3.5 shadow-sm max-w-[90%] self-end space-y-2 border border-slate-100 text-right">
                <div v-if="form.type === 'photo' && form.media_url" class="rounded-xl overflow-hidden max-h-36 bg-gray-100">
                  <img :src="form.media_url" class="w-full h-full object-cover" alt="Media preview" />
                </div>
                <div v-if="form.type === 'document'" class="p-2.5 bg-sky-50 rounded-xl flex items-center gap-2 text-xs font-bold text-sky-800">
                  <svg class="w-5 h-5 text-[#229ED9]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                  <span class="truncate">مستند مرفق</span>
                </div>
                <p class="text-xs font-medium text-gray-800 whitespace-pre-wrap leading-relaxed">{{ form.message || 'نص الرسالة سيظهر هنا...' }}</p>
                <div class="text-[9px] text-gray-400 font-bold text-left" dir="ltr">12:00 PM ✓✓</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, onMounted } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import api from '@/services/api';

export default {
  name: 'TelegramSendMessage',
  components: { DashboardLayout },
  setup() {
    const telegramStatus = ref('disconnected');
    const phoneNumber = ref('');
    const accountName = ref('');
    const isSending = ref(false);
    const isUploading = ref(false);
    const error = ref('');
    const successMsg = ref('');
    const outOfCredits = ref(false);

    const form = ref({
      to: '',
      type: 'text',
      message: '',
      media_url: '',
    });

    const loadStatusAndCredits = async () => {
      try {
        const [statRes, subRes] = await Promise.all([
          api.get('/telegram/status'),
          api.get('/subscription'),
        ]);

        if (statRes.success && statRes.data) {
          telegramStatus.value = statRes.data.status || 'disconnected';
          phoneNumber.value = statRes.data.phoneNumber || '';
          accountName.value = statRes.data.name || '';
        }

        if (subRes.success) {
          const rem = subRes.data?.messages_remaining ?? 0;
          outOfCredits.value = rem <= 0 && subRes.data?.status !== 'active';
        }
      } catch (err) {
        console.error('Failed to load initial data:', err);
      }
    };

    const handleFileUpload = async (event) => {
      const file = event.target.files[0];
      if (!file) return;

      const formData = new FormData();
      formData.append('file', file);

      try {
        isUploading.value = true;
        const res = await api.post('/telegram/upload-media', formData);
        if (res.success) {
          form.value.media_url = res.url;
        }
      } catch (err) {
        error.value = 'فشل رفع الملف.';
      } finally {
        isUploading.value = false;
      }
    };

    const sendMessage = async () => {
      try {
        isSending.value = true;
        error.value = '';
        successMsg.value = '';

        const res = await api.post('/telegram/send-message', form.value);
        if (res.success) {
          successMsg.value = 'تم إرسال رسالة التيليجرام بنجاح!';
          form.value.to = '';
          form.value.message = '';
          form.value.media_url = '';
        }
      } catch (err) {
        error.value = err.data?.message || err.message || 'فشل إرسال الرسالة عبر Telegram.';
      } finally {
        isSending.value = false;
      }
    };

    onMounted(() => {
      loadStatusAndCredits();
    });

    return {
      telegramStatus,
      phoneNumber,
      accountName,
      isSending,
      isUploading,
      error,
      successMsg,
      outOfCredits,
      form,
      handleFileUpload,
      sendMessage,
    };
  },
};
</script>
