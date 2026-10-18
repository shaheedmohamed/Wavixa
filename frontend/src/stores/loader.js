import { ref, computed } from 'vue';

const activeRequests = ref(0);

export const isLoading = computed(() => activeRequests.value > 0);

export function startLoading() {
  activeRequests.value++;
}

export function finishLoading() {
  if (activeRequests.value > 0) {
    activeRequests.value--;
  }
}
