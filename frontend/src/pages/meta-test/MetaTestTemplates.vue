<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('meta.templates.title') }}</h2>
      <div class="flex items-center gap-2">
        <button
          @click="showForm = !showForm"
          class="px-3 py-2 bg-green-600 text-white text-sm font-bold rounded-xl hover:bg-green-700"
        >
          {{ showForm ? t('meta.templates.cancel') : t('meta.templates.create') }}
        </button>
        <button
          @click="sync"
          :disabled="syncing"
          class="px-3 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 disabled:opacity-50"
        >
          {{ syncing ? t('meta.common.syncing') : t('meta.common.sync') }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-gray-500">{{ t('meta.common.loading') }}</div>
    <div v-else-if="error" class="text-red-600">{{ error }}</div>

    <div v-if="showForm" class="border border-gray-100 rounded-xl p-4 mb-6 bg-gray-50">
      <h3 class="text-lg font-medium text-gray-900 mb-3">{{ t('meta.templates.new_title') }}</h3>
      <div class="space-y-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('meta.templates.waba') }}</label>
          <select v-model="form.waba_id" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm">
            <option v-for="w in wabas" :key="w.id" :value="w.id">{{ w.name || w.meta_waba_id }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('meta.templates.name_label') }}</label>
          <input v-model="form.name" type="text" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm" placeholder="welcome_message" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('meta.templates.category') }}</label>
            <select v-model="form.category" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm">
              <option value="UTILITY">UTILITY</option>
              <option value="MARKETING">MARKETING</option>
              <option value="AUTHENTICATION">AUTHENTICATION</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('meta.templates.language') }}</label>
            <input v-model="form.language" type="text" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm" placeholder="ar or en_US" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('meta.templates.header') }}</label>
          <input v-model="form.header" type="text" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('meta.templates.body') }}</label>
          <textarea v-model="form.body" rows="3" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm" :placeholder="t('meta.templates.body_ph')"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('meta.templates.footer') }}</label>
          <input v-model="form.footer" type="text" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm" />
        </div>
        <div v-if="createError" class="text-red-600 text-sm">{{ createError }}</div>
        <button
          @click="create"
          :disabled="creating"
          class="px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 disabled:opacity-50"
        >
          {{ creating ? t('meta.templates.creating') : t('meta.templates.create_btn') }}
        </button>
      </div>
    </div>

    <div v-if="templates.length" class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead>
          <tr>
            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.templates.th_name') }}</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.templates.category') }}</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.templates.language') }}</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.templates.th_status') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="template in templates" :key="template.id">
            <td class="px-3 py-2">{{ template.name }}</td>
            <td class="px-3 py-2">{{ template.category }}</td>
            <td class="px-3 py-2">{{ template.language }}</td>
            <td class="px-3 py-2">
              <span :class="statusClass(template.status)">{{ template.status }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else class="text-gray-500">{{ t('meta.templates.empty') }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import metaTest from '@/services/metaTest';
import { t } from '@/i18n';

const loading = ref(true);
const syncing = ref(false);
const creating = ref(false);
const error = ref(null);
const createError = ref(null);
const showForm = ref(false);
const templates = ref([]);
const wabas = ref([]);

const form = ref({
  waba_id: '',
  name: '',
  category: 'UTILITY',
  language: 'ar',
  header: '',
  body: '',
  footer: '',
});

const statusClass = (status) => {
  if (status === 'APPROVED') return 'text-green-600';
  if (status === 'REJECTED') return 'text-red-600';
  return 'text-yellow-600';
};

async function load() {
  try {
    const [tRes, aRes] = await Promise.all([metaTest.templates(), metaTest.accounts()]);
    templates.value = tRes.data?.data || tRes.data;
    wabas.value = aRes.data;
    if (wabas.value.length === 1) {
      form.value.waba_id = wabas.value[0].id;
    }
  } catch (e) {
    error.value = e.data?.message || e.message;
  } finally {
    loading.value = false;
  }
}

async function sync() {
  syncing.value = true;
  error.value = null;
  try {
    const res = await metaTest.syncTemplates();
    templates.value = res.data;
  } catch (e) {
    error.value = e.data?.message || e.message;
  } finally {
    syncing.value = false;
  }
}

async function create() {
  createError.value = null;
  creating.value = true;

  const components = [];
  if (form.value.header.trim()) {
    components.push({ type: 'HEADER', format: 'TEXT', text: form.value.header.trim() });
  }
  components.push({ type: 'BODY', text: form.value.body.trim() });
  if (form.value.footer.trim()) {
    components.push({ type: 'FOOTER', text: form.value.footer.trim() });
  }

  if (!form.value.waba_id) {
    createError.value = t('meta.templates.err_waba');
    creating.value = false;
    return;
  }
  if (!form.value.name.trim()) {
    createError.value = t('meta.templates.err_name');
    creating.value = false;
    return;
  }
  if (!form.value.body.trim()) {
    createError.value = t('meta.templates.err_body');
    creating.value = false;
    return;
  }

  try {
    const res = await metaTest.createTemplate({
      waba_id: form.value.waba_id,
      name: form.value.name.trim(),
      category: form.value.category,
      language: form.value.language.trim(),
      components,
    });

    templates.value.unshift(res.data?.data || res.data);
    showForm.value = false;
    form.value = {
      waba_id: wabas.value.length === 1 ? wabas.value[0].id : '',
      name: '',
      category: 'UTILITY',
      language: 'ar',
      header: '',
      body: '',
      footer: '',
    };
  } catch (e) {
    createError.value = e.data?.message || e.message;
  } finally {
    creating.value = false;
  }
}

onMounted(load);
</script>
