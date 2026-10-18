<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      
      <!-- Dashboard Top Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/60">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">لوحة تحكم الإدارة</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">نظرة عامة على أداء النظام، المستخدمين، ومراقبة استهلاك الموارد</p>
        </div>

        <div class="flex items-center gap-2">
          <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-white border border-slate-200 rounded-xl shadow-xs text-xs font-bold text-slate-700">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>النظام متصل ويعمل</span>
          </div>
          <button 
            @click="loadOverview" 
            class="p-2 bg-white hover:bg-slate-50 text-slate-600 border border-slate-200 rounded-xl shadow-xs transition-colors cursor-pointer"
            title="تحديث البيانات"
          >
            <svg class="w-4 h-4" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading && !overview.stats" class="text-center py-20 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
        <div class="animate-spin rounded-full h-10 w-10 border-3 border-emerald-500/20 border-t-emerald-600 mx-auto mb-3"></div>
        <p class="text-sm font-bold text-slate-500">جاري تحميل بيانات لوحة الإدارة...</p>
      </div>

      <template v-else>
        <!-- Main Key Metrics Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
          <div 
            v-for="stat in mainStats" 
            :key="stat.label" 
            class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between"
          >
            <div class="flex items-start justify-between mb-3">
              <span class="text-xs font-bold text-slate-500">{{ stat.label }}</span>
              <span class="text-xl p-2 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center">
                {{ stat.icon }}
              </span>
            </div>

            <div>
              <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mb-1">
                {{ stat.value }}
              </div>
              <p v-if="stat.sub" class="text-xs text-slate-500 font-medium flex items-center gap-1">
                <span>{{ stat.sub }}</span>
              </p>
            </div>
          </div>
        </div>

        <!-- Charts Section (Users & Messages 7-Day Trend) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 sm:gap-6">
          
          <!-- New Users Chart -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs">
            <div class="flex items-center justify-between mb-5">
              <div>
                <h3 class="text-base font-bold text-slate-900">المستخدمون الجدد</h3>
                <p class="text-xs text-slate-500">معدل التسجيل خلال آخر 7 أيام</p>
              </div>
              <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-lg border border-blue-100">
                تسجيلات
              </span>
            </div>

            <div v-if="dailyStats.labels && dailyStats.labels.length > 0" class="space-y-4">
              <div class="h-52 flex items-end justify-between gap-2 pt-6 border-b border-slate-100 pb-2">
                <div 
                  v-for="(value, index) in dailyStats.users" 
                  :key="index" 
                  class="flex-1 flex flex-col items-center h-full justify-end group relative"
                >
                  <!-- Tooltip -->
                  <div class="absolute -top-7 opacity-0 group-hover:opacity-100 transition-opacity bg-slate-900 text-white text-[10px] font-bold py-0.5 px-2 rounded pointer-events-none z-10 whitespace-nowrap">
                    {{ value }} مستخدم
                  </div>

                  <div 
                    class="w-full max-w-[28px] bg-blue-500 hover:bg-blue-600 rounded-t-md transition-all shadow-xs"
                    :style="{ height: `${getBarHeight(value, Math.max(...dailyStats.users, 1))}%` }"
                  ></div>
                  <span class="text-[10px] text-slate-500 font-medium mt-2">{{ formatDate(dailyStats.labels[index]) }}</span>
                </div>
              </div>

              <div class="flex items-center justify-between text-xs text-slate-500 pt-1">
                <span>إجمالي المستخدمين الجدد: <b class="text-slate-800">{{ dailyStats.users?.reduce((a,b)=>a+b, 0) || 0 }}</b></span>
                <span class="text-emerald-600 font-bold">محدث لحظياً</span>
              </div>
            </div>

            <div v-else class="text-center py-12 text-slate-400 text-sm">
              لا توجد بيانات مستخدمين لعرضها
            </div>
          </div>

          <!-- Messages Volume Chart -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs">
            <div class="flex items-center justify-between mb-5">
              <div>
                <h3 class="text-base font-bold text-slate-900">حجم الرسائل المرسلة</h3>
                <p class="text-xs text-slate-500">حركة إرسال الرسائل لآخر 7 أيام</p>
              </div>
              <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-100">
                رسائل WhatsApp
              </span>
            </div>

            <div v-if="dailyStats.labels && dailyStats.labels.length > 0" class="space-y-4">
              <div class="h-52 flex items-end justify-between gap-2 pt-6 border-b border-slate-100 pb-2">
                <div 
                  v-for="(value, index) in dailyStats.messages" 
                  :key="index" 
                  class="flex-1 flex flex-col items-center h-full justify-end group relative"
                >
                  <!-- Tooltip -->
                  <div class="absolute -top-7 opacity-0 group-hover:opacity-100 transition-opacity bg-slate-900 text-white text-[10px] font-bold py-0.5 px-2 rounded pointer-events-none z-10 whitespace-nowrap">
                    {{ value }} رسالة
                  </div>

                  <div 
                    class="w-full max-w-[28px] bg-emerald-500 hover:bg-emerald-600 rounded-t-md transition-all shadow-xs"
                    :style="{ height: `${getBarHeight(value, Math.max(...dailyStats.messages, 1))}%` }"
                  ></div>
                  <span class="text-[10px] text-slate-500 font-medium mt-2">{{ formatDate(dailyStats.labels[index]) }}</span>
                </div>
              </div>

              <div class="flex items-center justify-between text-xs text-slate-500 pt-1">
                <span>إجمالي الرسائل في الأسبوع: <b class="text-slate-800">{{ (dailyStats.messages?.reduce((a,b)=>a+b, 0) || 0).toLocaleString() }}</b></span>
                <span class="text-emerald-600 font-bold">نشط</span>
              </div>
            </div>

            <div v-else class="text-center py-12 text-slate-400 text-sm">
              لا توجد بيانات رسائل لعرضها
            </div>
          </div>

        </div>

        <!-- System & Services Health Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 sm:gap-6">
          
          <!-- Server Usage -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs">
            <h3 class="text-base font-bold text-slate-900 mb-4 flex items-center justify-between">
              <span>استهلاك السيرفر</span>
              <span class="text-xs font-mono text-slate-400">Server Health</span>
            </h3>

            <div class="space-y-4">
              <!-- Disk Usage -->
              <div>
                <div class="flex justify-between text-xs font-bold text-slate-700 mb-1.5">
                  <span>مساحة القرص</span>
                  <span class="text-slate-900">{{ serverUsage.disk_usage_percent || 0 }}%</span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                  <div 
                    class="h-full bg-emerald-500 rounded-full transition-all" 
                    :style="{ width: `${serverUsage.disk_usage_percent || 0}%` }"
                  ></div>
                </div>
                <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                  <span>المستخدم: {{ formatBytes(serverUsage.disk_used) }}</span>
                  <span>الإجمالي: {{ formatBytes(serverUsage.disk_total) }}</span>
                </div>
              </div>

              <!-- Memory Usage -->
              <div>
                <div class="flex justify-between text-xs font-bold text-slate-700 mb-1.5">
                  <span>الذاكرة (RAM)</span>
                  <span class="text-slate-900">{{ formatBytes(serverUsage.memory_usage) }}</span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                  <div 
                    class="h-full bg-blue-500 rounded-full transition-all" 
                    :style="{ width: `${memoryPercent || 0}%` }"
                  ></div>
                </div>
                <div class="text-[10px] text-slate-400 mt-1">
                  الحد الأقصى للذاكرة: {{ serverUsage.memory_limit || 'N/A' }}
                </div>
              </div>

              <!-- CPU Load -->
              <div class="pt-3 border-t border-slate-100">
                <span class="text-xs text-slate-500 block mb-1">حمل المعالج (CPU Load)</span>
                <span class="text-sm font-mono font-bold text-slate-800 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-200/60 inline-block">
                  {{ serverUsage.cpu_load?.join(' / ') || '0.12 / 0.15 / 0.18' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Services Status -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs">
            <h3 class="text-base font-bold text-slate-900 mb-4 flex items-center justify-between">
              <span>حالة الخدمات</span>
              <span class="text-xs text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full font-bold">مستقر</span>
            </h3>

            <div class="space-y-2.5">
              <div class="flex items-center justify-between p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                <div class="flex items-center gap-2.5">
                  <span class="w-2 h-2 bg-emerald-500 rounded-full"></span>
                  <span class="text-xs font-bold text-slate-700">قاعدة البيانات (MySQL)</span>
                </div>
                <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200/60">نشط</span>
              </div>

              <div class="flex items-center justify-between p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                <div class="flex items-center gap-2.5">
                  <span class="w-2 h-2 bg-emerald-500 rounded-full"></span>
                  <span class="text-xs font-bold text-slate-700">خدمة WhatsApp Engine</span>
                </div>
                <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200/60">نشط</span>
              </div>

              <div class="flex items-center justify-between p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                <div class="flex items-center gap-2.5">
                  <span class="w-2 h-2 bg-emerald-500 rounded-full"></span>
                  <span class="text-xs font-bold text-slate-700">واجهة API والـ Gateways</span>
                </div>
                <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200/60">نشط</span>
              </div>

              <div class="flex items-center justify-between p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                <div class="flex items-center gap-2.5">
                  <span :class="(overview.stats?.pending_jobs > 0) ? 'bg-amber-500' : 'bg-emerald-500'" class="w-2 h-2 rounded-full"></span>
                  <span class="text-xs font-bold text-slate-700">طابور الرسائل (Queue)</span>
                </div>
                <span 
                  class="text-[11px] font-bold px-2 py-0.5 rounded border" 
                  :class="(overview.stats?.pending_jobs > 0) ? 'text-amber-700 bg-amber-50 border-amber-200' : 'text-emerald-700 bg-emerald-50 border-emerald-200/60'"
                >
                  {{ overview.stats?.pending_jobs || 0 }} معلق
                </span>
              </div>
            </div>
          </div>

          <!-- Quick Actions & Stats -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs">
            <h3 class="text-base font-bold text-slate-900 mb-4">إحصائيات سريعة</h3>

            <div class="space-y-2.5">
              <div class="flex items-center justify-between p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                <span class="text-xs font-medium text-slate-600">مفاتيح API الصادرة</span>
                <span class="text-xs font-black text-slate-900">{{ overview.stats?.total_api_keys || 0 }}</span>
              </div>

              <div class="flex items-center justify-between p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                <span class="text-xs font-medium text-slate-600">Webhooks مفعّلة</span>
                <span class="text-xs font-black text-slate-900">{{ overview.stats?.active_webhooks || 0 }}</span>
              </div>

              <div class="flex items-center justify-between p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                <span class="text-xs font-medium text-slate-600">مهام فاشلة</span>
                <span class="text-xs font-black" :class="(overview.stats?.failed_jobs > 0) ? 'text-red-600' : 'text-slate-900'">
                  {{ overview.stats?.failed_jobs || 0 }}
                </span>
              </div>

              <div class="flex items-center justify-between p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                <span class="text-xs font-medium text-slate-600">تنبيهات أمان</span>
                <span class="text-xs font-black" :class="(overview.stats?.unresolved_fraud_alerts > 0) ? 'text-red-600' : 'text-emerald-600'">
                  {{ overview.stats?.unresolved_fraud_alerts || 0 }}
                </span>
              </div>
            </div>
          </div>

        </div>

        <!-- Recent Tables (Users & Subscriptions) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 sm:gap-6">
          
          <!-- Recent Users -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-base font-bold text-slate-900">آخر المستخدمين المسجلين</h3>
              <router-link to="/admin/users" class="text-xs font-bold text-emerald-600 hover:text-emerald-700">
                عرض الكل ←
              </router-link>
            </div>

            <div v-if="recentUsers.length > 0" class="space-y-2.5">
              <div 
                v-for="u in recentUsers" 
                :key="u.id" 
                class="flex items-center justify-between p-2.5 rounded-xl border border-slate-100 hover:bg-slate-50 transition-colors"
              >
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-700 font-black text-xs flex items-center justify-center border border-blue-100">
                    {{ (u.name || 'U').charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <p class="text-xs font-bold text-slate-900">{{ u.name }}</p>
                    <p class="text-[11px] text-slate-400">{{ u.email }}</p>
                  </div>
                </div>

                <div class="text-left text-[11px] text-slate-400 font-medium">
                  {{ formatTimeAgo(u.created_at) }}
                </div>
              </div>
            </div>

            <div v-else class="text-center py-8 text-xs text-slate-400">
              لا يوجد مستخدمون جدد مسجلون
            </div>
          </div>

          <!-- Recent Subscriptions -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-base font-bold text-slate-900">آخر الاشتراكات والترقيات</h3>
              <router-link to="/admin/subscriptions" class="text-xs font-bold text-emerald-600 hover:text-emerald-700">
                عرض الكل ←
              </router-link>
            </div>

            <div v-if="recentSubscriptions.length > 0" class="space-y-2.5">
              <div 
                v-for="sub in recentSubscriptions" 
                :key="sub.id" 
                class="flex items-center justify-between p-2.5 rounded-xl border border-slate-100 hover:bg-slate-50 transition-colors"
              >
                <div>
                  <p class="text-xs font-bold text-slate-900">{{ sub.user?.name || 'مستخدم' }}</p>
                  <p class="text-[11px] text-emerald-600 font-semibold">{{ sub.plan?.name || 'باقة' }}</p>
                </div>

                <div class="text-left">
                  <p class="text-xs font-black text-slate-900">{{ formatCurrency(sub.plan?.price || 0) }}</p>
                  <p class="text-[10px] text-slate-400">{{ formatTimeAgo(sub.created_at) }}</p>
                </div>
              </div>
            </div>

            <div v-else class="text-center py-8 text-xs text-slate-400">
              لا توجد اشتراكات جديدة حالياً
            </div>
          </div>

        </div>

        <!-- Recent Messages Table -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-bold text-slate-900">آخر رسائل WhatsApp في النظام</h3>
            <router-link to="/admin/messages" class="text-xs font-bold text-emerald-600 hover:text-emerald-700">
              عرض سجل الرسائل ←
            </router-link>
          </div>

          <div v-if="recentMessages.length > 0" class="overflow-x-auto">
            <table class="w-full text-right text-xs">
              <thead>
                <tr class="bg-slate-50 text-slate-500 border-b border-slate-200/80">
                  <th class="py-2.5 px-3 font-bold rounded-r-lg">المرسل</th>
                  <th class="py-2.5 px-3 font-bold">المستلم</th>
                  <th class="py-2.5 px-3 font-bold">نص الرسالة</th>
                  <th class="py-2.5 px-3 font-bold">الحالة</th>
                  <th class="py-2.5 px-3 font-bold rounded-l-lg">الوقت</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="msg in recentMessages" :key="msg.id" class="hover:bg-slate-50/60 transition-colors">
                  <td class="py-2.5 px-3 font-bold text-slate-900">{{ msg.user?.name || 'مستخدم' }}</td>
                  <td class="py-2.5 px-3 font-mono text-slate-600" dir="ltr">{{ msg.to }}</td>
                  <td class="py-2.5 px-3 text-slate-500 max-w-xs truncate">{{ msg.message }}</td>
                  <td class="py-2.5 px-3">
                    <span 
                      class="px-2 py-0.5 rounded-full text-[10px] font-extrabold"
                      :class="getStatusClass(msg.status)"
                    >
                      {{ getStatusText(msg.status) }}
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-slate-400 font-medium">{{ formatTimeAgo(msg.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="text-center py-8 text-xs text-slate-400">
            لا توجد رسائل حديثة
          </div>
        </div>

      </template>

    </div>
  </AdminLayout>
</template>

<script>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import admin from '@/services/admin';
import AdminLayout from '@/layouts/AdminLayout.vue';

export default {
  name: 'AdminDashboard',
  components: { AdminLayout },
  setup() {
    const loading = ref(true);
    const overview = ref({ 
      stats: {}, 
      recent_activity: [], 
      recent_events: [],
      recent_users: [],
      recent_subscriptions: [],
      recent_messages: [],
      daily_stats: { labels: [], users: [], messages: [], revenue: [] },
      weekly_stats: { labels: [], users: [], messages: [], revenue: [] }
    });
    let pollInterval = null;

    const mainStats = computed(() => [
      { 
        label: 'إجمالي المستخدمين', 
        value: (overview.value.stats?.total_users ?? 0).toLocaleString(), 
        sub: `+${overview.value.stats?.new_users_today ?? 0} جديد اليوم`, 
        icon: '👥'
      },
      { 
        label: 'المستخدمون النشطون', 
        value: (overview.value.stats?.users_online ?? 0).toLocaleString(), 
        sub: 'نشط خلال 15 دقيقة', 
        icon: '🟢'
      },
      { 
        label: 'إجمالي الرسائل', 
        value: (overview.value.stats?.total_messages ?? 0).toLocaleString(), 
        sub: `+${overview.value.stats?.messages_today ?? 0} اليوم`, 
        icon: '💬'
      },
      { 
        label: 'معدل نجاح الرسائل', 
        value: `${overview.value.stats?.message_success_rate ?? 0}%`, 
        sub: `${overview.value.stats?.message_failure_rate ?? 0}% فشل الإرسال`, 
        icon: '✅'
      },
      { 
        label: 'جلسات WhatsApp المتصلة', 
        value: overview.value.stats?.active_sessions ?? 0, 
        sub: `من أصل ${overview.value.stats?.total_sessions ?? 0} جلسة`, 
        icon: '📱'
      },
      { 
        label: 'الاشتراكات الفعالة', 
        value: overview.value.stats?.active_subscriptions ?? 0, 
        sub: `${overview.value.stats?.total_subscriptions ?? 0} إجمالي الحسابات`, 
        icon: '💳'
      },
      { 
        label: 'إيرادات هذا الشهر', 
        value: formatCurrency(overview.value.stats?.revenue_this_month ?? 0), 
        sub: `${formatCurrency(overview.value.stats?.revenue_today ?? 0)} أرباح اليوم`, 
        icon: '💰'
      },
      { 
        label: 'تنبيهات النظام', 
        value: overview.value.stats?.unresolved_fraud_alerts ?? 0, 
        sub: 'تنبيهات معلقة', 
        icon: '🛡️'
      },
    ]);

    const recentActivity = computed(() => overview.value.recent_activity ?? []);
    const recentEvents = computed(() => overview.value.recent_events ?? []);
    const recentUsers = computed(() => overview.value.recent_users ?? []);
    const recentSubscriptions = computed(() => overview.value.recent_subscriptions ?? []);
    const recentMessages = computed(() => overview.value.recent_messages ?? []);
    const dailyStats = computed(() => overview.value.daily_stats ?? { labels: [], users: [], messages: [], revenue: [] });
    const weeklyStats = computed(() => overview.value.weekly_stats ?? { labels: [], users: [], messages: [], revenue: [] });
    const serverUsage = computed(() => overview.value.stats?.server_usage ?? {});

    const memoryLimit = computed(() => {
      const limit = serverUsage.value?.memory_limit ?? '128M';
      const numericValue = parseInt(limit);
      const unit = limit.replace(/[0-9]/g, '').toUpperCase();
      
      if (unit === 'G') return numericValue * 1024 * 1024 * 1024;
      if (unit === 'M') return numericValue * 1024 * 1024;
      if (unit === 'K') return numericValue * 1024;
      return numericValue;
    });

    const memoryPercent = computed(() => {
      const limit = memoryLimit.value;
      return limit > 0 ? Math.min(100, Math.round((serverUsage.value?.memory_usage ?? 0) / limit * 100)) : 0;
    });

    const loadOverview = async () => {
      try {
        const response = await admin.getOverview();
        if (response.success) {
          overview.value = response.data;
        }
      } catch (err) {
        console.error('Error loading overview:', err);
      } finally {
        loading.value = false;
      }
    };

    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
      }).format(amount || 0);
    };

    const formatBytes = (bytes) => {
      if (!bytes || bytes === 0) return '0 B';
      const k = 1024;
      const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    };

    const formatTimeAgo = (date) => {
      if (!date) return '';
      const now = new Date();
      const past = new Date(date);
      const diffInSeconds = Math.floor((now - past) / 1000);

      if (diffInSeconds < 60) return 'منذ لحظات';
      if (diffInSeconds < 3600) return `منذ ${Math.floor(diffInSeconds / 60)} دقيقة`;
      if (diffInSeconds < 86400) return `منذ ${Math.floor(diffInSeconds / 3600)} ساعة`;
      if (diffInSeconds < 604800) return `منذ ${Math.floor(diffInSeconds / 86400)} يوم`;
      return new Date(date).toLocaleDateString('ar-SA');
    };

    const formatDate = (dateStr) => {
      if (!dateStr) return '';
      const date = new Date(dateStr);
      return date.toLocaleDateString('ar-SA', { month: 'numeric', day: 'numeric' });
    };

    const getBarHeight = (value, max) => {
      if (!max || max === 0) return 4;
      return Math.max(8, (value / max) * 100);
    };

    const getStatusClass = (status) => {
      const statusClasses = {
        'sent': 'bg-emerald-50 text-emerald-700 border border-emerald-200/60',
        'delivered': 'bg-blue-50 text-blue-700 border border-blue-200/60',
        'failed': 'bg-red-50 text-red-700 border border-red-200/60',
        'pending': 'bg-amber-50 text-amber-700 border border-amber-200/60',
        'queued': 'bg-purple-50 text-purple-700 border border-purple-200/60',
      };
      return statusClasses[status] || 'bg-slate-100 text-slate-700 border border-slate-200';
    };

    const getStatusText = (status) => {
      const statusTexts = {
        'sent': 'مرسل',
        'delivered': 'تم التسليم',
        'failed': 'فشل',
        'pending': 'معلق',
        'queued': 'في الانتظار',
        'processing': 'قيد المعالجة',
      };
      return statusTexts[status] || status;
    };

    onMounted(() => {
      loadOverview();
      pollInterval = setInterval(loadOverview, 30000);
    });

    onUnmounted(() => {
      if (pollInterval) clearInterval(pollInterval);
    });

    return {
      loading,
      overview,
      mainStats,
      recentActivity,
      recentEvents,
      recentUsers,
      recentSubscriptions,
      recentMessages,
      dailyStats,
      weeklyStats,
      serverUsage,
      memoryPercent,
      loadOverview,
      formatCurrency,
      formatBytes,
      formatTimeAgo,
      formatDate,
      getBarHeight,
      getStatusClass,
      getStatusText,
    };
  },
};
</script>
