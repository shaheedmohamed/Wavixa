<template>
  <div class="min-h-screen bg-[#f8fafc] text-slate-800 font-sans selection:bg-emerald-500/20 selection:text-emerald-900" dir="rtl">
    
    <!-- Top Header / Navbar -->
    <header class="bg-white/95 backdrop-blur border-b border-slate-200/80 sticky top-0 z-40 shadow-xs">
      <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
          
          <!-- Brand Logo & Title -->
          <div class="flex items-center gap-3">
            <!-- Mobile Menu Toggle Button -->
            <button 
              @click="isMobileMenuOpen = !isMobileMenuOpen"
              class="lg:hidden p-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-none transition-colors"
              aria-label="القائمة"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path v-if="!isMobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>

            <router-link to="/admin" class="flex items-center gap-3 group">
              <div class="p-1 rounded-xl bg-slate-50 border border-slate-100 group-hover:border-emerald-200 transition-colors">
                <img :src="logo" alt="Wavixa" class="h-8 w-auto object-contain transition-transform duration-200 group-hover:scale-105" />
              </div>
              <div class="flex flex-col">
                <div class="flex items-center gap-1.5">
                  <span class="text-base sm:text-lg font-black tracking-tight text-slate-900">Wavixa</span>
                  <span class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white text-[9px] font-black px-2 py-0.5 rounded-md shadow-2xs uppercase">
                    الإدارة
                  </span>
                </div>
                <span class="text-[10px] font-semibold text-slate-400 -mt-0.5">لوحة تحكم النظام</span>
              </div>
            </router-link>
          </div>

          <!-- Actions / User Info -->
          <div class="flex items-center gap-2 sm:gap-3">
            
            <!-- Switch to Client Dashboard -->
            <router-link
              to="/dashboard"
              class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 hover:text-indigo-800 rounded-xl transition-all text-xs font-black border border-indigo-200/70 shadow-2xs group"
            >
              <svg class="w-3.5 h-3.5 text-indigo-500 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
              <span>لوحة العميل</span>
            </router-link>

            <!-- User Badge -->
            <div class="flex items-center gap-2 px-2.5 py-1.5 bg-slate-50 border border-slate-200/70 rounded-xl">
              <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 text-white font-black text-xs flex items-center justify-center shadow-2xs">
                {{ (user?.name || 'A').charAt(0).toUpperCase() }}
              </div>
              <span class="text-xs font-black text-slate-700 hidden md:inline-block truncate max-w-[120px]">{{ user?.name || 'مسؤول النظام' }}</span>
            </div>

            <!-- Logout Button -->
            <button
              @click="logout"
              class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 hover:text-red-700 rounded-xl transition-colors text-xs font-bold border border-red-200/60 flex items-center gap-1.5 cursor-pointer shadow-2xs"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              <span class="hidden sm:inline-block">خروج</span>
            </button>
          </div>

        </div>
      </div>
    </header>

    <div class="flex">
      
      <!-- Desktop Sidebar -->
      <aside class="w-68 bg-white border-l border-slate-200/80 min-h-[calc(100vh-4rem)] hidden lg:block sticky top-16 self-start overflow-y-auto max-h-[calc(100vh-4rem)] shadow-xs custom-scrollbar">
        <div class="p-3.5 space-y-5">
          <div v-for="section in adminMenuSections" :key="section.title" class="space-y-1.5">
            <!-- Section Header -->
            <div class="px-2.5 flex items-center justify-between text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">
              <div class="flex items-center gap-1.5">
                <span v-if="section.colorDot" class="w-1.5 h-1.5 rounded-full" :class="section.colorDot"></span>
                <span>{{ section.title }}</span>
              </div>
              <span v-if="section.badge" class="text-[9px] px-1.5 py-0.5 rounded font-extrabold" :class="section.badgeClass || 'bg-slate-100 text-slate-600'">
                {{ section.badge }}
              </span>
            </div>

            <!-- Items -->
            <router-link
              v-for="item in section.items"
              :key="item.path"
              :to="item.path"
              :class="[
                'flex items-center gap-2.5 px-3 py-2 rounded-xl transition-all text-right text-xs group relative',
                isActive(item.path)
                  ? 'bg-emerald-50 text-emerald-800 font-black shadow-2xs border border-emerald-200/60'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-semibold'
              ]"
            >
              <!-- Active Indicator Bar -->
              <span 
                v-if="isActive(item.path)" 
                class="absolute top-2 bottom-2 right-1 w-1 rounded-full bg-emerald-600"
              ></span>

              <!-- Icon Container -->
              <div 
                class="w-7 h-7 flex items-center justify-center rounded-lg transition-all"
                :class="[
                  isActive(item.path) 
                    ? 'bg-emerald-600 text-white shadow-xs' 
                    : 'text-slate-400 group-hover:text-emerald-700 group-hover:bg-emerald-50/70 bg-slate-50'
                ]"
              >
                <Icon :name="item.icon" class="w-4 h-4" />
              </div>

              <span class="flex-1 truncate">{{ item.label }}</span>
            </router-link>
          </div>
        </div>
      </aside>

      <!-- Mobile Sidebar Drawer Backdrop -->
      <div 
        v-if="isMobileMenuOpen" 
        @click="isMobileMenuOpen = false"
        class="lg:hidden fixed inset-0 bg-slate-900/30 backdrop-blur-xs z-40 transition-opacity"
      ></div>

      <!-- Mobile Sidebar Drawer -->
      <aside 
        :class="[
          'lg:hidden fixed top-0 right-0 bottom-0 w-72 bg-white border-l border-slate-200 z-50 p-4 overflow-y-auto transform transition-transform duration-300 ease-in-out shadow-2xl',
          isMobileMenuOpen ? 'translate-x-0' : 'translate-x-full'
        ]"
      >
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100">
          <div class="flex items-center gap-2.5">
            <img :src="logo" alt="Wavixa" class="h-7 w-auto object-contain" />
            <div class="flex flex-col">
              <span class="font-black text-slate-900 text-sm">لوحة الإدارة</span>
              <span class="text-[10px] text-slate-400">Wavixa Admin</span>
            </div>
          </div>
          <button @click="isMobileMenuOpen = false" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-xl hover:bg-slate-100 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="space-y-5">
          <div v-for="section in adminMenuSections" :key="section.title" class="space-y-1.5">
            <div class="px-2.5 flex items-center justify-between text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">
              <div class="flex items-center gap-1.5">
                <span v-if="section.colorDot" class="w-1.5 h-1.5 rounded-full" :class="section.colorDot"></span>
                <span>{{ section.title }}</span>
              </div>
              <span v-if="section.badge" class="text-[9px] px-1.5 py-0.5 rounded font-extrabold" :class="section.badgeClass || 'bg-slate-100 text-slate-600'">
                {{ section.badge }}
              </span>
            </div>

            <router-link
              v-for="item in section.items"
              :key="item.path"
              :to="item.path"
              @click="isMobileMenuOpen = false"
              :class="[
                'flex items-center gap-2.5 px-3 py-2 rounded-xl transition-all text-right text-xs group relative',
                isActive(item.path)
                  ? 'bg-emerald-50 text-emerald-800 font-black shadow-2xs border border-emerald-200/60'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-semibold'
              ]"
            >
              <span 
                v-if="isActive(item.path)" 
                class="absolute top-2 bottom-2 right-1 w-1 rounded-full bg-emerald-600"
              ></span>

              <div 
                class="w-7 h-7 flex items-center justify-center rounded-lg transition-all"
                :class="[
                  isActive(item.path) 
                    ? 'bg-emerald-600 text-white shadow-xs' 
                    : 'text-slate-400 group-hover:text-emerald-700 group-hover:bg-emerald-50/70 bg-slate-50'
                ]"
              >
                <Icon :name="item.icon" class="w-4 h-4" />
              </div>

              <span class="flex-1 truncate">{{ item.label }}</span>
            </router-link>
          </div>
        </div>
      </aside>

      <!-- Main Content Area -->
      <main class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8fafc] admin-content-wrapper">
        <slot></slot>
      </main>

    </div>
  </div>
</template>

<script>
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import auth from '@/services/auth';
import Icon from '@/components/Icon.vue';
import logo from '@/assets/logo.png';

export default {
  name: 'AdminLayout',
  components: { Icon },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const user = auth.getUser();
    const isMobileMenuOpen = ref(false);

    const currentRoute = computed(() => route.path);

    const adminMenuSections = [
      {
        title: 'الإدارة والمستخدمين',
        colorDot: 'bg-indigo-500',
        items: [
          { path: '/admin', label: 'الرئيسية والإحصائيات', icon: 'shield' },
          { path: '/admin/users', label: 'إدارة المستخدمين', icon: 'users' },
          { path: '/admin/messages', label: 'سجل الرسائل', icon: 'message' },
        ]
      },
      {
        title: 'خدمات WhatsApp',
        colorDot: 'bg-emerald-500',
        badge: 'WhatsApp',
        badgeClass: 'bg-emerald-50 text-emerald-700 border border-emerald-200/60',
        items: [
          { path: '/admin/sessions', label: 'جلسات WhatsApp', icon: 'whatsapp' },
          { path: '/admin/devices', label: 'الأجهزة المتصلة', icon: 'device' },
          { path: '/admin/queue', label: 'طابور الإرسال (Queue)', icon: 'queue' },
        ]
      },
      {
        title: 'خدمات Telegram',
        colorDot: 'bg-sky-500',
        badge: 'Telegram',
        badgeClass: 'bg-sky-50 text-sky-700 border border-sky-200/60',
        items: [
          { path: '/admin/telegram-sessions', label: 'بوتات وجلسات Telegram', icon: 'telegram' },
          { path: '/admin/telegram-messages', label: 'رسائل Telegram', icon: 'message' },
          { path: '/admin/telegram-campaigns', label: 'حملات Telegram', icon: 'campaign' },
        ]
      },
      {
        title: 'التكامل والمطورين',
        colorDot: 'bg-amber-500',
        badge: 'API & Ads',
        badgeClass: 'bg-amber-50 text-amber-700 border border-amber-200/60',
        items: [
          { path: '/admin/meta-ads', label: 'إعلانات Meta Ads', icon: 'meta' },
          { path: '/admin/api-keys', label: 'مفاتيح API', icon: 'key' },
          { path: '/admin/webhooks', label: 'الويب هوكس (Webhooks)', icon: 'webhook' },
          { path: '/admin/api-monitoring', label: 'مراقبة واجهة API', icon: 'stats' },
        ]
      },
      {
        title: 'المالية والخطط',
        colorDot: 'bg-emerald-600',
        badge: 'Billing',
        badgeClass: 'bg-emerald-50 text-emerald-700 border border-emerald-200/60',
        items: [
          { path: '/admin/subscriptions', label: 'الاشتراكات النشطة', icon: 'subscription' },
          { path: '/admin/plans', label: 'الخطط والباقات', icon: 'plan' },
          { path: '/admin/payments', label: 'سجل المدفوعات', icon: 'payment' },
        ]
      },
      {
        title: 'المراقبة والأمان',
        colorDot: 'bg-rose-500',
        items: [
          { path: '/admin/security', label: 'مركز الأمان والحماية', icon: 'security' },
          { path: '/admin/analytics', label: 'التحليلات الشاملة', icon: 'analytics' },
          { path: '/admin/system-stats', label: 'إحصائيات النظام', icon: 'stats' },
          { path: '/admin/live-events', label: 'الأحداث المباشرة', icon: 'activity' },
          { path: '/admin/activity-logs', label: 'سجل الأنشطة', icon: 'logs' },
          { path: '/admin/audit-logs', label: 'سجل التدقيق', icon: 'audit' },
        ]
      },
      {
        title: 'إعدادات النظام',
        colorDot: 'bg-slate-400',
        items: [
          { path: '/admin/notifications', label: 'مركز الإشعارات', icon: 'notification' },
          { path: '/admin/settings', label: 'إعدادات النظام', icon: 'settings' },
        ]
      }
    ];

    const isActive = (path) => {
      if (path === '/admin') {
        return currentRoute.value === '/admin';
      }
      return currentRoute.value === path || currentRoute.value.startsWith(`${path}/`);
    };

    const logout = () => {
      auth.logout();
      router.push('/login');
    };

    return {
      logo,
      user,
      isMobileMenuOpen,
      adminMenuSections,
      isActive,
      logout,
    };
  },
};
</script>

