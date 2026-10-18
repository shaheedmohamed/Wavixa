<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="pb-2 border-b border-slate-200/60">
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">سجلات النشاطات</h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">تتبع أحداث النظام وأفعال المديرين</p>
      </div>

      <!-- Filters -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
        <div class="flex flex-col md:flex-row gap-4">
          <input 
            v-model="action" 
            @input="debounceSearch" 
            type="text" 
            placeholder="فلترة حسب الإجراء..." 
            class="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all" 
          />
          <input 
            v-model="entityType" 
            @input="debounceSearch" 
            type="text" 
            placeholder="نوع الكيان..." 
            class="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all" 
          />
        </div>
      </div>

      <!-- Logs Table -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">#</th>
                <th class="px-5 py-3.5">الإجراء</th>
                <th class="px-5 py-3.5">الكيان</th>
                <th class="px-5 py-3.5">المستخدم</th>
                <th class="px-5 py-3.5">المدير</th>
                <th class="px-5 py-3.5">الوصف</th>
                <th class="px-5 py-3.5">IP</th>
                <th class="px-5 py-3.5">الوقت</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="log in logs" :key="log.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-4 text-slate-400 font-mono">{{ log.id }}</td>
                <td class="px-5 py-4 text-emerald-700 font-bold font-mono">{{ log.action }}</td>
                <td class="px-5 py-4 text-slate-900">{{ log.entity_type }}{{ log.entity_id ? `#${log.entity_id}` : '' }}</td>
                <td class="px-5 py-4 text-slate-900">{{ log.user?.name ?? 'غير معروف' }}</td>
                <td class="px-5 py-4 text-slate-900">{{ log.admin?.name ?? 'النظام' }}</td>
                <td class="px-5 py-4 text-slate-600 max-w-xs truncate">{{ log.description }}</td>
                <td class="px-5 py-4 text-slate-400 font-mono text-[10px]">{{ log.ip_address }}</td>
                <td class="px-5 py-4 text-slate-500">{{ formatDate(log.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="logs.length === 0" class="text-center py-12 text-slate-400 text-xs">لا توجد سجلات نشاط</div>

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
import { ref, onMounted } from 'vue';
import admin from '@/services/admin';
import AdminLayout from '@/layouts/AdminLayout.vue';

export default {
  name: 'AdminActivityLogs',
  components: { AdminLayout },
  setup() {
    const logs = ref([]);
    const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
    const action = ref('');
    const entityType = ref('');
    let searchTimeout = null;

    const loadLogs = async (page = 1) => {
      try {
        const params = { page, action: action.value, entity_type: entityType.value };
        const response = await admin.getActivityLogs(params);
        if (response.success) {
          logs.value = response.data.data;
          pagination.value = { current_page: response.data.current_page, last_page: response.data.last_page, total: response.data.total };
        }
      } catch (err) { console.error('Error loading activity logs:', err); }
    };

    const debounceSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => loadLogs(1), 300);
    };

    const prevPage = () => loadLogs(pagination.value.current_page - 1);
    const nextPage = () => loadLogs(pagination.value.current_page + 1);
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

    onMounted(() => loadLogs());

    return { logs, pagination, action, entityType, loadLogs, debounceSearch, prevPage, nextPage, formatDate };
  },
};
</script>
