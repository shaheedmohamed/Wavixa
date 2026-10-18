<template>
  <transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="opacity-0 -translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition duration-200 ease-in"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 -translate-y-2"
  >
    <div
      v-if="isLoading"
      class="fixed top-4 right-4 z-[9999] flex items-center gap-3 rounded-2xl bg-slate-900/80 px-4 py-2.5 text-sm font-bold text-white shadow-2xl backdrop-blur-md"
      dir="rtl"
    >
      <div class="loader-ring" />
      <span class="font-bold">جاري التحميل...</span>
    </div>
  </transition>
</template>

<script setup>
import { isLoading } from '@/stores/loader';
</script>

<style scoped>
.loader-ring {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: conic-gradient(from 0deg, #8B5CF6, #00C853, #8B5CF6);
  -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 4px), #000 calc(100% - 4px));
  mask: radial-gradient(farthest-side, transparent calc(100% - 4px), #000 calc(100% - 4px));
  animation: spin 0.9s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
