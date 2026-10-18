<template>
  <Transition
    enter-active-class="ease-out duration-300"
    enter-from-class="opacity-0 scale-95"
    enter-to-class="opacity-100 scale-100"
    leave-active-class="ease-in duration-200"
    leave-from-class="opacity-100 scale-100"
    leave-to-class="opacity-0 scale-95"
  >
    <div
      v-if="visible"
      class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
      @click.self="handleSkip"
    >
      <div
        class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full text-center shadow-2xl border border-slate-100 relative overflow-hidden"
        :dir="isRTL ? 'rtl' : 'ltr'"
      >
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-50/50 via-white to-purple-50/50 -z-10"></div>

        <div class="w-20 h-20 bg-indigo-600 text-white rounded-full flex items-center justify-center mx-auto mb-5 shadow-lg shadow-indigo-200">
          <span class="text-4xl">👋</span>
        </div>

        <h3 class="text-2xl font-black text-slate-900 mb-2">{{ texts.welcomeTitle }}</h3>
        <p class="text-slate-500 font-medium mb-6 leading-relaxed">{{ texts.welcomeDesc }}</p>

        <div class="flex flex-col sm:flex-row gap-3">
          <button
            @click="handleStart"
            class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-xl font-black text-sm shadow-lg shadow-indigo-100 transition-all active:scale-95"
          >
            {{ texts.welcomeStart }}
          </button>
          <button
            @click="handleSkip"
            class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 px-6 py-3 rounded-xl font-bold text-sm transition-all active:scale-95"
          >
            {{ texts.welcomeSkip }}
          </button>
        </div>

        <div class="mt-4">
          <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 cursor-pointer hover:text-slate-700">
            <input v-model="dontShow" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
            <span>{{ texts.welcomeDontShow }}</span>
          </label>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script>
import { ref, computed } from 'vue';
import { locale, isRTL } from '@/i18n';
import { TEXTS } from '@/composables/useOnboarding';

export default {
  name: 'OnboardingWelcome',
  props: {
    visible: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['start', 'skip'],
  setup(props, { emit }) {
    const dontShow = ref(true);

    const texts = computed(() => TEXTS[locale.value === 'ar' ? 'ar' : 'en']);

    const handleStart = () => {
      emit('start');
    };

    const handleSkip = () => {
      emit('skip', dontShow.value);
    };

    return {
      isRTL,
      texts,
      dontShow,
      handleStart,
      handleSkip,
    };
  },
};
</script>
