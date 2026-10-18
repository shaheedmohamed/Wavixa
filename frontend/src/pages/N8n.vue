<template>
  <DashboardLayout>
    <div v-if="isPro" class="space-y-6 animate-in fade-in duration-500">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('n8n_page.title') }}</h2>
          <p class="text-gray-500 text-sm mt-1">{{ t('n8n_page.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
          <router-link
            to="/n8n-docs"
            class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-50 transition-all shadow-sm active:scale-95"
          >
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
            <span>{{ t('n8n_page.docs') }}</span>
          </router-link>
          <button
            @click="openCreateModal"
            class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-sm active:scale-95"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>{{ t('n8n_page.add_connection') }}</span>
          </button>
        </div>
      </div>

      <!-- Status Card -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <div class="flex items-center gap-4">
          <div
            class="w-12 h-12 rounded-xl flex items-center justify-center"
            :class="{
              'bg-green-50 text-green-600': status === 'connected',
              'bg-gray-50 text-gray-400': status === 'not_connected',
              'bg-red-50 text-red-600': status === 'error'
            }"
          >
            <svg v-if="status === 'connected'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <svg v-else-if="status === 'error'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
          </div>
          <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ t('n8n_page.status_card_title') }}</p>
            <p class="text-lg font-black" :class="{
              'text-green-600': status === 'connected',
              'text-gray-600': status === 'not_connected',
              'text-red-600': status === 'error'
            }">{{ statusLabel }}</p>
            <p v-if="lastError" class="text-xs text-red-500 font-medium mt-1">{{ lastError }}</p>
          </div>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
          </div>
          <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ t('n8n_page.connections_count') }}</p>
            <p class="text-2xl font-black text-gray-900 leading-none">{{ stats.connections_count || 0 }}</p>
          </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
          </div>
          <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ t('n8n_page.workflows_count') }}</p>
            <p class="text-2xl font-black text-blue-600 leading-none">{{ stats.workflows_count || 0 }}</p>
          </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
          </div>
          <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ t('n8n_page.requests_count') }}</p>
            <p class="text-2xl font-black text-orange-600 leading-none">{{ stats.requests_count || 0 }}</p>
          </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
          </div>
          <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ t('n8n_page.messages_count') }}</p>
            <p class="text-2xl font-black text-green-600 leading-none">{{ stats.messages_count || 0 }}</p>
          </div>
        </div>
      </div>

      <!-- Connections Table -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse" :class="isRTL ? 'text-right' : 'text-left'">
            <thead>
              <tr class="bg-gray-50/50 border-b border-gray-50">
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('n8n_page.th_name') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">n8n URL</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">API Key</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('n8n_page.th_status') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ isRTL ? 'آخر اختبار' : 'Last Tested' }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Workflows</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ isRTL ? 'آخر خطأ' : 'Last Error' }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider" :class="isRTL ? 'text-left' : 'text-right'">{{ t('n8n_page.th_actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-if="loading" class="animate-pulse">
                <td colspan="8" class="px-6 py-12 text-center text-gray-400 font-medium">{{ t('common.loading') }}</td>
              </tr>
              <tr v-else-if="connections.length === 0">
                <td colspan="8" class="px-6 py-20 text-center">
                  <div class="flex flex-col items-center">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4 text-gray-200">
                      <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    </div>
                    <p class="text-gray-900 font-bold">{{ t('n8n_page.no_connections') }}</p>
                    <p class="text-gray-400 text-sm mt-1">{{ t('n8n_page.no_connections_desc') }}</p>
                  </div>
                </td>
              </tr>
              <tr v-else v-for="connection in connections" :key="connection.id" class="hover:bg-gray-50/50 transition-all group">
                <td class="px-6 py-4">
                  <span class="text-sm font-black text-gray-900">{{ connection.name }}</span>
                </td>
                <td class="px-6 py-4">
                  <span class="text-xs font-bold text-gray-500 font-mono truncate block max-w-[160px]" dir="ltr" :title="connection.url">{{ connection.url }}</span>
                </td>
                <td class="px-6 py-4">
                  <span class="text-xs font-bold text-gray-500 font-mono bg-gray-100 px-2 py-0.5 rounded-md" dir="ltr">{{ connection.prefix }}...</span>
                </td>
                <td class="px-6 py-4">
                  <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider', statusColor(connection.status)]">
                    {{ statusText(connection.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 text-xs font-bold text-gray-400">{{ connection.last_tested_at ? formatDate(connection.last_tested_at) : (isRTL ? 'لم يختبر بعد' : 'Not tested') }}</td>
                <td class="px-6 py-4 text-sm font-black text-gray-900">{{ connection.workflows_count }}</td>
                <td class="px-6 py-4">
                  <span v-if="connection.last_error" class="text-xs font-bold text-red-500 truncate block max-w-[120px]" :title="connection.last_error">{{ connection.last_error }}</span>
                  <span v-else class="text-xs font-bold text-gray-400">-</span>
                </td>
                <td class="px-6 py-4" :class="isRTL ? 'text-left' : 'text-right'">
                  <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity" :class="isRTL ? 'justify-end' : 'justify-end'">
                    <button @click="testConnection(connection.id)" :disabled="testing === connection.id" class="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-all disabled:opacity-50" title="Test Connection">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </button>
                    <button @click="editConnection(connection)" class="p-2 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-all" :title="t('common.edit')">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    </button>
                    <button @click="toggleConnection(connection)" class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all" :title="connection.is_active ? 'Disable' : 'Enable'">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="connection.is_active ? 'M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z' : 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z'" /></svg>
                    </button>
                    <button @click="deleteConnection(connection.id)" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" :title="t('common.delete')">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Templates Section -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
        <h3 class="text-lg font-black text-gray-900 mb-6 flex items-center gap-2">
          <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" /></svg>
          {{ isRTL ? 'قوالب الأتمتة' : 'Automation Templates' }}
        </h3>
        <div v-if="templates.length === 0" class="text-center text-gray-400 py-8">
          <p class="font-bold">{{ isRTL ? 'لا توجد قوالب حالياً' : 'No templates currently available' }}</p>
          <p class="text-sm mt-1">{{ isRTL ? 'يمكنك إنشاء Workflows خاصة بك في n8n' : 'You can build custom workflows inside n8n directly' }}</p>
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div v-for="template in templates" :key="template.id" class="p-5 rounded-2xl bg-gray-50 border border-gray-50 hover:border-indigo-100 transition-all group">
            <p class="text-sm font-black text-gray-900 mb-2">{{ template.name }}</p>
            <p class="text-xs text-gray-500 font-medium leading-relaxed mb-4">{{ template.description }}</p>
            <button
              v-if="template.template_json"
              @click="downloadTemplate(template)"
              class="text-xs font-bold text-indigo-600 hover:text-indigo-700"
            >
              {{ isRTL ? 'تحميل قالب JSON' : 'Download JSON Template' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="!isPro" class="fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center z-50 p-4" :dir="isRTL ? 'rtl' : 'ltr'">
      <div class="bg-white border border-gray-100 rounded-3xl shadow-2xl max-w-md w-full p-8 text-center">
        <div class="w-16 h-16 bg-yellow-50 text-yellow-600 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
        </div>
        <h3 class="text-2xl font-black text-gray-900 mb-2">{{ t('pro_modal.title') }}</h3>
        <p class="text-sm text-gray-500 mb-6">{{ t('pro_modal.description') }}</p>
        <div class="flex justify-center gap-3">
          <router-link to="/subscription" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors">{{ t('common.upgrade_now') }}</router-link>
          <button @click="router.push('/dashboard')" class="px-6 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-100 transition-colors">{{ t('common.close') }}</button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="isPro && showModal" class="fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-in fade-in duration-200">
      <div class="bg-white border border-gray-100 rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden" :dir="isRTL ? 'rtl' : 'ltr'">
        <div class="px-8 py-6 border-b border-gray-50 flex items-center justify-between bg-gray-50/30">
          <h3 class="text-lg font-black text-gray-900">{{ editingConnection ? (isRTL ? 'تعديل اتصال n8n' : 'Edit n8n Connection') : t('n8n_page.add_connection') }}</h3>
          <button @click="closeModal" class="w-8 h-8 rounded-full flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all font-bold">&times;</button>
        </div>
        <form @submit.prevent="saveConnection" class="p-8 space-y-6">
          <div class="space-y-2">
            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('contacts_page.th_name') }}</label>
            <input v-model="form.name" type="text" placeholder="e.g. Production n8n instance" required class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none" />
          </div>
          <div class="space-y-2">
            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">n8n URL</label>
            <input v-model="form.url" type="url" placeholder="https://n8n.example.com" required class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold font-mono placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none" dir="ltr" />
          </div>
          <div class="space-y-2">
            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">API Key</label>
            <input v-model="form.api_key" type="text" placeholder="n8n API Key" required class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold font-mono placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none" dir="ltr" />
          </div>
          <div class="px-8 py-6 border-t border-gray-50 flex justify-end gap-3 bg-gray-50/30">
            <button type="button" @click="closeModal" class="px-6 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-100 transition-all active:scale-95">{{ t('common.cancel') }}</button>
            <button type="submit" :disabled="saving" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-sm active:scale-95 disabled:opacity-50">
              {{ saving ? t('common.saving') : (editingConnection ? (isRTL ? 'تحديث' : 'Update') : t('common.save')) }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import auth from '@/services/auth';
import { t, isRTL } from '@/i18n';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

const connections = ref([]);
const templates = ref([]);
const stats = ref({});
const status = ref('not_connected');
const lastError = ref(null);
const loading = ref(true);
const saving = ref(false);
const testing = ref(null);
const showModal = ref(false);
const editingConnection = ref(null);

const user = ref(auth.getUser());
const subscription = ref(null);
const router = useRouter();

const form = ref({
  name: '',
  url: '',
  api_key: '',
});

const isPro = computed(() => {
  const plan = subscription.value?.plan?.name?.toLowerCase() || user.value?.subscription_plan?.toLowerCase();
  return plan === 'pro';
});

const statusLabel = computed(() => {
  const labels = isRTL.value ? {
    connected: 'متصل',
    not_connected: 'غير متصل',
    error: 'يوجد خطأ في الاتصال',
  } : {
    connected: 'Connected',
    not_connected: 'Not Connected',
    error: 'Connection Error',
  };
  return labels[status.value] || status.value;
});

const statusText = (s) => {
  const labels = isRTL.value ? {
    connected: 'متصل',
    not_connected: 'غير متصل',
    error: 'يوجد خطأ',
  } : {
    connected: 'Connected',
    not_connected: 'Disconnected',
    error: 'Error',
  };
  return labels[s] || s;
};

const statusColor = (s) => ({
  connected: 'bg-green-50 text-green-600',
  not_connected: 'bg-gray-100 text-gray-400',
  error: 'bg-red-50 text-red-600',
}[s] || 'bg-gray-100 text-gray-400');

const formatDate = (date) => {
  return new Date(date).toLocaleString(isRTL.value ? 'ar-EG' : 'en-US');
};

const loadData = async () => {
  loading.value = true;
  try {
    const [conns, statusRes, statsRes, templatesRes] = await Promise.all([
      api.get('/n8n'),
      api.get('/n8n/status'),
      api.get('/n8n/stats'),
      api.get('/n8n/templates'),
    ]);

    connections.value = conns.data?.data?.data || [];
    status.value = statusRes.data?.data?.status || 'not_connected';
    lastError.value = statusRes.data?.data?.last_error || null;
    stats.value = statsRes.data?.data || {};
    templates.value = templatesRes.data?.data || [];
  } catch (e) {
    console.error('Error loading n8n data:', e);
  } finally {
    loading.value = false;
  }
};

const openCreateModal = () => {
  editingConnection.value = null;
  form.value = { name: '', url: '', api_key: '' };
  showModal.value = true;
};

const editConnection = (connection) => {
  editingConnection.value = connection;
  form.value = {
    name: connection.name,
    url: connection.url,
    api_key: '',
  };
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
};

const saveConnection = async () => {
  saving.value = true;
  try {
    if (editingConnection.value) {
      await api.put(`/n8n/${editingConnection.value.id}`, form.value);
    } else {
      await api.post('/n8n', form.value);
    }
    await loadData();
    closeModal();
  } catch (e) {
    alert(e.data?.message || (isRTL.value ? 'فشل الحفظ' : 'Failed to save'));
  } finally {
    saving.value = false;
  }
};

const testConnection = async (id) => {
  testing.value = id;
  try {
    const res = await api.post(`/n8n/${id}/test`);
    alert(res.data?.message || (isRTL.value ? 'تم اختبار الاتصال بنجاح' : 'Connection tested successfully'));
    await loadData();
  } catch (e) {
    alert(e.data?.message || (isRTL.value ? 'فشل الاختبار' : 'Test failed'));
  } finally {
    testing.value = null;
  }
};

const toggleConnection = async (conn) => {
  try {
    await api.put(`/n8n/${conn.id}`, { is_active: !conn.is_active });
    await loadData();
  } catch (e) {
    alert(e.data?.message || (isRTL.value ? 'فشل التحديث' : 'Failed to update'));
  }
};

const deleteConnection = async (id) => {
  if (!confirm(isRTL.value ? 'هل أنت متأكد من حذف هذا الاتصال؟' : 'Are you sure you want to delete this connection?')) return;
  try {
    await api.delete(`/n8n/${id}`);
    await loadData();
  } catch (e) {
    alert(e.data?.message || (isRTL.value ? 'فشل الحذف' : 'Failed to delete'));
  }
};

const downloadTemplate = (t) => {
  const blob = new Blob([JSON.stringify(t.template_json, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${t.name.toLowerCase().replace(/\s+/g, '-')}-template.json`;
  a.click();
  URL.revokeObjectURL(url);
};

onMounted(async () => {
  const [subRes] = await Promise.all([
    api.get('/subscription'),
    loadData(),
  ]);
  if (subRes.success) {
    subscription.value = subRes.data;
  }
});
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
