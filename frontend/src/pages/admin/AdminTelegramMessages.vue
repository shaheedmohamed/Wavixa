<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/60">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">سجلات رسائل Telegram</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">سجل ومراقبة كافة رسائل تيليجرام الصادرة والواردة عبر المنصة</p>
        </div>

        <div class="flex items-center gap-2">
          <button 
            @click="loadMessages(1)" 
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
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <p class="text-[11px] font-bold text-slate-400 mb-1">إجمالي الرسائل</p>
          <p class="text-xl font-black text-slate-900">{{ counts.total || 0 }}</p>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <p class="text-[11px] font-bold text-slate-400 mb-1">مرسلة بنجاح</p>
          <p class="text-xl font-black text-emerald-600">{{ counts.sent || 0 }}</p>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <p class="text-[11px] font-bold text-slate-400 mb-1">رسائل فاشلة</p>
          <p class="text-xl font-black text-red-600">{{ counts.failed || 0 }}</p>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <p class="text-[11px] font-bold text-slate-400 mb-1">رسائل واردة</p>
          <p class="text-xl font-black text-sky-600">{{ counts.incoming || 0 }}</p>
        </div>
      </div>

      <!-- Filters Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-bold text-slate-500 mb-1.5">البحث السريع</label>
            <input
              v-model="search"
              @input="debounceSearch"
              type="text"
              placeholder="ابحث بـ Chat ID أو نص الرسالة أو اسم المستخدم..."
              class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-500 mb-1.5">الحالة</label>
            <select
              v-model="status"
              @change="loadMessages(1)"
              class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            >
              <option value="">جميع الحالات</option>
              <option value="sent">مرسل</option>
              <option value="pending">معلق</option>
              <option value="failed">فشل</option>
              <option value="delivered">تم التسليم</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-500 mb-1.5">الاتجاه</label>
            <select
              v-model="direction"
              @change="loadMessages(1)"
              class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            >
              <option value="">جميع الاتجاهات</option>
              <option value="outgoing">صادرة (Outgoing)</option>
              <option value="incoming">واردة (Incoming)</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Messages Table -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">#</th>
                <th class="px-5 py-3.5">المستخدم</th>
                <th class="px-5 py-3.5">البوت</th>
                <th class="px-5 py-3.5">المستلم (Chat ID)</th>
                <th class="px-5 py-3.5">الاتجاه</th>
                <th class="px-5 py-3.5">الرسالة</th>
                <th class="px-5 py-3.5">الحالة</th>
                <th class="px-5 py-3.5">التاريخ</th>
                <th class="px-5 py-3.5 text-center">إجراءات</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="m in messages" :key="m.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-3.5 text-slate-400 font-mono">{{ m.id }}</td>
                <td class="px-5 py-3.5 font-bold text-slate-900">{{ m.user?.name ?? 'غير معروف' }}</td>
                <td class="px-5 py-3.5 font-mono text-sky-600 font-bold" dir="ltr">@{{ m.session?.bot_username || '-' }}</td>
                <td class="px-5 py-3.5 font-mono text-slate-700" dir="ltr">{{ m.chat_id }}</td>
                <td class="px-5 py-3.5">
                  <span :class="m.direction === 'outgoing' ? 'text-indigo-600 bg-indigo-50 border-indigo-100' : 'text-emerald-600 bg-emerald-50 border-emerald-100'" class="px-2 py-0.5 rounded text-[10px] font-bold border">
                    {{ m.direction === 'outgoing' ? 'صادرة' : 'واردة' }}
                  </span>
                </td>
                <td class="px-5 py-3.5 max-w-xs truncate text-slate-700">
                  {{ m.message || (m.media_url ? '[مرفق ميديا]' : '-') }}
                </td>
                <td class="px-5 py-3.5">
                  <span :class="statusBadgeClass(m.status)" class="px-2 py-0.5 rounded-full text-[10px] font-bold border">
                    {{ statusBadgeText(m.status) }}
                  </span>
                </td>
                <td class="px-5 py-3.5 text-slate-500 whitespace-nowrap">{{ formatDate(m.created_at) }}</td>
                <td class="px-5 py-3.5 text-center">
                  <button
                    @click="deleteMessage(m.id)"
                    class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                    title="حذف السجل"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                  </button>
                </td>
              </tr>
              <tr v-if="messages.length === 0 && !loading">
                <td colspan="9" class="px-5 py-12 text-center text-slate-400 font-bold">لا توجد رسائل مطابقة للبحث</td>
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
  name: 'AdminTelegramMessages',
  components: { AdminLayout },
  setup() {
    const messages = ref([]);
    const counts = ref({});
    const loading = ref(false);
    const search = ref('');
    const status = ref('');
    const direction = ref('');
    let searchTimeout = null;

    const loadMessages = async (page = 1) => {
      try {
        loading.value = true;
        let url = `/admin/telegram/messages?page=${page}`;
        if (search.value) url += `&search=${encodeURIComponent(search.value)}`;
        if (status.value) url += `&status=${status.value}`;
        if (direction.value) url += `&direction=${direction.value}`;

        const res = await api.get(url);
        if (res.success) {
          messages.value = res.data?.data || res.data || [];
          counts.value = res.counts || {};
        }
      } catch (err) {
        console.error('Failed to load admin telegram messages:', err);
      } finally {
        loading.value = false;
      }
    };

    const debounceSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        loadMessages(1);
      }, 300);
    };

    const deleteMessage = async (id) => {
      if (!confirm('هل تريد حذف سجل الرسالة هذا؟')) return;
      try {
        await api.delete(`/admin/telegram/messages/${id}`);
        await loadMessages();
      } catch (err) {
        console.error('Failed to delete message:', err);
      }
    };

    const statusBadgeClass = (st) => {
      switch (st) {
        case 'sent': return 'bg-emerald-50 text-emerald-700 border-emerald-200/60';
        case 'delivered': return 'bg-sky-50 text-sky-700 border-sky-200/60';
        case 'failed': return 'bg-red-50 text-red-700 border-red-200/60';
        default: return 'bg-amber-50 text-amber-700 border-amber-200/60';
      }
    };

    const statusBadgeText = (st) => {
      switch (st) {
        case 'sent': return 'مرسل';
        case 'delivered': return 'تم التسليم';
        case 'failed': return 'فشل';
        default: return 'معلق';
      }
    };

    const formatDate = (d) => {
      if (!d) return '-';
      return new Date(d).toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    };

    onMounted(() => {
      loadMessages();
    });

    return {
      messages,
      counts,
      loading,
      search,
      status,
      direction,
      loadMessages,
      debounceSearch,
      deleteMessage,
      statusBadgeClass,
      statusBadgeText,
      formatDate,
    };
  },
};
</script>
