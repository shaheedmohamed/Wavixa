<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ campaign?.name || t('meta.campaigns.title') }}</h2>
      <router-link to="/meta/campaigns" class="text-sm text-indigo-600 hover:underline">{{ t('meta.campaign_show.back') }}</router-link>
    </div>

    <div v-if="loading" class="text-gray-500">{{ t('meta.common.loading') }}</div>
    <div v-else-if="error" class="text-red-600">{{ error }}</div>

    <div v-else-if="campaign" class="space-y-6">
      <div class="flex items-center gap-3">
        <span :class="['px-2 py-1 rounded text-xs font-semibold uppercase', statusBadge(campaign.status)]">
          {{ campaign.status }}
        </span>
        <span class="text-sm text-gray-500" dir="ltr">
          {{ campaign.phone_number?.phone_number }} · {{ campaign.template?.name || campaign.message_type }}
        </span>
        <div class="ml-auto space-x-2">
          <button
            v-if="['draft', 'paused'].includes(campaign.status)"
            @click="launch"
            class="px-3 py-1.5 bg-green-600 text-white text-sm font-bold rounded-xl hover:bg-green-700"
          >{{ t('meta.campaign_show.launch') }}</button>
          <button
            v-if="campaign.status === 'running'"
            @click="pause"
            class="px-3 py-1.5 bg-yellow-500 text-white text-sm font-bold rounded-xl hover:bg-yellow-600"
          >{{ t('meta.campaign_show.pause') }}</button>
        </div>
      </div>

      <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">
        <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-500">{{ t('meta.campaign_show.total') }}</p><p class="font-semibold">{{ campaign.total_recipients }}</p></div>
        <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-500">{{ t('meta.campaign_show.pending') }}</p><p class="font-semibold">{{ campaign.pending_count }}</p></div>
        <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-500">{{ t('meta.campaign_show.sent') }}</p><p class="font-semibold text-blue-600">{{ campaign.sent_count }}</p></div>
        <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-500">{{ t('meta.campaign_show.delivered') }}</p><p class="font-semibold text-green-600">{{ campaign.delivered_count }}</p></div>
        <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-500">{{ t('meta.campaign_show.read') }}</p><p class="font-semibold text-emerald-600">{{ campaign.read_count }}</p></div>
        <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-500">{{ t('meta.campaign_show.failed') }}</p><p class="font-semibold text-red-600">{{ campaign.failed_count }}</p></div>
      </div>

      <div v-if="campaign.error_message" class="bg-red-50 border border-red-200 rounded-xl p-3 text-sm text-red-700">
        {{ campaign.error_message }}
      </div>

      <div>
        <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ t('meta.campaign_show.recipients') }}</h3>
        <div v-if="recipients.length" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
              <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.campaign_show.th_phone') }}</th>
                <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.campaign_show.th_name') }}</th>
                <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.campaign_show.th_status') }}</th>
                <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.campaign_show.th_error') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="r in recipients" :key="r.id">
                <td class="px-3 py-2 font-mono" dir="ltr">{{ r.phone }}</td>
                <td class="px-3 py-2">{{ r.name || '—' }}</td>
                <td class="px-3 py-2"><span :class="recipientStatusClass(r.status)">{{ r.status }}</span></td>
                <td class="px-3 py-2 text-red-600 text-xs">{{ r.error_message || '' }}</td>
              </tr>
            </tbody>
          </table>
          <div class="flex justify-between items-center mt-3 text-sm" v-if="pagination">
            <button
              :disabled="!pagination.prev_page_url"
              @click="loadRecipients(pagination.current_page - 1)"
              class="px-3 py-1 border rounded disabled:opacity-40"
            >{{ t('meta.common.prev') }}</button>
            <span class="text-gray-500">{{ t('meta.common.page') }} {{ pagination.current_page }} / {{ pagination.last_page }}</span>
            <button
              :disabled="!pagination.next_page_url"
              @click="loadRecipients(pagination.current_page + 1)"
              class="px-3 py-1 border rounded disabled:opacity-40"
            >{{ t('meta.common.next') }}</button>
          </div>
        </div>
        <div v-else class="text-gray-500 text-sm">{{ t('meta.campaign_show.no_recipients') }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import metaTest from '@/services/metaTest';
import { t } from '@/i18n';

const route = useRoute();
const loading = ref(true);
const error = ref(null);
const campaign = ref(null);
const recipients = ref([]);
const pagination = ref(null);
let poller = null;

const statusBadge = (s) => ({
  running: 'bg-blue-50 text-blue-700',
  completed: 'bg-green-50 text-green-700',
  failed: 'bg-red-50 text-red-700',
  paused: 'bg-yellow-50 text-yellow-700',
}[s] || 'bg-gray-100 text-gray-700');

const recipientStatusClass = (s) => ({
  sent: 'text-blue-600',
  delivered: 'text-green-600',
  read: 'text-emerald-600',
  failed: 'text-red-600',
}[s] || 'text-gray-500');

async function load() {
  try {
    const res = await metaTest.campaign(route.params.id);
    campaign.value = res.data;
    await loadRecipients(1);
  } catch (e) {
    error.value = e.data?.message || e.message;
  } finally {
    loading.value = false;
  }
}

async function loadRecipients(page) {
  const res = await metaTest.campaignRecipients(route.params.id, { page });
  const p = res.data;
  recipients.value = p?.data || [];
  pagination.value = p;
}

async function launch() {
  try {
    await metaTest.launchCampaign(campaign.value.id);
    await load();
  } catch (e) {
    error.value = e.data?.message || e.message;
  }
}

async function pause() {
  try {
    await metaTest.pauseCampaign(campaign.value.id);
    await load();
  } catch (e) {
    error.value = e.data?.message || e.message;
  }
}

onMounted(() => {
  load();
  poller = setInterval(() => {
    if (campaign.value?.status === 'running') {
      load();
    }
  }, 10000);
});

onUnmounted(() => clearInterval(poller));
</script>
