<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      <!-- Loading State -->
      <div v-if="loading" class="text-center py-20 text-slate-500 text-sm">
        <div class="animate-spin rounded-full h-10 w-10 border-3 border-emerald-500/20 border-t-emerald-600 mx-auto mb-4"></div>
        جاري التحميل...
      </div>

      <template v-else-if="user">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-2 border-b border-slate-200/60">
          <div class="flex items-center gap-3">
            <button 
              @click="$router.back()" 
              class="w-10 h-10 bg-white hover:bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-center text-slate-600 hover:text-slate-900 transition-colors cursor-pointer"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4m0 0l6-6m-6 6l6 6"/></svg>
            </button>
            <div>
              <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ user.name }}</h1>
              <p class="text-sm text-slate-500">{{ user.email }}</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span 
              class="px-3 py-1 rounded-lg text-xs font-bold border" 
              :class="getStatusClass(user.status)"
            >
              {{ getStatusText(user.status) }}
            </span>
            <span 
              v-if="user.is_admin" 
              class="px-3 py-1 rounded-lg text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200/70"
            >
              مدير
            </span>
          </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs font-bold text-slate-500 mb-1">رصيد الرسائل</p>
                <p class="text-2xl font-black text-slate-900">{{ user.messages_balance || 0 }}</p>
              </div>
              <div class="w-12 h-12 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-xl flex items-center justify-center text-sm font-black">
                MSG
              </div>
            </div>
          </div>

          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs font-bold text-slate-500 mb-1">الرسائل المرسلة</p>
                <p class="text-2xl font-black text-slate-900">{{ stats.total_messages_sent || 0 }}</p>
                <p class="text-[10px] text-slate-400 mt-1">{{ stats.total_messages_failed || 0 }} فشل</p>
              </div>
              <div class="w-12 h-12 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-xl flex items-center justify-center text-sm font-black">
                SENT
              </div>
            </div>
          </div>

          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs font-bold text-slate-500 mb-1">الجلسات النشطة</p>
                <p class="text-2xl font-black text-slate-900">{{ stats.active_sessions_count || 0 }}</p>
                <p class="text-[10px] text-slate-400 mt-1">{{ user.whatsapp_sessions_count || 0 }} إجمالي</p>
              </div>
              <div class="w-12 h-12 bg-purple-50 text-purple-700 border border-purple-100 rounded-xl flex items-center justify-center text-sm font-black">
                PHONE
              </div>
            </div>
          </div>

          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs font-bold text-slate-500 mb-1">إجمالي المدفوعات</p>
                <p class="text-2xl font-black text-slate-900">{{ formatCurrency(stats.total_spent || 0) }}</p>
              </div>
              <div class="w-12 h-12 bg-amber-50 text-amber-700 border border-amber-100 rounded-xl flex items-center justify-center text-sm font-black">
                USD
              </div>
            </div>
          </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-5">
          <!-- User Info -->
          <div class="lg:col-span-2 space-y-4 sm:space-y-5">
            <!-- Account Information -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
              <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                <h3 class="text-sm font-black text-slate-900">معلومات الحساب</h3>
                <button 
                  @click="editUser" 
                  class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200/60 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                >
                  تعديل
                </button>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-1">الاسم</p>
                  <p class="text-sm text-slate-900">{{ user.name }}</p>
                </div>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-1">البريد الإلكتروني</p>
                  <p class="text-sm text-slate-900">{{ user.email }}</p>
                </div>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-1">رقم الهاتف</p>
                  <p class="text-sm text-slate-900 font-mono">{{ user.phone_number || '-' }}</p>
                </div>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-1">الدولة</p>
                  <p class="text-sm text-slate-900">{{ user.country || '-' }}</p>
                </div>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-1">المدينة</p>
                  <p class="text-sm text-slate-900">{{ user.city || '-' }}</p>
                </div>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-1">تاريخ التسجيل</p>
                  <p class="text-sm text-slate-900">{{ formatDate(user.created_at) }}</p>
                </div>
              </div>
            </div>

            <!-- Last Activity -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
              <h3 class="text-sm font-black text-slate-900 mb-4 pb-3 border-b border-slate-100">آخر نشاط</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-1">آخر تسجيل دخول</p>
                  <p class="text-sm text-slate-900">{{ formatTimeAgo(user.last_login_at || user.last_activity_at) }}</p>
                </div>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-1">آخر IP</p>
                  <p class="text-sm text-slate-900 font-mono">{{ user.last_ip || '-' }}</p>
                </div>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-1">الجهاز</p>
                  <p class="text-sm text-slate-900">{{ user.last_device || '-' }}</p>
                </div>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-1">المتصفح</p>
                  <p class="text-sm text-slate-900">{{ user.last_browser || '-' }}</p>
                </div>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-1">نظام التشغيل</p>
                  <p class="text-sm text-slate-900">{{ user.last_os || '-' }}</p>
                </div>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-1">اللغة</p>
                  <p class="text-sm text-slate-900">{{ user.language || '-' }}</p>
                </div>
              </div>
            </div>

            <!-- Current Subscription -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
              <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                <h3 class="text-sm font-black text-slate-900">الاشتراك الحالي</h3>
                <button 
                  @click="changeSubscription" 
                  class="px-3 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200/60 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                >
                  تغيير الاشتراك
                </button>
              </div>
              <div v-if="user.active_subscription">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <p class="text-[11px] font-bold text-slate-500 mb-1">الخطة</p>
                    <p class="text-sm text-slate-900 font-bold">{{ user.active_subscription.plan?.name || '-' }}</p>
                  </div>
                  <div>
                    <p class="text-[11px] font-bold text-slate-500 mb-1">السعر</p>
                    <p class="text-sm text-slate-900">{{ formatCurrency(user.active_subscription.plan?.price || 0) }}</p>
                  </div>
                  <div>
                    <p class="text-[11px] font-bold text-slate-500 mb-1">الحالة</p>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border" :class="getStatusClass(user.active_subscription.status)">
                      {{ getStatusText(user.active_subscription.status) }}
                    </span>
                  </div>
                  <div>
                    <p class="text-[11px] font-bold text-slate-500 mb-1">تاريخ البدء</p>
                    <p class="text-sm text-slate-900">{{ formatDate(user.active_subscription.starts_at) }}</p>
                  </div>
                  <div>
                    <p class="text-[11px] font-bold text-slate-500 mb-1">حد الرسائل</p>
                    <p class="text-sm text-slate-900">{{ user.active_subscription.messages_limit || 0 }}</p>
                  </div>
                  <div>
                    <p class="text-[11px] font-bold text-slate-500 mb-1">الرسائل المستخدمة</p>
                    <p class="text-sm text-slate-900">{{ user.active_subscription.messages_used || 0 }}</p>
                  </div>
                </div>
              </div>
              <div v-else class="text-center py-8 text-slate-400 text-xs">
                لا يوجد اشتراك نشط حالياً
              </div>
            </div>

            <!-- Recent Transactions -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
              <h3 class="text-sm font-black text-slate-900 mb-4 pb-3 border-b border-slate-100">آخر المدفوعات</h3>
              <div v-if="user.transactions && user.transactions.length > 0" class="space-y-2.5">
                <div 
                  v-for="transaction in user.transactions" 
                  :key="transaction.id"
                  class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-100"
                >
                  <div>
                    <p class="text-sm text-slate-900 font-bold">{{ transaction.type }}</p>
                    <p class="text-[10px] text-slate-500">{{ formatDate(transaction.created_at) }}</p>
                  </div>
                  <div class="text-left">
                    <p class="text-sm text-slate-900 font-black">{{ formatCurrency(transaction.amount) }}</p>
                    <span 
                      class="text-[10px] px-2 py-0.5 rounded font-bold border" 
                      :class="transaction.status === 'completed' ? 'bg-emerald-50 text-emerald-700 border-emerald-200/70' : 'bg-amber-50 text-amber-700 border-amber-200/70'"
                    >
                      {{ transaction.status }}
                    </span>
                  </div>
                </div>
              </div>
              <div v-else class="text-center py-8 text-slate-400 text-xs">
                لا توجد مدفوعات
              </div>
            </div>
          </div>

          <!-- Sidebar -->
          <div class="space-y-4 sm:space-y-5">
            <!-- Actions -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
              <h3 class="text-sm font-black text-slate-900 mb-4 pb-3 border-b border-slate-100">الإجراءات</h3>
              <div class="space-y-2">
                <button 
                  @click="adjustBalance" 
                  class="w-full px-4 py-2.5 bg-cyan-50 hover:bg-cyan-100 text-cyan-700 border border-cyan-200/60 rounded-xl text-xs font-bold transition-colors text-right cursor-pointer"
                >
                  تعديل رصيد الرسائل
                </button>
                <button 
                  @click="loginAsUser" 
                  class="w-full px-4 py-2.5 bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200/60 rounded-xl text-xs font-bold transition-colors text-right cursor-pointer"
                >
                  تسجيل دخول كمستخدم
                </button>
                <button 
                  v-if="user.status === 'active'" 
                  @click="suspendUser" 
                  class="w-full px-4 py-2.5 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200/60 rounded-xl text-xs font-bold transition-colors text-right cursor-pointer"
                >
                  إيقاف مؤقت
                </button>
                <button 
                  v-if="user.status !== 'banned'" 
                  @click="banUser" 
                  class="w-full px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200/60 rounded-xl text-xs font-bold transition-colors text-right cursor-pointer"
                >
                  حظر المستخدم
                </button>
                <button 
                  v-if="user.status !== 'active'" 
                  @click="reactivateUser" 
                  class="w-full px-4 py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200/60 rounded-xl text-xs font-bold transition-colors text-right cursor-pointer"
                >
                  تفعيل الحساب
                </button>
                <button 
                  @click="deleteUser" 
                  class="w-full px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200/60 rounded-xl text-xs font-bold transition-colors text-right cursor-pointer"
                >
                  حذف الحساب
                </button>
              </div>
            </div>

            <!-- WhatsApp Sessions -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
              <h3 class="text-sm font-black text-slate-900 mb-4 pb-3 border-b border-slate-100">جلسات WhatsApp</h3>
              <div v-if="user.whatsapp_sessions && user.whatsapp_sessions.length > 0" class="space-y-2.5">
                <div 
                  v-for="session in user.whatsapp_sessions" 
                  :key="session.id"
                  class="p-3 bg-slate-50 rounded-xl border border-slate-100"
                >
                  <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-slate-900 font-bold font-mono">{{ session.phone_number || 'غير معروف' }}</p>
                    <span 
                      class="text-[10px] px-2 py-0.5 rounded font-bold border" 
                      :class="session.status === 'connected' ? 'bg-emerald-50 text-emerald-700 border-emerald-200/70' : 'bg-slate-100 text-slate-600 border-slate-200/70'"
                    >
                      {{ session.status }}
                    </span>
                  </div>
                  <p class="text-[10px] text-slate-500">{{ formatTimeAgo(session.last_activity_at || session.created_at) }}</p>
                </div>
              </div>
              <div v-else class="text-center py-8 text-slate-400 text-xs">
                لا توجد جلسات
              </div>
            </div>

            <!-- API Keys -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
              <h3 class="text-sm font-black text-slate-900 mb-4 pb-3 border-b border-slate-100">مفاتيح API</h3>
              <div v-if="user.api_keys && user.api_keys.length > 0" class="space-y-2.5">
                <div 
                  v-for="apiKey in user.api_keys" 
                  :key="apiKey.id"
                  class="p-3 bg-slate-50 rounded-xl border border-slate-100"
                >
                  <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-slate-900 font-bold">{{ apiKey.name }}</p>
                    <span 
                      class="text-[10px] px-2 py-0.5 rounded font-bold border" 
                      :class="apiKey.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200/70' : 'bg-slate-100 text-slate-600 border-slate-200/70'"
                    >
                      {{ apiKey.is_active ? 'نشط' : 'معطل' }}
                    </span>
                  </div>
                  <p class="text-[10px] text-slate-500 font-mono">{{ apiKey.prefix }}...</p>
                </div>
              </div>
              <div v-else class="text-center py-8 text-slate-400 text-xs">
                لا توجد مفاتيح API
              </div>
            </div>

            <!-- Login History -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
              <h3 class="text-sm font-black text-slate-900 mb-4 pb-3 border-b border-slate-100">سجل تسجيل الدخول</h3>
              <div v-if="user.login_histories && user.login_histories.length > 0" class="space-y-2.5">
                <div 
                  v-for="login in user.login_histories" 
                  :key="login.id"
                  class="p-3 bg-slate-50 rounded-xl border border-slate-100"
                >
                  <p class="text-sm text-slate-900 font-mono mb-1">{{ login.ip_address }}</p>
                  <p class="text-[10px] text-slate-500">{{ formatTimeAgo(login.created_at) }}</p>
                  <p class="text-[10px] text-slate-400 mt-0.5">{{ login.user_agent }}</p>
                </div>
              </div>
              <div v-else class="text-center py-8 text-slate-400 text-xs">
                لا يوجد سجل
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Error State -->
      <div v-else class="text-center py-20 text-slate-400">
        <p class="text-lg font-bold text-slate-600">لم يتم العثور على المستخدم</p>
      </div>

      <!-- Balance Modal -->
      <div v-if="showBalanceModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center z-50 p-4" @click.self="showBalanceModal = false">
        <div class="bg-white border border-slate-100 rounded-2xl p-6 w-full max-w-md shadow-2xl" dir="rtl">
          <h3 class="text-lg font-black text-slate-900 mb-4 pb-3 border-b border-slate-100">تعديل رصيد الرسائل</h3>
          <p class="text-sm text-slate-500 mb-4">
            المستخدم: {{ user?.name }}<br>
            الرصيد الحالي: <span class="text-emerald-600 font-black">{{ user?.messages_balance || 0 }}</span> رسالة
          </p>
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">الكمية (استخدم السالب للخصم)</label>
              <input 
                v-model.number="balanceForm.amount" 
                type="number" 
                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all" 
              />
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">السبب</label>
              <input 
                v-model="balanceForm.reason" 
                type="text" 
                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all" 
              />
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
            <button 
              @click="showBalanceModal = false" 
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors cursor-pointer"
            >
              إلغاء
            </button>
            <button 
              @click="saveBalance" 
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-colors cursor-pointer"
            >
              حفظ
            </button>
          </div>
        </div>
      </div>

      <!-- Plan Modal -->
      <div v-if="showPlanModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center z-50 p-4" @click.self="showPlanModal = false">
        <div class="bg-white border border-slate-100 rounded-2xl p-6 w-full max-w-md shadow-2xl" dir="rtl">
          <h3 class="text-lg font-black text-slate-900 mb-4 pb-3 border-b border-slate-100">تغيير الاشتراك</h3>
          <p class="text-sm text-slate-500 mb-4">
            المستخدم: {{ user?.name }}
          </p>
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">الباقة</label>
              <select
                v-model="planForm.plan_id"
                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
              >
                <option value="">اختر باقة</option>
                <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                  {{ plan.name }} - {{ formatCurrency(plan.price) }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">الحالة</label>
              <select
                v-model="planForm.status"
                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all"
              >
                <option value="active">نشط</option>
                <option value="pending">معلق</option>
                <option value="suspended">موقوف</option>
                <option value="cancelled">ملغي</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
            <button
              @click="showPlanModal = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors cursor-pointer"
            >
              إلغاء
            </button>
            <button
              @click="savePlan"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-colors cursor-pointer"
            >
              حفظ
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import admin from '@/services/admin';
import AdminLayout from '@/layouts/AdminLayout.vue';

export default {
  name: 'AdminUserDetail',
  components: { AdminLayout },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const loading = ref(true);
    const user = ref(null);
    const stats = ref({});
    const showBalanceModal = ref(false);
    const balanceForm = ref({
      amount: 0,
      reason: '',
    });
    const showPlanModal = ref(false);
    const plans = ref([]);
    const planForm = ref({
      plan_id: '',
      status: 'active',
    });

    const loadUser = async () => {
      try {
        loading.value = true;
        const userId = route.params.id;
        const response = await admin.getUser(userId);
        
        if (response.success) {
          user.value = response.data.user;
          stats.value = response.data.stats || {};
        }
      } catch (error) {
        console.error('Error loading user:', error);
      } finally {
        loading.value = false;
      }
    };

    const editUser = () => {
      alert('ميزة التعديل قيد التطوير');
    };

    const changeSubscription = async () => {
      try {
        const response = await admin.getPlans();
        if (response.success) {
          plans.value = response.data || [];
          planForm.value = {
            plan_id: user.value?.active_subscription?.plan?.id || '',
            status: 'active',
          };
          showPlanModal.value = true;
        } else {
          alert(response.message || 'فشل تحميل الباقات');
        }
      } catch (error) {
        console.error('Error loading plans:', error);
        alert('حدث خطأ أثناء تحميل الباقات');
      }
    };

    const savePlan = async () => {
      try {
        if (!planForm.value.plan_id) {
          alert('اختر باقة أولاً');
          return;
        }
        const response = await admin.changeUserPlan(user.value.id, {
          plan_id: planForm.value.plan_id,
          status: planForm.value.status,
        });
        if (response.success) {
          showPlanModal.value = false;
          loadUser();
        } else {
          alert(response.message || 'حدث خطأ أثناء تغيير الباقة');
        }
      } catch (error) {
        console.error('Error changing plan:', error);
        alert('حدث خطأ أثناء تغيير الباقة');
      }
    };

    const adjustBalance = () => {
      balanceForm.value = {
        amount: 0,
        reason: '',
      };
      showBalanceModal.value = true;
    };

    const saveBalance = async () => {
      try {
        const response = await admin.adjustUserBalance(user.value.id, {
          amount: balanceForm.value.amount,
          reason: balanceForm.value.reason,
        });

        if (response.success) {
          showBalanceModal.value = false;
          loadUser();
        }
      } catch (error) {
        console.error('Error adjusting balance:', error);
        alert('حدث خطأ أثناء تعديل الرصيد');
      }
    };

    const loginAsUser = async () => {
      if (!confirm('هل أنت متأكد من تسجيل الدخول كهذا المستخدم؟')) return;

      try {
        const response = await admin.loginAsUser(user.value.id);
        if (response.success) {
          alert('تم تسجيل الدخول بنجاح');
        }
      } catch (error) {
        console.error('Error logging in as user:', error);
        alert('حدث خطأ أثناء تسجيل الدخول');
      }
    };

    const suspendUser = async () => {
      if (!confirm('هل أنت متأكد من إيقاف هذا المستخدم مؤقتاً؟')) return;

      try {
        const response = await admin.suspendUser(user.value.id);
        if (response.success) {
          loadUser();
        }
      } catch (error) {
        console.error('Error suspending user:', error);
        alert('حدث خطأ أثناء إيقاف المستخدم');
      }
    };

    const banUser = async () => {
      if (!confirm('هل أنت متأكد من حظر هذا المستخدم نهائياً؟')) return;

      try {
        const response = await admin.banUser(user.value.id);
        if (response.success) {
          loadUser();
        }
      } catch (error) {
        console.error('Error banning user:', error);
        alert('حدث خطأ أثناء حظر المستخدم');
      }
    };

    const reactivateUser = async () => {
      if (!confirm('هل أنت متأكد من تفعيل هذا المستخدم؟')) return;

      try {
        const response = await admin.reactivateUser(user.value.id);
        if (response.success) {
          loadUser();
        }
      } catch (error) {
        console.error('Error reactivating user:', error);
        alert('حدث خطأ أثناء تفعيل المستخدم');
      }
    };

    const deleteUser = async () => {
      if (!confirm('هل أنت متأكد من حذف هذا المستخدم نهائياً؟ هذا الإجراء لا يمكن التراجع عنه!')) return;

      try {
        const response = await admin.deleteUser(user.value.id);
        if (response.success) {
          router.push('/admin/users');
        }
      } catch (error) {
        console.error('Error deleting user:', error);
        alert('حدث خطأ أثناء حذف المستخدم');
      }
    };

    const getStatusClass = (status) => {
      const classes = {
        active: 'bg-emerald-50 text-emerald-700 border-emerald-200/70',
        suspended: 'bg-amber-50 text-amber-700 border-amber-200/70',
        banned: 'bg-red-50 text-red-700 border-red-200/70',
      };
      return classes[status] || 'bg-slate-100 text-slate-600 border-slate-200/70';
    };

    const getStatusText = (status) => {
      const texts = {
        active: 'نشط',
        suspended: 'موقوف',
        banned: 'محظور',
      };
      return texts[status] || status;
    };

    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('ar-SA', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2
      }).format(amount || 0);
    };

    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'long',
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
      loadUser();
    });

    return {
      loading,
      user,
      stats,
      showBalanceModal,
      balanceForm,
      showPlanModal,
      plans,
      planForm,
      editUser,
      changeSubscription,
      savePlan,
      adjustBalance,
      saveBalance,
      loginAsUser,
      suspendUser,
      banUser,
      reactivateUser,
      deleteUser,
      getStatusClass,
      getStatusText,
      formatCurrency,
      formatDate,
      formatTimeAgo,
    };
  },
};
</script>
