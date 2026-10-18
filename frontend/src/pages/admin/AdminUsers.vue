<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/60">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">إدارة المستخدمين</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">إدارة حسابات المشتركين، الصلاحيات، وحالة الحسابات والاشتراكات</p>
        </div>
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-white border border-slate-200 rounded-xl shadow-xs text-xs font-bold text-slate-700">
          <span>إجمالي المستخدمين:</span>
          <span class="text-emerald-600 font-black">{{ pagination.total ?? 0 }}</span>
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
              placeholder="ابحث بالاسم، البريد الإلكتروني، الهاتف، الدولة..."
              class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            />
          </div>

          <!-- Admin Filter -->
          <div>
            <label class="block text-xs font-bold text-slate-500 mb-1.5">نوع الحساب</label>
            <select
              v-model="filters.is_admin"
              @change="loadUsers"
              class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            >
              <option value="">جميع المستخدمين</option>
              <option value="1">مدراء النظام فقط</option>
              <option value="0">مستخدمون عاديون</option>
            </select>
          </div>

          <!-- Status Filter -->
          <div>
            <label class="block text-xs font-bold text-slate-500 mb-1.5">حالة الحساب</label>
            <select
              v-model="filters.status"
              @change="loadUsers"
              class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            >
              <option value="">جميع الحالات</option>
              <option value="active">نشط</option>
              <option value="suspended">موقوف</option>
              <option value="banned">محظور</option>
            </select>
          </div>

          <!-- Subscription Filter -->
          <div>
            <label class="block text-xs font-bold text-slate-500 mb-1.5">الاشتراك</label>
            <select
              v-model="filters.has_subscription"
              @change="loadUsers"
              class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            >
              <option value="">الكل</option>
              <option value="1">لديه اشتراك مفعل</option>
              <option value="0">بدون اشتراك</option>
            </select>
          </div>

          <!-- Sort By -->
          <div>
            <label class="block text-xs font-bold text-slate-500 mb-1.5">ترتيب حسب</label>
            <select
              v-model="filters.sort_by"
              @change="loadUsers"
              class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            >
              <option value="created_at">تاريخ التسجيل</option>
              <option value="name">الاسم</option>
              <option value="email">البريد الإلكتروني</option>
              <option value="last_activity_at">آخر نشاط</option>
              <option value="messages_balance">رصيد الرسائل</option>
            </select>
          </div>

          <!-- Sort Order -->
          <div>
            <label class="block text-xs font-bold text-slate-500 mb-1.5">الاتجاه</label>
            <select
              v-model="filters.sort_order"
              @change="loadUsers"
              class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
            >
              <option value="desc">تنازلي (الأحدث أولاً)</option>
              <option value="asc">تصاعدي (الأقدم أولاً)</option>
            </select>
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
        <p class="text-sm font-bold text-slate-500">جاري تحميل بيانات المستخدمين...</p>
      </div>

      <!-- Users Table Card -->
      <div v-else class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">#</th>
                <th class="px-5 py-3.5">المستخدم</th>
                <th class="px-5 py-3.5">البريد الإلكتروني</th>
                <th class="px-5 py-3.5">الهاتف</th>
                <th class="px-5 py-3.5">التحقق</th>
                <th class="px-5 py-3.5">الموقع</th>
                <th class="px-5 py-3.5">الاشتراك</th>
                <th class="px-5 py-3.5">رصيد الرسائل</th>
                <th class="px-5 py-3.5">الجلسات والمفاتيح</th>
                <th class="px-5 py-3.5">التاريخ</th>
                <th class="px-5 py-3.5 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr 
                v-for="user in users" 
                :key="user.id" 
                class="hover:bg-slate-50/80 transition-colors cursor-pointer"
                @click="viewUser(user.id)"
              >
                <td class="px-5 py-4 text-slate-400 font-mono">{{ user.id }}</td>
                <td class="px-5 py-4">
                  <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-gradient-to-br from-emerald-500 to-teal-600 text-white rounded-xl flex items-center justify-center font-black text-xs shadow-2xs">
                      {{ user.name?.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <p class="font-bold text-slate-900">{{ user.name }}</p>
                      <div class="flex items-center gap-1.5 mt-0.5">
                        <span 
                          class="px-2 py-0.5 rounded text-[10px] font-bold border" 
                          :class="getStatusClass(user.status)"
                        >
                          {{ getStatusText(user.status) }}
                        </span>
                        <span 
                          v-if="user.is_admin" 
                          class="px-1.5 py-0.5 rounded text-[9px] font-black bg-purple-50 text-purple-700 border border-purple-200/60"
                        >
                          ADMIN
                        </span>
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4 text-slate-600 font-mono">{{ user.email }}</td>
                <td class="px-5 py-4 text-slate-600 font-mono text-xs">{{ user.phone_number || '-' }}</td>
                <td class="px-5 py-4">
                  <span
                    v-if="user.phone_verified"
                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/60"
                    title="تم التحقق من رقم الهاتف"
                  >
                    متحقق
                  </span>
                  <span
                    v-else
                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-50 text-red-700 border border-red-200/60 cursor-help"
                    :title="user.phone_verification_error || 'لم يتم التحقق من رقم الهاتف'"
                  >
                    غير متحقق
                  </span>
                </td>
                <td class="px-5 py-4 text-slate-500 text-xs">
                  <div v-if="user.country || user.city">
                    {{ user.city ? user.city + '، ' : '' }}{{ user.country || '' }}
                  </div>
                  <div v-else>-</div>
                </td>
                <td class="px-5 py-4">
                  <span v-if="user.active_subscription" class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                    {{ user.active_subscription.plan?.name || 'نشط' }}
                  </span>
                  <span v-else class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-slate-50 text-slate-500 border border-slate-200/60">
                    بدون باقة
                  </span>
                </td>
                <td class="px-5 py-4">
                  <div class="font-bold text-slate-800">
                    <span class="text-emerald-600 font-black">{{ user.messages_balance || 0 }}</span>
                    <span class="text-[10px] text-slate-400 mr-1">رسالة</span>
                  </div>
                  <div class="text-[10px] text-slate-400 mt-0.5">
                    {{ user.messages_count || 0 }} تم إرسالها
                  </div>
                </td>
                <td class="px-5 py-4 text-slate-600">
                  <div class="font-semibold">{{ user.whatsapp_sessions_count || 0 }} جلسة</div>
                  <div class="text-[10px] text-slate-400">{{ user.api_keys_count || 0 }} مفتاح API</div>
                </td>
                <td class="px-5 py-4 text-slate-400 text-xs">
                  <div>{{ formatTimeAgo(user.last_activity_at || user.created_at) }}</div>
                  <div class="text-[10px] text-slate-400">{{ formatDate(user.created_at) }}</div>
                </td>
                <td class="px-5 py-4 text-center" @click.stop>
                  <div class="flex items-center justify-center gap-1.5 flex-wrap">
                    <button 
                      @click="viewUser(user.id)" 
                      class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg text-xs font-bold border border-emerald-200/60 transition-colors"
                      title="عرض التفاصيل"
                    >
                      عرض
                    </button>
                    <button 
                      @click="editUser(user)" 
                      class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-bold border border-blue-200/60 transition-colors"
                      title="تعديل"
                    >
                      تعديل
                    </button>
                    <button 
                      v-if="user.status === 'active'" 
                      @click="suspendUser(user.id)" 
                      class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 rounded-lg text-xs font-bold border border-amber-200/60 transition-colors"
                      title="إيقاف"
                    >
                      إيقاف
                    </button>
                    <button 
                      v-if="user.status !== 'active'" 
                      @click="reactivateUser(user.id)" 
                      class="px-2.5 py-1 bg-green-50 hover:bg-green-100 text-green-700 rounded-lg text-xs font-bold border border-green-200/60 transition-colors"
                      title="تفعيل"
                    >
                      تفعيل
                    </button>
                    <button 
                      @click="deleteUser(user.id)" 
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
        <div v-if="users.length === 0" class="text-center py-12 text-slate-400">
          <div class="text-5xl mb-3">👥</div>
          <p class="text-base font-bold text-slate-700">لا يوجد مستخدمون مطابقون</p>
          <p class="text-xs text-slate-400 mt-1">جرب تغيير الفلاتر أو عبارة البحث</p>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="px-6 py-4 border-t border-slate-100 flex flex-col md:flex-row items-center justify-between gap-4 text-xs">
          <div class="text-slate-500 font-medium">
            عرض {{ pagination.from }} إلى {{ pagination.to }} من أصل {{ pagination.total }} مستخدم
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

      <!-- Edit User Modal -->
      <div v-if="showEditModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center z-50 p-4" @click.self="showEditModal = false">
        <div class="bg-white border border-slate-100 rounded-2xl p-6 w-full max-w-md shadow-2xl" dir="rtl">
          <h3 class="text-lg font-black text-slate-900 mb-4">تعديل بيانات المستخدم</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-bold text-slate-600 mb-1.5">الاسم</label>
              <input 
                v-model="editForm.name" 
                type="text" 
                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all" 
              />
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-600 mb-1.5">البريد الإلكتروني</label>
              <input 
                v-model="editForm.email" 
                type="email" 
                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all" 
              />
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-600 mb-1.5">رقم الهاتف</label>
              <input 
                v-model="editForm.phone_number" 
                type="text" 
                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all" 
              />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold text-slate-600 mb-1.5">الدولة</label>
                <input 
                  v-model="editForm.country" 
                  type="text" 
                  class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all" 
                />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-600 mb-1.5">المدينة</label>
                <input 
                  v-model="editForm.city" 
                  type="text" 
                  class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all" 
                />
              </div>
            </div>
            <div class="flex items-center gap-2 pt-2">
              <input 
                v-model="editForm.is_admin" 
                type="checkbox" 
                id="is_admin_edit" 
                class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500" 
              />
              <label for="is_admin_edit" class="text-xs font-bold text-slate-700">منح صلاحيات مدير النظام (Admin)</label>
            </div>
          </div>
          <div class="flex justify-end gap-2.5 mt-6 pt-4 border-t border-slate-100">
            <button 
              @click="showEditModal = false" 
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors"
            >
              إلغاء
            </button>
            <button 
              @click="saveUser" 
              class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs"
            >
              حفظ التعديلات
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import admin from '@/services/admin';
import AdminLayout from '@/layouts/AdminLayout.vue';

export default {
  name: 'AdminUsers',
  components: { AdminLayout },
  setup() {
    const router = useRouter();
    const loading = ref(true);
    const users = ref([]);
    const search = ref('');
    const filters = ref({
      is_admin: '',
      status: '',
      has_subscription: '',
      sort_by: 'created_at',
      sort_order: 'desc',
      per_page: 15,
    });
    const pagination = ref({
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
      from: 0,
      to: 0,
    });
    const showEditModal = ref(false);
    const editForm = ref({
      id: null,
      name: '',
      email: '',
      phone_number: '',
      country: '',
      city: '',
      is_admin: false,
    });

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

    const loadUsers = async (page = 1) => {
      try {
        loading.value = true;
        const params = {
          page,
          search: search.value,
          ...filters.value,
        };

        const response = await admin.getUsers(params);
        if (response.success) {
          users.value = response.data.data;
          pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            per_page: response.data.per_page,
            total: response.data.total,
            from: response.data.from,
            to: response.data.to,
          };
        }
      } catch (error) {
        console.error('Error loading users:', error);
      } finally {
        loading.value = false;
      }
    };

    const debounceSearch = () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        loadUsers(1);
      }, 400);
    };

    const resetFilters = () => {
      search.value = '';
      filters.value = {
        is_admin: '',
        status: '',
        has_subscription: '',
        sort_by: 'created_at',
        sort_order: 'desc',
        per_page: 15,
      };
      loadUsers(1);
    };

    const goToPage = (page) => {
      if (page >= 1 && page <= pagination.value.last_page) {
        loadUsers(page);
      }
    };

    const nextPage = () => {
      if (pagination.value.current_page < pagination.value.last_page) {
        loadUsers(pagination.value.current_page + 1);
      }
    };

    const prevPage = () => {
      if (pagination.value.current_page > 1) {
        loadUsers(pagination.value.current_page - 1);
      }
    };

    const viewUser = (userId) => {
      router.push(`/admin/users/${userId}`);
    };

    const editUser = (user) => {
      editForm.value = {
        id: user.id,
        name: user.name,
        email: user.email,
        phone_number: user.phone_number || '',
        country: user.country || '',
        city: user.city || '',
        is_admin: !!user.is_admin,
      };
      showEditModal.value = true;
    };

    const saveUser = async () => {
      try {
        const response = await admin.updateUser(editForm.value.id, editForm.value);
        if (response.success) {
          showEditModal.value = false;
          loadUsers(pagination.value.current_page);
        }
      } catch (error) {
        console.error('Error updating user:', error);
        alert('حدث خطأ أثناء حفظ التعديلات');
      }
    };

    const suspendUser = async (userId) => {
      if (!confirm('هل أنت متأكد من إيقاف هذا المستخدم؟')) return;
      try {
        const response = await admin.suspendUser(userId);
        if (response.success) loadUsers(pagination.value.current_page);
      } catch (error) {
        console.error('Error suspending user:', error);
      }
    };

    const reactivateUser = async (userId) => {
      try {
        const response = await admin.reactivateUser(userId);
        if (response.success) loadUsers(pagination.value.current_page);
      } catch (error) {
        console.error('Error reactivating user:', error);
      }
    };

    const banUser = async (userId) => {
      if (!confirm('هل أنت متأكد من حظر هذا المستخدم نهائياً؟')) return;
      try {
        const response = await admin.banUser(userId);
        if (response.success) loadUsers(pagination.value.current_page);
      } catch (error) {
        console.error('Error banning user:', error);
      }
    };

    const deleteUser = async (userId) => {
      if (!confirm('تحذير: سيتم حذف المستخدم وكافة بياناته نهائياً. هل أنت متأكد؟')) return;
      try {
        const response = await admin.deleteUser(userId);
        if (response.success) loadUsers(pagination.value.current_page);
      } catch (error) {
        console.error('Error deleting user:', error);
      }
    };

    const getStatusClass = (status) => {
      const classes = {
        active: 'bg-emerald-50 text-emerald-700 border-emerald-200/70',
        suspended: 'bg-amber-50 text-amber-700 border-amber-200/70',
        banned: 'bg-red-50 text-red-700 border-red-200/70',
      };
      return classes[status] || 'bg-slate-50 text-slate-600 border-slate-200/70';
    };

    const getStatusText = (status) => {
      const texts = {
        active: 'نشط',
        suspended: 'موقوف',
        banned: 'محظور',
      };
      return texts[status] || status;
    };

    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
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
      loadUsers();
    });

    return {
      loading,
      users,
      search,
      filters,
      pagination,
      showEditModal,
      editForm,
      visiblePages,
      loadUsers,
      debounceSearch,
      resetFilters,
      goToPage,
      nextPage,
      prevPage,
      viewUser,
      editUser,
      saveUser,
      suspendUser,
      reactivateUser,
      banUser,
      deleteUser,
      getStatusClass,
      getStatusText,
      formatDate,
      formatTimeAgo,
    };
  },
};
</script>
