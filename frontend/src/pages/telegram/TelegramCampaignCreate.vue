<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="w-2.5 h-2.5 rounded-full bg-[#229ED9]"></span>
            <h2 class="text-2xl font-bold text-gray-900 tracking-tight">إنشاء حملة Telegram جديدة</h2>
          </div>
          <p class="text-gray-500 text-sm">حدد مستلميك وصياغة رسالتك وانطلق بحملتك الترويجية في خطوات بسيطة.</p>
        </div>
        <router-link to="/telegram/campaigns" class="text-xs font-bold text-gray-500 hover:text-[#229ED9] transition-colors">
          ← العودة للحملات
        </router-link>
      </div>

      <!-- Stepper Header -->
      <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-4">
        <div class="flex items-center justify-between gap-2 overflow-x-auto">
          <button
            v-for="(step, index) in steps"
            :key="step.key"
            @click="setStep(index)"
            class="flex items-center gap-2 px-4 py-2 rounded-2xl text-xs font-bold whitespace-nowrap transition-all"
            :class="currentStep === index ? 'bg-[#229ED9] text-white shadow-sm' : (currentStep > index ? 'bg-sky-50 text-[#229ED9]' : 'text-gray-400')"
          >
            <span
              class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-black"
              :class="currentStep === index ? 'bg-white text-[#229ED9]' : (currentStep > index ? 'bg-[#229ED9] text-white' : 'bg-gray-100 text-gray-500')"
            >
              {{ currentStep > index ? '✓' : index + 1 }}
            </span>
            <span>{{ step.label }}</span>
          </button>
        </div>
      </div>

      <!-- Step 0: Campaign Details & Bot Selection -->
      <div v-if="currentStep === 0" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-6">
        <h3 class="text-base font-black text-gray-900">تفاصيل الحملة والبوت المرسل</h3>
        
        <div class="space-y-2">
          <label class="block text-xs font-black text-gray-700 uppercase">اسم الحملة <span class="text-red-500">*</span></label>
          <input
            v-model="form.name"
            type="text"
            placeholder="مثال: عروض نهاية الأسبوع للمشتركين"
            class="w-full bg-gray-50 border border-gray-200 rounded-2xl p-4 text-xs font-bold text-gray-900 focus:bg-white focus:border-[#229ED9] outline-none"
            required
          />
        </div>

        <div class="space-y-3">
          <label class="block text-xs font-black text-gray-700 uppercase">اختر بوت تيليجرام المرسل <span class="text-red-500">*</span></label>
          <div v-if="sessions.length === 0" class="p-6 bg-amber-50 rounded-2xl border border-amber-200/80 text-center space-y-3">
            <p class="text-xs font-bold text-amber-900">لا يوجد لديك أي بوت تيليجرام متصل بعد.</p>
            <router-link to="/telegram/devices" class="inline-block px-4 py-2 bg-[#229ED9] text-white text-xs font-bold rounded-xl">ربط بوت جديد</router-link>
          </div>
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div
              v-for="s in sessions"
              :key="s.id"
              @click="form.session_id = s.id"
              :class="[
                'p-4 rounded-2xl border-2 cursor-pointer transition-all flex items-center justify-between',
                form.session_id === s.id ? 'border-[#229ED9] bg-sky-50/50 shadow-xs' : 'border-gray-100 hover:border-sky-200 bg-white'
              ]"
            >
              <div>
                <h4 class="text-xs font-black text-gray-900">{{ s.first_name || s.device_name }}</h4>
                <p class="text-[11px] text-[#229ED9] font-bold font-mono" dir="ltr">@{{ s.bot_username }}</p>
              </div>
              <span :class="['w-2.5 h-2.5 rounded-full', s.status === 'connected' ? 'bg-emerald-500' : 'bg-gray-300']"></span>
            </div>
          </div>
        </div>

        <div class="pt-4 flex justify-end">
          <button
            type="button"
            @click="currentStep = 1"
            :disabled="!form.name || !form.session_id"
            class="px-8 py-3 bg-[#229ED9] hover:bg-[#1c8ec4] disabled:opacity-50 text-white text-xs font-black rounded-xl transition-all"
          >
            التالي: تصميم الرسالة ←
          </button>
        </div>
      </div>

      <!-- Step 1: Message Composition -->
      <div v-if="currentStep === 1" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-6">
        <h3 class="text-base font-black text-gray-900">صياغة محتوى الحملة</h3>

        <!-- Type selector -->
        <div class="grid grid-cols-3 gap-3">
          <button
            type="button"
            @click="form.type = 'text'"
            :class="['py-2.5 rounded-xl text-xs font-bold border transition-all', form.type === 'text' ? 'bg-[#229ED9]/10 border-[#229ED9] text-[#229ED9]' : 'bg-gray-50 border-gray-200 text-gray-600']"
          >
            رسالة نصية
          </button>
          <button
            type="button"
            @click="form.type = 'photo'"
            :class="['py-2.5 rounded-xl text-xs font-bold border transition-all', form.type === 'photo' ? 'bg-[#229ED9]/10 border-[#229ED9] text-[#229ED9]' : 'bg-gray-50 border-gray-200 text-gray-600']"
          >
            صورة ومرفق
          </button>
          <button
            type="button"
            @click="form.type = 'document'"
            :class="['py-2.5 rounded-xl text-xs font-bold border transition-all', form.type === 'document' ? 'bg-[#229ED9]/10 border-[#229ED9] text-[#229ED9]' : 'bg-gray-50 border-gray-200 text-gray-600']"
          >
            ملف / مستند
          </button>
        </div>

        <!-- Media uploader -->
        <div v-if="form.type !== 'text'" class="p-4 bg-gray-50 rounded-2xl border border-gray-200 space-y-2">
          <label class="block text-xs font-black text-gray-700 uppercase">رابط الميديا أو رفع ملف</label>
          <div class="flex items-center gap-3">
            <input type="file" ref="campFileInput" @change="uploadCampMedia" class="hidden" />
            <button
              type="button"
              @click="$refs.campFileInput.click()"
              class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-bold rounded-xl"
            >
              {{ isUploading ? 'جاري الرفع...' : 'رفع من جهازك' }}
            </button>
            <input
              v-model="form.media_url"
              type="url"
              placeholder="https://example.com/image.jpg"
              class="flex-1 bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold text-gray-800 outline-none"
              dir="ltr"
            />
          </div>
        </div>

        <!-- Textarea -->
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-black text-gray-700 uppercase">نص الرسالة / الكابشن <span class="text-red-500">*</span></label>
            <span class="text-[10px] text-gray-400 font-bold">يمكنك استخدام {name} لإدراج اسم العميل تلقائياً</span>
          </div>
          <textarea
            v-model="form.message"
            rows="6"
            placeholder="أهلاً بك يا {name}، يسعدنا إعلامك بأحدث عروضنا..."
            class="w-full bg-gray-50 border border-gray-200 rounded-2xl p-4 text-xs font-medium text-gray-900 focus:bg-white focus:border-[#229ED9] outline-none transition-all resize-none"
          />
        </div>

        <!-- Button Builder -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-4 bg-gray-50 rounded-2xl border border-gray-200">
          <div>
            <label class="block text-[10px] font-bold text-gray-400 mb-1">نص الزر التفاعلي (اختياري)</label>
            <input
              v-model="form.button_text"
              type="text"
              placeholder="مثال: زيارة العرض الآن"
              class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold outline-none"
            />
          </div>
          <div>
            <label class="block text-[10px] font-bold text-gray-400 mb-1">رابط الزر (URL)</label>
            <input
              v-model="form.button_url"
              type="url"
              placeholder="https://mywebsite.com/promo"
              class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold outline-none"
              dir="ltr"
            />
          </div>
        </div>

        <div class="pt-4 flex items-center justify-between border-t border-gray-100">
          <button type="button" @click="currentStep = 0" class="px-5 py-2.5 text-xs font-bold text-gray-600 hover:bg-gray-50 rounded-xl">← السابق</button>
          <button
            type="button"
            @click="currentStep = 2"
            :disabled="!form.message"
            class="px-8 py-3 bg-[#229ED9] hover:bg-[#1c8ec4] disabled:opacity-50 text-white text-xs font-black rounded-xl transition-all"
          >
            التالي: إضافة المستلمين ←
          </button>
        </div>
      </div>

      <!-- Step 2: Recipients -->
      <div v-if="currentStep === 2" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-6">
        <h3 class="text-base font-black text-gray-900">إضافة المستلمين (Chat IDs / @usernames)</h3>

        <div class="p-4 bg-sky-50/50 rounded-2xl border border-sky-100 space-y-3">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-black text-gray-700 uppercase">استيراد جهات اتصال من ملف vCard (.vcf)</label>
            <span class="text-[10px] text-gray-500 font-bold">اختياري</span>
          </div>
          <input type="file" ref="vcfInput" accept=".vcf,text/vcard,text/x-vcard" @change="handleVcfUpload" class="hidden" />
          <div class="flex flex-wrap items-center gap-3">
            <button
              type="button"
              @click="$refs.vcfInput.click()"
              class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-bold rounded-xl hover:border-[#229ED9] hover:text-[#229ED9] transition-colors"
            >
              رفع ملف جهات الاتصال (.vcf)
            </button>
            <span v-if="vcfImportStatus" class="text-[11px] font-bold text-[#229ED9]">{{ vcfImportStatus }}</span>
          </div>
          <p class="text-[10px] text-gray-500 font-bold leading-relaxed">
            سيتم استخراج أرقام الهواتف وأسماء جهات الاتصال تلقائياً. يمكنك استخدام <code class="bg-white px-1 rounded text-[#229ED9]">{name}</code> في نص الرسالة لتخصيصها باسم كل مستلم.
          </p>
        </div>

        <div class="space-y-3">
          <label class="block text-xs font-black text-gray-700 uppercase">
            قائمة المستلمين (مستلم واحد في كل سطر)
          </label>
          <textarea
            v-model="rawRecipientsText"
            rows="8"
            placeholder="123456789|Ahmed&#10;987654321&#10;@username1|Mona&#10;@username2"
            class="w-full bg-gray-50 border border-gray-200 rounded-2xl p-4 text-xs font-mono font-bold text-gray-900 focus:bg-white focus:border-[#229ED9] outline-none resize-none"
            dir="ltr"
          />
          <div class="flex items-center justify-between text-xs text-gray-500 font-bold">
            <span>تم التعرف على: <strong class="text-[#229ED9] font-black">{{ parsedRecipientsCount }}</strong> مستلم</span>
            <span class="text-[10px] text-gray-400">يمكنك كتابة الاسم بعد علامة | لتخصيص الرسالة.</span>
          </div>
        </div>

        <div class="pt-4 flex items-center justify-between border-t border-gray-100">
          <button type="button" @click="currentStep = 1" class="px-5 py-2.5 text-xs font-bold text-gray-600 hover:bg-gray-50 rounded-xl">← السابق</button>
          <button
            type="button"
            @click="currentStep = 3"
            :disabled="parsedRecipientsCount === 0"
            class="px-8 py-3 bg-[#229ED9] hover:bg-[#1c8ec4] disabled:opacity-50 text-white text-xs font-black rounded-xl transition-all"
          >
            التالي: مراجعة وإطلاق ←
          </button>
        </div>
      </div>

      <!-- Step 3: Review & Launch -->
      <div v-if="currentStep === 3" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-6">
        <h3 class="text-base font-black text-gray-900">مراجعة الحملة وإعدادات الإرسال</h3>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-5 bg-gray-50 rounded-2xl border border-gray-200/80 text-xs">
          <div>
            <span class="text-[10px] font-bold text-gray-400 block uppercase">اسم الحملة</span>
            <span class="font-bold text-gray-900">{{ form.name }}</span>
          </div>
          <div>
            <span class="text-[10px] font-bold text-gray-400 block uppercase">عدد المستلمين</span>
            <span class="font-bold text-[#229ED9]">{{ parsedRecipientsCount }} مستلم</span>
          </div>
          <div>
            <span class="text-[10px] font-bold text-gray-400 block uppercase">نوع الرسالة</span>
            <span class="font-bold text-gray-800">{{ form.type }}</span>
          </div>
        </div>

        <!-- Delay settings -->
        <div class="space-y-2">
          <label class="block text-xs font-black text-gray-700 uppercase">الفارق الزمني بين كل رسالة والأخرى (بالثواني)</label>
          <input
            v-model.number="form.delay_seconds"
            type="number"
            min="1"
            max="30"
            class="w-32 bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-bold text-gray-900 outline-none focus:border-[#229ED9]"
          />
          <p class="text-[10px] text-gray-400 font-bold">يوصى بـ 2-3 ثوانٍ لتجنب قيود سرعة الإرسال على Telegram.</p>
        </div>

        <div v-if="error" class="p-4 bg-red-50 border border-red-200 rounded-xl text-xs font-bold text-red-600">
          {{ error }}
        </div>

        <div class="pt-6 flex items-center justify-between border-t border-gray-100">
          <button type="button" @click="currentStep = 2" class="px-5 py-2.5 text-xs font-bold text-gray-600 hover:bg-gray-50 rounded-xl">← السابق</button>
          <div class="flex items-center gap-3">
            <button
              type="button"
              @click="submitCampaign(false)"
              :disabled="isSubmitting"
              class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs font-bold rounded-xl"
            >
              حفظ كمسودة
            </button>
            <button
              type="button"
              @click="submitCampaign(true)"
              :disabled="isSubmitting"
              class="px-8 py-3 bg-[#229ED9] hover:bg-[#1c8ec4] text-white text-xs font-black rounded-xl shadow-lg shadow-sky-100 active:scale-95 flex items-center gap-2"
            >
              <svg v-if="isSubmitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              <span>إطلاق الحملة الآن 🚀</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import api from '@/services/api';

export default {
  name: 'TelegramCampaignCreate',
  components: { DashboardLayout },
  setup() {
    const router = useRouter();
    const currentStep = ref(0);
    const sessions = ref([]);
    const rawRecipientsText = ref('');
    const vcfInput = ref(null);
    const vcfImportStatus = ref('');
    const isUploading = ref(false);
    const isSubmitting = ref(false);
    const error = ref('');

    const steps = [
      { key: 'details', label: '1. تفاصيل الحملة' },
      { key: 'message', label: '2. صياغة الرسالة' },
      { key: 'recipients', label: '3. المستلمون' },
      { key: 'launch', label: '4. مراجعة وإطلاق' },
    ];

    const form = ref({
      name: '',
      session_id: null,
      type: 'text',
      message: '',
      media_url: '',
      button_text: '',
      button_url: '',
      delay_seconds: 2,
    });

    const parsedRecipients = computed(() => {
      const lines = rawRecipientsText.value.split('\n');
      const list = [];
      const seen = new Set();
      for (const l of lines) {
        const item = l.trim();
        if (!item) continue;
        const [chatIdRaw, ...nameParts] = item.split('|');
        const chat_id = chatIdRaw.trim();
        if (!chat_id || seen.has(chat_id)) continue;
        seen.add(chat_id);
        const name = nameParts.join('|').trim() || null;
        list.push({ chat_id, name });
      }
      return list;
    });

    const parsedRecipientsCount = computed(() => parsedRecipients.value.length);

    const loadSessions = async () => {
      try {
        const res = await api.get('/telegram/sessions');
        if (res.success) {
          sessions.value = res.data || [];
          const connected = sessions.value.find(s => s.status === 'connected');
          if (connected) form.value.session_id = connected.id;
          else if (sessions.value.length > 0) form.value.session_id = sessions.value[0].id;
        }
      } catch (err) {
        console.error('Failed to load sessions:', err);
      }
    };

    const setStep = (index) => {
      if (index < currentStep.value) {
        currentStep.value = index;
      }
    };

    const readVcfFile = (file) => {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (e) => resolve(e.target.result);
        reader.onerror = (e) => reject(e);
        reader.readAsText(file);
      });
    };

    const cleanVcfPhone = (value) => {
      let phone = String(value).trim();
      // vCard 4.0 may use a tel: URI prefix
      if (phone.toLowerCase().startsWith('tel:')) {
        phone = phone.slice(4);
      }
      // Keep only digits and the leading plus sign
      phone = phone.replace(/[^\d+]/g, '').replace(/(?!^)\+/g, '');
      if (phone.startsWith('00')) {
        phone = '+' + phone.slice(2);
      }
      return phone;
    };

    const parseVcfContent = (content) => {
      const text = String(content)
        .replace(/\r\n/g, '\n')
        .replace(/\r/g, '\n');
      // Unfold continuation lines
      const unfolded = text.replace(/\n[ \t]/g, '');
      const cards = unfolded.split(/BEGIN:VCARD\s*\n/i).slice(1).map(c => c.split(/END:VCARD/i)[0].trim());
      const contacts = [];

      for (const card of cards) {
        const lines = card.split('\n');
        let fullName = '';
        const phones = [];

        for (const rawLine of lines) {
          const line = rawLine.trim();
          if (!line) continue;
          const colonIndex = line.indexOf(':');
          if (colonIndex === -1) continue;

          const keyPart = line.slice(0, colonIndex).split(';')[0].toLowerCase();
          const key = keyPart.replace(/^item\d+\./i, '');
          let value = line.slice(colonIndex + 1).trim();
          if (!value) continue;

          if (key === 'fn') {
            fullName = value;
          } else if (key === 'n' && !fullName) {
            const parts = value.split(';');
            const family = parts[0]?.trim() || '';
            const given = parts[1]?.trim() || '';
            const middle = parts[2]?.trim() || '';
            fullName = [given, middle, family].filter(Boolean).join(' ').trim();
          } else if (key === 'tel') {
            const phone = cleanVcfPhone(value);
            if (phone.length >= 7) phones.push(phone);
          }
        }

        const uniquePhones = [...new Set(phones)];
        for (const phone of uniquePhones) {
          contacts.push({ phone, name: fullName });
        }
      }

      return contacts;
    };

    const handleVcfUpload = async (event) => {
      const file = event.target.files[0];
      if (!file) return;

      try {
        vcfImportStatus.value = 'جاري قراءة الملف...';
        const content = await readVcfFile(file);
        const contacts = parseVcfContent(content);

        if (contacts.length === 0) {
          vcfImportStatus.value = 'لم يتم العثور على أرقام صالحة في الملف.';
          return;
        }

        const existing = new Set(parsedRecipients.value.map(r => r.chat_id));
        const newLines = [];
        let imported = 0;
        let skipped = 0;

        for (const c of contacts) {
          if (existing.has(c.phone)) {
            skipped++;
            continue;
          }
          const safeName = c.name ? c.name.replace(/[|\n\r]/g, ' ').trim() : '';
          const line = safeName ? `${c.phone}|${safeName}` : c.phone;
          newLines.push(line);
          existing.add(c.phone);
          imported++;
        }

        if (newLines.length > 0) {
          const separator = rawRecipientsText.value.trim().length > 0 ? '\n' : '';
          rawRecipientsText.value = `${rawRecipientsText.value.trim()}${separator}${newLines.join('\n')}`;
        }

        vcfImportStatus.value = `تم استيراد ${imported} جهة اتصال جديدة` + (skipped > 0 ? ` (تم تخطي ${skipped} مكررة)` : '');
      } catch (err) {
        console.error('VCF parse error:', err);
        vcfImportStatus.value = 'فشل قراءة ملف vCard. تأكد من صحة الملف.';
      } finally {
        if (vcfInput.value) vcfInput.value.value = '';
      }
    };

    const uploadCampMedia = async (event) => {
      const file = event.target.files[0];
      if (!file) return;

      const formData = new FormData();
      formData.append('file', file);

      try {
        isUploading.value = true;
        const res = await api.post('/telegram/upload-media', formData);
        if (res.success) {
          form.value.media_url = res.url;
        }
      } catch (err) {
        error.value = 'فشل رفع الملف.';
      } finally {
        isUploading.value = false;
      }
    };

    const submitCampaign = async (launchNow = false) => {
      try {
        isSubmitting.value = true;
        error.value = '';

        // 1. Create Campaign
        const res = await api.post('/telegram/campaigns', form.value);
        if (!res.success || !res.data?.id) {
          throw new Error('فشل إنشاء الحملة');
        }

        const campaignId = res.data.id;

        // 2. Save Recipients
        await api.post(`/telegram/campaigns/${campaignId}/recipients`, {
          recipients: parsedRecipients.value,
        });

        // 3. Launch if requested
        if (launchNow) {
          await api.post(`/telegram/campaigns/${campaignId}/launch`);
        }

        router.push(`/telegram/campaigns/${campaignId}`);
      } catch (err) {
        error.value = err.data?.message || err.message || 'حدث خطأ أثناء إنشاء الحملة.';
      } finally {
        isSubmitting.value = false;
      }
    };

    onMounted(() => {
      loadSessions();
    });

    return {
      currentStep,
      steps,
      sessions,
      form,
      rawRecipientsText,
      vcfInput,
      vcfImportStatus,
      parsedRecipientsCount,
      isUploading,
      isSubmitting,
      error,
      setStep,
      handleVcfUpload,
      uploadCampMedia,
      submitCampaign,
    };
  },
};
</script>
