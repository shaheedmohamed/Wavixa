<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/60">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">إدارة حملات Telegram</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">مراقبة وإدارة جميع حملات تيليجرام الإعلانية والجماعية لكافة المستخدمين</p>
        </div>

        <div class="flex items-center gap-2">
          <button 
            @click="loadCampaigns(1)" 
            class="p-2 bg-white hover:bg-slate-50 text-slate-600 border border-slate-200 rounded-xl shadow-xs transition-colors cursor-pointer"
            title="تحديث القائمة"
          >
            <svg class="w-4 h-4" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">إجمالي الحملات</p>
          <h3 class="text-2xl font-black text-slate-900">{{ counts.total || 0 }}</h3>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">حملات قيد الإرسال</p>
          <h3 class="text-2xl font-black text-emerald-600">{{ counts.running || 0 }}</h3>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">حملات مكتملة</p>
          <h3 class="text-2xl font-black text-indigo-600">{{ counts.completed || 0 }}</h3>
        </div>
      </div>

      <!-- Filters Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
        <div class="flex flex-col md:flex-row gap-4">
          <div class="flex-1 relative">
            <input
              v-model="search"
              @input="debounceSearch"
              type="text"
              placeholder="ابحث باسم الحملة أو اسم المستخدم..."
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            />
          </div>
          <select
            v-model="status"
            @change="loadCampaigns(1)"
            class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all min-w-[160px]"
          >
            <option value="">جميع الحالات</option>
            <option value="running">قيد الإرسال (Running)</option>
            <option value="completed">مكتملة (Completed)</option>
            <option value="paused">متوقفة (Paused)</option>
            <option value="failed">فشلت (Failed)</option>
            <option value="draft">مسودة (Draft)</option>
          </select>
        </div>
      </div>

      <!-- Campaigns Table Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-right text-xs">
            <thead>
              <tr class="text-slate-400 text-[10px] font-black border-b border-slate-100 bg-slate-50/50 uppercase">
                <th class="px-5 py-3.5">#</th>
                <th class="px-5 py-3.5">اسم الحملة</th>
                <th class="px-5 py-3.5">المستخدم</th>
                <th class="px-5 py-3.5">البوت</th>
                <th class="px-5 py-3.5">المستلمون</th>
                <th class="px-5 py-3.5">النجاح / الفشل</th>
                <th class="px-5 py-3.5">الحالة</th>
                <th class="px-5 py-3.5">تاريخ الإنشاء</th>
                <th class="px-5 py-3.5 text-center">إجراءات</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="c in campaigns" :key="c.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-4 text-slate-400 font-mono">{{ c.id }}</td>
                <td class="px-5 py-4 font-bold text-slate-900">{{ c.name }}</td>
                <td class="px-5 py-4 font-bold text-slate-700">{{ c.user?.name ?? 'غير معروف' }}</td>
                <td class="px-5 py-4 font-mono text-sky-600 font-bold" dir="ltr">@{{ c.session?.bot_username || '-' }}</td>
                <td class="px-5 py-4 font-bold text-slate-900">{{ c.total_recipients || 0 }}</td>
                <td class="px-5 py-4">
                  <span class="text-emerald-600 font-bold">{{ c.success_count || 0 }}</span> / 
                  <span class="text-red-600 font-bold">{{ c.failed_count || 0 }}</span>
                </td>
                <td class="px-5 py-4">
                  <span :class="statusClass(c.status)" class="px-2.5 py-1 rounded-full text-[10px] font-extrabold inline-flex items-center gap-1 border">
                    <span v-if="c.status === 'running'" class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>{{ statusText(c.status) }}</span>
                  </span>
                </td>
                <td class="px-5 py-4 text-slate-500 whitespace-nowrap">{{ formatDate(c.created_at) }}</td>
                <td class="px-5 py-4 text-center">
                  <button
                    @click="deleteCampaign(c.id)"
                    class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                    title="حذف الحملة"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                  </button>
                </td>
              </tr>
              <tr v-if="campaigns.length === 0 && !loading">
                <td colspan="9" class="px-5 py-12 text-center text-slate-400 font-bold">لا توجد حملات تيليجرام مطابقة للبحث</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script>
import { ref, onMounted } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import api from '@/services/api';

export default {
  name: 'AdminTelegramCampaigns',
  components: { AdminLayout },
  setup() {
    const campaigns = ref([]);
    const counts = ref({});
    const loading = ref(false);
    const search = ref('');
    const status = ref('');
    let searchTimeout = null;

    const loadCampaigns = async (page = 1) => {
      try {
        loading.value = true;
        let url = `/admin/telegram/campaigns?page=${page}`;
        if (search.value) url += `&search=${encodeURIComponent(search.value)}`;
        if (status.value) url += `&status=${status.value}`;

        const res = await api.get(url);
        if (res.success) {
          campaigns.value = res.data?.data || res.data || [];
          counts.value = res.counts || {};
        }
      } catch (err) {
        console.error('Failed to load admin telegram campaigns:', err);
      } finally {
        loading.value = false;
      }
    };

    const debounceSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        loadCampaigns(1);
      }, 300);
    };

    const deleteCampaign = async (id) => {
      if (!confirm('هل تريد حذف هذه الحملة؟')) return;
      try {
        await api.delete(`/admin/telegram/campaigns/${id}`);
        await loadCampaigns();
      } catch (err) {
        console.error('Failed to delete campaign:', err);
      }
    };

    const statusClass = (status) => {
      switch (status) {
        case 'running': return 'bg-emerald-50 text-emerald-700 border-emerald-200/60';
        case 'completed': return 'bg-indigo-50 text-indigo-700 border-indigo-200/60';
        case 'paused': return 'bg-amber-50 text-amber-700 border-amber-200/60';
        case 'failed': return 'bg-red-50 text-red-700 border-red-200/60';
        default: return 'bg-slate-50 text-slate-700 border-slate-200/60';
      }
    };

    const statusText = (status) => {
      switch (status) {
        case 'running': return 'جارٍ الإرسال';
        case 'completed': return 'مكتملة';
        case 'paused': return 'متوقفة';
        case 'failed': return 'فشلت';
        case 'draft': return 'مسودة';
        default: return status;
      }
    };

    const formatDate = (d) => {
      if (!d) return '-';
      return new Date(d).toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    };

    onMounted(() => {
      loadCampaigns();
    });

    return {
      campaigns,
      counts,
      loading,
      search,
      status,
      loadCampaigns,
      debounceSearch,
      deleteCampaign,
      statusClass,
      statusText,
      formatDate,
    };
  },
};
</script>
