<template>
  <div class="min-h-screen bg-[#f8fafc] flex flex-col font-sans selection:bg-indigo-500/20 selection:text-indigo-900" :dir="isRTL ? 'rtl' : 'ltr'">
    <OnboardingWelcome
      :visible="welcomeVisible"
      @start="startTour"
      @skip="skipTour"
    />
    <!-- Sidebar -->
    <aside 
      class="fixed inset-y-0 z-50 w-64 max-w-[85vw] bg-white transition-all duration-300 transform lg:translate-x-0 shadow-sm"
      :class="[
        isRTL ? 'right-0 border-l border-slate-200/80' : 'left-0 border-r border-slate-200/80',
        mobileOpen
          ? 'translate-x-0'
          : (isRTL ? 'translate-x-full lg:translate-x-0' : '-translate-x-full lg:translate-x-0')
      ]"
    >
      <div class="flex flex-col h-full bg-white">
        <!-- Brand Header with Logo -->
        <div class="h-16 flex items-center justify-between px-4 border-b border-slate-100 bg-gradient-to-b from-slate-50/60 to-white">
          <router-link to="/dashboard" data-tour="sidebar-brand" class="flex items-center gap-2.5 group">
            <div class="p-1 rounded-xl bg-slate-50 border border-slate-100 group-hover:border-indigo-100 transition-colors">
              <img :src="logo" alt="Wavixa" class="h-7 w-auto object-contain transition-transform duration-200 group-hover:scale-105" />
            </div>
            <div class="flex flex-col">
              <div class="flex items-center gap-1.5">
                <span class="text-base font-black tracking-tight text-slate-900">Wavixa</span>
                <span class="text-[9px] font-extrabold px-1.5 py-0.2 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-100/70 uppercase">PRO</span>
              </div>
              <span class="text-[10px] font-semibold text-slate-400 -mt-0.5">{{ t('common.brand_tagline') }}</span>
            </div>
          </router-link>
          <div class="flex items-center gap-1">
            <LanguageSwitcher class="lg:hidden scale-90" />
            <button @click="mobileOpen = false" class="lg:hidden p-1.5 text-slate-400 hover:text-slate-700 rounded-xl hover:bg-slate-100 transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
        </div>

        <!-- Navigation Categories -->
        <nav class="flex-1 overflow-y-auto py-3.5 px-2.5 space-y-4 custom-scrollbar">
          <div v-for="section in menuSections" :key="section.title" class="space-y-1">
            <!-- Section Header -->
            <div
              v-if="section.tourKey"
              :data-tour="section.tourKey"
              class="px-2.5 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1"
            >
              <div class="flex items-center gap-1.5">
                <span v-if="section.colorDot" class="w-1.5 h-1.5 rounded-full" :class="section.colorDot"></span>
                <span>{{ section.title }}</span>
              </div>
              <span v-if="section.badge" class="text-[9px] px-1.5 py-0.2 rounded font-extrabold" :class="section.badgeClass || 'bg-slate-100 text-slate-600'">
                {{ section.badge }}
              </span>
            </div>
            <div v-else class="px-2.5 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
              <div class="flex items-center gap-1.5">
                <span v-if="section.colorDot" class="w-1.5 h-1.5 rounded-full" :class="section.colorDot"></span>
                <span>{{ section.title }}</span>
              </div>
              <span v-if="section.badge" class="text-[9px] px-1.5 py-0.2 rounded font-extrabold" :class="section.badgeClass || 'bg-slate-100 text-slate-600'">
                {{ section.badge }}
              </span>
            </div>

            <!-- Items -->
            <template v-for="item in section.items" :key="item.path">
              <a
                v-if="!item.pro || isPro"
                :href="item.disabled ? undefined : item.path"
                @click.prevent="handleNav(item)"
                :aria-disabled="item.disabled"
                :tabindex="item.disabled ? -1 : 0"
                :data-tour="item.path"
                :data-path="item.path"
                class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl text-xs font-bold transition-all group relative cursor-pointer"
                :class="[
                  isActive(item.path)
                    ? 'bg-indigo-50/90 text-indigo-700 shadow-2xs border border-indigo-200/60 font-black'
                    : (item.disabled ? 'text-slate-400 cursor-not-allowed opacity-60' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold')
                ]"
              >
                <!-- Active Indicator Bar -->
                <span 
                  v-if="isActive(item.path)" 
                  class="absolute top-2 bottom-2 w-1 rounded-full bg-indigo-600"
                  :class="isRTL ? 'right-1' : 'left-1'"
                ></span>

                <!-- Icon Container -->
                <span 
                  class="w-6 h-6 flex items-center justify-center rounded-lg transition-all shrink-0"
                  :class="[
                    isActive(item.path) 
                      ? 'bg-indigo-600 text-white shadow-xs' 
                      : (item.disabled ? 'text-slate-400 bg-slate-100' : 'text-slate-400 group-hover:text-indigo-600 group-hover:bg-indigo-50/60 bg-slate-50')
                  ]"
                >
                  <Icon :name="item.iconName || 'default'" class="w-3.5 h-3.5" />
                </span>

                <span class="flex-1 truncate" :class="isRTL ? 'text-right' : 'text-left'">{{ item.label }}</span>

                <!-- Badges -->
                <span v-if="item.pro" class="bg-gradient-to-r from-amber-500 to-amber-600 text-white text-[9px] px-1.5 py-0.5 rounded-md font-black shadow-2xs uppercase">
                  PRO
                </span>
                <span v-else-if="item.businessOnly" class="bg-indigo-50 text-indigo-700 border border-indigo-200/70 text-[9px] px-1.5 py-0.5 rounded-md font-extrabold uppercase">
                  {{ item.badge || 'Business' }}
                </span>
                <span v-else-if="item.badge" class="bg-indigo-100 text-indigo-700 text-[9px] px-1.5 py-0.5 rounded-full font-bold">
                  {{ item.badge }}
                </span>
              </a>
            </template>
          </div>

          <!-- Switch to Admin Dashboard if admin -->
          <div v-if="user?.is_admin" class="pt-2.5 border-t border-slate-100">
            <router-link
              to="/admin"
              @click="mobileOpen = false"
              class="flex items-center gap-2 px-2.5 py-2 rounded-xl text-xs font-black bg-gradient-to-r from-emerald-500/10 to-teal-500/10 text-emerald-800 hover:from-emerald-500/15 hover:to-teal-500/15 border border-emerald-200/80 transition-all shadow-2xs group"
            >
              <span class="w-6 h-6 rounded-lg bg-emerald-600 text-white flex items-center justify-center shadow-2xs group-hover:scale-105 transition-transform shrink-0">
                <Icon name="shield" class="w-3.5 h-3.5" />
              </span>
              <span class="flex-1 truncate">{{ t('nav.admin_dashboard') }}</span>
              <span class="text-[9px] bg-emerald-600 text-white px-1.5 py-0.5 rounded-md font-black uppercase shadow-2xs">{{ t('common.admin_badge') }}</span>
            </router-link>
          </div>
        </nav>

        <!-- Sidebar Footer: Plan & Profile -->
        <div class="p-2.5 border-t border-slate-100 bg-slate-50/70 space-y-2">
          <!-- Plan Info Card -->
          <div data-tour="sidebar-plan-card" class="p-2.5 rounded-xl border transition-all" :class="outOfCredits ? 'bg-red-50/90 border-red-200 shadow-2xs' : 'bg-white border-slate-200/80 shadow-2xs'">
            <div class="flex items-center justify-between mb-1.5">
              <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full" :class="outOfCredits ? 'bg-red-500 animate-pulse' : 'bg-emerald-500'"></span>
                <span class="text-[9px] font-black uppercase tracking-wider" :class="outOfCredits ? 'text-red-700' : 'text-slate-500'">{{ t('common.current_plan') }}</span>
              </div>
              <span class="text-[9px] font-black px-1.5 py-0.5 rounded-md uppercase" :class="outOfCredits ? 'bg-red-100 text-red-700 border border-red-200' : 'bg-indigo-50 text-indigo-700 border border-indigo-100'">
                {{ subscription?.plan?.name || user?.subscription_plan || t('common.free') }}
              </span>
            </div>

            <div class="text-xs font-black mb-2 flex items-baseline justify-between" :class="outOfCredits ? 'text-red-800' : 'text-slate-800'">
              <span class="text-xs tracking-tight">{{ Number(subscription?.messages_remaining ?? user?.messages_balance ?? 0).toLocaleString() }}</span>
              <span class="text-[9px] font-semibold text-slate-400">{{ t('common.remaining_messages') }}</span>
            </div>

            <router-link 
              to="/subscription" 
              class="block w-full text-center py-1.5 text-white text-[11px] font-black rounded-lg transition-all shadow-xs hover:shadow active:scale-98" 
              :class="outOfCredits ? 'bg-red-600 hover:bg-red-700' : 'bg-indigo-600 hover:bg-indigo-700'"
            >
              {{ outOfCredits ? t('common.buy_credits_now') : t('common.upgrade_plan') }}
            </router-link>
          </div>

          <!-- User Profile -->
          <div class="flex items-center gap-2 px-2 py-1 bg-white rounded-xl border border-slate-100 shadow-2xs">
            <div class="relative shrink-0">
              <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-black shadow-2xs">
                {{ userInitials }}
              </div>
              <span class="absolute -bottom-0.5 -right-0.5 w-2 h-2 bg-emerald-500 border-2 border-white rounded-full"></span>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-black text-slate-800 truncate leading-tight">{{ user?.name || t('common.user') }}</p>
              <p class="text-[9px] text-slate-400 truncate leading-tight mt-0.5">{{ user?.email }}</p>
            </div>
            <router-link to="/settings" class="p-1 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-colors" :title="t('nav.settings')">
              <Icon name="settings" class="w-3.5 h-3.5" />
            </router-link>
            <button @click="logout" class="p-1 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors cursor-pointer" :title="t('common.logout')">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Content Area -->
    <div class="flex-1 flex flex-col min-h-screen" :class="isRTL ? 'lg:pr-64' : 'lg:pl-64'">
      <!-- Top Header -->
      <header class="h-16 bg-white/95 backdrop-blur border-b border-slate-200/80 sticky top-0 z-40 flex items-center justify-between px-4 lg:px-8 shadow-xs">
        <div class="flex items-center gap-3">
          <button @click="mobileOpen = true" class="lg:hidden p-2 text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
          </button>
          <div class="min-w-0">
            <h1 class="text-base sm:text-lg font-black text-slate-900 tracking-tight truncate">{{ pageTitle }}</h1>
          </div>
        </div>

        <div class="flex items-center gap-2 lg:gap-3.5">
          <!-- Global Search -->
          <div data-tour="search" class="hidden md:flex items-center bg-slate-100/80 rounded-full px-3.5 py-1.5 w-64 border border-slate-200/60 focus-within:border-indigo-400 focus-within:bg-white focus-within:ring-2 focus-within:ring-indigo-100 transition-all group relative">
            <svg class="w-4 h-4 text-slate-400 group-focus-within:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            <input 
              v-model="searchQuery" 
              @focus="searchOpen = true" 
              @keydown.down.prevent="moveDown" 
              @keydown.up.prevent="moveUp" 
              @keydown.enter="selectResult" 
              @keydown.esc="searchOpen = false" 
              type="text" 
              :placeholder="t('common.search')" 
              class="bg-transparent border-none focus:outline-none text-xs w-full pr-2 text-slate-800 placeholder-slate-400"
            />
            <span class="text-[9px] font-black text-slate-400 bg-white border border-slate-200 px-1.5 py-0.5 rounded shadow-2xs mr-1">{{ t('common.search_shortcut') }}</span>
            
            <!-- Quick Search Dropdown -->
            <div v-if="searchOpen" class="absolute top-full right-0 left-0 mt-2 bg-white border border-slate-100 rounded-2xl shadow-xl p-2 z-50 max-h-64 overflow-y-auto">
              <button
                v-for="(page, i) in filteredPages"
                :key="page.path"
                @click="goTo(page.path)"
                class="block w-full px-3.5 py-2 rounded-xl text-xs font-bold transition-colors"
                :class="[isRTL ? 'text-right' : 'text-left', i === selectedIndex ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700 hover:bg-slate-50']"
              >
                {{ page.label }}
                <span class="text-[10px] font-normal text-slate-400 block">{{ page.path }}</span>
              </button>
              <p v-if="!filteredPages.length" class="text-xs text-slate-400 text-center py-4">{{ t('common.search_empty') }}</p>
            </div>
          </div>

          <!-- Language Switcher -->
          <LanguageSwitcher data-tour="language" class="hidden lg:inline-flex" />

          <!-- Reputation Score -->
          <ReputationBadge />

          <!-- Help Button -->
          <a
            href="https://wa.me/201024520482"
            target="_blank"
            data-tour="help"
            class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 rounded-xl border border-emerald-200/60 transition-colors"
          >
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.5 2 2 6.5 2 12c0 1.8.5 3.5 1.3 5L2 22l5.2-1.2c1.5.8 3.2 1.3 5 1.3 5.5 0 10-4.5 10-10S17.5 2 12 2zm0 18c-1.6 0-3.1-.4-4.4-1.2l-.3-.2-3.1.7.7-3-.2-.3C4.2 14.6 4 13.4 4 12c0-4.4 3.6-8 8-8s8 3.6 8 8-3.6 8-8 8z"/><path d="M15.6 13.4c-.3-.2-.6-.3-1-.2l-1.2.3c-.3.1-.5 0-.7-.2l-.2-.2c-.2-.2-.4-.5-.5-.8-.1-.3-.1-.6 0-.9l.2-.2c.2-.2.3-.5.2-.8-.1-.4-.4-1-.6-1.3-.3-.4-.7-.6-1.2-.6H10c-.4 0-.7.1-1 .4-.3.3-.4.7-.4 1.1 0 .4.1.8.3 1.2.3.6.7 1.2 1.2 1.7.6.6 1.3 1.1 2.1 1.5.7.3 1.4.5 2.1.5.5 0 .9-.1 1.3-.4.4-.2.6-.6.7-1 .1-.4 0-.7-.2-1-.2-.2-.5-.3-.8-.3z"/></svg>
            <span>{{ t('common.help') }}</span>
          </a>

          <!-- Documentation Link -->
          <router-link 
            to="/api-docs" 
            data-tour="api-docs"
            class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-slate-600 hover:text-slate-900 bg-slate-50 hover:bg-slate-100 rounded-xl border border-slate-200/60 transition-colors"
          >
            <Icon name="docs" class="w-3.5 h-3.5 text-slate-500" />
            <span>{{ t('common.api_guide') }}</span>
          </router-link>

          <!-- Action Button -->
          <router-link 
            v-if="!outOfCredits" 
            to="/send-message" 
            data-tour="new-message"
            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-all shadow-xs active:scale-95"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span class="hidden sm:inline">{{ t('common.new_message') }}</span>
          </router-link>
          <router-link 
            v-else 
            to="/subscription" 
            data-tour="new-message"
            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl transition-all shadow-xs active:scale-95"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span class="hidden sm:inline">{{ t('common.buy_credits') }}</span>
          </router-link>
        </div>
      </header>

      <!-- Low Credits Warning Banner -->
      <div v-if="outOfCredits" class="bg-red-50 border-b border-red-100 px-4 lg:px-8 py-2.5">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
          <p class="text-xs font-bold text-red-700">{{ t('banners.out_of_credits') }}</p>
          <router-link to="/subscription" class="text-xs font-black text-red-700 underline hover:text-red-800">{{ t('banners.out_of_credits_link') }} ←</router-link>
        </div>
      </div>

      <!-- Account Notice Banner -->
      <div v-if="false" class="bg-amber-50 border-b border-amber-100 px-4 lg:px-8 py-2.5">
        <div class="max-w-7xl mx-auto flex items-center justify-center gap-2">
          <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <p class="text-xs font-bold text-amber-700">{{ t('banners.no_campaigns') }}</p>
        </div>
      </div>

      <!-- Campaigns Delay Notice Banner -->
      <div v-if="false" class="bg-amber-50 border-b border-amber-100 px-4 lg:px-8 py-2.5">
        <div class="max-w-7xl mx-auto flex items-center justify-center gap-2">
          <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <p class="text-xs font-bold text-amber-700">{{ t('banners.campaigns_delay') }}</p>
        </div>
      </div>

      <!-- Main Content Container -->
      <main class="flex-1 p-4 sm:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto">
          <slot></slot>
        </div>
      </main>
    </div>

    <!-- Mobile Overlay -->
    <div v-if="mobileOpen" @click="mobileOpen = false" class="fixed inset-0 bg-slate-900/30 backdrop-blur-xs z-40 lg:hidden transition-opacity"></div>

    <!-- Pro Feature Modal -->
    <div v-if="showProModal" @click.self="showProModal = false" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center z-50 p-4">
      <div class="bg-white border border-slate-100 rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-8 text-center" :dir="isRTL ? 'rtl' : 'ltr'">
        <div class="w-14 h-14 bg-amber-50 text-amber-600 border border-amber-200/60 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-xs">
          <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
        </div>
        <h3 class="text-lg font-black text-slate-900 mb-2">{{ t('pro_modal.title') }}</h3>
        <p class="text-xs text-slate-500 mb-6 leading-relaxed">{{ t('pro_modal.description') }}</p>
        <div class="flex justify-center gap-3">
          <router-link to="/subscription" @click="showProModal = false" class="px-5 py-2.5 bg-indigo-600 text-white text-xs font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-xs active:scale-95">{{ t('common.upgrade_now') }}</router-link>
          <button @click="showProModal = false" class="px-5 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-100 transition-colors">{{ t('common.close') }}</button>
        </div>
      </div>
    </div>

    <!-- AI Voice Assistant -->
    <AIFloatingButton
      v-if="showVoiceButton"
      :is-listening="voiceOpen"
      @click="voiceOpen = true"
    />
    <VoiceAssistantPanel
      :is-open="voiceOpen"
      @close="voiceOpen = false"
    />
  </div>
</template>

<script>
import { ref, computed, onMounted, watch, onUnmounted, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import auth from '@/services/auth';
import api from '@/services/api';
import { t, setLocale, locale, isRTL } from '@/i18n';
import Icon from '@/components/Icon.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import ReputationBadge from '@/components/ReputationBadge.vue';
import OnboardingWelcome from '@/components/OnboardingWelcome.vue';
import AIFloatingButton from '@/components/AIFloatingButton.vue';
import VoiceAssistantPanel from '@/components/VoiceAssistantPanel.vue';
import { useOnboarding } from '@/composables/useOnboarding';
import logo from '@/assets/logo.png';

export default {
  name: 'DashboardLayout',
  components: { Icon, LanguageSwitcher, ReputationBadge, OnboardingWelcome, AIFloatingButton, VoiceAssistantPanel },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const mobileOpen = ref(false);
    const user = ref(auth.getUser());
    const subscription = ref(null);
    const campaignStats = ref(null);
    const creditsLoading = ref(false);
    const showProModal = ref(false);
    const searchQuery = ref('');
    const searchOpen = ref(false);
    const selectedIndex = ref(0);
    const voiceOpen = ref(false);

    const { welcomeVisible, start: startTour, skip: skipTour, load: loadOnboarding } = useOnboarding();

    const outOfCredits = computed(() => {
      if (!subscription.value) return false;
      return Number(subscription.value.messages_remaining ?? 0) <= 0 || subscription.value.status !== 'active';
    });

    const showVoiceButton = computed(() => {
      if (!user.value || voiceOpen.value) return false;
      return route.meta?.layout === 'dashboard';
    });

    const loadSubscription = async () => {
      try {
        creditsLoading.value = true;
        const response = await api.get('/subscription');
        if (response.success) {
          subscription.value = response.data;
        }
      } catch (err) {
        console.error('Failed to load subscription:', err);
      } finally {
        creditsLoading.value = false;
      }
    };

    const loadCampaignStats = async () => {
      campaignStats.value = null;
      return;
    };

    const onTourSidebar = (e) => {
      if (window.innerWidth < 1024) {
        mobileOpen.value = e?.detail?.open ?? false;
      }
    };

    onMounted(() => {
      loadSubscription();
      loadCampaignStats();
      loadOnboarding();
      scrollActiveLinkIntoView();
      window.addEventListener('wavixa:tour-sidebar', onTourSidebar);
    });

    onUnmounted(() => {
      window.removeEventListener('wavixa:tour-sidebar', onTourSidebar);
    });

    watch(() => route.path, () => {
      loadCampaignStats();
      scrollActiveLinkIntoView();
    });

    const userInitials = computed(() => {
      if (!user.value?.name) return 'U';
      return user.value.name.split(' ').map((n) => n[0]).join('').substring(0, 2).toUpperCase();
    });

    const showCampaignsDelayBanner = computed(() => {
      return false;
    });

    const pageTitle = computed(() => {
      const titles = {
        '/dashboard': t('page_titles.dashboard'),
        '/messages': t('page_titles.messages'),
        '/contacts': t('page_titles.contacts'),
        '/devices': t('page_titles.devices'),
        '/send-message': t('page_titles.send_message'),
        '/auto-reply': t('page_titles.auto_reply'),
        '/telegram/devices': t('page_titles.telegram_devices'),
        '/telegram/send-message': t('page_titles.telegram_send_message'),
        '/telegram/auto-reply': t('page_titles.telegram_auto_reply'),
        '/telegram/campaigns': t('page_titles.telegram_campaigns'),
        '/telegram/campaigns/new': t('page_titles.telegram_campaigns_new'),
        '/messenger': t('page_titles.messenger_dashboard'),
        '/messenger/connect': t('page_titles.messenger_pages'),
        '/messenger/inbox': t('page_titles.messenger_inbox'),
        '/messenger/contacts': t('page_titles.messenger_contacts'),
        '/messenger/auto-reply': t('page_titles.messenger_auto_reply'),
        '/messenger/campaigns': t('page_titles.messenger_campaigns'),
        '/messenger/campaigns/new': t('page_titles.messenger_campaigns_new'),
        '/messenger/campaigns/:id': t('page_titles.messenger_campaigns'),
        '/messenger/ads': 'إعلانات الصفحة',
        '/messenger/settings': t('page_titles.messenger_settings'),
        '/meta/dashboard': t('nav.whatsapp_business'),
        '/meta/connect': t('meta.nav.connect'),
        '/meta/chats': t('meta.nav.chats'),
        '/meta/campaigns': t('meta.nav.campaigns'),
        '/meta/templates': t('meta.nav.templates'),
        '/meta/phone-numbers': t('meta.nav.numbers'),
        '/meta/accounts': t('meta.nav.accounts'),
        '/meta/messages': t('meta.nav.messages'),
        '/meta/report': t('meta.nav.report'),
        '/meta/status': t('meta.nav.status'),
        '/meta/logs': t('meta.nav.logs'),
        '/meta/setup': t('meta.nav.setup'),
        '/api-keys': t('page_titles.api_keys'),
        '/api-docs': t('common.api_guide'),
        '/webhooks': t('page_titles.webhooks'),
        '/n8n': t('page_titles.n8n'),
        '/subscription': t('page_titles.subscription'),
        '/settings': t('page_titles.settings'),
      };
      return titles[route.path] || t('page_titles.default');
    });

    const currentPlanSlug = computed(() => subscription.value?.plan?.slug || user.value?.subscription_plan || '');

    const autoReplyQualifies = computed(() => {
      const qualifying = ['business', 'business-yearly', 'partners', 'partners-yearly'];
      return qualifying.includes(currentPlanSlug.value);
    });

    const CAMPAIGN_PLAN_SLUGS = ['pro', 'pro-yearly', 'business', 'business-yearly', 'partners', 'partners-yearly'];
    const BUSINESS_PLAN_SLUGS = ['business', 'business-yearly', 'partners', 'partners-yearly'];

    const planSlug = computed(() => subscription.value?.plan?.slug || user.value?.subscription_plan || '');

    const isPro = computed(() => planSlug.value === 'pro');

    const planSupportsCampaigns = computed(() => CAMPAIGN_PLAN_SLUGS.includes(planSlug.value));

    const planSupportsBusinessFeatures = computed(() => BUSINESS_PLAN_SLUGS.includes(planSlug.value));

    const menuSections = computed(() => [
      {
        title: t('nav.main'),
        tourKey: 'nav-section-main',
        colorDot: 'bg-indigo-500',
        items: [
          { path: '/dashboard', label: t('nav.overview'), iconName: 'chart' },
          { path: '/messages', label: t('nav.messages'), iconName: 'message' },
          { path: '/contacts', label: t('nav.contacts'), iconName: 'contacts' },
        ]
      },
      {
        title: t('nav.whatsapp'),
        tourKey: 'nav-section-whatsapp',
        colorDot: 'bg-emerald-500',
        badge: 'WhatsApp',
        badgeClass: 'bg-emerald-50 text-emerald-700 border border-emerald-200/60',
        items: [
          { path: '/devices', label: t('nav.devices'), iconName: 'whatsapp' },
          { path: '/send-message', label: t('nav.send_message'), iconName: 'send' },
          { path: '/auto-reply', label: t('nav.auto_reply'), iconName: 'bot', badge: autoReplyQualifies.value ? undefined : t('auto_reply_page.locked_badge') },
        ]
      },
      {
        title: t('nav.whatsapp_business'),
        tourKey: 'nav-section-meta-whatsapp',
        colorDot: 'bg-emerald-600',
        badge: 'Official API',
        badgeClass: 'bg-emerald-50 text-emerald-700 border border-emerald-200/60',
        items: [
          { path: '/meta/connect', label: t('meta.nav.connect'), iconName: 'whatsapp' },
          { path: '/meta/chats', label: t('meta.nav.chats'), iconName: 'chat' },
          { path: '/meta/campaigns', label: t('meta.nav.campaigns'), iconName: 'campaign' },
          { path: '/meta/templates', label: t('meta.nav.templates'), iconName: 'docs' },
          { path: '/meta/phone-numbers', label: t('meta.nav.numbers'), iconName: 'device' },
        ]
      },
      {
        title: t('nav.developers'),
        tourKey: 'nav-section-developers',
        colorDot: 'bg-amber-500',
        badge: 'API & Integration',
        badgeClass: 'bg-amber-50 text-amber-700 border border-amber-200/60',
        items: [
          { path: '/api-keys', label: t('nav.api_keys'), iconName: 'key' },
          { path: '/webhooks', label: t('nav.webhooks'), iconName: 'webhook' },
          { path: '/n8n', label: t('nav.n8n'), iconName: 'n8n', pro: true },
          { path: '/api-docs', label: t('common.api_guide'), iconName: 'docs' },
        ]
      },
      {
        title: t('nav.telegram'),
        tourKey: 'nav-section-telegram',
        colorDot: 'bg-sky-500',
        badge: 'Telegram',
        badgeClass: 'bg-sky-50 text-sky-700 border border-sky-200/60',
        items: [
          { path: '/telegram/devices', label: t('nav.devices'), iconName: 'telegram' },
          { path: '/telegram/send-message', label: t('nav.send_message'), iconName: 'send' },
          { path: '/telegram/auto-reply', label: t('nav.auto_reply'), iconName: 'bot' },
          { path: '/telegram/campaigns', label: t('nav.campaigns'), iconName: 'campaign' },
        ].map(item => ({ ...item, disabled: !planSupportsBusinessFeatures.value, businessOnly: !planSupportsBusinessFeatures.value, badge: planSupportsBusinessFeatures.value ? undefined : 'Business' }))
      },
      {
        title: t('nav.account'),
        tourKey: 'nav-section-account',
        colorDot: 'bg-slate-400',
        items: [
          { path: '/subscription', label: t('nav.subscription'), iconName: 'subscription' },
          { path: '/settings', label: t('nav.settings'), iconName: 'settings' },
        ]
      }
    ]);

    const isActive = (path) => {
      if (path === '/dashboard') return route.path === '/dashboard';
      return route.path === path || route.path.startsWith(`${path}/`);
    };

    const scrollActiveLinkIntoView = () => {
      nextTick(() => {
        const activeEl = document.querySelector(`nav a[data-path="${route.path}"]`);
        if (activeEl) {
          activeEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    };

    const handleNav = (item) => {
      if (item.disabled) {
        return;
      }
      if (item.pro) {
        const allowed = isPro.value;
        if (!allowed) {
          showProModal.value = true;
          return;
        }
      }
      router.push(item.path);
      mobileOpen.value = false;
    };

    const searchPages = [
      { path: '/dashboard', label: t('search_pages.overview') },
      { path: '/messages', label: t('search_pages.messages') },
      { path: '/contacts', label: t('search_pages.contacts') },
      { path: '/devices', label: t('search_pages.devices') },
      { path: '/send-message', label: t('search_pages.send_message') },
      { path: '/auto-reply', label: t('search_pages.auto_reply') },
      { path: '/messenger', label: t('search_pages.messenger_dashboard') },
      { path: '/messenger/inbox', label: t('search_pages.messenger_inbox') },
      { path: '/messenger/contacts', label: t('search_pages.messenger_contacts') },
      { path: '/messenger/campaigns', label: t('search_pages.messenger_campaigns') },
      { path: '/messenger/ads', label: 'إعلانات الماسنجر' },
      { path: '/messenger/auto-reply', label: t('search_pages.messenger_auto_reply') },
      { path: '/api-keys', label: t('search_pages.api_keys') },
      { path: '/api-docs', label: t('search_pages.api_docs') },
      { path: '/webhooks', label: t('search_pages.webhooks') },
      { path: '/n8n', label: t('search_pages.n8n') },
      { path: '/subscription', label: t('search_pages.subscription') },
      { path: '/settings', label: t('search_pages.settings') },
    ];

    const adminSearchPages = computed(() => user.value?.is_admin ? [
      { path: '/admin', label: t('search_pages.admin_dashboard') },
      { path: '/admin/users', label: t('search_pages.users') },
      { path: '/admin/messages', label: t('search_pages.system_messages') },
      { path: '/admin/sessions', label: t('search_pages.sessions') },
      { path: '/admin/api-keys', label: t('search_pages.admin_api_keys') },
      { path: '/admin/subscriptions', label: t('search_pages.admin_subscriptions') },
      { path: '/admin/plans', label: t('search_pages.plans') },
      { path: '/admin/payments', label: t('search_pages.payments') },
      { path: '/admin/settings', label: t('search_pages.system_settings') },
    ] : []);

    const allSearchPages = computed(() => [...searchPages, ...adminSearchPages.value]);

    const filteredPages = computed(() => {
      const q = searchQuery.value.trim();
      if (!q) return allSearchPages.value;
      const lq = q.toLowerCase();
      return allSearchPages.value.filter(p => p.label.toLowerCase().includes(lq) || p.path.toLowerCase().includes(lq));
    });

    watch(searchQuery, () => { selectedIndex.value = 0; });

    const moveDown = () => { if (selectedIndex.value < filteredPages.value.length - 1) selectedIndex.value++; };
    const moveUp = () => { if (selectedIndex.value > 0) selectedIndex.value--; };
    const goTo = (path) => { router.push(path); searchOpen.value = false; searchQuery.value = ''; };
    const selectResult = () => {
      if (filteredPages.value[selectedIndex.value]) {
        goTo(filteredPages.value[selectedIndex.value].path);
      }
    };

    const logout = async () => {
      await auth.logout();
      router.push('/login');
    };

    return {
      logo,
      mobileOpen,
      user,
      subscription,
      campaignStats,
      creditsLoading,
      userInitials,
      pageTitle,
      outOfCredits,
      showCampaignsDelayBanner,
      menuSections,
      isActive,
      isPro,
      planSupportsCampaigns,
      showProModal,
      handleNav,
      searchQuery,
      searchOpen,
      selectedIndex,
      voiceOpen,
      showVoiceButton,
      filteredPages,
      moveDown,
      moveUp,
      selectResult,
      goTo,
      logout,
      t,
      locale,
      setLocale,
      isRTL,
      welcomeVisible,
      startTour,
      skipTour,
    };
  },
};
</script>

