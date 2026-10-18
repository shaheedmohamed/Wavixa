<template>
  <div>
    <h2 class="text-2xl font-bold text-gray-900 tracking-tight mb-4">{{ t('meta.logs.title') }}</h2>
    <div v-if="loading" class="text-gray-500">{{ t('meta.common.loading') }}</div>
    <div v-else-if="logs.length" class="space-y-3">
      <div
        v-for="log in logs"
        :key="log.id"
        class="border border-gray-100 rounded-xl p-3 text-sm"
        :class="log.success ? 'bg-white' : 'bg-red-50'"
      >
        <div class="flex justify-between">
          <span class="font-mono">{{ log.method }} {{ log.endpoint }}</span>
          <span :class="log.success ? 'text-green-600' : 'text-red-600'">{{ log.http_status }}</span>
        </div>
        <p class="text-gray-500">{{ log.created_at }} | {{ log.duration_ms }}ms</p>
        <p v-if="log.meta_error_message" class="text-red-600 mt-1">{{ log.meta_error_message }}</p>
      </div>
    </div>
    <div v-else class="text-gray-500">{{ t('meta.logs.empty') }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import metaTest from '@/services/metaTest';
import { t } from '@/i18n';

const loading = ref(true);
const logs = ref([]);

onMounted(async () => {
  try {
    const res = await metaTest.logs();
    logs.value = res.data?.data || res.data;
  } finally {
    loading.value = false;
  }
});
</script>
