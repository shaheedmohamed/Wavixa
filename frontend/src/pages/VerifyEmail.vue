<template>
  <div class="min-h-screen bg-[#F9FAFB] flex flex-col justify-center py-12 sm:px-6 lg:px-8 font-sans relative" :dir="isRTL ? 'rtl' : 'ltr'">
    <!-- Top Language Switcher -->
    <div class="absolute top-4" :class="isRTL ? 'left-4' : 'right-4'">
      <LanguageSwitcher />
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md">
      <div class="flex justify-center">
        <div class="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center text-white font-black text-2xl shadow-xl shadow-indigo-100 animate-in zoom-in duration-500">
          W
        </div>
      </div>
      <h2 class="mt-6 text-center text-3xl font-black text-gray-900 tracking-tight">{{ t('auth.verify_email_title') }}</h2>
      <p class="mt-2 text-center text-sm text-gray-500 font-medium">{{ isRTL ? 'خطوة أخيرة لتأكيد بريدك الإلكتروني' : 'Final step to verify your email address' }}</p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md animate-in slide-in-from-bottom-4 duration-500">
      <div class="bg-white py-10 px-6 shadow-sm border border-gray-100 sm:rounded-3xl sm:px-10">
        <div v-if="!verified" class="space-y-8">
          <div class="flex justify-center">
            <div class="w-20 h-20 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center border border-indigo-100 animate-pulse">
              <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
            </div>
          </div>
          
          <div class="text-center space-y-3">
            <p class="text-sm text-gray-600 font-medium leading-relaxed">
              {{ isRTL ? 'لقد أرسلنا رابط التفعيل إلى بريدك الإلكتروني. يرجى التحقق من صندوق الوارد والضغط على الرابط لتفعيل حسابك.' : 'We sent a verification link to your email address. Please check your inbox and click the link to activate your account.' }}
            </p>
            <div v-if="email" class="inline-block px-4 py-2 bg-gray-50 rounded-xl border border-gray-100">
              <span class="text-xs font-black text-indigo-600 font-mono" dir="ltr">{{ email }}</span>
            </div>
          </div>

          <div class="space-y-4 pt-4">
            <button
              @click="resendEmail"
              :disabled="isResending"
              class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-8 py-4 rounded-2xl font-black text-sm transition-all shadow-lg shadow-indigo-100 active:scale-95 flex items-center justify-center gap-3"
            >
              <svg v-if="isResending" class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              <span>{{ isResending ? (isRTL ? 'جاري الإرسال...' : 'Sending...') : (isRTL ? 'إعادة إرسال البريد' : 'Resend Email') }}</span>
            </button>

            <button
              @click="verifyManually"
              :disabled="isVerifying"
              class="w-full bg-white border border-gray-200 text-gray-700 px-8 py-4 rounded-2xl font-black text-sm hover:bg-gray-50 transition-all active:scale-95 flex items-center justify-center gap-3"
            >
              <span>{{ isVerifying ? (isRTL ? 'جاري التحقق...' : 'Verifying...') : (isRTL ? 'تفعيل مباشر (نسخة تجريبية)' : 'Direct Verification (Dev)') }}</span>
            </button>
          </div>

          <div v-if="message" class="p-4 bg-indigo-50 text-indigo-600 text-[10px] font-black rounded-2xl border border-indigo-100 animate-in slide-in-from-top-2">
            {{ message }}
          </div>

          <div v-if="error" class="p-4 bg-red-50 text-red-600 text-[10px] font-black rounded-2xl border border-red-100">
            {{ error }}
          </div>

          <div class="text-center">
            <router-link to="/login" class="text-xs font-black text-gray-400 hover:text-indigo-600 transition-colors flex items-center justify-center gap-2">
              <svg class="w-4 h-4" :class="isRTL ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
              {{ t('auth.back_to_login') }}
            </router-link>
          </div>
        </div>

        <div v-else class="text-center space-y-8 animate-in zoom-in duration-500">
          <div class="flex justify-center">
            <div class="w-20 h-20 bg-green-50 text-green-600 rounded-full flex items-center justify-center border border-green-100 shadow-sm">
              <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
            </div>
          </div>
          
          <div class="space-y-3">
            <h2 class="text-2xl font-black text-gray-900">{{ isRTL ? 'تم التفعيل بنجاح!' : 'Account Activated!' }}</h2>
            <p class="text-sm text-gray-500 font-medium">{{ isRTL ? 'تم التحقق من بريدك الإلكتروني بنجاح، يمكنك الآن استخدام كافة الميزات.' : 'Your email has been verified successfully. You can now access all features.' }}</p>
          </div>

          <router-link to="/login" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-4 rounded-2xl font-black text-sm transition-all shadow-lg shadow-indigo-100 active:scale-95 inline-block">
            {{ t('auth.login_button') }}
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import auth from '@/services/auth';
import { t, isRTL } from '@/i18n';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';

export default {
  name: 'VerifyEmail',
  components: { LanguageSwitcher },
  setup() {
    const router = useRouter();
    const verified = ref(false);
    const email = ref('');
    const message = ref('');
    const error = ref('');
    const isResending = ref(false);
    const isVerifying = ref(false);

    onMounted(() => {
      const user = auth.getUser();
      if (user?.email) {
        email.value = user.email;
      } else {
        const storedEmail = localStorage.getItem('pending_verification_email');
        if (storedEmail) {
          email.value = storedEmail;
        }
      }
    });

    const resendEmail = async () => {
      if (!email.value) {
        error.value = isRTL.value ? 'لم يتم العثور على البريد الإلكتروني' : 'Email not found';
        return;
      }
      isResending.value = true;
      error.value = '';
      message.value = '';
      try {
        const response = await api.post('/auth/resend-verification-email', { email: email.value });
        if (response.success) {
          message.value = isRTL.value ? 'تم إرسال رابط التفعيل، يرجى مراجعة بريدك' : 'Activation link sent, please check your inbox';
        } else {
          error.value = response.message || (isRTL.value ? 'فشل إرسال البريد' : 'Failed to send email');
        }
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل إرسال البريد الإلكتروني' : 'Failed to send email');
      } finally {
        isResending.value = false;
      }
    };

    const verifyManually = async () => {
      if (!email.value) {
        error.value = isRTL.value ? 'لم يتم العثور على البريد الإلكتروني' : 'Email not found';
        return;
      }
      isVerifying.value = true;
      error.value = '';
      message.value = '';
      try {
        const response = await api.post('/auth/verify-email-dev', { email: email.value });
        if (response.success) {
          verified.value = true;
          message.value = isRTL.value ? 'تم تفعيل الحساب بنجاح!' : 'Account activated successfully!';
          setTimeout(() => {
            router.push('/login');
          }, 2000);
        } else {
          error.value = response.message || (isRTL.value ? 'فشل تفعيل الحساب' : 'Failed to activate account');
        }
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل تفعيل الحساب' : 'Failed to activate account');
      } finally {
        isVerifying.value = false;
      }
    };

    return { verified, email, message, error, isResending, isVerifying, resendEmail, verifyManually, t, isRTL };
  },
};
</script>

<style scoped>
.font-sans {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
.animate-in {
  animation: animate-in 0.5s ease-out;
}
@keyframes animate-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
