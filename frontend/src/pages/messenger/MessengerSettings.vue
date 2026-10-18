<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500 max-w-4xl mx-auto">
      <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('messenger_page.settings') }}</h2>

      <div v-if="loading" class="py-12 text-center text-gray-400 text-sm font-medium">{{ t('common.loading') }}</div>
      <div v-else-if="!page" class="p-6 bg-amber-50 border border-amber-100 rounded-2xl text-amber-800 text-xs font-bold">
        لا توجد صفحة متصلة. <router-link to="/messenger/connect" class="underline">ربط صفحة</router-link>
      </div>

      <div v-else class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-6">
        <div class="flex items-start gap-4">
          <div class="w-14 h-14 rounded-2xl bg-blue-50 text-[#0084FF] flex items-center justify-center font-black text-xl">
            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08s5.97 1.09 6 3.08c-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
          </div>
          <div>
            <p class="text-lg font-black text-gray-900">{{ page.page_name || page.page_id }}</p>
            <p class="text-[10px] font-bold text-gray-400 font-mono" dir="ltr">{{ page.page_id }}</p>
            <p class="text-[10px] font-bold mt-1" :class="page.status === 'connected' ? 'text-emerald-600' : 'text-gray-500'">{{ page.status }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100"><p class="text-[10px] font-bold text-gray-400 uppercase">إجمالي الرسائل</p><p class="text-xl font-black text-gray-900">{{ stats.total_messages }}</p></div>
          <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100"><p class="text-[10px] font-bold text-gray-400 uppercase">المرسلة</p><p class="text-xl font-black text-gray-900">{{ stats.messages_sent }}</p></div>
          <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100"><p class="text-[10px] font-bold text-gray-400 uppercase">المستلمة</p><p class="text-xl font-black text-gray-900">{{ stats.messages_received }}</p></div>
          <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100"><p class="text-[10px] font-bold text-gray-400 uppercase">المحادثات</p><p class="text-xl font-black text-gray-900">{{ stats.total_conversations }}</p></div>
        </div>

        <div class="pt-4 border-t border-gray-100 flex gap-3">
          <button @click="disconnect" :disabled="disconnecting" class="px-6 py-3 bg-red-50 text-red-600 text-xs font-black rounded-2xl border border-red-100 hover:bg-red-100 transition-all">
            {{ disconnecting ? '...' : 'فصل الصفحة' }}
          </button>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import api from '@/services/api';
import { t } from '@/i18n';

export default {
  name: 'MessengerSettings',
  components: { DashboardLayout },
  setup() {
    const router = useRouter();
    const page = ref(null);
    const stats = ref({});
    const loading = ref(true);
    const disconnecting = ref(false);

    const load = async () => {
      try {
        const statusRes = await api.get('/messenger/status');
        if (statusRes.success) {
          const pages = statusRes.data.pages || [];
          if (pages.length) {
            const res = await api.get(`/messenger/settings/${pages[0].id}`);
            if (res.success) { page.value = res.data.page; stats.value = res.data.stats; }
          }
        }
      } catch (err) {
        console.error('Failed to load settings:', err);
      } finally {
        loading.value = false;
      }
    };

    const disconnect = async () => {
      try {
        disconnecting.value = true;
        await api.post(`/messenger/disconnect/${page.value.id}`);
        router.push('/messenger');
      } catch (err) {
        console.error('Disconnect failed:', err);
      } finally {
        disconnecting.value = false;
      }
    };

    onMounted(load);

    return { page, stats, loading, disconnecting, disconnect, t };
  },
};
</script>
