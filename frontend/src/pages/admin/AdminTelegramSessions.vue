<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/60">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">جلسات وبوتات Telegram</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">إدارة ومراقبة جميع بوتات Telegram المربوطة من قبل المستخدمين على النظام</p>
        </div>

        <div class="flex items-center gap-2">
          <button 
            @click="loadSessions(1)" 
            class="p-2 bg-white hover:bg-slate-50 text-slate-600 border border-slate-200 rounded-xl shadow-xs transition-colors cursor-pointer"
            title="تحديث القائمة"
          >
            <svg class="w-4 h-4" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Stats Summary -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">إجمالي البوتات المربوطة</p>
          <h3 class="text-2xl font-black text-slate-900">{{ counts.total || 0 }}</h3>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">البوتات المتصلة</p>
          <h3 class="text-2xl font-black text-emerald-600">{{ counts.connected || 0 }}</h3>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">البوتات غير المتصلة</p>
          <h3 class="text-2xl font-black text-slate-500">{{ counts.disconnected || 0 }}</h3>
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
              placeholder="ابحث باسم المستخدم، اسم البوت أو @username..."
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            />
          </div>
          <select
            v-model="status"
            @change="loadSessions(1)"
            class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all min-w-[160px]"
          >
            <option value="">جميع الحالات</option>
            <option value="connected">متصل (Connected)</option>
            <option value="disconnected">غير متصل (Disconnected)</option>
            <option value="error">خطأ (Error)</option>
          </select>
        </div>
      </div>

      <!-- Sessions Table Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">#</th>
                <th class="px-5 py-3.5">المستخدم</th>
                <th class="px-5 py-3.5">معلومات البوت</th>
                <th class="px-5 py-3.5">حالة الويب هوك</th>
                <th class="px-5 py-3.5">الحالة</th>
                <th class="px-5 py-3.5">الرسائل</th>
                <th class="px-5 py-3.5">تاريخ الربط</th>
                <th class="px-5 py-3.5 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="s in sessions" :key="s.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-4 text-slate-400 font-mono">{{ s.id }}</td>
                <td class="px-5 py-4">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-800 font-black text-[10px] flex items-center justify-center">
                      {{ (s.user?.name || 'U').charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <p class="font-bold text-slate-900">{{ s.user?.name ?? 'غير معروف' }}</p>
                      <p class="text-[10px] text-slate-400">{{ s.user?.email }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4">
                  <div class="font-bold text-slate-900">{{ s.first_name || s.device_name || 'Bot' }}</div>
                  <a :href="'https://t.me/' + s.bot_username" target="_blank" class="text-[10px] font-bold text-sky-600 hover:underline font-mono" dir="ltr">
                    @{{ s.bot_username }}
                  </a>
                </td>
                <td class="px-5 py-4">
                  <span :class="s.webhook_set ? 'text-emerald-600 font-bold' : 'text-amber-600 font-bold'">
                    {{ s.webhook_set ? '✓ مفعل' : '✗ غير مفعل' }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <span :class="statusClass(s.status)" class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold inline-flex items-center gap-1.5 border">
                    <span class="w-1.5 h-1.5 rounded-full" :class="s.status === 'connected' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"></span>
                    <span>{{ getStatusLabel(s.status) }}</span>
                  </span>
                </td>
                <td class="px-5 py-4 font-bold text-slate-700">
                  {{ s.messages_count || s.messages_sent_count || 0 }}
                </td>
                <td class="px-5 py-4 text-slate-500">{{ formatDate(s.connected_at) }}</td>
                <td class="px-5 py-4 text-center">
                  <div class="flex gap-2 items-center justify-center">
                    <button 
                      v-if="s.status === 'connected'"
                      @click="disconnectSession(s.id)" 
                      class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200/60 rounded-lg text-xs font-bold transition-all cursor-pointer shadow-2xs"
                    >
                      فصل
                    </button>
                    <button 
                      @click="deleteSession(s.id)" 
                      class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200/60 rounded-lg text-xs font-bold transition-all cursor-pointer shadow-2xs"
                    >
                      حذف
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="sessions.length === 0 && !loading">
                <td colspan="8" class="px-5 py-12 text-center text-slate-400 font-bold">لا توجد جلسات بوتات تيليجرام مطابقة</td>
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
  name: 'AdminTelegramSessions',
  components: { AdminLayout },
  setup() {
    const sessions = ref([]);
    const counts = ref({});
    const loading = ref(false);
    const search = ref('');
    const status = ref('');
    let searchTimeout = null;

    const loadSessions = async (page = 1) => {
      try {
        loading.value = true;
        let url = `/admin/telegram/sessions?page=${page}`;
        if (search.value) url += `&search=${encodeURIComponent(search.value)}`;
        if (status.value) url += `&status=${status.value}`;

        const res = await api.get(url);
        if (res.success) {
          sessions.value = res.data?.data || res.data || [];
          counts.value = res.counts || {};
        }
      } catch (err) {
        console.error('Failed to load admin telegram sessions:', err);
      } finally {
        loading.value = false;
      }
    };

    const debounceSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        loadSessions(1);
      }, 300);
    };

    const disconnectSession = async (id) => {
      if (!confirm('هل تريد فصل هذا البوت؟')) return;
      try {
        await api.post(`/admin/telegram/sessions/${id}/disconnect`);
        await loadSessions();
      } catch (err) {
        console.error('Failed to disconnect bot:', err);
      }
    };

    const deleteSession = async (id) => {
      if (!confirm('هل تريد بالتأكيد حذف جلسة هذا البوت نهائياً؟')) return;
      try {
        await api.delete(`/admin/telegram/sessions/${id}`);
        await loadSessions();
      } catch (err) {
        console.error('Failed to delete bot:', err);
      }
    };

    const statusClass = (st) => {
      switch (st) {
        case 'connected': return 'bg-emerald-50 text-emerald-700 border-emerald-200/60';
        case 'error': return 'bg-red-50 text-red-700 border-red-200/60';
        default: return 'bg-slate-50 text-slate-600 border-slate-200/60';
      }
    };

    const getStatusLabel = (st) => {
      switch (st) {
        case 'connected': return 'متصل';
        case 'error': return 'خطأ';
        default: return 'غير متصل';
      }
    };

    const formatDate = (d) => {
      if (!d) return '-';
      return new Date(d).toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    };

    onMounted(() => {
      loadSessions();
    });

    return {
      sessions,
      counts,
      loading,
      search,
      status,
      loadSessions,
      debounceSearch,
      disconnectSession,
      deleteSession,
      statusClass,
      getStatusLabel,
      formatDate,
    };
  },
};
</script>
