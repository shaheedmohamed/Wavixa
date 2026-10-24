<template>
  <div>
    <h2 class="text-2xl font-bold text-gray-900 tracking-tight mb-4">{{ t('meta.connect.title') }}</h2>

    <div v-if="!connected" class="space-y-4">
      <p class="text-sm text-gray-600">{{ t('meta.connect.desc') }}</p>

      <button
        @click="startConnect"
        :disabled="connecting || exchanging"
        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
      >
        <span v-if="exchanging">{{ t('meta.connect.finishing') }}</span>
        <span v-else-if="connecting">{{ t('meta.connect.connecting') }}</span>
        <span v-else>{{ t('meta.connect.button') }}</span>
      </button>

      <p v-if="!config?.embedded_signup_available" class="text-xs text-amber-600">
        {{ t('meta.connect.legacy_notice') }}
      </p>

      <p v-if="cancelled" class="text-sm text-gray-500">{{ t('meta.connect.cancelled') }}</p>
      <p v-if="error" class="text-red-600 text-sm">{{ error }}</p>
    </div>

    <div v-else class="space-y-4">
      <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-green-800">
        <p class="font-semibold">{{ t('meta.connect.connected') }}</p>
        <p class="text-sm">{{ t('meta.connect.waba_count') }}: {{ connection?.waba_count ?? 0 }}</p>
        <p class="text-sm">
          {{ t('meta.connect.onboarding_type') }}:
          <span class="font-mono">{{ connection?.onboarding_type || 'unknown' }}</span>
          <span v-if="isCoexistence" class="ml-2 inline-block px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded text-xs font-semibold">
            {{ t('meta.connect.coexistence_badge') }}
          </span>
        </p>
        <p class="text-sm" v-if="connection && connection.webhook_subscribed === false">
          <span class="text-amber-700">{{ t('meta.connect.webhook_pending') }}</span>
        </p>
        <p v-if="connection?.error_message" class="text-sm text-amber-700 mt-1">{{ connection.error_message }}</p>
      </div>

      <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-3">
        <h3 class="font-semibold text-gray-900 text-sm">{{ t('meta.connect.test_title') }}</h3>
        <div class="flex flex-wrap gap-2 items-end">
          <div>
            <label class="block text-xs text-gray-500 mb-1">{{ t('meta.connect.test_from') }}</label>
            <select v-model="testForm.phone_number_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
              <option v-for="p in phoneNumbers" :key="p.id" :value="p.id">
                {{ p.phone_number }}<template v-if="p.display_name"> — {{ p.display_name }}</template>
              </option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">{{ t('meta.connect.test_to') }}</label>
            <input v-model="testForm.to" type="text" placeholder="2010XXXXXXXX"
              class="border border-gray-300 rounded-lg px-3 py-2 text-sm" />
          </div>
          <div class="flex-1 min-w-[200px]">
            <label class="block text-xs text-gray-500 mb-1">{{ t('meta.connect.test_message') }}</label>
            <input v-model="testForm.message" type="text" :placeholder="t('meta.connect.test_message_ph')"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
          </div>
          <button
            @click="sendTest"
            :disabled="sendingTest || !testForm.phone_number_id || !testForm.to || !testForm.message"
            class="px-4 py-2 text-sm font-medium rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50"
          >
            {{ sendingTest ? t('meta.common.sending') : t('meta.connect.test_send') }}
          </button>
        </div>
        <p v-if="testResult" class="text-sm" :class="testResult.ok ? 'text-emerald-700' : 'text-red-600'">
          {{ testResult.text }}
        </p>
      </div>

      <button
        @click="disconnect"
        class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50"
      >
        {{ t('meta.connect.disconnect') }}
      </button>
    </div>

    <!-- Coexistence: WhatsApp Business app + Cloud API on the same number -->
    <div class="mt-6 bg-emerald-50/60 border border-emerald-200 rounded-2xl p-5 space-y-4">
      <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3">
          <span class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2z"/></svg>
          </span>
          <div>
            <h3 class="font-bold text-emerald-900 text-sm">{{ t('meta.connect.coex_title') }}</h3>
            <p class="text-xs text-emerald-800/80 mt-0.5 max-w-xl leading-relaxed">{{ t('meta.connect.coex_desc') }}</p>
          </div>
        </div>
        <button
          @click="startConnect"
          :disabled="connecting || exchanging"
          class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 shrink-0"
        >
          {{ t('meta.connect.coex_btn') }}
        </button>
      </div>

      <ol class="text-xs text-emerald-900/80 space-y-1.5 list-decimal list-inside marker:font-bold">
        <li>{{ t('meta.connect.coex_step1') }}</li>
        <li>{{ t('meta.connect.coex_step2') }}</li>
        <li>{{ t('meta.connect.coex_step3') }}</li>
      </ol>

      <div class="text-[11px] text-emerald-800/70 bg-white/60 border border-emerald-100 rounded-lg px-3 py-2">
        {{ t('meta.connect.coex_api_note') }}
        <a
          href="https://business.facebook.com/latest/settings/whatsapp_business_presence"
          target="_blank"
          class="font-bold underline hover:text-emerald-900"
        >{{ t('meta.connect.coex_api_link') }}</a>
      </div>

      <!-- Per-number app link status -->
      <div v-if="phoneNumbers.length" class="flex flex-wrap gap-2">
        <span
          v-for="p in phoneNumbers"
          :key="p.id"
          class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border"
          :class="p.raw_data?.is_on_biz_app
            ? 'bg-emerald-100 text-emerald-800 border-emerald-300'
            : 'bg-white text-slate-500 border-slate-200'"
          dir="ltr"
        >
          <span class="w-1.5 h-1.5 rounded-full" :class="p.raw_data?.is_on_biz_app ? 'bg-emerald-500' : 'bg-slate-300'"></span>
          {{ p.phone_number }}
          <span class="font-normal" dir="auto">— {{ p.raw_data?.is_on_biz_app ? t('meta.connect.coex_linked') : t('meta.connect.coex_not_linked') }}</span>
        </span>
      </div>
    </div>

    <div class="mt-6 text-sm text-gray-500">
      <p>{{ t('meta.dashboard.redirect_uri') }}: {{ data?.redirect_uri || '—' }}</p>
      <p class="mt-1">{{ t('meta.connect.status_url') }}: {{ statusMessage }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import metaTest from '@/services/metaTest';
import { openMetaEmbeddedSignup } from '@/services/metaEmbeddedSignup';
import { t } from '@/i18n';

const route = useRoute();
const loading = ref(true);
const connecting = ref(false);
const exchanging = ref(false);
const cancelled = ref(false);
const error = ref(null);
const data = ref(null);
const config = ref(null);
const connected = ref(false);
const connection = ref(null);
const phoneNumbers = ref([]);
const sendingTest = ref(false);
const testResult = ref(null);
const testForm = ref({ phone_number_id: null, to: '', message: '' });

const isCoexistence = computed(() =>
  connection.value?.onboarding_type === 'business_app_onboarding'
);

const statusMessage = computed(() => {
  const status = route.query.status;
  if (!status) return '—';
  if (status === 'connected') return t('meta.connect.status_connected');
  if (status === 'denied') return t('meta.connect.status_denied');
  if (status === 'error') return `${t('meta.connect.status_error')} ${route.query.message || 'Unknown'}`;
  return status;
});

onMounted(async () => {
  await Promise.all([loadConnectData(), loadEmbeddedConfig(), refreshConnection()]);
  loading.value = false;
});

async function loadConnectData() {
  try {
    const res = await metaTest.connectUrl();
    data.value = res.data;
  } catch (e) {
    error.value = e.data?.message || e.message;
  }
}

async function loadEmbeddedConfig() {
  try {
    const res = await metaTest.embeddedSignupConfig();
    config.value = res.data;
  } catch {
    config.value = { embedded_signup_available: false };
  }
}

async function refreshConnection() {
  try {
    const [dash, phones] = await Promise.all([
      metaTest.dashboard(),
      metaTest.phoneNumbers().catch(() => ({ data: [] })),
    ]);

    phoneNumbers.value = phones.data || [];
    if (phoneNumbers.value.length && !testForm.value.phone_number_id) {
      testForm.value.phone_number_id = phoneNumbers.value[0].id;
    }

    if (dash.data?.connection_status === 'connected') {
      connected.value = true;
      connection.value = {
        waba_count: dash.data.waba_count,
        onboarding_type: dash.data.onboarding_type,
        webhook_subscribed: dash.data.webhook_subscribed,
        error_message: null,
      };
    }
  } catch {
    // Not connected yet — stay on the connect screen.
  }
}

async function startConnect() {
  error.value = null;
  cancelled.value = false;

  if (config.value?.embedded_signup_available) {
    await startEmbeddedSignup();
  } else if (data.value?.url) {
    window.location.href = data.value.url;
  } else {
    error.value = t('meta.connect.no_url');
  }
}

async function startEmbeddedSignup() {
  connecting.value = true;

  let result;
  try {
    result = await openMetaEmbeddedSignup({
      appId: config.value.app_id,
      apiVersion: config.value.api_version,
      configId: config.value.config_id,
      featureType: config.value.feature_type,
      sessionInfoVersion: config.value.session_info_version,
    });
  } catch (e) {
    connecting.value = false;
    if (e.code === 'cancelled' || e.code === 'popup_blocked') {
      cancelled.value = true;
    } else if (e.code === 'sdk_load_failed') {
      error.value = t('meta.connect.err_sdk');
    } else if (e.code === 'meta_flow_error') {
      error.value = t('meta.connect.err_flow');
    } else {
      error.value = t('meta.connect.err_generic');
    }
    return;
  }

  connecting.value = false;
  exchanging.value = true;

  try {
    const res = await metaTest.embeddedSignupCallback({
      code: result.code,
      waba_id: result.sessionInfo?.waba_id || null,
      phone_number_id: result.sessionInfo?.phone_number_id || null,
      event: result.event || null,
      session_info: result.sessionInfo || null,
    });

    if (!res.success) {
      error.value = res.message || t('meta.connect.err_generic');
      return;
    }

    connection.value = res.data;
    connected.value = res.data.status === 'connected';
    if (!connected.value) {
      error.value = res.data.error_message || t('meta.connect.err_generic');
    }
    await refreshConnection();
  } catch (e) {
    error.value = e.data?.message || t('meta.connect.err_generic');
  } finally {
    exchanging.value = false;
  }
}

async function sendTest() {
  sendingTest.value = true;
  testResult.value = null;
  try {
    const res = await metaTest.sendMessage({
      phone_number_id: testForm.value.phone_number_id,
      to: testForm.value.to,
      message: testForm.value.message,
    });
    if (res.success && res.data?.meta_message_id) {
      testResult.value = { ok: true, text: `${t('meta.connect.test_ok')} (${res.data.meta_message_id})` };
    } else {
      testResult.value = { ok: false, text: res.message || t('meta.connect.test_fail') };
    }
  } catch (e) {
    testResult.value = { ok: false, text: e.data?.message || e.message };
  } finally {
    sendingTest.value = false;
  }
}

async function disconnect() {
  try {
    await metaTest.disconnect();
    connected.value = false;
    connection.value = null;
  } catch (e) {
    error.value = e.data?.message || e.message;
  }
}
</script>
