<template>
  <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
    <!-- Header -->
    <div class="px-6 sm:px-8 py-5 border-b border-slate-100 bg-slate-50/50">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-2.5">
        <div class="flex items-center gap-3">
          <span :class="['px-3 py-1 rounded-xl text-xs font-black uppercase tracking-wider border shadow-2xs', methodClass]">
            {{ endpoint.method }}
          </span>
          <code class="text-indigo-600 font-mono text-xs sm:text-sm font-bold bg-indigo-50/80 px-3 py-1 rounded-xl border border-indigo-100" dir="ltr">
            {{ endpoint.url }}
          </code>
        </div>
        <span v-if="endpointBadge" class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-100">
          {{ endpointBadge }}
        </span>
      </div>
      <h3 class="text-lg sm:text-xl font-black text-slate-900">{{ endpointTitle }}</h3>
      <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1 leading-relaxed">{{ endpointDescription }}</p>
    </div>

    <!-- Tabs Navigation -->
    <div class="border-b border-slate-100 flex px-4 sm:px-8 gap-2 sm:gap-6 bg-white overflow-x-auto">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="activeTab = tab.id"
        :class="[
          'py-3.5 px-3 text-xs font-black transition-all relative whitespace-nowrap cursor-pointer',
          activeTab === tab.id
            ? 'text-indigo-600 after:absolute after:bottom-0 after:left-0 after:right-0 after:h-0.5 after:bg-indigo-600'
            : 'text-slate-400 hover:text-slate-700'
        ]"
      >
        <span class="flex items-center gap-1.5">
          <span>{{ tab.icon }}</span>
          <span>{{ tab.label }}</span>
        </span>
      </button>
    </div>

    <!-- Content Area -->
    <div class="p-5 sm:p-8">
      <!-- Tab 1: Overview & Parameters -->
      <div v-if="activeTab === 'overview'" class="space-y-6">
        <!-- Headers -->
        <div v-if="endpoint.headers && endpoint.headers.length">
          <h4 class="text-xs font-black text-slate-400 uppercase tracking-wider mb-3">
            {{ isRTL ? 'ترويسات الطلب (Headers)' : 'Request Headers' }}
          </h4>
          <div class="overflow-x-auto border border-slate-100 rounded-2xl">
            <table class="w-full text-xs" :class="isRTL ? 'text-right' : 'text-left'">
              <thead class="bg-slate-50/70 border-b border-slate-100">
                <tr>
                  <th class="px-5 py-3 font-bold text-slate-500">{{ isRTL ? 'العنوان' : 'Header' }}</th>
                  <th class="px-5 py-3 font-bold text-slate-500">{{ isRTL ? 'القيمة المقترحة' : 'Suggested Value' }}</th>
                  <th class="px-5 py-3 font-bold text-slate-500">{{ isRTL ? 'الحالة' : 'Status' }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium">
                <tr v-for="header in endpoint.headers" :key="header.name" class="hover:bg-slate-50/50">
                  <td class="px-5 py-3.5 font-mono font-bold text-indigo-600" dir="ltr">{{ header.name }}</td>
                  <td class="px-5 py-3.5 text-slate-600 font-mono" dir="ltr">{{ header.value }}</td>
                  <td class="px-5 py-3.5">
                    <span :class="['text-[10px] font-black px-2 py-0.5 rounded-md', header.required ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'bg-slate-100 text-slate-500']">
                      {{ header.required ? (isRTL ? 'مطلوب' : 'Required') : (isRTL ? 'اختياري' : 'Optional') }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Parameters -->
        <div v-if="endpoint.parameters && endpoint.parameters.length">
          <h4 class="text-xs font-black text-slate-400 uppercase tracking-wider mb-3">
            {{ isRTL ? 'معاملات الطلب (Parameters)' : 'Request Parameters' }}
          </h4>
          <div class="overflow-x-auto border border-slate-100 rounded-2xl">
            <table class="w-full text-xs" :class="isRTL ? 'text-right' : 'text-left'">
              <thead class="bg-slate-50/70 border-b border-slate-100">
                <tr>
                  <th class="px-5 py-3 font-bold text-slate-500">{{ isRTL ? 'المعامل' : 'Parameter' }}</th>
                  <th class="px-5 py-3 font-bold text-slate-500">{{ isRTL ? 'النوع' : 'Type' }}</th>
                  <th class="px-5 py-3 font-bold text-slate-500">{{ isRTL ? 'الحالة' : 'Status' }}</th>
                  <th class="px-5 py-3 font-bold text-slate-500">{{ isRTL ? 'الوصف والشرح' : 'Description' }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium">
                <tr v-for="param in endpoint.parameters" :key="param.name" class="hover:bg-slate-50/50">
                  <td class="px-5 py-3.5 font-mono font-bold text-indigo-600" dir="ltr">{{ param.name }}</td>
                  <td class="px-5 py-3.5 text-slate-500 font-mono text-[11px]">{{ param.type }}</td>
                  <td class="px-5 py-3.5">
                    <span :class="['text-[10px] font-black px-2 py-0.5 rounded-md', param.required ? 'bg-rose-50 text-rose-700 border border-rose-100' : 'bg-slate-100 text-slate-500']">
                      {{ param.required ? (isRTL ? 'إجباري' : 'Required') : (isRTL ? 'اختياري' : 'Optional') }}
                    </span>
                  </td>
                  <td class="px-5 py-3.5 text-slate-600 leading-relaxed">
                    {{ isRTL ? (param.description_ar || param.description) : (param.description_en || param.description) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Notes / Tips -->
        <div v-if="endpointNote" class="bg-amber-50/60 border border-amber-200/60 rounded-2xl p-4 text-xs font-medium text-amber-900 flex items-start gap-3">
          <span class="text-base">💡</span>
          <div class="leading-relaxed">{{ endpointNote }}</div>
        </div>
      </div>

      <!-- Tab 2: Code Snippets (cURL, JS, PHP, Python) -->
      <div v-if="activeTab === 'code'" class="space-y-4">
        <!-- Language Switcher -->
        <div class="flex items-center justify-between gap-3 flex-wrap">
          <div class="flex items-center gap-1.5 p-1 bg-slate-100 rounded-xl">
            <button
              v-for="lang in codeLanguages"
              :key="lang.id"
              @click="activeCodeLang = lang.id"
              :class="[
                'px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer',
                activeCodeLang === lang.id
                  ? 'bg-white text-slate-900 shadow-2xs'
                  : 'text-slate-500 hover:text-slate-800'
              ]"
            >
              {{ lang.name }}
            </button>
          </div>

          <button
            @click="copyActiveCode"
            class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition-all cursor-pointer shadow-xs active:scale-95"
          >
            <svg v-if="copied" class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
            <span>{{ copied ? (isRTL ? 'تم النسخ!' : 'Copied!') : (isRTL ? 'نسخ الكود' : 'Copy Code') }}</span>
          </button>
        </div>

        <!-- Code Box -->
        <div class="relative bg-slate-950 rounded-2xl p-5 border border-slate-800 shadow-xl overflow-hidden text-left" dir="ltr">
          <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800/80 text-[11px] text-slate-400 font-mono">
            <span>{{ currentLanguageTitle }}</span>
            <span class="text-indigo-400">
              {{ effectiveApiKey === 'YOUR_API_KEY' ? (isRTL ? '⚠️ استبدل YOUR_API_KEY بمفتاحك' : '⚠️ Replace YOUR_API_KEY with your key') : (isRTL ? '✅ تم تضمين مفتاحك' : '✅ Your key is injected') }}
            </span>
          </div>
          <pre class="text-xs text-indigo-300 font-mono overflow-x-auto leading-relaxed whitespace-pre font-medium">{{ currentCodeSnippet }}</pre>
        </div>
      </div>

      <!-- Tab 3: Response Schema / Examples -->
      <div v-if="activeTab === 'response'" class="space-y-5">
        <div>
          <div class="flex items-center justify-between mb-2.5">
            <h4 class="text-xs font-black text-slate-400 uppercase tracking-wider">
              {{ isRTL ? 'مثال استجابة النجاح (200 OK)' : 'Success Response Example (200 OK)' }}
            </h4>
            <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-emerald-500/10 text-emerald-600 font-mono">HTTP 200 OK</span>
          </div>
          <div class="bg-slate-950 rounded-2xl p-5 border border-slate-800 shadow-xl text-left" dir="ltr">
            <pre class="text-xs text-emerald-400 font-mono overflow-x-auto leading-relaxed">{{ responseExample }}</pre>
          </div>
        </div>

        <div v-if="endpoint.errors && endpoint.errors.length">
          <h4 class="text-xs font-black text-slate-400 uppercase tracking-wider mb-3 text-rose-500">
            {{ isRTL ? 'استجابات الأخطاء المحتملة' : 'Potential Error Responses' }}
          </h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div
              v-for="err in endpoint.errors"
              :key="err.status"
              class="flex items-center gap-3 p-3.5 bg-rose-50/50 border border-rose-100 rounded-xl"
            >
              <span class="w-10 h-10 bg-white text-rose-600 rounded-lg flex items-center justify-center text-xs font-black shadow-2xs border border-rose-100 font-mono">
                {{ err.status }}
              </span>
              <div>
                <p class="text-xs font-bold text-rose-900">{{ isRTL ? (err.message_ar || err.message) : (err.message_en || err.message) }}</p>
                <p v-if="err.solution" class="text-[11px] text-slate-500 mt-0.5">{{ isRTL ? (err.solution_ar || err.solution) : (err.solution_en || err.solution) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 4: Try It Out (Live Sandbox) -->
      <div v-if="activeTab === 'try-it'" class="space-y-6">
        <div v-if="!apiKey" class="bg-indigo-50 border border-indigo-200/60 rounded-2xl p-4 flex items-center gap-3 text-indigo-700 text-xs font-bold">
          <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <p>{{ isRTL ? 'أدخل مفتاح API الخاص بك في الحقل بالأعلى لتجربة إرسال الطلب فعلياً واستلام النتيجة المباشرة.' : 'Enter your API key at the top to test sending real requests and inspect live server responses.' }}</p>
        </div>

        <!-- Form Fields -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div v-for="param in endpoint.parameters" :key="param.name" :class="param.name === 'message' || param.name === 'caption' ? 'sm:col-span-2' : ''" class="space-y-1.5">
            <label class="block text-xs font-bold text-slate-700">
              <span class="font-mono text-indigo-600" dir="ltr">{{ param.name }}</span>
              <span v-if="param.required" class="text-rose-500 mx-1">*</span>
              <span class="text-[11px] font-normal text-slate-400">
                ({{ isRTL ? (param.description_ar || param.description) : (param.description_en || param.description) }})
              </span>
            </label>
            <textarea
              v-if="param.name === 'message' || param.name === 'caption'"
              v-model="tryValues[param.name]"
              rows="3"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-900 font-bold focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all outline-none resize-none"
              :placeholder="isRTL ? (param.description_ar || param.description) : (param.description_en || param.description)"
            ></textarea>
            <input
              v-else
              v-model="tryValues[param.name]"
              type="text"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-900 font-bold focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all outline-none"
              :placeholder="param.placeholder || param.name"
            />
          </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex items-center gap-3 pt-2">
          <button
            @click="tryEndpoint"
            :disabled="trying"
            class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-6 py-2.5 rounded-xl font-black text-xs transition-all shadow-md shadow-indigo-200 active:scale-95 flex items-center gap-2 cursor-pointer"
          >
            <svg v-if="trying" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span>{{ trying ? (isRTL ? 'جاري التنفيذ...' : 'Executing...') : (isRTL ? '🚀 تنفيذ الطلب الآن' : '🚀 Send Request Now') }}</span>
          </button>
          <button
            @click="resetValues"
            class="bg-white border border-slate-200 text-slate-500 px-4 py-2.5 rounded-xl font-bold text-xs hover:bg-slate-50 hover:text-slate-800 transition-all cursor-pointer"
          >
            {{ isRTL ? 'إعادة تعيين' : 'Reset' }}
          </button>
        </div>

        <!-- Live Response Box -->
        <div v-if="tryResponse" class="mt-6">
          <div class="flex items-center justify-between mb-2">
            <h4 class="text-xs font-black text-slate-400 uppercase tracking-wider">
              {{ isRTL ? 'الاستجابة المباشرة (Live Response)' : 'Live Server Response' }}
            </h4>
            <span v-if="tryStatus" class="text-[10px] font-black font-mono px-2 py-0.5 rounded" :class="tryResponse.success ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'">
              HTTP {{ tryStatus }}
            </span>
          </div>
          <div :class="['rounded-2xl p-5 border text-left shadow-md', tryResponse.success ? 'bg-emerald-950 border-emerald-800 text-emerald-300' : 'bg-slate-950 border-rose-900 text-rose-300']" dir="ltr">
            <pre class="text-xs font-mono overflow-x-auto leading-relaxed whitespace-pre">{{ tryResponseFormatted }}</pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch } from 'vue';
import { isRTL } from '@/i18n';

export default {
  name: 'EndpointCard',
  props: {
    endpoint: { type: Object, required: true },
    apiKey: { type: String, default: '' },
    baseUrl: { type: String, required: true },
  },
  setup(props) {
    const activeTab = ref('code');
    const activeCodeLang = ref('curl');
    const copied = ref(false);
    const trying = ref(false);
    const tryResponse = ref(null);
    const tryStatus = ref(null);
    const tryValues = ref({});

    const tabs = computed(() => isRTL.value ? [
      { id: 'code', label: 'كود الربط الجاهز', icon: '💻' },
      { id: 'overview', label: 'المعاملات والتفاصيل', icon: '📋' },
      { id: 'response', label: 'نموذج الاستجابة', icon: '📥' },
      { id: 'try-it', label: 'جرب مباشرة', icon: '⚡' },
    ] : [
      { id: 'code', label: 'Ready Code Snippets', icon: '💻' },
      { id: 'overview', label: 'Parameters & Details', icon: '📋' },
      { id: 'response', label: 'Response Schema', icon: '📥' },
      { id: 'try-it', label: 'Live Sandbox', icon: '⚡' },
    ]);

    const codeLanguages = [
      { id: 'curl', name: 'cURL' },
      { id: 'javascript', name: 'JavaScript' },
      { id: 'php', name: 'PHP' },
      { id: 'python', name: 'Python' },
    ];

    const endpointTitle = computed(() => {
      return isRTL.value ? (props.endpoint.title_ar || props.endpoint.title) : (props.endpoint.title_en || props.endpoint.title);
    });

    const endpointDescription = computed(() => {
      return isRTL.value ? (props.endpoint.description_ar || props.endpoint.description) : (props.endpoint.description_en || props.endpoint.description);
    });

    const endpointBadge = computed(() => {
      return isRTL.value ? (props.endpoint.badge_ar || props.endpoint.badge) : (props.endpoint.badge_en || props.endpoint.badge);
    });

    const endpointNote = computed(() => {
      return isRTL.value ? (props.endpoint.note_ar || props.endpoint.note) : (props.endpoint.note_en || props.endpoint.note);
    });

    const effectiveApiKey = computed(() => {
      return props.apiKey && props.apiKey.trim().length > 0 ? props.apiKey.trim() : 'YOUR_API_KEY';
    });

    const fullUrl = computed(() => {
      return `${props.baseUrl}${props.endpoint.url}`;
    });

    const methodClass = computed(() => {
      const map = {
        GET: 'bg-sky-50 text-sky-700 border-sky-200',
        POST: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        PUT: 'bg-amber-50 text-amber-700 border-amber-200',
        DELETE: 'bg-rose-50 text-rose-700 border-rose-200',
      };
      return map[props.endpoint.method] || 'bg-slate-100 text-slate-700 border-slate-200';
    });

    const currentLanguageTitle = computed(() => {
      const map = {
        curl: 'cURL Command (Bash / Terminal)',
        javascript: 'JavaScript / Node.js (Fetch API)',
        php: 'PHP (cURL Native / Guzzle)',
        python: 'Python (requests library)',
      };
      return map[activeCodeLang.value] || 'Code';
    });

    const currentCodeSnippet = computed(() => {
      const key = effectiveApiKey.value;
      const url = fullUrl.value;
      const method = props.endpoint.method;
      const body = props.endpoint.requestExample ? JSON.stringify(props.endpoint.requestExample, null, 2) : '{}';

      if (activeCodeLang.value === 'curl') {
        if (method === 'GET') {
          return `curl -X GET "${url}" \\
  -H "X-API-Key: ${key}" \\
  -H "Accept: application/json"`;
        }
        return `curl -X POST "${url}" \\
  -H "X-API-Key: ${key}" \\
  -H "Content-Type: application/json" \\
  -d '${JSON.stringify(props.endpoint.requestExample || {})}'`;
      }

      if (activeCodeLang.value === 'javascript') {
        if (method === 'GET') {
          return `// Node.js 18+ or Browser
const response = await fetch("${url}", {
  method: "GET",
  headers: {
    "X-API-Key": "${key}",
    "Accept": "application/json"
  }
});

const result = await response.json();
console.log(result);`;
        }

        return `// Node.js 18+ or Browser
const payload = ${body};

const response = await fetch("${url}", {
  method: "POST",
  headers: {
    "X-API-Key": "${key}",
    "Content-Type": "application/json",
    "Accept": "application/json"
  },
  body: JSON.stringify(payload)
});

const result = await response.json();
console.log(result);`;
      }

      if (activeCodeLang.value === 'php') {
        if (method === 'GET') {
          return `<?php

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "${url}",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => [
        "X-API-Key: ${key}",
        "Accept: application/json"
    ],
]);

$response = curl_exec($curl);
curl_close($curl);

$data = json_decode($response, true);
print_r($data);`;
        }

        return `<?php

$curl = curl_init();
$payload = ${body};

curl_setopt_array($curl, [
    CURLOPT_URL => "${url}",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        "X-API-Key: ${key}",
        "Content-Type: application/json",
        "Accept: application/json"
    ],
]);

$response = curl_exec($curl);
curl_close($curl);

$data = json_decode($response, true);
print_r($data);`;
      }

      if (activeCodeLang.value === 'python') {
        if (method === 'GET') {
          return `import requests

url = "${url}"
headers = {
    "X-API-Key": "${key}",
    "Accept": "application/json"
}

response = requests.get(url, headers=headers)
print(response.json())`;
        }

        return `import requests

url = "${url}"
headers = {
    "X-API-Key": "${key}",
    "Content-Type": "application/json",
    "Accept": "application/json"
}
payload = ${body}

response = requests.post(url, headers=headers, json=payload)
print(response.json())`;
      }

      return '';
    });

    const responseExample = computed(() => {
      return JSON.stringify(props.endpoint.responseExample || { success: true }, null, 2);
    });

    const tryResponseFormatted = computed(() => {
      return JSON.stringify(tryResponse.value, null, 2);
    });

    const resetValues = () => {
      tryValues.value = {};
      if (props.endpoint.requestExample) {
        Object.assign(tryValues.value, props.endpoint.requestExample);
      }
      tryResponse.value = null;
      tryStatus.value = null;
    };

    const copyActiveCode = async () => {
      try {
        await navigator.clipboard.writeText(currentCodeSnippet.value);
        copied.value = true;
        setTimeout(() => {
          copied.value = false;
        }, 2000);
      } catch (err) {
        console.error('Failed to copy', err);
      }
    };

    const tryEndpoint = async () => {
      try {
        trying.value = true;
        tryResponse.value = null;
        tryStatus.value = null;

        const headers = {
          'X-API-Key': props.apiKey || '',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        };

        const hasBody = !['GET', 'HEAD'].includes(props.endpoint.method);
        const options = {
          method: props.endpoint.method,
          headers,
        };

        if (hasBody) {
          options.body = JSON.stringify(tryValues.value);
        }

        const response = await fetch(fullUrl.value, options);
        tryStatus.value = response.status;
        const data = await response.json().catch(() => ({ success: false, message: isRTL.value ? 'استجابة غير نصية من الخادم' : 'Invalid response from server' }));
        tryResponse.value = data;
      } catch (err) {
        tryResponse.value = { success: false, message: err.message || (isRTL.value ? 'فشل الاتصال بالخادم' : 'Failed to connect to server') };
      } finally {
        trying.value = false;
      }
    };

    watch(() => props.endpoint, () => resetValues(), { immediate: true });

    return {
      isRTL,
      activeTab,
      tabs,
      activeCodeLang,
      codeLanguages,
      copied,
      trying,
      tryResponse,
      tryStatus,
      tryValues,
      methodClass,
      effectiveApiKey,
      fullUrl,
      endpointTitle,
      endpointDescription,
      endpointBadge,
      endpointNote,
      currentLanguageTitle,
      currentCodeSnippet,
      responseExample,
      tryResponseFormatted,
      resetValues,
      copyActiveCode,
      tryEndpoint,
    };
  },
};
</script>
