<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/60">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">جلسات WhatsApp</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">إدارة ومراقبة جميع جلسات WhatsApp المتصلة بالسيرفر وإرسال الرسائل من أي جهاز متصل</p>
        </div>

        <div class="flex items-center gap-2">
          <button 
            @click="loadSessions(1)" 
            class="p-2 bg-white hover:bg-slate-50 text-slate-600 border border-slate-200 rounded-xl shadow-xs transition-colors cursor-pointer flex items-center gap-1.5 text-xs font-bold"
            title="تحديث القائمة"
          >
            <svg class="w-4 h-4" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>تحديث</span>
          </button>
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
              placeholder="ابحث باسم المستخدم أو رقم الهاتف..."
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            />
          </div>
          <select
            v-model="status"
            @change="loadSessions(1)"
            class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all min-w-[160px]"
          >
            <option value="">جميع الحالات</option>
            <option value="connected">متصل (Connected)</option>
            <option value="disconnected">غير متصل (Disconnected)</option>
            <option value="pending">معلق (Pending)</option>
          </select>
        </div>
      </div>

      <!-- Success / Error Notice Banner -->
      <div v-if="alertMessage" :class="alertType === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'" class="p-4 rounded-2xl border flex items-center justify-between text-xs font-bold animate-in fade-in">
        <div class="flex items-center gap-2">
          <span>{{ alertType === 'success' ? '✅' : '⚠️' }}</span>
          <span>{{ alertMessage }}</span>
        </div>
        <button @click="alertMessage = ''" class="text-slate-400 hover:text-slate-600">✕</button>
      </div>

      <!-- Sessions Table Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">#</th>
                <th class="px-5 py-3.5">المستخدم</th>
                <th class="px-5 py-3.5">رقم الهاتف</th>
                <th class="px-5 py-3.5">الحالة</th>
                <th class="px-5 py-3.5">تاريخ الاتصال</th>
                <th class="px-5 py-3.5">آخر نشاط</th>
                <th class="px-5 py-3.5 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="session in sessions" :key="session.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-4 text-slate-400 font-mono">{{ session.id }}</td>
                <td class="px-5 py-4">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-800 font-black text-[10px] flex items-center justify-center">
                      {{ (session.user?.name || 'U').charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <p class="font-bold text-slate-900">{{ session.user?.name ?? 'غير معروف' }}</p>
                      <p class="text-[10px] text-slate-400">{{ session.user?.email }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4 text-slate-700 font-mono font-bold">{{ session.phone_number ?? '-' }}</td>
                <td class="px-5 py-4">
                  <span :class="statusClass(session.status)" class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold inline-flex items-center gap-1.5 border">
                    <span class="w-1.5 h-1.5 rounded-full" :class="session.status === 'connected' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"></span>
                    <span>{{ getStatusLabel(session.status) }}</span>
                  </span>
                </td>
                <td class="px-5 py-4 text-slate-500">{{ formatDate(session.connected_at) }}</td>
                <td class="px-5 py-4 text-slate-500">{{ formatDate(session.last_activity_at) }}</td>
                <td class="px-5 py-4 text-center">
                  <div class="flex gap-2 items-center justify-center flex-wrap">
                    <!-- Send Message Button for Connected Sessions -->
                    <button
                      v-if="session.status === 'connected'"
                      @click="openSendMessageModal(session)"
                      class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-black transition-all cursor-pointer shadow-xs flex items-center gap-1.5 active:scale-95"
                      title="إرسال رسالة من هذا الجهاز"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                      <span>إرسال رسالة</span>
                    </button>

                    <span v-if="systemSessionIds.includes(session.id)" class="px-2 py-0.5 bg-indigo-600 text-white rounded-md text-[9px] font-black tracking-wide">
                      جهاز النظام
                    </span>

                    <button 
                      @click="setSystemSession(session.id)" 
                      class="px-2.5 py-1 bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200/80 rounded-lg text-xs font-bold transition-all cursor-pointer shadow-2xs"
                    >
                      {{ systemSessionIds.includes(session.id) ? 'إلغاء جهاز نظام' : 'تعيين جهاز نظام' }}
                    </button>

                    <button 
                      @click="deleteSession(session.id)" 
                      class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200/60 rounded-lg text-xs font-bold transition-all cursor-pointer shadow-2xs"
                    >
                      حذف
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="sessions.length === 0 && !loading" class="text-center py-12 text-slate-400 text-xs">
          لا توجد جلسات مطابقة
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

      <!-- Modal: Send Message from Session -->
      <div v-if="isSendModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" @click.self="closeSendModal">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 sm:p-8 space-y-5 animate-in zoom-in-95">
          <!-- Modal Header -->
          <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 text-lg">
                💬
              </div>
              <div>
                <h3 class="text-base font-black text-slate-900">إرسال رسالة من هذا الجهاز</h3>
                <p class="text-xs text-slate-400 font-medium">إرسال رسالة واتساب فورية مباشرة لأي رقم هاتف</p>
              </div>
            </div>
            <button @click="closeSendModal" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <!-- Selected Device Info Box -->
          <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
            <div>
              <span class="block text-[10px] text-slate-400 font-bold uppercase">الجهاز المرسل</span>
              <span class="block text-xs font-mono font-bold text-slate-800" dir="ltr">{{ activeSendSession?.phone_number || `جلسة #${activeSendSession?.id}` }}</span>
            </div>
            <div class="text-left">
              <span class="block text-[10px] text-slate-400 font-bold uppercase">صاحب الحساب</span>
              <span class="block text-xs font-bold text-slate-700">{{ activeSendSession?.user?.name || 'مستخدم' }}</span>
            </div>
          </div>

          <!-- Form Fields -->
          <form @submit.prevent="submitSendMessage" class="space-y-4">
            <!-- Recipient Phone -->
            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-700">
                رقم هاتف المستلم <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="sendForm.to"
                type="text"
                required
                placeholder="مثال: +201012345678 أو 201012345678"
                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition-all"
                dir="ltr"
              />
              <p class="text-[11px] text-slate-400">يرجى كتابة الرقم مع كود الدولة (بدون 00 أو مسافات).</p>
            </div>

            <!-- Message Text -->
            <div class="space-y-1.5">
              <div class="flex items-center justify-between">
                <label class="block text-xs font-bold text-slate-700">
                  نص الرسالة <span class="text-rose-500">*</span>
                </label>
                <span class="text-[10px] font-mono text-slate-400">{{ sendForm.message.length }} حرف</span>
              </div>
              <textarea
                v-model="sendForm.message"
                rows="4"
                required
                placeholder="اكتب نص الرسالة هنا..."
                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition-all resize-none"
              ></textarea>
            </div>

            <!-- Optional Media URL -->
            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-700">
                رابط صورة أو ملف (اختياري)
              </label>
              <input
                v-model="sendForm.media_url"
                type="url"
                placeholder="https://example.com/image.jpg"
                class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition-all"
                dir="ltr"
              />
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
              <button
                type="button"
                @click="closeSendModal"
                class="px-4 py-2.5 text-slate-600 hover:bg-slate-100 rounded-xl text-xs font-bold transition-colors cursor-pointer"
              >
                إلغاء
              </button>
              <button
                type="submit"
                :disabled="sending"
                class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-xl text-xs font-black transition-all shadow-md shadow-emerald-200 active:scale-95 flex items-center gap-2 cursor-pointer"
              >
                <svg v-if="sending" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span>{{ sending ? 'جاري الإرسال...' : 'إرسال الرسالة الآن 🚀' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script>
import { ref, reactive, onMounted } from 'vue';
import admin from '@/services/admin';
import AdminLayout from '@/layouts/AdminLayout.vue';

export default {
  name: 'AdminSessions',
  components: { AdminLayout },
  setup() {
    const loading = ref(false);
    const sessions = ref([]);
    const systemSessionIds = ref([]);
    const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
    const search = ref('');
    const status = ref('');
    let searchTimeout = null;

    const alertMessage = ref('');
    const alertType = ref('success');

    // Send Message Modal State
    const isSendModalOpen = ref(false);
    const activeSendSession = ref(null);
    const sending = ref(false);
    const sendForm = reactive({
      to: '',
      message: '',
      media_url: '',
    });

    const showAlert = (msg, type = 'success') => {
      alertMessage.value = msg;
      alertType.value = type;
      setTimeout(() => {
        if (alertMessage.value === msg) {
          alertMessage.value = '';
        }
      }, 5000);
    };

    const loadSessions = async (page = 1) => {
      try {
        loading.value = true;
        const params = { page, search: search.value, status: status.value };
        const response = await admin.getSessions(params);
        if (response.success) {
          sessions.value = response.data.data;
          systemSessionIds.value = response.system_session_ids || [];
          pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            total: response.data.total,
          };
        }
      } catch (err) {
        console.error('Error loading sessions:', err);
      } finally {
        loading.value = false;
      }
    };

    const debounceSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => loadSessions(1), 300);
    };

    const setSystemSession = async (id) => {
      try {
        const response = await admin.setSystemSession(id);
        if (response.success) {
          showAlert(response.message || 'تم تحديث جهاز النظام بنجاح', 'success');
          loadSessions(pagination.value.current_page);
        }
      } catch (err) {
        console.error('Error setting system session:', err);
        showAlert(err.data?.message || err.message || 'فشل في تعيين جلسة النظام', 'error');
      }
    };

    const deleteSession = async (id) => {
      if (!confirm('هل أنت متأكد من حذف هذه الجلسة نهائياً؟')) return;
      try {
        const response = await admin.deleteSession(id);
        if (response.success) {
          showAlert('تم حذف الجلسة بنجاح', 'success');
          loadSessions(pagination.value.current_page);
        }
      } catch (err) {
        console.error('Error deleting session:', err);
        showAlert(err.data?.message || err.message || 'فشل في حذف الجلسة', 'error');
      }
    };

    // Modal Handlers
    const openSendMessageModal = (session) => {
      activeSendSession.value = session;
      sendForm.to = '';
      sendForm.message = '';
      sendForm.media_url = '';
      isSendModalOpen.value = true;
    };

    const closeSendModal = () => {
      isSendModalOpen.value = false;
      activeSendSession.value = null;
    };

    const submitSendMessage = async () => {
      if (!activeSendSession.value) return;
      if (!sendForm.to.trim() || !sendForm.message.trim()) {
        showAlert('يرجى ملء رقم الهاتف ونص الرسالة', 'error');
        return;
      }

      try {
        sending.value = true;
        const payload = {
          to: sendForm.to.trim(),
          message: sendForm.message.trim(),
          media_url: sendForm.media_url.trim() || undefined,
          message_type: sendForm.media_url.trim() ? 'image' : 'text',
        };

        const response = await admin.sendMessageFromSession(activeSendSession.value.id, payload);
        if (response.success) {
          showAlert(response.message || 'تم وضع الرسالة في طابور الإرسال بنجاح', 'success');
          closeSendModal();
        } else {
          showAlert(response.message || 'فشل إرسال الرسالة', 'error');
        }
      } catch (err) {
        console.error('Error sending message from session:', err);
        showAlert(err.data?.message || err.message || 'حدث خطأ أثناء إرسال الرسالة', 'error');
      } finally {
        sending.value = false;
      }
    };

    const statusClass = (s) => {
      if (s === 'connected') return 'bg-emerald-50 text-emerald-700 border-emerald-200/70';
      if (s === 'pending') return 'bg-amber-50 text-amber-700 border-amber-200/70';
      return 'bg-slate-50 text-slate-600 border-slate-200/70';
    };

    const getStatusLabel = (s) => {
      if (s === 'connected') return 'متصل';
      if (s === 'pending') return 'قيد الانتظار';
      return 'غير متصل';
    };

    const prevPage = () => loadSessions(pagination.value.current_page - 1);
    const nextPage = () => loadSessions(pagination.value.current_page + 1);

    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('ar-EG', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    };

    onMounted(() => {
      loadSessions(1);
    });

    return {
      loading,
      sessions,
      systemSessionIds,
      pagination,
      search,
      status,
      alertMessage,
      alertType,
      isSendModalOpen,
      activeSendSession,
      sendForm,
      sending,
      loadSessions,
      debounceSearch,
      setSystemSession,
      deleteSession,
      openSendMessageModal,
      closeSendModal,
      submitSendMessage,
      statusClass,
      getStatusLabel,
      prevPage,
      nextPage,
      formatDate,
    };
  },
};
</script>

<style scoped>
.animate-in {
  animation: animate-in 0.2s ease-out;
}
@keyframes animate-in {
  from { opacity: 0; transform: scale(0.97); }
  to { opacity: 1; transform: scale(1); }
}
</style>
