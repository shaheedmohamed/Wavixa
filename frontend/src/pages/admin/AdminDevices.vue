<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/60">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">الأجهزة والمتصفحات المسجلة</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">متابعة أجهزة تسجيل الدخول ومتصفحات المستخدمين وعناوين IP</p>
        </div>
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-white border border-slate-200 rounded-xl shadow-xs text-xs font-bold text-slate-700">
          <span>إجمالي الأجهزة:</span>
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
              placeholder="ابحث باسم المستخدم، المتصفح، أو IP..." 
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all" 
            />
          </div>
          <select 
            v-model="isCurrent" 
            @change="loadDevices(1)" 
            class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all min-w-[160px]"
          >
            <option value="">جميع الأجهزة</option>
            <option value="1">الجهاز الحالي</option>
            <option value="0">أجهزة أخرى</option>
          </select>
        </div>
      </div>

      <!-- Devices Table Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">#</th>
                <th class="px-5 py-3.5">المستخدم</th>
                <th class="px-5 py-3.5">اسم الجهاز</th>
                <th class="px-5 py-3.5">النوع</th>
                <th class="px-5 py-3.5">المتصفح</th>
                <th class="px-5 py-3.5">نظام التشغيل</th>
                <th class="px-5 py-3.5">عنوان IP</th>
                <th class="px-5 py-3.5">الحالي</th>
                <th class="px-5 py-3.5">آخر نشاط</th>
                <th class="px-5 py-3.5 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="device in devices" :key="device.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-4 text-slate-400 font-mono">{{ device.id }}</td>
                <td class="px-5 py-4 font-bold text-slate-900">{{ device.user?.name ?? 'غير معروف' }}</td>
                <td class="px-5 py-4 text-slate-700 font-medium">{{ device.name || '-' }}</td>
                <td class="px-5 py-4 text-slate-500">{{ device.device_type || '-' }}</td>
                <td class="px-5 py-4 text-slate-600 font-bold">{{ device.browser || '-' }}</td>
                <td class="px-5 py-4 text-slate-600">{{ device.os || '-' }}</td>
                <td class="px-5 py-4 text-slate-500 font-mono text-[11px]">{{ device.ip_address || '-' }}</td>
                <td class="px-5 py-4">
                  <span :class="device.is_current ? 'bg-emerald-50 text-emerald-700 border-emerald-200/70' : 'bg-slate-50 text-slate-500 border-slate-200/70'" class="px-2 py-0.5 rounded-md text-[10px] font-bold border">
                    {{ device.is_current ? 'نعم' : 'لا' }}
                  </span>
                </td>
                <td class="px-5 py-4 text-slate-500">{{ formatDate(device.last_active_at) }}</td>
                <td class="px-5 py-4 text-center">
                  <button 
                    @click="deleteDevice(device.id)" 
                    class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200/60 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                  >
                    حذف
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="devices.length === 0" class="text-center py-12 text-slate-400 text-xs">لا توجد أجهزة مسجلة</div>

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
  name: 'AdminDevices',
  components: { AdminLayout },
  setup() {
    const devices = ref([]);
    const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
    const search = ref('');
    const isCurrent = ref('');
    let searchTimeout = null;

    const loadDevices = async (page = 1) => {
      try {
        const params = { page, search: search.value, is_current: isCurrent.value };
        const response = await admin.getDevices(params);
        if (response.success) {
          devices.value = response.data.data;
          pagination.value = { current_page: response.data.current_page, last_page: response.data.last_page, total: response.data.total };
        }
      } catch (err) { console.error('Error loading devices:', err); }
    };

    const debounceSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => loadDevices(1), 300);
    };

    const deleteDevice = async (id) => {
      if (!confirm('هل أنت متأكد من حذف هذا الجهاز؟')) return;
      try {
        const response = await admin.deleteDevice(id);
        if (response.success) loadDevices(pagination.value.current_page);
      } catch (err) {
        console.error('Error deleting device:', err);
        alert(err.data?.message || 'فشل في حذف الجهاز');
      }
    };

    const prevPage = () => loadDevices(pagination.value.current_page - 1);
    const nextPage = () => loadDevices(pagination.value.current_page + 1);
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

    onMounted(() => loadDevices());

    return { 
      devices, 
      pagination, 
      search, 
      isCurrent, 
      loadDevices, 
      debounceSearch, 
      deleteDevice, 
      prevPage, 
      nextPage, 
      formatDate 
    };
  },
};
</script>
