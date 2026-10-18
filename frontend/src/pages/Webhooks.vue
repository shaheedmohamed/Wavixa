<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('webhooks_page.title') }}</h2>
          <p class="text-gray-500 text-sm mt-1">{{ t('webhooks_page.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
          <button
            @click="openCreateModal"
            class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-sm active:scale-95"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>{{ t('webhooks_page.add_webhook') }}</span>
          </button>
        </div>
      </div>

      <!-- Webhooks List Card -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse" :class="isRTL ? 'text-right' : 'text-left'">
            <thead>
              <tr class="bg-gray-50/50 border-b border-gray-50">
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('webhooks_page.th_name') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('webhooks_page.th_url') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('webhooks_page.th_events') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('webhooks_page.th_status') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('webhooks_page.th_logs') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider" :class="isRTL ? 'text-left' : 'text-right'">{{ t('webhooks_page.th_actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-if="loading" class="animate-pulse">
                <td colspan="6" class="px-6 py-12 text-center text-gray-400 font-medium">{{ t('common.loading') }}</td>
              </tr>
              <tr v-else-if="webhooks.length === 0">
                <td colspan="6" class="px-6 py-20 text-center">
                  <div class="flex flex-col items-center">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4 text-gray-200">
                      <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 00 5.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                    </div>
                    <p class="text-gray-900 font-bold">{{ t('webhooks_page.no_webhooks') }}</p>
                    <p class="text-gray-400 text-sm mt-1">{{ t('webhooks_page.no_webhooks_desc') }}</p>
                  </div>
                </td>
              </tr>
              <tr v-else v-for="webhook in webhooks" :key="webhook.id" class="hover:bg-gray-50/50 transition-all group">
                <td class="px-6 py-4">
                  <span class="text-sm font-black text-gray-900">{{ webhook.name }}</span>
                </td>
                <td class="px-6 py-4">
                  <span class="text-xs font-bold text-gray-400 font-mono truncate block max-w-[200px]" dir="ltr" :title="webhook.url">{{ webhook.url }}</span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex flex-wrap gap-1">
                    <span v-if="!webhook.events || webhook.events.length === 0" class="text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded uppercase">{{ t('webhooks_page.all_events') }}</span>
                    <span v-else v-for="event in webhook.events" :key="event" class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded uppercase tracking-tighter">{{ event }}</span>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider', webhook.is_active ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-400']">
                    {{ webhook.is_active ? t('webhooks_page.active') : t('webhooks_page.inactive') }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <span class="text-xs font-black text-gray-900">{{ webhook.logs_count || 0 }} {{ isRTL ? 'طلب' : 'reqs' }}</span>
                </td>
                <td class="px-6 py-4" :class="isRTL ? 'text-left' : 'text-right'">
                  <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity" :class="isRTL ? 'justify-end' : 'justify-end'">
                    <button @click="viewLogs(webhook)" class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all" :title="t('webhooks_page.logs')">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    </button>
                    <button @click="testWebhook(webhook.id)" :disabled="testing === webhook.id" class="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-all disabled:opacity-50" :title="t('webhooks_page.test')">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </button>
                    <button @click="editWebhook(webhook)" class="p-2 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-all" :title="t('webhooks_page.edit')">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    </button>
                    <button @click="deleteWebhook(webhook.id)" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" :title="t('webhooks_page.delete')">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Events Documentation -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
        <h3 class="text-lg font-black text-gray-900 mb-6 flex items-center gap-2">
          <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          {{ t('webhooks_page.available_events') }}
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div v-for="event in events" :key="event" class="p-5 rounded-2xl bg-gray-50 border border-gray-50 hover:border-indigo-100 transition-all group">
            <p class="text-xs font-black text-indigo-600 uppercase tracking-widest mb-2 font-mono group-hover:translate-x-1 transition-transform inline-block" dir="ltr">{{ event }}</p>
            <p class="text-xs text-gray-500 font-medium leading-relaxed">{{ eventDescription(event) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-in fade-in duration-200">
      <div class="bg-white border border-gray-100 rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden" :dir="isRTL ? 'rtl' : 'ltr'">
        <div class="px-8 py-6 border-b border-gray-50 flex items-center justify-between bg-gray-50/30">
          <h3 class="text-lg font-black text-gray-900">{{ editingWebhook ? t('webhooks_page.modal_edit_title') : t('webhooks_page.modal_add_title') }}</h3>
          <button @click="closeModal" class="w-8 h-8 rounded-full flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all font-bold">&times;</button>
        </div>
        <div class="p-8 space-y-6 max-h-[70vh] overflow-y-auto">
          <div class="space-y-2">
            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('webhooks_page.modal_name') }}</label>
            <input v-model="form.name" type="text" placeholder="e.g. My App Server" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none" />
          </div>
          <div class="space-y-2">
            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('webhooks_page.modal_url') }}</label>
            <input v-model="form.url" type="url" placeholder="https://your-api.com/webhook" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold font-mono placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none" dir="ltr" />
          </div>
          <div class="space-y-2">
            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('webhooks_page.modal_secret') }}</label>
            <input v-model="form.secret" type="text" placeholder="HMAC verification secret" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold font-mono placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none" dir="ltr" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ isRTL ? 'محاولات الإرسال' : 'Max Retries' }}</label>
              <input v-model.number="form.max_retries" type="number" min="0" max="10" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold focus:bg-white focus:border-indigo-300 transition-all outline-none" />
            </div>
            <div class="flex items-center gap-3 pt-6">
              <input id="is_active" v-model="form.is_active" type="checkbox" class="w-5 h-5 rounded-lg border-gray-300 text-indigo-600 focus:ring-indigo-500" />
              <label for="is_active" class="text-xs font-black text-gray-700 cursor-pointer">{{ t('webhooks_page.active') }}</label>
            </div>
          </div>
          <div class="space-y-3">
            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('webhooks_page.modal_events') }}</label>
            <div class="space-y-2 p-4 bg-gray-50 rounded-2xl border border-gray-50">
              <label class="flex items-center gap-3 text-xs font-bold text-gray-700 cursor-pointer">
                <input type="checkbox" :checked="allEventsSelected" @change="toggleAllEvents" class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                {{ t('webhooks_page.all_events') }}
              </label>
              <div class="grid grid-cols-1 gap-2 pt-2 border-t border-gray-200">
                <label v-for="event in events" :key="event" class="flex items-center gap-3 text-[11px] font-bold text-gray-500 cursor-pointer hover:text-indigo-600 transition-colors">
                  <input type="checkbox" :value="event" v-model="form.events" class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                  <span class="font-mono" dir="ltr">{{ event }}</span>
                </label>
              </div>
            </div>
          </div>
        </div>
        <div class="px-8 py-6 border-t border-gray-50 flex justify-end gap-3 bg-gray-50/30">
          <button @click="closeModal" class="px-6 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-100 transition-all active:scale-95">{{ t('common.cancel') }}</button>
          <button @click="saveWebhook" :disabled="saving" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-sm active:scale-95 disabled:opacity-50">
            <svg v-if="saving" class="w-4 h-4 animate-spin inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            {{ saving ? t('common.saving') : t('webhooks_page.save_webhook') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Logs Modal -->
    <div v-if="showLogs" class="fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-in fade-in duration-200">
      <div class="bg-white border border-gray-100 rounded-3xl shadow-2xl max-w-4xl w-full overflow-hidden" :dir="isRTL ? 'rtl' : 'ltr'">
        <div class="px-8 py-6 border-b border-gray-50 flex items-center justify-between bg-gray-50/30">
          <h3 class="text-lg font-black text-gray-900">{{ isRTL ? `سجلات الويب هوك: ${selectedWebhook?.name}` : `Webhook Logs: ${selectedWebhook?.name}` }}</h3>
          <button @click="showLogs = false" class="w-8 h-8 rounded-full flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all font-bold">&times;</button>
        </div>
        <div class="p-8 max-h-[70vh] overflow-y-auto">
          <div class="overflow-x-auto">
            <table class="w-full border-collapse" :class="isRTL ? 'text-right' : 'text-left'">
              <thead>
                <tr class="bg-gray-50/50 border-b border-gray-50">
                  <th class="px-4 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ isRTL ? 'الحدث' : 'Event' }}</th>
                  <th class="px-4 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('common.status') }}</th>
                  <th class="px-4 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider">HTTP</th>
                  <th class="px-4 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ isRTL ? 'المحاولات' : 'Attempts' }}</th>
                  <th class="px-4 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ isRTL ? 'الوقت' : 'Time' }}</th>
                  <th class="px-4 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider" :class="isRTL ? 'text-left' : 'text-right'">{{ t('common.actions') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <tr v-if="logsLoading" class="animate-pulse">
                  <td colspan="6" class="px-4 py-8 text-center text-gray-400 font-medium">{{ t('common.loading') }}</td>
                </tr>
                <tr v-else-if="logs.length === 0">
                  <td colspan="6" class="px-4 py-12 text-center text-gray-400 font-bold">{{ isRTL ? 'لا يوجد سجلات بعد' : 'No logs recorded yet' }}</td>
                </tr>
                <tr v-else v-for="log in logs" :key="log.id" class="hover:bg-gray-50/50 transition-colors">
                  <td class="px-4 py-3 text-xs font-black text-indigo-600 font-mono" dir="ltr">{{ log.event }}</td>
                  <td class="px-4 py-3">
                    <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', logStatusColorClass(log.status)]">{{ log.status }}</span>
                  </td>
                  <td class="px-4 py-3 text-xs font-black text-gray-700 font-mono">{{ log.http_status || 'N/A' }}</td>
                  <td class="px-4 py-3 text-xs font-bold text-gray-500">{{ log.attempts }}</td>
                  <td class="px-4 py-3 text-[10px] font-bold text-gray-400">{{ formatDateShort(log.created_at) }}</td>
                  <td class="px-4 py-3" :class="isRTL ? 'text-left' : 'text-right'">
                    <button @click="resendLog(log.id)" :disabled="resendingLog === log.id" class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all disabled:opacity-50" title="Resend">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, onMounted, computed, reactive } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import auth from '@/services/auth';
import { t, isRTL } from '@/i18n';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

export default {
  name: 'Webhooks',
  components: { DashboardLayout },
  setup() {
    const router = useRouter();
    const webhooks = ref([]);
    const events = ref([]);
    const loading = ref(false);
    const saving = ref(false);
    const testing = ref(null);
    const resendingLog = ref(null);
    const showModal = ref(false);
    const showLogs = ref(false);
    const editingWebhook = ref(null);
    const selectedWebhook = ref(null);
    const logs = ref([]);
    const logsLoading = ref(false);

    const form = reactive({
      name: '',
      url: '',
      secret: '',
      max_retries: 3,
      events: [],
      is_active: true,
    });

    onMounted(async () => {
      const userData = auth.getUser();
      if (!userData) {
        router.push('/login');
        return;
      }
      await loadWebhooks();
    });

    const loadWebhooks = async () => {
      loading.value = true;
      try {
        const response = await api.get('/webhooks');
        if (response.success) {
          webhooks.value = response.data || [];
          events.value = response.events || [];
        }
      } catch (err) {
        console.error('Error loading webhooks:', err);
      } finally {
        loading.value = false;
      }
    };

    const openCreateModal = () => {
      editingWebhook.value = null;
      form.name = '';
      form.url = '';
      form.secret = '';
      form.max_retries = 3;
      form.events = [];
      form.is_active = true;
      showModal.value = true;
    };

    const editWebhook = (webhook) => {
      editingWebhook.value = webhook;
      form.name = webhook.name;
      form.url = webhook.url;
      form.secret = webhook.secret || '';
      form.max_retries = webhook.max_retries;
      form.events = webhook.events || [];
      form.is_active = !!webhook.is_active;
      showModal.value = true;
    };

    const closeModal = () => {
      showModal.value = false;
      editingWebhook.value = null;
    };

    const saveWebhook = async () => {
      if (!form.name || !form.url) {
        alert(isRTL.value ? 'يرجى إدخال الاسم والرابط' : 'Please provide name and URL');
        return;
      }
      saving.value = true;
      try {
        const payload = { ...form };
        if (payload.events.length === 0) payload.events = null;

        let response;
        if (editingWebhook.value) {
          response = await api.put(`/webhooks/${editingWebhook.value.id}`, payload);
        } else {
          response = await api.post('/webhooks', payload);
        }

        if (response.success) {
          closeModal();
          await loadWebhooks();
        } else {
          alert(response.message || (isRTL.value ? 'فشل في حفظ الويب هوك' : 'Failed to save webhook'));
        }
      } catch (err) {
        console.error('Error saving webhook:', err);
        alert(isRTL.value ? 'فشل في حفظ الويب هوك' : 'Failed to save webhook');
      } finally {
        saving.value = false;
      }
    };

    const deleteWebhook = async (id) => {
      if (!confirm(isRTL.value ? 'هل أنت متأكد من رغبتك في حذف هذا الويب هوك؟' : 'Are you sure you want to delete this webhook?')) return;
      try {
        const response = await api.delete(`/webhooks/${id}`);
        if (response.success) {
          await loadWebhooks();
        } else {
          alert(response.message || (isRTL.value ? 'فشل في حذف الويب هوك' : 'Failed to delete webhook'));
        }
      } catch (err) {
        console.error('Error deleting webhook:', err);
        alert(isRTL.value ? 'فشل في حذف الويب هوك' : 'Failed to delete webhook');
      }
    };

    const testWebhook = async (id) => {
      testing.value = id;
      try {
        const response = await api.post(`/webhooks/${id}/test`, {});
        alert(response.message || (isRTL.value ? 'تم إرسال طلب الاختبار بنجاح' : 'Test event dispatched successfully'));
      } catch (err) {
        console.error('Error testing webhook:', err);
        alert(isRTL.value ? 'فشل في اختبار الويب هوك' : 'Failed to test webhook');
      } finally {
        testing.value = null;
      }
    };

    const viewLogs = async (webhook) => {
      selectedWebhook.value = webhook;
      showLogs.value = true;
      logsLoading.value = true;
      try {
        const response = await api.get(`/webhooks/${webhook.id}/logs`);
        if (response.success) {
          logs.value = response.data.data || [];
        }
      } catch (err) {
        console.error('Error loading logs:', err);
      } finally {
        logsLoading.value = false;
      }
    };

    const resendLog = async (id) => {
      resendingLog.value = id;
      try {
        const response = await api.post(`/webhook-logs/${id}/resend`, {});
        alert(response.message || (isRTL.value ? 'تمت إعادة الإرسال بنجاح' : 'Event resent successfully'));
      } catch (err) {
        console.error('Error resending webhook:', err);
        alert(isRTL.value ? 'فشل في إعادة الإرسال' : 'Failed to resend');
      } finally {
        resendingLog.value = null;
      }
    };

    const allEventsSelected = computed(() => {
      return form.events.length === events.value.length;
    });

    const toggleAllEvents = () => {
      if (form.events.length === events.value.length) {
        form.events = [];
      } else {
        form.events = [...events.value];
      }
    };

    const eventDescription = (event) => {
      const descriptions = isRTL.value ? {
        'message.sent': 'عند إرسال رسالة بنجاح',
        'message.failed': 'عند فشل إرسال رسالة',
        'message.received': 'عند استلام رسالة جديدة',
        'device.connected': 'عند اتصال جهاز بالواتساب',
        'device.disconnected': 'عند انقطاع اتصال جهاز',
        'session.expired': 'عند انتهاء صلاحية الجلسة',
      } : {
        'message.sent': 'Triggered when a message is dispatched successfully',
        'message.failed': 'Triggered when a message delivery fails',
        'message.received': 'Triggered when an incoming message is received',
        'device.connected': 'Triggered when a WhatsApp device pairs successfully',
        'device.disconnected': 'Triggered when a device connection drops',
        'session.expired': 'Triggered when the device session expires',
      };
      return descriptions[event] || event;
    };

    const logStatusColorClass = (status) => {
      const colors = {
        success: 'bg-green-100 text-green-700',
        failed: 'bg-red-100 text-red-700',
        pending: 'bg-amber-100 text-amber-700',
      };
      return colors[status] || 'bg-gray-100 text-gray-700';
    };

    const formatDateShort = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleString(isRTL.value ? 'ar-EG' : 'en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    };

    return {
      webhooks,
      events,
      loading,
      saving,
      testing,
      resendingLog,
      showModal,
      showLogs,
      editingWebhook,
      selectedWebhook,
      logs,
      logsLoading,
      form,
      allEventsSelected,
      loadWebhooks,
      openCreateModal,
      editWebhook,
      closeModal,
      saveWebhook,
      deleteWebhook,
      testWebhook,
      viewLogs,
      resendLog,
      toggleAllEvents,
      eventDescription,
      logStatusColorClass,
      formatDateShort,
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
