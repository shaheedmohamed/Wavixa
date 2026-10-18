<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500" :dir="isRTL ? 'rtl' : 'ltr'">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ campaign.name || t('campaign_show.title') }}</h2>
          <p class="text-gray-500 text-sm mt-1">{{ t('campaign_show.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
          <router-link to="/campaigns" class="text-xs font-bold text-gray-500 hover:text-indigo-600 transition-colors">{{ t('campaign_show.back_to_campaigns') }}</router-link>
          <button v-if="campaign.status === 'running'" @click="pause" :disabled="pausing" class="px-5 py-2.5 bg-orange-50 text-orange-700 rounded-2xl text-xs font-black border border-orange-200/60 hover:bg-orange-100 disabled:opacity-70 disabled:cursor-not-allowed transition-colors active:scale-95 flex items-center gap-2">
            <svg v-if="pausing" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span>{{ pausing ? (isRTL ? 'جاري الإيقاف...' : 'Pausing...') : t('campaign_show.pause') }}</span>
          </button>
          <button v-else-if="campaign.status === 'paused'" @click="resume" :disabled="resuming" class="px-5 py-2.5 bg-green-50 text-green-700 rounded-2xl text-xs font-black border border-green-200/60 hover:bg-green-100 disabled:opacity-70 disabled:cursor-not-allowed transition-colors active:scale-95 flex items-center gap-2">
            <svg v-if="resuming" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span>{{ resuming ? (isRTL ? 'جاري الاستئناف...' : 'Resuming...') : t('campaign_show.resume') }}</span>
          </button>
          <button v-else-if="['draft','pending'].includes(campaign.status)" @click="launch" :disabled="launching" class="px-5 py-2.5 bg-green-600 text-white rounded-2xl text-xs font-black border border-green-600 hover:bg-green-700 disabled:opacity-70 disabled:cursor-not-allowed transition-colors active:scale-95 flex items-center gap-2">
            <svg v-if="launching" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span>{{ launching ? (isRTL ? 'جاري التشغيل...' : 'Launching...') : t('campaign_show.launch') }}</span>
          </button>
          <button v-if="campaign.failed_count > 0 && campaign.status !== 'running'" @click="resendFailed" :disabled="resending" class="px-5 py-2.5 bg-red-50 text-red-700 rounded-2xl text-xs font-black border border-red-200/60 hover:bg-red-100 disabled:opacity-70 disabled:cursor-not-allowed transition-colors active:scale-95 flex items-center gap-2">
            <svg v-if="resending" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span>{{ resending ? (isRTL ? 'جاري إعادة الإرسال...' : 'Resending...') : (isRTL ? `إعادة إرسال الفاشل (${campaign.failed_count})` : `Resend Failed (${campaign.failed_count})`) }}</span>
          </button>
        </div>
      </div>

      <!-- Edit Form -->
      <div v-if="isEditing" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-4">
        <h3 class="text-sm font-black text-gray-900">{{ t('campaign_show.edit_campaign') }}</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-1">{{ t('campaign_create.campaign_name_label') }}</label>
            <input v-model="editForm.name" type="text" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm font-bold text-gray-900 outline-none focus:border-indigo-300" />
          </div>
          <div>
            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-1">{{ t('campaign_create.step_message') }}</label>
            <div v-for="(msg, i) in editForm.messages" :key="i" class="space-y-1 mb-3">
              <div class="flex items-center justify-between">
                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ t('campaign_create.variation_label') }} {{ i + 1 }}</span>
                <button v-if="editForm.messages.length > 1" @click="editForm.messages.splice(i, 1)" class="text-[10px] font-black text-red-500 hover:text-red-700 transition-colors">{{ t('campaign_create.remove_variation') }}</button>
              </div>
              <textarea v-model="editForm.messages[i]" rows="4" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm font-bold text-gray-900 outline-none focus:border-indigo-300 resize-none"></textarea>
            </div>
            <button v-if="editForm.messages.length < 5" @click="editForm.messages.push('')" class="w-full border-2 border-dashed border-gray-200 hover:border-indigo-300 text-gray-500 hover:text-indigo-600 rounded-2xl py-2.5 text-xs font-black transition-all">+ {{ t('campaign_create.add_variation') }}</button>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-1">{{ t('campaign_create.delay_min') }}</label>
              <input v-model.number="editForm.delay_min" type="number" min="1" max="300" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm font-bold text-gray-900 outline-none focus:border-indigo-300" />
            </div>
            <div>
              <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-1">{{ t('campaign_create.delay_max') }}</label>
              <input v-model.number="editForm.delay_max" type="number" min="1" max="300" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm font-bold text-gray-900 outline-none focus:border-indigo-300" />
            </div>
            <div>
              <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-1">{{ t('campaign_create.concurrent') }}</label>
              <input v-model.number="editForm.concurrent" type="number" min="1" max="10" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm font-bold text-gray-900 outline-none focus:border-indigo-300" />
            </div>
          </div>
        </div>
        <div class="flex items-center gap-3 pt-2">
          <button @click="saveEdit" :disabled="saving" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-70 disabled:cursor-not-allowed text-white rounded-2xl text-xs font-black transition-all active:scale-95 flex items-center gap-2">
            <svg v-if="saving" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span>{{ saving ? t('common.saving') : t('campaign_show.save_changes') }}</span>
          </button>
          <button @click="cancelEdit" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-2xl text-xs font-black transition-colors">{{ t('common.cancel') }}</button>
        </div>
      </div>

      <!-- Progress -->
      <div v-if="campaign.status !== 'draft'" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-black text-gray-900">{{ t('campaign_show.progress') }}</h3>
          <span class="text-2xl font-black text-indigo-600">{{ campaign.progress || 0 }}%</span>
        </div>
        <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
          <div class="h-full bg-indigo-600 rounded-full transition-all duration-500" :style="{ width: (campaign.progress || 0) + '%' }"></div>
        </div>
        <p class="text-xs font-bold" :class="statusClass(campaign.status)">{{ statusLabel(campaign.status) }}</p>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <p class="text-[10px] font-bold text-gray-400 uppercase mb-2">{{ t('campaign_show.total_recipients') }}</p>
          <h3 class="text-2xl font-black text-gray-900">{{ Number(campaign.total_recipients || 0).toLocaleString() }}</h3>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <p class="text-[10px] font-bold text-gray-400 uppercase mb-2">{{ t('campaign_show.sent_count') }}</p>
          <h3 class="text-2xl font-black text-gray-900">{{ Number(campaign.sent_count || 0).toLocaleString() }}</h3>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <p class="text-[10px] font-bold text-green-600 uppercase mb-2">{{ t('campaign_show.success_count') }}</p>
          <h3 class="text-2xl font-black text-green-700">{{ Number(campaign.success_count || 0).toLocaleString() }}</h3>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <p class="text-[10px] font-bold text-red-600 uppercase mb-2">{{ t('campaign_show.failed_count') }}</p>
          <h3 class="text-2xl font-black text-red-700">{{ Number(campaign.failed_count || 0).toLocaleString() }}</h3>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <p class="text-[10px] font-bold text-blue-600 uppercase mb-2">{{ t('campaign_show.pending_count') }}</p>
          <h3 class="text-2xl font-black text-blue-700">{{ Number(campaign.pending_count || 0).toLocaleString() }}</h3>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <p class="text-[10px] font-bold text-gray-400 uppercase mb-2">{{ t('campaigns_page.th_created_at') }}</p>
          <h3 class="text-lg font-black text-gray-900">{{ formatDate(campaign.created_at) }}</h3>
        </div>
      </div>

      <!-- Message -->
      <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-4">
        <h3 class="text-sm font-black text-gray-900">{{ t('campaign_create.step_message') }}</h3>
        <template v-if="campaignVariations.length > 1">
          <div v-for="(v, i) in campaignVariations" :key="i" class="space-y-1">
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ t('campaign_create.variation_label') }} {{ i + 1 }}</p>
            <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-wrap bg-gray-50 rounded-2xl p-4">{{ v }}</p>
          </div>
        </template>
        <p v-else class="text-sm text-gray-600 leading-relaxed whitespace-pre-wrap">{{ campaign.message }}</p>
      </div>

      <!-- Recipients -->
      <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-50 bg-gray-50/30 flex items-center justify-between">
          <h3 class="text-sm font-black text-gray-900">{{ t('campaign_show.recipients_log') }}</h3>
          <span class="text-[10px] font-bold text-gray-400">{{ recipients.length }} / {{ campaign.total_recipients }}</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full border-collapse" :class="isRTL ? 'text-right' : 'text-left'">
            <thead class="bg-gray-50/50">
              <tr>
                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">{{ t('campaign_show.th_phone') }}</th>
                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">{{ t('contacts_page.th_name') }}</th>
                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">{{ t('campaign_show.th_status') }}</th>
                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">{{ t('campaign_show.th_sent_at') }}</th>
                <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">{{ t('campaign_show.th_error') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="r in recipients" :key="r.id" class="hover:bg-gray-50/50 transition-colors">
                <td class="px-6 py-3 text-xs font-mono font-bold text-gray-900" dir="ltr">{{ r.phone }}</td>
                <td class="px-6 py-3 text-xs font-bold text-gray-700">{{ r.name || '-' }}</td>
                <td class="px-6 py-3">
                  <span :class="statusClass(r.status)" class="px-2 py-1 rounded-full text-[10px] font-black uppercase">{{ recipientStatus(r.status) }}</span>
                </td>
                <td class="px-6 py-3 text-[10px] font-bold text-gray-400">{{ r.sent_at ? new Date(r.sent_at).toLocaleTimeString(isRTL ? 'ar-EG' : 'en-US', {hour: '2-digit', minute: '2-digit'}) : '-' }}</td>
                <td class="px-6 py-3 text-[10px] font-bold" :class="r.status === 'failed' ? 'text-red-600' : 'text-gray-400'">{{ r.error_message || '-' }}</td>
              </tr>
              <tr v-if="recipients.length === 0">
                <td colspan="5" class="px-6 py-12 text-center text-sm font-bold text-gray-400">{{ isRTL ? 'لا يوجد مستلمون' : 'No recipients found' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="nextPage" class="px-6 py-4 border-t border-gray-50 text-center">
          <button @click="loadMore" class="text-xs font-black text-indigo-600 hover:underline">{{ isRTL ? 'عرض المزيد' : 'Load More' }}</button>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/services/api';
import auth from '@/services/auth';
import { t, isRTL } from '@/i18n';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

export default {
  name: 'CampaignShow',
  components: { DashboardLayout },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const campaign = ref({});
    const recipients = ref([]);
    const currentPage = ref(1);
    const lastPage = ref(1);
    const pausing = ref(false);
    const resuming = ref(false);
    const launching = ref(false);
    const resending = ref(false);
    const saving = ref(false);
    const editForm = ref({
      name: '',
      messages: [''],
      delay_min: 10,
      delay_max: 25,
      concurrent: 1,
    });
    const isEditing = computed(() => route.query.edit === '1');

    const campaignVariations = computed(() => {
      const vars = Array.isArray(campaign.value.message_variations)
        ? campaign.value.message_variations.filter(v => (v || '').trim().length > 0)
        : [];
      return vars.length ? vars : (campaign.value.message ? [campaign.value.message] : []);
    });
    let pollInterval = null;

    const statusLabel = (status) => {
      const labels = {
        draft: t('campaigns_page.status_draft'),
        pending: t('campaigns_page.status_pending'),
        running: t('campaigns_page.status_running'),
        paused: t('campaigns_page.status_paused'),
        completed: t('campaigns_page.status_completed'),
        failed: t('campaigns_page.status_failed'),
      };
      return labels[status] || status;
    };

    const recipientStatus = (status) => {
      const labels = {
        pending: isRTL.value ? 'قيد الانتظار' : 'Pending',
        queued: isRTL.value ? 'في قائمة الإرسال' : 'Queued',
        sent: isRTL.value ? 'تم الإرسال' : 'Sent',
        delivered: isRTL.value ? 'تم التسليم' : 'Delivered',
        read: isRTL.value ? 'تمت القراءة' : 'Read',
        failed: isRTL.value ? 'فشل' : 'Failed',
      };
      return labels[status] || status;
    };

    const statusClass = (status) => ({
      draft: 'bg-gray-100 text-gray-600',
      pending: 'bg-yellow-50 text-yellow-600',
      running: 'bg-blue-50 text-blue-600',
      paused: 'bg-orange-50 text-orange-600',
      completed: 'bg-green-50 text-green-600',
      failed: 'bg-red-50 text-red-600',
      sent: 'bg-indigo-50 text-indigo-600',
      delivered: 'bg-green-50 text-green-600',
      read: 'bg-purple-50 text-purple-600',
      queued: 'bg-blue-50 text-blue-600',
    }[status]);

    const loadCampaign = async () => {
      try {
        const res = await api.get(`/whatsapp/campaigns/${route.params.id}`);
        if (res.success) {
          campaign.value = res.data;
          editForm.value = {
            name: res.data.name || '',
            messages: Array.isArray(res.data.message_variations) && res.data.message_variations.length
              ? [...res.data.message_variations]
              : [res.data.message || ''],
            delay_min: res.data.delay_min ?? 10,
            delay_max: res.data.delay_max ?? 25,
            concurrent: res.data.concurrent ?? 1,
          };
        }
      } catch (err) {
        console.error('Error loading campaign:', err);
      }
    };

    const loadRecipients = async (page = 1) => {
      try {
        const res = await api.get(`/whatsapp/campaigns/${route.params.id}/recipients`, { params: { page } });
        if (res.success) {
          const data = res.data.data || [];
          if (page === 1) {
            recipients.value = data;
          } else {
            recipients.value = [...recipients.value, ...data];
          }
          currentPage.value = res.data.current_page || 1;
          lastPage.value = res.data.last_page || 1;
        }
      } catch (err) {
        console.error('Error loading recipients:', err);
      }
    };

    const nextPage = computed(() => currentPage.value < lastPage.value ? currentPage.value + 1 : null);

    const loadMore = () => {
      if (!nextPage.value) return;
      loadRecipients(nextPage.value);
    };

    const pause = async () => {
      if (pausing.value) return;
      pausing.value = true;
      try {
        const res = await api.post(`/whatsapp/campaigns/${route.params.id}/pause`);
        if (!res.success) throw new Error(res.message || (isRTL.value ? 'فشل إيقاف الحملة' : 'Failed to pause campaign'));
        await loadCampaign();
      } catch (err) {
        alert(err.data?.message || err.message || (isRTL.value ? 'فشل إيقاف الحملة' : 'Failed to pause campaign'));
      } finally {
        pausing.value = false;
      }
    };

    const resume = async () => {
      if (resuming.value) return;
      resuming.value = true;
      try {
        const res = await api.post(`/whatsapp/campaigns/${route.params.id}/resume`);
        if (!res.success) throw new Error(res.message || (isRTL.value ? 'فشل استئناف الحملة' : 'Failed to resume campaign'));
        await loadCampaign();
      } catch (err) {
        alert(err.data?.message || err.message || (isRTL.value ? 'فشل استئناف الحملة' : 'Failed to resume campaign'));
      } finally {
        resuming.value = false;
      }
    };

    const launch = async () => {
      if (launching.value) return;
      launching.value = true;
      try {
        const res = await api.post(`/whatsapp/campaigns/${route.params.id}/launch`);
        if (!res.success) throw new Error(res.message || (isRTL.value ? 'فشل تشغيل الحملة' : 'Failed to launch campaign'));
        await loadCampaign();
      } catch (err) {
        alert(err.data?.message || err.message || (isRTL.value ? 'فشل تشغيل الحملة' : 'Failed to launch campaign'));
      } finally {
        launching.value = false;
      }
    };

    const resendFailed = async () => {
      if (resending.value) return;
      resending.value = true;
      try {
        const res = await api.post(`/whatsapp/campaigns/${route.params.id}/resend-failed`);
        if (!res.success) throw new Error(res.message || (isRTL.value ? 'فشل إعادة إرسال الرسائل الفاشلة' : 'Failed to resend failed messages'));
        await loadCampaign();
      } catch (err) {
        alert(err.data?.message || err.message || (isRTL.value ? 'فشل إعادة إرسال الرسائل الفاشلة' : 'Failed to resend failed messages'));
      } finally {
        resending.value = false;
      }
    };

    const saveEdit = async () => {
      if (saving.value) return;
      saving.value = true;
      try {
        const res = await api.put(`/whatsapp/campaigns/${route.params.id}`, editForm.value);
        if (!res.success) throw new Error(res.message || (isRTL.value ? 'فشل تحديث الحملة' : 'Failed to update campaign'));
        await router.replace({ query: {} });
        await loadCampaign();
      } catch (err) {
        alert(err.data?.message || err.message || (isRTL.value ? 'فشل تحديث الحملة' : 'Failed to update campaign'));
      } finally {
        saving.value = false;
      }
    };

    const cancelEdit = () => {
      router.replace({ query: {} });
    };

    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleDateString(isRTL.value ? 'ar-EG' : 'en-US');
    };

    onMounted(() => {
      const user = auth.getUser();
      if (!user) {
        router.push('/login');
        return;
      }
      loadCampaign();
      loadRecipients();
      pollInterval = setInterval(() => {
        if (['running', 'pending'].includes(campaign.value.status)) {
          loadCampaign();
        }
      }, 5000);
    });

    onUnmounted(() => {
      if (pollInterval) clearInterval(pollInterval);
    });

    return {
      campaign,
      recipients,
      nextPage,
      statusLabel,
      recipientStatus,
      statusClass,
      pausing,
      resuming,
      launching,
      resending,
      saving,
      isEditing,
      editForm,
      campaignVariations,
      pause,
      resume,
      launch,
      resendFailed,
      saveEdit,
      cancelEdit,
      loadMore,
      formatDate,
      t,
      isRTL,
    };
  },
};
</script>

<style scoped>
.animate-in {
  animation: animate-in 0.3s ease-out;
}
@keyframes animate-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
