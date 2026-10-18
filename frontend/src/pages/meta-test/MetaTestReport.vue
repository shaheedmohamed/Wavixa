<template>
  <div>
    <h2 class="text-2xl font-bold text-gray-900 tracking-tight mb-4">{{ t('meta.report.title') }}</h2>
    <div v-if="loading" class="text-gray-500">{{ t('meta.common.loading') }}</div>
    <div v-else-if="data" class="space-y-6">
      <section>
        <h3 class="font-medium text-green-800 mb-2">{{ t('meta.report.ready') }}</h3>
        <ul class="list-disc list-inside text-sm text-gray-700">
          <li v-for="item in data.ready" :key="item">{{ item }}</li>
        </ul>
      </section>

      <section v-if="data.missing.length">
        <h3 class="font-medium text-yellow-800 mb-2">{{ t('meta.report.missing') }}</h3>
        <ul class="list-disc list-inside text-sm text-yellow-700">
          <li v-for="item in data.missing" :key="item">{{ item }}</li>
        </ul>
      </section>

      <section v-if="data.meta_approval_required.length">
        <h3 class="font-medium text-orange-800 mb-2">{{ t('meta.report.approval') }}</h3>
        <ul class="list-disc list-inside text-sm text-orange-700">
          <li v-for="item in data.meta_approval_required" :key="item">{{ item }}</li>
        </ul>
      </section>

      <section v-if="data.technical_issues.length">
        <h3 class="font-medium text-red-800 mb-2">{{ t('meta.report.issues') }}</h3>
        <ul class="list-disc list-inside text-sm text-red-700">
          <li v-for="item in data.technical_issues" :key="item">{{ item }}</li>
        </ul>
      </section>

      <section>
        <h3 class="font-medium text-gray-800 mb-2">{{ t('meta.report.next') }}</h3>
        <ul class="list-disc list-inside text-sm text-gray-700">
          <li v-for="item in data.next_steps" :key="item">{{ item }}</li>
        </ul>
      </section>
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
    const res = await metaTest.report();
    data.value = res.data;
  } finally {
    loading.value = false;
  }
});
</script>
