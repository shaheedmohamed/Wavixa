<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <!-- Top Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="w-2.5 h-2.5 rounded-full bg-[#229ED9]"></span>
            <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ campaign?.name || 'تفاصيل الحملة' }}</h2>
            <span
              v-if="campaign"
              :class="[
                'px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wider',
                statusClass(campaign.status)
              ]"
            >
              {{ statusText(campaign.status) }}
            </span>
          </div>
          <p class="text-gray-500 text-sm">إحصائيات الإرسال المباشرة وسجلات المستلمين للحملة.</p>
        </div>

        <!-- Action buttons -->
        <div v-if="campaign" class="flex items-center gap-2">
          <button
            v-if="campaign.status === 'draft' || campaign.status === 'paused'"
            @click="launchOrResume"
            class="px-4 py-2 bg-[#229ED9] hover:bg-[#1c8ec4] text-white text-xs font-black rounded-xl transition-all shadow-sm flex items-center gap-1.5"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /></svg>
            <span>{{ campaign.status === 'draft' ? 'إطلاق الحملة' : 'استئناف الحملة' }}</span>
          </button>

          <button
            v-if="campaign.status === 'running'"
            @click="pauseCampaign"
            class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-black rounded-xl transition-all shadow-sm flex items-center gap-1.5"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6" /></svg>
            <span>إيقاف مؤقت</span>
          </button>

          <button
            v-if="campaign.failed_count > 0"
            @click="resendFailed"
            class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-black rounded-xl border border-indigo-200/60 transition-all"
          >
            إعادة إرسال الفاشل ({{ campaign.failed_count }})
          </button>

          <router-link
            to="/telegram/campaigns"
            class="px-4 py-2 bg-gray-50 hover:bg-gray-100 text-gray-700 text-xs font-bold rounded-xl border border-gray-200 transition-colors"
          >
            ← العودة
          </router-link>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="py-12 flex justify-center">
        <svg class="w-8 h-8 text-[#229ED9] animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
      </div>

      <template v-else-if="campaign">
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
            <span class="text-[10px] font-bold text-gray-400 block uppercase mb-1">إجمالي المستلمين</span>
            <span class="text-2xl font-black text-gray-900">{{ campaign.total_recipients || 0 }}</span>
          </div>
          <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
            <span class="text-[10px] font-bold text-gray-400 block uppercase mb-1">تم الإرسال بنجاح</span>
            <span class="text-2xl font-black text-emerald-600">{{ campaign.success_count || 0 }}</span>
          </div>
          <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
            <span class="text-[10px] font-bold text-gray-400 block uppercase mb-1">فشل الإرسال</span>
            <span class="text-2xl font-black text-red-600">{{ campaign.failed_count || 0 }}</span>
          </div>
          <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
            <span class="text-[10px] font-bold text-gray-400 block uppercase mb-1">نسبة التسليم الناجح</span>
            <span class="text-2xl font-black text-sky-600">{{ deliveryRate }}%</span>
          </div>
        </div>

        <!-- Live Progress Bar -->
        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm space-y-3">
          <div class="flex items-center justify-between text-xs font-bold text-gray-700">
            <span>تقدم الإرسال</span>
            <span>{{ campaign.success_count + campaign.failed_count }} من {{ campaign.total_recipients }} ({{ progressPercent }}%)</span>
          </div>
          <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden flex">
            <div class="bg-emerald-500 h-full transition-all duration-500" :style="{ width: progressPercent + '%' }"></div>
            <div class="bg-red-500 h-full transition-all duration-500" :style="{ width: failedPercent + '%' }"></div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <!-- Recipients Table -->
          <div class="lg:col-span-2 bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
              <h3 class="text-xs font-black text-gray-900 uppercase">قائمة المستلمين</h3>
              <div class="flex items-center gap-1 bg-gray-50 p-1 rounded-xl">
                <button
                  v-for="st in ['all', 'sent', 'failed', 'pending']"
                  :key="st"
                  @click="filterStatus = st; loadRecipients()"
                  :class="[
                    'px-3 py-1 rounded-lg text-xs font-bold transition-all',
                    filterStatus === st ? 'bg-white text-[#229ED9] shadow-2xs' : 'text-gray-500'
                  ]"
                >
                  {{ filterText(st) }}
                </button>
              </div>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full border-collapse text-right text-xs">
                <thead class="bg-gray-50/50 text-[10px] text-gray-400 font-black uppercase">
                  <tr>
                    <th class="px-6 py-3">المستلم (Chat ID / Username)</th>
                    <th class="px-6 py-3">الحالة</th>
                    <th class="px-6 py-3">وقت الإرسال</th>
                    <th class="px-6 py-3">التفاصيل</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                  <tr v-for="r in recipients" :key="r.id" class="hover:bg-gray-50/50">
                    <td class="px-6 py-3.5 font-bold font-mono text-gray-900" dir="ltr">
                      {{ r.chat_id }}
                    </td>
                    <td class="px-6 py-3.5">
                      <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase', recipientStatusClass(r.status)]">
                        {{ recipientStatusText(r.status) }}
                      </span>
                    </td>
                    <td class="px-6 py-3.5 text-gray-500">
                      {{ formatTime(r.sent_at) }}
                    </td>
                    <td class="px-6 py-3.5 text-[11px] text-red-600 font-medium truncate max-w-xs">
                      {{ r.error_message || '-' }}
                    </td>
                  </tr>
                  <tr v-if="recipients.length === 0">
                    <td colspan="4" class="px-6 py-8 text-center text-gray-400 font-bold">لا يوجد مستلمون مطابقون.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Message Card -->
          <div class="space-y-6">
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 space-y-4">
              <div class="flex items-center justify-between">
                <h3 class="text-xs font-black text-gray-900 uppercase tracking-wider">محتوى رسالة الحملة</h3>
                <button
                  v-if="canEdit && !isEditing"
                  @click="startEdit"
                  class="px-3 py-1.5 bg-gray-50 hover:bg-gray-100 text-gray-700 text-[10px] font-black rounded-lg border border-gray-200 transition-all"
                >
                  تعديل
                </button>
              </div>

              <div v-if="campaign.type === 'photo' && campaign.media_url" class="rounded-2xl overflow-hidden max-h-48 bg-gray-100">
                <img :src="campaign.media_url" class="w-full h-full object-cover" alt="Campaign media" />
              </div>

              <template v-if="!isEditing">
                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 text-xs font-medium text-gray-800 whitespace-pre-wrap leading-relaxed">
                  {{ campaign.message }}
                </div>

                <div v-if="campaign.button_text && campaign.button_url" class="p-2.5 bg-sky-50 rounded-xl text-center text-xs font-black text-[#229ED9] border border-sky-200">
                  زر تفاعلي: {{ campaign.button_text }} ({{ campaign.button_url }})
                </div>
              </template>

              <form v-else @submit.prevent="saveEdit" class="space-y-3">
                <div>
                  <label class="block text-[10px] font-bold text-gray-500 mb-1">نص الرسالة</label>
                  <textarea
                    v-model="editForm.message"
                    rows="6"
                    class="w-full bg-gray-50 border border-gray-200 rounded-2xl p-3 text-xs font-medium text-gray-900 focus:bg-white focus:border-[#229ED9] outline-none resize-none"
                  ></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">نص الزر التفاعلي</label>
                    <input
                      v-model="editForm.button_text"
                      type="text"
                      class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold outline-none"
                    />
                  </div>
                  <div>
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">رابط الزر</label>
                    <input
                      v-model="editForm.button_url"
                      type="url"
                      dir="ltr"
                      class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold outline-none"
                    />
                  </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                  <button
                    type="button"
                    @click="cancelEdit"
                    class="px-4 py-2 bg-gray-50 text-gray-600 text-xs font-black rounded-xl border border-gray-200 hover:bg-gray-100"
                  >
                    إلغاء
                  </button>
                  <button
                    type="submit"
                    :disabled="isSaving || !editForm.message"
                    class="px-4 py-2 bg-[#229ED9] hover:bg-[#1c8ec4] disabled:opacity-50 text-white text-xs font-black rounded-xl"
                  >
                    {{ isSaving ? 'جاري الحفظ...' : 'حفظ' }}
                  </button>
                </div>
              </form>

              <div class="text-[10px] text-gray-400 font-bold space-y-1 pt-2 border-t border-gray-100">
                <div>البوت: @{{ campaign.session?.bot_username || '-' }}</div>
                <div>الفارق الزمني: {{ campaign.delay_seconds }} ثانية</div>
                <div>تاريخ البدء: {{ formatDate(campaign.started_at) }}</div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import api from '@/services/api';

export default {
  name: 'TelegramCampaignShow',
  components: { DashboardLayout },
  setup() {
    const route = useRoute();
    const campaignId = route.params.id;
    const campaign = ref(null);
    const recipients = ref([]);
    const filterStatus = ref('all');
    const loading = ref(true);
    const isEditing = ref(false);
    const isSaving = ref(false);
    const editForm = ref({ message: '', button_text: '', button_url: '' });
    let pollInterval = null;

    const deliveryRate = computed(() => {
      if (!campaign.value || !campaign.value.total_recipients) return 0;
      return Math.round(((campaign.value.success_count || 0) / campaign.value.total_recipients) * 100);
    });

    const progressPercent = computed(() => {
      if (!campaign.value || !campaign.value.total_recipients) return 0;
      return Math.round(((campaign.value.success_count || 0) / campaign.value.total_recipients) * 100);
    });

    const failedPercent = computed(() => {
      if (!campaign.value || !campaign.value.total_recipients) return 0;
      return Math.round(((campaign.value.failed_count || 0) / campaign.value.total_recipients) * 100);
    });

    const loadCampaign = async () => {
      try {
        const res = await api.get(`/telegram/campaigns/${campaignId}`);
        if (res.success) {
          campaign.value = res.data;
        }
      } catch (err) {
        console.error('Failed to load campaign:', err);
      }
    };

    const loadRecipients = async () => {
      try {
        const url = filterStatus.value === 'all'
          ? `/telegram/campaigns/${campaignId}/recipients`
          : `/telegram/campaigns/${campaignId}/recipients?status=${filterStatus.value}`;

        const res = await api.get(url);
        if (res.success) {
          recipients.value = res.data?.data || res.data || [];
        }
      } catch (err) {
        console.error('Failed to load recipients:', err);
      }
    };

    const canEdit = computed(() => {
      return campaign.value && (campaign.value.status === 'paused' || campaign.value.status === 'draft');
    });

    const startEdit = () => {
      editForm.value = {
        message: campaign.value.message || '',
        button_text: campaign.value.button_text || '',
        button_url: campaign.value.button_url || '',
      };
      isEditing.value = true;
    };

    const cancelEdit = () => {
      isEditing.value = false;
    };

    const saveEdit = async () => {
      try {
        isSaving.value = true;
        const res = await api.put(`/telegram/campaigns/${campaignId}`, {
          message: editForm.value.message,
          button_text: editForm.value.button_text || null,
          button_url: editForm.value.button_url || null,
        });
        if (res.success) {
          isEditing.value = false;
          await loadCampaign();
        } else {
          alert(res.message || 'فشل حفظ التعديلات.');
        }
      } catch (err) {
        alert(err.data?.message || err.message || 'فشل حفظ التعديلات.');
      } finally {
        isSaving.value = false;
      }
    };

    const launchOrResume = async () => {
      try {
        const endpoint = campaign.value.status === 'draft' ? 'launch' : 'resume';
        await api.post(`/telegram/campaigns/${campaignId}/${endpoint}`);
        await loadCampaign();
        await loadRecipients();
      } catch (err) {
        alert(err.data?.message || err.message || 'فشل تشغيل الحملة.');
      }
    };

    const pauseCampaign = async () => {
      try {
        await api.post(`/telegram/campaigns/${campaignId}/pause`);
        await loadCampaign();
      } catch (err) {
        console.error('Failed to pause campaign:', err);
      }
    };

    const resendFailed = async () => {
      try {
        await api.post(`/telegram/campaigns/${campaignId}/resend-failed`);
        await loadCampaign();
        await loadRecipients();
      } catch (err) {
        console.error('Failed to resend failed:', err);
      }
    };

    const statusClass = (status) => {
      switch (status) {
        case 'running': return 'bg-emerald-50 text-emerald-700 border border-emerald-200/60';
        case 'completed': return 'bg-indigo-50 text-indigo-700 border border-indigo-200/60';
        case 'paused': return 'bg-amber-50 text-amber-700 border border-amber-200/60';
        case 'failed': return 'bg-red-50 text-red-700 border border-red-200/60';
        default: return 'bg-gray-100 text-gray-700';
      }
    };

    const statusText = (status) => {
      switch (status) {
        case 'running': return 'جارٍ الإرسال';
        case 'completed': return 'مكتملة';
        case 'paused': return 'متوقفة';
        case 'failed': return 'فشلت';
        case 'draft': return 'مسودة';
        default: return status;
      }
    };

    const recipientStatusClass = (st) => {
      switch (st) {
        case 'sent': return 'bg-emerald-50 text-emerald-600';
        case 'failed': return 'bg-red-50 text-red-600';
        default: return 'bg-amber-50 text-amber-600';
      }
    };

    const recipientStatusText = (st) => {
      switch (st) {
        case 'sent': return 'ناجحة';
        case 'failed': return 'فشلت';
        default: return 'قيد الانتظار';
      }
    };

    const filterText = (st) => {
      switch (st) {
        case 'sent': return 'ناجحة';
        case 'failed': return 'فاشلة';
        case 'pending': return 'معلقة';
        default: return 'الكل';
      }
    };

    const formatDate = (d) => {
      if (!d) return '-';
      return new Date(d).toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric' });
    };

    const formatTime = (d) => {
      if (!d) return '-';
      return new Date(d).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    };

    onMounted(async () => {
      loading.value = true;
      await loadCampaign();
      await loadRecipients();
      loading.value = false;

      // Poll every 5s if running
      pollInterval = setInterval(() => {
        if (campaign.value?.status === 'running') {
          loadCampaign();
          loadRecipients();
        }
      }, 5000);
    });

    onUnmounted(() => {
      if (pollInterval) clearInterval(pollInterval);
    });

    return {
      campaign,
      recipients,
      filterStatus,
      loading,
      isEditing,
      isSaving,
      editForm,
      deliveryRate,
      progressPercent,
      failedPercent,
      canEdit,
      launchOrResume,
      pauseCampaign,
      resendFailed,
      startEdit,
      cancelEdit,
      saveEdit,
      loadRecipients,
      statusClass,
      statusText,
      recipientStatusClass,
      recipientStatusText,
      filterText,
      formatDate,
      formatTime,
    };
  },
};
</script>
