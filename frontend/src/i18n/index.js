import { ref, computed, watch } from 'vue';
import ar from './locales/ar.json';
import en from './locales/en.json';

const STORAGE_KEY = 'wavixa-locale';
const SUPPORTED = ['ar', 'en'];

const messages = { ar, en };

const saved = localStorage.getItem(STORAGE_KEY);
const locale = ref(SUPPORTED.includes(saved) ? saved : 'ar');

const isRTL = computed(() => locale.value === 'ar');

function t(key, fallback = key) {
  const parts = String(key).split('.');
  let obj = messages[locale.value];
  if (!obj) return fallback;
  for (const p of parts) {
    if (obj && typeof obj === 'object' && p in obj) {
      obj = obj[p];
    } else {
      return fallback;
    }
  }
  return typeof obj === 'string' ? obj : fallback;
}

function setLocale(value) {
  if (SUPPORTED.includes(value) && value !== locale.value) {
    locale.value = value;
    localStorage.setItem(STORAGE_KEY, value);
  }
}

function applyLocale(value) {
  document.documentElement.lang = value;
  document.documentElement.dir = value === 'ar' ? 'rtl' : 'ltr';
}

watch(locale, applyLocale, { immediate: true });

export { locale, isRTL, t, setLocale, messages };
