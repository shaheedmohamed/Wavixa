<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('meta.accounts.title') }}</h2>
      <button
        @click="sync"
        :disabled="syncing"
        class="px-3 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 disabled:opacity-50"
      >
        {{ syncing ? t('meta.common.syncing') : t('meta.common.sync') }}
      </button>
    </div>

    <div v-if="loading" class="text-gray-500">{{ t('meta.common.loading') }}</div>
    <div v-else-if="error" class="text-red-600">{{ error }}</div>
    <div v-else-if="accounts.length" class="space-y-4">
      <div v-for="account in accounts" :key="account.id" class="border border-gray-100 rounded-xl p-4">
        <p class="font-medium text-gray-900">{{ account.name || t('meta.accounts.unnamed') }}</p>
        <p class="text-sm text-gray-600">{{ t('meta.accounts.waba_id') }}: {{ account.meta_waba_id }}</p>
        <p class="text-sm text-gray-600">{{ t('meta.common.status') }}: {{ account.status }}</p>
        <p class="text-sm text-gray-600">{{ t('meta.accounts.timezone') }}: {{ account.timezone || '—' }} | {{ t('meta.accounts.currency') }}: {{ account.currency || '—' }}</p>
        <div v-if="account.phone_numbers?.length" class="mt-3">
          <p class="text-sm font-medium text-gray-700">{{ t('meta.accounts.numbers_title') }}</p>
          <ul class="list-disc list-inside text-sm text-gray-600">
            <li v-for="phone in account.phone_numbers" :key="phone.id">
              {{ phone.display_name || phone.phone_number }} ({{ phone.status }})
            </li>
          </ul>
        </div>
      </div>
    </div>
    <div v-else class="text-gray-500">{{ t('meta.accounts.empty') }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import metaTest from '@/services/metaTest';
import { t } from '@/i18n';

const loading = ref(true);
const syncing = ref(false);
const error = ref(null);
const accounts = ref([]);

async function load() {
  try {
    const res = await metaTest.accounts();
    accounts.value = res.data;
  } catch (e) {
    error.value = e.data?.message || e.message;
  } finally {
    loading.value = false;
  }
}

async function sync() {
  syncing.value = true;
  error.value = null;
  try {
    const res = await metaTest.syncAccounts();
    accounts.value = res.data;
  } catch (e) {
    error.value = e.data?.message || e.message;
  } finally {
    syncing.value = false;
  }
}

onMounted(load);
</script>
