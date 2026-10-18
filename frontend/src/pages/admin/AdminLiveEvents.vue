<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="pb-2 border-b border-slate-200/60">
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">المراقبة المباشرة</h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">الأحداث الفورية عبر المنصة (يتم التحديث كل 5 ثوانٍ)</p>
      </div>

      <!-- Filters -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
        <div class="flex flex-col md:flex-row gap-4">
          <select 
            v-model="typeFilter" 
            @change="load" 
            class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all min-w-[200px]"
          >
            <option value="">جميع الأنواع</option>
            <option v-for="type in eventTypes" :key="type" :value="type">{{ type }}</option>
          </select>
          <button 
            @click="load" 
            class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-colors cursor-pointer shadow-sm"
          >
            تحديث الآن
          </button>
        </div>
      </div>

      <!-- Events Table -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">النوع</th>
                <th class="px-5 py-3.5">العنوان</th>
                <th class="px-5 py-3.5">المستخدم</th>
                <th class="px-5 py-3.5">الكيان</th>
                <th class="px-5 py-3.5">الوصف</th>
                <th class="px-5 py-3.5">الوقت</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="event in events" :key="event.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-4"><span class="px-2 py-0.5 rounded text-[10px] font-bold border" :class="eventTypeClass(event.type)">{{ event.type }}</span></td>
                <td class="px-5 py-4 text-slate-900 font-bold">{{ event.title }}</td>
                <td class="px-5 py-4 text-slate-900">{{ event.user?.name ?? 'النظام' }}</td>
                <td class="px-5 py-4 text-slate-600">{{ event.entity_type }}{{ event.entity_id ? `#${event.entity_id}` : '' }}</td>
                <td class="px-5 py-4 text-slate-600 max-w-xs truncate">{{ event.description }}</td>
                <td class="px-5 py-4 text-slate-500">{{ formatDate(event.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="events.length === 0" class="text-center py-12 text-slate-400 text-xs">لا توجد أحداث حالية</div>
      </div>
    </div>
  </AdminLayout>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue';
import admin from '@/services/admin';
import AdminLayout from '@/layouts/AdminLayout.vue';

export default {
  name: 'AdminLiveEvents',
  components: { AdminLayout },
  setup() {
    const events = ref([]);
    const typeFilter = ref('');
    const eventTypes = ['login', 'logout', 'device_connected', 'device_disconnected', 'message_sent', 'message_failed', 'api_key_created', 'subscription_purchased', 'subscription_expired', 'session_expired'];
    let pollInterval = null;

    const load = async () => {
      try {
        const params = { type: typeFilter.value, limit: 50 };
        const response = await admin.getLiveEvents(params);
        if (response.success) events.value = response.data;
      } catch (err) { console.error(err); }
    };

    const eventTypeClass = (type) => {
      const map = {
        login: 'bg-emerald-50 text-emerald-700 border-emerald-200/70',
        logout: 'bg-slate-100 text-slate-700 border-slate-200/70',
        device_connected: 'bg-blue-50 text-blue-700 border-blue-200/70',
        device_disconnected: 'bg-amber-50 text-amber-700 border-amber-200/70',
        message_sent: 'bg-emerald-50 text-emerald-700 border-emerald-200/70',
        message_failed: 'bg-red-50 text-red-700 border-red-200/70',
        api_key_created: 'bg-purple-50 text-purple-700 border-purple-200/70',
        subscription_purchased: 'bg-emerald-50 text-emerald-700 border-emerald-200/70',
        subscription_expired: 'bg-amber-50 text-amber-700 border-amber-200/70',
        session_expired: 'bg-orange-50 text-orange-700 border-orange-200/70',
      };
      return map[type] || 'bg-slate-100 text-slate-700 border-slate-200/70';
    };

    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('ar-SA', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      });
    };

    onMounted(() => { load(); pollInterval = setInterval(load, 5000); });
    onUnmounted(() => { if (pollInterval) clearInterval(pollInterval); });

    return { events, typeFilter, eventTypes, load, eventTypeClass, formatDate };
  },
};
</script>
