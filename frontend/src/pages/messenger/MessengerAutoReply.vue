<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500 max-w-5xl mx-auto">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('messenger_page.auto_reply') }}</h2>
          <p class="text-gray-500 text-sm">أضبط الرد التلقائي والذكاء الاصطناعي لمحادثات Messenger.</p>
        </div>
      </div>

      <div v-if="!pageId" class="p-6 bg-amber-50 border border-amber-100 rounded-2xl text-amber-800 text-xs font-bold">
        لا توجد صفحة متصلة. <router-link to="/messenger/connect" class="underline">ربط صفحة</router-link>
      </div>

      <div v-else class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="w-2.5 h-2.5 rounded-full" :class="form.is_enabled ? 'bg-emerald-500' : 'bg-gray-400'"></span>
            <span class="text-sm font-black text-gray-900">{{ form.is_enabled ? 'الرد التلقائي مفعّل' : 'الرد التلقائي متوقف' }}</span>
          </div>
          <button
            type="button"
            @click="form.is_enabled = !form.is_enabled"
            class="relative inline-flex h-7 w-14 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200"
            :class="form.is_enabled ? 'bg-emerald-500' : 'bg-gray-300'"
          >
            <span class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-md transition duration-200" :class="form.is_enabled ? '-translate-x-7' : 'translate-x-0'" />
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <button type="button" @click="form.mode = 'ai'" class="p-6 rounded-2xl border text-right transition-all" :class="form.mode === 'ai' ? 'bg-indigo-900 text-white border-indigo-700' : 'bg-white border-gray-100 text-gray-800 hover:border-gray-200'">
            <h3 class="text-base font-black">الذكاء الاصطناعي</h3>
            <p class="text-xs opacity-80 mt-1">يرد AI حسب سياق المحادثة وتعليماتك.</p>
          </button>
          <button type="button" @click="form.mode = 'rules'" class="p-6 rounded-2xl border text-right transition-all" :class="form.mode === 'rules' ? 'bg-indigo-900 text-white border-indigo-700' : 'bg-white border-gray-100 text-gray-800 hover:border-gray-200'">
            <h3 class="text-base font-black">كلمات مفتاحية</h3>
            <p class="text-xs opacity-80 mt-1">ردود ثابتة عند تطابق كلمات معينة.</p>
          </button>
        </div>

        <div class="space-y-4">
          <label class="block text-xs font-black text-gray-700 uppercase">رسالة الترحيب الأولى</label>
          <textarea v-model="form.welcome_message" rows="3" class="w-full bg-gray-50 border border-gray-100 rounded-2xl p-4 text-xs font-medium text-gray-900 outline-none focus:border-[#0084FF] resize-none" placeholder="أهلاً بك! كيف يمكنني مساعدتك؟"></textarea>
        </div>

        <div v-if="form.mode === 'ai'" class="space-y-4">
          <label class="block text-xs font-black text-gray-700 uppercase">تعليمات AI (System Prompt)</label>
          <textarea v-model="form.ai_system_prompt" rows="5" class="w-full bg-gray-50 border border-gray-100 rounded-2xl p-4 text-xs font-medium text-gray-900 outline-none focus:border-[#0084FF] resize-none" placeholder="أنت مساعد ذكي لخدمة العملاء على Messenger..."></textarea>

          <label class="block text-xs font-black text-gray-700 uppercase">معلومات النشاط / المعرفة</label>
          <textarea v-model="form.ai_business_info" rows="4" class="w-full bg-gray-50 border border-gray-100 rounded-2xl p-4 text-xs font-medium text-gray-900 outline-none focus:border-[#0084FF] resize-none" placeholder="أدخل تفاصيل المنتجات والأسعار..."></textarea>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-[10px] font-bold text-gray-400 mb-1">نبرة الرد</label>
              <select v-model="form.ai_tone" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:border-[#0084FF]">
                <option value="friendly">ودود</option>
                <option value="formal">رسمي</option>
                <option value="direct">مختصر</option>
              </select>
            </div>
            <div>
              <label class="block text-[10px] font-bold text-gray-400 mb-1">اللغة</label>
              <select v-model="form.ai_language" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:border-[#0084FF]">
                <option value="ar">العربية</option>
                <option value="en">English</option>
                <option value="auto">تلقائي</option>
              </select>
            </div>
            <div>
              <label class="block text-[10px] font-bold text-gray-400 mb-1">نموذج AI</label>
              <input v-model="form.ai_model" type="text" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:border-[#0084FF]" placeholder="مثال: gemini-2.0-flash" />
            </div>
          </div>

          <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 space-y-3">
            <label class="block text-xs font-black text-gray-700 uppercase">اختبار AI</label>
            <input v-model="testMessage" @keydown.enter="runTest" type="text" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:border-[#0084FF]" placeholder="اكتب رسالة تجريبية..." />
            <button type="button" @click="runTest" :disabled="isTesting || !testMessage" class="px-4 py-2 bg-indigo-600 text-white text-xs font-black rounded-xl">{{ isTesting ? 'جاري التوليد...' : 'اختبار' }}</button>
            <div v-if="testResult" class="p-3 bg-indigo-50 rounded-xl text-xs text-indigo-900 whitespace-pre-wrap">{{ testResult.reply }}</div>
          </div>
        </div>

        <div v-else class="space-y-4">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-black text-gray-700 uppercase">قواعد الكلمات المفتاحية</h4>
            <button type="button" @click="addRule" class="px-3 py-1.5 bg-[#0084FF] text-white text-xs font-bold rounded-xl">+ إضافة قاعدة</button>
          </div>
          <div v-for="(rule, idx) in form.rules" :key="idx" class="p-4 bg-gray-50 rounded-2xl border border-gray-100 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <input v-model="rule.keywords_input" type="text" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:border-[#0084FF]" placeholder="سعر, اسعار, بكم" />
              <select v-model="rule.match_type" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:border-[#0084FF]">
                <option value="contains">يحتوي</option>
                <option value="exact">تطابق تام</option>
                <option value="starts_with">يبدأ بـ</option>
              </select>
            </div>
            <textarea v-model="rule.reply" rows="2" class="w-full bg-white border border-gray-200 rounded-xl p-3 text-xs font-medium outline-none focus:border-[#0084FF] resize-none" placeholder="نص الرد..."></textarea>
            <button type="button" @click="removeRule(idx)" class="text-xs font-bold text-red-600">حذف القاعدة</button>
          </div>
        </div>

        <div class="pt-4 border-t border-gray-100 flex justify-end">
          <button type="button" @click="save" :disabled="isSaving" class="px-8 py-3 bg-[#0084FF] hover:bg-[#0073E6] text-white text-xs font-black rounded-2xl transition-all shadow-md">
            {{ isSaving ? 'جاري الحفظ...' : 'حفظ الإعدادات' }}
          </button>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, onMounted } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import api from '@/services/api';
import { t } from '@/i18n';

export default {
  name: 'MessengerAutoReply',
  components: { DashboardLayout },
  setup() {
    const pages = ref([]);
    const pageId = ref(null);
    const isSaving = ref(false);
    const isTesting = ref(false);
    const testMessage = ref('');
    const testResult = ref(null);
    const form = ref({
      is_enabled: false,
      mode: 'ai',
      welcome_message: '',
      ai_system_prompt: '',
      ai_business_info: '',
      ai_tone: 'friendly',
      ai_language: 'ar',
      ai_model: '',
      rules: [],
    });

    const loadPages = async () => {
      try {
        const res = await api.get('/messenger/status');
        if (res.success) pages.value = res.data.pages || [];
        if (pages.value.length) pageId.value = pages.value[0].id;
        if (pageId.value) loadSettings();
      } catch (err) {
        console.error('Failed to load pages:', err);
      }
    };

    const loadSettings = async () => {
      try {
        const res = await api.get('/messenger/auto-reply', { params: { page_id: pageId.value } });
        if (res.success && res.data.settings) {
          const s = res.data.settings;
          form.value = {
            is_enabled: s.is_enabled,
            mode: s.mode,
            welcome_message: s.welcome_message || '',
            ai_system_prompt: s.ai_system_prompt || '',
            ai_business_info: s.ai_business_info || '',
            ai_tone: s.ai_tone || 'friendly',
            ai_language: s.ai_language || 'ar',
            ai_model: s.ai_model || '',
            rules: (s.rules || []).map(r => ({ ...r, keywords_input: (r.keywords || []).join(', ') })),
          };
        }
      } catch (err) {
        console.error('Failed to load auto reply settings:', err);
      }
    };

    const addRule = () => form.value.rules.push({ keywords: [], keywords_input: '', match_type: 'contains', reply: '' });
    const removeRule = (idx) => form.value.rules.splice(idx, 1);

    const runTest = async () => {
      try {
        isTesting.value = true;
        const res = await api.post('/messenger/auto-reply/test-ai', {
          message: testMessage.value,
          ai_system_prompt: form.value.ai_system_prompt,
          ai_business_info: form.value.ai_business_info,
          ai_tone: form.value.ai_tone,
          ai_language: form.value.ai_language,
          ai_model: form.value.ai_model,
        });
        if (res.success) testResult.value = res.data;
      } catch (err) {
        console.error('AI test failed:', err);
      } finally {
        isTesting.value = false;
      }
    };

    const save = async () => {
      try {
        isSaving.value = true;
        const payload = { ...form.value, page_id: pageId.value };
        payload.rules = form.value.rules.map(r => ({
          keywords: r.keywords_input.split(',').map(k => k.trim()).filter(Boolean),
          match_type: r.match_type,
          reply: r.reply,
        }));
        await api.post('/messenger/auto-reply', payload);
        alert('تم الحفظ بنجاح');
      } catch (err) {
        console.error('Save failed:', err);
        alert(err.data?.message || 'فشل الحفظ.');
      } finally {
        isSaving.value = false;
      }
    };

    onMounted(loadPages);

    return { pages, pageId, form, isSaving, isTesting, testMessage, testResult, addRule, removeRule, runTest, save, t };
  },
};
</script>
