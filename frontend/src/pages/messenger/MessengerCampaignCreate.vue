<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500 max-w-4xl mx-auto">
      <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('page_titles.messenger_campaigns_new') }}</h2>
        <router-link to="/messenger/campaigns" class="text-xs font-bold text-gray-500 hover:text-[#0084FF]">← العودة للحملات</router-link>
      </div>

      <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-6">
        <div class="space-y-2">
          <label class="block text-xs font-black text-gray-700 uppercase">اسم الحملة</label>
          <input v-model="form.name" type="text" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-xs font-bold text-gray-900 outline-none focus:border-[#0084FF]" placeholder="حملة عروض Messenger" />
        </div>

        <div class="space-y-2">
          <label class="block text-xs font-black text-gray-700 uppercase">اختر الصفحة</label>
          <select v-model="form.page_id" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-xs font-bold text-gray-900 outline-none focus:border-[#0084FF]">
            <option v-for="p in pages" :key="p.id" :value="p.id">{{ p.page_name || p.page_id }}</option>
          </select>
          <p v-if="pages.length === 0" class="text-[10px] text-amber-600 font-bold">لا توجد صفحات متصلة. <router-link to="/messenger/connect" class="underline">ربط صفحة</router-link></p>
        </div>

        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-black text-gray-700 uppercase">المستلمين</label>
            <label class="flex items-center gap-2 text-[10px] font-bold text-[#0084FF] cursor-pointer select-none">
              <input v-model="sendToAll" type="checkbox" class="w-4 h-4 accent-[#0084FF] rounded" />
              إرسال لكل المتابعين المسجّلين
            </label>
          </div>
          <textarea v-model="recipientsInput" :disabled="sendToAll" rows="8" class="w-full bg-gray-50 border border-gray-100 rounded-2xl p-4 text-xs font-mono text-gray-900 outline-none focus:border-[#0084FF] resize-none disabled:bg-gray-100 disabled:text-gray-400" placeholder="1234567890\n1234567891"></textarea>
          <p v-if="!sendToAll" class="text-[10px] text-gray-400 font-bold">يمكنك إضافة اسم لكل مستلم بالصيغة: PSID,اسم</p>
          <p v-else class="text-[10px] text-[#0084FF] font-bold">
            <span v-if="loadingFollowers">جاري عد المتابعين...</span>
            <span v-else-if="followersCount > 0">سيتم الإرسال لـ {{ followersCount }} متابع مسجّل.</span>
            <span v-else class="text-amber-600">لا يوجد متابعون مسجّلون لهذه الصفحة.</span>
          </p>
        </div>

        <div class="space-y-2">
          <label class="block text-xs font-black text-gray-700 uppercase">نص الرسالة</label>
          <textarea v-model="form.message" rows="5" class="w-full bg-gray-50 border border-gray-100 rounded-2xl p-4 text-xs font-medium text-gray-900 outline-none focus:border-[#0084FF] resize-none" placeholder="اكتب رسالة الحملة... {name} لاسم العميل"></textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="space-y-2">
            <label class="block text-[10px] font-bold text-gray-400 uppercase">التأخير الأدنى (ثواني)</label>
            <input v-model.number="form.delay_min" type="number" min="1" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:border-[#0084FF]" />
          </div>
          <div class="space-y-2">
            <label class="block text-[10px] font-bold text-gray-400 uppercase">التأخير الأقصى (ثواني)</label>
            <input v-model.number="form.delay_max" type="number" min="1" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:border-[#0084FF]" />
          </div>
        </div>

        <div v-if="validation.total > 0" class="p-4 bg-gray-50 rounded-2xl border border-gray-100 text-xs font-bold text-gray-700 space-y-1">
          <p>إجمالي: {{ validation.total }} | صالح: {{ validation.valid_count }} | غير صالح: {{ validation.invalid_count }}</p>
        </div>

        <div v-if="error" class="p-4 bg-red-50 border border-red-100 rounded-2xl text-xs font-bold text-red-700">{{ error }}</div>

        <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
          <button type="button" @click="validateRecipients" :disabled="validating" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-black rounded-2xl transition-all">
            {{ validating ? 'جاري التحقق...' : 'التحقق من المستلمين' }}
          </button>
          <button type="button" @click="saveCampaign" :disabled="isSaving || !form.page_id" class="px-8 py-3 bg-[#0084FF] hover:bg-[#0073E6] disabled:opacity-50 text-white text-xs font-black rounded-2xl transition-all shadow-md">
            {{ isSaving ? 'جاري الحفظ...' : 'حفظ وإطلاق' }}
          </button>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, reactive, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import api from '@/services/api';
import { t } from '@/i18n';

export default {
  name: 'MessengerCampaignCreate',
  components: { DashboardLayout },
  setup() {
    const router = useRouter();
    const pages = ref([]);
    const recipientsInput = ref('');
    const sendToAll = ref(false);
    const followersCount = ref(0);
    const loadingFollowers = ref(false);
    const form = reactive({ name: '', page_id: null, message: '', delay_min: 2, delay_max: 5 });
    const validation = ref({ total: 0, valid_count: 0, invalid_count: 0, sample_valid: [] });
    const isSaving = ref(false);
    const validating = ref(false);
    const error = ref('');
    const campaignId = ref(null);

    const loadPages = async () => {
      try {
        const res = await api.get('/messenger/status');
        if (res.success) pages.value = res.data.pages || [];
        if (pages.value.length) form.page_id = pages.value[0].id;
      } catch (err) { console.error('Failed to load pages:', err); }
    };

    const parseRecipients = () => {
      return recipientsInput.value.split('\n').map(line => {
        const [psid, name] = line.split(',').map(s => s.trim());
        return { psid, name: name || null };
      }).filter(r => r.psid);
    };

    const loadFollowersCount = async () => {
      if (!sendToAll.value || !form.page_id) {
        followersCount.value = 0;
        return;
      }
      try {
        loadingFollowers.value = true;
        const res = await api.get('/messenger/contacts', { params: { page_id: form.page_id, per_page: 1 } });
        if (res.success) followersCount.value = res.data.total || 0;
      } catch (err) {
        console.error('Failed to load followers count:', err);
      } finally {
        loadingFollowers.value = false;
      }
    };

    const validateRecipients = async () => {
      try {
        validating.value = true;
        error.value = '';
        const payload = sendToAll.value
          ? { send_to_all: true, page_id: form.page_id }
          : { recipients: parseRecipients() };
        const res = await api.post('/messenger/campaigns/0/recipients/validate', payload);
        if (res.success) validation.value = res.data;
      } catch (err) {
        console.error('Validation failed:', err);
        error.value = err.data?.message || 'فشل التحقق.';
      } finally {
        validating.value = false;
      }
    };

    watch([sendToAll, () => form.page_id], loadFollowersCount);

    const saveCampaign = async () => {
      try {
        isSaving.value = true;
        error.value = '';
        const createRes = await api.post('/messenger/campaigns', form);
        if (!createRes.success) throw new Error(createRes.message || 'فشل إنشاء الحملة.');
        campaignId.value = createRes.data.id;

        const recipientsPayload = sendToAll.value ? { send_to_all: true } : { recipients: parseRecipients() };
        await api.post(`/messenger/campaigns/${campaignId.value}/recipients`, recipientsPayload);
        const launchRes = await api.post(`/messenger/campaigns/${campaignId.value}/launch`);
        if (!launchRes.success) throw new Error(launchRes.message || 'فشل إطلاق الحملة.');

        router.push(`/messenger/campaigns/${campaignId.value}`);
      } catch (err) {
        console.error('Save campaign failed:', err);
        error.value = err.data?.message || err.message || 'فشل حفظ الحملة.';
      } finally {
        isSaving.value = false;
      }
    };

    onMounted(loadPages);

    return { pages, recipientsInput, sendToAll, followersCount, loadingFollowers, form, validation, isSaving, validating, error, validateRecipients, saveCampaign, t };
  },
};
</script>
