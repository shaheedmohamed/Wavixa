<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('messenger_page.campaigns') }}</h2>
          <p class="text-gray-500 text-sm">حملات Messenger محدودة بالسياسة: يمكن الإرسال فقط ضمن نافذة الرسائل المفتوحة (24 ساعة).</p>
        </div>
        <router-link to="/messenger/campaigns/new" class="flex items-center gap-2 px-5 py-2.5 bg-[#0084FF] hover:bg-[#0073E6] text-white text-sm font-bold rounded-2xl transition-all shadow-md active:scale-95">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
          <span>إنشاء حملة</span>
        </router-link>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm"><p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">إجمالي الحملات</p><p class="text-xl font-black text-gray-900 leading-none">{{ stats.total }}</p></div>
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm"><p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">جارية</p><p class="text-xl font-black text-blue-600 leading-none">{{ stats.active }}</p></div>
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm"><p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">ناجحة</p><p class="text-xl font-black text-emerald-600 leading-none">{{ stats.successful }}</p></div>
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm"><p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">مستبعدة</p><p class="text-xl font-black text-amber-600 leading-none">{{ stats.excluded }}</p></div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-right">
            <thead>
              <tr class="bg-gray-50/50 border-b border-gray-50">
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase">اسم الحملة</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase">الصفحة</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase">المستلمين</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase">الحالة</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase">التاريخ</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-if="loading" class="animate-pulse"><td colspan="5" class="px-6 py-12 text-center text-gray-400 font-medium">{{ t('common.loading') }}</td></tr>
              <tr v-else-if="campaigns.length === 0"><td colspan="5" class="px-6 py-20 text-center text-gray-900 font-bold">لا توجد حملات.</td></tr>
              <tr v-else v-for="c in campaigns" :key="c.id" class="hover:bg-gray-50/50 transition-all cursor-pointer" @click="$router.push(`/messenger/campaigns/${c.id}`)">
                <td class="px-6 py-4 text-sm font-black text-gray-900">{{ c.name }}</td>
                <td class="px-6 py-4 text-xs text-gray-500">{{ c.page?.page_name || '-' }}</td>
                <td class="px-6 py-4 text-xs font-black text-gray-900">{{ c.total_recipients }}</td>
                <td class="px-6 py-4"><span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase" :class="statusClass(c.status)">{{ c.status }}</span></td>
                <td class="px-6 py-4 text-xs text-gray-400 font-bold">{{ formatDate(c.created_at) }}</td>
              </tr>
            </tbody>
          </table>
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
  name: 'MessengerCampaigns',
  components: { DashboardLayout },
  setup() {
    const campaigns = ref([]);
    const stats = ref({ total: 0, active: 0, successful: 0, excluded: 0 });
    const loading = ref(true);

    const load = async () => {
      try {
        loading.value = true;
        const [listRes, statsRes] = await Promise.all([
          api.get('/messenger/campaigns'),
          api.get('/messenger/campaigns/stats'),
        ]);
        if (listRes.success) campaigns.value = listRes.data.data || [];
        if (statsRes.success) stats.value = statsRes.data;
      } catch (err) {
        console.error('Failed to load campaigns:', err);
      } finally {
        loading.value = false;
      }
    };

    const statusClass = (status) => ({
      draft: 'bg-gray-100 text-gray-600',
      running: 'bg-blue-50 text-blue-600',
      paused: 'bg-amber-50 text-amber-600',
      completed: 'bg-emerald-50 text-emerald-600',
      failed: 'bg-red-50 text-red-600',
    }[status] || 'bg-gray-100 text-gray-600');

    const formatDate = (d) => d ? new Date(d).toLocaleDateString('ar-EG') : '-';

    onMounted(load);

    return { campaigns, stats, loading, statusClass, formatDate, t };
  },
};
</script>
