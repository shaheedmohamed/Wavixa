<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">
            {{ t('meta.dashboard.title') }}
          </h2>
          <p class="text-gray-500 text-sm mt-1">
            {{ isRTL ? 'حسابات ميتا المربوطة، القوالب، الرسائل والحملات الجماعية' : 'Connected Meta accounts, templates, messaging & bulk campaigns' }}
          </p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold rounded-full self-start">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          {{ isRTL ? 'رسمي · إنتاج' : 'Official · Production' }}
        </span>
      </div>

      <!-- Sub navigation -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-1.5 flex gap-1 overflow-x-auto">
        <router-link
          v-for="item in navItems"
          :key="item.path"
          :to="item.path"
          class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all"
          :class="isActive(item.path)
            ? 'bg-indigo-600 text-white shadow-sm'
            : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800'"
        >
          {{ t(item.key) }}
        </router-link>
      </div>

      <!-- Content -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 min-h-[420px] text-slate-800">
        <router-view />
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import metaTest from '@/services/metaTest';
import { isRTL, t } from '@/i18n';

const route = useRoute();
const isConnected = ref(false);

const allNavItems = [
  { path: '/meta/dashboard', key: 'meta.nav.dashboard' },
  { path: '/meta/connect', key: 'meta.nav.connect', onlyWhenDisconnected: true },
  { path: '/meta/accounts', key: 'meta.nav.accounts' },
  { path: '/meta/phone-numbers', key: 'meta.nav.numbers' },
  { path: '/meta/chats', key: 'meta.nav.chats' },
  { path: '/meta/messages', key: 'meta.nav.messages' },
  { path: '/meta/templates', key: 'meta.nav.templates' },
  { path: '/meta/campaigns', key: 'meta.nav.campaigns' },
  { path: '/meta/setup', key: 'meta.nav.setup' },
  { path: '/meta/logs', key: 'meta.nav.logs' },
  { path: '/meta/status', key: 'meta.nav.status' },
  { path: '/meta/report', key: 'meta.nav.report' },
];

const navItems = computed(() =>
  allNavItems.filter((i) => !i.onlyWhenDisconnected || !isConnected.value)
);

const isActive = (path) => route.path === path || route.path.startsWith(path + '/');

onMounted(async () => {
  try {
    const res = await metaTest.dashboard();
    isConnected.value = res.data?.connection_status === 'connected';
  } catch (e) { /* keep Connect visible on failure */ }
});
</script>
