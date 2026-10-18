<template>
  <div>
    <h2 class="text-2xl font-bold text-gray-900 tracking-tight mb-4">{{ t('meta.status.title') }}</h2>
    <div v-if="loading" class="text-gray-500">{{ t('meta.common.loading') }}</div>
    <div v-else-if="data" class="space-y-2">
      <div
        v-for="check in data.checks"
        :key="check.name"
        class="flex items-center justify-between border border-gray-100 rounded-xl p-3"
      >
        <span class="font-medium">{{ check.name }}</span>
        <span :class="check.ok ? 'text-green-600' : 'text-red-600'">
          {{ check.ok ? '✅' : '❌' }}
        </span>
      </div>
      <div v-if="!data.all_ok" class="mt-4 text-sm text-yellow-700 bg-yellow-50 p-3 rounded">
        {{ t('meta.status.failing') }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import metaTest from '@/services/metaTest';
import { t } from '@/i18n';

const loading = ref(true);
const data = ref(null);

onMounted(async () => {
  try {
    const res = await metaTest.status();
    data.value = res.data;
  } finally {
    loading.value = false;
  }
});
</script>
