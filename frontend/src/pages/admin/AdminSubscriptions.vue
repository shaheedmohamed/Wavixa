<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/60">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">الاشتراكات والفوترة</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">إدارة اشتراكات المستخدمين، تجديد الخطط، وتعديل رصيد الرسائل</p>
        </div>
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-white border border-slate-200 rounded-xl shadow-xs text-xs font-bold text-slate-700">
          <span>إجمالي الاشتراكات:</span>
          <span class="text-emerald-600 font-black">{{ pagination.total || 0 }}</span>
        </div>
      </div>

      <!-- Filters Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
        <div class="flex flex-col md:flex-row gap-4">
          <div class="flex-1 relative">
            <input 
              v-model="search" 
              @input="debounceSearch" 
              type="text" 
              placeholder="ابحث باسم المستخدم أو البريد..." 
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all" 
            />
          </div>
          <select 
            v-model="status" 
            @change="loadSubscriptions(1)" 
            class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all min-w-[160px]"
          >
            <option value="">جميع الحالات</option>
            <option value="active">نشط (Active)</option>
            <option value="cancelled">ملغي (Cancelled)</option>
            <option value="expired">منتهي (Expired)</option>
            <option value="pending">معلق (Pending)</option>
            <option value="suspended">موقوف (Suspended)</option>
          </select>
        </div>
      </div>

      <!-- Subscriptions Table Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">#</th>
                <th class="px-5 py-3.5">المستخدم</th>
                <th class="px-5 py-3.5">الخطة / الباقة</th>
                <th class="px-5 py-3.5">الحالة</th>
                <th class="px-5 py-3.5">الرسائل (مستخدم / حد)</th>
                <th class="px-5 py-3.5">المتبقي</th>
                <th class="px-5 py-3.5">تاريخ البدء</th>
                <th class="px-5 py-3.5">تاريخ الانتهاء</th>
                <th class="px-5 py-3.5 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="sub in subscriptions" :key="sub.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-4 text-slate-400 font-mono">{{ sub.id }}</td>
                <td class="px-5 py-4">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-800 font-black text-[10px] flex items-center justify-center">
                      {{ (sub.user?.name || 'U').charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <p class="font-bold text-slate-900">{{ sub.user?.name ?? 'غير معروف' }}</p>
                      <p class="text-[10px] text-slate-400">{{ sub.user?.email }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4">
                  <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-800 border border-slate-200/60">
                    {{ sub.plan?.name ?? 'مجاني' }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <span :class="statusClass(sub.status)" class="px-2.5 py-1 rounded-lg text-[10px] font-bold border">
                    {{ getStatusLabel(sub.status) }}
                  </span>
                </td>
                <td class="px-5 py-4 text-slate-700 font-mono font-bold">
                  {{ sub.messages_used }} / {{ sub.messages_limit }}
                </td>
                <td class="px-5 py-4 text-emerald-600 font-mono font-black text-sm">
                  {{ sub.messages_remaining }}
                </td>
                <td class="px-5 py-4 text-slate-500">{{ formatDate(sub.starts_at) }}</td>
                <td class="px-5 py-4 text-slate-500">{{ formatDate(sub.ends_at) }}</td>
                <td class="px-5 py-4 text-center">
                  <button 
                    @click="editSubscription(sub)" 
                    class="px-3 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg text-xs font-bold border border-emerald-200/60 transition-colors cursor-pointer"
                  >
                    تعديل
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="subscriptions.length === 0 && !loading" class="text-center py-12 text-slate-400 text-xs">
          لا توجد اشتراكات مطابقة
        </div>

        <div v-if="pagination.last_page > 1" class="px-6 py-4 border-t border-slate-100 flex items-center justify-between text-xs">
          <button 
            @click="prevPage" 
            :disabled="pagination.current_page === 1" 
            class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-lg disabled:opacity-50 border border-slate-200 font-bold transition-colors cursor-pointer"
          >
            السابق
          </button>
          <span class="text-slate-500 font-medium">صفحة {{ pagination.current_page }} من {{ pagination.last_page }}</span>
          <button 
            @click="nextPage" 
            :disabled="pagination.current_page === pagination.last_page" 
            class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-lg disabled:opacity-50 border border-slate-200 font-bold transition-colors cursor-pointer"
          >
            التالي
          </button>
        </div>
      </div>

      <!-- Edit Modal -->
      <div v-if="showEditModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center z-50 p-4" @click.self="showEditModal = false">
        <div class="bg-white border border-slate-100 rounded-2xl p-6 w-full max-w-md shadow-2xl" dir="rtl">
          <h3 class="text-lg font-black text-slate-900 mb-4">تعديل بيانات الاشتراك</h3>
          <div class="space-y-4 text-xs">
            <div>
              <label class="block text-slate-600 font-bold mb-1.5">حالة الاشتراك</label>
              <select v-model="editForm.status" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100">
                <option value="active">نشط (Active)</option>
                <option value="cancelled">ملغي (Cancelled)</option>
                <option value="expired">منتهي (Expired)</option>
                <option value="pending">معلق (Pending)</option>
                <option value="suspended">موقوف (Suspended)</option>
              </select>
            </div>
            <div>
              <label class="block text-slate-600 font-bold mb-1.5">حد الرسائل الإجمالي (Limit)</label>
              <input v-model.number="editForm.messages_limit" type="number" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 font-mono" />
            </div>
            <div>
              <label class="block text-slate-600 font-bold mb-1.5">الرسائل المتبقية (Remaining)</label>
              <input v-model.number="editForm.messages_remaining" type="number" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 font-mono" />
            </div>
            <div>
              <label class="block text-slate-600 font-bold mb-1.5">تاريخ الانتهاء</label>
              <input v-model="editForm.ends_at" type="datetime-local" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 font-mono" />
            </div>
          </div>
          <div class="flex justify-end gap-2.5 mt-6 pt-4 border-t border-slate-100">
            <button @click="showEditModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors">إلغاء</button>
            <button @click="saveSubscription" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs">حفظ</button>
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
  name: 'AdminSubscriptions',
  components: { AdminLayout },
  setup() {
    const loading = ref(false);
    const subscriptions = ref([]);
    const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
    const search = ref('');
    const status = ref('');
    const showEditModal = ref(false);
    const editForm = reactive({ id: null, status: '', messages_limit: 0, messages_remaining: 0, ends_at: '' });
    let searchTimeout = null;

    const loadSubscriptions = async (page = 1) => {
      try {
        loading.value = true;
        const params = { page, search: search.value, status: status.value };
        const response = await admin.getSubscriptions(params);
        if (response.success) {
          subscriptions.value = response.data.data;
          pagination.value = { current_page: response.data.current_page, last_page: response.data.last_page, total: response.data.total };
        }
      } catch (err) { console.error('Error loading subscriptions:', err); }
      finally { loading.value = false; }
    };

    const debounceSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => loadSubscriptions(1), 300);
    };

    const editSubscription = (sub) => {
      editForm.id = sub.id;
      editForm.status = sub.status;
      editForm.messages_limit = sub.messages_limit;
      editForm.messages_remaining = sub.messages_remaining;
      editForm.ends_at = sub.ends_at ? new Date(sub.ends_at).toISOString().slice(0, 16) : '';
      showEditModal.value = true;
    };

    const saveSubscription = async () => {
      try {
        const data = {
          status: editForm.status,
          messages_limit: editForm.messages_limit,
          messages_remaining: editForm.messages_remaining,
          ends_at: editForm.ends_at || null,
        };
        const response = await admin.updateSubscription(editForm.id, data);
        if (response.success) {
          showEditModal.value = false;
          loadSubscriptions(pagination.value.current_page);
        }
      } catch (err) {
        console.error('Error saving subscription:', err);
        alert(err.data?.message || 'فشل في حفظ التعديلات');
      }
    };

    const statusClass = (s) => {
      if (s === 'active') return 'bg-emerald-50 text-emerald-700 border-emerald-200/70';
      if (s === 'cancelled' || s === 'expired') return 'bg-slate-50 text-slate-500 border-slate-200/70';
      if (s === 'suspended') return 'bg-red-50 text-red-700 border-red-200/70';
      return 'bg-amber-50 text-amber-700 border-amber-200/70';
    };

    const getStatusLabel = (s) => {
      const labels = {
        active: 'نشط',
        cancelled: 'ملغي',
        expired: 'منتهي',
        pending: 'معلق',
        suspended: 'موقوف',
      };
      return labels[s] || s;
    };

    const prevPage = () => loadSubscriptions(pagination.value.current_page - 1);
    const nextPage = () => loadSubscriptions(pagination.value.current_page + 1);
    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('ar-SA', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
      });
    };

    onMounted(() => loadSubscriptions());

    return { 
      loading, 
      subscriptions, 
      pagination, 
      search, 
      status, 
      showEditModal, 
      editForm, 
      loadSubscriptions, 
      debounceSearch, 
      editSubscription, 
      saveSubscription, 
      statusClass, 
      getStatusLabel, 
      prevPage, 
      nextPage, 
      formatDate 
    };
  },
};
</script>
