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
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-1.5 flex gap-1 overflow-x-auto items-center">
        <router-link
          v-for="item in primaryItems"
          :key="item.path"
          :to="item.path"
          class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all"
          :class="isActive(item.path)
            ? 'bg-indigo-600 text-white shadow-sm'
            : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800'"
        >
          {{ t(item.key) }}
        </router-link>

        <!-- More dropdown for secondary pages -->
        <div class="relative shrink-0" ref="moreWrap">
          <button
            @click="moreOpen = !moreOpen"
            class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all flex items-center gap-1"
            :class="secondaryActive
              ? 'bg-indigo-600 text-white shadow-sm'
              : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800'"
          >
            {{ t('meta.nav.more') }}
            <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-180': moreOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <div
            v-if="moreOpen"
            class="absolute top-full mt-1.5 end-0 bg-white border border-gray-100 rounded-xl shadow-xl py-1.5 w-48 z-30"
          >
            <router-link
              v-for="item in secondaryItems"
              :key="item.path"
              :to="item.path"
              @click="moreOpen = false"
              class="block px-4 py-2 text-xs font-bold transition-colors"
              :class="isActive(item.path) ? 'text-indigo-700 bg-indigo-50/70' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
            >
              {{ t(item.key) }}
            </router-link>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 min-h-[420px] text-slate-800">
        <router-view />
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import metaTest from '@/services/metaTest';
import { isRTL, t } from '@/i18n';

const route = useRoute();
const isConnected = ref(false);

const primaryNavItems = [
  { path: '/meta/dashboard', key: 'meta.nav.dashboard' },
  { path: '/meta/connect', key: 'meta.nav.connect', onlyWhenDisconnected: true },
  { path: '/meta/chats', key: 'meta.nav.chats' },
  { path: '/meta/campaigns', key: 'meta.nav.campaigns' },
  { path: '/meta/templates', key: 'meta.nav.templates' },
  { path: '/meta/phone-numbers', key: 'meta.nav.numbers' },
];

const secondaryNavItems = [
  { path: '/meta/accounts', key: 'meta.nav.accounts' },
  { path: '/meta/messages', key: 'meta.nav.messages' },
  { path: '/meta/report', key: 'meta.nav.report' },
  { path: '/meta/status', key: 'meta.nav.status' },
  { path: '/meta/logs', key: 'meta.nav.logs' },
  { path: '/meta/setup', key: 'meta.nav.setup' },
];

const moreOpen = ref(false);
const moreWrap = ref(null);

const visibleFilter = (i) => !i.onlyWhenDisconnected || !isConnected.value;
const primaryItems = computed(() => primaryNavItems.filter(visibleFilter));
const secondaryItems = computed(() => secondaryNavItems.filter(visibleFilter));

const isActive = (path) => route.path === path || route.path.startsWith(path + '/');
const secondaryActive = computed(() => secondaryNavItems.some((i) => isActive(i.path)));

const closeMore = (e) => {
  if (moreWrap.value && !moreWrap.value.contains(e.target)) moreOpen.value = false;
};

onMounted(async () => {
  document.addEventListener('click', closeMore);
  try {
    const res = await metaTest.dashboard();
    isConnected.value = res.data?.connection_status === 'connected';
  } catch (e) { /* keep Connect visible on failure */ }
});

onUnmounted(() => {
  document.removeEventListener('click', closeMore);
});
</script>
