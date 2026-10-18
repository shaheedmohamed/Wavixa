<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="pb-2 border-b border-slate-200/60">
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">إحصائيات النظام</h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">حالة الخادم والتطبيق والموارد</p>
      </div>

      <div v-if="loading" class="text-center py-12 text-slate-400 text-sm">جاري تحميل الإحصائيات...</div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
          <h3 class="text-sm font-black text-slate-900 mb-4">التطبيق</h3>
          <div class="space-y-3 text-xs">
            <div class="flex justify-between items-center"><span class="text-slate-500 font-bold">إصدار PHP</span><span class="text-slate-900 font-mono">{{ stats.php_version }}</span></div>
            <div class="flex justify-between items-center"><span class="text-slate-500 font-bold">إصدار Laravel</span><span class="text-slate-900 font-mono">{{ stats.laravel_version }}</span></div>
            <div class="flex justify-between items-center"><span class="text-slate-500 font-bold">البيئة</span><span class="text-slate-900 font-mono">{{ stats.environment }}</span></div>
            <div class="flex justify-between items-center"><span class="text-slate-500 font-bold">Debug</span><span class="text-slate-900 font-mono">{{ stats.debug ? 'نعم' : 'لا' }}</span></div>
            <div class="flex justify-between items-center"><span class="text-slate-500 font-bold">التوقيت</span><span class="text-slate-900 font-mono">{{ stats.timezone }}</span></div>
            <div class="flex justify-between items-center"><span class="text-slate-500 font-bold">قاعدة البيانات</span><span class="text-slate-900 font-mono">{{ stats.database }}</span></div>
            <div class="flex justify-between items-center"><span class="text-slate-500 font-bold">Queue Driver</span><span class="text-slate-900 font-mono">{{ stats.queue_driver }}</span></div>
          </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
          <h3 class="text-sm font-black text-slate-900 mb-4">استخدام القرص</h3>
          <div class="space-y-3 text-xs">
            <div class="flex justify-between items-center"><span class="text-slate-500 font-bold">الإجمالي</span><span class="text-slate-900 font-mono">{{ formatBytes(stats.disk?.total) }}</span></div>
            <div class="flex justify-between items-center"><span class="text-slate-500 font-bold">المستخدم</span><span class="text-slate-900 font-mono">{{ formatBytes(stats.disk?.used) }}</span></div>
            <div class="flex justify-between items-center"><span class="text-slate-500 font-bold">المتاح</span><span class="text-slate-900 font-mono">{{ formatBytes(stats.disk?.free) }}</span></div>
            <div class="flex justify-between items-center"><span class="text-slate-500 font-bold">نسبة الاستخدام</span><span class="text-slate-900 font-mono">{{ stats.disk?.usage_percent }}%</span></div>
            <div class="w-full bg-slate-100 rounded-full h-2 mt-4 overflow-hidden">
              <div class="bg-emerald-500 h-2 rounded-full" :style="{ width: stats.disk?.usage_percent + '%' }"></div>
            </div>
          </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
          <h3 class="text-sm font-black text-slate-900 mb-4">الذاكرة</h3>
          <div class="space-y-3 text-xs">
            <div class="flex justify-between items-center"><span class="text-slate-500 font-bold">الحد الأقصى</span><span class="text-slate-900 font-mono">{{ stats.memory?.limit }}</span></div>
            <div class="flex justify-between items-center"><span class="text-slate-500 font-bold">الاستخدام</span><span class="text-slate-900 font-mono">{{ formatBytes(stats.memory?.usage) }}</span></div>
            <div class="flex justify-between items-center"><span class="text-slate-500 font-bold">الذروة</span><span class="text-slate-900 font-mono">{{ formatBytes(stats.memory?.peak) }}</span></div>
            <div class="flex justify-between items-center"><span class="text-slate-500 font-bold">وقت الخادم</span><span class="text-slate-900 font-mono">{{ formatDate(stats.now) }}</span></div>
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
  name: 'AdminSystemStats',
  components: { AdminLayout },
  setup() {
    const stats = ref({ disk: {}, memory: {} });
    const loading = ref(true);

    const loadStats = async () => {
      try {
        const response = await admin.getSystemStats();
        if (response.success) stats.value = response.data;
      } catch (err) { console.error('Error loading system stats:', err); }
      finally { loading.value = false; }
    };

    const formatBytes = (bytes) => {
      if (!bytes || bytes === 0) return '0 B';
      const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
      const i = Math.floor(Math.log(bytes) / Math.log(1024));
      return parseFloat((bytes / Math.pow(1024, i)).toFixed(2)) + ' ' + sizes[i];
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

    onMounted(() => loadStats());

    return { stats, loading, formatBytes, formatDate };
  },
};
</script>
