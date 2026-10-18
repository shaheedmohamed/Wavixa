<template>
  <div>
    <h2 class="text-2xl font-bold text-gray-900 tracking-tight mb-4">{{ isRTL ? 'لوحة WhatsApp الرسمي' : 'WhatsApp Official Dashboard' }}</h2>

    <!-- Support strip -->
    <div class="mb-5 rounded-2xl border border-emerald-200 bg-gradient-to-l from-emerald-50 to-teal-50 p-4 flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
      <div class="flex items-start gap-3">
        <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-4 4v-4z"/></svg>
        </div>
        <div>
          <p class="font-bold text-emerald-900 text-sm">{{ isRTL ? 'محتاج مساعدة في الربط أو الإرسال؟' : 'Need help with setup or sending?' }}</p>
          <p class="text-xs text-emerald-700 mt-0.5">{{ isRTL ? 'لو واجهت أي صعوبة في ربط حساب ميتا أو إرسال الرسايل، فريقنا متاح يرد عليك مباشرة على واتساب.' : 'If you face any difficulty connecting your Meta account or sending messages, our team is available on WhatsApp.' }}</p>
        </div>
      </div>
      <a :href="whatsappUrl" target="_blank" rel="noopener"
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#25D366] hover:bg-[#1ebe5b] text-white text-sm font-bold rounded-xl shadow-sm transition-colors shrink-0">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2zm0 18.12c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31c-.82-1.31-1.26-2.83-1.26-4.38 0-4.54 3.7-8.24 8.25-8.24 4.54 0 8.24 3.7 8.24 8.24 0 4.55-3.7 8.25-8.24 8.25zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.78.97-.14.16-.29.18-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.15.16-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.22.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.15-1.18-.07-.1-.23-.16-.48-.27z"/></svg>
        {{ isRTL ? 'تواصل معنا واتساب' : 'Chat on WhatsApp' }}
      </a>
    </div>

    <div v-if="loading" class="text-gray-500">{{ t('meta.common.loading') }}</div>
    <div v-else-if="error" class="text-red-600">{{ error }}</div>
    <div v-else-if="data" class="space-y-6">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-gray-50 rounded-xl p-4">
          <p class="text-xs text-gray-500 uppercase">{{ t('meta.dashboard.connection') }}</p>
          <p class="text-lg font-semibold" :class="connectionColor">{{ data.connection_status }}</p>
        </div>
        <div class="bg-gray-50 rounded-xl p-4">
          <p class="text-xs text-gray-500 uppercase">{{ t('meta.dashboard.wabas') }}</p>
          <p class="text-lg font-semibold">{{ data.waba_count }}</p>
        </div>
        <div class="bg-gray-50 rounded-xl p-4">
          <p class="text-xs text-gray-500 uppercase">{{ t('meta.dashboard.phone_numbers') }}</p>
          <p class="text-lg font-semibold">{{ data.total_phone_numbers }}</p>
        </div>
      </div>

      <div class="bg-white border border-gray-100 rounded-xl p-4 space-y-2 text-sm">
        <div class="flex justify-between"><span>{{ t('meta.dashboard.app_id') }}</span> <span class="font-mono">{{ data.app_id || '—' }}</span></div>
        <div class="flex justify-between"><span>{{ t('meta.dashboard.api_version') }}</span> <span class="font-mono">{{ data.api_version }}</span></div>
        <div class="flex justify-between"><span>{{ t('meta.dashboard.graph_url') }}</span> <span class="font-mono">{{ data.graph_url }}</span></div>
        <div class="flex justify-between"><span>{{ t('meta.dashboard.redirect_uri') }}</span> <span class="font-mono break-all">{{ data.redirect_uri }}</span></div>
        <div class="flex justify-between"><span>{{ t('meta.dashboard.webhook_url') }}</span> <span class="font-mono break-all">{{ data.webhook_url }}</span></div>
        <div class="flex justify-between"><span>{{ t('meta.dashboard.setup') }}</span> <span :class="data.setup_status === 'configured' ? 'text-green-600' : 'text-yellow-600'">{{ data.setup_status }}</span></div>
      </div>

      <div v-if="data.last_error" class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm">
        <p class="font-semibold text-red-700">{{ t('meta.dashboard.last_error') }}</p>
        <p class="text-red-600">{{ data.last_error.meta_error_message || 'Unknown error' }}</p>
        <p class="text-gray-500">{{ data.last_error.endpoint }} — {{ data.last_error.created_at }}</p>
      </div>

      <div v-if="data.requires_action?.length" class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
        <p class="font-semibold text-yellow-800">{{ t('meta.dashboard.requires_action') }}</p>
        <ul class="list-disc list-inside text-sm text-yellow-700 mt-1">
          <li v-for="item in data.requires_action" :key="item.name">{{ item.name }}: {{ item.message }}</li>
        </ul>
      </div>

      <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
        <p class="font-semibold text-indigo-900 mb-1">{{ t('meta.dashboard.api_title') }}</p>
        <p class="text-sm text-indigo-700">
          {{ t('meta.dashboard.api_desc') }}
        </p>
        <div class="mt-2 flex flex-wrap gap-3 text-sm">
          <router-link to="/api-keys" class="text-indigo-700 font-medium hover:underline">{{ t('meta.dashboard.api_key_link') }}</router-link>
          <a href="/api-docs#meta-group" class="text-indigo-700 font-medium hover:underline">{{ t('meta.dashboard.api_docs_link') }}</a>
        </div>
        <pre class="mt-3 bg-slate-900 text-emerald-400 text-xs rounded p-3 overflow-x-auto" dir="ltr">curl -X POST {{ baseUrl }}/api/public/meta/send-template \
  -H "X-API-Key: YOUR_API_KEY" -H "Content-Type: application/json" \
  -d '{"to": "+201012345678", "template": "order_confirmation", "variables": ["Ahmed", "95412"]}'</pre>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import metaTest from '@/services/metaTest';
import { isRTL, t } from '@/i18n';

const loading = ref(true);
const error = ref(null);
const data = ref(null);

const whatsappUrl = computed(() => {
  const text = isRTL.value
    ? 'مرحبًا، محتاج مساعدة في ربط حساب WhatsApp Business (Meta) في منصة Wavixa'
    : 'Hi, I need help connecting my WhatsApp Business (Meta) account on Wavixa';
  return `https://wa.me/201011679726?text=${encodeURIComponent(text)}`;
});

const connectionColor = computed(() => {
  if (!data.value) return 'text-gray-500';
  if (data.value.connection_status === 'connected') return 'text-green-600';
  if (data.value.connection_status === 'none') return 'text-gray-500';
  return 'text-yellow-600';
});

const baseUrl = window.location.origin;

onMounted(async () => {
  try {
    const res = await metaTest.dashboard();
    data.value = res.data;
  } catch (e) {
    error.value = e.data?.message || e.message;
  } finally {
    loading.value = false;
  }
});
</script>
