<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500" :dir="isRTL ? 'rtl' : 'ltr'">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('campaigns_page.title') }}</h2>
          <p class="text-gray-500 text-sm mt-1">{{ t('campaigns_page.subtitle') }}</p>
        </div>
        <template v-if="mode === 'wavixa'">
          <router-link
            v-if="createAllowed"
            to="/campaigns/new"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-3 rounded-2xl font-black text-sm transition-all shadow-lg shadow-indigo-100 active:scale-95"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>{{ t('campaigns_page.create_campaign') }}</span>
          </router-link>
          <button
            v-else
            type="button"
            disabled
            class="inline-flex items-center gap-2 bg-indigo-300 text-white px-5 py-3 rounded-2xl font-black text-sm cursor-not-allowed opacity-80"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>{{ t('campaigns_page.create_campaign') }}</span>
            <span class="px-2 py-0.5 rounded-full bg-white/40 text-[10px] font-black uppercase">Pro</span>
          </button>
        </template>
      </div>

      <p class="text-[10px] font-bold text-gray-400 -mt-4">
        {{ isRTL ? 'الإرسال من خلال جهاز WhatsApp المتصل بـ Wavixa.' : 'Sending through your connected WhatsApp device on Wavixa.' }}
      </p>

      <!-- Wavixa Content -->
      <template v-if="mode === 'wavixa'">
        <!-- Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
          <div v-for="stat in stats" :key="stat.label" class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">{{ stat.label }}</p>
            <h3 class="text-2xl font-black text-gray-900">{{ Number(stat.value || 0).toLocaleString() }}</h3>
          </div>
        </div>

        <!-- Campaigns Table -->
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="px-6 py-5 border-b border-gray-50 bg-gray-50/30 flex items-center justify-between">
            <h3 class="text-sm font-black text-gray-900">{{ t('campaigns_page.previous_campaigns') }}</h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full border-collapse" :class="isRTL ? 'text-right' : 'text-left'">
              <thead class="bg-gray-50/50">
                <tr>
                  <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">{{ t('campaigns_page.th_name') }}</th>
                  <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">{{ t('campaigns_page.th_device') }}</th>
                  <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">{{ t('campaigns_page.th_recipients') }}</th>
                  <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">{{ t('campaigns_page.th_success') }}</th>
                  <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">{{ t('campaigns_page.th_failed') }}</th>
                  <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">{{ t('campaigns_page.th_status') }}</th>
                  <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">{{ t('campaigns_page.th_created_at') }}</th>
                  <th class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider" :class="isRTL ? 'text-left' : 'text-right'">{{ t('campaigns_page.th_actions') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <tr v-for="campaign in campaigns" :key="campaign.id" class="hover:bg-gray-50/50 transition-colors">
                  <td class="px-6 py-4 text-sm font-bold text-gray-900">{{ campaign.name }}</td>
                  <td class="px-6 py-4 text-xs font-mono font-bold text-gray-600" dir="ltr">{{ campaign.session?.phone_number || '-' }}</td>
                  <td class="px-6 py-4 text-sm font-black text-gray-900">{{ Number(campaign.total_recipients || 0).toLocaleString() }}</td>
                  <td class="px-6 py-4 text-sm font-bold text-green-600">{{ Number(campaign.success_count || 0).toLocaleString() }}</td>
                  <td class="px-6 py-4 text-sm font-bold text-red-600">{{ Number(campaign.failed_count || 0).toLocaleString() }}</td>
                  <td class="px-6 py-4">
                    <span :class="statusClass(campaign.status)" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider">{{ statusLabel(campaign.status) }}</span>
                  </td>
                  <td class="px-6 py-4 text-xs font-bold text-gray-400">{{ formatDate(campaign.created_at) }}</td>
                  <td class="px-6 py-4" :class="isRTL ? 'text-left' : 'text-right'">
                    <div class="flex items-center gap-2" :class="isRTL ? 'justify-end' : 'justify-end'">
                      <router-link :to="`/campaigns/${campaign.id}`" class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-xl transition-colors" :title="t('campaigns_page.view_details')">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                      </router-link>
                      <router-link v-if="campaign.status === 'draft'" :to="`/campaigns/${campaign.id}?edit=1`" class="p-2 text-amber-600 hover:bg-amber-50 rounded-xl transition-colors" :title="t('campaigns_page.resume_draft')">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                      </router-link>
                      <button v-if="campaign.status === 'running'" @click="pauseCampaign(campaign.id)" class="p-2 text-orange-600 hover:bg-orange-50 rounded-xl transition-colors" :title="t('campaigns_page.pause_campaign')">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                      </button>
                      <button v-else-if="campaign.status === 'paused'" @click="resumeCampaign(campaign.id)" class="p-2 text-green-600 hover:bg-green-50 rounded-xl transition-colors" :title="t('campaigns_page.resume_campaign')">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                      </button>
                      <button v-if="campaign.status === 'draft'" @click="deleteCampaign(campaign.id)" class="p-2 text-red-600 hover:bg-red-50 rounded-xl transition-colors" :title="t('campaigns_page.delete_draft')">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="campaigns.length === 0">
                  <td colspan="8" class="px-6 py-12 text-center">
                    <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-300">
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 003.5 0V5.882M17 9.176V19.24a1.76 1.76 0 003.5 0V9.176M5 14.294V19.24a1.76 1.76 0 003.5 0v-4.946" /></svg>
                    </div>
                    <p class="text-sm font-bold text-gray-400">{{ t('campaigns_page.no_campaigns') }}</p>
                    <router-link to="/campaigns/new" class="text-xs font-bold text-indigo-600 mt-1 inline-block hover:underline">{{ t('campaigns_page.create_first_campaign') }}</router-link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, computed, onMounted } from 'vue';

import api from '@/services/api';
import auth from '@/services/auth';
import { t, isRTL } from '@/i18n';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

export default {
  name: 'Campaigns',
  components: { DashboardLayout },
  setup() {
    const campaigns = ref([]);
    const stats = ref([]);
    const loading = ref(true);
    const mode = ref('wavixa');
    const subscription = ref(null);
    const user = ref(auth.getUser());

    const CAMPAIGN_PLAN_SLUGS = [
      'pro', 'pro-yearly',
      'business', 'business-yearly',
      'partners', 'partners-yearly',
    ];

    const planSlug = computed(() =>
      subscription.value?.plan?.slug || user.value?.subscription_plan || ''
    );

    const createAllowed = computed(() =>
      user.value?.is_admin || CAMPAIGN_PLAN_SLUGS.includes(planSlug.value)
    );

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

    const statusClass = (status) => ({
      draft: 'bg-gray-100 text-gray-600',
      pending: 'bg-yellow-50 text-yellow-600',
      running: 'bg-blue-50 text-blue-600',
      paused: 'bg-orange-50 text-orange-600',
      completed: 'bg-green-50 text-green-600',
      failed: 'bg-red-50 text-red-600',
    }[status]);

    const loadStats = async () => {
      try {
        const response = await api.get('/whatsapp/campaigns/stats');
        if (response.success) {
          const d = response.data;
          stats.value = [
            { label: isRTL.value ? 'إجمالي الحملات' : 'Total Campaigns', value: d.total },
            { label: isRTL.value ? 'الحملات النشطة' : 'Active Campaigns', value: d.active },
            { label: isRTL.value ? 'الحملات المكتملة' : 'Completed Campaigns', value: d.completed },
            { label: isRTL.value ? 'إجمالي الرسائل' : 'Total Messages', value: d.total_messages },
            { label: isRTL.value ? 'الرسائل الناجحة' : 'Successful Messages', value: d.successful },
            { label: isRTL.value ? 'الرسائل الفاشلة' : 'Failed Messages', value: d.failed },
          ];
        }
      } catch (err) {
        console.error('Error loading campaign stats:', err);
      }
    };

    const loadCampaigns = async () => {
      try {
        loading.value = true;
        const response = await api.get('/whatsapp/campaigns');
        if (response.success) {
          campaigns.value = response.data.data || [];
        }
      } catch (err) {
        console.error('Error loading campaigns:', err);
      } finally {
        loading.value = false;
      }
    };

    const loadSubscription = async () => {
      try {
        const response = await api.get('/subscription');
        if (response.success) {
          subscription.value = response.data;
        }
      } catch (err) {
        console.error('Failed to load subscription:', err);
      }
    };

    const pauseCampaign = async (id) => {
      try {
        await api.post(`/whatsapp/campaigns/${id}/pause`);
        await loadCampaigns();
      } catch (err) {
        alert(err.data?.message || (isRTL.value ? 'فشل إيقاف الحملة' : 'Failed to pause campaign'));
      }
    };

    const resumeCampaign = async (id) => {
      try {
        await api.post(`/whatsapp/campaigns/${id}/resume`);
        await loadCampaigns();
      } catch (err) {
        alert(err.data?.message || (isRTL.value ? 'فشل استئناف الحملة' : 'Failed to resume campaign'));
      }
    };

    const deleteCampaign = async (id) => {
      if (!confirm(isRTL.value ? 'هل أنت متأكد من حذف هذه الحملة؟' : 'Are you sure you want to delete this campaign?')) return;
      try {
        await api.delete(`/whatsapp/campaigns/${id}`);
        await loadCampaigns();
      } catch (err) {
        alert(err.data?.message || (isRTL.value ? 'فشل حذف الحملة' : 'Failed to delete campaign'));
      }
    };

    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleDateString(isRTL.value ? 'ar-EG' : 'en-US');
    };

    onMounted(() => {
      const user = auth.getUser();
      if (!user) {
        window.location.href = '/login';
        return;
      }
      loadStats();
      loadCampaigns();
      loadSubscription();
    });

    return {
      campaigns,
      stats,
      loading,
      mode,
      createAllowed,
      statusLabel,
      statusClass,
      pauseCampaign,
      resumeCampaign,
      deleteCampaign,
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
