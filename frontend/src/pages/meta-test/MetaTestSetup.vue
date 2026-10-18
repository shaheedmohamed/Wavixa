<template>
  <div>
    <h2 class="text-2xl font-bold text-gray-900 tracking-tight mb-4">{{ t('meta.setup.title') }}</h2>

    <div v-if="loading" class="text-gray-500">{{ t('meta.common.loading') }}</div>
    <div v-else-if="error" class="text-red-600">{{ error }}</div>
    <div v-else-if="data" class="space-y-4">
      <div
        v-for="item in data.items"
        :key="item.name"
        class="border border-gray-100 rounded-xl p-4 flex items-start gap-3"
      >
        <span class="text-2xl">{{ statusIcon(item.status) }}</span>
        <div>
          <p class="font-medium text-gray-900">{{ item.name }}</p>
          <p class="text-sm text-gray-600">{{ item.message }}</p>
          <pre v-if="item.details" class="mt-2 text-xs bg-gray-50 p-2 rounded overflow-auto">{{ JSON.stringify(item.details, null, 2) }}</pre>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import metaTest from '@/services/metaTest';
import { t } from '@/i18n';

const loading = ref(true);
const error = ref(null);
const data = ref(null);

const statusIcon = (status) => {
  if (['ok', 'valid', 'configured'].includes(status)) return '✅';
  if (status === 'review_required') return '⚠️';
  return '❌';
};

onMounted(async () => {
  try {
    const res = await metaTest.setup();
    data.value = res.data;
  } catch (e) {
    error.value = e.data?.message || e.message;
  } finally {
    loading.value = false;
  }
});
</script>
