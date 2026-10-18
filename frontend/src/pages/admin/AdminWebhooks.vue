<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/60">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">إدارة الويب هوكس (Webhooks)</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">متابعة روابط الإشعارات التلقائية للأحداث وسجلات الاستجابة</p>
        </div>
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-white border border-slate-200 rounded-xl shadow-xs text-xs font-bold text-slate-700">
          <span>إجمالي الويب هوكس:</span>
          <span class="text-emerald-600 font-black">{{ pagination.total || 0 }}</span>
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
              placeholder="ابحث بالاسم أو الرابط أو اسم المستخدم..." 
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all" 
            />
          </div>
          <select 
            v-model="isActive" 
            @change="loadWebhooks(1)" 
            class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all min-w-[160px]"
          >
            <option value="">جميع الحالات</option>
            <option value="1">مفعل (Active)</option>
            <option value="0">معطل (Inactive)</option>
          </select>
        </div>
      </div>

      <!-- Webhooks Table Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">#</th>
                <th class="px-5 py-3.5">الاسم</th>
                <th class="px-5 py-3.5">المستخدم</th>
                <th class="px-5 py-3.5">رابط الـ URL</th>
                <th class="px-5 py-3.5">الأحداث</th>
                <th class="px-5 py-3.5">الحالة</th>
                <th class="px-5 py-3.5">عدد السجلات</th>
                <th class="px-5 py-3.5">تاريخ الإنشاء</th>
                <th class="px-5 py-3.5 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="webhook in webhooks" :key="webhook.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-4 text-slate-400 font-mono">{{ webhook.id }}</td>
                <td class="px-5 py-4 font-bold text-slate-900">{{ webhook.name }}</td>
                <td class="px-5 py-4">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-800 font-black text-[10px] flex items-center justify-center">
                      {{ (webhook.user?.name || 'U').charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <p class="font-bold text-slate-900">{{ webhook.user?.name ?? 'غير معروف' }}</p>
                      <p class="text-[10px] text-slate-400">{{ webhook.user?.email }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4 text-slate-600 font-mono text-[11px] max-w-xs truncate" dir="ltr">{{ webhook.url }}</td>
                <td class="px-5 py-4 text-slate-600">{{ (webhook.events || []).join(', ') || 'الكل' }}</td>
                <td class="px-5 py-4">
                  <span :class="webhook.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200/70' : 'bg-slate-50 text-slate-500 border-slate-200/70'" class="px-2 py-0.5 rounded-md text-[10px] font-bold border">
                    {{ webhook.is_active ? 'نشط' : 'معطل' }}
                  </span>
                </td>
                <td class="px-5 py-4 text-emerald-600 font-mono font-bold">{{ webhook.logs_count ?? 0 }}</td>
                <td class="px-5 py-4 text-slate-500">{{ formatDate(webhook.created_at) }}</td>
                <td class="px-5 py-4 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <button @click="viewLogs(webhook.id)" class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200/60 rounded-lg text-xs font-bold transition-colors cursor-pointer">السجلات</button>
                    <button @click="deleteWebhook(webhook.id)" class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200/60 rounded-lg text-xs font-bold transition-colors cursor-pointer">حذف</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="webhooks.length === 0" class="text-center py-12 text-slate-400 text-xs">لا توجد روابط Webhooks مسجلة</div>

        <div v-if="pagination.last_page > 1" class="px-6 py-4 border-t border-slate-100 flex items-center justify-between text-xs">
          <button @click="prevPage" :disabled="pagination.current_page === 1" class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-lg disabled:opacity-50 border border-slate-200 font-bold transition-colors cursor-pointer">السابق</button>
          <span class="text-slate-500 font-medium">صفحة {{ pagination.current_page }} من {{ pagination.last_page }}</span>
          <button @click="nextPage" :disabled="pagination.current_page === pagination.last_page" class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-lg disabled:opacity-50 border border-slate-200 font-bold transition-colors cursor-pointer">التالي</button>
        </div>
      </div>

      <!-- Logs Modal -->
      <div v-if="showLogsModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center z-50 p-4" @click.self="showLogsModal = false">
        <div class="bg-white border border-slate-100 rounded-2xl p-6 w-full max-w-4xl max-h-[85vh] overflow-hidden flex flex-col shadow-2xl" dir="rtl">
          <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100">
            <h3 class="text-lg font-black text-slate-900">سجل أحداث Webhook</h3>
            <button @click="showLogsModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">×</button>
          </div>
          <div class="overflow-y-auto flex-1">
            <table class="w-full text-right text-xs">
              <thead>
                <tr class="text-slate-400 font-bold border-b border-slate-100 bg-slate-50/50">
                  <th class="px-4 py-3">#</th>
                  <th class="px-4 py-3">الحدث</th>
                  <th class="px-4 py-3">الحالة</th>
                  <th class="px-4 py-3">كود HTTP</th>
                  <th class="px-4 py-3">المحاولات</th>
                  <th class="px-4 py-3">تاريخ الإرسال</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="log in logs" :key="log.id" class="hover:bg-slate-50/80">
                  <td class="px-4 py-3 text-slate-400 font-mono">{{ log.id }}</td>
                  <td class="px-4 py-3 text-slate-800 font-bold font-mono">{{ log.event }}</td>
                  <td class="px-4 py-3">
                    <span :class="log.status === 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-200/70' : 'bg-red-50 text-red-700 border-red-200/70'" class="px-2 py-0.5 rounded text-[10px] font-bold border">
                      {{ log.status === 'success' ? 'نجاح' : 'فشل' }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-slate-600 font-mono font-bold">{{ log.http_status ?? '-' }}</td>
                  <td class="px-4 py-3 text-slate-600 font-mono">{{ log.attempts }}</td>
                  <td class="px-4 py-3 text-slate-500">{{ formatDate(log.sent_at) }}</td>
                </tr>
              </tbody>
            </table>
            <div v-if="logs.length === 0" class="text-center py-12 text-slate-400 text-xs">لا توجد سجلات</div>
          </div>
          <div class="flex justify-end pt-4 border-t border-slate-100 mt-4">
            <button @click="showLogsModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors">إغلاق</button>
          </div>
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
  name: 'AdminWebhooks',
  components: { AdminLayout },
  setup() {
    const webhooks = ref([]);
    const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
    const search = ref('');
    const isActive = ref('');
    const showLogsModal = ref(false);
    const logs = ref([]);
    let searchTimeout = null;

    const loadWebhooks = async (page = 1) => {
      try {
        const params = { page, search: search.value, is_active: isActive.value };
        const response = await admin.getWebhooks(params);
        if (response.success) {
          webhooks.value = response.data.data;
          pagination.value = { current_page: response.data.current_page, last_page: response.data.last_page, total: response.data.total };
        }
      } catch (err) { console.error('Error loading webhooks:', err); }
    };

    const debounceSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => loadWebhooks(1), 300);
    };

    const viewLogs = async (id) => {
      try {
        const response = await admin.getWebhookLogs(id);
        if (response.success) logs.value = response.data.data;
      } catch (err) {
        console.error('Error loading logs:', err);
        logs.value = [];
      }
      showLogsModal.value = true;
    };

    const deleteWebhook = async (id) => {
      if (!confirm('هل أنت متأكد من حذف هذا الـ Webhook؟')) return;
      try {
        const response = await admin.deleteWebhook(id);
        if (response.success) loadWebhooks(pagination.value.current_page);
      } catch (err) {
        console.error('Error deleting webhook:', err);
        alert(err.data?.message || 'فشل في حذف الـ Webhook');
      }
    };

    const prevPage = () => loadWebhooks(pagination.value.current_page - 1);
    const nextPage = () => loadWebhooks(pagination.value.current_page + 1);
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

    onMounted(() => loadWebhooks());

    return { 
      webhooks, 
      pagination, 
      search, 
      isActive, 
      showLogsModal, 
      logs, 
      loadWebhooks, 
      debounceSearch, 
      viewLogs, 
      deleteWebhook, 
      prevPage, 
      nextPage, 
      formatDate 
    };
  },
};
</script>
