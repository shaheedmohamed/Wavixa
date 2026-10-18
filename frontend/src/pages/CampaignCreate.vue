<template>
  <DashboardLayout>
    <div class="space-y-6 animate-in fade-in duration-500" :dir="isRTL ? 'rtl' : 'ltr'">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('campaign_create.title') }}</h2>
          <p class="text-gray-500 text-sm mt-1">{{ t('campaign_create.subtitle') }}</p>
        </div>
        <router-link to="/campaigns" class="text-xs font-bold text-gray-500 hover:text-indigo-600 transition-colors">{{ t('campaign_create.back_to_campaigns') }}</router-link>
      </div>

      <!-- Stepper -->
      <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-4">
        <div class="flex items-center justify-between gap-2 overflow-x-auto">
          <button
            v-for="(step, index) in steps"
            :key="step.key"
            @click="setStep(index)"
            class="flex items-center gap-2 px-4 py-2 rounded-2xl text-xs font-bold whitespace-nowrap transition-all"
            :class="stepClass(index)"
          >
            <span class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-black" :class="stepNumberClass(index)">{{ index + 1 }}</span>
            <span>{{ step.label }}</span>
          </button>
        </div>
      </div>

      <!-- Step 1: Name -->
      <div v-if="currentStep === 0" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8">
        <h3 class="text-lg font-black text-gray-900 mb-6">{{ t('campaign_create.step_name') }}</h3>
        <div class="space-y-2">
          <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('campaign_create.campaign_name_label') }}</label>
          <input
            v-model="form.name"
            type="text"
            :placeholder="t('campaign_create.campaign_name_placeholder')"
            class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-4 text-gray-900 font-bold placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none"
          />
          <p class="text-[10px] text-gray-400 font-bold">{{ t('campaign_create.campaign_name_hint') }}</p>
        </div>
      </div>

      <!-- Step 2: Device -->
      <div v-if="currentStep === 1" class="space-y-6">
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8">
          <h3 class="text-lg font-black text-gray-900 mb-6">{{ t('campaign_create.select_device') }}</h3>
          <div v-if="sessions.length === 0" class="text-center py-12 bg-gray-50 rounded-2xl border border-gray-100">
            <p class="text-sm font-bold text-gray-500 mb-4">{{ t('campaign_create.no_connected_device') }}</p>
            <router-link to="/whatsapp-connect" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-2xl font-black text-xs transition-all active:scale-95">{{ t('campaign_create.connect_new_device') }}</router-link>
          </div>
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
              v-for="session in sessions"
              :key="session.id"
              @click="form.session_id = session.id"
              :class="['p-5 rounded-2xl border-2 cursor-pointer transition-all', form.session_id === session.id ? 'border-indigo-500 bg-indigo-50/50' : 'border-gray-100 hover:border-indigo-200']"
            >
              <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-black text-gray-900">{{ session.device_name || 'WhatsApp Device' }}</span>
                <span :class="['w-2 h-2 rounded-full', session.status === 'connected' ? 'bg-green-500' : 'bg-red-500']"></span>
              </div>
              <p class="text-sm font-mono font-bold text-gray-700" dir="ltr">{{ session.phone_number || '-' }}</p>
              <p class="text-[10px] font-bold mt-2" :class="session.status === 'connected' ? 'text-green-600' : 'text-red-600'">
                {{ session.status === 'connected' ? t('common.connected') : t('common.disconnected') }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Step 3: Recipients -->
      <div v-if="currentStep === 2" class="space-y-6">
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8">
          <h3 class="text-lg font-black text-gray-900 mb-6">{{ t('campaign_create.add_recipients') }}</h3>

          <div class="flex gap-2 mb-6 p-1 bg-gray-50 rounded-2xl w-fit">
            <button v-if="contacts.length" @click="recipientMode = 'contacts'" :class="['px-5 py-2.5 rounded-xl text-xs font-black transition-all', recipientMode === 'contacts' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500']">{{ t('campaign_create.tab_contacts') }}</button>
            <button @click="recipientMode = 'file'" :class="['px-5 py-2.5 rounded-xl text-xs font-black transition-all', recipientMode === 'file' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500']">{{ t('campaign_create.tab_file') }}</button>
            <button @click="recipientMode = 'manual'" :class="['px-5 py-2.5 rounded-xl text-xs font-black transition-all', recipientMode === 'manual' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500']">{{ t('campaign_create.tab_manual') }}</button>
          </div>

          <div v-if="recipientMode === 'file'" class="space-y-4">
            <div class="border-2 border-dashed border-gray-200 rounded-2xl p-8 text-center hover:border-indigo-300 transition-colors">
              <input ref="fileInput" type="file" accept=".csv,.xlsx,.xls,.txt,.vcf" @change="handleFile" class="hidden" />
              <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
              </div>
              <p class="text-sm font-bold text-gray-900 mb-1">{{ t('campaign_create.upload_file_title') }}</p>
              <p class="text-[10px] text-gray-400 mb-4">{{ t('campaign_create.upload_file_hint') }}</p>
              <button @click="$refs.fileInput.click()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-xs font-bold transition-all active:scale-95">{{ t('campaign_create.choose_file') }}</button>
              <p v-if="uploadedFile" class="mt-3 text-xs font-bold text-green-600">{{ uploadedFile.name }}</p>
            </div>
          </div>

          <div v-else-if="recipientMode === 'contacts'" class="space-y-4">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
              <input v-model="contactSearch" type="text" :placeholder="t('campaign_create.search_contacts')" class="flex-1 bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm font-bold text-gray-900 placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none" />
              <div class="flex gap-2">
                <button @click="selectAllContacts" class="px-4 py-2.5 bg-indigo-50 text-indigo-700 rounded-xl text-xs font-black hover:bg-indigo-100 transition-colors">{{ t('campaign_create.select_all') }}</button>
                <button @click="deselectAllContacts" class="px-4 py-2.5 bg-gray-50 text-gray-600 rounded-xl text-xs font-black hover:bg-gray-100 transition-colors">{{ t('campaign_create.deselect_all') }}</button>
              </div>
            </div>
            <p class="text-[10px] font-bold text-gray-400">
              {{ isRTL ? `تم اختيار ${Number(selectedContactsCount).toLocaleString()} من ${Number(contacts.length).toLocaleString()} جهة اتصال` : `Selected ${Number(selectedContactsCount).toLocaleString()} of ${Number(contacts.length).toLocaleString()} contacts` }}
            </p>
            <div v-if="filteredContacts.length === 0" class="text-center py-8 text-gray-400 text-sm font-bold">{{ t('common.search_empty') }}</div>
            <div v-else class="border border-gray-100 rounded-2xl overflow-hidden max-h-64 overflow-y-auto">
              <table class="w-full text-xs" :class="isRTL ? 'text-right' : 'text-left'">
                <thead class="bg-gray-50 sticky top-0">
                  <tr>
                    <th class="px-4 py-2 font-black text-gray-400 w-10"></th>
                    <th class="px-4 py-2 font-black text-gray-400">{{ t('contacts_page.th_name') }}</th>
                    <th class="px-4 py-2 font-black text-gray-400">{{ t('contacts_page.th_phone') }}</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                  <tr v-for="contact in filteredContacts" :key="contact.phone" class="hover:bg-gray-50/50">
                    <td class="px-4 py-2">
                      <input type="checkbox" v-model="selectedPhones[contact.phone]" class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    </td>
                    <td class="px-4 py-2 font-bold text-gray-700">{{ contact.name || '-' }}</td>
                    <td class="px-4 py-2 font-mono font-bold text-gray-900" dir="ltr">{{ contact.phone }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button @click="validateContacts" :disabled="selectedContactsCount === 0 || validating" class="w-full bg-gray-900 hover:bg-black disabled:opacity-70 disabled:cursor-not-allowed text-white px-6 py-3 rounded-2xl font-bold text-xs transition-all active:scale-95 flex items-center justify-center gap-2">
              <svg v-if="validating" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              <span>{{ validating ? (isRTL ? 'جاري التحقق...' : 'Validating...') : (isRTL ? 'تحقق من الأرقام المختارة' : 'Validate Selected Numbers') }}</span>
            </button>
          </div>

          <div v-else-if="recipientMode === 'manual'" class="space-y-4">
            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('campaign_create.manual_placeholder') }}</label>
            <textarea
              v-model="manualNumbers"
              rows="8"
              placeholder="+201000000000&#10;+201111111111&#10;+201222222222"
              class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-4 text-gray-900 font-medium placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none resize-none font-mono"
              dir="ltr"
            />
            <button @click="validateManual" :disabled="validating" class="w-full bg-gray-900 hover:bg-black disabled:opacity-70 disabled:cursor-not-allowed text-white px-6 py-3 rounded-2xl font-bold text-xs transition-all active:scale-95 flex items-center justify-center gap-2">
              <svg v-if="validating" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              <span>{{ validating ? (isRTL ? 'جاري التحقق...' : 'Validating...') : (isRTL ? 'تحقق من الأرقام' : 'Validate Numbers') }}</span>
            </button>
          </div>
        </div>

        <!-- Validation Result -->
        <div v-if="validation" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-6">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100 text-center">
              <p class="text-[10px] font-bold text-gray-400 uppercase">{{ isRTL ? 'إجمالي الأرقام' : 'Total Numbers' }}</p>
              <p class="text-xl font-black text-gray-900">{{ Number(validation.total).toLocaleString() }}</p>
            </div>
            <div class="p-4 rounded-2xl bg-green-50 border border-green-100 text-center">
              <p class="text-[10px] font-bold text-green-600 uppercase">{{ isRTL ? 'صحيحة' : 'Valid' }}</p>
              <p class="text-xl font-black text-green-700">{{ Number(validation.valid_count).toLocaleString() }}</p>
            </div>
            <div class="p-4 rounded-2xl bg-red-50 border border-red-100 text-center">
              <p class="text-[10px] font-bold text-red-600 uppercase">{{ isRTL ? 'غير صحيحة' : 'Invalid' }}</p>
              <p class="text-xl font-black text-red-700">{{ Number(validation.invalid_count).toLocaleString() }}</p>
            </div>
            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-100 text-center">
              <p class="text-[10px] font-bold text-amber-600 uppercase">{{ isRTL ? 'مكررة' : 'Duplicate' }}</p>
              <p class="text-xl font-black text-amber-700">{{ Number(validation.duplicate_count).toLocaleString() }}</p>
            </div>
          </div>

          <div class="flex items-center gap-4">
            <button v-if="validation.invalid.length > 0" @click="removeInvalid" class="px-4 py-2 text-xs font-bold text-red-600 bg-red-50 hover:bg-red-100 rounded-xl transition-colors">{{ isRTL ? 'حذف الأرقام غير الصحيحة' : 'Remove Invalid Numbers' }}</button>
            <button v-if="validation.duplicates.length > 0" @click="removeDuplicates" class="px-4 py-2 text-xs font-bold text-amber-600 bg-amber-50 hover:bg-amber-100 rounded-xl transition-colors">{{ isRTL ? 'حذف الأرقام المكررة' : 'Remove Duplicate Numbers' }}</button>
          </div>

          <div v-if="validation.valid.length > 0" class="border border-gray-100 rounded-2xl overflow-hidden max-h-64 overflow-y-auto">
            <table class="w-full text-xs" :class="isRTL ? 'text-right' : 'text-left'">
              <thead class="bg-gray-50 sticky top-0">
                <tr>
                  <th class="px-4 py-2 font-black text-gray-400">{{ t('contacts_page.th_name') }}</th>
                  <th class="px-4 py-2 font-black text-gray-400">{{ t('contacts_page.th_phone') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <tr v-for="(r, i) in validation.valid.slice(0, 20)" :key="i" class="hover:bg-gray-50/50">
                  <td class="px-4 py-2 font-bold text-gray-700">{{ r.name || '-' }}</td>
                  <td class="px-4 py-2 font-mono font-bold text-gray-900" dir="ltr">{{ r.phone }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Step 4: Message -->
      <div v-if="currentStep === 3" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-black text-gray-900">{{ t('campaign_create.step_message') }}</h3>
            <span class="text-[10px] font-black text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full">{{ activeVariations.length }} / 5</span>
          </div>
          <p class="text-[10px] font-bold text-gray-400 leading-relaxed">{{ t('campaign_create.variations_hint') }}</p>

          <div v-for="(msg, i) in form.messages" :key="i" class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="text-xs font-black text-gray-400 uppercase tracking-widest">{{ t('campaign_create.variation_label') }} {{ i + 1 }}</label>
              <button
                v-if="form.messages.length > 1"
                @click="removeVariation(i)"
                class="text-[10px] font-black text-red-500 hover:text-red-700 transition-colors"
              >{{ t('campaign_create.remove_variation') }}</button>
            </div>
            <textarea
              v-model="form.messages[i]"
              rows="6"
              :placeholder="t('campaign_create.message_content_placeholder')"
              class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-6 py-4 text-gray-900 font-medium placeholder-gray-400 focus:bg-white focus:border-indigo-300 transition-all outline-none resize-none"
            />
            <p class="text-[10px] font-bold text-left" dir="ltr" :class="(msg || '').length > 4096 ? 'text-red-500' : 'text-gray-400'">{{ (msg || '').length }} / 4096</p>
          </div>

          <button
            v-if="form.messages.length < 5"
            @click="addVariation"
            class="w-full border-2 border-dashed border-gray-200 hover:border-indigo-300 text-gray-500 hover:text-indigo-600 rounded-2xl py-3 text-xs font-black transition-all active:scale-95"
          >+ {{ t('campaign_create.add_variation') }}</button>

          <p class="text-[10px] font-bold text-gray-400">{{ t('campaign_create.variables_hint') }}</p>
        </div>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-black text-gray-900">{{ t('send_message_page.preview_title') }}</h3>
            <div v-if="form.messages.length > 1" class="flex gap-1">
              <button
                v-for="(msg, i) in form.messages"
                :key="'preview-' + i"
                @click="previewIndex = i"
                :class="['w-7 h-7 rounded-lg text-[10px] font-black transition-all', previewIndex === i ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200']"
              >{{ i + 1 }}</button>
            </div>
          </div>
          <div class="bg-[#e5ddd5] rounded-3xl p-4 min-h-[200px]">
            <div class="bg-white rounded-2xl rounded-tr-sm p-4 shadow-sm max-w-sm" :class="isRTL ? 'mr-auto' : 'ml-auto'">
              <p class="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">{{ previewMessage }}</p>
              <p class="text-[10px] text-gray-400 mt-2">{{ new Date().toLocaleTimeString(isRTL ? 'ar-EG' : 'en-US', {hour: '2-digit', minute: '2-digit'}) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Step 5: Settings & Review -->
      <div v-if="currentStep === 4" class="space-y-6">
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-6">
          <h3 class="text-lg font-black text-gray-900">{{ t('campaign_create.step_settings') }}</h3>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="space-y-2">
              <label class="block text-xs font-bold text-gray-400">{{ t('campaign_create.delay_min') }}</label>
              <input v-model.number="form.delay_min" type="number" min="1" max="300" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold text-gray-900 outline-none focus:border-indigo-300" />
            </div>
            <div class="space-y-2">
              <label class="block text-xs font-bold text-gray-400">{{ t('campaign_create.delay_max') }}</label>
              <input v-model.number="form.delay_max" type="number" min="1" max="300" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold text-gray-900 outline-none focus:border-indigo-300" />
            </div>
          </div>
          <p class="text-[10px] text-gray-400 font-bold">
            {{ isRTL ? 'يتم تنظيم سرعة الإرسال الفعلية لتقليل مخاطر التقييد، دون ضمان بعدم الحظر.' : 'Actual sending velocity is throttled to reduce restriction risk; this does not guarantee that your number will not be banned.' }}
          </p>
        </div>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-4">
          <h3 class="text-lg font-black text-gray-900">{{ t('campaign_create.summary_title') }}</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div class="flex justify-between p-3 rounded-xl bg-gray-50"><span class="text-gray-500 font-bold">{{ t('campaign_create.campaign_name_label') }}</span><span class="font-black text-gray-900">{{ form.name }}</span></div>
            <div class="flex justify-between p-3 rounded-xl bg-gray-50"><span class="text-gray-500 font-bold">{{ t('campaign_create.step_device') }}</span><span class="font-black text-gray-900" dir="ltr">{{ selectedSession?.phone_number || '-' }}</span></div>
            <div class="flex justify-between p-3 rounded-xl bg-gray-50"><span class="text-gray-500 font-bold">{{ t('campaigns_page.th_recipients') }}</span><span class="font-black text-gray-900">{{ Number(validation?.valid_count || 0).toLocaleString() }}</span></div>
            <div class="flex justify-between p-3 rounded-xl bg-gray-50"><span class="text-gray-500 font-bold">{{ t('campaign_create.variations_count') }}</span><span class="font-black text-gray-900">{{ activeVariations.length }}</span></div>
            <div class="flex justify-between p-3 rounded-xl bg-gray-50"><span class="text-gray-500 font-bold">{{ isRTL ? 'الرصيد المتاح' : 'Available Balance' }}</span><span class="font-black text-gray-900">{{ Number(credits).toLocaleString() }}</span></div>
            <div class="flex justify-between p-3 rounded-xl bg-gray-50"><span class="text-gray-500 font-bold">{{ isRTL ? 'الرصيد بعد الحملة' : 'Balance After Campaign' }}</span><span class="font-black" :class="credits >= (validation?.valid_count || 0) ? 'text-gray-900' : 'text-red-600'">{{ Number(Math.max(0, credits - (validation?.valid_count || 0))).toLocaleString() }}</span></div>
          </div>

          <div v-if="credits < (validation?.valid_count || 0)" class="bg-red-50 border border-red-100 rounded-2xl p-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <div>
              <p class="text-sm font-bold text-red-700">{{ isRTL ? 'رصيد الرسائل غير كافٍ لإرسال هذه الحملة.' : 'Insufficient message credits for this campaign.' }}</p>
              <router-link to="/subscription" class="text-xs font-black text-red-700 underline mt-1 inline-block">{{ isRTL ? 'شراء رسائل' : 'Buy credits' }}</router-link>
            </div>
          </div>

          <label class="flex items-start gap-3 cursor-pointer mt-4">
            <input v-model="form.consent" type="checkbox" class="mt-1 w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500" />
            <span class="text-xs font-bold text-gray-600 leading-relaxed">
              {{ isRTL ? 'أؤكد أن لدي الحق في التواصل مع هذه الأرقام وأن هذه الرسائل متوافقة مع سياسات الاستخدام المعمول بها.' : 'I confirm I have explicit permission to contact these recipients and comply with acceptable use policies.' }}
            </span>
          </label>
        </div>
      </div>

      <!-- Navigation -->
      <div class="flex items-center justify-between">
        <button v-if="currentStep > 0" @click="currentStep--" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-2xl text-xs font-black transition-colors">{{ t('common.previous') }}</button>
        <div v-else></div>

        <button
          v-if="currentStep < steps.length - 1"
          @click="nextStep"
          :disabled="!canProceed || validating"
          class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-70 disabled:cursor-not-allowed text-white rounded-2xl text-xs font-black transition-all active:scale-95 flex items-center gap-2"
        >
          <svg v-if="validating && currentStep === 2" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
          <span>{{ validating && currentStep === 2 ? (isRTL ? 'جاري فلترة الأرقام...' : 'Filtering numbers...') : t('common.next') }}</span>
        </button>
        <button
          v-else
          @click="saveOrLaunch"
          :disabled="!canLaunch"
          class="px-8 py-3 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-2xl text-xs font-black transition-all active:scale-95 flex items-center gap-2"
        >
          <svg v-if="saving" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
          <span>{{ saving ? t('common.saving') : t('campaign_create.launch_campaign') }}</span>
        </button>
      </div>

      <!-- Second action: save as draft -->
      <div v-if="currentStep === steps.length - 1" class="text-center">
        <button @click="saveDraft" :disabled="saving" class="text-xs font-bold text-gray-500 hover:text-indigo-600 transition-colors underline">{{ t('campaign_create.save_draft') }}</button>
      </div>
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
  name: 'CampaignCreate',
  components: { DashboardLayout },
  setup() {
    const router = useRouter();
    const currentStep = ref(0);
    const steps = computed(() => [
      { key: 'name', label: t('campaign_create.step_name') },
      { key: 'device', label: t('campaign_create.step_device') },
      { key: 'recipients', label: t('campaign_create.step_recipients') },
      { key: 'message', label: t('campaign_create.step_message') },
      { key: 'review', label: t('campaign_create.step_settings') },
    ]);

    const form = ref({
      name: '',
      messages: [''],
      session_id: null,
      delay_min: 10,
      delay_max: 25,
      consent: false,
    });

    const sessions = ref([]);
    const credits = ref(0);
    const saving = ref(false);
    const validating = ref(false);
    const recipientMode = ref('file');
    const manualNumbers = ref('');
    const uploadedFile = ref(null);
    const validation = ref(null);
    const contacts = ref([]);
    const contactSearch = ref('');
    const selectedPhones = ref({});

    const filteredContacts = computed(() => {
      const q = contactSearch.value.trim().toLowerCase();
      if (!q) return contacts.value;
      return contacts.value.filter(c =>
        (c.name || '').toLowerCase().includes(q) || (c.phone || '').includes(q)
      );
    });

    const selectedContactsCount = computed(() =>
      Object.values(selectedPhones.value).filter(Boolean).length
    );

    const selectedSession = computed(() => sessions.value.find(s => s.id === form.value.session_id));

    const previewIndex = ref(0);

    const activeVariations = computed(() =>
      form.value.messages.map(m => (m || '').trim()).filter(m => m.length > 0)
    );

    const previewMessage = computed(() => {
      const idx = Math.min(previewIndex.value, form.value.messages.length - 1);
      const text = form.value.messages[idx] || activeVariations.value[0] || '';
      if (!text) return isRTL.value ? 'معاينة الرسالة ستظهر هنا...' : 'Message preview will appear here...';
      return text.replace(/\{name\}|\{\{name\}\}/g, isRTL.value ? 'أحمد محمد' : 'John Doe');
    });

    const addVariation = () => {
      if (form.value.messages.length < 5) {
        form.value.messages.push('');
        previewIndex.value = form.value.messages.length - 1;
      }
    };

    const removeVariation = (i) => {
      if (form.value.messages.length <= 1) return;
      form.value.messages.splice(i, 1);
      if (previewIndex.value >= form.value.messages.length) {
        previewIndex.value = form.value.messages.length - 1;
      }
    };

    const canProceed = computed(() => {
      if (currentStep.value === 0) return form.value.name.trim().length > 0;
      if (currentStep.value === 1) return form.value.session_id !== null;
      if (currentStep.value === 2) return validation.value && validation.value.valid_count > 0;
      if (currentStep.value === 3) return activeVariations.value.length > 0;
      return true;
    });

    const canLaunch = computed(() => {
      return (
        canProceed.value &&
        form.value.consent &&
        credits.value >= (validation.value?.valid_count || 0) &&
        !saving.value
      );
    });

    const stepClass = (idx) => {
      if (idx === currentStep.value) return 'bg-indigo-50 text-indigo-700 shadow-xs border border-indigo-200/60';
      if (idx < currentStep.value) return 'bg-emerald-50 text-emerald-700';
      return 'text-gray-400 hover:text-gray-600';
    };

    const stepNumberClass = (idx) => {
      if (idx === currentStep.value) return 'bg-indigo-600 text-white';
      if (idx < currentStep.value) return 'bg-emerald-600 text-white';
      return 'bg-gray-100 text-gray-500';
    };

    const setStep = (idx) => {
      if (idx <= currentStep.value) {
        currentStep.value = idx;
      }
    };

    const loadInitialData = async () => {
      try {
        const [sessRes, subRes, contactsRes] = await Promise.all([
          api.get('/whatsapp/sessions'),
          api.get('/subscription'),
          api.get('/contacts'),
        ]);

        if (sessRes.success) {
          sessions.value = sessRes.data || [];
          const conn = sessions.value.find(s => s.status === 'connected');
          if (conn) form.value.session_id = conn.id;
        }

        if (subRes.success) {
          credits.value = subRes.data?.messages_remaining ?? 0;
        }

        if (contactsRes.success) {
          contacts.value = contactsRes.data || [];
        }
      } catch (err) {
        console.error('Failed to load initial data:', err);
      }
    };

    const selectAllContacts = () => {
      const map = {};
      filteredContacts.value.forEach(c => {
        map[c.phone] = true;
      });
      selectedPhones.value = { ...selectedPhones.value, ...map };
    };

    const deselectAllContacts = () => {
      selectedPhones.value = {};
    };

    const handleFile = async (e) => {
      const file = e.target.files?.[0];
      if (!file) return;
      uploadedFile.value = file;
      await validateFile(file);
    };

    const validateFile = async (file) => {
      try {
        validating.value = true;
        const fd = new FormData();
        fd.append('file', file);
        const res = await api.upload('/whatsapp/campaigns/validate-recipients', fd);
        if (res.success) {
          validation.value = res.data;
        }
      } catch (err) {
        alert(err.data?.message || (isRTL.value ? 'فشل فحص الملف' : 'Failed to validate file'));
      } finally {
        validating.value = false;
      }
    };

    const validateContacts = async () => {
      const phones = Object.entries(selectedPhones.value)
        .filter(([, v]) => v)
        .map(([p]) => p);
      if (!phones.length) return;

      try {
        validating.value = true;
        const list = contacts.value
          .filter(c => phones.includes(c.phone))
          .map(c => ({ phone: c.phone, name: c.name || '' }));

        const res = await api.post('/whatsapp/campaigns/validate-recipients', { recipients: list });
        if (res.success) {
          validation.value = res.data;
        }
      } catch (err) {
        alert(err.data?.message || (isRTL.value ? 'فشل فحص جهات الاتصال' : 'Failed to validate contacts'));
      } finally {
        validating.value = false;
      }
    };

    const validateManual = async () => {
      const raw = manualNumbers.value
        .split('\n')
        .map(n => n.trim())
        .filter(Boolean);
      if (!raw.length) {
        alert(isRTL.value ? 'يرجى إدخال أرقام هواتف' : 'Please enter phone numbers');
        return;
      }

      try {
        validating.value = true;
        const list = raw.map(p => ({ phone: p }));
        const res = await api.post('/whatsapp/campaigns/validate-recipients', { recipients: list });
        if (res.success) {
          validation.value = res.data;
        }
      } catch (err) {
        alert(err.data?.message || (isRTL.value ? 'فشل فحص الأرقام' : 'Failed to validate numbers'));
      } finally {
        validating.value = false;
      }
    };

    const removeInvalid = () => {
      if (!validation.value) return;
      validation.value.invalid = [];
      validation.value.invalid_count = 0;
      validation.value.total = validation.value.valid_count + validation.value.duplicate_count;
    };

    const removeDuplicates = () => {
      if (!validation.value) return;
      validation.value.duplicates = [];
      validation.value.duplicate_count = 0;
      validation.value.total = validation.value.valid_count + validation.value.invalid_count;
    };

    const nextStep = async () => {
      if (currentStep.value === 2 && !validation.value) {
        if (recipientMode.value === 'contacts') await validateContacts();
        else if (recipientMode.value === 'manual') await validateManual();
      }
      if (canProceed.value) {
        currentStep.value++;
      }
    };

    const saveOrLaunch = async () => {
      await submitCampaign('running');
    };

    const saveDraft = async () => {
      await submitCampaign('draft');
    };

    const submitCampaign = async (status) => {
      try {
        saving.value = true;
        const payload = {
          name: form.value.name,
          session_id: form.value.session_id,
          message: activeVariations.value[0],
          messages: form.value.messages,
          delay_min: form.value.delay_min,
          delay_max: form.value.delay_max,
          status,
          recipients: validation.value?.valid || [],
        };

        const res = await api.post('/whatsapp/campaigns', payload);
        if (res.success) {
          router.push(`/campaigns/${res.data.id}`);
        } else {
          alert(res.message || (isRTL.value ? 'فشل إنشاء الحملة' : 'Failed to create campaign'));
        }
      } catch (err) {
        alert(err.data?.message || (isRTL.value ? 'فشل إنشاء الحملة' : 'Failed to create campaign'));
      } finally {
        saving.value = false;
      }
    };

    onMounted(() => {
      const user = auth.getUser();
      if (!user) {
        router.push('/login');
        return;
      }
      loadInitialData();
    });

    return {
      currentStep,
      steps,
      form,
      sessions,
      credits,
      saving,
      validating,
      recipientMode,
      manualNumbers,
      uploadedFile,
      validation,
      contacts,
      contactSearch,
      filteredContacts,
      selectedPhones,
      selectedContactsCount,
      selectedSession,
      previewIndex,
      activeVariations,
      previewMessage,
      addVariation,
      removeVariation,
      canProceed,
      canLaunch,
      stepClass,
      stepNumberClass,
      setStep,
      selectAllContacts,
      deselectAllContacts,
      handleFile,
      validateContacts,
      validateManual,
      removeInvalid,
      removeDuplicates,
      nextStep,
      saveOrLaunch,
      saveDraft,
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
