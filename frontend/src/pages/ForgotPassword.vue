<template>
  <div class="min-h-screen bg-[#020617] text-white flex flex-col justify-center py-6 px-3 sm:py-12 sm:px-6 lg:px-8 font-sans relative" :dir="isRTL ? 'rtl' : 'ltr'">
    <!-- Top Language Switcher -->
    <div class="absolute top-4" :class="isRTL ? 'left-4' : 'right-4'">
      <LanguageSwitcher />
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md">
      <div class="flex justify-center mb-6">
        <router-link to="/" class="flex items-center gap-2">
          <img :src="logo" alt="Wavixa" class="h-10 sm:h-12 w-auto" />
          <span class="text-2xl sm:text-3xl font-bold tracking-tight text-white">WAVIXA</span>
        </router-link>
      </div>

      <h2 class="text-center text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
        {{ step === 1 ? t('auth.forgot_password_title') : (showPasswordFields ? (isRTL ? 'تغيير كلمة المرور' : 'Set New Password') : (isRTL ? 'تأكيد الكود' : 'Verify Code')) }}
      </h2>
      <p class="mt-2 text-center text-sm text-gray-400">
        {{ step === 1 ? (isRTL ? 'أدخل رقم الموبايل المسجل لاستلام كود واتساب' : 'Enter registered phone number to receive WhatsApp code') : (isRTL ? `أدخل الكود المرسل إلى ${phone}` : `Enter the verification code sent to ${phone}`) }}
      </p>
    </div>

    <div class="mt-6 sm:mt-8 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white/[0.03] backdrop-blur-xl py-6 px-4 sm:py-10 sm:px-10 shadow-2xl border border-white/5 rounded-2xl sm:rounded-3xl">

        <!-- Step 1: Phone number -->
        <form v-if="step === 1" @submit.prevent="handleSendOtp" class="space-y-4 sm:space-y-6">
          <div class="space-y-2">
            <label for="phone" class="block text-xs font-bold text-gray-400 uppercase tracking-widest mr-1">{{ t('auth.phone') }}</label>
            <div class="relative group">
              <input
                id="phone"
                v-model="form.phone"
                type="tel"
                placeholder="201000000000"
                required
                :disabled="isLoading"
                class="w-full bg-white/5 border border-white/10 rounded-2xl px-4 py-3 sm:px-6 sm:py-4 text-white font-medium placeholder-gray-500 focus:bg-white/10 focus:border-emerald-500/50 transition-all outline-none font-mono"
                dir="ltr"
              />
            </div>
          </div>

          <div v-if="errors.general" class="p-3 sm:p-4 bg-red-500/10 text-red-400 text-xs font-bold rounded-2xl border border-red-500/20 text-center">
            {{ errors.general }}
          </div>

          <button
            type="submit"
            :disabled="isLoading"
            class="w-full bg-[#00C853] hover:bg-[#00E676] disabled:opacity-50 text-white px-4 py-3 sm:px-8 sm:py-4 rounded-2xl font-bold text-base sm:text-lg transition-all shadow-xl shadow-emerald-500/10 active:scale-95 flex items-center justify-center gap-3"
          >
            <svg v-if="isLoading" class="w-6 h-6 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>{{ isLoading ? (isRTL ? 'جاري الإرسال...' : 'Sending...') : (isRTL ? 'إرسال الكود' : 'Send Code') }}</span>
          </button>
        </form>

        <!-- Step 2: OTP and choose action -->
        <form v-else @submit.prevent="showPasswordFields ? handleReset : handleLogin" class="space-y-4 sm:space-y-6">
          <div class="space-y-2">
            <label for="code" class="block text-xs font-bold text-gray-400 uppercase tracking-widest mr-1">{{ isRTL ? 'كود التحقق' : 'Verification Code' }}</label>
            <input
              id="code"
              v-model="form.code"
              type="tel"
              inputmode="numeric"
              pattern="[0-9]*"
              maxlength="6"
              placeholder="000000"
              required
              :disabled="isLoading"
              @input="form.code = (form.code || '').replace(/\D/g, '').slice(0, 6)"
              class="w-full text-center bg-white/5 border border-white/10 rounded-2xl px-4 py-3 sm:px-6 sm:py-4 text-2xl sm:text-3xl font-black text-white tracking-[0.5em] placeholder-gray-500 focus:bg-white/10 focus:border-emerald-500/50 transition-all outline-none font-mono"
              dir="ltr"
            />
          </div>

          <div v-if="!showPasswordFields" class="grid grid-cols-2 gap-3">
            <button
              type="button"
              @click="handleLogin"
              :disabled="isLoading || form.code.length !== 6"
              class="w-full bg-[#00C853] hover:bg-[#00E676] disabled:opacity-50 text-white px-4 py-3 sm:px-6 sm:py-4 rounded-2xl font-bold text-sm transition-all shadow-xl shadow-emerald-500/10 active:scale-95 flex items-center justify-center gap-2"
            >
              {{ isRTL ? 'دخول' : 'Sign In' }}
            </button>
            <button
              type="button"
              @click="showPasswordFields = true"
              :disabled="isLoading || form.code.length !== 6"
              class="w-full bg-white/5 hover:bg-white/10 disabled:opacity-50 text-white px-4 py-3 sm:px-6 sm:py-4 rounded-2xl font-bold text-sm transition-all"
            >
              {{ isRTL ? 'تغيير كلمة المرور' : 'Change Password' }}
            </button>
          </div>

          <template v-if="showPasswordFields">
            <div class="space-y-2">
              <label for="password" class="block text-xs font-bold text-gray-400 uppercase tracking-widest mr-1">{{ isRTL ? 'كلمة المرور الجديدة' : 'New Password' }}</label>
              <input
                id="password"
                v-model="form.password"
                type="password"
                placeholder="••••••••"
                required
                :disabled="isLoading"
                class="w-full bg-white/5 border border-white/10 rounded-2xl px-4 py-3 sm:px-6 sm:py-4 text-white font-medium placeholder-gray-500 focus:bg-white/10 focus:border-emerald-500/50 transition-all outline-none"
                :class="isRTL ? 'text-right' : 'text-left'"
                dir="ltr"
              />
            </div>

            <div class="space-y-2">
              <label for="password_confirmation" class="block text-xs font-bold text-gray-400 uppercase tracking-widest mr-1">{{ t('auth.password_confirmation') }}</label>
              <input
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                placeholder="••••••••"
                required
                :disabled="isLoading"
                class="w-full bg-white/5 border border-white/10 rounded-2xl px-4 py-3 sm:px-6 sm:py-4 text-white font-medium placeholder-gray-500 focus:bg-white/10 focus:border-emerald-500/50 transition-all outline-none"
                :class="isRTL ? 'text-right' : 'text-left'"
                dir="ltr"
              />
            </div>

            <button
              type="submit"
              :disabled="isLoading"
              class="w-full bg-[#00C853] hover:bg-[#00E676] disabled:opacity-50 text-white px-4 py-3 sm:px-8 sm:py-4 rounded-2xl font-bold text-base sm:text-lg transition-all shadow-xl shadow-emerald-500/10 active:scale-95 flex items-center justify-center gap-3"
            >
              <svg v-if="isLoading" class="w-6 h-6 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ isLoading ? (isRTL ? 'جاري التغيير...' : 'Updating...') : (isRTL ? 'تغيير كلمة المرور ودخول' : 'Update Password & Sign In') }}</span>
            </button>

            <button
              type="button"
              @click="showPasswordFields = false"
              class="w-full bg-white/5 hover:bg-white/10 text-white px-4 py-3 sm:px-8 sm:py-4 rounded-2xl font-bold text-sm transition-all"
            >
              {{ isRTL ? 'رجوع' : 'Back' }}
            </button>
          </template>

          <div v-if="errors.general" class="p-3 sm:p-4 bg-red-500/10 text-red-400 text-xs font-bold rounded-2xl border border-red-500/20 text-center">
            {{ errors.general }}
          </div>

          <button
            type="button"
            @click="resendOtp"
            :disabled="isResending"
            class="w-full bg-white/5 hover:bg-white/10 disabled:opacity-50 text-white px-4 py-3 sm:px-8 sm:py-4 rounded-2xl font-bold text-sm transition-all"
          >
            {{ isResending ? (isRTL ? 'جاري إعادة الإرسال...' : 'Resending...') : t('auth.resend_otp') }}
          </button>
        </form>

        <div class="mt-6 sm:mt-10 border-t border-white/5 pt-6 sm:pt-8 text-center">
          <p class="text-[10px] text-gray-500 font-medium">
            <router-link to="/login" class="text-gray-300 hover:underline font-bold">{{ t('auth.back_to_login') }}</router-link>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import auth from '@/services/auth';
import { t, isRTL } from '@/i18n';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import logo from '@/assets/logo.png';

export default {
  name: 'ForgotPassword',
  components: { LanguageSwitcher },
  setup() {
    const router = useRouter();
    const isLoading = ref(false);
    const isResending = ref(false);
    const step = ref(1);
    const showPasswordFields = ref(false);
    const phone = ref('');
    const errors = ref({});
    const form = ref({
      phone: '',
      code: '',
      password: '',
      password_confirmation: '',
    });

    const handleSendOtp = async () => {
      errors.value = {};
      isLoading.value = true;

      try {
        const response = await api.post('/auth/forgot-password-phone', {
          phone_number: form.value.phone,
        });

        if (response.success) {
          step.value = 2;
          phone.value = form.value.phone;
        } else {
          errors.value.general = response.message || (isRTL.value ? 'فشل إرسال الكود' : 'Failed to send code');
        }
      } catch (error) {
        errors.value.general = error.data?.message || (isRTL.value ? 'فشل إرسال الكود' : 'Failed to send code');
      } finally {
        isLoading.value = false;
      }
    };

    const resendOtp = async () => {
      errors.value = {};
      isResending.value = true;

      try {
        const response = await api.post('/auth/forgot-password-phone', {
          phone_number: phone.value,
        });

        if (!response.success) {
          errors.value.general = response.message || (isRTL.value ? 'فشل إعادة إرسال الكود' : 'Failed to resend code');
        }
      } catch (error) {
        errors.value.general = error.data?.message || (isRTL.value ? 'فشل إعادة إرسال الكود' : 'Failed to resend code');
      } finally {
        isResending.value = false;
      }
    };

    const handleLogin = async () => {
      errors.value = {};
      isLoading.value = true;

      try {
        const response = await api.post('/auth/reset-password-phone', {
          phone_number: phone.value,
          code: form.value.code,
        });

        if (response.success) {
          api.setToken(response.data.token);
          auth.setUser(response.data.user);
          router.push(response.data.user?.is_admin ? '/admin' : '/dashboard');
        } else {
          errors.value.general = response.message || (isRTL.value ? 'فشل تسجيل الدخول' : 'Failed to sign in');
        }
      } catch (error) {
        errors.value.general = error.data?.message || (isRTL.value ? 'فشل تسجيل الدخول' : 'Failed to sign in');
      } finally {
        isLoading.value = false;
      }
    };

    const handleReset = async () => {
      errors.value = {};
      isLoading.value = true;

      try {
        const response = await api.post('/auth/reset-password-phone', {
          phone_number: phone.value,
          code: form.value.code,
          password: form.value.password,
          password_confirmation: form.value.password_confirmation,
        });

        if (response.success) {
          api.setToken(response.data.token);
          auth.setUser(response.data.user);
          router.push(response.data.user?.is_admin ? '/admin' : '/dashboard');
        } else {
          errors.value.general = response.message || (isRTL.value ? 'فشل تغيير كلمة المرور' : 'Failed to reset password');
        }
      } catch (error) {
        errors.value.general = error.data?.message || (isRTL.value ? 'فشل تغيير كلمة المرور' : 'Failed to reset password');
      } finally {
        isLoading.value = false;
      }
    };

    return {
      logo,
      form,
      errors,
      step,
      phone,
      showPasswordFields,
      isLoading,
      isResending,
      handleSendOtp,
      resendOtp,
      handleLogin,
      handleReset,
      t,
      isRTL,
    };
  },
};
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap');

.font-sans {
  font-family: 'Cairo', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
</style>
