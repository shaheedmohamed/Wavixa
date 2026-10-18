<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="pb-2 border-b border-slate-200/60">
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">مراقبة API</h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">سجلات طلبات API وقواها وسرعة الاستجابة</p>
      </div>

      <!-- Filters -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
        <div class="flex flex-col md:flex-row gap-4">
          <input 
            v-model="search" 
            @input="debounceLoad" 
            type="text" 
            placeholder="ابحث بـ endpoint..." 
            class="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all" 
          />
          <select 
            v-model="status" 
            @change="load" 
            class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all min-w-[160px]"
          >
            <option value="">جميع الحالات</option>
            <option value="success">ناجحة</option>
            <option value="error">فاشلة</option>
          </select>
        </div>
      </div>

      <!-- API Logs Table -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">الطريقة</th>
                <th class="px-5 py-3.5">المسار</th>
                <th class="px-5 py-3.5">المستخدم</th>
                <th class="px-5 py-3.5">الحالة</th>
                <th class="px-5 py-3.5">المدة</th>
                <th class="px-5 py-3.5">الطلب</th>
                <th class="px-5 py-3.5">الرد</th>
                <th class="px-5 py-3.5">IP</th>
                <th class="px-5 py-3.5">الوقت</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="log in logs" :key="log.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-4"><span class="px-2 py-0.5 rounded text-[10px] font-bold border" :class="methodClass(log.method)">{{ log.method }}</span></td>
                <td class="px-5 py-4 text-slate-800 font-mono text-[10px]">{{ log.endpoint }}</td>
                <td class="px-5 py-4 text-slate-900">{{ log.user?.name ?? 'غير معروف' }}</td>
                <td class="px-5 py-4"><span class="px-2 py-0.5 rounded text-[10px] font-bold border" :class="statusClass(log.status_code)">{{ log.status_code }}</span></td>
                <td class="px-5 py-4 text-slate-600 font-mono">{{ log.duration_ms }}ms</td>
                <td class="px-5 py-4 text-slate-600 font-mono text-[10px]">{{ formatBytes(log.request_size) }}</td>
                <td class="px-5 py-4 text-slate-600 font-mono text-[10px]">{{ formatBytes(log.response_size) }}</td>
                <td class="px-5 py-4 text-slate-400 font-mono text-[10px]">{{ log.ip_address }}</td>
                <td class="px-5 py-4 text-slate-500">{{ formatDate(log.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="logs.length === 0" class="text-center py-12 text-slate-400 text-xs">لا توجد سجلات API</div>

        <div v-if="pagination.last_page > 1" class="px-6 py-4 border-t border-slate-100 flex items-center justify-between text-xs">
          <button @click="prevPage" :disabled="pagination.current_page === 1" class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-lg disabled:opacity-50 border border-slate-200 font-bold transition-colors cursor-pointer">السابق</button>
          <span class="text-slate-500 font-medium">صفحة {{ pagination.current_page }} من {{ pagination.last_page }}</span>
          <button @click="nextPage" :disabled="pagination.current_page === pagination.last_page" class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-lg disabled:opacity-50 border border-slate-200 font-bold transition-colors cursor-pointer">التالي</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue';
import admin from '@/services/admin';
import AdminLayout from '@/layouts/AdminLayout.vue';

export default {
  name: 'AdminApiMonitoring',
  components: { AdminLayout },
  setup() {
    const logs = ref([]);
    const pagination = ref({ current_page: 1, last_page: 1 });
    const search = ref('');
    const status = ref('');
    let timeout = null;
    let pollInterval = null;

    const load = async (page = 1) => {
      try {
        const params = { page, search: search.value, status: status.value };
        const response = await admin.getApiMonitoring(params);
        if (response.success) {
          logs.value = response.data.data;
          pagination.value = { current_page: response.data.current_page, last_page: response.data.last_page };
        }
      } catch (err) { console.error(err); }
    };

    const debounceLoad = () => {
      clearTimeout(timeout);
      timeout = setTimeout(() => load(1), 300);
    };

    const prevPage = () => load(pagination.value.current_page - 1);
    const nextPage = () => load(pagination.value.current_page + 1);

    const methodClass = (method) => {
      const m = (method || '').toUpperCase();
      if (m === 'GET') return 'bg-blue-50 text-blue-700 border-blue-200/70';
      if (m === 'POST') return 'bg-emerald-50 text-emerald-700 border-emerald-200/70';
      if (m === 'PUT' || m === 'PATCH') return 'bg-amber-50 text-amber-700 border-amber-200/70';
      if (m === 'DELETE') return 'bg-red-50 text-red-700 border-red-200/70';
      return 'bg-slate-100 text-slate-600 border-slate-200/70';
    };

    const statusClass = (code) => {
      if (!code) return 'bg-slate-100 text-slate-600 border-slate-200/70';
      if (code >= 200 && code < 300) return 'bg-emerald-50 text-emerald-700 border-emerald-200/70';
      if (code >= 400 && code < 500) return 'bg-amber-50 text-amber-700 border-amber-200/70';
      return 'bg-red-50 text-red-700 border-red-200/70';
    };

    const formatBytes = (bytes) => {
      if (!bytes) return '0 B';
      const sizes = ['B', 'KB', 'MB'];
      const i = Math.floor(Math.log(bytes) / Math.log(1024));
      return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`;
    };

    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('ar-SA', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    };

    onMounted(() => { load(); pollInterval = setInterval(() => load(pagination.value.current_page), 10000); });
    onUnmounted(() => { if (pollInterval) clearInterval(pollInterval); });

    return { logs, pagination, search, status, load, debounceLoad, prevPage, nextPage, methodClass, statusClass, formatBytes, formatDate };
  },
};
</script>
