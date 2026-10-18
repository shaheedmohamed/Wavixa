<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="w-2.5 h-2.5 rounded-full bg-[#229ED9]"></span>
            <h2 class="text-2xl font-bold text-gray-900 tracking-tight">حملات Telegram الإعلانية والجماعية</h2>
          </div>
          <p class="text-gray-500 text-sm">إرسال حملات تسويقية وإشعارات جماعية عبر بوتات تيليجرام لآلاف المشتركين والعملاء.</p>
        </div>
        <div>
          <router-link
            to="/telegram/campaigns/new"
            class="inline-flex items-center gap-2 bg-[#229ED9] hover:bg-[#1c8ec4] text-white px-5 py-3 rounded-2xl font-black text-sm transition-all shadow-lg shadow-sky-100 active:scale-95"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>إنشاء حملة جديدة</span>
          </router-link>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">إجمالي الحملات</p>
          <h3 class="text-2xl font-black text-gray-900">{{ stats.total || 0 }}</h3>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">حملات نشطة</p>
          <h3 class="text-2xl font-black text-emerald-600">{{ stats.active || 0 }}</h3>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">حملات مكتملة</p>
          <h3 class="text-2xl font-black text-indigo-600">{{ stats.completed || 0 }}</h3>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">إجمالي الرسائل</p>
          <h3 class="text-2xl font-black text-gray-900">{{ Number(stats.total_messages || 0).toLocaleString() }}</h3>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">الرسائل الناجحة</p>
          <h3 class="text-2xl font-black text-emerald-600">{{ Number(stats.successful || 0).toLocaleString() }}</h3>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">الرسائل الفاشلة</p>
          <h3 class="text-2xl font-black text-red-600">{{ Number(stats.failed || 0).toLocaleString() }}</h3>
        </div>
      </div>

      <!-- Campaigns Table Container -->
      <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-50 bg-gray-50/30 flex items-center justify-between">
          <h3 class="text-sm font-black text-gray-900">سجل حملات Telegram</h3>
        </div>

        <div v-if="loading" class="py-12 flex justify-center">
          <svg class="w-8 h-8 text-[#229ED9] animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>

        <div v-else-if="campaigns.length > 0" class="overflow-x-auto">
          <table class="w-full border-collapse text-right">
            <thead class="bg-gray-50/50">
              <tr>
                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">اسم الحملة</th>
                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">البوت المرسل</th>
                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">المستلمون</th>
                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">نسبة الإنجاز</th>
                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">الحالة</th>
                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">تاريخ الإنشاء</th>
                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider text-left">إجراءات</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="c in campaigns" :key="c.id" class="hover:bg-gray-50/50 transition-colors">
                <td class="px-6 py-4">
                  <div class="font-bold text-gray-900 text-xs">{{ c.name }}</div>
                  <span class="text-[10px] text-gray-400">{{ c.type === 'photo' ? 'صورة ومرفق' : (c.type === 'document' ? 'ملف مستند' : 'رسالة نصية') }}</span>
                </td>
                <td class="px-6 py-4 text-xs font-bold text-gray-700 font-mono" dir="ltr">
                  @{{ c.session?.bot_username || '-' }}
                </td>
                <td class="px-6 py-4 text-xs font-black text-gray-900">
                  {{ c.total_recipients || 0 }}
                </td>
                <td class="px-6 py-4">
                  <div class="w-36 space-y-1">
                    <div class="flex items-center justify-between text-[10px] font-bold text-gray-500">
                      <span>{{ c.success_count || 0 }} ناجح</span>
                      <span>{{ c.failed_count || 0 }} فشل</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden flex">
                      <div
                        class="bg-emerald-500 h-full"
                        :style="{ width: getProgressPercent(c) + '%' }"
                      ></div>
                      <div
                        class="bg-red-500 h-full"
                        :style="{ width: getFailedPercent(c) + '%' }"
                      ></div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <span
                    :class="[
                      'px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider inline-flex items-center gap-1',
                      statusClass(c.status)
                    ]"
                  >
                    <span v-if="c.status === 'running'" class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ statusText(c.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 text-xs text-gray-500 font-medium">
                  {{ formatDate(c.created_at) }}
                </td>
                <td class="px-6 py-4 text-left">
                  <div class="flex items-center justify-end gap-2">
                    <router-link
                      :to="'/telegram/campaigns/' + c.id"
                      class="px-3 py-1.5 bg-gray-50 hover:bg-gray-100 text-gray-700 text-xs font-bold rounded-xl border border-gray-200/60 transition-colors"
                    >
                      عرض التفاصيل
                    </router-link>
                    <button
                      @click="deleteCampaign(c)"
                      class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                      title="حذف الحملة"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <div v-else class="p-12 text-center">
          <div class="w-16 h-16 bg-sky-50 text-[#229ED9] rounded-2xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 003.5 0V5.882M17 9.176V19.24a1.76 1.76 0 003.5 0V9.176M5 14.294V19.24a1.76 1.76 0 003.5 0v-4.946" /></svg>
          </div>
          <h4 class="text-base font-black text-gray-900 mb-1">لا توجد حملات تيليجرام بعد</h4>
          <p class="text-xs text-gray-400 max-w-sm mx-auto mb-6">ابدأ بإنشاء حملتك الإعلانية الأولى عبر تيليجرام واستهدف آلاف المستخدمين والعملاء بضغطة زر واحدة.</p>
          <router-link
            to="/telegram/campaigns/new"
            class="inline-flex items-center gap-2 bg-[#229ED9] hover:bg-[#1c8ec4] text-white px-5 py-2.5 rounded-xl font-bold text-xs transition-all shadow-sm active:scale-95"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>إنشاء أول حملة تيليجرام</span>
          </router-link>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, onMounted } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import api from '@/services/api';

export default {
  name: 'TelegramCampaigns',
  components: { DashboardLayout },
  setup() {
    const campaigns = ref([]);
    const stats = ref({});
    const loading = ref(true);

    const loadData = async () => {
      try {
        loading.value = true;
        const [campRes, statsRes] = await Promise.all([
          api.get('/telegram/campaigns'),
          api.get('/telegram/campaigns/stats'),
        ]);

        if (campRes.success) {
          campaigns.value = campRes.data?.data || campRes.data || [];
        }
        if (statsRes.success) {
          stats.value = statsRes.data || {};
        }
      } catch (err) {
        console.error('Failed to load campaigns:', err);
      } finally {
        loading.value = false;
      }
    };

    const deleteCampaign = async (c) => {
      if (!confirm(`هل أنت متأكد من حذف الحملة "${c.name}"؟`)) return;
      try {
        await api.delete(`/telegram/campaigns/${c.id}`);
        await loadData();
      } catch (err) {
        console.error('Failed to delete campaign:', err);
      }
    };

    const getProgressPercent = (c) => {
      if (!c.total_recipients) return 0;
      return Math.round(((c.success_count || 0) / c.total_recipients) * 100);
    };

    const getFailedPercent = (c) => {
      if (!c.total_recipients) return 0;
      return Math.round(((c.failed_count || 0) / c.total_recipients) * 100);
    };

    const statusClass = (status) => {
      switch (status) {
        case 'running': return 'bg-emerald-50 text-emerald-700 border border-emerald-200/60';
        case 'completed': return 'bg-indigo-50 text-indigo-700 border border-indigo-200/60';
        case 'paused': return 'bg-amber-50 text-amber-700 border border-amber-200/60';
        case 'failed': return 'bg-red-50 text-red-700 border border-red-200/60';
        default: return 'bg-gray-100 text-gray-700';
      }
    };

    const statusText = (status) => {
      switch (status) {
        case 'running': return 'جارٍ الإرسال';
        case 'completed': return 'مكتملة';
        case 'paused': return 'متوقفة مؤقتاً';
        case 'failed': return 'فشلت';
        case 'draft': return 'مسودة';
        default: return status;
      }
    };

    const formatDate = (d) => {
      if (!d) return '-';
      return new Date(d).toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric' });
    };

    onMounted(() => {
      loadData();
    });

    return {
      campaigns,
      stats,
      loading,
      deleteCampaign,
      getProgressPercent,
      getFailedPercent,
      statusClass,
      statusText,
      formatDate,
    };
  },
};
</script>
