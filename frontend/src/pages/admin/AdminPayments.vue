<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/60">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">سجل المدفوعات والعمليات</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">متابعة الفواتير وعمليات الدفع الإلكتروني واسترداد الأموال</p>
        </div>
      </div>

      <!-- Filters Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
        <div class="flex flex-col md:flex-row gap-4">
          <div class="flex-1 relative">
            <input 
              v-model="search" 
              @input="debounceLoad" 
              type="text" 
              placeholder="ابحث باسم المستخدم أو رقم المعاملة..." 
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all" 
            />
          </div>
          <select 
            v-model="status" 
            @change="load(1)" 
            class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all min-w-[160px]"
          >
            <option value="">جميع الحالات</option>
            <option value="completed">مكتملة (Completed)</option>
            <option value="pending">معلقة (Pending)</option>
            <option value="refunded">مستردة (Refunded)</option>
            <option value="failed">فاشلة (Failed)</option>
          </select>
        </div>
      </div>

      <!-- Payments Table Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">#</th>
                <th class="px-5 py-3.5">المستخدم</th>
                <th class="px-5 py-3.5">المبلغ</th>
                <th class="px-5 py-3.5">طريقة الدفع</th>
                <th class="px-5 py-3.5">الخطة / الباقة</th>
                <th class="px-5 py-3.5">رقم المعاملة (Gateway ID)</th>
                <th class="px-5 py-3.5">الحالة</th>
                <th class="px-5 py-3.5">التاريخ</th>
                <th class="px-5 py-3.5 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="transaction in transactions" :key="transaction.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-4 text-slate-400 font-mono">{{ transaction.id }}</td>
                <td class="px-5 py-4 font-bold text-slate-900">{{ transaction.user?.name ?? 'غير معروف' }}</td>
                <td class="px-5 py-4 text-emerald-600 font-mono font-black text-sm">{{ formatCurrency(transaction.amount) }}</td>
                <td class="px-5 py-4 text-slate-600 uppercase font-bold">{{ transaction.payment_method }}</td>
                <td class="px-5 py-4 text-slate-700 font-bold">{{ transaction.plan?.name || '-' }}</td>
                <td class="px-5 py-4 text-slate-400 font-mono text-[11px]">{{ transaction.gateway_transaction_id || '-' }}</td>
                <td class="px-5 py-4">
                  <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold border" :class="statusClass(transaction.status)">
                    {{ getStatusLabel(transaction.status) }}
                  </span>
                </td>
                <td class="px-5 py-4 text-slate-500">{{ formatDate(transaction.created_at) }}</td>
                <td class="px-5 py-4 text-center">
                  <button 
                    v-if="transaction.status === 'completed'" 
                    @click="refund(transaction.id)" 
                    class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200/60 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                  >
                    استرداد
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="transactions.length === 0" class="text-center py-12 text-slate-400 text-xs">لا توجد عمليات دفع مسجلة</div>
        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between text-xs" v-if="pagination.last_page > 1">
          <button @click="prevPage" :disabled="pagination.current_page === 1" class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-lg disabled:opacity-50 border border-slate-200 font-bold transition-colors cursor-pointer">السابق</button>
          <span class="text-slate-500 font-medium">صفحة {{ pagination.current_page }} من {{ pagination.last_page }}</span>
          <button @click="nextPage" :disabled="pagination.current_page === pagination.last_page" class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-lg disabled:opacity-50 border border-slate-200 font-bold transition-colors cursor-pointer">التالي</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script>
import { ref, onMounted } from 'vue';
import admin from '@/services/admin';
import AdminLayout from '@/layouts/AdminLayout.vue';

export default {
  name: 'AdminPayments',
  components: { AdminLayout },
  setup() {
    const transactions = ref([]);
    const pagination = ref({ current_page: 1, last_page: 1 });
    const search = ref('');
    const status = ref('');
    let timeout = null;

    const load = async (page = 1) => {
      try {
        const params = { page, search: search.value, status: status.value };
        const response = await admin.getTransactions(params);
        if (response.success) {
          transactions.value = response.data.data;
          pagination.value = { current_page: response.data.current_page, last_page: response.data.last_page };
        }
      } catch (err) { console.error(err); }
    };

    const debounceLoad = () => {
      clearTimeout(timeout);
      timeout = setTimeout(() => load(1), 300);
    };

    const prevPage = () => load(pagination.value.current_page - 1);
    const nextPage = () => load(pagination.value.current_page + 1);

    const refund = async (id) => {
      if (!confirm('هل أنت متأكد من استرداد قيمة هذه المعاملة؟')) return;
      try {
        const response = await admin.refundTransaction(id);
        if (response.success) load();
      } catch (err) { console.error(err); }
    };

    const statusClass = (s) => {
      const map = {
        completed: 'bg-emerald-50 text-emerald-700 border-emerald-200/70',
        pending: 'bg-amber-50 text-amber-700 border-amber-200/70',
        refunded: 'bg-purple-50 text-purple-700 border-purple-200/70',
        failed: 'bg-red-50 text-red-700 border-red-200/70'
      };
      return map[s] || 'bg-slate-50 text-slate-600 border-slate-200/70';
    };

    const getStatusLabel = (s) => {
      const map = {
        completed: 'مكتملة',
        pending: 'معلقة',
        refunded: 'مستردة',
        failed: 'فاشلة'
      };
      return map[s] || s;
    };

    const formatCurrency = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0);
    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('ar-SA', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
      });
    };

    onMounted(() => load());

    return { 
      transactions, 
      pagination, 
      search, 
      status, 
      load, 
      debounceLoad, 
      prevPage, 
      nextPage, 
      refund, 
      statusClass, 
      getStatusLabel,
      formatCurrency, 
      formatDate 
    };
  },
};
</script>
