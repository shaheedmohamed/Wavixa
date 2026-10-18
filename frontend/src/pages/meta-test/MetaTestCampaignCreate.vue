<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('meta.campaign_create.title') }}</h2>
      <router-link to="/meta/campaigns" class="text-sm text-indigo-600 hover:underline">{{ t('meta.campaign_create.back') }}</router-link>
    </div>

    <div v-if="loading" class="text-gray-500">{{ t('meta.common.loading') }}</div>
    <div v-else class="space-y-4 max-w-2xl">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('meta.campaign_create.name') }}</label>
        <input v-model="form.name" type="text" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm" :placeholder="t('meta.campaign_create.name_ph')" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('meta.campaign_create.from') }}</label>
        <select v-model="form.phone_number_id" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm">
          <option value="" disabled>{{ t('meta.campaign_create.from_ph') }}</option>
          <option v-for="n in numbers" :key="n.id" :value="n.id">
            {{ n.display_name || n.phone_number }} ({{ n.phone_number }})
          </option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('meta.campaign_create.type') }}</label>
        <select v-model="form.message_type" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm">
          <option value="auto">{{ t('meta.campaign_create.type_auto') }}</option>
          <option value="marketing">{{ t('meta.campaign_create.type_marketing') }}</option>
          <option value="utility">{{ t('meta.campaign_create.type_utility') }}</option>
          <option value="authentication">{{ t('meta.campaign_create.type_auth') }}</option>
          <option value="text">{{ t('meta.campaign_create.type_text') }}</option>
        </select>
        <p v-if="form.message_type === 'auto'" class="text-xs text-emerald-600 mt-1">
          {{ t('meta.campaign_create.auto_hint') }}
        </p>
      </div>

      <div v-if="form.message_type !== 'text'">
        <label class="block text-sm font-medium text-gray-700 mb-1">
          {{ t('meta.campaign_create.template_label') }}{{ form.message_type === 'auto' ? t('meta.campaign_create.template_fallback') : '' }}
        </label>
        <select v-model="form.template_id" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm">
          <option value="" disabled>{{ t('meta.campaign_create.template_ph') }}</option>
          <option v-for="t in approvedTemplates" :key="t.id" :value="t.id">
            {{ t.name }} — {{ t.language }} ({{ t.category }})
          </option>
        </select>
        <p v-if="!approvedTemplates.length" class="text-xs text-yellow-600 mt-1">
          {{ t('meta.campaign_create.no_templates') }}
        </p>
      </div>

      <div v-if="form.message_type === 'text' || form.message_type === 'auto'">
        <label class="block text-sm font-medium text-gray-700 mb-1">
          {{ t('meta.campaign_create.body_label') }}{{ form.message_type === 'auto' ? t('meta.campaign_create.body_auto') : '' }}
        </label>
        <textarea v-model="form.text" rows="4" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm"
          :placeholder="t('meta.campaign_create.body_ph')"></textarea>
        <p class="text-xs text-gray-500 mt-1">{{ t('meta.campaign_create.body_hint') }}</p>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('meta.campaign_create.delay') }}</label>
          <input v-model.number="form.delay_seconds" type="number" min="1" max="120" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm" />
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('meta.campaign_create.recipients') }}</label>

        <div class="border-2 border-dashed border-gray-300 rounded p-4 mb-3 text-center"
          :class="{ 'border-indigo-400 bg-indigo-50': dragging }"
          @dragover.prevent="dragging = true"
          @dragleave.prevent="dragging = false"
          @drop.prevent="onDrop">
          <input ref="fileInput" type="file" accept=".csv,.txt,.tsv,.xlsx,.xls,.vcf" class="hidden" @change="onFilePick" />
          <p class="text-sm text-gray-600">
            {{ t('meta.campaign_create.upload_drop') }}
            <button type="button" @click="$refs.fileInput.click()" class="text-indigo-600 font-medium hover:underline">{{ t('meta.campaign_create.browse') }}</button>
          </p>
          <p class="text-xs text-gray-400 mt-1">{{ t('meta.campaign_create.upload_hint') }}</p>
          <p v-if="uploading" class="text-xs text-indigo-600 mt-2">{{ t('meta.campaign_create.uploading') }}</p>
        </div>

        <div v-if="fileSummary" class="bg-gray-50 rounded-xl p-3 mb-3 text-sm">
          <p class="font-medium">{{ fileSummary.name }}</p>
          <p class="text-xs mt-1">
            <span class="text-green-600">{{ fileSummary.valid }} {{ t('meta.campaign_create.file_valid') }}</span> ·
            <span class="text-yellow-600">{{ fileSummary.duplicates }} {{ t('meta.campaign_create.file_dupes') }}</span> ·
            <span class="text-red-600">{{ fileSummary.invalid }} {{ t('meta.campaign_create.file_invalid') }}</span>
          </p>
        </div>

        <label class="block text-sm font-medium text-gray-700 mb-1">
          {{ t('meta.campaign_create.or_paste') }} <code class="text-xs bg-gray-100 px-1 rounded">phone,name,var1,var2</code>
        </label>
        <textarea v-model="recipientsText" rows="6" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm font-mono"
          placeholder="+201012345678,Ahmed,ORDER123&#10;+966512345678,Sara"></textarea>
        <p class="text-xs text-gray-500 mt-1">{{ t('meta.campaign_create.paste_hint') }}</p>
      </div>

      <div v-if="allRecipients.length" class="bg-gray-50 rounded-xl p-3 text-sm">
        <p><span class="font-semibold">{{ allRecipients.length }}</span> {{ t('meta.campaign_create.total_recipients') }} ({{ fileRecipients.length }} {{ t('meta.campaign_create.from_file') }} + {{ parsed.length }} {{ t('meta.campaign_create.pasted') }})</p>
      </div>

      <label class="flex items-start gap-2 text-sm">
        <input v-model="form.opt_in_confirmed" type="checkbox" class="mt-1" />
        <span>{{ t('meta.campaign_create.opt_in') }}</span>
      </label>

      <div v-if="error" class="text-red-600 text-sm">{{ error }}</div>

      <div class="flex gap-2">
        <button
          @click="create(false)"
          :disabled="creating"
          class="px-4 py-2 bg-gray-700 text-white text-sm font-bold rounded-xl hover:bg-gray-800 disabled:opacity-50"
        >
          Save as Draft
        </button>
        <button
          @click="create(true)"
          :disabled="creating || !form.opt_in_confirmed"
          class="px-4 py-2 bg-green-600 text-white text-sm font-bold rounded-xl hover:bg-green-700 disabled:opacity-50"
        >
          {{ creating ? t('meta.campaign_create.creating') : t('meta.campaign_create.create_launch') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import metaTest from '@/services/metaTest';
import { t } from '@/i18n';

const router = useRouter();
const loading = ref(true);
const creating = ref(false);
const error = ref(null);
const numbers = ref([]);
const templates = ref([]);
const recipientsText = ref('');
const fileRecipients = ref([]);
const fileSummary = ref(null);
const uploading = ref(false);
const dragging = ref(false);
const fileInput = ref(null);

const form = ref({
  name: '',
  phone_number_id: '',
  message_type: 'auto',
  template_id: '',
  text: '',
  delay_seconds: 2,
  opt_in_confirmed: false,
});

const approvedTemplates = computed(() =>
  templates.value.filter((t) => t.status === 'APPROVED')
);

const parsed = computed(() => {
  const seen = new Set();
  const out = [];
  for (const line of recipientsText.value.split('\n')) {
    const parts = line.split(',').map((p) => p.trim()).filter((p, i) => i === 0 || p !== '');
    const phone = (parts[0] || '').replace(/[^\d+]/g, '').replace(/^\+/, '');
    if (phone.length < 7 || seen.has(phone)) continue;
    seen.add(phone);
    out.push({
      phone,
      name: parts[1] || null,
      variables: parts.slice(2).length ? parts.slice(2) : undefined,
    });
  }
  return out;
});

// Merge pasted + file recipients, deduped by phone.
const allRecipients = computed(() => {
  const seen = new Set();
  const out = [];
  for (const r of [...fileRecipients.value, ...parsed.value]) {
    if (seen.has(r.phone)) continue;
    seen.add(r.phone);
    out.push(r);
  }
  return out;
});

async function handleFile(file) {
  if (!file) return;
  error.value = null;
  uploading.value = true;
  try {
    const fd = new FormData();
    fd.append('file', file);
    const res = await metaTest.validateCampaignRecipients(fd);
    const d = res.data || {};
    fileRecipients.value = (d.valid || []).map((r) => ({ phone: r.phone, name: r.name || null }));
    fileSummary.value = {
      name: file.name,
      valid: d.valid_count ?? fileRecipients.value.length,
      invalid: d.invalid_count ?? 0,
      duplicates: d.duplicate_count ?? 0,
    };
  } catch (e) {
    error.value = e.data?.message || e.message;
  } finally {
    uploading.value = false;
    if (fileInput.value) fileInput.value.value = '';
  }
}

function onFilePick(e) {
  handleFile(e.target.files?.[0]);
}

function onDrop(e) {
  dragging.value = false;
  handleFile(e.dataTransfer?.files?.[0]);
}

async function load() {
  try {
    const [nRes, tRes] = await Promise.all([metaTest.phoneNumbers(), metaTest.templates()]);
    numbers.value = (nRes.data || []).filter((n) => n.is_active);
    templates.value = tRes.data?.data || tRes.data || [];
    if (numbers.value.length === 1) form.value.phone_number_id = numbers.value[0].id;
  } catch (e) {
    error.value = e.data?.message || e.message;
  } finally {
    loading.value = false;
  }
}

async function create(launchAfter) {
  error.value = null;

  if (!form.value.name.trim()) { error.value = t('meta.campaign_create.err_name'); return; }
  if (!form.value.phone_number_id) { error.value = t('meta.campaign_create.err_phone'); return; }
  if (form.value.message_type !== 'text' && !form.value.template_id) { error.value = t('meta.campaign_create.err_template'); return; }
  if (form.value.message_type === 'text' && !form.value.text.trim()) { error.value = t('meta.campaign_create.err_text'); return; }
  if (!allRecipients.value.length) { error.value = t('meta.campaign_create.err_recipients'); return; }

  creating.value = true;
  try {
    const res = await metaTest.createCampaign({
      ...form.value,
      template_id: form.value.message_type === 'text' ? null : form.value.template_id,
      recipients: allRecipients.value,
    });
    const campaign = res.data;

    if (launchAfter) {
      await metaTest.launchCampaign(campaign.id);
    }

    router.push(`/meta/campaigns/${campaign.id}`);
  } catch (e) {
    error.value = e.data?.message || e.message;
  } finally {
    creating.value = false;
  }
}

onMounted(load);
</script>
