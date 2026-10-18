<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('meta.numbers.title') }}</h2>
      <div class="flex gap-2">
        <button
          @click="addModal = true"
          class="px-3 py-2 bg-emerald-600 text-white text-sm font-bold rounded-xl hover:bg-emerald-700"
        >
          + Add Number
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
    <div v-else-if="numbers.length" class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead>
          <tr>
            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.numbers.th_number') }}</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.numbers.th_name') }}</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.numbers.th_waba') }}</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.numbers.th_sent') }}</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.numbers.th_status') }}</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.numbers.th_quality') }}</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.numbers.th_tier') }}</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('meta.numbers.th_actions') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="phone in numbers" :key="phone.id">
            <td class="px-3 py-2 font-mono font-semibold" dir="ltr">{{ phone.phone_number }}</td>
            <td class="px-3 py-2">{{ phone.display_name || '—' }}</td>
            <td class="px-3 py-2 text-gray-500 text-xs">{{ phone.waba?.name || '—' }}</td>
            <td class="px-3 py-2 font-bold text-indigo-600">{{ (phone.sent_count ?? 0).toLocaleString() }}</td>
            <td class="px-3 py-2">
              <span :class="statusBadge(phone.status)" class="px-2 py-0.5 rounded-lg text-[10px] font-bold uppercase">
                {{ phone.status }}
              </span>
            </td>
            <td class="px-3 py-2">{{ phone.quality_rating || '—' }}</td>
            <td class="px-3 py-2">{{ phone.messaging_limit_tier || '—' }}</td>
            <td class="px-3 py-2">
              <div class="flex gap-1.5">
                <button
                  v-if="['PENDING', 'UNVERIFIED'].includes(phone.status)"
                  @click="openVerify(phone)"
                  class="px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-lg text-xs font-bold hover:bg-amber-100"
                >{{ t('meta.numbers.verify') }}
                </button>
                <button
                  v-if="phone.status === 'VERIFIED'"
                  @click="registerPhone(phone)"
                  class="px-2.5 py-1 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-bold hover:bg-indigo-100"
                >
                  Register
                </button>
                <button
                  @click="removePhone(phone)"
                  :disabled="deleting === phone.id"
                  class="px-2.5 py-1 bg-red-50 text-red-600 border border-red-200 rounded-lg text-xs font-bold hover:bg-red-100 disabled:opacity-50"
                >
                  {{ deleting === phone.id ? t('meta.numbers.deleting') : t('meta.numbers.delete') }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else class="text-gray-500">{{ t('meta.numbers.empty') }}</div>

    <!-- Add number modal -->
    <div v-if="addModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" @click.self="addModal = false">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h3 class="text-lg font-black text-slate-900">{{ t('meta.numbers.add_title') }}</h3>
        <p class="text-xs text-slate-500">{{ t('meta.numbers.add_desc') }}</p>

        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">{{ t('meta.numbers.waba_label') }}</label>
          <select v-model="addForm.waba_id" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm">
            <option value="" disabled>{{ t('meta.numbers.waba_ph') }}</option>
            <option v-for="w in wabas" :key="w.id" :value="w.id">{{ w.name }} — {{ w.meta_waba_id }}</option>
          </select>
        </div>

        <div class="grid grid-cols-3 gap-2">
          <div>
            <label class="block text-xs font-bold text-slate-600 mb-1">{{ t('meta.numbers.cc_label') }}</label>
            <input v-model="addForm.cc" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm" dir="ltr" placeholder="20" />
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-bold text-slate-600 mb-1">{{ t('meta.numbers.number_label') }}</label>
            <input v-model="addForm.phone_number" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm" dir="ltr" placeholder="1012345678" />
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">{{ t('meta.numbers.name_label') }}</label>
          <input v-model="addForm.display_name" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-sm" placeholder="My Business" />
          <p class="text-[10px] text-slate-400 mt-1">{{ t('meta.numbers.name_hint') }}</p>
        </div>

        <p v-if="addError" class="text-xs text-red-600 font-semibold">{{ addError }}</p>

        <div class="flex gap-2 justify-end">
          <button @click="addModal = false" class="px-4 py-2 text-sm font-bold text-slate-500 hover:text-slate-700">{{ t('meta.common.cancel') }}</button>
          <button @click="addNumber" :disabled="adding" class="px-4 py-2 bg-emerald-600 text-white text-sm font-bold rounded-xl hover:bg-emerald-700 disabled:opacity-50">
            {{ adding ? t('meta.numbers.adding') : t('meta.numbers.add_btn') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Verify modal -->
    <div v-if="verifyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" @click.self="verifyModal = null">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h3 class="text-lg font-black text-slate-900">{{ t('meta.numbers.verify_title') }} {{ verifyModal.phone_number }}</h3>

        <div class="flex gap-2">
          <button @click="sendCode('SMS')" :disabled="sendingCode" class="flex-1 px-3 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 disabled:opacity-50">
            {{ sendingCode ? t('meta.messages.sending') : t('meta.numbers.send_sms') }}
          </button>
          <button @click="sendCode('VOICE')" :disabled="sendingCode" class="flex-1 px-3 py-2 bg-slate-700 text-white text-sm font-bold rounded-xl hover:bg-slate-800 disabled:opacity-50">
            {{ t('meta.numbers.send_call') }}
          </button>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">{{ t('meta.numbers.code_label') }}</label>
          <input v-model="verifyCode" class="w-full border border-gray-100 rounded-xl px-3 py-2 text-lg font-mono tracking-widest text-center" dir="ltr" placeholder="123456" maxlength="8" />
        </div>

        <p v-if="verifyError" class="text-xs text-red-600 font-semibold">{{ verifyError }}</p>
        <p v-if="verifyMsg" class="text-xs text-emerald-600 font-semibold">{{ verifyMsg }}</p>

        <div class="flex gap-2 justify-end">
          <button @click="verifyModal = null" class="px-4 py-2 text-sm font-bold text-slate-500 hover:text-slate-700">{{ t('meta.numbers.close') }}</button>
          <button @click="submitVerify" :disabled="verifying || !verifyCode" class="px-4 py-2 bg-emerald-600 text-white text-sm font-bold rounded-xl hover:bg-emerald-700 disabled:opacity-50">
            {{ verifying ? t('meta.numbers.verifying') : t('meta.numbers.verify') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import metaTest from '@/services/metaTest';
import { t } from '@/i18n';

const loading = ref(true);
const syncing = ref(false);
const error = ref(null);
const numbers = ref([]);
const wabas = ref([]);

const addModal = ref(false);
const addForm = ref({ waba_id: '', cc: '20', phone_number: '', display_name: '' });
const adding = ref(false);
const addError = ref(null);

const deleting = ref(null);

const verifyModal = ref(null);
const verifyCode = ref('');
const sendingCode = ref(false);
const verifying = ref(false);
const verifyError = ref(null);
const verifyMsg = ref(null);

function statusBadge(status) {
  return {
    CONNECTED: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    VERIFIED: 'bg-blue-50 text-blue-700 border border-blue-200',
    PENDING: 'bg-amber-50 text-amber-700 border border-amber-200',
  }[status] || 'bg-slate-50 text-slate-500 border border-slate-200';
}

async function load() {
  try {
    const res = await metaTest.phoneNumbers();
    numbers.value = res.data;
  } catch (e) {
    error.value = e.data?.message || e.message;
  } finally {
    loading.value = false;
  }
}

async function loadWabas() {
  try {
    const res = await metaTest.accounts();
    wabas.value = res.data?.wabas || res.data || [];
  } catch (e) { /* ignore */ }
}

async function sync() {
  syncing.value = true;
  error.value = null;
  try {
    const res = await metaTest.syncPhoneNumbers();
    numbers.value = res.data;
  } catch (e) {
    error.value = e.data?.message || e.message;
  } finally {
    syncing.value = false;
  }
}

async function addNumber() {
  adding.value = true;
  addError.value = null;
  try {
    const res = await metaTest.addPhoneNumber(addForm.value);
    if (!res.success) throw new Error(res.message);
    addModal.value = false;
    addForm.value = { waba_id: '', cc: '20', phone_number: '', display_name: '' };
    await load();
  } catch (e) {
    addError.value = e.data?.message || e.message;
  } finally {
    adding.value = false;
  }
}

function openVerify(phone) {
  verifyModal.value = phone;
  verifyCode.value = '';
  verifyError.value = null;
  verifyMsg.value = null;
}

async function sendCode(method) {
  sendingCode.value = true;
  verifyError.value = null;
  verifyMsg.value = null;
  try {
    const res = await metaTest.sendPhoneCode(verifyModal.value.id, method);
    if (!res.success) throw new Error(res.message);
    verifyMsg.value = method === 'SMS' ? t('meta.numbers.sms_sent') : t('meta.numbers.call_sent');
  } catch (e) {
    verifyError.value = e.data?.message || e.message;
  } finally {
    sendingCode.value = false;
  }
}

async function submitVerify() {
  verifying.value = true;
  verifyError.value = null;
  try {
    const res = await metaTest.verifyPhone(verifyModal.value.id, verifyCode.value);
    if (!res.success) throw new Error(res.message);
    verifyMsg.value = t('meta.numbers.verified_msg');
    await load();
  } catch (e) {
    verifyError.value = e.data?.message || e.message;
  } finally {
    verifying.value = false;
  }
}

async function removePhone(phone) {
  if (!confirm(`${t('meta.numbers.delete_confirm')} ${phone.phone_number}`)) return;
  deleting.value = phone.id;
  error.value = null;
  try {
    const res = await metaTest.deletePhoneNumber(phone.id);
    if (!res.success) throw new Error(res.message);
    numbers.value = numbers.value.filter((n) => n.id !== phone.id);
  } catch (e) {
    error.value = e.data?.message || e.message;
  } finally {
    deleting.value = null;
  }
}

async function registerPhone(phone) {
  try {
    const res = await metaTest.registerPhone(phone.id);
    if (!res.success) throw new Error(res.message);
    await load();
  } catch (e) {
    alert(e.data?.message || e.message);
  }
}

onMounted(() => { load(); loadWabas(); });
</script>
