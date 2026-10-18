<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/60">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">إدارة الرسائل</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">سجل ومراقبة كافة الرسائل الصادرة والواردة عبر منصة WhatsApp</p>
        </div>
        <div class="flex items-center gap-2">
          <button 
            @click="exportMessages('csv')" 
            class="px-3 py-1.5 bg-white hover:bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-bold transition-all shadow-2xs flex items-center gap-1.5 cursor-pointer"
          >
            <span>📊</span>
            <span>تصدير CSV</span>
          </button>
          <button 
            @click="exportMessages('excel')" 
            class="px-3 py-1.5 bg-white hover:bg-blue-50 text-blue-700 border border-blue-200 rounded-xl text-xs font-bold transition-all shadow-2xs flex items-center gap-1.5 cursor-pointer"
          >
            <span>�</span>
            <span>تصدير Excel</span>
          </button>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <p class="text-[11px] font-bold text-slate-400 mb-1">إجمالي الرسائل</p>
          <p class="text-xl font-black text-slate-900">{{ pagination.total || 0 }}</p>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <p class="text-[11px] font-bold text-slate-400 mb-1">مرسلة بنجاح</p>
          <p class="text-xl font-black text-emerald-600">{{ stats.sent || 0 }}</p>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <p class="text-[11px] font-bold text-slate-400 mb-1">رسائل فاشلة</p>
          <p class="text-xl font-black text-red-600">{{ stats.failed || 0 }}</p>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <p class="text-[11px] font-bold text-slate-400 mb-1">قيد الانتظار</p>
          <p class="text-xl font-black text-amber-600">{{ stats.pending || 0 }}</p>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs col-span-2 sm:col-span-1">
          <p class="text-[11px] font-bold text-slate-400 mb-1">قيد المعالجة</p>
          <p class="text-xl font-black text-blue-600">{{ stats.processing || 0 }}</p>
        </div>
      </div>

      <!-- Filters Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Search -->
          <div class="lg:col-span-2">
            <label class="block text-xs font-bold text-slate-500 mb-1.5">البحث السريع</label>
            <input
              v-model="search"
              @input="debounceSearch"
              type="text"
              placeholder="ابحث برقم الهاتف، نص الرسالة، المستخدم..."
              class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            />
          </div>

          <!-- Status Filter -->
          <div>
            <label class="block text-xs font-bold text-slate-500 mb-1.5">حالة الرسالة</label>
            <select
              v-model="filters.status"
              @change="loadMessages(1)"
              class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            >
              <option value="">جميع الحالات</option>
              <option value="pending">معلق</option>
              <option value="queued">في الانتظار</option>
              <option value="processing">قيد المعالجة</option>
              <option value="sent">مرسل</option>
              <option value="delivered">تم التسليم</option>
              <option value="failed">فشل</option>
            </select>
          </div>

          <!-- Message Type Filter -->
          <div>
            <label class="block text-xs font-bold text-slate-500 mb-1.5">النوع</label>
            <select
              v-model="filters.message_type"
              @change="loadMessages(1)"
              class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            >
              <option value="">جميع الأنواع</option>
              <option value="text">نص</option>
              <option value="image">صورة</option>
              <option value="video">فيديو</option>
              <option value="document">مستند</option>
              <option value="audio">صوت</option>
            </select>
          </div>

          <!-- Direction Filter -->
          <div>
            <label class="block text-xs font-bold text-slate-500 mb-1.5">الاتجاه</label>
            <select
              v-model="filters.direction"
              @change="loadMessages(1)"
              class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            >
              <option value="">الكل</option>
              <option value="incoming">وارد</option>
              <option value="outgoing">صادر</option>
            </select>
          </div>

          <!-- Date From -->
          <div>
            <label class="block text-xs font-bold text-slate-500 mb-1.5">من تاريخ</label>
            <input
              v-model="filters.date_from"
              @change="loadMessages(1)"
              type="date"
              class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            />
          </div>

          <!-- Date To -->
          <div>
            <label class="block text-xs font-bold text-slate-500 mb-1.5">إلى تاريخ</label>
            <input
              v-model="filters.date_to"
              @change="loadMessages(1)"
              type="date"
              class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            />
          </div>

          <!-- Reset Button -->
          <div class="flex items-end">
            <button
              @click="resetFilters"
              class="w-full px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs sm:text-sm transition-colors cursor-pointer border border-slate-200/60"
            >
              إعادة تعيين
            </button>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-center py-16 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
        <div class="animate-spin rounded-full h-10 w-10 border-3 border-emerald-500/20 border-t-emerald-600 mx-auto mb-3"></div>
        <p class="text-sm font-bold text-slate-500">جاري تحميل الرسائل...</p>
      </div>

      <!-- Messages Table Card -->
      <div v-else class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">#</th>
                <th class="px-5 py-3.5">المستخدم</th>
                <th class="px-5 py-3.5">من</th>
                <th class="px-5 py-3.5">إلى</th>
                <th class="px-5 py-3.5">الرسالة</th>
                <th class="px-5 py-3.5">النوع</th>
                <th class="px-5 py-3.5">الحالة</th>
                <th class="px-5 py-3.5">الجلسة</th>
                <th class="px-5 py-3.5">API Key</th>
                <th class="px-5 py-3.5">الوقت</th>
                <th class="px-5 py-3.5 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr 
                v-for="msg in messages" 
                :key="msg.id" 
                class="hover:bg-slate-50/80 transition-colors"
              >
                <td class="px-5 py-4 text-slate-400 font-mono">{{ msg.id }}</td>
                <td class="px-5 py-4">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 bg-emerald-100 text-emerald-800 rounded-lg flex items-center justify-center text-[10px] font-black">
                      {{ msg.user?.name?.charAt(0).toUpperCase() || '?' }}
                    </div>
                    <div>
                      <p class="font-bold text-slate-900">{{ msg.user?.name || 'غير معروف' }}</p>
                      <p class="text-[10px] text-slate-400">{{ msg.user?.email || '' }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4 text-slate-600 font-mono font-bold">{{ msg.from || msg.session?.phone_number || '-' }}</td>
                <td class="px-5 py-4 text-slate-800 font-mono font-bold">{{ msg.to || '-' }}</td>
                <td class="px-5 py-4 text-slate-600 max-w-xs">
                  <div class="truncate font-medium" :title="msg.message">{{ formatMessagePreview(msg) }}</div>
                  <div v-if="msg.status === 'failed' && msg.error_message" class="mt-1 text-[10px] text-rose-600 font-bold bg-rose-50 px-2 py-0.5 rounded border border-rose-100/80 inline-flex items-center gap-1 max-w-full" :title="msg.error_message">
                    <span>سبب الفشل:</span>
                    <span class="truncate">{{ msg.error_message }}</span>
                  </div>
                </td>
                <td class="px-5 py-4">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200/60">
                    {{ getMessageTypeText(msg.message_type) }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <div class="flex flex-col gap-1 items-start">
                    <span 
                      class="px-2.5 py-1 rounded-lg text-[10px] font-bold border inline-flex items-center gap-1" 
                      :class="getStatusClass(msg.status)"
                    >
                      {{ getStatusText(msg.status) }}
                    </span>
                    <span v-if="msg.status === 'failed' && msg.error_message" class="text-[10px] text-rose-600 font-bold max-w-[140px] truncate" :title="msg.error_message">
                      ⚠️ {{ msg.error_message }}
                    </span>
                  </div>
                </td>
                <td class="px-5 py-4 text-slate-500 font-mono text-[11px]">
                  {{ msg.session?.phone_number || '-' }}
                </td>
                <td class="px-5 py-4 text-slate-500 text-xs">
                  {{ msg.api_key?.name || '-' }}
                </td>
                <td class="px-5 py-4 text-slate-400 text-xs">
                  <div>{{ formatTimeAgo(msg.created_at) }}</div>
                  <div class="text-[10px] text-slate-400">{{ formatDate(msg.created_at) }}</div>
                </td>
                <td class="px-5 py-4 text-center" @click.stop>
                  <div class="flex items-center justify-center gap-1.5 flex-wrap">
                    <button 
                      @click="viewMessage(msg)" 
                      class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg text-xs font-bold border border-emerald-200/60 transition-colors"
                      title="عرض التفاصيل"
                    >
                      عرض
                    </button>
                    <button 
                      v-if="msg.status === 'failed'" 
                      @click="resendMessage(msg.id)" 
                      class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-bold border border-blue-200/60 transition-colors"
                      title="إعادة الإرسال"
                    >
                      إعادة
                    </button>
                    <button 
                      @click="deleteMessage(msg.id)" 
                      class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg text-xs font-bold border border-red-200/60 transition-colors"
                      title="حذف"
                    >
                      حذف
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <div v-if="messages.length === 0" class="text-center py-12 text-slate-400">
          <div class="text-5xl mb-3">💬</div>
          <p class="text-base font-bold text-slate-700">لا توجد رسائل مطابقة</p>
          <p class="text-xs text-slate-400 mt-1">جرب تغيير الفلاتر أو عبارة البحث</p>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="px-6 py-4 border-t border-slate-100 flex flex-col md:flex-row items-center justify-between gap-4 text-xs">
          <div class="text-slate-500 font-medium">
            عرض {{ pagination.from }} إلى {{ pagination.to }} من أصل {{ pagination.total }} رسالة
          </div>
          <div class="flex items-center gap-2">
            <button 
              @click="goToPage(1)" 
              :disabled="pagination.current_page === 1" 
              class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 rounded-lg font-bold disabled:opacity-50 transition-colors cursor-pointer"
            >
              الأولى
            </button>
            <button 
              @click="prevPage" 
              :disabled="pagination.current_page === 1" 
              class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 rounded-lg font-bold disabled:opacity-50 transition-colors cursor-pointer"
            >
              السابق
            </button>
            
            <div class="flex items-center gap-1">
              <button
                v-for="page in visiblePages"
                :key="page"
                @click="goToPage(page)"
                :class="page === pagination.current_page ? 'bg-emerald-600 text-white font-black' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200 font-bold'"
                class="px-3 py-1.5 rounded-lg text-xs transition-colors cursor-pointer"
              >
                {{ page }}
              </button>
            </div>

            <button 
              @click="nextPage" 
              :disabled="pagination.current_page === pagination.last_page" 
              class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 rounded-lg font-bold disabled:opacity-50 transition-colors cursor-pointer"
            >
              التالي
            </button>
            <button 
              @click="goToPage(pagination.last_page)" 
              :disabled="pagination.current_page === pagination.last_page" 
              class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 rounded-lg font-bold disabled:opacity-50 transition-colors cursor-pointer"
            >
              الأخيرة
            </button>
          </div>
        </div>
      </div>

      <!-- View Message Modal -->
      <div v-if="showModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center z-50 p-4" @click.self="showModal = false">
        <div class="bg-white border border-slate-100 rounded-2xl p-6 w-full max-w-2xl max-h-[85vh] overflow-y-auto shadow-2xl" dir="rtl">
          <div class="flex justify-between items-center mb-5 pb-3 border-b border-slate-100">
            <h3 class="text-lg font-black text-slate-900">تفاصيل الرسالة</h3>
            <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">×</button>
          </div>
          
          <div class="space-y-3 text-xs">
            <div class="grid grid-cols-3 gap-4 p-3 bg-slate-50 rounded-xl border border-slate-100">
              <span class="text-slate-400 font-bold">رقم الرسالة</span>
              <span class="col-span-2 text-slate-900 font-mono font-bold">{{ selectedMessage?.id }}</span>
            </div>
            <div class="grid grid-cols-3 gap-4 p-3 bg-slate-50 rounded-xl border border-slate-100">
              <span class="text-slate-400 font-bold">المستخدم</span>
              <span class="col-span-2 text-slate-900 font-bold">{{ selectedMessage?.user?.name || 'غير معروف' }}</span>
            </div>
            <div class="grid grid-cols-3 gap-4 p-3 bg-slate-50 rounded-xl border border-slate-100">
              <span class="text-slate-400 font-bold">من</span>
              <span class="col-span-2 text-slate-900 font-mono font-bold">{{ selectedMessage?.from || selectedMessage?.session?.phone_number || '-' }}</span>
            </div>
            <div class="grid grid-cols-3 gap-4 p-3 bg-slate-50 rounded-xl border border-slate-100">
              <span class="text-slate-400 font-bold">إلى</span>
              <span class="col-span-2 text-slate-900 font-mono font-bold">{{ selectedMessage?.to || '-' }}</span>
            </div>
            <div class="grid grid-cols-3 gap-4 p-3 bg-slate-50 rounded-xl border border-slate-100">
              <span class="text-slate-400 font-bold">النوع</span>
              <span class="col-span-2 text-slate-900 font-bold">{{ getMessageTypeText(selectedMessage?.message_type) }}</span>
            </div>
            <div class="grid grid-cols-3 gap-4 p-3 bg-slate-50 rounded-xl border border-slate-100">
              <span class="text-slate-400 font-bold">الحالة</span>
              <span class="col-span-2">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold border" :class="getStatusClass(selectedMessage?.status)">
                  {{ getStatusText(selectedMessage?.status) }}
                </span>
              </span>
            </div>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
              <p class="text-slate-400 font-bold mb-1.5">نص ومحتوى الرسالة</p>
              <p class="text-slate-800 whitespace-pre-wrap font-medium leading-relaxed">{{ selectedMessage?.message || '-' }}</p>
            </div>
            <div v-if="selectedMessage?.error_message" class="p-3 bg-red-50 border border-red-200 rounded-xl">
              <p class="text-red-700 font-bold mb-1">سبب الفشل / الخطأ</p>
              <p class="text-red-600 font-mono text-[11px]">{{ selectedMessage.error_message }}</p>
            </div>
          </div>

          <div class="flex justify-end gap-2.5 mt-6 pt-4 border-t border-slate-100">
            <button 
              @click="showModal = false" 
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors"
            >
              إغلاق
            </button>
            <button 
              v-if="selectedMessage?.status === 'failed'" 
              @click="resendMessage(selectedMessage.id); showModal = false" 
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs"
            >
              إعادة الإرسال
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script>
import { ref, onMounted, computed } from 'vue';
import admin from '@/services/admin';
import api from '@/services/api';
import AdminLayout from '@/layouts/AdminLayout.vue';

export default {
  name: 'AdminMessages',
  components: { AdminLayout },
  setup() {
    const loading = ref(true);
    const messages = ref([]);
    const search = ref('');
    const filters = ref({
      status: '',
      message_type: '',
      direction: '',
      date_from: '',
      date_to: '',
      per_page: 25,
    });
    const pagination = ref({
      current_page: 1,
      last_page: 1,
      per_page: 25,
      total: 0,
      from: 0,
      to: 0,
    });
    const stats = ref({
      sent: 0,
      failed: 0,
      pending: 0,
      processing: 0,
    });

    const showModal = ref(false);
    const selectedMessage = ref(null);

    let debounceTimer = null;

    const visiblePages = computed(() => {
      const current = pagination.value.current_page;
      const last = pagination.value.last_page;
      const delta = 2;
      const pages = [];

      for (let i = Math.max(1, current - delta); i <= Math.min(last, current + delta); i++) {
        pages.push(i);
      }

      return pages;
    });

    const loadMessages = async (page = 1) => {
      try {
        loading.value = true;
        const params = {
          page,
          search: search.value,
          ...filters.value,
        };

        const response = await admin.getMessages(params);
        if (response.success) {
          messages.value = response.data.data;
          pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            per_page: response.data.per_page,
            total: response.data.total,
            from: response.data.from,
            to: response.data.to,
          };

          // Calculate stats
          calculateStats();
        }
      } catch (error) {
        console.error('Error loading messages:', error);
      } finally {
        loading.value = false;
      }
    };

    const calculateStats = () => {
      stats.value = {
        sent: messages.value.filter(m => m.status === 'sent').length,
        failed: messages.value.filter(m => m.status === 'failed').length,
        pending: messages.value.filter(m => m.status === 'pending').length,
        processing: messages.value.filter(m => m.status === 'processing').length,
      };
    };

    const debounceSearch = () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        loadMessages(1);
      }, 400);
    };

    const resetFilters = () => {
      search.value = '';
      filters.value = {
        status: '',
        message_type: '',
        direction: '',
        date_from: '',
        date_to: '',
        per_page: 25,
      };
      loadMessages(1);
    };

    const goToPage = (page) => {
      if (page >= 1 && page <= pagination.value.last_page) {
        loadMessages(page);
      }
    };

    const nextPage = () => {
      if (pagination.value.current_page < pagination.value.last_page) {
        loadMessages(pagination.value.current_page + 1);
      }
    };

    const prevPage = () => {
      if (pagination.value.current_page > 1) {
        loadMessages(pagination.value.current_page - 1);
      }
    };

    const viewMessage = (message) => {
      selectedMessage.value = message;
      showModal.value = true;
    };

    const resendMessage = async (messageId) => {
      if (!confirm('هل أنت متأكد من إعادة إرسال هذه الرسالة؟')) return;

      try {
        const response = await api.post(`/admin/messages/${messageId}/resend`);
        if (response.success) {
          alert('تم إعادة إرسال الرسالة بنجاح');
          loadMessages(pagination.value.current_page);
        }
      } catch (error) {
        console.error('Error resending message:', error);
        alert('حدث خطأ أثناء إعادة الإرسال');
      }
    };

    const deleteMessage = async (messageId) => {
      if (!confirm('هل أنت متأكد من حذف هذه الرسالة؟')) return;

      try {
        const response = await admin.deleteMessage(messageId);
        if (response.success) {
          loadMessages(pagination.value.current_page);
        }
      } catch (error) {
        console.error('Error deleting message:', error);
        alert('حدث خطأ أثناء الحذف');
      }
    };

    const exportMessages = async (format) => {
      try {
        const params = new URLSearchParams({
          search: search.value,
          ...filters.value,
        });

        const url = `/admin/messages/export/${format}?${params.toString()}`;
        window.open(api.defaults.baseURL + url, '_blank');
      } catch (error) {
        console.error('Error exporting messages:', error);
        alert('حدث خطأ أثناء التصدير');
      }
    };

    const getStatusClass = (status) => {
      const classes = {
        sent: 'bg-emerald-50 text-emerald-700 border-emerald-200/70',
        delivered: 'bg-blue-50 text-blue-700 border-blue-200/70',
        failed: 'bg-red-50 text-red-700 border-red-200/70',
        pending: 'bg-amber-50 text-amber-700 border-amber-200/70',
        queued: 'bg-purple-50 text-purple-700 border-purple-200/70',
        processing: 'bg-cyan-50 text-cyan-700 border-cyan-200/70',
      };
      return classes[status] || 'bg-slate-50 text-slate-600 border-slate-200/70';
    };

    const getStatusText = (status) => {
      const texts = {
        sent: 'مرسل',
        delivered: 'تم التسليم',
        failed: 'فشل',
        pending: 'معلق',
        queued: 'في الانتظار',
        processing: 'قيد المعالجة',
      };
      return texts[status] || status;
    };

    const formatMessagePreview = (msg) => {
      if (!msg) return '-';
      if (msg.message && msg.message.trim() !== '') {
        if (msg.message.startsWith('data:image/') || (msg.message_type === 'image' && msg.message.length > 200)) {
          return '📷 [صورة]';
        }
        if (msg.message.startsWith('data:video/') || (msg.message_type === 'video' && msg.message.length > 200)) {
          return '🎥 [فيديو]';
        }
        if (msg.message.startsWith('data:') || (msg.message_type === 'document' && msg.message.length > 200)) {
          return '📄 [مستند]';
        }
        return msg.message;
      }
      if (msg.message_type === 'image') return '📷 [صورة]';
      if (msg.message_type === 'video') return '🎥 [فيديو]';
      if (msg.message_type === 'document') return '📄 [مستند]';
      if (msg.message_type === 'audio' || msg.message_type === 'voice') return '🎵 [صوت]';
      if (msg.message_type === 'location') return '📍 [موقع]';
      if (msg.message_type === 'contact') return '👤 [جهة اتصال]';
      return '-';
    };

    const getMessageTypeText = (type) => {
      const types = {
        text: 'نص',
        image: 'صورة',
        video: 'فيديو',
        document: 'مستند',
        audio: 'صوت',
      };
      return types[type] || type || 'نص';
    };

    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    };

    const formatTimeAgo = (date) => {
      if (!date) return '-';
      const now = new Date();
      const past = new Date(date);
      const diffInSeconds = Math.floor((now - past) / 1000);

      if (diffInSeconds < 60) return 'منذ لحظات';
      if (diffInSeconds < 3600) return `منذ ${Math.floor(diffInSeconds / 60)} دقيقة`;
      if (diffInSeconds < 86400) return `منذ ${Math.floor(diffInSeconds / 3600)} ساعة`;
      if (diffInSeconds < 604800) return `منذ ${Math.floor(diffInSeconds / 86400)} يوم`;
      return formatDate(date);
    };

    onMounted(() => {
      loadMessages();
    });

    return {
      loading,
      messages,
      search,
      filters,
      pagination,
      stats,
      visiblePages,
      showModal,
      selectedMessage,
      loadMessages,
      debounceSearch,
      resetFilters,
      goToPage,
      nextPage,
      prevPage,
      viewMessage,
      resendMessage,
      deleteMessage,
      exportMessages,
      getStatusClass,
      getStatusText,
      getMessageTypeText,
      formatMessagePreview,
      formatDate,
      formatTimeAgo,
    };
  },
};
</script>
