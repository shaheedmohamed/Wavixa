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
        <h2 class="text-center text-2xl sm:text-3xl font-extrabold text-white tracking-tight">{{ t('auth.register_title') }}</h2>
        <p class="mt-2 text-center text-sm text-gray-400">
          {{ t('auth.already_have_account') }}
          <router-link to="/login" class="font-bold text-emerald-500 hover:text-emerald-400 transition-colors">{{ t('auth.login_button') }}</router-link>
        </p>
      </div>

      <div v-else>
        <h2 class="text-center text-2xl sm:text-3xl font-extrabold text-white tracking-tight">{{ isRTL ? 'تحقق من رقمك' : 'Verify Your Phone' }}</h2>
        <p class="mt-2 text-center text-sm text-gray-400">
          {{ t('auth.enter_otp_desc') }} <br />
          <span class="text-white font-bold font-mono" dir="ltr">{{ form.phone_number }}</span>
        </p>
      </div>
    </div>

    <div class="mt-6 sm:mt-8 sm:mx-auto sm:w-full sm:max-w-lg">
      <div class="bg-white/[0.03] backdrop-blur-xl py-6 px-4 sm:py-10 sm:px-10 shadow-2xl border border-white/5 rounded-2xl sm:rounded-3xl">
        <!-- Registration Form -->
        <form v-if="!showOtpStep" @submit.prevent="handleRegister" class="space-y-4 sm:space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
            <div class="space-y-2">
              <label for="name" class="block text-xs font-bold text-gray-400 uppercase tracking-widest mr-1">{{ t('auth.name') }}</label>
              <input
                id="name"
                v-model="form.name"
                type="text"
                :placeholder="isRTL ? 'الاسم الثلاثي' : 'Full Name'"
                required
                :disabled="isLoading"
                class="w-full bg-white/5 border border-white/10 rounded-2xl px-4 py-3 sm:px-6 sm:py-4 text-white font-medium placeholder-gray-500 focus:bg-white/10 focus:border-emerald-500/50 transition-all outline-none"
                :class="isRTL ? 'text-right' : 'text-left'"
              />
              <p v-if="errors.name" class="text-[10px] text-red-400 font-bold mt-1">{{ errors.name[0] }}</p>
            </div>

            <div class="space-y-2">
              <label for="phone" class="block text-xs font-bold text-gray-400 uppercase tracking-widest mr-1">{{ t('auth.phone') }}</label>
              <input
                id="phone"
                v-model="form.phone_number"
                type="tel"
                placeholder="+20 10..."
                required
                :disabled="isLoading"
                class="w-full bg-white/5 border border-white/10 rounded-2xl px-4 py-3 sm:px-6 sm:py-4 text-white font-medium placeholder-gray-500 focus:bg-white/10 focus:border-emerald-500/50 transition-all outline-none font-mono"
                dir="ltr"
              />
              <p class="text-[10px] text-gray-500">{{ isRTL ? 'مطلوب للتحقق من الحساب' : 'Required for OTP verification' }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <label for="email" class="block text-xs font-bold text-gray-400 uppercase tracking-widest mr-1">{{ t('auth.email') }}</label>
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
            <p v-if="errors.email" class="text-[10px] text-red-400 font-bold mt-1">{{ errors.email[0] }}</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
            <div class="space-y-2">
              <label for="password" class="block text-xs font-bold text-gray-400 uppercase tracking-widest mr-1">{{ t('auth.password') }}</label>
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
          </div>
          <p v-if="errors.password" class="text-[10px] text-red-400 font-bold mt-1">{{ errors.password[0] }}</p>

          <div v-if="errors.general" class="p-3 sm:p-4 bg-red-500/10 text-red-400 text-xs font-bold rounded-2xl border border-red-500/20">
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
            <span>{{ isLoading ? t('auth.registering') : t('auth.register_button') }}</span>
          </button>
        </form>

        <!-- OTP Verification Step -->
        <form v-else @submit.prevent="handleVerifyOtp" class="space-y-6 sm:space-y-8">
          <div class="flex justify-center gap-4 ltr">
             <input
              v-model="otpCode"
              type="text"
              maxlength="6"
              placeholder="000000"
              required
              class="w-full max-w-[200px] text-center bg-white/5 border border-white/10 rounded-2xl px-4 py-4 sm:px-6 sm:py-5 text-2xl sm:text-3xl font-black text-white tracking-[0.5em] focus:bg-white/10 focus:border-emerald-500/50 transition-all outline-none font-mono"
              autofocus
              dir="ltr"
            />
          </div>

          <div v-if="errors.otp" class="p-3 sm:p-4 bg-red-500/10 text-red-400 text-xs font-bold rounded-2xl border border-red-500/20 text-center">
            {{ errors.otp }}
          </div>

          <div v-if="otpMessage" class="p-3 sm:p-4 bg-emerald-500/10 text-emerald-400 text-xs font-bold rounded-2xl border border-emerald-500/20 text-center">
            {{ otpMessage }}
          </div>

          <div class="space-y-4">
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

            <button
              type="button"
              @click="handleResendOtp"
              :disabled="isLoading || resendCountdown > 0"
              class="w-full bg-white/5 hover:bg-white/10 text-white px-4 py-3 sm:px-8 sm:py-4 rounded-2xl font-bold text-sm transition-all disabled:opacity-50"
            >
              {{ resendCountdown > 0 ? (isRTL ? `إعادة إرسال خلال ${resendCountdown} ثانية` : `Resend in ${resendCountdown}s`) : t('auth.resend_otp') }}
            </button>
          </div>
          
          <p class="text-center">
            <button @click="showOtpStep = false" class="text-xs text-gray-500 hover:text-white transition-colors">{{ isRTL ? 'العودة لتعديل البيانات' : 'Back to edit information' }}</button>
          </p>
        </form>

        <div class="mt-6 sm:mt-10 border-t border-white/5 pt-6 sm:pt-8 text-center">
          <p class="text-[10px] text-gray-500 font-medium">
            {{ isRTL ? 'بالمتابعة أنت توافق على شروط الخدمة وسياسة الخصوصية' : 'By signing up, you agree to our Terms of Service & Privacy Policy' }}
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
  name: 'Register',
  components: { LanguageSwitcher },
  setup() {
    const router = useRouter();
    const isLoading = ref(false);
    const showOtpStep = ref(false);
    const otpCode = ref('');
    const otpMessage = ref('');
    const resendCountdown = ref(0);
    const form = ref({
      name: '',
      email: '',
      phone_number: '',
      password: '',
      password_confirmation: '',
    });
    const errors = ref({});

    const handleRegister = async () => {
      errors.value = {};
      isLoading.value = true;

      try {
        const response = await api.post('/auth/register', form.value);
        if (response.success) {
          showOtpStep.value = true;
          startCountdown();
        } else {
          errors.value.general = response.message || (isRTL.value ? 'فشل إنشاء الحساب' : 'Failed to register account');
        }
      } catch (error) {
        if (error.data?.errors) {
          errors.value = error.data.errors;
        } else if (error.data?.message) {
          errors.value.general = error.data.message;
        } else {
          errors.value.general = isRTL.value ? 'حدث خطأ ما، يرجى المحاولة لاحقاً' : 'An error occurred, please try again';
        }
      } finally {
        isLoading.value = false;
      }
    };

    const handleVerifyOtp = async () => {
      errors.value = {};
      isLoading.value = true;

      try {
        const response = await api.post('/auth/verify-otp', {
          email: form.value.email,
          code: otpCode.value
        });

        if (response.success) {
          api.setToken(response.data.token);
          auth.setUser(response.data.user);

          router.push('/dashboard');
        } else {
          errors.value.otp = response.message;
        }
      } catch (error) {
        errors.value.otp = error.data?.message || (isRTL.value ? 'كود التحقق غير صحيح' : 'Invalid OTP code');
      } finally {
        isLoading.value = false;
      }
    };

    const handleResendOtp = async () => {
      isLoading.value = true;
      try {
        const response = await api.post('/auth/resend-otp', { email: form.value.email });
        if (response.success) {
          otpMessage.value = isRTL.value ? 'تم إعادة إرسال الكود بنجاح' : 'Code resent successfully';
          startCountdown();
          setTimeout(() => otpMessage.value = '', 3000);
        }
      } catch (error) {
        errors.value.otp = error.data?.message || (isRTL.value ? 'فشل إرسال الكود' : 'Failed to resend code');
      } finally {
        isLoading.value = false;
      }
    };

    const startCountdown = () => {
      resendCountdown.value = 60;
      const timer = setInterval(() => {
        resendCountdown.value--;
        if (resendCountdown.value <= 0) clearInterval(timer);
      }, 1000);
    };

    return {
      logo,
      form,
      errors,
      isLoading,
      showOtpStep,
      otpCode,
      otpMessage,
      resendCountdown,
      handleRegister,
      handleVerifyOtp,
      handleResendOtp,
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
