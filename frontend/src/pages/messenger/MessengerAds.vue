<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500 max-w-4xl mx-auto">
      <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900 tracking-tight">إنشاء إعلان على الصفحة</h2>
        <router-link to="/messenger" class="text-xs font-bold text-gray-500 hover:text-[#0084FF]">← العودة للـ Messenger</router-link>
      </div>

      <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-6">
        <div class="space-y-2">
          <label class="block text-xs font-black text-gray-700 uppercase">Access Token الخاص بـ Meta Ads</label>
          <input v-model="form.access_token" type="password" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-xs font-bold text-gray-900 outline-none focus:border-[#0084FF]" placeholder="EAA..." />
          <p class="text-[10px] text-gray-400 font-bold">مطلوب صلاحيات ads_management و pages_read_engagement.</p>
        </div>

        <div class="space-y-2">
          <label class="block text-xs font-black text-gray-700 uppercase">Ad Account ID</label>
          <input v-model="form.ad_account_id" type="text" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-xs font-bold text-gray-900 outline-none focus:border-[#0084FF]" placeholder="act_1234567890" />
        </div>

        <div class="space-y-2">
          <label class="block text-xs font-black text-gray-700 uppercase">اختر الصفحة</label>
          <select v-model="form.page_id" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-xs font-bold text-gray-900 outline-none focus:border-[#0084FF]">
            <option v-for="p in pages" :key="p.id" :value="p.id">{{ p.page_name || p.page_id }}</option>
          </select>
          <p v-if="pages.length === 0" class="text-[10px] text-amber-600 font-bold">لا توجد صفحات متصلة. <router-link to="/messenger/connect" class="underline">ربط صفحة</router-link></p>
        </div>

        <div v-if="testResult" class="p-4 rounded-2xl border text-xs font-bold" :class="testResult.ready ? 'bg-emerald-50 border-emerald-100 text-emerald-700' : 'bg-amber-50 border-amber-100 text-amber-700'">
          <p v-if="testResult.ready">✅ الصفحة والحساب جاهزان. يمكن إنشاء الإعلان.</p>
          <p v-else>⚠️ لم تكتمل التراخيص. عدد حسابات الإعلانات: {{ testResult.ad_accounts_count }} / الصفحة متاحة: {{ testResult.page_accessible ? 'نعم' : 'لا' }}</p>
          <p v-if="testResult.error" class="mt-1 text-red-600">{{ testResult.error }}</p>
        </div>

        <div class="space-y-2">
          <label class="block text-xs font-black text-gray-700 uppercase">اسم الحملة الإعلانية</label>
          <input v-model="form.campaign_name" type="text" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-xs font-bold text-gray-900 outline-none focus:border-[#0084FF]" placeholder="حملة ترويج صفحة" />
        </div>

        <div class="space-y-2">
          <label class="block text-xs font-black text-gray-700 uppercase">الهدف</label>
          <select v-model="form.objective" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-xs font-bold text-gray-900 outline-none focus:border-[#0084FF]">
            <option value="OUTCOME_TRAFFIC">زيارات (Traffic)</option>
            <option value="OUTCOME_SALES">مبيعات (Sales)</option>
            <option value="OUTCOME_LEADS">عملاء محتملين (Leads)</option>
            <option value="OUTCOME_ENGAGEMENT">تفاعل (Engagement)</option>
            <option value="OUTCOME_AWARENESS">وعي (Awareness)</option>
            <option value="OUTCOME_APP_PROMOTION">تطبيق (App Promotion)</option>
          </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="space-y-2">
            <label class="block text-xs font-black text-gray-700 uppercase">نوع الميزانية</label>
            <select v-model="form.budget_type" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-xs font-bold text-gray-900 outline-none focus:border-[#0084FF]">
              <option value="daily">يومية</option>
              <option value="lifetime">مدى الحياة</option>
            </select>
          </div>
          <div class="space-y-2">
            <label class="block text-xs font-black text-gray-700 uppercase">المبلغ (بعملة الحساب)</label>
            <input v-model.number="form.budget" type="number" min="1" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-xs font-bold text-gray-900 outline-none focus:border-[#0084FF]" placeholder="10" />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="space-y-2">
            <label class="block text-[10px] font-bold text-gray-400 uppercase">تاريخ البداية</label>
            <input v-model="form.start_date" type="date" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:border-[#0084FF]" />
          </div>
          <div class="space-y-2">
            <label class="block text-[10px] font-bold text-gray-400 uppercase">تاريخ النهاية</label>
            <input v-model="form.end_date" type="date" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:border-[#0084FF]" />
          </div>
        </div>

        <div class="space-y-2">
          <label class="block text-xs font-black text-gray-700 uppercase">البلد (كود ISO)</label>
          <input v-model="form.country" type="text" maxlength="2" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-xs font-bold text-gray-900 outline-none focus:border-[#0084FF]" placeholder="EG" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="space-y-2">
            <label class="block text-[10px] font-bold text-gray-400 uppercase">العمر الأدنى</label>
            <input v-model.number="form.age_min" type="number" min="18" max="65" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:border-[#0084FF]" />
          </div>
          <div class="space-y-2">
            <label class="block text-[10px] font-bold text-gray-400 uppercase">العمر الأقصى</label>
            <input v-model.number="form.age_max" type="number" min="18" max="65" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:border-[#0084FF]" />
          </div>
          <div class="space-y-2">
            <label class="block text-[10px] font-bold text-gray-400 uppercase">الجنس</label>
            <select v-model="form.gender" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:border-[#0084FF]">
              <option value="ALL">الكل</option>
              <option value="MALE">ذكور</option>
              <option value="FEMALE">إناث</option>
            </select>
          </div>
        </div>

        <div class="space-y-2">
          <label class="block text-xs font-black text-gray-700 uppercase">عنوان الإعلان</label>
          <input v-model="form.title" type="text" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-xs font-bold text-gray-900 outline-none focus:border-[#0084FF]" placeholder="عنوان جذاب" />
        </div>

        <div class="space-y-2">
          <label class="block text-xs font-black text-gray-700 uppercase">نص الإعلان</label>
          <textarea v-model="form.body" rows="4" class="w-full bg-gray-50 border border-gray-100 rounded-2xl p-4 text-xs font-medium text-gray-900 outline-none focus:border-[#0084FF] resize-none" placeholder="اكتب نص الإعلان..."></textarea>
        </div>

        <div class="space-y-2">
          <label class="block text-xs font-black text-gray-700 uppercase">رابط الهدف</label>
          <input v-model="form.destination_url" type="url" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-xs font-bold text-gray-900 outline-none focus:border-[#0084FF]" placeholder="https://example.com" />
        </div>

        <div class="space-y-2">
          <label class="block text-xs font-black text-gray-700 uppercase">زر الدعوة</label>
          <select v-model="form.call_to_action_type" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-xs font-bold text-gray-900 outline-none focus:border-[#0084FF]">
            <option value="LEARN_MORE">اعرف المزيد</option>
            <option value="SHOP_NOW">تسوق الآن</option>
            <option value="SIGN_UP">سجل الآن</option>
            <option value="CONTACT_US">تواصل معنا</option>
            <option value="APPLY_NOW">قدم الآن</option>
            <option value="ORDER_NOW">اطلب الآن</option>
            <option value="GET_OFFER">احصل على العرض</option>
            <option value="SEND_MESSAGE">أرسل رسالة</option>
          </select>
        </div>

        <div class="space-y-2">
          <label class="block text-xs font-black text-gray-700 uppercase">رابط صورة (اختياري)</label>
          <input v-model="form.image_url" type="url" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-xs font-bold text-gray-900 outline-none focus:border-[#0084FF]" placeholder="https://example.com/image.jpg" />
        </div>

        <div class="flex items-center gap-2">
          <input v-model="form.publish_immediately" type="checkbox" class="w-4 h-4 accent-[#0084FF] rounded" />
          <label class="text-[10px] font-bold text-gray-700">نشر فوراً (بدونها يُنشأ متوقف)</label>
        </div>

        <div v-if="error" class="p-4 bg-red-50 border border-red-100 rounded-2xl text-xs font-bold text-red-700">{{ error }}</div>
        <div v-if="success" class="p-4 bg-emerald-50 border border-emerald-100 rounded-2xl text-xs font-bold text-emerald-700">{{ success }}</div>

        <div class="pt-4 border-t border-gray-100 flex items-center justify-between gap-4">
          <button type="button" @click="testConnection" :disabled="testing || !form.page_id" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-black rounded-2xl transition-all">
            {{ testing ? 'جاري الفحص...' : 'فحص التراخيص' }}
          </button>
          <button type="button" @click="createAd" :disabled="saving || !form.page_id" class="px-8 py-3 bg-[#0084FF] hover:bg-[#0073E6] disabled:opacity-50 text-white text-xs font-black rounded-2xl transition-all shadow-md">
            {{ saving ? 'جاري الإنشاء...' : 'إنشاء الإعلان' }}
          </button>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, reactive, onMounted } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import api from '@/services/api';

export default {
  name: 'MessengerAds',
  components: { DashboardLayout },
  setup() {
    const pages = ref([]);
    const testing = ref(false);
    const saving = ref(false);
    const error = ref('');
    const success = ref('');
    const testResult = ref(null);

    const form = reactive({
      access_token: '',
      ad_account_id: '',
      page_id: null,
      campaign_name: '',
      objective: 'OUTCOME_TRAFFIC',
      budget_type: 'daily',
      budget: 10,
      start_date: '',
      end_date: '',
      country: 'EG',
      age_min: 18,
      age_max: 65,
      gender: 'ALL',
      title: '',
      body: '',
      destination_url: '',
      call_to_action_type: 'LEARN_MORE',
      image_url: '',
      publish_immediately: false,
    });

    const loadPages = async () => {
      try {
        const res = await api.get('/messenger/status');
        if (res.success) pages.value = res.data.pages || [];
        if (pages.value.length) form.page_id = pages.value[0].id;
      } catch (err) { console.error('Failed to load pages:', err); }
    };

    const testConnection = async () => {
      try {
        testing.value = true;
        error.value = '';
        success.value = '';
        const res = await api.post('/messenger/ads/test', {
          access_token: form.access_token,
          page_id: form.page_id,
        });
        if (res.success) testResult.value = res.data;
      } catch (err) {
        error.value = err.data?.message || 'فشل فحص التراخيص.';
      } finally {
        testing.value = false;
      }
    };

    const createAd = async () => {
      try {
        saving.value = true;
        error.value = '';
        success.value = '';
        const res = await api.post('/messenger/ads', form);
        if (res.success) {
          success.value = res.message;
          testResult.value = null;
        } else {
          error.value = res.message || 'فشل إنشاء الإعلان.';
        }
      } catch (err) {
        error.value = err.data?.message || 'فشل إنشاء الإعلان.';
      } finally {
        saving.value = false;
      }
    };

    onMounted(loadPages);

    return { pages, form, testing, saving, error, success, testResult, testConnection, createAd };
  },
};
</script>
