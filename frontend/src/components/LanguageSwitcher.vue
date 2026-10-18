<template>
  <div class="inline-flex items-center p-1 bg-slate-100/90 rounded-2xl border border-slate-200/80 shadow-2xs">
    <button
      type="button"
      @click="setLocale('ar')"
      class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all duration-200"
      :class="locale === 'ar'
        ? 'bg-white text-indigo-600 shadow-xs font-black ring-1 ring-black/5'
        : 'text-slate-500 hover:text-slate-900'"
      title="العربية"
    >
      <span class="text-sm leading-none">🇪🇬</span>
      <span class="hidden sm:inline">العربية</span>
      <span class="sm:hidden text-[10px] uppercase font-black">AR</span>
    </button>
    <button
      type="button"
      @click="setLocale('en')"
      class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all duration-200"
      :class="locale === 'en'
        ? 'bg-white text-indigo-600 shadow-xs font-black ring-1 ring-black/5'
        : 'text-slate-500 hover:text-slate-900'"
      title="English"
    >
      <span class="text-sm leading-none">🇬🇧</span>
      <span class="hidden sm:inline">English</span>
      <span class="sm:hidden text-[10px] uppercase font-black">EN</span>
    </button>
  </div>
</template>

<script>
import { locale, isRTL, setLocale, t } from '@/i18n';

export default {
  name: 'LanguageSwitcher',
  setup() {
    return { locale, isRTL, setLocale, t };
  },
};
</script>
