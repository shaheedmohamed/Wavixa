<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('meta.campaigns.title') }}</h2>
      <router-link
        to="/meta/campaigns/new"
        class="px-3 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700"
      >
        New Campaign
      </router-link>
    </div>

    <div v-if="stats" class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
      <div class="bg-gray-50 rounded-xl p-3">
        <p class="text-xs text-gray-500 uppercase">{{ t('meta.campaigns.stat_campaigns') }}</p>
        <p class="text-lg font-semibold">{{ stats.total }}</p>
      </div>
      <div class="bg-gray-50 rounded-xl p-3">
        <p class="text-xs text-gray-500 uppercase">{{ t('meta.campaigns.stat_sent') }}</p>
        <p class="text-lg font-semibold text-green-600">{{ stats.sent }}</p>
      </div>
      <div class="bg-gray-50 rounded-xl p-3">
        <p class="text-xs text-gray-500 uppercase">{{ t('meta.campaigns.stat_failed') }}</p>
        <p class="text-lg font-semibold text-red-600">{{ stats.failed }}</p>
      </div>
      <div class="bg-gray-50 rounded-xl p-3">
        <p class="text-xs text-gray-500 uppercase">{{ t('meta.campaigns.stat_credits') }}</p>
        <p class="text-lg font-semibold">{{ stats.credits_remaining }}</p>
      </div>
    </div>

    <div v-if="loading" class="text-gray-500">{{ t('meta.common.loading') }}</div>
    <div v-else-if="error" class="text-red-600">{{ error }}</div>

    <div v-else-if="campaigns.length" class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead>
          <tr>
            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.campaigns.th_name') }}</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.campaigns.th_number') }}</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.campaigns.th_type') }}</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.campaigns.th_progress') }}</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.campaigns.th_status') }}</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="c in campaigns" :key="c.id">
            <td class="px-3 py-2 font-medium">
              <router-link :to="`/meta/campaigns/${c.id}`" class="text-indigo-600 hover:underline">
                {{ c.name }}
              </router-link>
            </td>
            <td class="px-3 py-2" dir="ltr">{{ c.phone_number?.phone_number || '—' }}</td>
            <td class="px-3 py-2">{{ c.template?.name || c.message_type }}</td>
            <td class="px-3 py-2">
              {{ (c.sent_count || 0) + (c.delivered_count || 0) + (c.read_count || 0) + (c.failed_count || 0) }}/{{ c.total_recipients }}
            </td>
            <td class="px-3 py-2"><span :class="statusClass(c.status)">{{ c.status }}</span></td>
            <td class="px-3 py-2 space-x-2 whitespace-nowrap">
              <button
                v-if="['draft', 'paused'].includes(c.status)"
                @click="launch(c)"
                class="text-green-600 hover:underline"
              >{{ t('meta.campaigns.launch') }}</button>
              <button
                v-if="c.status === 'running'"
                @click="pause(c)"
                class="text-yellow-600 hover:underline"
              >{{ t('meta.campaigns.pause') }}</button>
              <button
                v-if="['draft', 'failed', 'completed'].includes(c.status)"
                @click="remove(c)"
                class="text-red-600 hover:underline"
              >{{ t('meta.campaigns.delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else class="text-gray-500">{{ t('meta.campaigns.empty') }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import metaTest from '@/services/metaTest';
import { t } from '@/i18n';

const loading = ref(true);
const error = ref(null);
const campaigns = ref([]);
const stats = ref(null);

const statusClass = (s) => ({
  running: 'text-blue-600',
  completed: 'text-green-600',
  failed: 'text-red-600',
  paused: 'text-yellow-600',
}[s] || 'text-gray-600');

async function load() {
  try {
    const [cRes, sRes] = await Promise.all([metaTest.campaigns(), metaTest.campaignStats()]);
    campaigns.value = cRes.data?.data || cRes.data || [];
    stats.value = sRes.data;
  } catch (e) {
    error.value = e.data?.message || e.message;
  } finally {
    loading.value = false;
  }
}

async function launch(c) {
  try {
    await metaTest.launchCampaign(c.id);
    await load();
  } catch (e) {
    error.value = e.data?.message || e.message;
  }
}

async function pause(c) {
  try {
    await metaTest.pauseCampaign(c.id);
    await load();
  } catch (e) {
    error.value = e.data?.message || e.message;
  }
}

async function remove(c) {
  if (!confirm(`${t('meta.campaigns.delete_confirm')} "${c.name}"?`)) return;
  try {
    await metaTest.deleteCampaign(c.id);
    await load();
  } catch (e) {
    error.value = e.data?.message || e.message;
  }
}

onMounted(load);
</script>
