<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="pb-2 border-b border-slate-200/60">
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">مركز الأمان</h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">تتبع التنبيهات الاحتيالية وسجلات تسجيل الدخول والمحاولات الفاشلة</p>
      </div>

      <!-- Tabs -->
      <div class="flex flex-wrap gap-2">
        <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key" :class="[
          'px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shadow-2xs',
          activeTab === tab.key 
            ? 'bg-emerald-600 text-white' 
            : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'
        ]">
          {{ tab.label }}
        </button>
      </div>

      <!-- Fraud Alerts Filters -->
      <div v-if="activeTab === 'fraud'" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
        <select v-model="filters.severity" @change="load" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">
          <option value="">كل الخطورات</option>
          <option value="critical">حرج</option>
          <option value="high">عالى</option>
          <option value="medium">متوسط</option>
          <option value="low">منخفض</option>
        </select>
        <select v-model="filters.is_resolved" @change="load" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">
          <option value="">كل الحالات</option>
          <option value="0">مفتوحة</option>
          <option value="1">محلولة</option>
        </select>
        <input v-model="filters.type" @keyup.enter="load" placeholder="نوع التنبيه" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
        <input v-model="filters.search" @keyup.enter="load" placeholder="بحث: IP / اسم / بريد / هاتف" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
      </div>

      <!-- Table Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div v-if="activeTab === 'fraud'" class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-4 py-3.5">الخطورة</th>
                <th class="px-4 py-3.5">النوع</th>
                <th class="px-4 py-3.5">الوصف</th>
                <th class="px-4 py-3.5">IP</th>
                <th class="px-4 py-3.5">الجهاز</th>
                <th class="px-4 py-3.5">الاسم</th>
                <th class="px-4 py-3.5">بيانات التواصل</th>
                <th class="px-4 py-3.5">البصمة</th>
                <th class="px-4 py-3.5">الوقت</th>
                <th class="px-4 py-3.5">الحالة</th>
                <th class="px-4 py-3.5"></th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="alert in fraudAlerts" :key="alert.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-[10px] font-bold border" :class="severityClass(alert.severity)">{{ alert.severity }}</span></td>
                <td class="px-4 py-3 text-slate-800 font-bold font-mono">{{ alert.type }}</td>
                <td class="px-4 py-3 text-slate-600 max-w-xs truncate" :title="alert.description">{{ alert.description }}</td>
                <td class="px-4 py-3 text-slate-400 font-mono text-[10px]">{{ alert.ip_address }}</td>
                <td class="px-4 py-3 text-slate-600">{{ alert.device_name }}</td>
                <td class="px-4 py-3 text-slate-900 font-bold">{{ alert.owner_name ?? alert.user?.name ?? 'غير معروف' }}</td>
                <td class="px-4 py-3 text-slate-500 text-[10px]">
                  <div v-if="alert.owner_email">{{ alert.owner_email }}</div>
                  <div v-if="alert.owner_phone">{{ alert.owner_phone }}</div>
                  <div v-if="!alert.owner_email && !alert.owner_phone">-</div>
                </td>
                <td class="px-4 py-3 text-slate-400 font-mono text-[10px]" :title="alert.device_fingerprint">{{ fingerprintShort(alert.device_fingerprint) }}</td>
                <td class="px-4 py-3 text-slate-500 whitespace-nowrap">{{ formatDate(alert.created_at) }}</td>
                <td class="px-4 py-3">
                  <span :class="alert.is_resolved ? 'bg-emerald-50 text-emerald-700 border-emerald-200/70' : 'bg-red-50 text-red-700 border-red-200/70'" class="px-2 py-0.5 rounded text-[10px] font-bold border">
                    {{ alert.is_resolved ? 'محلولة' : 'مفتوحة' }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <button @click="openAlert(alert)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[10px] font-bold transition-colors cursor-pointer">تفاصيل</button>
                    <button v-if="!alert.is_resolved" @click="resolveAlert(alert.id)" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px] font-bold transition-colors cursor-pointer">حلها</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="fraudAlerts.length === 0" class="text-center py-12 text-slate-400 text-xs">لا توجد تنبيهات احتيالية</div>
          <div v-if="pagination.last_page > 1" class="flex justify-center gap-2 py-4 border-t border-slate-100">
            <button :disabled="pagination.current_page === 1" @click="goToPage(pagination.current_page - 1)" class="px-3 py-1 rounded-lg border border-slate-200 text-xs font-bold disabled:opacity-50 cursor-pointer">السابق</button>
            <span class="px-3 py-1 text-xs font-bold text-slate-600">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
            <button :disabled="pagination.current_page === pagination.last_page" @click="goToPage(pagination.current_page + 1)" class="px-3 py-1 rounded-lg border border-slate-200 text-xs font-bold disabled:opacity-50 cursor-pointer">التالى</button>
          </div>
        </div>

        <div v-if="activeTab === 'login'" class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">المستخدم</th>
                <th class="px-5 py-3.5">IP</th>
                <th class="px-5 py-3.5">الجهاز</th>
                <th class="px-5 py-3.5">المتصفح</th>
                <th class="px-5 py-3.5">نظام التشغيل</th>
                <th class="px-5 py-3.5">ناجح</th>
                <th class="px-5 py-3.5">الوقت</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="log in loginHistory" :key="log.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-4 text-slate-900">{{ log.user?.name ?? 'غير معروف' }}</td>
                <td class="px-5 py-4 text-slate-400 font-mono text-[10px]">{{ log.ip_address }}</td>
                <td class="px-5 py-4 text-slate-600">{{ log.device_name }}</td>
                <td class="px-5 py-4 text-slate-600">{{ log.browser }}</td>
                <td class="px-5 py-4 text-slate-600">{{ log.os }}</td>
                <td class="px-5 py-4">
                  <span :class="log.success ? 'bg-emerald-50 text-emerald-700 border-emerald-200/70' : 'bg-red-50 text-red-700 border-red-200/70'" class="px-2 py-0.5 rounded text-[10px] font-bold border">
                    {{ log.success ? 'نعم' : 'لا' }}
                  </span>
                </td>
                <td class="px-5 py-4 text-slate-500">{{ formatDate(log.login_at) }}</td>
              </tr>
            </tbody>
          </table>
          <div v-if="loginHistory.length === 0" class="text-center py-12 text-slate-400 text-xs">لا يوجد سجل دخول</div>
        </div>

        <div v-if="activeTab === 'failed'" class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">المستخدم</th>
                <th class="px-5 py-3.5">IP</th>
                <th class="px-5 py-3.5">سبب الفشل</th>
                <th class="px-5 py-3.5">الجهاز</th>
                <th class="px-5 py-3.5">الوقت</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="log in failedLogins" :key="log.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-4 text-slate-900">{{ log.user?.name ?? log.attempted_email ?? 'غير معروف' }}</td>
                <td class="px-5 py-4 text-slate-400 font-mono text-[10px]">{{ log.ip_address }}</td>
                <td class="px-5 py-4 text-red-600 font-bold">{{ log.failure_reason ?? 'غير معروف' }}</td>
                <td class="px-5 py-4 text-slate-600">{{ log.device_name }}</td>
                <td class="px-5 py-4 text-slate-500">{{ formatDate(log.login_at) }}</td>
              </tr>
            </tbody>
          </table>
          <div v-if="failedLogins.length === 0" class="text-center py-12 text-slate-400 text-xs">لا توجد محاولات دخول فاشلة</div>
        </div>
      </div>

      <!-- Alert Details Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showModal = false">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6 space-y-4" dir="rtl">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h2 class="text-lg font-black text-slate-900">تفاصيل التنبيه</h2>
            <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
          </div>
          <div v-if="selectedAlert" class="space-y-3 text-xs">
            <div class="grid grid-cols-2 gap-3">
              <div class="bg-slate-50 rounded-xl p-3"><span class="text-slate-400 font-bold">النوع</span><div class="font-mono text-slate-800">{{ selectedAlert.type }}</div></div>
              <div class="bg-slate-50 rounded-xl p-3"><span class="text-slate-400 font-bold">الخطورة</span><div><span class="px-2 py-0.5 rounded text-[10px] font-bold border" :class="severityClass(selectedAlert.severity)">{{ selectedAlert.severity }}</span></div></div>
              <div class="bg-slate-50 rounded-xl p-3"><span class="text-slate-400 font-bold">IP</span><div class="font-mono">{{ selectedAlert.ip_address }}</div></div>
              <div class="bg-slate-50 rounded-xl p-3"><span class="text-slate-400 font-bold">الوقت</span><div>{{ formatDate(selectedAlert.created_at) }}</div></div>
              <div class="bg-slate-50 rounded-xl p-3"><span class="text-slate-400 font-bold">الجهاز</span><div>{{ selectedAlert.device_name }} ({{ selectedAlert.device_type }})</div></div>
              <div class="bg-slate-50 rounded-xl p-3"><span class="text-slate-400 font-bold">البصمة</span><div class="font-mono break-all">{{ selectedAlert.device_fingerprint }}</div></div>
              <div class="bg-slate-50 rounded-xl p-3"><span class="text-slate-400 font-bold">الاسم</span><div>{{ selectedAlert.owner_name ?? selectedAlert.user?.name ?? 'غير معروف' }}</div></div>
              <div class="bg-slate-50 rounded-xl p-3"><span class="text-slate-400 font-bold">البريد / الهاتف</span><div>{{ selectedAlert.owner_email || '-' }}<br />{{ selectedAlert.owner_phone || '-' }}</div></div>
            </div>
            <div class="bg-slate-50 rounded-xl p-3">
              <span class="text-slate-400 font-bold">User-Agent</span>
              <div class="font-mono text-[10px] break-all text-slate-700 mt-1">{{ selectedAlert.user_agent || '-' }}</div>
            </div>
            <div class="bg-slate-50 rounded-xl p-3">
              <span class="text-slate-400 font-bold">الوصف</span>
              <div class="text-slate-700 mt-1">{{ selectedAlert.description }}</div>
            </div>
            <div class="bg-slate-50 rounded-xl p-3 overflow-auto">
              <span class="text-slate-400 font-bold">البيانات التقنية (Metadata)</span>
              <pre class="font-mono text-[10px] text-slate-700 mt-1 whitespace-pre-wrap">{{ prettyMetadata(selectedAlert.metadata) }}</pre>
            </div>
            <div class="flex gap-2 pt-2">
              <button v-if="!selectedAlert.is_resolved" @click="resolveAlert(selectedAlert.id); showModal = false" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold cursor-pointer">تحديد كمحلول</button>
              <button @click="showModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold cursor-pointer">إغلاق</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script>
import { ref, reactive, watch, onMounted } from 'vue';
import admin from '@/services/admin';
import AdminLayout from '@/layouts/AdminLayout.vue';

export default {
  name: 'AdminSecurityCenter',
  components: { AdminLayout },
  setup() {
    const activeTab = ref('fraud');
    const fraudAlerts = ref([]);
    const loginHistory = ref([]);
    const failedLogins = ref([]);
    const showModal = ref(false);
    const selectedAlert = ref(null);
    const pagination = reactive({ current_page: 1, last_page: 1 });
    const filters = reactive({ severity: '', is_resolved: '', type: '', search: '' });
    const tabs = [
      { key: 'fraud', label: 'تنبيهات الاحتيال' },
      { key: 'login', label: 'سجلات الدخول' },
      { key: 'failed', label: 'محاولات فاشلة' },
    ];

    const load = async () => {
      try {
        if (activeTab.value === 'fraud') {
          const params = { page: pagination.current_page };
          if (filters.severity) params.severity = filters.severity;
          if (filters.is_resolved !== '') params.is_resolved = filters.is_resolved;
          if (filters.type) params.type = filters.type;
          if (filters.search) params.search = filters.search;
          const response = await admin.getFraudAlerts(params);
          if (response.success) {
            fraudAlerts.value = response.data.data;
            pagination.current_page = response.data.current_page;
            pagination.last_page = response.data.last_page;
          }
        } else if (activeTab.value === 'login') {
          const response = await admin.getLoginHistory();
          if (response.success) loginHistory.value = response.data.data;
        } else if (activeTab.value === 'failed') {
          const response = await admin.getFailedLogins();
          if (response.success) failedLogins.value = response.data.data;
        }
      } catch (err) { console.error(err); }
    };

    const goToPage = (page) => {
      pagination.current_page = page;
      load();
    };

    const resolveAlert = async (id) => {
      try {
        const response = await admin.resolveFraudAlert(id);
        if (response.success) load();
      } catch (err) { console.error(err); }
    };

    const openAlert = (alert) => {
      selectedAlert.value = alert;
      showModal.value = true;
    };

    const severityClass = (severity) => {
      const map = { 
        low: 'bg-slate-100 text-slate-600 border-slate-200/70', 
        medium: 'bg-amber-50 text-amber-700 border-amber-200/70', 
        high: 'bg-orange-50 text-orange-700 border-orange-200/70', 
        critical: 'bg-red-50 text-red-700 border-red-200/70' 
      };
      return map[severity] || map.low;
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

    const fingerprintShort = (fp) => {
      if (!fp) return '-';
      return fp.substring(0, 12) + '...';
    };

    const prettyMetadata = (metadata) => {
      try {
        return JSON.stringify(metadata, null, 2);
      } catch (e) {
        return String(metadata);
      }
    };

    watch(activeTab, () => {
      pagination.current_page = 1;
      load();
    });
    onMounted(load);

    return { activeTab, tabs, fraudAlerts, loginHistory, failedLogins, filters, pagination, showModal, selectedAlert, load, goToPage, resolveAlert, openAlert, severityClass, formatDate, fingerprintShort, prettyMetadata };
  },
};
</script>
