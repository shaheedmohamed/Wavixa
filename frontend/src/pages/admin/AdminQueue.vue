<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/60">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">مراقبة طابور الانتظار (Queue)</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">متابعة ومعالجة مهام إرسال الرسائل المعلقة والفاشلة وإعادة المحاولة</p>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-5">
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
          <p class="text-xs font-bold text-slate-400 mb-1">المهام قيد الانتظار</p>
          <p class="text-2xl font-black text-amber-600">{{ counts.pending }}</p>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
          <p class="text-xs font-bold text-slate-400 mb-1">المهام الفاشلة</p>
          <p class="text-2xl font-black text-red-600">{{ counts.failed }}</p>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
          <p class="text-xs font-bold text-slate-400 mb-1">إجمالي المهام</p>
          <p class="text-2xl font-black text-slate-900">{{ counts.pending + counts.failed }}</p>
        </div>
      </div>

      <!-- Tabs -->
      <div class="flex gap-2">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          @click="type = tab.value; loadQueue()"
          :class="[
            'px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shadow-2xs', 
            type === tab.value 
              ? 'bg-emerald-600 text-white' 
              : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'
          ]"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Queue Table Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">#</th>
                <th class="px-5 py-3.5">الطابور</th>
                <th class="px-5 py-3.5">البيانات (Payload)</th>
                <th class="px-5 py-3.5">التاريخ</th>
                <th class="px-5 py-3.5">الخطأ إن وجد</th>
                <th v-if="type === 'failed'" class="px-5 py-3.5 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="job in jobs" :key="job.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-4 text-slate-400 font-mono">{{ job.id }}</td>
                <td class="px-5 py-4 font-bold text-slate-900 font-mono">{{ job.queue }}</td>
                <td class="px-5 py-4 text-slate-600 font-mono text-[11px] max-w-md truncate">{{ job.payload }}</td>
                <td class="px-5 py-4 text-slate-500">{{ formatDate(type === 'failed' ? job.failed_at : job.created_at) }}</td>
                <td class="px-5 py-4 text-red-600 font-mono text-[11px] max-w-md truncate">{{ job.exception || '-' }}</td>
                <td v-if="type === 'failed'" class="px-5 py-4 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <button @click="retryJob(job.id)" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200/60 rounded-lg text-xs font-bold transition-colors cursor-pointer">إعادة المحاولة</button>
                    <button @click="deleteJob(job.id)" class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200/60 rounded-lg text-xs font-bold transition-colors cursor-pointer">حذف</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="jobs.length === 0" class="text-center py-12 text-slate-400 text-xs">لا توجد مهام في هذا الطابور</div>

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
  name: 'AdminQueue',
  components: { AdminLayout },
  setup() {
    const jobs = ref([]);
    const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
    const type = ref('pending');
    const counts = ref({ pending: 0, failed: 0 });
    const tabs = [
      { value: 'pending', label: 'المهام المعلقة' },
      { value: 'failed', label: 'المهام الفاشلة' },
    ];

    const loadQueue = async (page = 1) => {
      try {
        const response = await admin.getQueue({ page, type: type.value });
        if (response.success) {
          jobs.value = response.data.data;
          pagination.value = { current_page: response.data.current_page, last_page: response.data.last_page, total: response.data.total };
          counts.value = response.data.counts || { pending: 0, failed: 0 };
        }
      } catch (err) { console.error('Error loading queue:', err); }
    };

    const retryJob = async (id) => {
      try {
        const response = await admin.retryFailedJob(id);
        if (response.success) loadQueue(pagination.value.current_page);
      } catch (err) {
        console.error('Error retrying job:', err);
        alert(err.data?.message || 'فشل في إعادة المحاولة');
      }
    };

    const deleteJob = async (id) => {
      if (!confirm('هل أنت متأكد من حذف هذه المهمة؟')) return;
      try {
        const response = await admin.deleteFailedJob(id);
        if (response.success) loadQueue(pagination.value.current_page);
      } catch (err) {
        console.error('Error deleting job:', err);
        alert(err.data?.message || 'فشل في حذف المهمة');
      }
    };

    const prevPage = () => loadQueue(pagination.value.current_page - 1);
    const nextPage = () => loadQueue(pagination.value.current_page + 1);
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

    onMounted(() => loadQueue());

    return { jobs, pagination, type, counts, tabs, loadQueue, retryJob, deleteJob, prevPage, nextPage, formatDate };
  },
};
</script>
