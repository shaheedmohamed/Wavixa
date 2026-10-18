<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/60">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">الخطط والباقات</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">إدارة وتعديل باقات الاشتراك الشهرية والسنوية وتحديد أسعار ورصيد الرسائل</p>
        </div>
        <button 
          @click="createPlan" 
          class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs active:scale-95 cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
          <span>إضافة خطة جديدة</span>
        </button>
      </div>

      <!-- Plans Table Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">#</th>
                <th class="px-5 py-3.5">اسم الباقة</th>
                <th class="px-5 py-3.5">الرابط التعريفي</th>
                <th class="px-5 py-3.5">حد الرسائل</th>
                <th class="px-5 py-3.5">السعر</th>
                <th class="px-5 py-3.5">الدورة</th>
                <th class="px-5 py-3.5">الحالة</th>
                <th class="px-5 py-3.5">مميزة</th>
                <th class="px-5 py-3.5">الترتيب</th>
                <th class="px-5 py-3.5 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="plan in plans" :key="plan.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-4 text-slate-400 font-mono">{{ plan.id }}</td>
                <td class="px-5 py-4 font-bold text-slate-900">{{ plan.name }}</td>
                <td class="px-5 py-4 text-slate-500 font-mono text-[11px]">{{ plan.slug }}</td>
                <td class="px-5 py-4 text-emerald-600 font-mono font-black">{{ plan.messages_limit?.toLocaleString() }}</td>
                <td class="px-5 py-4 text-slate-800 font-mono font-bold">{{ plan.price }} {{ plan.currency }}</td>
                <td class="px-5 py-4 text-slate-600">{{ plan.interval === 'month' ? 'شهري' : (plan.interval === 'year' ? 'سنوي' : 'مرة واحدة') }}</td>
                <td class="px-5 py-4">
                  <span :class="plan.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200/70' : 'bg-slate-50 text-slate-500 border-slate-200/70'" class="px-2 py-0.5 rounded-md text-[10px] font-bold border">
                    {{ plan.is_active ? 'مفعلة' : 'معطلة' }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <span :class="plan.is_popular ? 'bg-amber-50 text-amber-700 border-amber-200/70' : 'bg-slate-50 text-slate-400 border-slate-200/70'" class="px-2 py-0.5 rounded-md text-[10px] font-bold border">
                    {{ plan.is_popular ? 'شائعة' : 'عادية' }}
                  </span>
                </td>
                <td class="px-5 py-4 text-slate-400 font-mono">{{ plan.sort_order }}</td>
                <td class="px-5 py-4 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <button @click="editPlan(plan)" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg text-xs font-bold border border-emerald-200/60 transition-colors cursor-pointer">تعديل</button>
                    <button @click="deletePlan(plan.id)" class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg text-xs font-bold border border-red-200/60 transition-colors cursor-pointer">حذف</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="plans.length === 0" class="text-center py-12 text-slate-400 text-xs">لا توجد خطط حالياً</div>
      </div>

      <!-- Edit/Create Modal -->
      <div v-if="showModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center z-50 p-4" @click.self="showModal = false">
        <div class="bg-white border border-slate-100 rounded-2xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-2xl" dir="rtl">
          <h3 class="text-lg font-black text-slate-900 mb-4">{{ isEditing ? 'تعديل الخطة' : 'إنشاء خطة جديدة' }}</h3>
          <div class="space-y-3.5 text-xs">
            <div>
              <label class="block text-slate-600 font-bold mb-1">اسم الخطة</label>
              <input v-model="form.name" type="text" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100" />
            </div>
            <div>
              <label class="block text-slate-600 font-bold mb-1">الاسم اللطيف (Slug - اختياري)</label>
              <input v-model="form.slug" type="text" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 font-mono" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-600 font-bold mb-1">حد الرسائل</label>
                <input v-model.number="form.messages_limit" type="number" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 font-mono" />
              </div>
              <div>
                <label class="block text-slate-600 font-bold mb-1">السعر</label>
                <input v-model.number="form.price" type="number" step="0.01" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 font-mono" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-600 font-bold mb-1">العملة</label>
                <input v-model="form.currency" type="text" maxlength="3" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 font-mono uppercase" />
              </div>
              <div>
                <label class="block text-slate-600 font-bold mb-1">دورة الفوترة</label>
                <select v-model="form.interval" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100">
                  <option value="month">شهري</option>
                  <option value="year">سنوي</option>
                  <option value="one_time">مرة واحدة</option>
                </select>
              </div>
            </div>
            <div>
              <label class="block text-slate-600 font-bold mb-1">ترتيب العرض</label>
              <input v-model.number="form.sort_order" type="number" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 font-mono" />
            </div>
            <div>
              <label class="block text-slate-600 font-bold mb-1">الوصف</label>
              <textarea v-model="form.description" rows="2" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100"></textarea>
            </div>
            <div>
              <label class="block text-slate-600 font-bold mb-1">المميزات (مفصولة بفواصل)</label>
              <input v-model="featuresText" type="text" placeholder="إرسال غير محدود, دعم 24/7, API سريع" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100" />
            </div>
            <div class="flex flex-wrap gap-4 pt-1">
              <label class="flex items-center gap-2 text-slate-700 font-bold cursor-pointer">
                <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded text-emerald-600 border-slate-300" /> مفعلة
              </label>
              <label class="flex items-center gap-2 text-slate-700 font-bold cursor-pointer">
                <input v-model="form.is_free" type="checkbox" class="w-4 h-4 rounded text-emerald-600 border-slate-300" /> خطة مجانية
              </label>
              <label class="flex items-center gap-2 text-slate-700 font-bold cursor-pointer">
                <input v-model="form.is_popular" type="checkbox" class="w-4 h-4 rounded text-emerald-600 border-slate-300" /> الأكثر طلباً (Popular)
              </label>
            </div>
          </div>
          <div class="flex justify-end gap-2.5 mt-6 pt-4 border-t border-slate-100">
            <button @click="showModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors">إلغاء</button>
            <button @click="savePlan" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs">حفظ</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script>
import { ref, onMounted, reactive } from 'vue';
import admin from '@/services/admin';
import AdminLayout from '@/layouts/AdminLayout.vue';

export default {
  name: 'AdminPlans',
  components: { AdminLayout },
  setup() {
    const plans = ref([]);
    const showModal = ref(false);
    const isEditing = ref(false);
    const form = reactive({
      id: null, name: '', slug: '', messages_limit: 0, price: 0, currency: 'USD', interval: 'month',
      is_active: true, is_free: false, is_popular: false, sort_order: 0, description: '', features: [],
    });
    const featuresText = ref('');

    const loadPlans = async () => {
      try {
        const response = await admin.getPlans();
        if (response.success) plans.value = response.data;
      } catch (err) { console.error('Error loading plans:', err); }
    };

    const createPlan = () => {
      isEditing.value = false;
      resetForm();
      showModal.value = true;
    };

    const editPlan = (plan) => {
      isEditing.value = true;
      Object.assign(form, plan);
      featuresText.value = (plan.features || []).join(', ');
      showModal.value = true;
    };

    const resetForm = () => {
      form.id = null; form.name = ''; form.slug = ''; form.messages_limit = 0; form.price = 0; form.currency = 'USD';
      form.interval = 'month'; form.is_active = true; form.is_free = false; form.is_popular = false;
      form.sort_order = 0; form.description = ''; form.features = [];
      featuresText.value = '';
    };

    const savePlan = async () => {
      try {
        const data = {
          name: form.name, slug: form.slug || null, messages_limit: form.messages_limit, price: form.price,
          currency: form.currency, interval: form.interval, is_active: form.is_active, is_free: form.is_free,
          is_popular: form.is_popular, sort_order: form.sort_order, description: form.description,
          features: featuresText.value.split(',').map(f => f.trim()).filter(f => f),
        };
        const response = isEditing.value ? await admin.updatePlan(form.id, data) : await admin.createPlan(data);
        if (response.success) {
          showModal.value = false;
          loadPlans();
        }
      } catch (err) {
        console.error('Error saving plan:', err);
        alert(err.data?.message || err.data?.errors ? JSON.stringify(err.data.errors) : 'فشل في حفظ الخطة');
      }
    };

    const deletePlan = async (id) => {
      if (!confirm('هل أنت متأكد من حذف هذه الخطة؟')) return;
      try {
        const response = await admin.deletePlan(id);
        if (response.success) loadPlans();
      } catch (err) {
        console.error('Error deleting plan:', err);
        alert(err.data?.message || 'فشل في حذف الخطة');
      }
    };

    onMounted(() => loadPlans());

    return { plans, showModal, isEditing, form, featuresText, createPlan, editPlan, savePlan, deletePlan };
  },
};
</script>
