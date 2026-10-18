<template>
  <DashboardLayout>
    <div class="space-y-8 animate-in fade-in duration-500 max-w-6xl mx-auto pb-24">
      <!-- Top Header & Master Switch -->
      <div class="bg-white border border-slate-100 rounded-3xl p-6 sm:p-8 shadow-xs relative overflow-hidden">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
          <div class="space-y-2">
            <div class="flex items-center gap-3 flex-wrap">
              <div class="w-10 h-10 rounded-2xl bg-[#229ED9]/10 text-[#229ED9] flex items-center justify-center font-black">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.75-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
              </div>
              <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                AI مكانك - Telegram
              </h1>
              <span
                v-if="form.is_enabled"
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-emerald-50 text-emerald-700 border border-emerald-200/60 shadow-2xs"
              >
                <span class="w-2 h-2 rounded-full bg-[#00C853] animate-pulse"></span>
                مفعّل ويعمل الآن
              </span>
              <span
                v-else
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500 border border-slate-200"
              >
                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                متوقف حالياً
              </span>
            </div>
            <p class="text-slate-500 text-xs sm:text-sm font-medium leading-relaxed max-w-2xl">
              قم بتهيئة الذكاء الاصطناعي للرد على عملائك ومستخدمي بوت تيليجرام على مدار الساعة بدقة واحترافية وبطريقة بشرية ذكية.
            </p>
          </div>

          <!-- Master Action Switch + Stats -->
          <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 border-t lg:border-t-0 pt-4 lg:pt-0 border-slate-100">
            <div class="flex items-center gap-3 bg-slate-50 border border-slate-200/60 rounded-2xl px-4 py-2.5">
              <div class="flex flex-col">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">إجمالي الردود</span>
                <span class="text-base font-black text-slate-900 font-mono">{{ Number(stats.total_replies || 0).toLocaleString() }}</span>
              </div>
            </div>

            <!-- Master Toggle -->
            <div class="flex items-center gap-3 bg-slate-50/80 border border-slate-200/60 rounded-2xl p-2 sm:px-4 sm:py-2.5">
              <div class="text-right">
                <span class="text-xs font-black text-slate-800 block">حالة الرد التلقائي</span>
                <span class="text-[10px] font-bold" :class="form.is_enabled ? 'text-emerald-600' : 'text-slate-400'">
                  {{ form.is_enabled ? 'مفعّل ونشط' : 'متوقف' }}
                </span>
              </div>
              <button
                type="button"
                @click="toggleEnabled"
                class="relative inline-flex h-7 w-14 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                :class="form.is_enabled ? 'bg-[#00C853]' : 'bg-slate-300'"
              >
                <span
                  class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out"
                  :class="form.is_enabled ? '-translate-x-7' : 'translate-x-0'"
                />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Session Selector if multiple -->
      <div v-if="sessions.length > 1" class="flex items-center gap-3 bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs">
        <label class="text-xs font-black text-gray-700 whitespace-nowrap">اختر البوت المراد ضبطه:</label>
        <select
          v-model="selectedSessionId"
          @change="loadSettings"
          class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 text-xs font-bold text-gray-800 outline-none focus:border-[#229ED9]"
        >
          <option v-for="s in sessions" :key="s.id" :value="s.id">
            {{ s.first_name || s.device_name }} (@{{ s.bot_username }})
          </option>
        </select>
      </div>

      <!-- Mode Selector -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <button
          type="button"
          @click="form.mode = 'ai'"
          class="p-6 rounded-3xl border text-right transition-all flex flex-col justify-between gap-4 relative overflow-hidden group"
          :class="form.mode === 'ai' ? 'bg-gradient-to-br from-indigo-900 to-indigo-950 text-white border-indigo-700 shadow-xl shadow-indigo-950/10' : 'bg-white border-gray-100 text-gray-800 hover:border-gray-200'"
        >
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <span class="w-9 h-9 rounded-xl flex items-center justify-center font-black" :class="form.mode === 'ai' ? 'bg-white/10 text-white' : 'bg-indigo-50 text-indigo-600'">
                ✦
              </span>
              <span v-if="form.mode === 'ai'" class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-indigo-500/30 text-indigo-200 border border-indigo-400/30">
                الوضع النشط
              </span>
            </div>
            <h3 class="text-base font-black" :class="form.mode === 'ai' ? 'text-white' : 'text-gray-900'">
              الذكاء الاصطناعي الذكي (AI Agent)
            </h3>
            <p class="text-xs leading-relaxed" :class="form.mode === 'ai' ? 'text-indigo-200' : 'text-gray-500'">
              يفهم سياق الحديث، يجيب على الأسئلة المعقدة، يقترح المنتجات ويفاوض العملاء بناءً على تعليماتك.
            </p>
          </div>
        </button>

        <button
          type="button"
          @click="form.mode = 'rules'"
          class="p-6 rounded-3xl border text-right transition-all flex flex-col justify-between gap-4 relative overflow-hidden group"
          :class="form.mode === 'rules' ? 'bg-gradient-to-br from-indigo-900 to-indigo-950 text-white border-indigo-700 shadow-xl shadow-indigo-950/10' : 'bg-white border-gray-100 text-gray-800 hover:border-gray-200'"
        >
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <span class="w-9 h-9 rounded-xl flex items-center justify-center font-black" :class="form.mode === 'rules' ? 'bg-white/10 text-white' : 'bg-sky-50 text-[#229ED9]'">
                #
              </span>
              <span v-if="form.mode === 'rules'" class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-indigo-500/30 text-indigo-200 border border-indigo-400/30">
                الوضع النشط
              </span>
            </div>
            <h3 class="text-base font-black" :class="form.mode === 'rules' ? 'text-white' : 'text-gray-900'">
              قواعد الكلمات المفتاحية (Keywords)
            </h3>
            <p class="text-xs leading-relaxed" :class="form.mode === 'rules' ? 'text-indigo-200' : 'text-gray-500'">
              ردود فورية ثابتة ومحددة بدقة عند كتابة كلمات أو عبارات معينة مثل الأسعار ومواعيد العمل.
            </p>
          </div>
        </button>
      </div>

      <!-- Settings Body -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-6">
            
            <!-- Welcome Message (/start) -->
            <div class="space-y-2">
              <label class="block text-xs font-black text-gray-700 uppercase tracking-wider">
                رسالة الترحيب الأولى (عند إرسال /start)
              </label>
              <textarea
                v-model="form.welcome_message"
                rows="3"
                placeholder="أهلاً بك! أنا المساعد الذكي لبوت تيليجرام. كيف يمكنني خدمتك اليوم؟"
                class="w-full bg-gray-50 border border-gray-200 rounded-2xl p-4 text-xs font-medium text-gray-900 focus:bg-white focus:border-[#229ED9] outline-none transition-all resize-none"
              />
            </div>

            <!-- AI Mode Configuration -->
            <template v-if="form.mode === 'ai'">
              <!-- AI Persona & System Prompt -->
              <div class="space-y-2">
                <label class="block text-xs font-black text-gray-700 uppercase tracking-wider">
                  تعليمات وشخصية الذكاء الاصطناعي (System Prompt)
                </label>
                <textarea
                  v-model="form.ai_system_prompt"
                  rows="4"
                  placeholder="أنت ممثل خدمة عملاء محترف لمتجرنا. تتحدث بلباقة وترحب بالعميل وتجيب على استفساراته باختصار ووضوح..."
                  class="w-full bg-gray-50 border border-gray-200 rounded-2xl p-4 text-xs font-medium text-gray-900 focus:bg-white focus:border-[#229ED9] outline-none transition-all resize-none"
                />
              </div>

              <!-- Business Info / Knowledge Base -->
              <div class="space-y-2">
                <label class="block text-xs font-black text-gray-700 uppercase tracking-wider">
                  معلومات الشركة والأسعار وقاعدة المعرفة (Knowledge Base)
                </label>
                <textarea
                  v-model="form.ai_business_info"
                  rows="5"
                  placeholder="أدخل هنا تفاصيل المنتجات والأسعار، سياسة الاسترجاع، أوقات العمل، ورابط المتجر..."
                  class="w-full bg-gray-50 border border-gray-200 rounded-2xl p-4 text-xs font-medium text-gray-900 focus:bg-white focus:border-[#229ED9] outline-none transition-all resize-none"
                />
              </div>

              <!-- Tone & Language -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-black text-gray-700 uppercase mb-1.5">نبرة الحديث (Tone)</label>
                  <select
                    v-model="form.ai_tone"
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-bold text-gray-800 outline-none focus:border-[#229ED9]"
                  >
                    <option value="friendly">ودود ولبق (Friendly)</option>
                    <option value="formal">رسمي واحترافي (Formal)</option>
                    <option value="enthusiastic">حماسي ومشجع (Enthusiastic)</option>
                    <option value="direct">مختصر ومباشر (Direct)</option>
                  </select>
                </div>

                <div>
                  <label class="block text-xs font-black text-gray-700 uppercase mb-1.5">لغة الرد الأساسية</label>
                  <select
                    v-model="form.ai_language"
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-bold text-gray-800 outline-none focus:border-[#229ED9]"
                  >
                    <option value="ar">العربية (Arabic)</option>
                    <option value="en">English</option>
                    <option value="auto">تلقائي حسب لغة العميل</option>
                  </select>
                </div>
              </div>
            </template>

            <!-- Rules Mode Configuration -->
            <template v-else>
              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <h4 class="text-xs font-black text-gray-700 uppercase">قائمة الكلمات المفتاحية والردود</h4>
                  <button
                    type="button"
                    @click="addRule"
                    class="px-3 py-1.5 bg-[#229ED9] text-white text-xs font-bold rounded-xl flex items-center gap-1 shadow-2xs hover:bg-[#1c8ec4]"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    <span>إضافة قاعدة</span>
                  </button>
                </div>

                <div v-if="form.rules.length === 0" class="p-8 text-center bg-gray-50 rounded-2xl border border-gray-200/80 text-gray-400 text-xs font-bold">
                  لم تقم بإضافة أي كلمات مفتاحية بعد. اضغط "إضافة قاعدة" للبدء.
                </div>

                <div v-for="(rule, index) in form.rules" :key="index" class="p-4 bg-gray-50/80 rounded-2xl border border-gray-200/80 space-y-3 relative">
                  <button
                    type="button"
                    @click="removeRule(index)"
                    class="absolute top-3 left-3 text-red-500 hover:text-red-700 p-1"
                    title="حذف"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                  </button>

                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pr-2">
                    <div>
                      <label class="block text-[10px] font-bold text-gray-400 mb-1">الكلمات المفتاحية (مفصولة بفواصل)</label>
                      <input
                        v-model="rule.keywords_input"
                        type="text"
                        placeholder="سعر, اسعار, بكم, تكلفة"
                        class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold text-gray-800 outline-none focus:border-[#229ED9]"
                      />
                    </div>
                    <div>
                      <label class="block text-[10px] font-bold text-gray-400 mb-1">نوع التطابق</label>
                      <select
                        v-model="rule.match_type"
                        class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold text-gray-800 outline-none focus:border-[#229ED9]"
                      >
                        <option value="contains">يحتوي على الكلمة (Contains)</option>
                        <option value="exact">تطابق تام (Exact)</option>
                        <option value="starts_with">يبدأ بـ (Starts with)</option>
                      </select>
                    </div>
                  </div>

                  <div>
                    <label class="block text-[10px] font-bold text-gray-400 mb-1">نص الرد التلقائي</label>
                    <textarea
                      v-model="rule.reply"
                      rows="2"
                      placeholder="أهلاً بك! أسعار باقاتنا تبدأ من..."
                      class="w-full bg-white border border-gray-200 rounded-xl p-3 text-xs font-medium text-gray-900 outline-none focus:border-[#229ED9] resize-none"
                    />
                  </div>
                </div>
              </div>
            </template>

            <!-- Save Settings Button -->
            <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
              <div v-if="saveSuccess" class="text-xs font-bold text-emerald-600 flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                <span>تم حفظ الإعدادات بنجاح!</span>
              </div>
              <div v-else></div>

              <button
                type="button"
                @click="saveSettings"
                :disabled="isSaving"
                class="px-8 py-3 bg-[#229ED9] hover:bg-[#1c8ec4] text-white text-xs font-black rounded-xl transition-all shadow-md active:scale-95 flex items-center gap-2"
              >
                <svg v-if="isSaving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ isSaving ? 'جاري الحفظ...' : 'حفظ الإعدادات' }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Simulator & Activity Logs Side -->
        <div class="space-y-6">
          <!-- AI Test Simulator -->
          <div v-if="form.mode === 'ai'" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 space-y-4">
            <h3 class="text-xs font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-indigo-600 animate-pulse"></span>
              تجربة الرد بالذكاء الاصطناعي
            </h3>
            <p class="text-[11px] text-gray-400">اختبر ردود المساعد قبل تطبيقها في المحادثات المباشرة.</p>

            <div class="space-y-3">
              <input
                v-model="testMessage"
                type="text"
                placeholder="اكتب سؤالاً للتجربة... مثال: ما هي الأسعار؟"
                class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-900 outline-none focus:border-[#229ED9]"
                @keydown.enter="runAiTest"
              />
              <button
                type="button"
                @click="runAiTest"
                :disabled="isTesting || !testMessage"
                class="w-full py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl border border-indigo-200/60 transition-all flex items-center justify-center gap-2"
              >
                <svg v-if="isTesting" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span>{{ isTesting ? 'جاري التوليد...' : 'اختبار الرد' }}</span>
              </button>
            </div>

            <!-- Test Output Bubble -->
            <div v-if="testResult" class="p-3.5 bg-indigo-50/70 border border-indigo-100 rounded-2xl text-xs text-indigo-950 space-y-1">
              <div class="text-[10px] font-black text-indigo-600">رد المساعد الذكي:</div>
              <p class="leading-relaxed font-medium whitespace-pre-wrap">{{ testResult.reply }}</p>
            </div>
          </div>

          <!-- Recent Auto Reply Logs -->
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 space-y-4">
            <h3 class="text-xs font-black text-gray-900 uppercase tracking-wider">سجل الردود الحديثة</h3>
            
            <div v-if="logs.length === 0" class="text-center py-6 text-gray-400 text-xs font-bold">
              لا توجد ردود مسجلة بعد.
            </div>

            <div v-else class="space-y-3 max-h-96 overflow-y-auto">
              <div
                v-for="log in logs"
                :key="log.id"
                class="p-3 bg-gray-50 rounded-xl border border-gray-100 text-xs space-y-1"
              >
                <div class="flex items-center justify-between text-[10px] text-gray-400 font-bold">
                  <span>Chat ID: {{ log.chat_id }}</span>
                  <span>{{ formatTime(log.created_at) }}</span>
                </div>
                <div class="text-gray-900 font-bold">س: {{ log.incoming_text }}</div>
                <div class="text-indigo-600 font-medium">ج: {{ log.reply_text }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, onMounted } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import api from '@/services/api';

export default {
  name: 'TelegramAutoReply',
  components: { DashboardLayout },
  setup() {
    const sessions = ref([]);
    const selectedSessionId = ref(null);
    const isSaving = ref(false);
    const saveSuccess = ref(false);
    const stats = ref({ total_replies: 0 });
    const logs = ref([]);
    const testMessage = ref('');
    const testResult = ref(null);
    const isTesting = ref(false);

    const form = ref({
      is_enabled: false,
      mode: 'ai',
      welcome_message: '',
      ai_system_prompt: '',
      ai_business_info: '',
      ai_tone: 'friendly',
      ai_language: 'ar',
      rules: [],
    });

    const loadSessions = async () => {
      try {
        const res = await api.get('/telegram/sessions');
        if (res.success) {
          sessions.value = res.data || [];
          if (sessions.value.length > 0) {
            selectedSessionId.value = sessions.value[0].id;
            await loadSettings();
            await loadLogs();
          }
        }
      } catch (err) {
        console.error('Failed to load sessions:', err);
      }
    };

    const loadSettings = async () => {
      if (!selectedSessionId.value) return;
      try {
        const res = await api.get(`/telegram/auto-reply?session_id=${selectedSessionId.value}`);
        if (res.success && res.data?.settings) {
          const s = res.data.settings;
          form.value.is_enabled = s.is_enabled;
          form.value.mode = s.mode || 'ai';
          form.value.welcome_message = s.welcome_message || '';
          form.value.ai_system_prompt = s.ai_system_prompt || '';
          form.value.ai_business_info = s.ai_business_info || '';
          form.value.ai_tone = s.ai_tone || 'friendly';
          form.value.ai_language = s.ai_language || 'ar';
          form.value.rules = (s.rules || []).map(r => ({
            ...r,
            keywords_input: Array.isArray(r.keywords) ? r.keywords.join(', ') : '',
          }));
          stats.value = res.data.stats || { total_replies: 0 };
        }
      } catch (err) {
        console.error('Failed to load settings:', err);
      }
    };

    const loadLogs = async () => {
      if (!selectedSessionId.value) return;
      try {
        const res = await api.get(`/telegram/auto-reply/logs?session_id=${selectedSessionId.value}`);
        if (res.success) {
          logs.value = res.data?.data || [];
        }
      } catch (err) {
        console.error('Failed to load logs:', err);
      }
    };

    const toggleEnabled = () => {
      form.value.is_enabled = !form.value.is_enabled;
      saveSettings();
    };

    const addRule = () => {
      form.value.rules.push({
        keywords_input: '',
        match_type: 'contains',
        reply: '',
      });
    };

    const removeRule = (index) => {
      form.value.rules.splice(index, 1);
    };

    const saveSettings = async () => {
      if (!selectedSessionId.value) return;
      try {
        isSaving.value = true;
        saveSuccess.value = false;

        const payload = {
          session_id: selectedSessionId.value,
          is_enabled: form.value.is_enabled,
          mode: form.value.mode,
          welcome_message: form.value.welcome_message,
          ai_system_prompt: form.value.ai_system_prompt,
          ai_business_info: form.value.ai_business_info,
          ai_tone: form.value.ai_tone,
          ai_language: form.value.ai_language,
          rules: form.value.rules.map(r => ({
            keywords: r.keywords_input.split(',').map(k => k.trim()).filter(Boolean),
            match_type: r.match_type,
            reply: r.reply,
          })),
        };

        const res = await api.post('/telegram/auto-reply', payload);
        if (res.success) {
          saveSuccess.value = true;
          setTimeout(() => { saveSuccess.value = false; }, 3000);
        }
      } catch (err) {
        console.error('Failed to save settings:', err);
      } finally {
        isSaving.value = false;
      }
    };

    const runAiTest = async () => {
      if (!testMessage.value) return;
      try {
        isTesting.value = true;
        const res = await api.post('/telegram/auto-reply/test-ai', {
          message: testMessage.value,
          ai_system_prompt: form.value.ai_system_prompt,
          ai_business_info: form.value.ai_business_info,
          ai_tone: form.value.ai_tone,
          ai_language: form.value.ai_language,
        });

        if (res.success) {
          testResult.value = res.data;
        }
      } catch (err) {
        console.error('AI test failed:', err);
      } finally {
        isTesting.value = false;
      }
    };

    const formatTime = (dateStr) => {
      if (!dateStr) return '';
      return new Date(dateStr).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
    };

    onMounted(() => {
      loadSessions();
    });

    return {
      sessions,
      selectedSessionId,
      form,
      stats,
      logs,
      isSaving,
      saveSuccess,
      testMessage,
      testResult,
      isTesting,
      loadSettings,
      toggleEnabled,
      addRule,
      removeRule,
      saveSettings,
      runAiTest,
      formatTime,
    };
  },
};
</script>
