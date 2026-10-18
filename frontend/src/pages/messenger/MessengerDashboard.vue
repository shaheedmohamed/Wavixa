<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="w-2.5 h-2.5 rounded-full bg-[#0084FF]"></span>
            <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('page_titles.messenger_dashboard') }}</h2>
          </div>
          <p class="text-gray-500 text-sm">{{ t('messenger_page.subtitle') }}</p>
        </div>
        <router-link
          to="/messenger/connect"
          class="flex items-center gap-2 px-5 py-2.5 bg-[#0084FF] hover:bg-[#0073E6] text-white text-sm font-bold rounded-2xl transition-all shadow-md active:scale-95"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
          <span>{{ t('messenger_page.connect') }}</span>
        </router-link>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">{{ t('messenger_page.connected_pages') }}</p>
          <p class="text-xl font-black text-gray-900 leading-none">{{ stats.connected_pages }}</p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">{{ t('messages_page.total_messages') }}</p>
          <p class="text-xl font-black text-gray-900 leading-none">{{ stats.total_messages }}</p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">{{ t('dashboard.new_contacts') }}</p>
          <p class="text-xl font-black text-gray-900 leading-none">{{ stats.total_contacts }}</p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">{{ t('campaigns_page.th_success') }}</p>
          <p class="text-xl font-black text-emerald-600 leading-none">{{ stats.sent }}</p>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
          <h3 class="text-sm font-black text-gray-900">{{ t('messenger_page.connected_pages') }}</h3>
          <div class="flex items-center gap-3">
            <router-link to="/messenger/ads" class="text-xs font-bold text-[#0084FF] hover:underline">+ إنشاء إعلان</router-link>
            <router-link to="/messenger/connect" class="text-xs font-bold text-[#0084FF] hover:underline">+ {{ t('messenger_page.connect') }}</router-link>
          </div>
        </div>
        <div class="p-6">
          <div v-if="loading" class="py-12 text-center text-gray-400 text-sm font-medium">{{ t('common.loading') }}</div>
          <div v-else-if="pages.length === 0" class="py-12 text-center space-y-4">
            <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto text-[#0084FF]">
              <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08s5.97 1.09 6 3.08c-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
            </div>
            <p class="text-gray-900 font-bold">{{ t('messenger_page.no_pages') }}</p>
            <router-link to="/messenger/connect" class="inline-block px-6 py-2.5 bg-[#0084FF] hover:bg-[#0073E6] text-white text-xs font-black rounded-xl transition-all shadow-md">
              {{ t('messenger_page.connect_now') }}
            </router-link>
          </div>
          <div v-else class="space-y-3">
            <div v-for="page in pages" :key="page.id" class="flex items-center justify-between p-4 rounded-2xl border border-gray-100 hover:bg-gray-50/50 transition-all">
              <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0084FF] flex items-center justify-center font-black">
                  <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08s5.97 1.09 6 3.08c-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
                </div>
                <div>
                  <p class="text-sm font-black text-gray-900">{{ page.page_name || page.page_id }}</p>
                  <p class="text-[10px] font-bold text-gray-400 font-mono" dir="ltr">{{ page.page_id }}</p>
                </div>
              </div>
              <div class="flex items-center gap-3">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black" :class="page.status === 'connected' ? 'bg-emerald-50 text-emerald-600' : 'bg-gray-100 text-gray-500'">
                  {{ page.status === 'connected' ? t('messenger_page.active') : t('messenger_page.inactive') }}
                </span>
                <button @click="disconnect(page.id)" :disabled="disconnecting === page.id" class="px-3 py-1.5 bg-red-50 text-red-600 text-xs font-bold rounded-lg hover:bg-red-100 transition-all">
                  {{ t('messenger_page.disconnect') }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, onMounted } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import api from '@/services/api';
import { t } from '@/i18n';

export default {
  name: 'MessengerDashboard',
  components: { DashboardLayout },
  setup() {
    const pages = ref([]);
    const loading = ref(true);
    const disconnecting = ref(null);
    const stats = ref({ connected_pages: 0, total_messages: 0, total_contacts: 0, sent: 0 });

    const load = async () => {
      try {
        const [statusRes, statsRes] = await Promise.all([
          api.get('/messenger/status'),
          api.get('/messenger/dashboard-stats'),
        ]);
        if (statusRes.success) pages.value = statusRes.data.pages || [];
        if (statsRes.success) stats.value = statsRes.data;
      } catch (err) {
        console.error('Failed to load Messenger status:', err);
      } finally {
        loading.value = false;
      }
    };

    const disconnect = async (id) => {
      try {
        disconnecting.value = id;
        await api.post(`/messenger/disconnect/${id}`);
        await load();
      } catch (err) {
        console.error('Disconnect failed:', err);
      } finally {
        disconnecting.value = null;
      }
    };

    onMounted(load);

    return { pages, loading, disconnecting, stats, disconnect, t };
  },
};
</script>
