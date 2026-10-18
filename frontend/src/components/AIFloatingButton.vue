<template>
  <button
    v-if="isVisible"
    type="button"
    aria-label="Open Wavixa AI Voice"
    class="fixed bottom-6 left-6 z-50 flex items-center justify-center w-14 h-14 rounded-full bg-dark-900 text-emerald-500 shadow-emerald-lg border border-emerald-500/20 transition-all duration-300 hover:scale-110 hover:shadow-emerald focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
    :class="[isListening ? 'animate-pulse' : '']"
    @click="$emit('click')"
  >
    <svg
      class="w-6 h-6"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <path d="M12 2a3 3 0 0 1 3 3v7a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3Z" />
      <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
      <line x1="12" y1="19" x2="12" y2="22" />
    </svg>
    <span
      v-if="isListening"
      class="absolute inset-0 rounded-full bg-emerald-500/20 animate-ping"
    />
  </button>
</template>

<script>
import { computed } from 'vue';
import auth from '@/services/auth';

export default {
  name: 'AIFloatingButton',
  props: {
    isListening: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['click'],
  setup() {
    const isVisible = computed(() => {
      const user = auth.getUser();
      if (!user) return false;
      const publicPages = ['/login', '/register', '/forgot-password', '/verify-email', '/'];
      return !publicPages.includes(window.location.pathname);
    });

    return { isVisible };
  },
};
</script>
