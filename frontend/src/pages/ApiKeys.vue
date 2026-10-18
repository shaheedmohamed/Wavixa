<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('api_keys_page.title') }}</h2>
          <p class="text-gray-500 text-sm mt-1">{{ t('api_keys_page.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
          <router-link
            to="/api-docs"
            class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-50 transition-all shadow-sm active:scale-95"
          >
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
            <span>{{ t('api_keys_page.api_guide') }}</span>
          </router-link>
          <button
            @click="showCreateModal = true"
            class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-sm active:scale-95"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>{{ t('api_keys_page.create_key') }}</span>
          </button>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
          </div>
          <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ t('api_keys_page.total_keys') }}</p>
            <p class="text-2xl font-black text-gray-900 leading-none">{{ apiKeys.total || 0 }}</p>
          </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          </div>
          <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ t('api_keys_page.active_keys') }}</p>
            <p class="text-2xl font-black text-green-600 leading-none">{{ activeKeysCount }}</p>
          </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
          </div>
          <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ t('api_keys_page.total_requests') }}</p>
            <p class="text-2xl font-black text-orange-600 leading-none">{{ totalRequests }}</p>
          </div>
        </div>
      </div>

      <!-- Filters Area -->
      <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex flex-col md:flex-row gap-4 items-center">
        <div class="relative flex-1 w-full">
          <svg class="absolute top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" :class="isRTL ? 'right-3' : 'left-3'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
          <input
            v-model="filters.search"
            @input="debounceSearch"
            type="text"
            :placeholder="t('api_keys_page.search_placeholder')"
            class="w-full bg-gray-50 border border-gray-100 rounded-xl py-2 text-sm text-gray-700 placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none"
            :class="isRTL ? 'pr-10 pl-4' : 'pl-10 pr-4'"
          />
        </div>
        <div class="flex items-center gap-3 w-full md:w-auto">
          <select v-model="filters.is_active" @change="loadApiKeys(1)" class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2 text-sm text-gray-700 focus:bg-white focus:border-indigo-300 transition-all outline-none">
            <option value="">{{ t('api_keys_page.all_statuses') }}</option>
            <option value="1">{{ t('api_keys_page.active') }}</option>
            <option value="0">{{ t('api_keys_page.inactive') }}</option>
          </select>
        </div>
      </div>

      <!-- API Keys Table -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse" :class="isRTL ? 'text-right' : 'text-left'">
            <thead>
              <tr class="bg-gray-50/50 border-b border-gray-50">
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('api_keys_page.th_name') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('api_keys_page.th_key') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('api_keys_page.th_device') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('api_keys_page.th_status') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('api_keys_page.th_requests') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('api_keys_page.th_last_used') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ t('api_keys_page.th_created_at') }}</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider" :class="isRTL ? 'text-left' : 'text-right'">{{ t('api_keys_page.th_actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-if="loading" class="animate-pulse">
                <td colspan="8" class="px-6 py-12 text-center text-gray-400 font-medium">{{ t('common.loading') }}</td>
              </tr>
              <tr v-else-if="apiKeys.data && apiKeys.data.length === 0">
                <td colspan="8" class="px-6 py-20 text-center">
                  <div class="flex flex-col items-center">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4 text-gray-200">
                      <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                    </div>
                    <p class="text-gray-900 font-bold">{{ isRTL ? 'لا يوجد مفاتيح API' : 'No API Keys' }}</p>
                    <p class="text-gray-400 text-sm mt-1">{{ isRTL ? 'ابدأ بإنشاء مفتاحك الأول الآن' : 'Generate your first API key now' }}</p>
                  </div>
                </td>
              </tr>
              <tr v-else v-for="key in apiKeys.data" :key="key.id" class="hover:bg-gray-50/50 transition-all group">
                <td class="px-6 py-4">
                  <div class="flex flex-col">
                    <span class="text-sm font-black text-gray-900">{{ key.name }}</span>
                    <span v-if="key.expires_at" class="text-[10px] text-red-500 font-bold">{{ isRTL ? `ينتهي في: ${formatDate(key.expires_at)}` : `Expires: ${formatDate(key.expires_at)}` }}</span>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <span class="text-xs font-bold text-gray-500 font-mono bg-gray-100 px-2 py-0.5 rounded-md" dir="ltr">{{ key.prefix }}...</span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex flex-col gap-1">
                    <span v-if="key.whatsapp_session" class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-700">
                      <span class="w-2 h-2 rounded-full" :class="key.whatsapp_session.status === 'connected' ? 'bg-green-500' : 'bg-gray-300'"></span>
                      <span dir="ltr">{{ key.whatsapp_session.device_name || key.whatsapp_session.phone_number || 'Device ' + key.whatsapp_session.id }}</span>
                    </span>
                    <span v-if="key.meta_phone_number" class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700">
                      <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                      <span dir="ltr">Meta: {{ key.meta_phone_number.phone_number }}</span>
                    </span>
                    <span v-if="key.allow_remote_pairing" class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600">
                      <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                      API Pairing ({{ key.remote_pairing_limit }})
                    </span>
                    <span v-if="!key.whatsapp_session && !key.meta_phone_number && !key.allow_remote_pairing" class="text-xs font-bold text-gray-400">{{ isRTL ? 'غير مرتبط' : 'Unlinked' }}</span>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider', key.is_active ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600']">
                    {{ key.is_active ? t('api_keys_page.active') : t('api_keys_page.inactive') }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm font-black text-gray-900">{{ key.requests_count }}</td>
                <td class="px-6 py-4 text-xs font-bold text-gray-400">{{ key.last_used_at ? formatDate(key.last_used_at) : (isRTL ? 'لم يستخدم بعد' : 'Never used') }}</td>
                <td class="px-6 py-4 text-xs font-bold text-gray-400">{{ formatDate(key.created_at) }}</td>
                <td class="px-6 py-4" :class="isRTL ? 'text-left' : 'text-right'">
                  <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity" :class="isRTL ? 'justify-end' : 'justify-end'">
                    <button @click="copyKey(key)" class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all" :title="t('api_keys_page.copy')">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                    </button>
                    <button @click="testKey(key)" class="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-all" title="Test">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </button>
                    <button @click="regenerateKey(key)" class="p-2 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-all" title="Regenerate">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    </button>
                    <button @click="toggleKey(key)" class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all" :title="key.is_active ? t('api_keys_page.revoke') : 'Enable'">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="key.is_active ? 'M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z' : 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z'" /></svg>
                    </button>
                    <button @click="deleteKey(key)" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" :title="t('api_keys_page.delete')">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="apiKeys.last_page > 1" class="px-6 py-4 bg-gray-50/50 border-t border-gray-50 flex items-center justify-center gap-4">
          <button @click="changePage(apiKeys.current_page - 1)" :disabled="apiKeys.current_page === 1" class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-white rounded-lg border border-transparent hover:border-gray-200 disabled:opacity-30 disabled:hover:bg-transparent transition-all">
            <svg class="w-5 h-5" :class="isRTL ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
          </button>
          <span class="text-xs font-black text-gray-900 mx-2">{{ t('messages_page.page') }} {{ apiKeys.current_page }} {{ t('messages_page.of') }} {{ apiKeys.last_page }}</span>
          <button @click="changePage(apiKeys.current_page + 1)" :disabled="apiKeys.current_page === apiKeys.last_page" class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-white rounded-lg border border-transparent hover:border-gray-200 disabled:opacity-30 disabled:hover:bg-transparent transition-all">
            <svg class="w-5 h-5" :class="isRTL ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Create Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-in fade-in duration-200">
      <div class="bg-white border border-gray-100 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden" :dir="isRTL ? 'rtl' : 'ltr'">
        <div class="px-8 py-6 border-b border-gray-50 flex items-center justify-between bg-gray-50/30">
          <h3 class="text-lg font-black text-gray-900">{{ t('api_keys_page.modal_create_title') }}</h3>
          <button @click="showCreateModal = false" class="w-8 h-8 rounded-full flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all font-bold">&times;</button>
        </div>
        <form @submit.prevent="createKey" class="p-8 space-y-6">
          <div class="space-y-2">
            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('api_keys_page.modal_key_name') }}</label>
            <input
              v-model="newKey.name"
              type="text"
              :placeholder="t('api_keys_page.modal_name_placeholder')"
              class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none"
              required
            />
          </div>
          <div class="space-y-2">
            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('api_keys_page.modal_select_device') }} ({{ t('common.optional') }})</label>
            <select
              v-model="newKey.whatsapp_session_id"
              class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold focus:bg-white focus:border-indigo-300 transition-all outline-none"
            >
              <option value="">{{ isRTL ? 'أي جهاز متصل' : 'Any connected device' }}</option>
              <option value="remote" :disabled="!canUseRemotePairing">
                {{ canUseRemotePairing ? (isRTL ? 'اتصال جديد عبر API' : 'New API Connection') : (isRTL ? 'اتصال جديد عبر API (Partners فقط)' : 'New API Connection (Partners only)') }}
              </option>
              <option v-for="device in connectedDevices" :key="device.id" :value="device.id">
                {{ device.phone_number || device.device_name || 'Device ' + device.id }}
              </option>
            </select>
          </div>



          <div v-if="newKey.whatsapp_session_id === 'remote'" class="space-y-2">
            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ isRTL ? 'حد اتصالات الـ API' : 'API Connection Limit' }}</label>
            <input
              v-model.number="newKey.remote_pairing_limit"
              type="number"
              min="1"
              max="100"
              class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold focus:bg-white focus:border-indigo-300 transition-all outline-none"
            />
          </div>
          <div class="space-y-2">
            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ isRTL ? 'تاريخ الانتهاء (اختياري)' : 'Expiration Date (Optional)' }}</label>
            <input
              v-model="newKey.expires_at"
              type="date"
              class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-3 text-gray-900 font-bold placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none"
            />
          </div>
          <div v-if="createError" class="p-4 bg-red-50 text-red-600 text-xs font-bold rounded-2xl border border-red-100">{{ createError }}</div>
          <button
            type="submit"
            :disabled="isCreating"
            class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-6 py-3 rounded-2xl font-black text-sm transition-all active:scale-95 flex items-center justify-center gap-3"
          >
            <svg v-if="isCreating" class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span>{{ isCreating ? t('common.loading') : t('api_keys_page.modal_create_btn') }}</span>
          </button>
        </form>
      </div>
    </div>

    <!-- Generated Key Modal -->
    <div v-if="showKeyModal" class="fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-in fade-in duration-200">
      <div class="bg-white border border-gray-100 rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden" :dir="isRTL ? 'rtl' : 'ltr'">
        <div class="p-8 text-center space-y-6">
          <div class="w-16 h-16 bg-green-50 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4 border border-green-100">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
          </div>
          <div>
            <h3 class="text-xl font-black text-gray-900 mb-2">{{ t('api_keys_page.key_created_title') }}</h3>
            <p class="text-sm text-gray-500 font-medium">{{ t('api_keys_page.key_created_warning') }}</p>
          </div>
          <div class="relative group">
            <input
              ref="keyInput"
              :value="generatedKey"
              readonly
              class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-4 text-sm font-black text-gray-900 font-mono select-all focus:bg-white focus:border-indigo-300 outline-none transition-all"
              :class="isRTL ? 'pl-16 pr-6' : 'pr-16 pl-6'"
              dir="ltr"
            />
            <button @click="copyGeneratedKey" class="absolute inset-y-0 px-4 text-indigo-600 hover:text-indigo-700 transition-colors font-bold text-xs" :class="isRTL ? 'left-0' : 'right-0'">{{ t('api_keys_page.copy') }}</button>
          </div>
          <button
            @click="showKeyModal = false"
            class="w-full bg-gray-900 text-white px-6 py-3 rounded-2xl font-black text-sm hover:bg-black transition-all active:scale-95"
          >
            {{ t('api_keys_page.done') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Toast -->
    <div v-if="toast.show" class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50 bg-gray-900 text-white px-6 py-3 rounded-2xl font-bold text-sm shadow-2xl animate-in slide-in-from-bottom-4">
      {{ toast.message }}
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import auth from '@/services/auth';
import { t, isRTL } from '@/i18n';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

export default {
  name: 'ApiKeys',
  components: { DashboardLayout },
  setup() {
    const router = useRouter();
    const apiKeys = ref({ data: [], total: 0, current_page: 1, last_page: 1 });
    const connectedDevices = ref([]);
    const metaNumbers = ref([]);
    const loading = ref(false);
    const showCreateModal = ref(false);
    const showKeyModal = ref(false);
    const generatedKey = ref('');
    const newKey = ref({ name: '', expires_at: '', whatsapp_session_id: '', meta_phone_number_id: '', remote_pairing_limit: 1 });
    const currentUser = ref(auth.getUser() || {});
    const canUseRemotePairing = computed(() => {
      const plan = (currentUser.value?.subscription_plan || '').toLowerCase();
      return plan.includes('partners') && currentUser.value?.subscription_status === 'active';
    });
    const createError = ref('');
    const isCreating = ref(false);
    const toast = ref({ show: false, message: '' });
    const filters = ref({ search: '', is_active: '' });
    let searchTimeout = null;

    const activeKeysCount = computed(() => {
      return (apiKeys.value.data || []).filter(k => k.is_active).length;
    });

    const totalRequests = computed(() => {
      return (apiKeys.value.data || []).reduce((sum, k) => sum + (k.requests_count || 0), 0);
    });

    onMounted(async () => {
      const userData = auth.getUser();
      if (!userData) {
        router.push('/login');
        return;
      }
      await loadApiKeys();
      await loadConnectedDevices();


      try {
        const response = await auth.getCurrentUser();
        if (response.success && response.data) {
          currentUser.value = response.data;
          auth.setUser(response.data);
        }
      } catch (err) {
        console.error('Error refreshing user:', err);
      }
    });

    const loadConnectedDevices = async () => {
      try {
        const response = await api.get('/whatsapp/sessions');
        if (response.success) {
          connectedDevices.value = (response.data || []).filter(device => device.status === 'connected');
        }
      } catch (err) {
        console.error('Error loading connected devices:', err);
      }
    };

    const loadMetaNumbers = async () => {
      try {
        const response = await api.get('/meta-test/phone-numbers');
        if (response.success) {
          metaNumbers.value = (response.data || []).filter(n => n.is_active);
        }
      } catch (err) {
        console.error('Error loading Meta numbers:', err);
      }
    };

    const loadApiKeys = async (page = 1) => {
      try {
        loading.value = true;
        const params = new URLSearchParams();
        params.append('page', page);
        if (filters.value.search) params.append('search', filters.value.search);
        if (filters.value.is_active !== '') params.append('is_active', filters.value.is_active);

        const response = await api.get(`/api-keys?${params.toString()}`);
        if (response.success) {
          apiKeys.value = response.data;
        }
      } catch (err) {
        console.error('Error loading API keys:', err);
      } finally {
        loading.value = false;
      }
    };

    const debounceSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => loadApiKeys(1), 300);
    };

    const changePage = (page) => {
      if (page < 1 || page > apiKeys.value.last_page) return;
      loadApiKeys(page);
    };

    const createKey = async () => {
      try {
        isCreating.value = true;
        createError.value = '';

        const payload = { ...newKey.value };
        if (payload.whatsapp_session_id === 'remote') {
          payload.whatsapp_session_id = '';
          payload.allow_remote_pairing = true;
        } else {
          payload.allow_remote_pairing = false;
        }

        const response = await api.post('/api-keys', payload);
        if (response.success) {
          generatedKey.value = response.data.key;
          showCreateModal.value = false;
          showKeyModal.value = true;
          newKey.value = { name: '', expires_at: '', whatsapp_session_id: '', meta_phone_number_id: '', remote_pairing_limit: 1 };
          await loadApiKeys();
        } else {
          createError.value = response.message || (isRTL.value ? 'فشل إنشاء المفتاح' : 'Failed to create key');
        }
      } catch (err) {
        createError.value = err.data?.message || (isRTL.value ? 'فشل إنشاء المفتاح' : 'Failed to create key');
      } finally {
        isCreating.value = false;
      }
    };

    const copyKey = (key) => {
      showToast(isRTL.value ? 'المفتاح الكامل لا يتم تخزينه لدواعي أمنية. قم بتجديده للنسخ.' : 'Full secret is not stored for security. Regenerate to view.');
    };

    const testKey = async (key) => {
      try {
        const response = await api.post(`/api-keys/${key.id}/test`, {});
        if (response.success) {
          showToast(isRTL.value ? `تم اختبار المفتاح بنجاح! الحالة: ${response.status_code}` : `Key verified successfully! Status: ${response.status_code}`);
        } else {
          showToast(isRTL.value ? `فشل الاختبار: ${response.message}` : `Test failed: ${response.message}`);
        }
      } catch (err) {
        showToast(err.data?.message || (isRTL.value ? 'فشل اختبار المفتاح' : 'Failed to test key'));
      }
    };

    const copyGeneratedKey = () => {
      navigator.clipboard.writeText(generatedKey.value);
      showToast(t('api_keys_page.copied'));
    };

    const regenerateKey = async (key) => {
      if (!confirm(isRTL.value ? 'هل أنت متأكد؟ سيتم تعطيل المفتاح القديم فوراً.' : 'Are you sure? The previous key will be invalidated immediately.')) return;
      try {
        const response = await api.post(`/api-keys/${key.id}/regenerate`, {});
        if (response.success) {
          generatedKey.value = response.data.key;
          showKeyModal.value = true;
          await loadApiKeys();
          showToast(isRTL.value ? 'تم تجديد المفتاح بنجاح' : 'Key regenerated successfully');
        }
      } catch (err) {
        showToast(err.data?.message || (isRTL.value ? 'فشل تجديد المفتاح' : 'Failed to regenerate key'));
      }
    };

    const toggleKey = async (key) => {
      try {
        const response = await api.put(`/api-keys/${key.id}`, { is_active: !key.is_active });
        if (response.success) {
          await loadApiKeys(apiKeys.value.current_page);
          showToast(isRTL.value ? `تم ${key.is_active ? 'تعطيل' : 'تفعيل'} المفتاح` : `Key ${key.is_active ? 'disabled' : 'activated'}`);
        }
      } catch (err) {
        showToast(err.data?.message || (isRTL.value ? 'فشل تحديث المفتاح' : 'Failed to update key'));
      }
    };

    const deleteKey = async (key) => {
      if (!confirm(isRTL.value ? 'هل أنت متأكد من رغبتك في حذف هذا المفتاح؟ لا يمكن التراجع عن هذا الإجراء.' : 'Are you sure you want to delete this API key? This action is irreversible.')) return;
      try {
        const response = await api.delete(`/api-keys/${key.id}`);
        if (response.success) {
          await loadApiKeys(apiKeys.value.current_page);
          showToast(isRTL.value ? 'تم حذف المفتاح بنجاح' : 'Key deleted successfully');
        }
      } catch (err) {
        showToast(err.data?.message || (isRTL.value ? 'فشل حذف المفتاح' : 'Failed to delete key'));
      }
    };

    const showToast = (message) => {
      toast.value = { show: true, message };
      setTimeout(() => toast.value.show = false, 3000);
    };

    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleDateString(isRTL.value ? 'ar-EG' : 'en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    };

    return {
      apiKeys,
      loading,
      showCreateModal,
      showKeyModal,
      generatedKey,
      newKey,
      createError,
      isCreating,
      toast,
      filters,
      connectedDevices,
      metaNumbers,
      activeKeysCount,
      totalRequests,
      canUseRemotePairing,
      loadApiKeys,
      loadConnectedDevices,
      debounceSearch,
      changePage,
      createKey,
      copyKey,
      testKey,
      copyGeneratedKey,
      regenerateKey,
      toggleKey,
      deleteKey,
      formatDate,
      t,
      isRTL,
    };
  },
};
</script>

<style scoped>
.animate-in {
  animation: animate-in 0.3s ease-out;
}

@keyframes animate-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
