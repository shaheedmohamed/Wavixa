<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/60">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">التحليلات والإحصائيات</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">الاتجاهات خلال آخر {{ days }} يوم</p>
        </div>
        <select 
          v-model="days" 
          @change="loadAnalytics" 
          class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all min-w-[160px]"
        >
          <option value="7">آخر 7 أيام</option>
          <option value="30">آخر 30 يوم</option>
          <option value="60">آخر 60 يوم</option>
          <option value="90">آخر 90 يوم</option>
        </select>
      </div>

      <div v-if="loading" class="text-center py-20 text-slate-500 text-sm">جاري تحميل التحليلات...</div>

      <div v-else class="space-y-5 sm:space-y-6">
        <!-- Messages Per Day -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs">
          <h3 class="text-sm font-black text-slate-900 mb-4">الرسائل يومياً</h3>
          <div class="flex items-end gap-1 h-64 pb-4 border-b border-slate-100">
            <div v-for="item in messagesPerDay" :key="item.date" class="flex-1 flex flex-col items-center group min-w-[24px]">
              <div class="w-full max-w-[32px] bg-emerald-500 hover:bg-emerald-600 rounded-t-md transition-all relative" :style="{ height: barHeight(item.count, maxMessages) }">
                <span class="absolute -top-6 left-1/2 -translate-x-1/2 text-[10px] font-bold text-slate-900 bg-white border border-slate-200 px-1.5 py-0.5 rounded shadow-sm opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10">{{ item.count }}</span>
              </div>
              <span class="text-[10px] text-slate-500 mt-2">{{ formatShortDate(item.date) }}</span>
            </div>
            <div v-if="messagesPerDay.length === 0" class="w-full text-center text-slate-400 text-xs">لا توجد بيانات</div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 sm:gap-6">
          <!-- Users Per Day -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs">
            <h3 class="text-sm font-black text-slate-900 mb-4">المستخدمون الجدد يومياً</h3>
            <div class="flex items-end gap-1 h-48 pb-4 border-b border-slate-100">
              <div v-for="item in usersPerDay" :key="item.date" class="flex-1 flex flex-col items-center group min-w-[24px]">
                <div class="w-full max-w-[32px] bg-blue-500 hover:bg-blue-600 rounded-t-md transition-all relative" :style="{ height: barHeight(item.count, maxUsers) }">
                  <span class="absolute -top-6 left-1/2 -translate-x-1/2 text-[10px] font-bold text-slate-900 bg-white border border-slate-200 px-1.5 py-0.5 rounded shadow-sm opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10">{{ item.count }}</span>
                </div>
                <span class="text-[10px] text-slate-500 mt-2">{{ formatShortDate(item.date) }}</span>
              </div>
              <div v-if="usersPerDay.length === 0" class="w-full text-center text-slate-400 text-xs">لا توجد بيانات</div>
            </div>
          </div>

          <!-- Revenue Per Day -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs">
            <h3 class="text-sm font-black text-slate-900 mb-4">الإيرادات يومياً</h3>
            <div class="flex items-end gap-1 h-48 pb-4 border-b border-slate-100">
              <div v-for="item in revenuePerDay" :key="item.date" class="flex-1 flex flex-col items-center group min-w-[24px]">
                <div class="w-full max-w-[32px] bg-amber-500 hover:bg-amber-600 rounded-t-md transition-all relative" :style="{ height: barHeight(item.total, maxRevenue) }">
                  <span class="absolute -top-6 left-1/2 -translate-x-1/2 text-[10px] font-bold text-slate-900 bg-white border border-slate-200 px-1.5 py-0.5 rounded shadow-sm opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10">{{ formatCurrency(item.total) }}</span>
                </div>
                <span class="text-[10px] text-slate-500 mt-2">{{ formatShortDate(item.date) }}</span>
              </div>
              <div v-if="revenuePerDay.length === 0" class="w-full text-center text-slate-400 text-xs">لا توجد بيانات</div>
            </div>
          </div>
        </div>

        <!-- Total Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-5">
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs text-center">
            <p class="text-xs font-bold text-slate-500">إجمالي الرسائل</p>
            <p class="text-3xl font-black text-emerald-600 mt-2">{{ totalMessages }}</p>
          </div>
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs text-center">
            <p class="text-xs font-bold text-slate-500">المستخدمون الجدد</p>
            <p class="text-3xl font-black text-blue-600 mt-2">{{ totalUsers }}</p>
          </div>
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs text-center">
            <p class="text-xs font-bold text-slate-500">الإيرادات</p>
            <p class="text-3xl font-black text-amber-600 mt-2">{{ formatCurrency(totalRevenue) }}</p>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script>
import { ref, onMounted, computed } from 'vue';
import admin from '@/services/admin';
import AdminLayout from '@/layouts/AdminLayout.vue';

export default {
  name: 'AdminAnalytics',
  components: { AdminLayout },
  setup() {
    const days = ref(30);
    const loading = ref(true);
    const messagesPerDay = ref([]);
    const usersPerDay = ref([]);
    const revenuePerDay = ref([]);

    const loadAnalytics = async () => {
      loading.value = true;
      try {
        const response = await admin.getAnalytics({ days: days.value });
        if (response.success) {
          messagesPerDay.value = response.data.messages_per_day;
          usersPerDay.value = response.data.users_per_day;
          revenuePerDay.value = response.data.revenue_per_day;
        }
      } catch (err) { console.error('Error loading analytics:', err); }
      finally { loading.value = false; }
    };

    const maxMessages = computed(() => Math.max(1, ...messagesPerDay.value.map(i => i.count)));
    const maxUsers = computed(() => Math.max(1, ...usersPerDay.value.map(i => i.count)));
    const maxRevenue = computed(() => Math.max(1, ...revenuePerDay.value.map(i => parseFloat(i.total || 0))));

    const totalMessages = computed(() => messagesPerDay.value.reduce((sum, i) => sum + i.count, 0));
    const totalUsers = computed(() => usersPerDay.value.reduce((sum, i) => sum + i.count, 0));
    const totalRevenue = computed(() => revenuePerDay.value.reduce((sum, i) => sum + parseFloat(i.total || 0), 0));

    const barHeight = (value, max) => {
      if (!max || max === 0) return '0%';
      return Math.max(4, (value / max) * 100) + '%';
    };

    const formatShortDate = (date) => {
      if (!date) return '';
      const d = new Date(date);
      return `${d.getMonth() + 1}/${d.getDate()}`;
    };

    const formatCurrency = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0);

    onMounted(() => loadAnalytics());

    return { days, loading, messagesPerDay, usersPerDay, revenuePerDay, loadAnalytics, maxMessages, maxUsers, maxRevenue, totalMessages, totalUsers, totalRevenue, barHeight, formatShortDate, formatCurrency };
  },
};
</script>
