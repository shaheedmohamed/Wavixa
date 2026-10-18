<template>
  <div class="min-h-screen bg-[#020617] text-white flex flex-col justify-center py-6 px-3 sm:py-12 sm:px-6 lg:px-8 font-sans relative" :dir="isRTL ? 'rtl' : 'ltr'">
    <!-- Top Right Language Switcher -->
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
      
      <div v-if="!showOtpStep">
        <h2 class="text-center text-2xl sm:text-3xl font-extrabold text-white tracking-tight">{{ t('auth.login_title') }}</h2>
        <p class="mt-2 text-center text-sm text-gray-400">
          {{ t('auth.or') }}
          <router-link to="/register" class="font-bold text-emerald-500 hover:text-emerald-400 transition-colors">{{ t('auth.create_account_free') }}</router-link>
        </p>
      </div>

      <div v-else>
        <h2 class="text-center text-2xl sm:text-3xl font-extrabold text-white tracking-tight">{{ isRTL ? 'تحقق من حسابك' : 'Verify Account' }}</h2>
        <p class="mt-2 text-center text-sm text-gray-400">
          {{ t('auth.enter_otp_desc') }} <br />
          <span class="text-white font-bold font-mono" dir="ltr">{{ phoneForOtp }}</span>
        </p>
      </div>
    </div>

    <div class="mt-6 sm:mt-8 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white/[0.03] backdrop-blur-xl py-6 px-4 sm:py-10 sm:px-10 shadow-2xl border border-white/5 rounded-2xl sm:rounded-3xl">
        
        <!-- Login Form -->
        <form v-if="!showOtpStep" @submit.prevent="handleLogin" class="space-y-4 sm:space-y-6">
          <div class="space-y-2">
            <label for="email" class="block text-xs font-bold text-gray-400 uppercase tracking-widest">{{ t('auth.email') }}</label>
            <div class="relative group">
              <input
                id="email"
                v-model="form.email"
                type="email"
                placeholder="name@company.com"
                required
                :disabled="isLoading"
                class="w-full bg-white/5 border border-white/10 rounded-2xl px-4 py-3 sm:px-6 sm:py-4 text-white font-medium placeholder-gray-500 focus:bg-white/10 focus:border-emerald-500/50 transition-all outline-none"
                :class="isRTL ? 'text-right' : 'text-left'"
                dir="ltr"
              />
            </div>
          </div>

          <div class="space-y-2">
            <div class="flex items-center justify-between px-1">
              <label for="password" class="block text-xs font-bold text-gray-400 uppercase tracking-widest">{{ t('auth.password') }}</label>
              <router-link to="/forgot-password" class="text-[10px] font-bold text-emerald-500 hover:text-emerald-400">{{ t('auth.forgot_password_link') }}</router-link>
            </div>
            <div class="relative group">
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
            <span>{{ isLoading ? t('auth.logging_in') : t('auth.login_button') }}</span>
          </button>
        </form>

        <!-- OTP Verification Step -->
        <form v-else @submit.prevent="handleVerifyOtp" class="space-y-8">
          <div class="flex justify-center gap-4 ltr">
             <input
              v-model="otpCode"
              type="text"
              maxlength="6"
              placeholder="000000"
              required
              class="w-full max-w-[200px] text-center bg-white/5 border border-white/10 rounded-2xl px-4 py-4 sm:px-6 sm:py-5 text-2xl sm:text-3xl font-black text-white tracking-[0.5em] focus:bg-white/10 focus:border-emerald-500/50 transition-all outline-none"
              autofocus
              dir="ltr"
            />
          </div>

          <div v-if="errors.otp" class="p-3 sm:p-4 bg-red-500/10 text-red-400 text-xs font-bold rounded-2xl border border-red-500/20 text-center">
            {{ errors.otp }}
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
            <span>{{ isLoading ? (isRTL ? 'جاري التحقق...' : 'Verifying...') : t('auth.verify_otp_btn') }}</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import auth from '@/services/auth';
import { t, isRTL } from '@/i18n';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import logo from '@/assets/logo.png';

export default {
  name: 'Login',
  components: { LanguageSwitcher },
  setup() {
    const router = useRouter();
    const isLoading = ref(false);
    const showOtpStep = ref(false);
    const phoneForOtp = ref('');
    const otpCode = ref('');
    const tempUserId = ref(null);

    const form = reactive({
      email: '',
      password: '',
    });

    const errors = reactive({
      email: '',
      password: '',
      general: '',
      otp: '',
    });

    const handleLogin = async () => {
      isLoading.value = true;
      errors.general = '';
      errors.email = '';
      errors.password = '';

      try {
        const response = await auth.login(form);

        if (response.success && response.data) {
          if (response.data.otp_required) {
            showOtpStep.value = true;
            phoneForOtp.value = response.data.user?.phone_number;
            tempUserId.value = response.data.user?.id;
          } else if (response.data.token) {
            const isAdmin = response.data.user?.is_admin === true;
            router.push(isAdmin ? '/admin' : '/dashboard');
          }
        } else {
          errors.general = response.message || (isRTL.value ? 'بيانات الاعتماد غير صحيحة' : 'Invalid email or password');
        }
      } catch (err) {
        errors.general = err.data?.message || err.response?.data?.message || (isRTL.value ? 'بيانات الاعتماد غير صحيحة' : 'Invalid email or password');
      } finally {
        isLoading.value = false;
      }
    };

    const handleVerifyOtp = async () => {
      isLoading.value = true;
      errors.otp = '';

      try {
        const response = await api.post('/auth/verify-otp', {
          email: form.email,
          code: otpCode.value,
        });

        if (response.success && response.data?.token) {
          api.setToken(response.data.token);
          auth.setUser(response.data.user);
          const isAdmin = response.data.user?.is_admin === true;
          router.push(isAdmin ? '/admin' : '/dashboard');
        } else {
          errors.otp = response.message || (isRTL.value ? 'رمز التحقق غير صحيح' : 'Invalid verification code');
        }
      } catch (err) {
        errors.otp = err.data?.message || err.response?.data?.message || (isRTL.value ? 'رمز التحقق غير صحيح' : 'Invalid verification code');
      } finally {
        isLoading.value = false;
      }
    };

    return {
      logo,
      form,
      errors,
      isLoading,
      showOtpStep,
      phoneForOtp,
      otpCode,
      handleLogin,
      handleVerifyOtp,
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
