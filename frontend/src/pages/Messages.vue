<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('messages_page.title') }}</h2>
          <p class="text-gray-500 text-sm mt-1">{{ t('messages_page.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
          <button
            @click="exportMessages"
            :disabled="loadingExport"
            class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-50 transition-all shadow-sm active:scale-95 disabled:opacity-50"
          >
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
            <span>{{ loadingExport ? t('messages_page.exporting') : t('messages_page.export_csv') }}</span>
          </button>
          <router-link to="/send-message" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-sm active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>{{ t('messages_page.new_send') }}</span>
          </router-link>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">{{ t('messages_page.total_messages') }}</p>
          <p class="text-xl font-black text-gray-900 leading-none">{{ pagination.total }}</p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">{{ t('messages_page.sent') }}</p>
          <p class="text-xl font-black text-green-600 leading-none">{{ statusCounts.sent }}</p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">{{ t('messages_page.pending') }}</p>
          <p class="text-xl font-black text-blue-600 leading-none">{{ statusCounts.queued + statusCounts.processing }}</p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">{{ t('messages_page.failed') }}</p>
          <p class="text-xl font-black text-red-600 leading-none">{{ statusCounts.failed }}</p>
        </div>
      </div>

      <!-- Filters & Search -->
      <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex flex-col md:flex-row gap-4 items-center">
        <div class="relative flex-1 w-full">
          <svg class="absolute top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" :class="isRTL ? 'right-3' : 'left-3'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
          <input
            v-model="filters.search"
            @input="debounceSearch"
            type="text"
            :placeholder="t('messages_page.search_placeholder')"
            class="w-full bg-gray-50 border border-gray-100 rounded-xl py-2 text-sm text-gray-700 placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none"
            :class="isRTL ? 'pr-10 pl-4' : 'pl-10 pr-4'"
          />
        </div>
        <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
          <select v-model="filters.status" @change="loadMessages" class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2 text-sm text-gray-700 focus:bg-white focus:border-indigo-300 transition-all outline-none">
            <option value="">{{ t('messages_page.all_statuses') }}</option>
            <option v-for="status in filterOptions.statuses" :key="status" :value="status">{{ status }}</option>
          </select>
          <select v-model="filters.direction" @change="loadMessages" class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2 text-sm text-gray-700 focus:bg-white focus:border-indigo-300 transition-all outline-none">
            <option value="">{{ t('messages_page.all_directions') }}</option>
            <option value="incoming">{{ t('messages_page.incoming') }}</option>
            <option value="outgoing">{{ t('messages_page.outgoing') }}</option>
          </select>
          <button @click="toggleSort" class="p-2 bg-gray-50 hover:bg-gray-100 text-gray-500 rounded-xl border border-gray-100 transition-all">
            <svg class="w-5 h-5" :class="{'rotate-180': filters.sort_order === 'asc'}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" /></svg>
          </button>
        </div>
      </div>

      <!-- Messages Table Card -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse" :class="isRTL ? 'text-right' : 'text-left'">
            <thead>
              <tr class="bg-gray-50/50 border-b border-gray-50">
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('messages_page.th_direction') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('messages_page.th_recipient_sender') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('messages_page.th_type') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('messages_page.th_status') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('messages_page.th_message') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('messages_page.th_date') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider" :class="isRTL ? 'text-left' : 'text-right'">{{ t('messages_page.th_actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-if="loading" class="animate-pulse">
                <td colspan="7" class="px-6 py-12 text-center text-gray-400 font-medium">{{ t('messages_page.loading_messages') }}</td>
              </tr>
              <tr v-else-if="messages.length === 0">
                <td colspan="7" class="px-6 py-20 text-center">
                  <div class="flex flex-col items-center">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4 text-gray-200">
                      <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                    </div>
                    <p class="text-gray-900 font-bold">{{ t('messages_page.no_messages') }}</p>
                    <p class="text-gray-400 text-sm mt-1">{{ t('messages_page.no_messages_desc') }}</p>
                  </div>
                </td>
              </tr>
              <tr v-else v-for="msg in messages" :key="msg.id" class="hover:bg-gray-50/50 transition-all group">
                <td class="px-6 py-4">
                  <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider', msg.direction === 'incoming' ? 'bg-green-50 text-green-600' : 'bg-blue-50 text-blue-600']">
                    {{ msg.direction === 'incoming' ? t('messages_page.incoming') : t('messages_page.outgoing') }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <p class="text-sm font-black text-gray-900 font-mono" dir="ltr">{{ msg.to || msg.from || 'N/A' }}</p>
                </td>
                <td class="px-6 py-4">
                  <span class="text-xs font-bold text-gray-500 capitalize bg-gray-100 px-2 py-0.5 rounded-md">{{ msg.message_type || 'text' }}</span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex flex-col gap-1 items-start">
                    <span :class="['inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-tighter', statusColorClass(msg.status)]">
                      {{ msg.status }}
                    </span>
                    <span v-if="msg.status === 'failed' && msg.error_message" class="text-[11px] text-red-600 font-bold max-w-[180px] truncate" :title="msg.error_message">
                      ⚠️ {{ msg.error_message }}
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4 max-w-[260px]">
                  <p class="text-xs text-gray-600 truncate font-medium">{{ msg.message || '-' }}</p>
                  <div v-if="msg.status === 'failed' && msg.error_message" class="mt-1 text-[11px] text-red-600 font-bold bg-red-50 px-2 py-0.5 rounded-md border border-red-100/80 inline-flex items-center gap-1 max-w-full" :title="msg.error_message">
                    <span>سبب الفشل:</span>
                    <span class="truncate">{{ msg.error_message }}</span>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <p class="text-xs text-gray-400 font-bold">{{ formatDate(msg.created_at) }}</p>
                </td>
                <td class="px-6 py-4" :class="isRTL ? 'text-left' : 'text-right'">
                  <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity" :class="isRTL ? 'justify-end' : 'justify-end'">
                    <button @click="showDetails(msg)" class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all" :title="t('messages_page.view_details')">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    </button>
                    <button v-if="msg.direction === 'outgoing'" @click="resendMessage(msg.id)" :disabled="resending === msg.id" class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all disabled:opacity-50" :title="t('messages_page.retry')">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 bg-gray-50/50 border-t border-gray-50 flex items-center justify-between">
          <div class="flex items-center gap-4">
            <span class="text-xs font-bold text-gray-400">{{ t('common.view') }}</span>
            <select v-model="filters.per_page" @change="loadMessages" class="bg-white border border-gray-200 rounded-lg px-2 py-1 text-xs font-bold text-gray-600 outline-none focus:border-indigo-300">
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
          </div>
          <div class="flex items-center gap-2">
            <button @click="prevPage" :disabled="pagination.current_page === 1" class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-white rounded-lg border border-transparent hover:border-gray-200 disabled:opacity-30 disabled:hover:bg-transparent transition-all">
              <svg class="w-5 h-5" :class="isRTL ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <span class="text-xs font-black text-gray-900 mx-2">{{ t('messages_page.page') }} {{ pagination.current_page }} {{ t('messages_page.of') }} {{ pagination.last_page }}</span>
            <button @click="nextPage" :disabled="pagination.current_page === pagination.last_page" class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-white rounded-lg border border-transparent hover:border-gray-200 disabled:opacity-30 disabled:hover:bg-transparent transition-all">
              <svg class="w-5 h-5" :class="isRTL ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Details Modal -->
    <div v-if="selectedMessage" class="fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-in fade-in duration-200">
      <div class="bg-white border border-gray-100 rounded-3xl shadow-2xl max-w-2xl w-full overflow-hidden" :dir="isRTL ? 'rtl' : 'ltr'">
        <div class="px-8 py-6 border-b border-gray-50 flex items-center justify-between bg-gray-50/30">
          <h3 class="text-lg font-black text-gray-900">{{ t('messages_page.view_details') }}</h3>
          <button @click="selectedMessage = null" class="w-8 h-8 rounded-full flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all font-bold">&times;</button>
        </div>
        <div class="p-8 space-y-6">
          <div class="grid grid-cols-2 gap-6">
            <div>
              <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">ID</p>
              <p class="text-xs font-black text-gray-900 font-mono select-all">{{ selectedMessage.id }}</p>
            </div>
            <div>
              <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ t('common.status') }}</p>
              <span :class="['inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase', statusColorClass(selectedMessage.status)]">{{ selectedMessage.status }}</span>
            </div>
            <div>
              <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ t('messages_page.th_direction') }}</p>
              <p class="text-xs font-black text-gray-900">{{ selectedMessage.direction === 'incoming' ? t('messages_page.incoming') : t('messages_page.outgoing') }}</p>
            </div>
            <div>
              <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ t('messages_page.th_type') }}</p>
              <p class="text-xs font-black text-gray-900 capitalize">{{ selectedMessage.message_type || 'text' }}</p>
            </div>
            <div>
              <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ t('contacts_page.th_phone') }}</p>
              <p class="text-xs font-black text-gray-900 font-mono" dir="ltr">{{ selectedMessage.to || selectedMessage.from || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ t('common.date') }}</p>
              <p class="text-xs font-black text-gray-900">{{ formatDate(selectedMessage.created_at) }}</p>
            </div>
          </div>
          <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">{{ t('messages_page.th_message') }}</p>
            <p class="text-sm text-gray-700 leading-relaxed font-medium whitespace-pre-wrap">{{ selectedMessage.message || '-' }}</p>
          </div>
          <div v-if="selectedMessage.error_message" class="bg-red-50 rounded-2xl p-4 border border-red-100">
            <p class="text-[10px] font-bold text-red-400 uppercase tracking-widest mb-1">{{ t('common.error') }}</p>
            <p class="text-xs text-red-600 font-bold leading-relaxed">{{ selectedMessage.error_message }}</p>
          </div>
        </div>
        <div class="px-8 py-6 border-t border-gray-50 flex justify-end gap-3 bg-gray-50/30">
          <button v-if="selectedMessage.direction === 'outgoing'" @click="resendMessage(selectedMessage.id); selectedMessage = null" :disabled="resending === selectedMessage.id" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl transition-all shadow-sm active:scale-95 disabled:opacity-50">
            {{ t('messages_page.retry') }}
          </button>
          <button @click="selectedMessage = null" class="px-6 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-100 transition-all active:scale-95">{{ t('common.close') }}</button>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, onMounted, computed, reactive } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import auth from '@/services/auth';
import { t, isRTL, locale } from '@/i18n';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

export default {
  name: 'Messages',
  components: { DashboardLayout },
  setup() {
    const router = useRouter();
    const messages = ref([]);
    const loading = ref(false);
    const loadingExport = ref(false);
    const resending = ref(null);
    const selectedMessage = ref(null);
    const pagination = ref({
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 0,
    });
    const filterOptions = ref({
      statuses: [],
      types: [],
      directions: ['incoming', 'outgoing'],
    });
    const filters = reactive({
      search: '',
      status: '',
      direction: '',
      message_type: '',
      sort_by: 'created_at',
      sort_order: 'desc',
      per_page: 10,
      page: 1,
    });
    let searchTimeout = null;

    onMounted(async () => {
      const userData = auth.getUser();
      if (!userData) {
        router.push('/login');
        return;
      }

      await loadFilterOptions();
      await loadMessages();
    });

    const loadMessages = async () => {
      loading.value = true;
      try {
        const params = new URLSearchParams();
        Object.keys(filters).forEach(key => {
          if (filters[key]) {
            params.append(key, filters[key]);
          }
        });
        params.append('page', filters.page);
        params.append('per_page', filters.per_page);

        const response = await api.get(`/messages?${params.toString()}`);
        if (response.success) {
          messages.value = response.data.data || [];
          pagination.value = {
            current_page: response.data.current_page || 1,
            last_page: response.data.last_page || 1,
            per_page: response.data.per_page || 10,
            total: response.data.total || 0,
          };
        }
      } catch (err) {
        console.error('Error loading messages:', err);
      } finally {
        loading.value = false;
      }
    };

    const loadFilterOptions = async () => {
      try {
        const response = await api.get('/messages/filters');
        if (response.success) {
          filterOptions.value = response.data;
        }
      } catch (err) {
        console.error('Error loading filters:', err);
      }
    };

    const debounceSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        filters.page = 1;
        loadMessages();
      }, 300);
    };

    const toggleSort = () => {
      filters.sort_order = filters.sort_order === 'desc' ? 'asc' : 'desc';
      loadMessages();
    };

    const prevPage = () => {
      if (filters.page > 1) {
        filters.page--;
        loadMessages();
      }
    };

    const nextPage = () => {
      if (filters.page < pagination.value.last_page) {
        filters.page++;
        loadMessages();
      }
    };

    const showDetails = async (msg) => {
      try {
        const response = await api.get(`/messages/${msg.id}`);
        if (response.success) {
          selectedMessage.value = response.data.data;
        }
      } catch (err) {
        console.error('Error loading message details:', err);
      }
    };

    const resendMessage = async (id) => {
      resending.value = id;
      try {
        const response = await api.post(`/messages/${id}/resend`, {});
        if (response.success) {
          alert(isRTL.value ? 'تمت إضافة الرسالة لزمام الإرسال' : 'Message queued for resending');
          await loadMessages();
        } else {
          alert(response.message || (isRTL.value ? 'فشل إعادة الإرسال' : 'Failed to resend message'));
        }
      } catch (err) {
        console.error('Error resending message:', err);
      } finally {
        resending.value = null;
      }
    };

    const exportMessages = async () => {
      loadingExport.value = true;
      try {
        const params = new URLSearchParams();
        ['search', 'status', 'direction', 'message_type'].forEach(key => {
          if (filters[key]) params.append(key, filters[key]);
        });

        const blob = await api.get(`/messages/export/csv?${params.toString()}`, {
          responseType: 'blob',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `wavixa-messages-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
      } catch (err) {
        console.error('Error exporting messages:', err);
      } finally {
        loadingExport.value = false;
      }
    };

    const statusColorClass = (status) => {
      const colors = {
        sent: 'bg-green-100 text-green-700',
        delivered: 'bg-emerald-100 text-emerald-700',
        read: 'bg-indigo-100 text-indigo-700',
        queued: 'bg-blue-100 text-blue-700',
        processing: 'bg-amber-100 text-amber-700',
        failed: 'bg-red-100 text-red-700',
      };
      return colors[status] || 'bg-gray-100 text-gray-700';
    };

    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleString(isRTL.value ? 'ar-EG' : 'en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    };

    const statusCounts = computed(() => {
      const counts = { sent: 0, failed: 0, queued: 0, processing: 0, retrying: 0, delivered: 0, read: 0, pending: 0 };
      messages.value.forEach(m => {
        if (counts[m.status] !== undefined) counts[m.status]++;
      });
      return counts;
    });

    return {
      messages,
      loading,
      loadingExport,
      resending,
      selectedMessage,
      pagination,
      filters,
      filterOptions,
      statusCounts,
      loadMessages,
      debounceSearch,
      toggleSort,
      prevPage,
      nextPage,
      showDetails,
      resendMessage,
      exportMessages,
      statusColorClass,
      formatDate,
      t,
      isRTL,
    };
  },
};
</script>

<style scoped>
.animate-in {
  animation: animate-in 0.3s ease-out;
}

@keyframes animate-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
