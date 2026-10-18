<template>
  <div class="space-y-8">
    <section>
      <h2 class="text-2xl font-bold text-gray-900 tracking-tight mb-4">{{ t('meta.messages.send_title') }}</h2>
      <form @submit.prevent="send" class="grid grid-cols-1 gap-4 max-w-xl">
        <div>
          <label class="block text-sm font-medium text-gray-700">{{ t('meta.messages.from') }}</label>
          <select v-model="form.phone_number_id" required class="mt-1 block w-full border rounded p-2">
            <option v-for="phone in numbers" :key="phone.id" :value="phone.id">
              {{ phone.display_name || phone.phone_number }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">{{ t('meta.messages.to') }}</label>
          <input v-model="form.to" type="text" required class="mt-1 block w-full border rounded p-2" placeholder="+2010..." />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">{{ t('meta.messages.message') }}</label>
          <textarea v-model="form.message" rows="3" class="mt-1 block w-full border rounded p-2" :placeholder="t('meta.messages.message_ph')"></textarea>
        </div>
        <button
          type="submit"
          :disabled="sending"
          class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50"
        >
          {{ sending ? t('meta.messages.sending') : t('meta.messages.send') }}
        </button>
        <p v-if="sendError" class="text-red-600 text-sm">{{ sendError }}</p>
        <div v-if="sendResult" class="bg-green-50 border border-green-200 rounded p-3 text-sm text-green-800">
          <p>{{ t('meta.messages.message_id') }}: {{ sendResult.meta_message_id }}</p>
          <p>Status: {{ sendResult.status }}</p>
        </div>
      </form>
    </section>

    <section>
      <h2 class="text-2xl font-bold text-gray-900 tracking-tight mb-4">{{ t('meta.messages.inbox') }}</h2>
      <div v-if="loading" class="text-gray-500">{{ t('meta.common.loading') }}</div>
      <div v-else-if="messages.length" class="space-y-3">
        <div
          v-for="msg in messages"
          :key="msg.id"
          class="border border-gray-100 rounded-xl p-3"
          :class="msg.direction === 'incoming' ? 'bg-gray-50' : 'bg-indigo-50'"
        >
          <div class="flex justify-between text-xs text-gray-500">
            <span>{{ msg.from_phone }} → {{ msg.to_phone }}</span>
            <span>{{ msg.direction === 'incoming' ? t('meta.messages.incoming') : t('meta.messages.outgoing') }}</span>
          </div>
          <p class="text-sm mt-1">{{ msg.body }}</p>
          <p class="text-xs text-gray-400 mt-1">{{ msg.created_at }} | {{ msg.status }}</p>
        </div>
      </div>
      <div v-else class="text-gray-500">{{ t('meta.messages.empty_inbox') }}</div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import metaTest from '@/services/metaTest';
import { t } from '@/i18n';

const numbers = ref([]);
const messages = ref([]);
const loading = ref(true);
const sending = ref(false);
const sendError = ref(null);
const sendResult = ref(null);

const form = ref({
  phone_number_id: '',
  to: '',
  message: '',
});

async function load() {
  try {
    const [phoneRes, msgRes] = await Promise.all([metaTest.phoneNumbers(), metaTest.messages()]);
    numbers.value = phoneRes.data;
    messages.value = msgRes.data?.data || [];
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function send() {
  sending.value = true;
  sendError.value = null;
  sendResult.value = null;
  try {
    const res = await metaTest.sendMessage({
      phone_number_id: form.value.phone_number_id,
      to: form.value.to,
      message: form.value.message,
    });
    sendResult.value = res.data;
    form.value.to = '';
    form.value.message = '';
  } catch (e) {
    sendError.value = e.data?.message || e.message;
  } finally {
    sending.value = false;
  }
}

onMounted(load);
</script>
