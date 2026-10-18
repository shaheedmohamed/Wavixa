<template>
  <DashboardLayout>
    <div class="space-y-8 animate-in fade-in duration-500 max-w-6xl mx-auto pb-24">
      <!-- Top Header & Master Switch -->
      <div class="bg-white border border-slate-100 rounded-3xl p-6 sm:p-8 shadow-xs relative overflow-hidden">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
          <div class="space-y-2">
            <div class="flex items-center gap-3 flex-wrap">
              <div class="w-10 h-10 rounded-2xl bg-indigo-600/10 text-indigo-600 flex items-center justify-center font-black">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
              </div>
              <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                {{ t('auto_reply_page.title') }}
              </h1>
              <span
                v-if="form.is_enabled"
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-emerald-50 text-emerald-700 border border-emerald-200/60 shadow-2xs"
              >
                <span class="w-2 h-2 rounded-full bg-[#00C853] animate-pulse"></span>
                {{ t('auto_reply_page.status_enabled') }}
              </span>
              <span
                v-else
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500 border border-slate-200"
              >
                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                {{ t('auto_reply_page.status_disabled') }}
              </span>
            </div>
            <p class="text-slate-500 text-xs sm:text-sm font-medium leading-relaxed max-w-2xl">
              {{ t('auto_reply_page.subtitle') }}
            </p>
          </div>

          <!-- Master Action Switch + Quick Stats -->
          <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 border-t lg:border-t-0 pt-4 lg:pt-0 border-slate-100">
            <div v-if="stats.total_replies > 0 || stats.ai_total_replies > 0" class="flex items-center gap-3 bg-slate-50 border border-slate-200/60 rounded-2xl px-4 py-2.5">
              <div class="flex flex-col">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                  {{ form.mode === 'ai' ? t('auto_reply_page.ai_replies_total') : t('auto_reply_page.stats_total_replies') }}
                </span>
                <span class="text-base font-black text-slate-900 font-mono">
                  {{ Number(form.mode === 'ai' ? stats.ai_total_replies : stats.total_replies).toLocaleString() }}
                </span>
              </div>
            </div>

            <!-- Integrated Master Toggle -->
            <div class="flex items-center gap-3 bg-slate-50/80 border border-slate-200/60 rounded-2xl p-2 sm:px-4 sm:py-2.5">
              <div class="text-right sm:text-left">
                <span class="text-xs font-black text-slate-800 block">حالة الرد التلقائي</span>
                <span class="text-[10px] font-bold" :class="form.is_enabled ? 'text-emerald-600' : 'text-slate-400'">
                  {{ form.is_enabled ? 'مفعّل ويعمل الآن' : 'متوقف حالياً' }}
                </span>
              </div>
              <button
                type="button"
                @click="toggleAutoReply"
                :disabled="!qualifies"
                class="relative inline-flex h-7 w-14 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                :class="form.is_enabled ? 'bg-[#00C853]' : 'bg-slate-300'"
                role="switch"
                :aria-checked="form.is_enabled"
              >
                <span
                  aria-hidden="true"
                  class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out"
                  :class="form.is_enabled ? (isRTL ? '-translate-x-7' : 'translate-x-7') : 'translate-x-0'"
                />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Locked Plan Banner -->
      <div
        v-if="!qualifies"
        class="bg-amber-50 border border-amber-200/80 rounded-3xl p-5 sm:p-6 relative overflow-hidden"
      >
        <div class="relative z-10 flex flex-col sm:flex-row items-center justify-between gap-4">
          <div class="space-y-1 text-center sm:text-right">
            <h2 class="text-sm font-black text-amber-900">{{ t('auto_reply_page.locked_title') }}</h2>
            <p class="text-xs text-amber-800 leading-relaxed max-w-2xl">{{ t('auto_reply_page.locked_message') }}</p>
          </div>
          <router-link
            to="/subscription"
            class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl transition-all shadow-xs active:scale-95"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
            <span>{{ t('auto_reply_page.locked_upgrade') }}</span>
          </router-link>
        </div>
      </div>

      <!-- Success Notification Alert -->
      <div
        v-if="successMessage"
        class="bg-emerald-50 border border-emerald-200/80 rounded-2xl p-4 flex items-center justify-between gap-3 text-emerald-800 animate-in slide-in-from-top-2 duration-300"
      >
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-emerald-500 text-white flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
          </div>
          <span class="text-sm font-bold">{{ successMessage }}</span>
        </div>
        <button @click="successMessage = ''" class="text-emerald-600 hover:text-emerald-800 p-1 rounded-lg">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
      </div>

      <!-- Error Notification Alert -->
      <div
        v-if="errorMessage"
        class="bg-red-50 border border-red-200/80 rounded-2xl p-4 flex items-center justify-between gap-3 text-red-800 animate-in slide-in-from-top-2 duration-300"
      >
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-red-500 text-white flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
          </div>
          <span class="text-sm font-bold">{{ errorMessage }}</span>
        </div>
        <button @click="errorMessage = ''" class="text-red-600 hover:text-red-800 p-1 rounded-lg">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
      </div>


      <!-- Step 1: Device Selection -->
      <div class="bg-white border border-slate-100 rounded-3xl p-6 sm:p-8 shadow-xs space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
          <div class="space-y-1">
            <h2 class="text-base sm:text-lg font-black text-slate-900 flex items-center gap-2">
              <span class="w-6 h-6 rounded-lg bg-indigo-50 text-indigo-600 text-xs flex items-center justify-center font-black">1</span>
              <span>{{ t('auto_reply_page.select_device_title') }}</span>
              <span class="text-[11px] font-extrabold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md">
                {{ connectedSessions.length }} {{ t('common.connected') }}
              </span>
            </h2>
            <p class="text-xs text-slate-500 font-medium">
              {{ t('auto_reply_page.select_device_desc') }}
            </p>
          </div>

          <router-link
            to="/devices"
            class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-xl transition-colors self-start sm:self-auto"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>{{ t('auto_reply_page.connect_new_device') }}</span>
          </router-link>
        </div>

        <div v-if="sessions.length > 0" class="space-y-3">
          <div
            v-for="s in sessions"
            :key="s.id"
            @click="s.status === 'connected' ? selectSession(s.id) : null"
            class="relative rounded-2xl p-4 border-2 transition-all flex items-center justify-between gap-3 cursor-pointer"
            :class="[
              form.session_id === s.id
                ? 'border-emerald-500 bg-emerald-50/40 shadow-xs ring-2 ring-emerald-500/10'
                : (s.status === 'connected' ? 'border-slate-100 hover:border-slate-300 bg-white' : 'border-slate-100 bg-slate-50/60 opacity-60 cursor-not-allowed')
            ]"
          >
            <div class="flex items-center gap-3 min-w-0">
              <div
                class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 transition-colors"
                :class="s.status === 'connected' ? (form.session_id === s.id ? 'bg-emerald-500 text-white' : 'bg-emerald-100 text-[#00C853]') : 'bg-slate-200 text-slate-400'"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
              </div>
              <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                  <span class="text-xs font-black text-slate-900 truncate">{{ s.device_name || 'WhatsApp Device' }}</span>
                  <span
                    v-if="s.status === 'connected'"
                    class="w-2 h-2 rounded-full bg-[#00C853] animate-pulse flex-shrink-0"
                    title="متصل"
                  ></span>
                </div>
                <span class="text-xs font-bold text-slate-600 font-mono block" dir="ltr">
                  {{ s.phone_number ? (s.phone_number.startsWith('+') ? s.phone_number : `+${s.phone_number}`) : '—' }}
                </span>
              </div>
            </div>

            <div class="flex items-center">
              <div
                class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                :class="form.session_id === s.id ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300'"
              >
                <svg v-if="form.session_id === s.id" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-8 px-4 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
          <h3 class="text-sm font-black text-slate-800 mb-1">{{ t('auto_reply_page.no_connected_devices') }}</h3>
          <p class="text-xs text-slate-500 mb-4">{{ t('send_message_page.not_connected_desc') }}</p>
          <router-link to="/devices" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-all shadow-xs active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>{{ t('auto_reply_page.connect_new_device') }}</span>
          </router-link>
        </div>
      </div>

      <!-- Step 2: Mode Selection -->
      <div class="bg-white border border-slate-100 rounded-3xl p-6 sm:p-8 shadow-xs space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-5">
          <div class="space-y-1">
            <h2 class="text-base sm:text-lg font-black text-slate-900 flex items-center gap-2">
              <span class="w-6 h-6 rounded-lg bg-indigo-50 text-indigo-600 text-xs flex items-center justify-center font-black">2</span>
              <span>{{ t('auto_reply_page.mode_title') }}</span>
            </h2>
            <p class="text-xs text-slate-500 font-medium">اختر بين الرد الذكي المخصص أو الرد الثابت السريع</p>
          </div>

          <div class="flex p-1 bg-slate-100 rounded-2xl self-start sm:self-auto">
            <button
              type="button"
              @click="form.mode = 'ai'"
              class="py-2 px-4 sm:px-6 rounded-xl text-xs font-black transition-all flex items-center gap-2"
              :class="form.mode === 'ai' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.4 7.2h7.6l-6 4.8 2.4 7.2-6-4.8-6 4.8 2.4-7.2-6-4.8h7.6z"/></svg>
              <span>{{ t('auto_reply_page.mode_ai') }}</span>
            </button>
            <button
              type="button"
              @click="form.mode = 'static'"
              class="py-2 px-4 sm:px-6 rounded-xl text-xs font-black transition-all"
              :class="form.mode === 'static' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
            >
              {{ t('auto_reply_page.mode_static') }}
            </button>
          </div>
        </div>

        <!-- AI MODE -->
        <div v-if="form.mode === 'ai'" class="space-y-6 animate-in fade-in duration-300">
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-7 space-y-5">
              <div class="space-y-2">
                <div class="flex items-center justify-between">
                  <label class="text-xs font-black text-slate-900 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>{{ t('auto_reply_page.ai_system_prompt_label') }}</span>
                  </label>
                  <span class="text-[11px] font-mono text-slate-400">{{ form.ai_system_prompt.length }} / 8000</span>
                </div>
                <textarea
                  v-model="form.ai_system_prompt"
                  :placeholder="t('auto_reply_page.ai_system_prompt_placeholder')"
                  rows="6"
                  class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-slate-900 font-medium text-xs sm:text-sm placeholder-slate-400 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/10 transition-all outline-none resize-none leading-relaxed"
                ></textarea>

                <div class="flex items-center gap-2 flex-wrap pt-1">
                  <span class="text-[11px] font-bold text-slate-400">نماذج سريعة:</span>
                  <button type="button" @click="applyTemplate('support')" class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-colors">خدمة عملاء</button>
                  <button type="button" @click="applyTemplate('sales')" class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors">مبيعات</button>
                  <button type="button" @click="applyTemplate('brief')" class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors">مختصر</button>
                </div>
              </div>

              <div class="space-y-2">
                <div class="flex items-center justify-between">
                  <label class="text-xs font-black text-slate-900 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                    <span>{{ t('auto_reply_page.ai_business_info_label') }}</span>
                  </label>
                  <span class="text-[11px] font-mono text-slate-400">{{ form.ai_business_info.length }} / 8000</span>
                </div>
                <textarea
                  v-model="form.ai_business_info"
                  :placeholder="t('auto_reply_page.ai_business_info_placeholder')"
                  rows="6"
                  class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-slate-900 font-medium text-xs sm:text-sm placeholder-slate-400 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/10 transition-all outline-none resize-none leading-relaxed"
                ></textarea>
              </div>
            </div>

            <div class="lg:col-span-5 space-y-5">
              <div class="bg-slate-50/80 border border-slate-100 rounded-2xl p-5 space-y-4">
                <h3 class="text-xs font-black text-slate-900 flex items-center gap-2 border-b border-slate-200/60 pb-3">
                  <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                  <span>تخصيص الذكاء الاصطناعي</span>
                </h3>

                <div class="space-y-1.5">
                  <label class="text-[11px] font-black text-slate-700 block">{{ t('auto_reply_page.ai_model_label') }}</label>
                  <select v-model="form.ai_model" class="w-full bg-white border border-slate-200 rounded-xl p-2.5 text-xs font-bold text-slate-800 focus:border-indigo-400 outline-none">
                    <option value="google/gemma-4-31b-it:free">Gemma 4 31B IT (Free - موصى به)</option>
                    <option value="google/gemma-4-26b-a4b-it:free">Gemma 4 26B A4B IT (Free)</option>
                    <option value="liquid/lfm-2.5-2.6b:free">LFM 2.5 2.6B (Free)</option>
                    <option value="dots-studio/dots-3-note-preview:free">Dots 3 Note (Free - للاختبار فقط)</option>
                  </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                  <div class="space-y-1.5">
                    <label class="text-[11px] font-black text-slate-700 block">{{ t('auto_reply_page.ai_tone_label') }}</label>
                    <select v-model="form.ai_tone" class="w-full bg-white border border-slate-200 rounded-xl p-2.5 text-xs font-bold text-slate-800 focus:border-indigo-400 outline-none">
                      <option value="friendly">{{ t('auto_reply_page.ai_tone_friendly') }}</option>
                      <option value="professional">{{ t('auto_reply_page.ai_tone_professional') }}</option>
                      <option value="brief">{{ t('auto_reply_page.ai_tone_brief') }}</option>
                      <option value="formal">{{ t('auto_reply_page.ai_tone_formal') }}</option>
                    </select>
                  </div>
                  <div class="space-y-1.5">
                    <label class="text-[11px] font-black text-slate-700 block">{{ t('auto_reply_page.ai_language_label') }}</label>
                    <select v-model="form.ai_language" class="w-full bg-white border border-slate-200 rounded-xl p-2.5 text-xs font-bold text-slate-800 focus:border-indigo-400 outline-none">
                      <option value="auto">{{ t('auto_reply_page.ai_language_auto') }}</option>
                      <option value="arabic">{{ t('auto_reply_page.ai_language_arabic') }}</option>
                      <option value="egyptian">{{ t('auto_reply_page.ai_language_egyptian') }}</option>
                      <option value="english">{{ t('auto_reply_page.ai_language_english') }}</option>
                    </select>
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                  <div class="space-y-1.5">
                    <label class="text-[11px] font-black text-slate-700 block">{{ t('auto_reply_page.ai_context_limit_label') }}</label>
                    <select v-model="form.ai_context_limit" class="w-full bg-white border border-slate-200 rounded-xl p-2.5 text-xs font-bold text-slate-800 focus:border-indigo-400 outline-none">
                      <option :value="5">5 رسائل</option>
                      <option :value="10">10 رسائل</option>
                      <option :value="15">15 رسالة</option>
                      <option :value="20">20 رسالة</option>
                    </select>
                  </div>
                  <div class="space-y-1.5">
                    <label class="text-[11px] font-black text-slate-700 block">{{ t('auto_reply_page.ai_daily_limit_label') }}</label>
                    <input v-model.number="form.ai_max_daily_replies" type="number" min="0" max="10000" class="w-full bg-white border border-slate-200 rounded-xl p-2.5 text-xs font-bold text-slate-800 focus:border-indigo-400 outline-none" />
                  </div>
                </div>

                <div class="space-y-1.5">
                  <label class="text-[11px] font-black text-slate-700 block">{{ t('auto_reply_page.ai_handoff_minutes_label') }}</label>
                  <input v-model.number="form.ai_handoff_minutes" type="number" min="0" max="1440" placeholder="0" class="w-full bg-white border border-slate-200 rounded-xl p-2.5 text-xs font-bold text-slate-800 focus:border-indigo-400 outline-none" />
                  <span class="text-[10px] text-slate-400 font-medium block">{{ t('auto_reply_page.ai_handoff_zero') }}</span>
                </div>
              </div>

              <div class="space-y-3">
                <div class="bg-slate-50/80 border border-slate-100 rounded-2xl p-4 space-y-3">
                  <label for="bh" class="text-xs font-black text-slate-800 cursor-pointer flex items-center gap-2">
                    <input v-model="form.ai_business_hours_enabled" id="bh" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    <span>{{ t('auto_reply_page.ai_business_hours_label') }}</span>
                    <span v-if="form.ai_business_hours_enabled" class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md mr-auto">مفعّل</span>
                  </label>
                  <div v-if="form.ai_business_hours_enabled" class="grid grid-cols-2 gap-3 pt-1">
                    <div class="space-y-1">
                      <label class="text-[10px] font-bold text-slate-500">{{ t('auto_reply_page.ai_business_hours_from') }}</label>
                      <input v-model="form.ai_business_hours_from" type="time" class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs font-bold text-slate-800 focus:border-indigo-400 outline-none" />
                    </div>
                    <div class="space-y-1">
                      <label class="text-[10px] font-bold text-slate-500">{{ t('auto_reply_page.ai_business_hours_to') }}</label>
                      <input v-model="form.ai_business_hours_to" type="time" class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs font-bold text-slate-800 focus:border-indigo-400 outline-none" />
                    </div>
                  </div>
                </div>

                <div class="bg-slate-50/80 border border-slate-100 rounded-2xl p-4 space-y-3">
                  <label for="fb" class="text-xs font-black text-slate-800 cursor-pointer flex items-center gap-2">
                    <input v-model="form.ai_fallback_enabled" id="fb" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    <span>{{ t('auto_reply_page.ai_fallback_enabled') }}</span>
                    <span v-if="form.ai_fallback_enabled" class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md mr-auto">مفعّل</span>
                  </label>
                  <div v-if="form.ai_fallback_enabled" class="space-y-1 pt-1">
                    <textarea
                      v-model="form.ai_fallback_message"
                      :placeholder="t('auto_reply_page.ai_fallback_placeholder')"
                      rows="2"
                      class="w-full bg-white border border-slate-200 rounded-xl p-2.5 text-xs font-medium text-slate-800 placeholder-slate-400 focus:border-indigo-400 outline-none resize-none"
                    ></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- AI Test Box -->
          <div class="bg-indigo-50/40 border border-indigo-100/80 rounded-3xl p-6 sm:p-7 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-indigo-100/60 pb-3">
              <div class="space-y-0.5">
                <h3 class="text-sm font-black text-indigo-950 flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-indigo-600 animate-ping"></span>
                  <span>{{ t('auto_reply_page.ai_test_title') }}</span>
                </h3>
                <p class="text-xs text-indigo-700/70 font-medium">اكتب رسالة كأنك عميل لتجربة إجابة AI قبل التفعيل</p>
              </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
              <input
                v-model="aiTestMessage"
                @keyup.enter="testAI"
                :placeholder="'مثال: ما هي الباقات المتاحة؟'"
                class="flex-1 bg-white border border-indigo-200/80 rounded-2xl p-3.5 text-xs sm:text-sm font-bold text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 outline-none shadow-2xs"
              />
              <button
                type="button"
                @click="testAI"
                :disabled="aiTestLoading || !aiTestMessage.trim()"
                class="px-6 py-3.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-xs font-black rounded-2xl transition-all shadow-xs active:scale-95 flex items-center justify-center gap-2 flex-shrink-0"
              >
                <svg v-if="aiTestLoading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span>{{ aiTestLoading ? t('auto_reply_page.ai_test_generating') : t('auto_reply_page.ai_test_button') }}</span>
              </button>
            </div>

            <div v-if="aiTestResult" class="bg-white border border-indigo-100 rounded-2xl p-4 sm:p-5 shadow-xs space-y-2 animate-in fade-in slide-in-from-top-2 duration-300">
              <div class="flex items-center justify-between">
                <span class="text-[10px] font-black text-indigo-600 uppercase tracking-wider flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                  {{ t('auto_reply_page.ai_test_preview') }}
                </span>
                <span class="text-[10px] font-bold text-slate-400 font-mono">OpenRouter AI</span>
              </div>
              <div class="bg-[#E7F8E8] text-slate-900 rounded-2xl rounded-tr-none p-4 text-xs sm:text-sm font-medium leading-relaxed max-w-xl self-start border border-emerald-100 whitespace-pre-line shadow-2xs">
                {{ aiTestResult }}
              </div>
            </div>
          </div>
        </div>

        <!-- STATIC MODE -->
        <div v-else class="space-y-5 animate-in fade-in duration-300">
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-7 space-y-3">
              <div class="flex items-center justify-between">
                <label class="text-xs font-black text-slate-800 block">{{ t('auto_reply_page.message_title') }}</label>
                <button type="button" @click="applyDefaultTemplate" class="text-indigo-600 hover:text-indigo-800 text-xs font-black underline transition-colors">{{ t('auto_reply_page.use_default') }}</button>
              </div>
              <textarea
                v-model="form.reply_message"
                :placeholder="t('auto_reply_page.message_placeholder')"
                rows="8"
                class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-slate-900 font-medium text-xs sm:text-sm placeholder-slate-400 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/10 transition-all outline-none resize-none leading-relaxed"
              ></textarea>
              <div class="flex items-center justify-between text-[11px] font-bold text-slate-400 px-1">
                <span>{{ t('auto_reply_page.chars_count', { count: form.reply_message.length }) }}</span>
                <span>سيتم إرسال هذا الرد حرفياً لكل رسالة واردة</span>
              </div>
            </div>

            <div class="lg:col-span-5 flex flex-col">
              <span class="text-xs font-black text-slate-800 mb-3 block">معاينة شكل الرسالة</span>
              <div class="flex-1 bg-[#EFEAE2] border border-slate-200 rounded-2xl p-4 flex flex-col justify-between min-h-[220px]">
                <div class="space-y-3">
                  <div class="bg-white rounded-2xl rounded-tl-none p-3 max-w-[80%] text-xs text-slate-800 shadow-2xs self-start">
                    <p class="font-medium">{{ t('auto_reply_page.preview_incoming') }}</p>
                    <span class="text-[9px] text-slate-400 block text-left mt-1 font-mono">10:30 ص</span>
                  </div>

                  <div class="bg-[#D9FDD3] rounded-2xl rounded-tr-none p-3 max-w-[85%] text-xs text-slate-900 shadow-2xs mr-auto whitespace-pre-line border border-[#C2EAC0]">
                    <p class="font-medium">{{ form.reply_message || 'اكتب نص الرسالة في المربع ليظهر هنا...' }}</p>
                    <span class="text-[9px] text-slate-500 block text-left mt-1 font-mono">10:30 ص ✓✓</span>
                  </div>
                </div>

                <div class="text-center pt-2">
                  <span class="text-[10px] font-bold text-slate-400">معاينة حية على WhatsApp</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Shared Settings: Reply Once + Delay -->
        <div class="border-t border-slate-100 pt-6 space-y-5">
          <div class="bg-slate-50/70 border border-slate-100 rounded-2xl p-4 flex items-center justify-between gap-4">
            <div class="space-y-0.5">
              <span class="text-xs font-black text-slate-800 block">{{ t('auto_reply_page.reply_once_title') }}</span>
              <span class="text-[11px] text-slate-500 font-medium block leading-relaxed">{{ t('auto_reply_page.reply_once_desc') }}</span>
            </div>
            <button
              type="button"
              @click="form.reply_once = !form.reply_once"
              class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
              :class="form.reply_once ? 'bg-indigo-600' : 'bg-slate-300'"
              role="switch"
              :aria-checked="form.reply_once"
            >
              <span aria-hidden="true" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out" :class="form.reply_once ? (isRTL ? '-translate-x-5' : 'translate-x-5') : 'translate-x-0'" />
            </button>
          </div>

          <div class="bg-slate-50/70 border border-slate-100 rounded-2xl p-4 space-y-3">
            <div class="space-y-0.5">
              <span class="text-xs font-black text-slate-800 block">{{ t('auto_reply_page.delay_title') }}</span>
              <span class="text-[11px] text-slate-500 font-medium block">{{ t('auto_reply_page.delay_desc') }}</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
              <button
                v-for="d in [0, 5, 10, 30]"
                :key="d"
                type="button"
                @click="form.delay_seconds = d"
                class="py-2.5 px-3 rounded-xl text-xs font-black transition-all border text-center shadow-2xs"
                :class="form.delay_seconds === d ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
              >
                {{ delayLabel(d) }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Step 3: Activity & Logs -->
      <div class="bg-white border border-slate-100 rounded-3xl p-6 sm:p-8 shadow-xs space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
          <div class="space-y-1">
            <h2 class="text-base sm:text-lg font-black text-slate-900 flex items-center gap-2">
              <span class="w-6 h-6 rounded-lg bg-indigo-50 text-indigo-600 text-xs flex items-center justify-center font-black">3</span>
              <span>{{ t('auto_reply_page.logs_section_title') }}</span>
            </h2>
            <p class="text-xs text-slate-500 font-medium">{{ t('auto_reply_page.logs_section_desc') }}</p>
          </div>

          <button type="button" @click="loadLogs" :disabled="logsLoading" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-all active:scale-95 self-start sm:self-auto">
            <svg class="w-3.5 h-3.5 text-slate-500" :class="{ 'animate-spin': logsLoading }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
            <span>{{ t('auto_reply_page.refresh_logs') }}</span>
          </button>
        </div>

        <div v-if="logs.length > 0" class="overflow-x-auto border border-slate-100 rounded-2xl">
          <table class="w-full text-xs text-right divide-y divide-slate-100">
            <thead class="bg-slate-50/80 text-slate-400 font-bold uppercase text-[10px] tracking-wider">
              <tr>
                <th class="px-5 py-3.5 text-right">{{ t('auto_reply_page.th_sender') }}</th>
                <th class="px-5 py-3.5 text-right">{{ t('auto_reply_page.th_incoming_msg') }}</th>
                <th class="px-5 py-3.5 text-right">{{ t('auto_reply_page.th_auto_reply') }}</th>
                <th class="px-5 py-3.5 text-right">{{ t('auto_reply_page.th_status') }}</th>
                <th class="px-5 py-3.5 text-right">{{ t('auto_reply_page.th_time') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 bg-white">
              <tr v-for="log in logs" :key="log.id" class="hover:bg-slate-50/50 transition-colors">
                <td class="px-5 py-4 whitespace-nowrap">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-[10px] flex-shrink-0">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                    </div>
                    <div>
                      <span class="font-mono font-bold text-slate-900 block" dir="ltr">{{ formatPhone(log.sender_phone) }}</span>
                      <span v-if="log.sender_name" class="text-[10px] text-slate-400 block">{{ log.sender_name }}</span>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4 max-w-xs"><p class="text-slate-700 font-medium truncate" :title="log.incoming_message">{{ log.incoming_message || '—' }}</p></td>
                <td class="px-5 py-4 max-w-xs"><p class="text-slate-600 font-medium truncate" :title="log.reply_message">{{ log.reply_message || '—' }}</p></td>
                <td class="px-5 py-4 whitespace-nowrap">
                  <div class="space-y-0.5">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-black" :class="getStatusBadgeClass(log.status)">
                      <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDotClass(log.status)"></span>
                      {{ getStatusLabel(log.status) }}
                    </span>
                    <span v-if="log.status_reason" class="text-[10px] text-slate-400 block max-w-xs truncate" :title="log.status_reason">{{ log.status_reason }}</span>
                  </div>
                </td>
                <td class="px-5 py-4 whitespace-nowrap text-slate-400 font-mono text-[11px]" dir="ltr">{{ formatDate(log.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else-if="!logsLoading" class="text-center py-10 px-4 bg-slate-50/70 rounded-2xl border border-dashed border-slate-200">
          <h3 class="text-sm font-black text-slate-800 mb-1">{{ t('auto_reply_page.no_logs_title') }}</h3>
          <p class="text-xs text-slate-500 max-w-md mx-auto">{{ t('auto_reply_page.no_logs_desc') }}</p>
        </div>
      </div>

      <!-- Sticky Floating Save Bar -->
      <div class="fixed bottom-4 left-4 right-4 sm:left-8 sm:right-8 max-w-6xl mx-auto z-40 bg-[#020617]/90 backdrop-blur-md border border-slate-800/80 rounded-2xl p-4 sm:p-5 shadow-2xl flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="w-3 h-3 rounded-full flex-shrink-0" :class="form.is_enabled ? 'bg-[#00C853] animate-ping' : 'bg-slate-500'"></div>
          <div class="text-right sm:text-left">
            <span class="text-xs font-black text-white block">
              {{ form.is_enabled ? `الرد ${form.mode === 'ai' ? 'بالذكاء الاصطناعي' : 'الثابت'} مفعّل` : 'الرد التلقائي غير مفعّل' }}
            </span>
            <span class="text-[11px] font-mono text-slate-400 block">
              الجهاز: {{ selectedSession?.phone_number || (connectedSessions.length > 0 ? connectedSessions[0].phone_number : 'لم يتم تحديد جهاز') }}
            </span>
          </div>
        </div>

        <button
          type="button"
          @click="saveSettings"
          :disabled="isSaving || !qualifies"
          class="w-full sm:w-auto px-8 py-3 bg-[#00C853] hover:bg-emerald-500 text-slate-950 text-xs font-black rounded-xl transition-all shadow-lg active:scale-95 disabled:opacity-60 flex items-center justify-center gap-2"
        >
          <svg v-if="isSaving" class="animate-spin -ml-1 mr-2 h-4 w-4 text-slate-950" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
          <span>{{ isSaving ? t('auto_reply_page.saving') : t('auto_reply_page.save_button') }}</span>
        </button>
      </div>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import api from '@/services/api';
import auth from '@/services/auth';
import { t, isRTL } from '@/i18n';

export default {
  name: 'AutoReply',
  components: { DashboardLayout },
  setup() {
    const isLoading = ref(true);
    const isSaving = ref(false);
    const logsLoading = ref(false);
    const successMessage = ref('');
    const errorMessage = ref('');
    const messageInputRef = ref(null);
    const aiTestMessage = ref('');
    const aiTestLoading = ref(false);
    const aiTestResult = ref('');

    const sessions = ref([]);
    const logs = ref([]);
    const subscription = ref(null);
    const stats = reactive({
      total_replies: 0,
      last_reply_at: null,
      ai_total_replies: 0,
      ai_last_reply_at: null,
    });

    const form = reactive({
      is_enabled: false,
      session_id: null,
      mode: 'ai',
      reply_message: '',
      reply_once: false,
      delay_seconds: 0,
      ai_system_prompt: '',
      ai_business_info: '',
      ai_tone: 'friendly',
      ai_language: 'auto',
      ai_context_limit: 10,
      ai_fallback_enabled: false,
      ai_fallback_message: '',
      ai_max_daily_replies: 0,
      ai_business_hours_enabled: false,
      ai_business_hours_from: '09:00',
      ai_business_hours_to: '22:00',
      ai_model: 'google/gemma-4-31b-it:free',
      ai_handoff_minutes: 0,
    });

    const connectedSessions = computed(() => sessions.value.filter((s) => s.status === 'connected'));
    const selectedSession = computed(() => sessions.value.find((s) => s.id === form.session_id) || null);

    const qualifies = computed(() => {
      const slug = subscription.value?.plan?.slug || auth.getUser()?.subscription_plan || '';
      return ['business', 'business-yearly', 'partners', 'partners-yearly'].includes(slug);
    });

    const defaultExample = `أهلاً بك 👋\nشكراُ لتواصلك معنا.\nوصلتنا رسالتك وسنقوم بالرد عليك في أقرب وقت.`;

    const applyDefaultTemplate = () => {
      form.reply_message = defaultExample;
    };

    const applyTemplate = (type) => {
      if (type === 'support') {
        form.ai_system_prompt = `أنت موظف خدمة عملاء في منصة Wavixa. مهمتك مساعدة العملاء عبر WhatsApp بإجابات واضحة ومختصرة وودودة.\n- رد دائمًا بنفس لغة العميل.\n- إذا سأل عن الأسعار، اذكر الباقات: Starter، Pro، Business، Partners.\n- إذا تعذر عليك الإجابة، أخبر العميل أنه سيتم تحويله للدعم الفني.`;
        if (!form.ai_business_info.trim()) {
          form.ai_business_info = `Wavixa هي منصة لخدمات WhatsApp API وأتمتة الرسائل.\nالباقات المتاحة: Starter و Pro و Business و Partners.\nللتواصل والدعم الفني المباشر متاح عبر لوحة التحكم.`;
        }
      } else if (type === 'sales') {
        form.ai_system_prompt = `أنت مسؤول المبيعات وخدمة العملاء. رحب بالعميل بحرارة، واعرض خدماتنا أو منتجاتنا بإيجاز، وشجعه على الشراء أو الاشتراك.`;
      } else if (type === 'brief') {
        form.ai_system_prompt = `أنت مساعد واتساب ذكي ومختصر جداً. قدم إجابات مباشرة بدون إطالة ولا تتعدى 2 إلى 3 جمل.`;
      }
    };

    const delayLabel = (d) => {
      return [0, 5, 10, 30].includes(d) ? t(`auto_reply_page.delay_${d}`) : `${d}`;
    };

    const selectSession = (id) => {
      form.session_id = id;
    };

    const toggleAutoReply = () => {
      if (!qualifies.value) {
        errorMessage.value = t('auto_reply_page.locked_message');
        return;
      }
      form.is_enabled = !form.is_enabled;
      if (form.is_enabled && !form.session_id && connectedSessions.value.length > 0) {
        form.session_id = connectedSessions.value[0].id;
      }
      if (form.is_enabled && form.mode === 'static' && !form.reply_message.trim()) {
        form.reply_message = defaultExample;
      }
      if (form.is_enabled && form.mode === 'ai' && !form.ai_system_prompt.trim()) {
        applyTemplate('support');
      }
    };

    const loadSubscription = async () => {
      try {
        const response = await api.get('/subscription');
        if (response.success) {
          subscription.value = response.data;
        }
      } catch (err) {
        console.error('Failed to load subscription:', err);
      }
    };

    const loadSettings = async () => {
      try {
        isLoading.value = true;
        errorMessage.value = '';
        const response = await api.get('/whatsapp/auto-reply');
        if (response.success && response.data) {
          sessions.value = response.data.sessions || [];
          const autoReply = response.data.auto_reply;

          if (autoReply) {
            form.is_enabled = Boolean(autoReply.is_enabled);
            form.session_id = autoReply.session_id;
            form.mode = autoReply.mode || 'ai';
            form.reply_message = autoReply.reply_message || '';
            form.reply_once = Boolean(autoReply.reply_once);
            form.delay_seconds = Number(autoReply.delay_seconds || 0);

            form.ai_system_prompt = autoReply.ai_system_prompt || '';
            form.ai_business_info = autoReply.ai_business_info || '';
            form.ai_tone = autoReply.ai_tone || 'friendly';
            form.ai_language = autoReply.ai_language || 'auto';
            form.ai_context_limit = Number(autoReply.ai_context_limit || 10);
            form.ai_fallback_enabled = Boolean(autoReply.ai_fallback_enabled);
            form.ai_fallback_message = autoReply.ai_fallback_message || '';
            form.ai_max_daily_replies = Number(autoReply.ai_max_daily_replies || 0);
            form.ai_business_hours_enabled = Boolean(autoReply.ai_business_hours_enabled);
            form.ai_business_hours_from = autoReply.ai_business_hours_from || '09:00';
            form.ai_business_hours_to = autoReply.ai_business_hours_to || '22:00';
            form.ai_model = autoReply.ai_model || 'google/gemma-4-31b-it:free';
            form.ai_handoff_minutes = Number(autoReply.ai_handoff_minutes || 0);

            stats.total_replies = autoReply.total_replies_sent || 0;
            stats.last_reply_at = autoReply.last_reply_at;
            stats.ai_total_replies = autoReply.ai_total_replies_sent || 0;
            stats.ai_last_reply_at = autoReply.ai_last_reply_at;
          } else if (connectedSessions.value.length > 0) {
            form.session_id = connectedSessions.value[0].id;
          }
        }
      } catch (err) {
        console.error('Failed to load auto reply settings:', err);
        errorMessage.value = err.response?.data?.message || 'فشل في تحميل إعدادات الرد التلقائي.';
      } finally {
        isLoading.value = false;
      }
    };

    const loadLogs = async () => {
      try {
        logsLoading.value = true;
        const response = await api.get('/whatsapp/auto-reply/logs');
        if (response.success && response.data) {
          logs.value = response.data.data || [];
        }
      } catch (err) {
        console.error('Failed to load auto reply logs:', err);
      } finally {
        logsLoading.value = false;
      }
    };

    const saveSettings = async () => {
      try {
        errorMessage.value = '';
        successMessage.value = '';

        if (!qualifies.value) {
          errorMessage.value = t('auto_reply_page.locked_message');
          return;
        }

        if (form.is_enabled) {
          if (!form.session_id) {
            errorMessage.value = 'يرجى اختيار جهاز WhatsApp متصل.';
            return;
          }

          const target = sessions.value.find((s) => s.id === form.session_id);
          if (!target || target.status !== 'connected') {
            errorMessage.value = 'لا يمكن التفعيل على جهاز غير متصل.';
            return;
          }

          if (form.mode === 'static' && !form.reply_message.trim()) {
            errorMessage.value = 'يرجى كتابة رسالة الرد الثابت.';
            return;
          }

          if (form.mode === 'ai' && !form.ai_system_prompt.trim()) {
            errorMessage.value = 'يرجى كتابة تعليمات AI.';
            return;
          }
        }

        isSaving.value = true;
        const payload = { ...form };

        const response = await api.post('/whatsapp/auto-reply', payload);
        if (response.success) {
          successMessage.value = t('auto_reply_page.save_success');
          if (response.data?.auto_reply) {
            stats.total_replies = response.data.auto_reply.total_replies_sent || 0;
            stats.last_reply_at = response.data.auto_reply.last_reply_at;
            stats.ai_total_replies = response.data.auto_reply.ai_total_replies_sent || 0;
            stats.ai_last_reply_at = response.data.auto_reply.ai_last_reply_at;
          }
          loadLogs();
          window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
          errorMessage.value = response.message || 'حدث خطأ أثناء حفظ الإعدادات.';
        }
      } catch (err) {
        console.error('Failed to save auto reply settings:', err);
        errorMessage.value = err.response?.data?.message || 'حدث خطأ أثناء حفظ الإعدادات.';
      } finally {
        isSaving.value = false;
      }
    };

    const testAI = async () => {
      if (!aiTestMessage.value.trim()) return;
      try {
        aiTestLoading.value = true;
        aiTestResult.value = '';
        const response = await api.post('/whatsapp/auto-reply/test-ai', {
          test_message: aiTestMessage.value,
          session_id: form.session_id,
        });
        if (response.success && response.data) {
          aiTestResult.value = response.data.reply;
        } else {
          errorMessage.value = response.message || 'فشل اختبار AI.';
        }
      } catch (err) {
        errorMessage.value = err.response?.data?.message || 'فشل اختبار AI.';
      } finally {
        aiTestLoading.value = false;
      }
    };

    const getStatusLabel = (status) => {
      const labels = {
        sent: t('auto_reply_page.status_sent'),
        queued: t('auto_reply_page.status_queued'),
        failed: t('auto_reply_page.status_failed'),
        cooldown: t('auto_reply_page.status_cooldown'),
        reply_once_skipped: t('auto_reply_page.status_reply_once_skipped'),
        disabled: t('auto_reply_page.status_feature_disabled'),
        ai_pending: t('auto_reply_page.status_ai_pending'),
        processing: t('auto_reply_page.status_ai_processing'),
        blocked: t('auto_reply_page.status_ai_blocked'),
      };
      return labels[status] || status;
    };

    const getStatusBadgeClass = (status) => {
      switch (status) {
        case 'sent': return 'bg-emerald-50 text-emerald-700 border border-emerald-200/60';
        case 'queued': return 'bg-blue-50 text-blue-700 border border-blue-200/60';
        case 'failed':
        case 'ai_failed': return 'bg-red-50 text-red-700 border border-red-200/60';
        case 'cooldown': return 'bg-amber-50 text-amber-700 border border-amber-200/60';
        case 'reply_once_skipped': return 'bg-purple-50 text-purple-700 border border-purple-200/60';
        case 'ai_pending':
        case 'processing': return 'bg-indigo-50 text-indigo-700 border border-indigo-200/60';
        case 'blocked': return 'bg-slate-100 text-slate-600 border border-slate-200';
        default: return 'bg-slate-100 text-slate-600 border border-slate-200';
      }
    };

    const getStatusDotClass = (status) => {
      switch (status) {
        case 'sent': return 'bg-[#00C853]';
        case 'queued': return 'bg-blue-500 animate-pulse';
        case 'failed':
        case 'ai_failed': return 'bg-red-500';
        case 'cooldown': return 'bg-amber-500';
        case 'reply_once_skipped': return 'bg-purple-500';
        case 'ai_pending':
        case 'processing': return 'bg-indigo-500 animate-pulse';
        case 'blocked': return 'bg-slate-400';
        default: return 'bg-slate-400';
      }
    };

    const formatDate = (dateStr) => {
      if (!dateStr) return '—';
      try {
        const d = new Date(dateStr);
        return d.toLocaleString('ar-EG', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
      } catch (e) {
        return dateStr;
      }
    };

    const formatPhone = (phone) => {
      if (!phone) return '—';
      const digits = phone.replace(/\D/g, '');
      // Egyptian mobile stored with country code, e.g. 201011679726 -> 01011679726
      if (/^20\d{10,11}$/.test(digits)) {
        return '0' + digits.slice(2);
      }
      return phone;
    };

    onMounted(() => {
      loadSubscription();
      loadSettings();
      loadLogs();
    });

    return {
      t,
      isRTL,
      isLoading,
      isSaving,
      logsLoading,
      successMessage,
      errorMessage,
      messageInputRef,
      subscription,
      qualifies,
      sessions,
      connectedSessions,
      selectedSession,
      logs,
      stats,
      form,
      aiTestMessage,
      aiTestLoading,
      aiTestResult,
      selectSession,
      toggleAutoReply,
      applyDefaultTemplate,
      applyTemplate,
      delayLabel,
      saveSettings,
      loadLogs,
      testAI,
      getStatusLabel,
      getStatusBadgeClass,
      getStatusDotClass,
      formatDate,
      formatPhone,
    };
  },
};
</script>
