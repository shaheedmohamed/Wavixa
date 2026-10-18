<template>
  <DashboardLayout>
    <div v-if="campaign" class="space-y-6 animate-in fade-in duration-500">
      <div class="flex items-center justify-between">
        <div>
          <router-link to="/messenger/campaigns" class="text-xs font-bold text-gray-500 hover:text-[#0084FF]">← العودة للحملات</router-link>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight mt-1">{{ campaign.name }}</h2>
          <p class="text-gray-500 text-sm">{{ campaign.page?.page_name }} | {{ campaign.status }}</p>
        </div>
        <div class="flex gap-2">
          <button v-if="campaign.status === 'running'" @click="pause" class="px-5 py-2 bg-amber-50 text-amber-700 text-xs font-black rounded-xl border border-amber-100 hover:bg-amber-100">إيقاف</button>
          <button v-if="campaign.status === 'paused'" @click="resume" class="px-5 py-2 bg-emerald-50 text-emerald-700 text-xs font-black rounded-xl border border-emerald-100 hover:bg-emerald-100">استئناف</button>
          <button @click="resendFailed" :disabled="campaign.failed_count === 0" class="px-5 py-2 bg-blue-50 text-blue-700 text-xs font-black rounded-xl border border-blue-100 hover:bg-blue-100 disabled:opacity-50">إعادة الفاشل</button>
        </div>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm"><p class="text-[10px] font-bold text-gray-400 uppercase">إجمالي</p><p class="text-xl font-black text-gray-900">{{ campaign.total_recipients }}</p></div>
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm"><p class="text-[10px] font-bold text-gray-400 uppercase">متبقي</p><p class="text-xl font-black text-blue-600">{{ campaign.pending_count }}</p></div>
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm"><p class="text-[10px] font-bold text-gray-400 uppercase">ناجح</p><p class="text-xl font-black text-emerald-600">{{ campaign.success_count }}</p></div>
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm"><p class="text-[10px] font-bold text-gray-400 uppercase">فاشل</p><p class="text-xl font-black text-red-600">{{ campaign.failed_count }}</p></div>
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm"><p class="text-[10px] font-bold text-gray-400 uppercase">مستبعد</p><p class="text-xl font-black text-amber-600">{{ campaign.excluded_count }}</p></div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h3 class="text-sm font-black text-gray-900 mb-4">نص الرسالة</h3>
        <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 text-xs font-medium text-gray-700 whitespace-pre-wrap">{{ campaign.message }}</div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
          <h3 class="text-sm font-black text-gray-900">سجل المستلمين</h3>
          <select v-model="statusFilter" @change="loadRecipients" class="bg-gray-50 border border-gray-100 rounded-xl px-3 py-1.5 text-xs font-bold outline-none focus:border-[#0084FF]">
            <option value="">الكل</option>
            <option value="pending">متبقي</option>
            <option value="sent">ناجح</option>
            <option value="failed">فاشل</option>
            <option value="excluded">مستبعد</option>
          </select>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-right">
            <thead>
              <tr class="bg-gray-50/50 border-b border-gray-50">
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase">PSID</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase">الاسم</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase">الحالة</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase">الخطأ</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase">تاريخ الإرسال</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-if="loadingRecipients"><td colspan="5" class="px-6 py-12 text-center text-gray-400 text-xs font-bold">{{ t('common.loading') }}</td></tr>
              <tr v-else-if="recipients.length === 0"><td colspan="5" class="px-6 py-20 text-center text-gray-900 font-bold">لا يوجد سجل.</td></tr>
              <tr v-else v-for="r in recipients" :key="r.id">
                <td class="px-6 py-4 text-xs font-mono text-gray-600" dir="ltr">{{ r.psid }}</td>
                <td class="px-6 py-4 text-xs font-black text-gray-900">{{ r.name || '-' }}</td>
                <td class="px-6 py-4"><span class="px-2 py-0.5 rounded-md text-[10px] font-black" :class="statusClass(r.status)">{{ r.status }}</span></td>
                <td class="px-6 py-4 text-[10px] text-red-600 font-bold">{{ r.error_message || '-' }}</td>
                <td class="px-6 py-4 text-xs text-gray-400 font-bold">{{ formatDate(r.sent_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div v-else class="h-96 flex items-center justify-center text-gray-400 text-sm font-medium">{{ t('common.loading') }}</div>
  </DashboardLayout>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import api from '@/services/api';
import { t } from '@/i18n';

export default {
  name: 'MessengerCampaignShow',
  components: { DashboardLayout },
  setup() {
    const route = useRoute();
    const id = route.params.id;
    const campaign = ref(null);
    const recipients = ref([]);
    const loadingRecipients = ref(false);
    const statusFilter = ref('');

    const loadCampaign = async () => {
      try {
        const res = await api.get(`/messenger/campaigns/${id}`);
        if (res.success) campaign.value = res.data;
      } catch (err) { console.error('Failed to load campaign:', err); }
    };

    const loadRecipients = async () => {
      try {
        loadingRecipients.value = true;
        const res = await api.get(`/messenger/campaigns/${id}/recipients`, { params: { status: statusFilter.value, per_page: 50 } });
        if (res.success) recipients.value = res.data.data || [];
      } catch (err) { console.error('Failed to load recipients:', err); }
      finally { loadingRecipients.value = false; }
    };

    const refresh = async () => { await loadCampaign(); await loadRecipients(); };

    const pause = async () => { await api.post(`/messenger/campaigns/${id}/pause`); await refresh(); };
    const resume = async () => { await api.post(`/messenger/campaigns/${id}/resume`); await refresh(); };
    const resendFailed = async () => { await api.post(`/messenger/campaigns/${id}/resend-failed`); await refresh(); };

    const statusClass = (status) => ({
      pending: 'bg-gray-100 text-gray-600',
      sent: 'bg-emerald-50 text-emerald-600',
      failed: 'bg-red-50 text-red-600',
      excluded: 'bg-amber-50 text-amber-600',
    }[status] || 'bg-gray-100 text-gray-600');

    const formatDate = (d) => d ? new Date(d).toLocaleDateString('ar-EG') : '-';

    onMounted(() => { loadCampaign(); loadRecipients(); });

    return { campaign, recipients, loadingRecipients, statusFilter, loadRecipients, pause, resume, resendFailed, statusClass, formatDate, t };
  },
};
</script>
