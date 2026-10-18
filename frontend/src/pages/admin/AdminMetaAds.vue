<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-6" dir="rtl">
      
      <!-- Top Header -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-2 border-b border-slate-200/60">
        <div>
          <div class="flex items-center gap-2.5">
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">إدارة إعلانات Meta</h1>
            <span 
              :class="status.is_configured ? 'bg-emerald-50 text-emerald-700 border-emerald-200/70' : 'bg-amber-50 text-amber-700 border-amber-200/70'"
              class="px-2.5 py-0.5 rounded-full text-xs font-black border flex items-center gap-1.5"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="status.is_configured ? 'bg-emerald-500' : 'bg-amber-500'"></span>
              {{ status.is_configured ? 'متصل بـ Meta' : 'غير متصل' }}
            </span>
          </div>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
            إنشاء وإدارة وتحليل حملات Facebook & Instagram الإعلانية ومتابعة العائد على الإنفاق (ROAS)
          </p>
        </div>

        <!-- Header Actions -->
        <div class="flex flex-wrap items-center gap-2.5">
          <button
            @click="syncMeta"
            :disabled="syncing"
            class="px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 rounded-xl text-xs font-bold transition-all shadow-2xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
          >
            <svg class="w-4 h-4" :class="{ 'animate-spin': syncing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>{{ syncing ? 'جاري المزامنة...' : 'مزامنة مع Meta' }}</span>
          </button>

          <button
            @click="showConnectModal = true"
            class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300/70 rounded-xl text-xs font-bold transition-all shadow-2xs flex items-center gap-1.5 cursor-pointer"
          >
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            </svg>
            <span>ربط / إعدادات الربط</span>
          </button>

          <button
            @click="openCreateWizard"
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <span>إنشاء حملة إعلانية جديدة</span>
          </button>
        </div>
      </div>

      <!-- Alert when not configured -->
      <div v-if="!status.is_configured" class="bg-amber-50 border border-amber-200/80 rounded-2xl p-4 sm:p-5 flex items-start gap-3.5 shadow-2xs">
        <div class="p-2 bg-amber-100 text-amber-700 rounded-xl shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="flex-1">
          <h3 class="text-sm font-bold text-amber-900">حساب Meta Marketing غير مرتبط بعد</h3>
          <p class="text-xs text-amber-700 mt-1">
            لإنشاء وإدارة الحملات الإعلانية من داخل لوحة التحكم، يرجى ربط حساب Meta Business وإدخال الـ Access Token مع صلاحيات الإعلانات.
          </p>
          <button
            @click="showConnectModal = true"
            class="mt-3 px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold transition-colors cursor-pointer"
          >
            ربط الحساب الآن
          </button>
        </div>
      </div>

      <!-- Overview KPI Cards (8 Cards) -->
      <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-4 gap-3.5 sm:gap-4">
        <!-- Total Spend -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-xs font-bold text-slate-500">إجمالي الإنفاق (Spend)</span>
            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs">$</div>
          </div>
          <p class="text-xl sm:text-2xl font-black text-slate-900">${{ formatNumber(overview.total_spend || 0) }}</p>
          <p class="text-[11px] text-slate-400 mt-1">خلال آخر 30 يوماً</p>
        </div>

        <!-- Active Campaigns -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-xs font-bold text-slate-500">الحملات النشطة</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
          </div>
          <p class="text-xl sm:text-2xl font-black text-emerald-600">{{ overview.active_campaigns || 0 }}</p>
          <p class="text-[11px] text-slate-400 mt-1">من إجمالي {{ overview.total_campaigns || 0 }} حملة</p>
        </div>

        <!-- Total Impressions -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-xs font-bold text-slate-500">مرات الظهور (Impressions)</span>
            <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </div>
          </div>
          <p class="text-xl sm:text-2xl font-black text-slate-900">{{ formatNumber(overview.total_impressions || 0) }}</p>
          <p class="text-[11px] text-slate-400 mt-1">الوصول: {{ formatNumber(overview.total_reach || 0) }}</p>
        </div>

        <!-- Total Clicks -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-xs font-bold text-slate-500">النقرات (Clicks)</span>
            <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
              </svg>
            </div>
          </div>
          <p class="text-xl sm:text-2xl font-black text-slate-900">{{ formatNumber(overview.total_clicks || 0) }}</p>
          <p class="text-[11px] text-slate-400 mt-1">نقرات الرابط والتفاعل</p>
        </div>

        <!-- CTR -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-xs font-bold text-slate-500">معدل النقر (CTR)</span>
            <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-xs">%</div>
          </div>
          <p class="text-xl sm:text-2xl font-black text-slate-900">{{ overview.ctr || 0 }}%</p>
          <p class="text-[11px] text-slate-400 mt-1">نسبة النقرات للظهور</p>
        </div>

        <!-- CPC -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-xs font-bold text-slate-500">تكلفة النقرة (CPC)</span>
            <div class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold text-xs">$</div>
          </div>
          <p class="text-xl sm:text-2xl font-black text-slate-900">${{ overview.cpc || 0 }}</p>
          <p class="text-[11px] text-slate-400 mt-1">CPM: ${{ overview.cpm || 0 }}</p>
        </div>

        <!-- Conversions -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-xs font-bold text-slate-500">التحويلات (Conversions)</span>
            <div class="w-8 h-8 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
          <p class="text-xl sm:text-2xl font-black text-slate-900">{{ formatNumber(overview.conversions || 0) }}</p>
          <p class="text-[11px] text-slate-400 mt-1">CPA: ${{ overview.cpa || 0 }}</p>
        </div>

        <!-- ROAS -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-xs font-bold text-slate-500">العائد على الإعلانات (ROAS)</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-xs">x</div>
          </div>
          <p class="text-xl sm:text-2xl font-black text-emerald-600">{{ overview.roas || 0 }}x</p>
          <p class="text-[11px] text-slate-400 mt-1">قيمة المبيعات: ${{ formatNumber(overview.purchase_value || 0) }}</p>
        </div>
      </div>

      <!-- Filters & Search Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-xs">
        <div class="flex flex-col md:flex-row gap-3 sm:gap-4">
          <div class="flex-1 relative">
            <input 
              v-model="filters.search" 
              @input="debounceSearch" 
              type="text" 
              placeholder="ابحث باسم الحملة أو الهدف..." 
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all" 
            />
          </div>

          <!-- Account Filter -->
          <select 
            v-model="filters.ad_account_id" 
            @change="loadCampaigns(1)" 
            class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all min-w-[180px]"
          >
            <option value="">جميع الحسابات الإعلانية</option>
            <option v-for="acc in status.ad_accounts" :key="acc.id" :value="acc.id">
              {{ acc.name }} ({{ acc.currency }})
            </option>
          </select>

          <!-- Status Filter -->
          <select 
            v-model="filters.status" 
            @change="loadCampaigns(1)" 
            class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition-all min-w-[140px]"
          >
            <option value="">جميع الحالات</option>
            <option value="ACTIVE">نشط (Active)</option>
            <option value="PAUSED">متوقف (Paused)</option>
          </select>
        </div>
      </div>

      <!-- Campaigns Table Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-sm font-bold text-slate-900">قائمة الحملات الإعلانية (Campaigns)</h2>
          <span class="text-xs font-medium text-slate-500">إجمالي الحملات: {{ pagination.total || 0 }}</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-right">
            <thead>
              <tr class="text-slate-400 text-xs font-bold border-b border-slate-100 bg-slate-50/50">
                <th class="px-5 py-3.5">اسم الحملة</th>
                <th class="px-5 py-3.5">الهدف (Objective)</th>
                <th class="px-5 py-3.5">الحالة</th>
                <th class="px-5 py-3.5">الميزانية</th>
                <th class="px-5 py-3.5">الإنفاق</th>
                <th class="px-5 py-3.5">الظهور</th>
                <th class="px-5 py-3.5">النقرات</th>
                <th class="px-5 py-3.5">CTR</th>
                <th class="px-5 py-3.5">CPC</th>
                <th class="px-5 py-3.5">التحويلات</th>
                <th class="px-5 py-3.5">ROAS</th>
                <th class="px-5 py-3.5 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-slate-100">
              <tr v-for="camp in campaigns" :key="camp.id" class="hover:bg-slate-50/80 transition-colors">
                <!-- Name & ID -->
                <td class="px-5 py-4">
                  <div>
                    <p class="font-bold text-slate-900 max-w-xs truncate">{{ camp.name }}</p>
                    <p class="text-[10px] text-slate-400 font-mono mt-0.5">ID: {{ camp.meta_campaign_id }}</p>
                  </div>
                </td>

                <!-- Objective -->
                <td class="px-5 py-4">
                  <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                    {{ formatObjective(camp.objective) }}
                  </span>
                </td>

                <!-- Status Badge -->
                <td class="px-5 py-4">
                  <span 
                    :class="camp.status === 'ACTIVE' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'"
                    class="px-2 py-0.5 rounded-md text-[10px] font-bold border inline-flex items-center gap-1"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="camp.status === 'ACTIVE' ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                    {{ camp.status === 'ACTIVE' ? 'نشط' : 'متوقف' }}
                  </span>
                </td>

                <!-- Budget -->
                <td class="px-5 py-4 font-mono font-bold text-slate-800">
                  <span v-if="camp.daily_budget">${{ camp.daily_budget }}/يومي</span>
                  <span v-else-if="camp.lifetime_budget">${{ camp.lifetime_budget }}/كلي</span>
                  <span v-else class="text-slate-400">-</span>
                </td>

                <!-- Spend -->
                <td class="px-5 py-4 font-mono text-slate-900 font-bold">${{ formatNumber(camp.metrics?.spend || 0) }}</td>

                <!-- Impressions -->
                <td class="px-5 py-4 font-mono text-slate-700">{{ formatNumber(camp.metrics?.impressions || 0) }}</td>

                <!-- Clicks -->
                <td class="px-5 py-4 font-mono text-slate-700">{{ formatNumber(camp.metrics?.clicks || 0) }}</td>

                <!-- CTR -->
                <td class="px-5 py-4 font-mono text-slate-700">{{ camp.metrics?.ctr || 0 }}%</td>

                <!-- CPC -->
                <td class="px-5 py-4 font-mono text-slate-700">${{ camp.metrics?.cpc || 0 }}</td>

                <!-- Conversions -->
                <td class="px-5 py-4 font-mono font-bold text-teal-700">{{ camp.metrics?.conversions || 0 }}</td>

                <!-- ROAS -->
                <td class="px-5 py-4 font-mono font-bold" :class="(camp.metrics?.roas || 0) > 1 ? 'text-emerald-600' : 'text-slate-600'">
                  {{ camp.metrics?.roas || 0 }}x
                </td>

                <!-- Actions -->
                <td class="px-5 py-4 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <!-- Toggle Status (Activate / Pause) -->
                    <button
                      v-if="camp.status === 'ACTIVE'"
                      @click="pauseCampaign(camp.id)"
                      title="إيقاف الحملة مؤقتاً"
                      class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200/60 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                    >
                      إيقاف
                    </button>
                    <button
                      v-else
                      @click="activateCampaign(camp.id)"
                      title="تشغيل ونشر الحملة على Meta"
                      class="px-2 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200/60 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                    >
                      تشغيل
                    </button>

                    <!-- Edit Budget -->
                    <button
                      @click="openBudgetModal(camp)"
                      title="تعديل الميزانية"
                      class="px-2 py-1 bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                    >
                      الميزانية
                    </button>

                    <!-- Live Insights -->
                    <button
                      @click="viewInsights(camp)"
                      title="عرض النتائج المباشرة"
                      class="px-2 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200/60 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                    >
                      النتائج
                    </button>

                    <!-- Delete -->
                    <button
                      @click="deleteCampaign(camp.id)"
                      title="حذف الحملة"
                      class="px-2 py-1 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200/60 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                    >
                      حذف
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="campaigns.length === 0 && !loading" class="text-center py-12 text-slate-400 text-xs">
          لا توجد حملات إعلانية مسجلة حتى الآن. اضغط على "إنشاء حملة إعلانية جديدة" للبدء.
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="px-6 py-4 border-t border-slate-100 flex items-center justify-between text-xs">
          <button @click="loadCampaigns(pagination.current_page - 1)" :disabled="pagination.current_page === 1" class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-lg disabled:opacity-50 border border-slate-200 font-bold transition-colors cursor-pointer">السابق</button>
          <span class="text-slate-500 font-medium">صفحة {{ pagination.current_page }} من {{ pagination.last_page }}</span>
          <button @click="loadCampaigns(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-lg disabled:opacity-50 border border-slate-200 font-bold transition-colors cursor-pointer">التالي</button>
        </div>
      </div>

      <!-- ========================================================================= -->
      <!-- MODAL: Multi-Step Campaign Creation Wizard                                -->
      <!-- ========================================================================= -->
      <div v-if="showWizardModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-3xl shadow-2xl max-w-3xl w-full p-6 space-y-6 max-h-[90vh] overflow-y-auto" dir="rtl">
          
          <!-- Wizard Header -->
          <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div>
              <h3 class="text-lg font-black text-slate-900">إنشاء حملة إعلانية جديدة (Meta Ads Wizard)</h3>
              <p class="text-xs text-slate-500 mt-0.5">خطوات سهلة لإنشاء حملتك الإعلانية واستهداف جمهورك</p>
            </div>
            <button @click="showWizardModal = false" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <!-- Wizard Step Indicator -->
          <div class="grid grid-cols-4 gap-2 text-center text-xs font-bold">
            <div :class="wizardStep >= 1 ? 'text-emerald-600 border-b-2 border-emerald-500 pb-1.5' : 'text-slate-400 border-b-2 border-slate-200 pb-1.5'">
              1. إعداد الحملة
            </div>
            <div :class="wizardStep >= 2 ? 'text-emerald-600 border-b-2 border-emerald-500 pb-1.5' : 'text-slate-400 border-b-2 border-slate-200 pb-1.5'">
              2. الجمهور والجمارك (Ad Set)
            </div>
            <div :class="wizardStep >= 3 ? 'text-emerald-600 border-b-2 border-emerald-500 pb-1.5' : 'text-slate-400 border-b-2 border-slate-200 pb-1.5'">
              3. الإعلان والتصميم (Creative)
            </div>
            <div :class="wizardStep >= 4 ? 'text-emerald-600 border-b-2 border-emerald-500 pb-1.5' : 'text-slate-400 border-b-2 border-slate-200 pb-1.5'">
              4. المراجعة والنشر (Publish)
            </div>
          </div>

          <!-- STEP 1: Campaign Setup -->
          <div v-if="wizardStep === 1" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Ad Account -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">الحساب الإعلاني (Ad Account) *</label>
                <select v-model="form.ad_account_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-100 focus:border-emerald-500">
                  <option value="" disabled>اختر الحساب الإعلاني</option>
                  <option v-for="acc in status.ad_accounts" :key="acc.id" :value="acc.id">
                    {{ acc.name }} ({{ acc.currency }})
                  </option>
                </select>
              </div>

              <!-- Facebook Page -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">صفحة Facebook *</label>
                <select v-model="form.page_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-100 focus:border-emerald-500">
                  <option value="" disabled>اختر الصفحة</option>
                  <option v-for="p in status.pages" :key="p.meta_page_id" :value="p.meta_page_id">
                    {{ p.name }}
                  </option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Campaign Name -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">اسم الحملة الإعلانية *</label>
                <input v-model="form.campaign_name" type="text" placeholder="مثال: حملة عروض الصيف 2026" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-100 focus:border-emerald-500" />
              </div>

              <!-- Objective -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">هدف الحملة (Objective) *</label>
                <select v-model="form.objective" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-100 focus:border-emerald-500">
                  <option value="OUTCOME_TRAFFIC">زيارات للموقع (Traffic)</option>
                  <option value="OUTCOME_SALES">المبيعات والتحويلات (Sales / Conversions)</option>
                  <option value="OUTCOME_LEADS">تجميع بيانات العملاء (Leads)</option>
                  <option value="OUTCOME_ENGAGEMENT">التفاعل والرسائل (Engagement)</option>
                  <option value="OUTCOME_AWARENESS">الوعي بالعلامة التجارية (Awareness)</option>
                </select>
              </div>
            </div>

            <!-- Budget & Schedule -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">نوع الميزانية *</label>
                <select v-model="form.budget_type" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-100 focus:border-emerald-500">
                  <option value="daily">ميزانية يومية (Daily Budget)</option>
                  <option value="lifetime">ميزانية كلية (Lifetime Budget)</option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">المبلغ ($) *</label>
                <input v-model="form.budget" type="number" min="1" step="0.5" placeholder="مثال: 20" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-100 focus:border-emerald-500" />
              </div>

              <!-- Pixel / Dataset -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pixel / Dataset (اختياري)</label>
                <select v-model="form.pixel_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-100 focus:border-emerald-500">
                  <option value="">بدون Pixel</option>
                  <option v-for="px in status.pixels" :key="px.meta_pixel_id" :value="px.meta_pixel_id">
                    {{ px.name }} ({{ px.meta_pixel_id }})
                  </option>
                </select>
              </div>
            </div>
          </div>

          <!-- STEP 2: Ad Set Targeting -->
          <div v-if="wizardStep === 2" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Country -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">الدولة المستهدفة *</label>
                <select v-model="form.country" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-100 focus:border-emerald-500">
                  <option value="EG">مصر (Egypt)</option>
                  <option value="SA">المملكة العربية السعودية (Saudi Arabia)</option>
                  <option value="AE">الإمارات العربية المتحدة (UAE)</option>
                  <option value="KW">الكويت (Kuwait)</option>
                  <option value="QA">قطر (Qatar)</option>
                  <option value="US">الولايات المتحدة (USA)</option>
                </select>
              </div>

              <!-- Gender -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">الجنس *</label>
                <select v-model="form.gender" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-100 focus:border-emerald-500">
                  <option value="ALL">الكل (رجال ونساء)</option>
                  <option value="MALE">رجال فقط</option>
                  <option value="FEMALE">نساء فقط</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">العمر الأدنى *</label>
                <input v-model="form.age_min" type="number" min="18" max="65" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-100 focus:border-emerald-500" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">العمر الأقصى *</label>
                <input v-model="form.age_max" type="number" min="18" max="65" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-100 focus:border-emerald-500" />
              </div>
            </div>

            <!-- Placements -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">المنصات والمواضع (Placements)</label>
              <div class="flex flex-wrap gap-4 text-xs">
                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                  <input type="checkbox" value="facebook" v-model="form.placements" class="rounded text-emerald-600 focus:ring-emerald-500" />
                  <span>Facebook</span>
                </label>
                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                  <input type="checkbox" value="instagram" v-model="form.placements" class="rounded text-emerald-600 focus:ring-emerald-500" />
                  <span>Instagram</span>
                </label>
                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                  <input type="checkbox" value="audience_network" v-model="form.placements" class="rounded text-emerald-600 focus:ring-emerald-500" />
                  <span>Audience Network</span>
                </label>
                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                  <input type="checkbox" value="messenger" v-model="form.placements" class="rounded text-emerald-600 focus:ring-emerald-500" />
                  <span>Messenger</span>
                </label>
              </div>
            </div>
          </div>

          <!-- STEP 3: Creative & Media -->
          <div v-if="wizardStep === 3" class="space-y-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">عنوان الإعلان الرئيسي (Headline / Title) *</label>
              <input v-model="form.title" type="text" placeholder="مثال: خصم 50% على جميع باقات واتساب" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-100 focus:border-emerald-500" />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">نص الإعلان الأساسي (Primary Text / Body) *</label>
              <textarea v-model="form.body" rows="3" placeholder="اكتب تفاصيل العرض والرسالة الترويجية..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-100 focus:border-emerald-500"></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">رابط الوجهة (Destination URL) *</label>
                <input v-model="form.destination_url" type="url" placeholder="https://example.com/offer" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-100 focus:border-emerald-500" dir="ltr" />
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">زر الحث على اتخاذ إجراء (CTA) *</label>
                <select v-model="form.call_to_action_type" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-100 focus:border-emerald-500">
                  <option value="LEARN_MORE">معرفة المزيد (Learn More)</option>
                  <option value="SHOP_NOW">تسوق الآن (Shop Now)</option>
                  <option value="SIGN_UP">اشتراك (Sign Up)</option>
                  <option value="CONTACT_US">اتصل بنا (Contact Us)</option>
                  <option value="SEND_MESSAGE">إرسال رسالة (Send Message)</option>
                  <option value="GET_OFFER">احصل على العرض (Get Offer)</option>
                </select>
              </div>
            </div>

            <!-- Media Upload -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">صورة أو فيديو الإعلان</label>
              <div class="border-2 border-dashed border-slate-200 rounded-2xl p-4 text-center hover:border-emerald-500 transition-colors bg-slate-50/50">
                <input type="file" ref="fileInput" @change="handleFileUpload" accept="image/*,video/*" class="hidden" />
                <div v-if="!form.image_url && !form.image_hash && !uploadingMedia" class="space-y-2">
                  <button type="button" @click="$refs.fileInput.click()" class="px-3.5 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-2xs">
                    رفع صورة / فيديو
                  </button>
                  <p class="text-[11px] text-slate-400">PNG, JPG, MP4 حتى 50 ميجابايت</p>
                </div>
                <div v-else-if="uploadingMedia" class="text-xs text-emerald-600 font-bold py-3 flex items-center justify-center gap-2">
                  <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                  <span>جاري رفع الوسائط إلى Meta...</span>
                </div>
                <div v-else class="flex items-center justify-center gap-4 py-2">
                  <img v-if="form.image_url" :src="form.image_url" alt="Preview" class="h-16 w-16 object-cover rounded-xl border border-slate-200 shadow-xs" />
                  <div class="text-right text-xs">
                    <p class="font-bold text-emerald-700">تم تجهيز الوسائط بنجاح ✓</p>
                    <button type="button" @click="form.image_url = ''; form.image_hash = ''" class="text-[11px] text-red-600 underline mt-1">تغيير الصورة</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- STEP 4: Review & Publish (Safety Confirmation) -->
          <div v-if="wizardStep === 4" class="space-y-4">
            <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-5 space-y-4">
              <h4 class="text-xs font-black text-slate-900 border-b border-slate-200 pb-2">ملخص بيانات الحملة قبل النشر</h4>

              <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                <div>
                  <span class="text-slate-400 block text-[11px]">اسم الحملة:</span>
                  <span class="font-bold text-slate-800">{{ form.campaign_name }}</span>
                </div>
                <div>
                  <span class="text-slate-400 block text-[11px]">الهدف:</span>
                  <span class="font-bold text-slate-800">{{ formatObjective(form.objective) }}</span>
                </div>
                <div>
                  <span class="text-slate-400 block text-[11px]">الميزانية:</span>
                  <span class="font-bold text-slate-800">${{ form.budget }} ({{ form.budget_type === 'daily' ? 'يومي' : 'كلي' }})</span>
                </div>
                <div>
                  <span class="text-slate-400 block text-[11px]">الاستهداف الجغرافي:</span>
                  <span class="font-bold text-slate-800">{{ form.country }} (العمر: {{ form.age_min }} - {{ form.age_max }})</span>
                </div>
                <div>
                  <span class="text-slate-400 block text-[11px]">المنصات:</span>
                  <span class="font-bold text-slate-800">{{ form.placements.join(', ') || 'الكل' }}</span>
                </div>
                <div>
                  <span class="text-slate-400 block text-[11px]">زر الإجراء:</span>
                  <span class="font-bold text-slate-800">{{ form.call_to_action_type }}</span>
                </div>
              </div>

              <div class="border-t border-slate-200 pt-3 text-xs space-y-1">
                <span class="text-slate-400 block text-[11px]">عنوان الإعلان:</span>
                <p class="font-bold text-slate-900">{{ form.title }}</p>
                <span class="text-slate-400 block text-[11px] mt-2">رابط الوجهة:</span>
                <p class="font-mono text-emerald-700 text-[11px]" dir="ltr">{{ form.destination_url }}</p>
              </div>
            </div>

            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs text-emerald-800 flex items-start gap-2.5">
              <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              <div>
                <p class="font-bold">أمان النشر (Publishing Safety):</p>
                <p class="text-[11px] text-emerald-700 mt-0.5">عند الضغط على "تأكيد وإنشاء الحملة"، سيتم إنشاء الحملة والإعلان على Meta بحالة متوقفة (PAUSED) لحين مراجعتها أو نشرها فوراً حسب رغبتك.</p>
              </div>
            </div>
          </div>

          <!-- Wizard Footer Buttons -->
          <div class="flex items-center justify-between pt-4 border-t border-slate-100">
            <button
              v-if="wizardStep > 1"
              @click="wizardStep--"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors cursor-pointer"
            >
              السابق
            </button>
            <div v-else></div>

            <div class="flex items-center gap-2">
              <button
                v-if="wizardStep < 4"
                @click="validateAndNext"
                class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-colors cursor-pointer"
              >
                التالي
              </button>

              <button
                v-else
                @click="submitCampaign"
                :disabled="submittingWizard"
                class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs flex items-center gap-2 cursor-pointer disabled:opacity-50"
              >
                <svg v-if="submittingWizard" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                <span>{{ submittingWizard ? 'جاري الإنشاء على Meta...' : 'تأكيد وإنشاء الحملة' }}</span>
              </button>
            </div>
          </div>

        </div>
      </div>

      <!-- ========================================================================= -->
      <!-- MODAL: Live Insights                                                      -->
      <!-- ========================================================================= -->
      <div v-if="showInsightsModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-3xl shadow-2xl max-w-2xl w-full p-6 space-y-5" dir="rtl">
          <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div>
              <h3 class="text-base font-black text-slate-900">النتائج والتحليلات المباشرة من Meta</h3>
              <p class="text-xs text-slate-500 mt-0.5">{{ selectedCampaign?.name }}</p>
            </div>
            <button @click="showInsightsModal = false" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div v-if="loadingInsights" class="py-12 text-center text-xs text-slate-400 flex flex-col items-center gap-2">
            <svg class="w-6 h-6 text-emerald-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
            <span>جاري جلب بيانات النتائج من خوادم Meta...</span>
          </div>

          <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3.5 text-xs">
            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3">
              <span class="text-slate-400 block text-[11px]">الإنفاق (Spend)</span>
              <p class="text-base font-black text-slate-900 mt-0.5">${{ formatNumber(campaignInsights.spend || 0) }}</p>
            </div>
            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3">
              <span class="text-slate-400 block text-[11px]">مرات الظهور (Impressions)</span>
              <p class="text-base font-black text-slate-900 mt-0.5">{{ formatNumber(campaignInsights.impressions || 0) }}</p>
            </div>
            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3">
              <span class="text-slate-400 block text-[11px]">الوصول (Reach)</span>
              <p class="text-base font-black text-slate-900 mt-0.5">{{ formatNumber(campaignInsights.reach || 0) }}</p>
            </div>
            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3">
              <span class="text-slate-400 block text-[11px]">النقرات (Clicks)</span>
              <p class="text-base font-black text-slate-900 mt-0.5">{{ formatNumber(campaignInsights.clicks || 0) }}</p>
            </div>
            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3">
              <span class="text-slate-400 block text-[11px]">معدل النقر (CTR)</span>
              <p class="text-base font-black text-slate-900 mt-0.5">{{ campaignInsights.ctr || 0 }}%</p>
            </div>
            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3">
              <span class="text-slate-400 block text-[11px]">تكلفة النقرة (CPC)</span>
              <p class="text-base font-black text-slate-900 mt-0.5">${{ campaignInsights.cpc || 0 }}</p>
            </div>
            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3">
              <span class="text-slate-400 block text-[11px]">التحويلات (Conversions)</span>
              <p class="text-base font-black text-teal-700 mt-0.5">{{ campaignInsights.conversions || 0 }}</p>
            </div>
            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3">
              <span class="text-slate-400 block text-[11px]">تكلفة التحويل (CPA)</span>
              <p class="text-base font-black text-slate-900 mt-0.5">${{ campaignInsights.cpa || 0 }}</p>
            </div>
            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3">
              <span class="text-slate-400 block text-[11px]">العائد على الإعلانات (ROAS)</span>
              <p class="text-base font-black text-emerald-600 mt-0.5">{{ campaignInsights.roas || 0 }}x</p>
            </div>
          </div>
        </div>
      </div>

      <!-- ========================================================================= -->
      <!-- MODAL: Edit Budget                                                        -->
      <!-- ========================================================================= -->
      <div v-if="showBudgetModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-4" dir="rtl">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-black text-slate-900">تعديل ميزانية الحملة</h3>
            <button @click="showBudgetModal = false" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="space-y-3">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">نوع الميزانية</label>
              <select v-model="budgetForm.budget_type" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
                <option value="daily">ميزانية يومية (Daily Budget)</option>
                <option value="lifetime">ميزانية كلية (Lifetime Budget)</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">المبلغ ($)</label>
              <input v-model="budgetForm.budget" type="number" min="1" step="0.5" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-bold" />
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button @click="showBudgetModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold">إلغاء</button>
            <button @click="saveBudget" :disabled="savingBudget" class="px-5 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold disabled:opacity-50">
              {{ savingBudget ? 'جاري الحفظ...' : 'حفظ التغييرات' }}
            </button>
          </div>
        </div>
      </div>

      <!-- ========================================================================= -->
      <!-- MODAL: Connect Meta Account                                               -->
      <!-- ========================================================================= -->
      <div v-if="showConnectModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-3xl shadow-2xl max-w-lg w-full p-6 space-y-4" dir="rtl">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
              <h3 class="text-base font-black text-slate-900">ربط حساب Meta Marketing</h3>
              <p class="text-xs text-slate-500 mt-0.5">أدخل رمز الوصول (Access Token) الخاص بحساب Meta للأعمال</p>
            </div>
            <button @click="showConnectModal = false" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="space-y-3">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">اسم الحساب / النشاط</label>
              <input v-model="connectForm.name" type="text" placeholder="مثال: حساب أعمال Wavixa" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs" />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Meta App ID (اختياري)</label>
              <input v-model="connectForm.app_id" type="text" placeholder="1234567890..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono" />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">رمز الوصول (System User / User Access Token) *</label>
              <textarea v-model="connectForm.access_token" rows="3" placeholder="EAAB..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono" dir="ltr"></textarea>
              <p class="text-[10px] text-slate-400 mt-1">يتم تشفير الرمز وتخزينه بأمان كامل في قاعدة البيانات ولا يتم عرضه إطلاقاً.</p>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button @click="showConnectModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold">إلغاء</button>
            <button @click="saveConnection" :disabled="savingConnection" class="px-5 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold disabled:opacity-50">
              {{ savingConnection ? 'جاري التحقق والربط...' : 'ربط الحساب ومزامنة الأصول' }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script>
import { ref, reactive, onMounted } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import adminService from '@/services/admin';

export default {
  name: 'AdminMetaAds',
  components: { AdminLayout },
  setup() {
    const loading = ref(false);
    const syncing = ref(false);
    const showWizardModal = ref(false);
    const showInsightsModal = ref(false);
    const showBudgetModal = ref(false);
    const showConnectModal = ref(false);

    const wizardStep = ref(1);
    const submittingWizard = ref(false);
    const uploadingMedia = ref(false);
    const loadingInsights = ref(false);
    const savingBudget = ref(false);
    const savingConnection = ref(false);

    const selectedCampaign = ref(null);
    const campaignInsights = ref({});

    const status = reactive({
      is_configured: false,
      connection: null,
      ad_accounts: [],
      pages: [],
      pixels: [],
    });

    const overview = reactive({
      total_spend: 0,
      active_campaigns: 0,
      paused_campaigns: 0,
      total_campaigns: 0,
      total_impressions: 0,
      total_reach: 0,
      total_clicks: 0,
      ctr: 0,
      cpc: 0,
      cpm: 0,
      conversions: 0,
      cpa: 0,
      roas: 0,
      purchase_value: 0,
    });

    const campaigns = ref([]);
    const pagination = reactive({
      current_page: 1,
      last_page: 1,
      total: 0,
    });

    const filters = reactive({
      search: '',
      ad_account_id: '',
      status: '',
    });

    const form = reactive({
      ad_account_id: '',
      page_id: '',
      pixel_id: '',
      conversion_event: 'PURCHASE',
      campaign_name: '',
      objective: 'OUTCOME_TRAFFIC',
      budget_type: 'daily',
      budget: 20,
      country: 'EG',
      gender: 'ALL',
      age_min: 18,
      age_max: 65,
      placements: ['facebook', 'instagram'],
      title: '',
      body: '',
      destination_url: '',
      call_to_action_type: 'LEARN_MORE',
      image_url: '',
      image_hash: '',
    });

    const budgetForm = reactive({
      id: null,
      budget_type: 'daily',
      budget: 20,
    });

    const connectForm = reactive({
      name: 'حساب Meta الرئيسي',
      app_id: '',
      access_token: '',
    });

    let searchTimeout = null;

    const formatNumber = (num) => {
      return Number(num || 0).toLocaleString();
    };

    const formatObjective = (obj) => {
      const map = {
        'OUTCOME_TRAFFIC': 'زيارات (Traffic)',
        'OUTCOME_SALES': 'مبيعات (Sales)',
        'OUTCOME_LEADS': 'عملاء محتملين (Leads)',
        'OUTCOME_ENGAGEMENT': 'تفاعل (Engagement)',
        'OUTCOME_AWARENESS': 'وعي (Awareness)',
        'OUTCOME_APP_PROMOTION': 'تطبيقات (Apps)',
      };
      return map[obj] || obj;
    };

    const loadStatus = async () => {
      try {
        const res = await adminService.getMetaAdsStatus();
        if (res?.success) {
          status.is_configured = res.is_configured;
          status.connection = res.connection;
          status.ad_accounts = res.ad_accounts || [];
          status.pages = res.pages || [];
          status.pixels = res.pixels || [];

          if (status.ad_accounts.length && !form.ad_account_id) {
            form.ad_account_id = status.ad_accounts[0].id;
          }
          if (status.pages.length && !form.page_id) {
            form.page_id = status.pages[0].meta_page_id;
          }
        }
      } catch (err) {
        console.error('Error loading Meta status:', err);
      }
    };

    const loadOverview = async () => {
      try {
        const res = await adminService.getMetaAdsOverview({
          ad_account_id: filters.ad_account_id,
        });
        if (res?.success && res.overview) {
          Object.assign(overview, res.overview);
        }
      } catch (err) {
        console.error('Error loading overview:', err);
      }
    };

    const loadCampaigns = async (page = 1) => {
      loading.value = true;
      try {
        const res = await adminService.getMetaAdsCampaigns({
          page,
          search: filters.search,
          ad_account_id: filters.ad_account_id,
          status: filters.status,
        });
        if (res?.success && res.campaigns) {
          campaigns.value = res.campaigns.data || [];
          pagination.current_page = res.campaigns.current_page || 1;
          pagination.last_page = res.campaigns.last_page || 1;
          pagination.total = res.campaigns.total || 0;
        }
      } catch (err) {
        console.error('Error loading campaigns:', err);
      } finally {
        loading.value = false;
      }
    };

    const debounceSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        loadCampaigns(1);
      }, 300);
    };

    const syncMeta = async () => {
      syncing.value = true;
      try {
        await adminService.syncMetaAds();
        await loadStatus();
        await loadOverview();
        await loadCampaigns(1);
        alert('تمت مزامنة بيانات Meta بنجاح.');
      } catch (err) {
        alert(err.response?.data?.message || 'فشلت عملية المزامنة مع Meta.');
      } finally {
        syncing.value = false;
      }
    };

    const openCreateWizard = () => {
      wizardStep.value = 1;
      showWizardModal.value = true;
    };

    const validateAndNext = () => {
      if (wizardStep.value === 1) {
        if (!form.ad_account_id) return alert('يرجى اختيار الحساب الإعلاني.');
        if (!form.page_id) return alert('يرجى اختيار صفحة فيسبوك.');
        if (!form.campaign_name) return alert('يرجى إدخال اسم الحملة.');
        if (!form.budget || form.budget <= 0) return alert('يرجى تحديد ميزانية صالحة.');
      } else if (wizardStep.value === 3) {
        if (!form.title) return alert('يرجى إدخال عنوان الإعلان.');
        if (!form.body) return alert('يرجى إدخال نص الإعلان الأساسي.');
        if (!form.destination_url) return alert('يرجى إدخال رابط الوجهة.');
      }
      wizardStep.value++;
    };

    const handleFileUpload = async (event) => {
      const file = event.target.files[0];
      if (!file) return;
      if (!form.ad_account_id) return alert('يرجى اختيار الحساب الإعلاني أولاً.');

      uploadingMedia.value = true;
      const formData = new FormData();
      formData.append('ad_account_id', form.ad_account_id);
      formData.append('file', file);

      try {
        const res = await adminService.uploadMetaAdsMedia(formData);
        if (res?.success) {
          if (res.type === 'image') {
            form.image_hash = res.hash || '';
            form.image_url = res.url || URL.createObjectURL(file);
          }
        }
      } catch (err) {
        alert(err.response?.data?.message || 'فشل رفع الوسائط.');
      } finally {
        uploadingMedia.value = false;
      }
    };

    const submitCampaign = async () => {
      submittingWizard.value = true;
      try {
        const res = await adminService.createMetaAdsCampaign(form);
        if (res?.success) {
          showWizardModal.value = false;
          alert(res.message || 'تم إنشاء الحملة بنجاح!');
          await loadOverview();
          await loadCampaigns(1);
        }
      } catch (err) {
        alert(err.response?.data?.message || 'فشل إنشاء الحملة على Meta.');
      } finally {
        submittingWizard.value = false;
      }
    };

    const pauseCampaign = async (id) => {
      if (!confirm('هل أنت متأكد من إيقاف هذه الحملة مؤقتاً؟')) return;
      try {
        await adminService.pauseMetaAdsCampaign(id);
        await loadCampaigns(pagination.current_page);
        await loadOverview();
      } catch (err) {
        alert(err.response?.data?.message || 'فشل إيقاف الحملة.');
      }
    };

    const activateCampaign = async (id) => {
      if (!confirm('هل تريد تشغيل ونشر هذه الحملة على Meta الآن؟')) return;
      try {
        await adminService.activateMetaAdsCampaign(id);
        await loadCampaigns(pagination.current_page);
        await loadOverview();
        alert('تم تفعيل الحملة بنجاح على Meta!');
      } catch (err) {
        alert(err.response?.data?.message || 'فشل تفعيل الحملة.');
      }
    };

    const openBudgetModal = (camp) => {
      budgetForm.id = camp.id;
      budgetForm.budget_type = camp.daily_budget ? 'daily' : 'lifetime';
      budgetForm.budget = camp.daily_budget || camp.lifetime_budget || 20;
      showBudgetModal.value = true;
    };

    const saveBudget = async () => {
      savingBudget.value = true;
      try {
        await adminService.updateMetaAdsCampaignBudget(budgetForm.id, {
          budget_type: budgetForm.budget_type,
          budget: budgetForm.budget,
        });
        showBudgetModal.value = false;
        await loadCampaigns(pagination.current_page);
      } catch (err) {
        alert(err.response?.data?.message || 'فشل تحديث الميزانية.');
      } finally {
        savingBudget.value = false;
      }
    };

    const deleteCampaign = async (id) => {
      if (!confirm('هل أنت متأكد من حذف هذه الحملة نهائياً من Meta والنظام؟')) return;
      try {
        await adminService.deleteMetaAdsCampaign(id);
        await loadCampaigns(pagination.current_page);
        await loadOverview();
      } catch (err) {
        alert(err.response?.data?.message || 'فشل حذف الحملة.');
      }
    };

    const viewInsights = async (camp) => {
      selectedCampaign.value = camp;
      showInsightsModal.value = true;
      loadingInsights.value = true;
      try {
        const res = await adminService.getMetaAdsInsights(camp.id);
        if (res?.success) {
          campaignInsights.value = res.metrics || {};
        }
      } catch (err) {
        console.error('Error fetching insights:', err);
      } finally {
        loadingInsights.value = false;
      }
    };

    const saveConnection = async () => {
      if (!connectForm.access_token) return alert('يرجى إدخال رمز الوصول.');
      savingConnection.value = true;
      try {
        const res = await adminService.connectMetaAds(connectForm);
        if (res?.success) {
          showConnectModal.value = false;
          alert('تم ربط حساب Meta بنجاح.');
          await loadStatus();
          await loadOverview();
          await loadCampaigns(1);
        }
      } catch (err) {
        alert(err.response?.data?.message || 'فشل الربط. يرجى التأكد من صحة رمز الوصول.');
      } finally {
        savingConnection.value = false;
      }
    };

    onMounted(async () => {
      await loadStatus();
      await loadOverview();
      await loadCampaigns(1);
    });

    return {
      loading,
      syncing,
      showWizardModal,
      showInsightsModal,
      showBudgetModal,
      showConnectModal,
      wizardStep,
      submittingWizard,
      uploadingMedia,
      loadingInsights,
      savingBudget,
      savingConnection,
      selectedCampaign,
      campaignInsights,
      status,
      overview,
      campaigns,
      pagination,
      filters,
      form,
      budgetForm,
      connectForm,
      formatNumber,
      formatObjective,
      loadCampaigns,
      debounceSearch,
      syncMeta,
      openCreateWizard,
      validateAndNext,
      handleFileUpload,
      submitCampaign,
      pauseCampaign,
      activateCampaign,
      openBudgetModal,
      saveBudget,
      deleteCampaign,
      viewInsights,
      saveConnection,
    };
  },
};
</script>
