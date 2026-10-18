<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/60">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">مفاتيح API</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">إدارة ومراقبة كافة مفاتيح الربط البرمجي ومعدلات الطلبات للمستخدمين</p>
        </div>
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-white border border-slate-200 rounded-xl shadow-xs text-xs font-bold text-slate-700">
          <span>إجمالي المفاتيح:</span>
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
              placeholder="ابحث بالاسم أو البادئة أو اسم المستخدم..."
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            />
          </div>
          <select
            v-model="isActive"
            @change="loadApiKeys(1)"
            class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all min-w-[160px]"
          >
            <option value="">جميع الحالات</option>
            <option value="1">مفعل (Active)</option>
            <option value="0">معطل (Inactive)</option>
          </select>
        </div>
      </div>

      <!-- API Keys Table Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">#</th>
                <th class="px-5 py-3.5">اسم المفتاح</th>
                <th class="px-5 py-3.5">المستخدم</th>
                <th class="px-5 py-3.5">البادئة</th>
                <th class="px-5 py-3.5">الحالة</th>
                <th class="px-5 py-3.5">عدد الطلبات</th>
                <th class="px-5 py-3.5">استهلاك ميتا</th>
                <th class="px-5 py-3.5">آخر استخدام</th>
                <th class="px-5 py-3.5">تاريخ الإنشاء</th>
                <th class="px-5 py-3.5">نوع الربط</th>
                <th class="px-5 py-3.5 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="key in apiKeys" :key="key.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-4 text-slate-400 font-mono">{{ key.id }}</td>
                <td class="px-5 py-4 font-bold text-slate-900">{{ key.name }}</td>
                <td class="px-5 py-4">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-800 font-black text-[10px] flex items-center justify-center">
                      {{ (key.user?.name || 'U').charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <p class="font-bold text-slate-900">{{ key.user?.name ?? 'غير معروف' }}</p>
                      <p class="text-[10px] text-slate-400">{{ key.user?.email }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4 text-slate-700 font-mono text-[11px] font-bold">{{ key.prefix }}...</td>
                <td class="px-5 py-4">
                  <span :class="key.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200/70' : 'bg-slate-50 text-slate-500 border-slate-200/70'" class="px-2 py-0.5 rounded-md text-[10px] font-bold border">
                    {{ key.is_active ? 'نشط' : 'معطل' }}
                  </span>
                </td>
                <td class="px-5 py-4 text-emerald-600 font-mono font-bold">{{ key.requests_count?.toLocaleString() || 0 }}</td>
                <td class="px-5 py-4">
                  <div v-if="key.meta_usage?.total" class="space-y-0.5">
                    <p class="text-slate-800 font-bold text-[11px]">{{ key.meta_usage.total.toLocaleString() }} رسالة</p>
                    <p class="text-[10px] text-slate-400">
                      مدفوعة: <span class="text-red-600 font-bold">{{ key.meta_usage.billable }}</span> ·
                      مجانية: <span class="text-emerald-600 font-bold">{{ key.meta_usage.free }}</span>
                    </p>
                    <p class="text-[10px] font-black text-indigo-600">≈ ${{ key.meta_usage.estimated_usd.toFixed(4) }}</p>
                  </div>
                  <span v-else class="text-slate-400 text-xs">—</span>
                </td>
                <td class="px-5 py-4 text-slate-500">{{ formatDate(key.last_used_at) }}</td>
                <td class="px-5 py-4 text-slate-500">{{ formatDate(key.created_at) }}</td>
                <td class="px-5 py-4">
                  <span v-if="key.allow_remote_pairing" class="inline-flex items-center gap-1.5 text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded text-[10px] font-bold border border-emerald-200/60">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    ربط خارجي ({{ key.paired_sessions_count || 0 }} جهاز)
                  </span>
                  <span v-else-if="key.whatsapp_session_id" class="inline-flex items-center gap-1.5 text-blue-700 bg-blue-50 px-2 py-0.5 rounded text-[10px] font-bold border border-blue-200/60">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                    جلسة شخصية
                  </span>
                  <span v-else class="text-slate-400 text-xs">—</span>
                </td>
                <td class="px-5 py-4 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <button
                      v-if="key.meta_usage?.total"
                      @click="openUsage(key.id)"
                      class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 border border-indigo-200/60 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                    >
                      استهلاك
                    </button>
                    <button 
                      @click="deleteApiKey(key.id)" 
                      class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200/60 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                    >
                      حذف
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="apiKeys.length === 0" class="text-center py-12 text-slate-400 text-xs">
          لا توجد مفاتيح API مسجلة
        </div>

        <div v-if="pagination.last_page > 1" class="px-6 py-4 border-t border-slate-100 flex items-center justify-between text-xs">
          <button @click="prevPage" :disabled="pagination.current_page === 1" class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-lg disabled:opacity-50 border border-slate-200 font-bold transition-colors cursor-pointer">السابق</button>
          <span class="text-slate-500 font-medium">صفحة {{ pagination.current_page }} من {{ pagination.last_page }}</span>
          <button @click="nextPage" :disabled="pagination.current_page === pagination.last_page" class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-lg disabled:opacity-50 border border-slate-200 font-bold transition-colors cursor-pointer">التالي</button>
        </div>
      </div>
    </div>

    <!-- Meta usage detail modal -->
    <div v-if="usageModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" @click.self="usageModal = null">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[85vh] overflow-y-auto p-6 space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-black text-slate-900">استهلاك ميتا — {{ usageModal.key.name }}</h3>
            <p class="text-xs text-slate-400">{{ usageModal.key.user?.name }} · {{ usageModal.key.user?.email }}</p>
          </div>
          <button @click="usageModal = null" class="text-slate-400 hover:text-slate-700 text-xl leading-none px-2">×</button>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="bg-slate-50 rounded-xl p-3"><p class="text-[10px] text-slate-500">إجمالي الرسايل</p><p class="font-black text-lg">{{ usageModal.usage.total }}</p></div>
          <div class="bg-slate-50 rounded-xl p-3"><p class="text-[10px] text-slate-500">مدفوعة لدى ميتا</p><p class="font-black text-lg text-red-600">{{ usageModal.usage.billable }}</p></div>
          <div class="bg-slate-50 rounded-xl p-3"><p class="text-[10px] text-slate-500">مجانية (نافذة 24س)</p><p class="font-black text-lg text-emerald-600">{{ usageModal.usage.free }}</p></div>
          <div class="bg-slate-50 rounded-xl p-3"><p class="text-[10px] text-slate-500">تكلفة تقديرية</p><p class="font-black text-lg text-indigo-600">${{ usageModal.usage.estimated_usd.toFixed(4) }}</p></div>
        </div>

        <div v-if="Object.keys(usageModal.usage.categories).length" class="bg-slate-50 rounded-xl p-3 text-xs">
          <p class="font-bold text-slate-600 mb-1.5">حسب فئة التسعير (ميتا):</p>
          <div class="flex flex-wrap gap-2">
            <span v-for="(count, cat) in usageModal.usage.categories" :key="cat" class="px-2 py-1 bg-white border border-slate-200 rounded-lg font-bold text-slate-700">
              {{ cat }}: {{ count }}<span v-if="usageModal.rates[cat]" class="text-slate-400"> (~${{ (usageModal.rates[cat] * count).toFixed(3) }})</span>
            </span>
          </div>
        </div>

        <p class="text-[10px] text-slate-400">التكلفة تقديرية — ميتا بترجع فئة التسعير وعلامة billable فقط في الـ webhook، مش المبلغ الفعلي. الأسعار من rate card قابلة للتعديل في config/meta.php</p>

        <div class="border border-slate-100 rounded-xl overflow-hidden">
          <table class="w-full text-[11px]">
            <thead><tr class="bg-slate-50 text-slate-500 font-bold"><th class="px-3 py-2 text-right">إلى</th><th class="px-3 py-2 text-right">النوع</th><th class="px-3 py-2 text-right">الحالة</th><th class="px-3 py-2 text-right">الفئة</th><th class="px-3 py-2 text-right">مدفوعة؟</th><th class="px-3 py-2 text-right">الوقت</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="m in usageModal.recent_messages" :key="m.id" class="text-slate-700">
                <td class="px-3 py-1.5 font-mono" dir="ltr">+{{ m.to_phone }}</td>
                <td class="px-3 py-1.5">{{ m.message_type }}</td>
                <td class="px-3 py-1.5 font-bold" :class="m.status === 'failed' ? 'text-red-600' : 'text-emerald-600'">{{ m.status }}</td>
                <td class="px-3 py-1.5">{{ m.pricing_category || '—' }}</td>
                <td class="px-3 py-1.5">{{ m.pricing_billable === null ? '—' : (m.pricing_billable ? 'نعم' : 'لا') }}</td>
                <td class="px-3 py-1.5 text-slate-400">{{ formatDate(m.created_at) }}</td>
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
import admin from '@/services/admin';
import AdminLayout from '@/layouts/AdminLayout.vue';

export default {
  name: 'AdminApiKeys',
  components: { AdminLayout },
  setup() {
    const apiKeys = ref([]);
    const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
    const search = ref('');
    const isActive = ref('');
    let searchTimeout = null;

    const loadApiKeys = async (page = 1) => {
      try {
        const params = { page, search: search.value, is_active: isActive.value };
        const response = await admin.getApiKeys(params);
        if (response.success) {
          apiKeys.value = response.data.data;
          pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            total: response.data.total,
          };
        }
      } catch (err) {
        console.error('Error loading API keys:', err);
      }
    };

    const debounceSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => loadApiKeys(1), 300);
    };

    const deleteApiKey = async (id) => {
      if (!confirm('هل أنت متأكد من حذف مفتاح API هذا؟')) return;
      try {
        const response = await admin.deleteApiKey(id);
        if (response.success) loadApiKeys(pagination.value.current_page);
      } catch (err) {
        console.error('Error deleting API key:', err);
        alert(err.data?.message || 'فشل في حذف المفتاح');
      }
    };

    const usageModal = ref(null);
    const openUsage = async (id) => {
      try {
        const res = await admin.getApiKeyMetaUsage(id);
        if (res.success) usageModal.value = res.data;
      } catch (e) {
        alert(e.data?.message || 'فشل تحميل الاستهلاك');
      }
    };

    const prevPage = () => loadApiKeys(pagination.value.current_page - 1);
    const nextPage = () => loadApiKeys(pagination.value.current_page + 1);

    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('ar-SA', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
      });
    };

    onMounted(() => loadApiKeys());

    return {
      apiKeys, pagination, search, isActive, loadApiKeys, debounceSearch, deleteApiKey,
      prevPage, nextPage, formatDate, usageModal, openUsage,
    };
  },
};
</script>
