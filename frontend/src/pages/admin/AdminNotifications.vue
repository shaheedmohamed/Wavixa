<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/60">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">الإشعارات</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">إرسال وإدارة إشعارات المستخدمين</p>
        </div>
        <button @click="showSendModal = true" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-colors cursor-pointer shadow-sm">إرسال إشعار</button>
      </div>

      <!-- Filters -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
        <div class="flex flex-col md:flex-row gap-4">
          <select 
            v-model="type" 
            @change="loadNotifications" 
            class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all min-w-[160px]"
          >
            <option value="">جميع الأنواع</option>
            <option value="info">معلومة</option>
            <option value="warning">تحذير</option>
            <option value="success">نجاح</option>
            <option value="error">خطأ</option>
          </select>
        </div>
      </div>

      <!-- Notifications Table -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">#</th>
                <th class="px-5 py-3.5">العنوان</th>
                <th class="px-5 py-3.5">المستخدم</th>
                <th class="px-5 py-3.5">النوع</th>
                <th class="px-5 py-3.5">مقروء</th>
                <th class="px-5 py-3.5">المرسل</th>
                <th class="px-5 py-3.5">تاريخ الإرسال</th>
                <th class="px-5 py-3.5 text-center">إجراءات</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="notification in notifications" :key="notification.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-4 text-slate-400 font-mono">{{ notification.id }}</td>
                <td class="px-5 py-4 text-slate-900 font-bold">{{ notification.title }}</td>
                <td class="px-5 py-4 text-slate-600">{{ notification.user?.name ?? 'الكل' }}</td>
                <td class="px-5 py-4">
                  <span :class="typeClass(notification.type)" class="px-2 py-0.5 rounded-md text-[10px] font-bold border">{{ notification.type }}</span>
                </td>
                <td class="px-5 py-4">
                  <span :class="notification.is_read ? 'bg-emerald-50 text-emerald-700 border-emerald-200/70' : 'bg-slate-50 text-slate-500 border-slate-200/70'" class="px-2 py-0.5 rounded text-[10px] font-bold border">
                    {{ notification.is_read ? 'نعم' : 'لا' }}
                  </span>
                </td>
                <td class="px-5 py-4 text-slate-600">{{ notification.admin?.name ?? 'النظام' }}</td>
                <td class="px-5 py-4 text-slate-500">{{ formatDate(notification.created_at) }}</td>
                <td class="px-5 py-4 text-center">
                  <button @click="deleteNotification(notification.id)" class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200/60 rounded-lg text-xs font-bold transition-colors cursor-pointer">حذف</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="notifications.length === 0" class="text-center py-12 text-slate-400 text-xs">لا توجد إشعارات</div>

        <div v-if="pagination.last_page > 1" class="px-6 py-4 border-t border-slate-100 flex items-center justify-between text-xs">
          <button @click="prevPage" :disabled="pagination.current_page === 1" class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-lg disabled:opacity-50 border border-slate-200 font-bold transition-colors cursor-pointer">السابق</button>
          <span class="text-slate-500 font-medium">صفحة {{ pagination.current_page }} من {{ pagination.last_page }}</span>
          <button @click="nextPage" :disabled="pagination.current_page === pagination.last_page" class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-lg disabled:opacity-50 border border-slate-200 font-bold transition-colors cursor-pointer">التالي</button>
        </div>
      </div>

      <!-- Send Modal -->
      <div v-if="showSendModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center z-50 p-4" @click.self="showSendModal = false">
        <div class="bg-white border border-slate-100 rounded-2xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-2xl" dir="rtl">
          <h3 class="text-lg font-black text-slate-900 mb-4 pb-3 border-b border-slate-100">إرسال إشعار جديد</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">العنوان</label>
              <input v-model="form.title" type="text" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all" />
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">الرسالة</label>
              <textarea v-model="form.message" rows="4" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"></textarea>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">النوع</label>
              <select v-model="form.type" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all">
                <option value="info">معلومة</option>
                <option value="warning">تحذير</option>
                <option value="success">نجاح</option>
                <option value="error">خطأ</option>
              </select>
            </div>
            <div class="flex items-center gap-3">
              <input id="send_to_all" v-model="form.send_to_all" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
              <label for="send_to_all" class="text-xs font-bold text-slate-700">إرسال لجميع المستخدمين</label>
            </div>
            <div v-if="!form.send_to_all">
              <label class="block text-xs font-bold text-slate-700 mb-1.5">المستلمون</label>
              <input v-model="userSearch" @input="searchUsers" type="text" placeholder="ابحث عن مستخدم..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all mb-2" />
              <div v-if="userSearchResults.length > 0" class="bg-white border border-slate-200 rounded-xl max-h-40 overflow-y-auto mb-2 shadow-sm">
                <div v-for="user in userSearchResults" :key="user.id" @click="selectUser(user)" class="px-4 py-2 hover:bg-slate-50 cursor-pointer text-xs text-slate-700 border-b border-slate-100 last:border-0">
                  {{ user.name }} ({{ user.email }})
                </div>
              </div>
              <div v-if="selectedUsers.length > 0" class="flex flex-wrap gap-2">
                <span v-for="user in selectedUsers" :key="user.id" class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200/60 rounded-lg text-xs font-bold flex items-center gap-1.5">
                  {{ user.name }}
                  <button @click="removeUser(user.id)" class="text-emerald-700 hover:text-emerald-900 font-black leading-none">×</button>
                </span>
              </div>
              <div v-else class="text-xs text-slate-400">لم يتم اختيار مستخدمين</div>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
            <button @click="showSendModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors cursor-pointer">إلغاء</button>
            <button @click="sendNotification" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-colors cursor-pointer">إرسال</button>
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
  name: 'AdminNotifications',
  components: { AdminLayout },
  setup() {
    const notifications = ref([]);
    const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
    const type = ref('');
    const showSendModal = ref(false);
    const form = reactive({ title: '', message: '', type: 'info', send_to_all: true, user_ids: [] });
    const userSearch = ref('');
    const userSearchResults = ref([]);
    const selectedUsers = ref([]);
    let searchTimeout = null;

    const loadNotifications = async (page = 1) => {
      try {
        const params = { page, type: type.value };
        const response = await admin.getNotifications(params);
        if (response.success) {
          notifications.value = response.data.data;
          pagination.value = { current_page: response.data.current_page, last_page: response.data.last_page, total: response.data.total };
        }
      } catch (err) { console.error('Error loading notifications:', err); }
    };

    const searchUsers = async () => {
      clearTimeout(searchTimeout);
      if (!userSearch.value.trim()) {
        userSearchResults.value = [];
        return;
      }
      searchTimeout = setTimeout(async () => {
        try {
          const response = await admin.getUsers({ search: userSearch.value, per_page: 10 });
          if (response.success) {
            const selectedIds = selectedUsers.value.map(u => u.id);
            userSearchResults.value = response.data.data.filter(u => !selectedIds.includes(u.id));
          }
        } catch (err) { console.error('Error searching users:', err); }
      }, 300);
    };

    const selectUser = (user) => {
      selectedUsers.value.push(user);
      userSearchResults.value = userSearchResults.value.filter(u => u.id !== user.id);
      userSearch.value = '';
    };

    const removeUser = (id) => {
      selectedUsers.value = selectedUsers.value.filter(u => u.id !== id);
    };

    const sendNotification = async () => {
      try {
        const data = {
          title: form.title,
          message: form.message,
          type: form.type,
          send_to_all: form.send_to_all,
          user_ids: form.send_to_all ? [] : selectedUsers.value.map(u => u.id),
        };
        const response = await admin.sendNotification(data);
        if (response.success) {
          showSendModal.value = false;
          resetForm();
          loadNotifications();
        }
      } catch (err) {
        console.error('Error sending notification:', err);
        alert(err.data?.message || 'فشل في إرسال الإشعار');
      }
    };

    const resetForm = () => {
      form.title = ''; form.message = ''; form.type = 'info'; form.send_to_all = true; form.user_ids = [];
      selectedUsers.value = []; userSearch.value = ''; userSearchResults.value = [];
    };

    const deleteNotification = async (id) => {
      if (!confirm('هل أنت متأكد من حذف هذا الإشعار؟')) return;
      try {
        const response = await admin.deleteNotification(id);
        if (response.success) loadNotifications(pagination.value.current_page);
      } catch (err) {
        console.error('Error deleting notification:', err);
        alert(err.data?.message || 'فشل في حذف الإشعار');
      }
    };

    const typeClass = (t) => {
      if (t === 'success') return 'bg-emerald-50 text-emerald-700 border-emerald-200/70';
      if (t === 'warning') return 'bg-amber-50 text-amber-700 border-amber-200/70';
      if (t === 'error') return 'bg-red-50 text-red-700 border-red-200/70';
      return 'bg-blue-50 text-blue-700 border-blue-200/70';
    };

    const prevPage = () => loadNotifications(pagination.value.current_page - 1);
    const nextPage = () => loadNotifications(pagination.value.current_page + 1);
    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('ar-SA', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    };

    onMounted(() => loadNotifications());

    return {
      notifications, pagination, type, showSendModal, form, userSearch, userSearchResults, selectedUsers,
      loadNotifications, searchUsers, selectUser, removeUser, sendNotification, deleteNotification,
      typeClass, prevPage, nextPage, formatDate,
    };
  },
};
</script>
