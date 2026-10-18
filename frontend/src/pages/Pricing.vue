<template>
  <div class="min-h-screen bg-[#070b14] text-slate-100 font-sans selection:bg-emerald-500/25 selection:text-emerald-300 overflow-x-hidden w-full max-w-[100vw]" :dir="isRTL ? 'rtl' : 'ltr'">
    
    <!-- Ultra-Compact Sticky Header -->
    <header
      :class="[
        'fixed top-0 left-0 right-0 z-50 transition-all duration-200 border-b',
        scrolled
          ? 'bg-[#070b14]/95 backdrop-blur-md border-slate-800/80 py-2 shadow-xl shadow-black/40'
          : 'bg-[#070b14]/80 backdrop-blur-xs border-slate-800/40 py-2.5 sm:py-3',
      ]"
    >
      <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
          
          <!-- Logo & Meta Badge -->
          <div class="flex items-center gap-2.5">
            <router-link to="/" class="flex items-center gap-2 group">
              <div class="w-8 h-8 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-center p-1 group-hover:border-emerald-500/50 transition-colors">
                <img :src="logo" alt="Wavixa" class="h-6 w-auto object-contain" />
              </div>
              <div class="flex flex-col">
                <div class="flex items-center gap-1.5">
                  <span class="text-base sm:text-lg font-black tracking-tight text-white leading-none">WAVIXA</span>
                </div>
                <span class="text-[9px] font-bold text-slate-400 leading-tight">{{ t('landing.brand_tagline') }}</span>
              </div>
            </router-link>

            <!-- Meta Partner Pill -->
            <div class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-950/80 border border-emerald-500/30 text-[10px] font-bold text-emerald-400">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
              <span>{{ t('landing.meta_partner') }}</span>
            </div>
          </div>

          <!-- Desktop Quick Nav -->
          <nav class="hidden md:flex items-center gap-1 bg-slate-900/60 border border-slate-800/80 rounded-full px-2 py-1">
            <router-link to="/" class="px-3 py-1 text-xs font-bold text-slate-300 hover:text-white rounded-full hover:bg-slate-800/60 transition-colors">{{ isRTL ? 'الرئيسية' : 'Home' }}</router-link>
            <router-link to="/#channels" class="px-3 py-1 text-xs font-bold text-slate-300 hover:text-white rounded-full hover:bg-slate-800/60 transition-colors">{{ t('landing.nav_channels') }}</router-link>
            <router-link to="/#features" class="px-3 py-1 text-xs font-bold text-slate-300 hover:text-white rounded-full hover:bg-slate-800/60 transition-colors">{{ t('landing.nav_features') }}</router-link>
            <router-link to="/pricing" class="px-3 py-1 text-xs font-black text-emerald-400 bg-slate-800/80 rounded-full transition-colors">{{ t('landing.nav_pricing') }}</router-link>
            <router-link to="/api-docs" class="px-3 py-1 text-xs font-bold text-slate-300 hover:text-white rounded-full hover:bg-slate-800/60 transition-colors">{{ t('landing.nav_api_docs') }}</router-link>
          </nav>

          <!-- Auth CTA & Language -->
          <div class="flex items-center gap-1.5 sm:gap-2">
            <div class="hidden md:block"><LanguageSwitcher class="scale-90" /></div>
            
            <template v-if="isAuthenticated">
              <router-link 
                to="/dashboard" 
                class="bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 px-3.5 py-1.5 rounded-xl text-xs font-black transition-all shadow-md shadow-emerald-500/20 active:scale-95"
              >
                {{ t('landing.dashboard') }}
              </router-link>
            </template>
            <template v-else>
              <router-link 
                to="/login" 
                class="hidden xs:inline-block text-xs font-bold text-slate-300 hover:text-white px-2.5 py-1.5 rounded-lg hover:bg-slate-800/50 transition-colors"
              >
                {{ t('landing.login') }}
              </router-link>
              <router-link 
                to="/register" 
                class="bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 px-3 sm:px-4 py-1.5 rounded-xl text-xs font-black transition-all shadow-md shadow-emerald-500/20 active:scale-95 flex items-center gap-1"
              >
                <span>{{ t('landing.start_free') }}</span>
                <span class="text-[10px] opacity-80">⚡</span>
              </router-link>
            </template>

            <!-- Mobile Menu Toggle -->
            <button class="md:hidden p-1.5 text-slate-300 hover:text-white hover:bg-slate-800 rounded-lg transition-colors" @click="isMenuOpen = !isMenuOpen" aria-label="Menu">
              <Menu v-if="!isMenuOpen" class="w-5 h-5" />
              <X v-else class="w-5 h-5" />
            </button>
          </div>

        </div>
      </div>

      <!-- Mobile Dropdown -->
      <div v-if="isMenuOpen" class="md:hidden bg-[#0a0f1d] border-b border-slate-800 px-4 py-3 space-y-2 text-xs font-bold animate-fadeIn">
        <router-link @click="isMenuOpen = false" to="/" class="block py-1.5 text-slate-300 hover:text-white">{{ isRTL ? 'الرئيسية' : 'Home' }}</router-link>
        <router-link @click="isMenuOpen = false" to="/#channels" class="block py-1.5 text-slate-300 hover:text-white">{{ t('landing.nav_channels') }}</router-link>
        <router-link @click="isMenuOpen = false" to="/#features" class="block py-1.5 text-slate-300 hover:text-white">{{ t('landing.nav_features') }}</router-link>
        <router-link @click="isMenuOpen = false" to="/pricing" class="block py-1.5 text-emerald-400 font-black">{{ t('landing.nav_pricing') }}</router-link>
        <router-link @click="isMenuOpen = false" to="/api-docs" class="block py-1.5 text-slate-300 hover:text-white">{{ t('landing.nav_api_docs') }}</router-link>
        <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between">
          <router-link @click="isMenuOpen = false" to="/login" class="text-slate-300 hover:text-white">{{ t('landing.login') }}</router-link>
          <router-link @click="isMenuOpen = false" to="/register" class="bg-emerald-500 text-slate-950 px-3 py-1.5 rounded-lg font-black">{{ t('landing.start_free') }}</router-link>
        </div>
        <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between">
          <span class="text-slate-500 text-[10px] font-bold">{{ isRTL ? 'اللغة' : 'Language' }}</span>
          <LanguageSwitcher />
        </div>
      </div>
    </header>

    <main class="pt-20 sm:pt-28 pb-16 sm:pb-24">
      
      <!-- HERO & PRICING HEADER -->
      <section class="relative px-3 sm:px-6 lg:px-8 mb-6 sm:mb-10 text-center">
        <!-- Subtle Ambient Lighting -->
        <div class="absolute inset-0 pointer-events-none">
          <div class="absolute top-0 right-1/2 translate-x-1/2 w-[340px] h-[340px] sm:w-[600px] sm:h-[500px] bg-emerald-500/10 rounded-full blur-[100px] sm:blur-[140px]"></div>
        </div>

        <div class="relative max-w-4xl mx-auto">
          <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[11px] font-black text-emerald-400 mb-2">
            {{ t('landing.pricing_badge') }}
          </div>
          <h1 class="text-2xl xs:text-3xl sm:text-5xl font-black text-white leading-tight mb-2 sm:mb-3">
            {{ t('landing.pricing_title') }}
          </h1>
          <p class="text-xs sm:text-base text-slate-300 max-w-xl mx-auto leading-relaxed font-medium">
            {{ t('landing.pricing_subtitle') }}
          </p>
        </div>
      </section>

      <!-- PRICING SECTION -->
      <section class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        
        <!-- Main Plan Category Filter TOGGLE (Quota vs Unlimited) -->
        <div class="flex items-center justify-center p-1 bg-slate-900/90 border border-slate-800 rounded-2xl max-w-md mx-auto mb-5 sm:mb-7 shadow-lg">
          <button
            type="button"
            @click="planCategory = 'quota'"
            :class="[
              'flex-1 py-2 px-2.5 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 cursor-pointer',
              planCategory === 'quota'
                ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-slate-950 shadow-md'
                : 'text-slate-400 hover:text-white'
            ]"
          >
            <Zap class="w-3.5 h-3.5" />
            <span>{{ t('landing.toggle_quota') }}</span>
          </button>
          <button
            type="button"
            @click="planCategory = 'unlimited'"
            :class="[
              'flex-1 py-2 px-2.5 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 cursor-pointer',
              planCategory === 'unlimited'
                ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-slate-950 shadow-md'
                : 'text-slate-400 hover:text-white'
            ]"
          >
            <Sparkles class="w-3.5 h-3.5" />
            <span>{{ t('landing.toggle_unlimited') }}</span>
          </button>
        </div>

        <!-- 2-Column Compact Paid Cards Grid (2 per row on all screens) -->
        <div class="max-w-4xl mx-auto mb-3 sm:mb-4">
          <!-- TAB 1: QUOTA PLANS (Starter & Pro) -->
          <div v-if="planCategory === 'quota'" class="grid grid-cols-2 gap-2 sm:gap-4 items-stretch">
            
            <!-- 1. Starter Plan Card -->
            <div class="relative rounded-xl sm:rounded-2xl p-2.5 sm:p-5 bg-slate-900/80 border border-slate-800 hover:border-slate-700 flex flex-col justify-between transition-all">
              <div>
                <div class="flex items-center justify-between mb-1 sm:mb-1.5">
                  <h3 class="text-xs sm:text-base font-black text-white">Starter</h3>
                  <span class="text-[8px] sm:text-[10px] font-extrabold bg-slate-800 text-slate-300 px-1.5 py-0.2 sm:px-2 sm:py-0.5 rounded">Quota</span>
                </div>
                <p class="text-[9px] sm:text-[11px] text-slate-400 mb-1.5 sm:mb-3 leading-tight line-clamp-1 sm:line-clamp-none">{{ locale === 'ar' ? 'باقة رسائل للمشاريع الصغيرة' : 'Message quota plan for small stores' }}</p>

                <!-- Card-Specific Monthly / Yearly Toggle -->
                <div class="flex items-center justify-between p-0.5 sm:p-1 bg-slate-950/80 rounded-lg sm:rounded-xl border border-slate-800/80 mb-2 sm:mb-3">
                  <span class="text-[8px] sm:text-[10px] font-bold text-slate-400 px-1 hidden xs:inline">{{ t('landing.billing_type') }}</span>
                  <div class="flex items-center gap-0.5 sm:gap-1 w-full xs:w-auto justify-center">
                    <button
                      type="button"
                      @click="cardBilling.starter = 'monthly'"
                      :class="[
                        'flex-1 xs:flex-none px-1.5 sm:px-2 py-0.5 rounded text-[8px] sm:text-[10px] font-black transition-all cursor-pointer',
                        cardBilling.starter === 'monthly' ? 'bg-slate-800 text-white shadow-xs' : 'text-slate-500 hover:text-slate-300'
                      ]"
                    >
                      {{ t('landing.monthly') }}
                    </button>
                    <button
                      type="button"
                      @click="cardBilling.starter = 'yearly'"
                      :class="[
                        'flex-1 xs:flex-none px-1.5 sm:px-2 py-0.5 rounded text-[8px] sm:text-[10px] font-black transition-all flex items-center justify-center gap-0.5 cursor-pointer',
                        cardBilling.starter === 'yearly' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 shadow-xs' : 'text-slate-500 hover:text-slate-300'
                      ]"
                    >
                      <span>{{ t('landing.yearly') }}</span>
                      <span class="text-[7px] sm:text-[8px] bg-emerald-500 text-slate-950 px-1 rounded font-extrabold">-17%</span>
                    </button>
                  </div>
                </div>

                <!-- Price & Message Display -->
                <div class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1 mb-2 sm:mb-3 pb-2 sm:pb-3 border-b border-slate-800">
                  <div class="flex items-baseline gap-1">
                    <span class="text-lg sm:text-3xl font-black text-white">
                      {{ cardBilling.starter === 'yearly' ? '$190' : '$19' }}
                    </span>
                    <span class="text-[9px] sm:text-xs text-slate-400 font-bold">
                      {{ cardBilling.starter === 'yearly' ? t('landing.per_year') : t('landing.per_month') }}
                    </span>
                  </div>
                  <span class="text-[9px] sm:text-xs font-black text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-1.5 py-0.5 rounded-md w-fit">
                    {{ cardBilling.starter === 'yearly' ? (locale === 'ar' ? '36k رسالة' : '36k Msgs') : (locale === 'ar' ? '3k رسالة' : '3k Msgs') }}
                  </span>
                </div>

                <!-- Features -->
                <div class="space-y-1 sm:space-y-1.5 mb-2.5 sm:mb-4 text-[9px] sm:text-xs text-slate-300">
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-emerald-400 shrink-0" />
                    <span class="truncate">{{ cardBilling.starter === 'yearly' ? (locale === 'ar' ? '36,000 رسالة للسنة' : '36,000 msgs / yr') : (locale === 'ar' ? '3,000 رسالة رصيد' : '3,000 msgs quota') }}</span>
                  </div>
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-emerald-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? 'جهاز WhatsApp واحد' : '1 WhatsApp Device' }}</span>
                  </div>
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-emerald-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? 'API & Webhooks كامل' : 'Full API & Webhooks' }}</span>
                  </div>
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-emerald-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? 'تقليل مخاطر الحظر' : 'Ban Risk Reduction' }}</span>
                  </div>
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-emerald-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? 'دعم فني بأولوية' : 'Priority Support' }}</span>
                  </div>
                </div>
              </div>

              <!-- Action Button -->
              <button
                @click="handlePlanSelect(cardBilling.starter === 'yearly' ? 'starter-yearly' : 'starter')"
                class="w-full text-center py-1.5 sm:py-2.5 rounded-lg sm:rounded-xl text-[10px] sm:text-xs font-black transition-all bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 block cursor-pointer"
              >
                {{ t('landing.subscribe') }}
              </button>
            </div>

            <!-- 2. Pro Plan Card (Popular) -->
            <div class="relative rounded-xl sm:rounded-2xl p-2.5 sm:p-5 bg-gradient-to-b from-slate-900 via-slate-900 to-slate-950 border-2 border-emerald-500/80 shadow-xl shadow-emerald-500/10 flex flex-col justify-between transition-all">
              <!-- Popular Badge -->
              <div class="absolute -top-2.5 right-1/2 translate-x-1/2 bg-gradient-to-r from-emerald-500 to-teal-500 text-slate-950 text-[8px] sm:text-[10px] font-black px-2 sm:px-3 py-0.2 sm:py-0.5 rounded-full uppercase shadow-md whitespace-nowrap">
                ⭐ {{ locale === 'ar' ? 'الأكثر طلباً' : 'Popular' }}
              </div>

              <div>
                <div class="flex items-center justify-between mb-1 sm:mb-1.5 pt-1">
                  <h3 class="text-xs sm:text-base font-black text-white">Pro</h3>
                  <span class="text-[8px] sm:text-[10px] font-extrabold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 px-1.5 py-0.2 sm:px-2 sm:py-0.5 rounded">Pro</span>
                </div>
                <p class="text-[9px] sm:text-[11px] text-slate-400 mb-1.5 sm:mb-3 leading-tight line-clamp-1 sm:line-clamp-none">{{ locale === 'ar' ? 'باقة متقدمة للأعمال والمتاجر' : 'Advanced package for growing stores' }}</p>

                <!-- Card-Specific Monthly / Yearly Toggle -->
                <div class="flex items-center justify-between p-0.5 sm:p-1 bg-slate-950/80 rounded-lg sm:rounded-xl border border-slate-800/80 mb-2 sm:mb-3">
                  <span class="text-[8px] sm:text-[10px] font-bold text-slate-400 px-1 hidden xs:inline">{{ t('landing.billing_type') }}</span>
                  <div class="flex items-center gap-0.5 sm:gap-1 w-full xs:w-auto justify-center">
                    <button
                      type="button"
                      @click="cardBilling.pro = 'monthly'"
                      :class="[
                        'flex-1 xs:flex-none px-1.5 sm:px-2 py-0.5 rounded text-[8px] sm:text-[10px] font-black transition-all cursor-pointer',
                        cardBilling.pro === 'monthly' ? 'bg-slate-800 text-white shadow-xs' : 'text-slate-500 hover:text-slate-300'
                      ]"
                    >
                      {{ t('landing.monthly') }}
                    </button>
                    <button
                      type="button"
                      @click="cardBilling.pro = 'yearly'"
                      :class="[
                        'flex-1 xs:flex-none px-1.5 sm:px-2 py-0.5 rounded text-[8px] sm:text-[10px] font-black transition-all flex items-center justify-center gap-0.5 cursor-pointer',
                        cardBilling.pro === 'yearly' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 shadow-xs' : 'text-slate-500 hover:text-slate-300'
                      ]"
                    >
                      <span>{{ t('landing.yearly') }}</span>
                      <span class="text-[7px] sm:text-[8px] bg-emerald-500 text-slate-950 px-1 rounded font-extrabold">-17%</span>
                    </button>
                  </div>
                </div>

                <!-- Price & Message Display -->
                <div class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1 mb-2 sm:mb-3 pb-2 sm:pb-3 border-b border-slate-800">
                  <div class="flex items-baseline gap-1">
                    <span class="text-lg sm:text-3xl font-black text-white">
                      {{ cardBilling.pro === 'yearly' ? '$490' : '$49' }}
                    </span>
                    <span class="text-[9px] sm:text-xs text-slate-400 font-bold">
                      {{ cardBilling.pro === 'yearly' ? t('landing.per_year') : t('landing.per_month') }}
                    </span>
                  </div>
                  <span class="text-[9px] sm:text-xs font-black text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-1.5 py-0.5 rounded-md w-fit">
                    {{ cardBilling.pro === 'yearly' ? (locale === 'ar' ? '180k رسالة' : '180k Msgs') : (locale === 'ar' ? '15k رسالة' : '15k Msgs') }}
                  </span>
                </div>

                <!-- Features -->
                <div class="space-y-1 sm:space-y-1.5 mb-2.5 sm:mb-4 text-[9px] sm:text-xs text-slate-300">
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-emerald-400 shrink-0" />
                    <span class="truncate">{{ cardBilling.pro === 'yearly' ? (locale === 'ar' ? '180,000 رسالة للسنة' : '180,000 msgs / yr') : (locale === 'ar' ? '15,000 رسالة رصيد' : '15,000 msgs quota') }}</span>
                  </div>
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-emerald-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? 'AI مكانك الرد الذكي' : 'AI Smart Auto-Reply' }}</span>
                  </div>
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-emerald-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? 'حملات تسويقية مجدولة' : 'Targeted Campaigns' }}</span>
                  </div>
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-emerald-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? 'عقدة n8n و Webhooks' : 'n8n Node & Webhooks' }}</span>
                  </div>
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-emerald-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? 'دعم 24/7 بأولوية' : '24/7 Priority Support' }}</span>
                  </div>
                </div>
              </div>

              <!-- Action Button -->
              <button
                @click="handlePlanSelect(cardBilling.pro === 'yearly' ? 'pro-yearly' : 'pro')"
                class="w-full text-center py-1.5 sm:py-2.5 rounded-lg sm:rounded-xl text-[10px] sm:text-xs font-black transition-all bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 shadow-md shadow-emerald-500/20 active:scale-98 block cursor-pointer"
              >
                {{ t('landing.subscribe') }}
              </button>
            </div>

          </div>

          <!-- TAB 2: UNLIMITED PLANS (Business & Partners) -->
          <div v-else class="grid grid-cols-2 gap-2 sm:gap-4 items-stretch">
            
            <!-- 1. Business Plan Card (Unlimited) -->
            <div class="relative rounded-xl sm:rounded-2xl p-2.5 sm:p-5 bg-gradient-to-b from-slate-900 to-slate-950 border-2 border-cyan-500/70 shadow-xl shadow-cyan-500/10 flex flex-col justify-between transition-all">
              <div class="absolute -top-2.5 right-1/2 translate-x-1/2 bg-gradient-to-r from-cyan-500 to-blue-500 text-slate-950 text-[8px] sm:text-[10px] font-black px-2 sm:px-3 py-0.2 sm:py-0.5 rounded-full uppercase shadow-md whitespace-nowrap">
                🚀 {{ locale === 'ar' ? 'غير محدود' : 'Unlimited' }}
              </div>

              <div>
                <div class="flex items-center justify-between mb-1 sm:mb-1.5 pt-1">
                  <h3 class="text-xs sm:text-base font-black text-white">Business</h3>
                  <span class="text-[8px] sm:text-[10px] font-extrabold bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 px-1.5 py-0.2 sm:px-2 sm:py-0.5 rounded">Unlimited</span>
                </div>
                <p class="text-[9px] sm:text-[11px] text-slate-400 mb-1.5 sm:mb-3 leading-tight line-clamp-1 sm:line-clamp-none">{{ locale === 'ar' ? 'حل متكامل بدون أي قيود' : 'All-in-one unlimited messaging' }}</p>

                <!-- Card-Specific Monthly / Yearly Toggle -->
                <div class="flex items-center justify-between p-0.5 sm:p-1 bg-slate-950/80 rounded-lg sm:rounded-xl border border-slate-800/80 mb-2 sm:mb-3">
                  <span class="text-[8px] sm:text-[10px] font-bold text-slate-400 px-1 hidden xs:inline">{{ t('landing.billing_type') }}</span>
                  <div class="flex items-center gap-0.5 sm:gap-1 w-full xs:w-auto justify-center">
                    <button
                      type="button"
                      @click="cardBilling.business = 'monthly'"
                      :class="[
                        'flex-1 xs:flex-none px-1.5 sm:px-2 py-0.5 rounded text-[8px] sm:text-[10px] font-black transition-all cursor-pointer',
                        cardBilling.business === 'monthly' ? 'bg-slate-800 text-white shadow-xs' : 'text-slate-500 hover:text-slate-300'
                      ]"
                    >
                      {{ t('landing.monthly') }}
                    </button>
                    <button
                      type="button"
                      @click="cardBilling.business = 'yearly'"
                      :class="[
                        'flex-1 xs:flex-none px-1.5 sm:px-2 py-0.5 rounded text-[8px] sm:text-[10px] font-black transition-all flex items-center justify-center gap-0.5 cursor-pointer',
                        cardBilling.business === 'yearly' ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/40 shadow-xs' : 'text-slate-500 hover:text-slate-300'
                      ]"
                    >
                      <span>{{ t('landing.yearly') }}</span>
                      <span class="text-[7px] sm:text-[8px] bg-cyan-500 text-slate-950 px-1 rounded font-extrabold">-17%</span>
                    </button>
                  </div>
                </div>

                <!-- Price & Message Display -->
                <div class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1 mb-2 sm:mb-3 pb-2 sm:pb-3 border-b border-slate-800">
                  <div class="flex items-baseline gap-1">
                    <span class="text-lg sm:text-3xl font-black text-white">
                      {{ cardBilling.business === 'yearly' ? '$1,490' : '$149' }}
                    </span>
                    <span class="text-[9px] sm:text-xs text-slate-400 font-bold">
                      {{ cardBilling.business === 'yearly' ? t('landing.per_year') : t('landing.per_month') }}
                    </span>
                  </div>
                  <span class="text-[9px] sm:text-xs font-black text-cyan-400 bg-cyan-500/10 border border-cyan-500/20 px-1.5 py-0.5 rounded-md w-fit">
                    {{ locale === 'ar' ? 'غير محدود' : 'Unlimited' }}
                  </span>
                </div>

                <!-- Features -->
                <div class="space-y-1 sm:space-y-1.5 mb-2.5 sm:mb-4 text-[9px] sm:text-xs text-slate-300">
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-cyan-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? 'رسائل وحملات غير محدودة' : 'Unlimited Messages' }}</span>
                  </div>
                  <div class="flex items-center gap-1.5 text-xs text-slate-300">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-cyan-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? 'WhatsApp + Telegram + Messenger' : 'Omnichannel Hub' }}</span>
                  </div>
                  <div class="flex items-center gap-1.5 text-xs text-slate-300">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-cyan-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? 'AI مكانك بدون سقف' : 'Uncapped AI Auto-Reply' }}</span>
                  </div>
                  <div class="flex items-center gap-1.5 text-xs text-slate-300">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-cyan-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? 'API و Webhooks كامل' : 'Full REST API & Webhooks' }}</span>
                  </div>
                  <div class="flex items-center gap-1.5 text-xs text-slate-300">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-cyan-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? 'مدير حساب مخصص 24/7' : 'Account Manager 24/7' }}</span>
                  </div>
                </div>
              </div>

              <!-- Action Button -->
              <button
                @click="handlePlanSelect(cardBilling.business === 'yearly' ? 'business-yearly' : 'business')"
                class="w-full text-center py-1.5 sm:py-2.5 rounded-lg sm:rounded-xl text-[10px] sm:text-xs font-black transition-all bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-400 hover:to-blue-400 text-slate-950 shadow-md shadow-cyan-500/20 active:scale-98 block cursor-pointer"
              >
                {{ t('landing.subscribe') }}
              </button>
            </div>

            <!-- 2. Partners Plan Card (Multi-Device Unlimited) -->
            <div class="relative rounded-xl sm:rounded-2xl p-2.5 sm:p-5 bg-slate-900/80 border border-slate-800 hover:border-slate-700 flex flex-col justify-between transition-all">
              <div>
                <div class="flex items-center justify-between mb-1 sm:mb-1.5">
                  <h3 class="text-xs sm:text-base font-black text-white">Partners</h3>
                  <span class="text-[8px] sm:text-[10px] font-extrabold bg-purple-500/20 text-purple-400 border border-purple-500/30 px-1.5 py-0.2 sm:px-2 sm:py-0.5 rounded">100 Devices</span>
                </div>
                <p class="text-[9px] sm:text-[11px] text-slate-400 mb-1.5 sm:mb-3 leading-tight line-clamp-1 sm:line-clamp-none">{{ locale === 'ar' ? 'للوكالات وإدارة حسابات متعددة' : 'For agencies managing multi-clients' }}</p>

                <!-- Card-Specific Monthly / Yearly Toggle -->
                <div class="flex items-center justify-between p-0.5 sm:p-1 bg-slate-950/80 rounded-lg sm:rounded-xl border border-slate-800/80 mb-2 sm:mb-3">
                  <span class="text-[8px] sm:text-[10px] font-bold text-slate-400 px-1 hidden xs:inline">{{ t('landing.billing_type') }}</span>
                  <div class="flex items-center gap-0.5 sm:gap-1 w-full xs:w-auto justify-center">
                    <button
                      type="button"
                      @click="cardBilling.partners = 'monthly'"
                      :class="[
                        'flex-1 xs:flex-none px-1.5 sm:px-2 py-0.5 rounded text-[8px] sm:text-[10px] font-black transition-all cursor-pointer',
                        cardBilling.partners === 'monthly' ? 'bg-slate-800 text-white shadow-xs' : 'text-slate-500 hover:text-slate-300'
                      ]"
                    >
                      {{ t('landing.monthly') }}
                    </button>
                    <button
                      type="button"
                      @click="cardBilling.partners = 'yearly'"
                      :class="[
                        'flex-1 xs:flex-none px-1.5 sm:px-2 py-0.5 rounded text-[8px] sm:text-[10px] font-black transition-all flex items-center justify-center gap-0.5 cursor-pointer',
                        cardBilling.partners === 'yearly' ? 'bg-purple-500/20 text-purple-400 border border-purple-500/40 shadow-xs' : 'text-slate-500 hover:text-slate-300'
                      ]"
                    >
                      <span>{{ t('landing.yearly') }}</span>
                      <span class="text-[7px] sm:text-[8px] bg-purple-500 text-slate-950 px-1 rounded font-extrabold">-17%</span>
                    </button>
                  </div>
                </div>

                <!-- Price & Message Display -->
                <div class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1 mb-2 sm:mb-3 pb-2 sm:pb-3 border-b border-slate-800">
                  <div class="flex items-baseline gap-1">
                    <span class="text-lg sm:text-3xl font-black text-white">
                      {{ cardBilling.partners === 'yearly' ? '$2,990' : '$299' }}
                    </span>
                    <span class="text-[9px] sm:text-xs text-slate-400 font-bold">
                      {{ cardBilling.partners === 'yearly' ? t('landing.per_year') : t('landing.per_month') }}
                    </span>
                  </div>
                  <span class="text-[9px] sm:text-xs font-black text-purple-400 bg-purple-500/10 border border-purple-500/20 px-1.5 py-0.5 rounded-md w-fit">
                    {{ locale === 'ar' ? '100 جهاز' : '100 Devs' }}
                  </span>
                </div>

                <!-- Features -->
                <div class="space-y-1 sm:space-y-1.5 mb-2.5 sm:mb-4 text-[9px] sm:text-xs text-slate-300">
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-purple-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? '100 جهاز متزامن' : '100 WhatsApp Devices' }}</span>
                  </div>
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-purple-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? 'رسائل غير محدودة' : 'Unlimited Messages' }}</span>
                  </div>
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-purple-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? 'API مخصص وتقارير' : 'Custom APIs & Reports' }}</span>
                  </div>
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-purple-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? 'أعلى أولوية وسرعة' : 'Highest Queue Priority' }}</span>
                  </div>
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-purple-400 shrink-0" />
                    <span class="truncate">{{ locale === 'ar' ? 'دعم VIP هاتفي 24/7' : '24/7 VIP Phone Support' }}</span>
                  </div>
                </div>
              </div>

              <!-- Action Button -->
              <button
                @click="handlePlanSelect(cardBilling.partners === 'yearly' ? 'partners-yearly' : 'partners')"
                class="w-full text-center py-1.5 sm:py-2.5 rounded-lg sm:rounded-xl text-[10px] sm:text-xs font-black transition-all bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 block cursor-pointer"
              >
                {{ t('landing.subscribe') }}
              </button>
            </div>

          </div>
        </div>

        <!-- FULL-WIDTH FREE PLAN CARD UNDERNEATH -->
        <div class="max-w-4xl mx-auto mb-10 sm:mb-16">
          <div class="relative rounded-xl sm:rounded-2xl p-3 sm:p-5 bg-gradient-to-r from-slate-900/90 via-slate-900/60 to-slate-900/90 border border-slate-800 hover:border-slate-700 transition-all flex flex-col md:flex-row md:items-center justify-between gap-3 sm:gap-4">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <h3 class="text-xs sm:text-base font-black text-white">{{ t('landing.free_trial_title') }}</h3>
                <span class="text-[8px] sm:text-[10px] font-black bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 px-1.5 py-0.2 sm:px-2 sm:py-0.5 rounded-md">{{ t('landing.free_trial_badge') }}</span>
              </div>
              <p class="text-[10px] sm:text-xs text-slate-400 mb-2 sm:mb-3 leading-relaxed">
                {{ t('landing.free_trial_desc') }}
              </p>

              <!-- Horizontal Feature Badges -->
              <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 text-[10px] sm:text-xs text-slate-300">
                <span class="flex items-center gap-1 bg-slate-950/80 px-2 py-0.5 sm:py-1 rounded-lg border border-slate-800 text-[10px] sm:text-[11px] font-medium">
                  <Check class="w-3 h-3 text-emerald-400" /> {{ locale === 'ar' ? '100 رسالة تجريبية' : '100 Test Messages' }}
                </span>
                <span class="flex items-center gap-1 bg-slate-950/80 px-2 py-0.5 sm:py-1 rounded-lg border border-slate-800 text-[10px] sm:text-[11px] font-medium">
                  <Check class="w-3 h-3 text-emerald-400" /> {{ locale === 'ar' ? 'جهاز WhatsApp واحد' : '1 WhatsApp Device' }}
                </span>
                <span class="flex items-center gap-1 bg-slate-950/80 px-2 py-0.5 sm:py-1 rounded-lg border border-slate-800 text-[10px] sm:text-[11px] font-medium">
                  <Check class="w-3 h-3 text-emerald-400" /> {{ locale === 'ar' ? 'مفاتيح API & Webhooks' : 'API Keys & Webhooks' }}
                </span>
                <span class="flex items-center gap-1 bg-slate-950/80 px-2 py-0.5 sm:py-1 rounded-lg border border-slate-800 text-[10px] sm:text-[11px] font-medium">
                  <Check class="w-3 h-3 text-emerald-400" /> {{ locale === 'ar' ? 'دعم فني مباشر' : 'Live Support' }}
                </span>
              </div>
            </div>

            <!-- Price & CTA in right side -->
            <div class="flex sm:flex-row md:flex-col items-center md:items-end justify-between gap-2 sm:gap-3 pt-2 md:pt-0 border-t md:border-t-0 border-slate-800">
              <div class="text-right">
                <span class="text-xl sm:text-3xl font-black text-white">0$</span>
                <span class="text-[10px] sm:text-[11px] text-slate-400 font-bold block -mt-0.5 sm:-mt-1">{{ t('landing.free_trial_lifetime') }}</span>
              </div>
              <router-link
                to="/register"
                class="bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl text-[10px] sm:text-xs font-black transition-all shadow-sm hover:text-emerald-400 cursor-pointer whitespace-nowrap"
              >
                {{ t('landing.free_trial_cta') }}
              </router-link>
            </div>
          </div>
        </div>

        <!-- HOW IT WORKS (Compact 2-Box) -->
        <div class="max-w-4xl mx-auto mb-12 sm:mb-16">
          <div class="p-4 sm:p-7 rounded-2xl bg-slate-900/60 border border-slate-800">
            <h2 class="text-base sm:text-xl font-black text-white mb-4 text-center">
              {{ locale === 'ar' ? '💡 كيف تعمل خطط وأرصدة Wavixa؟' : '💡 How Wavixa Pricing & Quotas Work' }}
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-5 text-xs text-slate-300">
              <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800/80 space-y-1.5">
                <div class="flex items-center gap-2 font-black text-emerald-400">
                  <span class="w-5 h-5 rounded-full bg-emerald-500/10 flex items-center justify-center text-[11px]">1</span>
                  <span>Starter & Pro ({{ locale === 'ar' ? 'رصيد رسائل ممتد' : 'Quota Plans' }})</span>
                </div>
                <p class="text-[11px] text-slate-400 leading-relaxed font-medium">
                  {{ locale === 'ar' 
                    ? 'أرصدة رسائل لا تنتهي بنهاية الشهر. يظل رصيدك متاحاً حتى تستهلكه بالكامل. الاشتراك السنوي يمنحك رصيد 12 شهراً دفعة واحدة مع خصم شهرين.'
                    : 'Message bundles that never expire at month-end. Credits carry over until consumed. Yearly plans grant all 12 months upfront with a 2-month discount.' }}
                </p>
              </div>

              <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800/80 space-y-1.5">
                <div class="flex items-center gap-2 font-black text-cyan-400">
                  <span class="w-5 h-5 rounded-full bg-cyan-500/10 flex items-center justify-center text-[11px]">2</span>
                  <span>Business & Partners ({{ locale === 'ar' ? 'اشتراك غير محدود' : 'Unlimited Plans' }})</span>
                </div>
                <p class="text-[11px] text-slate-400 leading-relaxed font-medium">
                  {{ locale === 'ar'
                    ? 'اشتراكات شهرية أو سنوية بدون سقف لعدد الرسائل أو الحملات، مع دعم أجهزة متعددة وأعلى أولوية إرسال مخصصة للمؤسسات.'
                    : 'Monthly or yearly subscriptions with zero message limits, multi-device support, and high-priority server queues for scaling teams.' }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- FEATURE COMPARISON TABLE (Ultra-Compact) -->
        <div class="max-w-4xl mx-auto mb-12 sm:mb-16">
          <div class="text-center mb-5">
            <h2 class="text-lg sm:text-2xl font-black text-white">{{ locale === 'ar' ? 'جدول مقارنة مميزات الباقات' : 'Complete Plan Features Matrix' }}</h2>
            <p class="text-xs text-slate-400 mt-1">{{ locale === 'ar' ? 'قارن بين كل خطة واختر الأنسب لحجم إرسالك' : 'Compare all plans and choose the ideal fit for your workload' }}</p>
          </div>

          <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-slate-900/60">
            <table class="w-full text-[11px] sm:text-xs" :class="isRTL ? 'text-right' : 'text-left'">
              <thead>
                <tr class="bg-slate-950/80 text-white font-black border-b border-slate-800">
                  <th class="p-3 sm:p-4">{{ locale === 'ar' ? 'الميزة / الخاصية' : 'Feature' }}</th>
                  <th class="p-3 sm:p-4 text-center">Free</th>
                  <th class="p-3 sm:p-4 text-center">Starter</th>
                  <th class="p-3 sm:p-4 text-center text-emerald-400">Pro</th>
                  <th class="p-3 sm:p-4 text-center text-cyan-400">Business</th>
                  <th class="p-3 sm:p-4 text-center text-purple-400">Partners</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/60 font-medium text-slate-300">
                <tr class="hover:bg-slate-800/30">
                  <td class="p-3 sm:p-4 font-bold text-white">{{ locale === 'ar' ? 'حصة الرسائل (شهري)' : 'Monthly Messages' }}</td>
                  <td class="p-3 sm:p-4 text-center text-slate-400">100</td>
                  <td class="p-3 sm:p-4 text-center font-bold">3,000</td>
                  <td class="p-3 sm:p-4 text-center font-bold text-emerald-400">15,000</td>
                  <td class="p-3 sm:p-4 text-center font-bold text-cyan-400">{{ locale === 'ar' ? 'غير محدود' : 'Unlimited' }}</td>
                  <td class="p-3 sm:p-4 text-center font-bold text-purple-400">{{ locale === 'ar' ? 'غير محدود' : 'Unlimited' }}</td>
                </tr>
                <tr class="hover:bg-slate-800/30">
                  <td class="p-3 sm:p-4 font-bold text-white">{{ locale === 'ar' ? 'حصة الرسائل (سنوي)' : 'Yearly Messages' }}</td>
                  <td class="p-3 sm:p-4 text-center text-slate-400">—</td>
                  <td class="p-3 sm:p-4 text-center font-bold">36,000</td>
                  <td class="p-3 sm:p-4 text-center font-bold text-emerald-400">180,000</td>
                  <td class="p-3 sm:p-4 text-center font-bold text-cyan-400">{{ locale === 'ar' ? 'غير محدود' : 'Unlimited' }}</td>
                  <td class="p-3 sm:p-4 text-center font-bold text-purple-400">{{ locale === 'ar' ? 'غير محدود' : 'Unlimited' }}</td>
                </tr>
                <tr class="hover:bg-slate-800/30">
                  <td class="p-3 sm:p-4 font-bold text-white">{{ locale === 'ar' ? 'أجهزة WhatsApp المتزامنة' : 'Connected Devices' }}</td>
                  <td class="p-3 sm:p-4 text-center">1</td>
                  <td class="p-3 sm:p-4 text-center">1</td>
                  <td class="p-3 sm:p-4 text-center text-emerald-400">1</td>
                  <td class="p-3 sm:p-4 text-center text-cyan-400">1</td>
                  <td class="p-3 sm:p-4 text-center font-black text-purple-400">100</td>
                </tr>
                <tr class="hover:bg-slate-800/30">
                  <td class="p-3 sm:p-4 font-bold text-white">{{ locale === 'ar' ? 'الرد الآلي الذكي (AI مكانك)' : 'Smart AI Auto-Reply' }}</td>
                  <td class="p-3 sm:p-4 text-center text-slate-500">—</td>
                  <td class="p-3 sm:p-4 text-center text-slate-500">—</td>
                  <td class="p-3 sm:p-4 text-center text-emerald-400 font-bold">✓</td>
                  <td class="p-3 sm:p-4 text-center text-cyan-400 font-bold">✓ (Unlimited)</td>
                  <td class="p-3 sm:p-4 text-center text-purple-400 font-bold">✓ (VIP)</td>
                </tr>
                <tr class="hover:bg-slate-800/30">
                  <td class="p-3 sm:p-4 font-bold text-white">{{ locale === 'ar' ? 'الحملات التسويقية والجدولة' : 'Targeted Campaigns' }}</td>
                  <td class="p-3 sm:p-4 text-center text-slate-500">—</td>
                  <td class="p-3 sm:p-4 text-center font-bold">✓</td>
                  <td class="p-3 sm:p-4 text-center text-emerald-400 font-bold">✓</td>
                  <td class="p-3 sm:p-4 text-center text-cyan-400 font-bold">✓</td>
                  <td class="p-3 sm:p-4 text-center text-purple-400 font-bold">✓</td>
                </tr>
                <tr class="hover:bg-slate-800/30">
                  <td class="p-3 sm:p-4 font-bold text-white">REST API & Webhooks</td>
                  <td class="p-3 sm:p-4 text-center text-slate-400">{{ locale === 'ar' ? 'أساسي' : 'Basic' }}</td>
                  <td class="p-3 sm:p-4 text-center font-bold">✓</td>
                  <td class="p-3 sm:p-4 text-center text-emerald-400 font-bold">✓</td>
                  <td class="p-3 sm:p-4 text-center text-cyan-400 font-bold">✓</td>
                  <td class="p-3 sm:p-4 text-center text-purple-400 font-bold">✓ (Dedicated)</td>
                </tr>
                <tr class="hover:bg-slate-800/30">
                  <td class="p-3 sm:p-4 font-bold text-white">{{ locale === 'ar' ? 'عقدة n8n والتكاملات' : 'n8n Community Node' }}</td>
                  <td class="p-3 sm:p-4 text-center text-slate-500">—</td>
                  <td class="p-3 sm:p-4 text-center text-slate-500">—</td>
                  <td class="p-3 sm:p-4 text-center text-emerald-400 font-bold">✓</td>
                  <td class="p-3 sm:p-4 text-center text-cyan-400 font-bold">✓</td>
                  <td class="p-3 sm:p-4 text-center text-purple-400 font-bold">✓</td>
                </tr>
                <tr class="hover:bg-slate-800/30">
                  <td class="p-3 sm:p-4 font-bold text-white">{{ locale === 'ar' ? 'قنوات Telegram & Messenger' : 'Omnichannel Integration' }}</td>
                  <td class="p-3 sm:p-4 text-center text-slate-500">—</td>
                  <td class="p-3 sm:p-4 text-center text-slate-500">—</td>
                  <td class="p-3 sm:p-4 text-center text-emerald-400 font-bold">Telegram</td>
                  <td class="p-3 sm:p-4 text-center text-cyan-400 font-bold">All Channels</td>
                  <td class="p-3 sm:p-4 text-center text-purple-400 font-bold">All Channels</td>
                </tr>
                <tr class="hover:bg-slate-800/30">
                  <td class="p-3 sm:p-4 font-bold text-white">{{ locale === 'ar' ? 'مستوى الدعم الفني' : 'Support SLA' }}</td>
                  <td class="p-3 sm:p-4 text-center text-slate-400">{{ locale === 'ar' ? 'شات' : 'Chat' }}</td>
                  <td class="p-3 sm:p-4 text-center">{{ locale === 'ar' ? 'بريد وشات' : 'Email & Chat' }}</td>
                  <td class="p-3 sm:p-4 text-center text-emerald-400 font-bold">24/7 Priority</td>
                  <td class="p-3 sm:p-4 text-center text-cyan-400 font-bold">Dedicated Manager</td>
                  <td class="p-3 sm:p-4 text-center text-purple-400 font-bold">VIP Phone & Direct SLA</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- FAQS SECTION -->
        <div class="max-w-3xl mx-auto mb-12 sm:mb-16">
          <div class="text-center mb-6">
            <h2 class="text-lg sm:text-2xl font-black text-white">{{ t('landing.faq_title') }}</h2>
            <p class="text-xs text-slate-400 mt-1">{{ t('landing.faq_subtitle') }}</p>
          </div>

          <div class="space-y-2">
            <div 
              v-for="(faq, fIndex) in [
                { q: t('landing.faq_1_q'), a: t('landing.faq_1_a') },
                { q: t('landing.faq_2_q'), a: t('landing.faq_2_a') },
                { q: t('landing.faq_3_q'), a: t('landing.faq_3_a') },
                { q: t('landing.faq_4_q'), a: t('landing.faq_4_a') },
                { q: t('landing.faq_5_q'), a: t('landing.faq_5_a') },
              ]" 
              :key="fIndex"
              class="rounded-xl border border-slate-800 bg-slate-900/70 overflow-hidden"
            >
              <button 
                @click="toggleFaq(fIndex)"
                class="w-full px-4 py-3 text-left flex items-center justify-between text-xs sm:text-sm font-bold text-white hover:text-emerald-400 transition-colors cursor-pointer"
                :class="isRTL ? 'text-right' : 'text-left'"
              >
                <span>{{ faq.q }}</span>
                <ChevronDown 
                  class="w-4 h-4 text-slate-400 transition-transform duration-200" 
                  :class="{ 'rotate-180 text-emerald-400': expandedFaq === fIndex }"
                />
              </button>
              <div 
                v-if="expandedFaq === fIndex" 
                class="px-4 pb-3 text-xs text-slate-300 leading-relaxed border-t border-slate-800/60 pt-2 font-medium bg-slate-950/40"
              >
                {{ faq.a }}
              </div>
            </div>
          </div>
        </div>

        <!-- FINAL CTA BANNER -->
        <div class="max-w-4xl mx-auto mb-10 sm:mb-16">
          <div class="p-6 sm:p-10 rounded-3xl bg-gradient-to-r from-emerald-950/70 via-slate-900 to-teal-950/70 border border-emerald-500/30 text-center">
            <h2 class="text-xl sm:text-3xl font-black text-white mb-2">
              {{ t('landing.cta_banner_title') }}
            </h2>
            <p class="text-xs sm:text-sm text-slate-300 max-w-lg mx-auto mb-5 font-medium">
              {{ t('landing.cta_banner_subtitle') }}
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 max-w-sm mx-auto">
              <router-link 
                to="/register" 
                class="w-full sm:w-auto flex-1 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 px-6 py-3 rounded-xl font-black text-xs sm:text-sm transition-all shadow-lg shadow-emerald-500/25 active:scale-98 flex items-center justify-center gap-2"
              >
                <span>{{ t('landing.cta_banner_button') }}</span>
                <Rocket class="w-4 h-4" />
              </router-link>
              <a 
                href="https://wa.me/201024520482" 
                target="_blank" 
                class="w-full sm:w-auto bg-slate-900 hover:bg-slate-800 border border-slate-700 text-white px-5 py-3 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-2"
              >
                <Headphones class="w-4 h-4 text-emerald-400" />
                <span>{{ locale === 'ar' ? 'تحدث مع المبيعات' : 'Talk to Sales' }}</span>
              </a>
            </div>
          </div>
        </div>

      </section>

    </main>

    <!-- ULTRA-CLEAN MODERN FOOTER -->
    <footer class="border-t border-slate-800 bg-[#05080f] py-8 text-xs text-slate-400">
      <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
          
          <!-- Col 1: Brand Info -->
          <div class="col-span-2 md:col-span-1">
            <div class="flex items-center gap-2 mb-2">
              <img :src="logo" alt="Wavixa" class="h-6 w-auto" />
              <span class="text-sm font-black text-white">WAVIXA</span>
            </div>
            <p class="text-[11px] text-slate-400 leading-relaxed font-medium mb-3">
              {{ t('landing.footer_desc') }}
            </p>
            <span class="inline-flex items-center gap-1 text-[10px] text-emerald-400 font-bold bg-emerald-950/60 border border-emerald-500/30 px-2 py-0.5 rounded">
              Meta Approved Tech Solution
            </span>
          </div>

          <!-- Col 2: Navigation -->
          <div>
            <h4 class="text-xs font-black text-white mb-2.5">{{ t('landing.footer_products') }}</h4>
            <ul class="space-y-1.5 text-[11px]">
              <li><router-link to="/#channels" class="hover:text-emerald-400 transition-colors">{{ t('landing.nav_channels') }}</router-link></li>
              <li><router-link to="/#features" class="hover:text-emerald-400 transition-colors">{{ t('landing.nav_features') }}</router-link></li>
              <li><router-link to="/pricing" class="hover:text-emerald-400 transition-colors">{{ t('landing.nav_pricing') }}</router-link></li>
              <li><router-link to="/api-docs" class="hover:text-emerald-400 transition-colors">{{ t('landing.nav_api_docs') }}</router-link></li>
            </ul>
          </div>

          <!-- Col 3: Legal & Support -->
          <div>
            <h4 class="text-xs font-black text-white mb-2.5">{{ t('landing.footer_developers') }}</h4>
            <ul class="space-y-1.5 text-[11px]">
              <li><a href="https://wa.me/201024520482" target="_blank" class="hover:text-emerald-400 transition-colors">WhatsApp Support</a></li>
              <li><router-link to="/api-docs" class="hover:text-emerald-400 transition-colors">API Docs & SDK</router-link></li>
              <li><router-link to="/login" class="hover:text-emerald-400 transition-colors">{{ t('landing.login') }}</router-link></li>
              <li><router-link to="/register" class="hover:text-emerald-400 transition-colors">{{ t('landing.start_free') }}</router-link></li>
            </ul>
          </div>

          <!-- Col 4: Status & Contact -->
          <div class="col-span-2 md:col-span-1">
            <h4 class="text-xs font-black text-white mb-2.5">{{ locale === 'ar' ? 'حالة النظام' : 'System Status' }}</h4>
            <div class="p-2.5 rounded-xl bg-slate-900 border border-slate-800 space-y-2">
              <div class="flex items-center justify-between text-[11px]">
                <span class="text-slate-400">{{ locale === 'ar' ? 'خوادم السحابة' : 'Cloud Servers' }}</span>
                <span class="text-emerald-400 font-black flex items-center gap-1">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                  99.99% Online
                </span>
              </div>
              <div class="flex items-center gap-2 text-[10px] text-slate-400 whitespace-nowrap">
                <a href="mailto:support@wavixa.net" class="truncate hover:text-emerald-400 transition-colors">support@wavixa.net</a>
                <span class="text-slate-700">|</span>
                <a
                  :href="'https://wa.me/201011679726?text=' + encodeURIComponent(locale === 'ar' ? 'أحتاج إلى الدعم في الموقع' : 'I need support on the website')"
                  target="_blank"
                  rel="noopener"
                  class="hover:text-emerald-400 transition-colors font-bold"
                  dir="ltr"
                >01011679726</a>
              </div>
            </div>
          </div>

        </div>

        <!-- Copyright -->
        <div class="pt-4 border-t border-slate-800/80 flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px] text-slate-500">
          <div>© {{ new Date().getFullYear() }} Wavixa.net. {{ t('landing.footer_rights') }}</div>
          <div class="flex items-center gap-3">
            <span>WhatsApp Business API Cloud</span>
          </div>
        </div>

      </div>
    </footer>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import {
  Zap,
  Sparkles,
  Check,
  ChevronDown,
  Rocket,
  Headphones,
  Menu,
  X,
} from 'lucide-vue-next';
import logo from '@/assets/logo.png';
import auth from '@/services/auth';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { isRTL, t, locale } from '@/i18n';

const router = useRouter();
const isMenuOpen = ref(false);
const scrolled = ref(false);
const expandedFaq = ref(0);

const isAuthenticated = computed(() => auth.isAuthenticated());

// Plan Category Toggle: 'quota' (Starter & Pro) vs 'unlimited' (Business & Partners)
const planCategory = ref('quota');

// Per-card billing toggle ('monthly' | 'yearly')
const cardBilling = ref({
  starter: 'yearly',
  pro: 'yearly',
  business: 'yearly',
  partners: 'yearly',
});

function handlePlanSelect(slug) {
  if (isAuthenticated.value) {
    router.push({
      path: '/subscription',
      query: { upgrade: slug },
    });
  } else {
    router.push(`/register?plan=${slug}`);
  }
}

function toggleFaq(index) {
  expandedFaq.value = expandedFaq.value === index ? null : index;
}

function onScroll() {
  scrolled.value = window.scrollY > 15;
}

onMounted(() => {
  window.addEventListener('scroll', onScroll, { passive: true });
});

onUnmounted(() => {
  window.removeEventListener('scroll', onScroll);
});
</script>
