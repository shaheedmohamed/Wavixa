<template>
  <DashboardLayout>
    <div class="space-y-10 -mx-4 sm:-mx-6 lg:-mx-8 -my-4 sm:-my-6 lg:-my-8 p-4 sm:p-6 lg:p-8 bg-[#f8fafc] text-slate-900 min-h-[calc(100vh-4rem)]" :dir="isRTL ? 'rtl' : 'ltr'">
      <!-- Header -->
      <section class="text-center max-w-3xl mx-auto pt-4">
        <div class="inline-flex items-center gap-2 bg-emerald-600/10 border border-emerald-500/30 px-3.5 py-1 rounded-full mb-5">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
          <span class="text-emerald-600 text-xs font-black tracking-wider">{{ t('subscription_page.badge') }}</span>
        </div>
        <h1 class="text-3xl sm:text-4xl md:text-5xl font-black leading-tight mb-4">
          {{ t('subscription_page.title') }} <span class="text-emerald-600">Wavixa</span>
        </h1>
        <p class="text-slate-500 text-base md:text-lg max-w-xl mx-auto font-medium leading-relaxed">
          {{ t('subscription_page.subtitle') }}
        </p>
      </section>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <div class="w-12 h-12 border-4 border-slate-100 border-t-[#00C853] rounded-full animate-spin"></div>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="max-w-xl mx-auto bg-red-500/10 border border-red-500/20 rounded-2xl p-6 text-center">
        <p class="text-red-400 font-bold text-sm">{{ error }}</p>
        <button @click="loadSubscription" class="mt-3 text-xs text-red-300 font-bold underline">{{ t('subscription_page.retry') }}</button>
      </div>

      <template v-else>
        <!-- Current Plan / Balance -->
        <section class="max-w-6xl mx-auto">
          <div class="bg-white border border-slate-100 rounded-3xl p-6 md:p-8 shadow">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 pb-6 border-b border-slate-100">
              <div>
                <p class="text-slate-500 text-xs font-black uppercase tracking-wider mb-1">{{ t('subscription_page.current_plan_card') }}</p>
                <div class="flex items-center gap-3">
                  <h2 class="text-2xl md:text-3xl font-black">{{ subscription?.plan?.name || t('subscription_page.free_tier') }}</h2>
                  <span :class="['px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider border', statusBadgeClass]">
                    {{ subscription?.status || 'active' }}
                  </span>
                </div>
              </div>
              <div v-if="subscription?.ends_at" class="text-slate-500 text-sm font-medium">
                {{ t('subscription_page.ends_at').replace('{date}', formatDate(subscription.ends_at)) }}
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
              <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100" :class="isRTL ? 'text-right' : 'text-left'">
                <p class="text-slate-500 text-xs font-black uppercase tracking-wider mb-1.5">{{ t('subscription_page.total_balance') }}</p>
                <p class="text-2xl font-black text-slate-900">{{ formatMessages(subscription?.messages_limit) }}</p>
              </div>
              <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100" :class="isRTL ? 'text-right' : 'text-left'">
                <p class="text-slate-500 text-xs font-black uppercase tracking-wider mb-1.5">{{ t('subscription_page.used_balance') }}</p>
                <p class="text-2xl font-black text-emerald-600">{{ formatMessages(subscription?.messages_used) }}</p>
              </div>
              <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100" :class="isRTL ? 'text-right' : 'text-left'">
                <p class="text-slate-500 text-xs font-black uppercase tracking-wider mb-1.5">{{ t('subscription_page.remaining_balance') }}</p>
                <p class="text-2xl font-black text-emerald-600">{{ formatMessages(subscription?.messages_remaining) }}</p>
              </div>
            </div>

            <div class="space-y-2">
              <div class="flex justify-between items-center text-xs font-black">
                <span class="text-slate-500 uppercase">{{ t('subscription_page.usage_rate') }}</span>
                <span class="text-slate-900">{{ subscription?.usage_percentage || 0 }}%</span>
              </div>
              <div class="w-full bg-slate-800 rounded-full h-2.5 overflow-hidden">
                <div
                  class="h-full bg-emerald-600 transition-all duration-1000 ease-out"
                  :style="{ width: Math.min(100, subscription?.usage_percentage || 0) + '%' }"
                  :class="{ 'bg-red-500': (subscription?.usage_percentage || 0) > 90 }"
                ></div>
              </div>
            </div>
          </div>
        </section>

        <!-- Plan Type Note + Toggle -->
        <section data-tour="subscription-plans" class="max-w-7xl mx-auto" id="plans">
          <div class="text-center mb-8">
            <div class="flex flex-wrap items-center justify-center gap-3 mb-6">
              <span class="text-sm font-bold text-slate-500">{{ t('subscription_page.message_packages') }}</span>
              <span class="w-1 h-1 rounded-full bg-slate-600"></span>
              <span class="text-sm font-bold text-slate-500">{{ t('subscription_page.business_subscriptions') }}</span>
            </div>

            <div class="inline-flex items-center bg-slate-50 border border-slate-200 rounded-2xl p-1.5 gap-1">
              <button
                @click="isYearly = false"
                :class="['px-6 py-2.5 rounded-xl text-sm font-black transition-all', !isYearly ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-500/20' : 'text-slate-500 hover:text-slate-900']"
              >
                {{ t('subscription_page.monthly') }}
              </button>
              <button
                @click="isYearly = true"
                :class="['px-6 py-2.5 rounded-xl text-sm font-black transition-all flex items-center gap-2', isYearly ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-500/20' : 'text-slate-500 hover:text-slate-900']"
              >
                {{ t('subscription_page.yearly') }}
                <span class="text-[10px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-md font-black">{{ t('subscription_page.save_two_months') }}</span>
              </button>
            </div>
          </div>

          <!-- Pricing Cards -->
          <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 md:gap-6">
            <div
              v-for="group in planGroups"
              :key="group.baseSlug"
              :class="[
                'relative rounded-3xl p-6 flex flex-col border transition-all duration-300 overflow-hidden',
                group.isPopular
                  ? 'bg-emerald-50/50 border-emerald-500/50 shadow shadow-emerald-500/10 scale-[1.01] z-10'
                  : 'bg-white border-slate-100 hover:border-slate-200'
              ]"
            >
              <div v-if="group.isPopular" class="absolute top-0 inset-x-0 h-1 bg-gradient-to-l from-[#00C853] to-emerald-400"></div>

              <!-- Badges -->
              <div class="flex items-center gap-2 mb-4 flex-wrap">
                <span class="text-[10px] font-black uppercase tracking-wider px-2 py-1 rounded-full bg-slate-100 text-slate-700">
                  {{ group.isMessageCredit ? t('subscription_page.plan_type_message_bundle') : t('subscription_page.plan_type_subscription') }}
                </span>
                <span v-if="group.isPopular" class="bg-emerald-600 text-white text-[10px] px-2 py-1 rounded-full font-black">{{ t('subscription_page.popular_badge') }}</span>
                <span v-else-if="group.baseSlug === 'partners'" class="bg-slate-100 text-slate-700 text-[10px] px-2 py-1 rounded-full font-black">{{ t('subscription_page.label_partners') }}</span>
                <span v-else-if="group.baseSlug === 'business'" class="bg-slate-100 text-slate-700 text-[10px] px-2 py-1 rounded-full font-black">{{ t('subscription_page.label_business') }}</span>
                <span v-if="group.isCurrent" class="bg-emerald-100 text-emerald-700 border border-[#00C853]/30 text-[10px] px-2 py-1 rounded-full font-black">{{ t('subscription_page.current_active_plan') }}</span>
              </div>

              <!-- Plan Name -->
              <h3 class="text-xl font-black mb-1">{{ group.displayName }}</h3>
              <p class="text-slate-500 text-sm font-medium leading-relaxed mb-5">{{ group.description }}</p>

              <!-- Price -->
              <div class="mb-4">
                <div class="flex items-baseline gap-2">
                  <span class="text-3xl md:text-4xl font-black text-slate-900">{{ formatPrice(group.activePlan?.price) }}</span>
                  <span v-if="group.activePlan?.price > 0" class="text-slate-500 text-sm font-bold">{{ intervalLabel(group.activePlan) }}</span>
                </div>
                <div v-if="isYearly && group.activePlan?.price > 0" class="flex items-center gap-2 mt-2">
                  <span class="text-slate-500 text-sm line-through">{{ formatPrice(group.monthlyTotal) }}</span>
                  <span class="text-emerald-600 text-xs font-black">{{ t('subscription_page.save_amount').replace('{amount}', formatPrice(group.savings)) }}</span>
                </div>
              </div>

              <!-- Limit -->
              <div class="mb-5 p-3 rounded-xl bg-slate-50 border border-slate-100">
                <p class="text-emerald-600 text-sm font-black mb-0.5">{{ group.limitText }}</p>
                <p v-if="group.isMessageCredit" class="text-slate-500 text-xs font-medium">{{ t('subscription_page.credits_usable_anytime') }}</p>
              </div>

              <!-- CTA -->
              <div class="mb-5">
                <div v-if="group.isCurrent" class="w-full py-3.5 rounded-2xl font-black text-sm text-center bg-emerald-50 text-emerald-600 border border-[#00C853]/30">
                  {{ t('subscription_page.current_active_plan') }}
                </div>
                <div v-else-if="group.isContact" class="w-full">
                  <a
                    href="https://wa.me/201011679726?text=Hello,%20I%20want%20to%20activate%20Wavixa"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="block w-full py-3.5 rounded-2xl font-black text-sm text-center bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition-colors active:scale-95"
                  >
                    {{ t('subscription_page.contact_us') }}
                  </a>
                  <p class="text-slate-500 text-xs text-center mt-2 font-bold font-mono" dir="ltr">+20 101 167 9726</p>
                </div>
                <div v-else>
                  <div v-if="selectedPlanSlug === group.activePlan?.slug" class="space-y-3">
                    <PayPalButton :plan="group.activePlan" @success="handlePaymentSuccess" @error="handlePaymentError" />
                    <button @click="selectedPlanSlug = null" class="text-[10px] font-bold text-slate-500 hover:text-red-400">{{ t('common.cancel') }}</button>
                  </div>
                  <button
                    v-else
                    @click="selectedPlanSlug = group.activePlan?.slug"
                    :class="[
                      'w-full py-3.5 rounded-2xl font-black text-sm transition-all active:scale-95',
                      group.isPopular
                        ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-500/20'
                        : 'bg-white hover:bg-slate-50 text-slate-900 border border-slate-200 shadow-sm'
                    ]"
                  >
                    {{ t('subscription_page.choose_plan') }}
                  </button>
                  <p v-if="selectedPlanSlug !== group.activePlan?.slug" class="text-slate-500 text-xs text-center mt-2 font-bold">{{ t('subscription_page.secure_checkout_paypal') }}</p>
                </div>
              </div>

              <!-- Features -->
              <ul class="space-y-2.5 mt-auto">
                <li v-for="(feature, idx) in group.features" :key="idx" class="flex items-start gap-2.5 text-slate-700 text-sm font-medium">
                  <div class="w-5 h-5 mt-0.5 rounded-full bg-emerald-600/10 text-emerald-600 flex items-center justify-center shrink-0">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 13l4 4L19 7" /></svg>
                  </div>
                  <span>{{ feature }}</span>
                </li>
              </ul>
            </div>
          </div>
        </section>

        <!-- How It Works -->
        <section class="max-w-5xl mx-auto">
          <div class="bg-white border border-slate-100 rounded-3xl p-6 md:p-10">
            <h2 class="text-2xl md:text-3xl font-black mb-8 text-center">{{ t('subscription_page.how_it_works_title') }}</h2>
            <div class="grid md:grid-cols-2 gap-6 md:gap-10">
              <div class="space-y-4">
                <div class="flex items-center gap-3 mb-2">
                  <div class="w-10 h-10 rounded-xl bg-emerald-600/10 text-emerald-600 flex items-center justify-center font-black text-lg">1</div>
                  <h3 class="text-lg font-black">{{ t('subscription_page.message_packages') }}</h3>
                </div>
                <p class="text-slate-500 text-sm leading-relaxed">
                  {{ t('subscription_page.how_it_works_message_desc') }}
                </p>
              </div>
              <div class="space-y-4">
                <div class="flex items-center gap-3 mb-2">
                  <div class="w-10 h-10 rounded-xl bg-emerald-900/40 text-emerald-700 flex items-center justify-center font-black text-lg">2</div>
                  <h3 class="text-lg font-black">{{ t('subscription_page.business_subscriptions') }}</h3>
                </div>
                <p class="text-slate-500 text-sm leading-relaxed">
                  {{ t('subscription_page.how_it_works_business_desc') }}
                </p>
              </div>
            </div>
          </div>
        </section>

        <!-- Comparison -->
        <section class="max-w-6xl mx-auto">
          <h2 class="text-2xl md:text-3xl font-black text-center mb-8">{{ t('subscription_page.compare_plans_title') }}</h2>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[600px] border border-slate-100 rounded-2xl overflow-hidden bg-white" :class="isRTL ? 'text-right' : 'text-left'">
              <thead>
                <tr class="bg-slate-50 text-slate-700 text-sm font-black border-b border-slate-100">
                  <th class="px-5 py-4">{{ t('subscription_page.feature') }}</th>
                  <th v-for="group in planGroups" :key="group.baseSlug" class="px-5 py-4 text-center">{{ group.displayName }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-sm font-medium">
                <tr v-for="row in comparisonRows" :key="row.label" class="hover:bg-slate-50 transition-colors">
                  <td class="px-5 py-4 text-slate-500">{{ row.label }}</td>
                  <td v-for="(value, idx) in row.values" :key="idx" class="px-5 py-4 text-center text-slate-900 font-bold">{{ value }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- FAQ -->
        <section class="max-w-3xl mx-auto">
          <h2 class="text-2xl md:text-3xl font-black text-center mb-8">{{ t('subscription_page.faq_title') }}</h2>
          <div class="space-y-4">
            <details
              v-for="(item, index) in faqList"
              :key="index"
              class="group bg-white border border-slate-100 rounded-2xl open:bg-slate-50 transition-colors"
            >
              <summary class="flex items-center justify-between p-5 cursor-pointer list-none">
                <span class="font-black text-sm md:text-base">{{ item.question }}</span>
                <span class="text-emerald-600 text-xl transition-transform group-open:rotate-180">+</span>
              </summary>
              <p class="px-5 pb-5 text-slate-500 text-sm leading-relaxed">{{ item.answer }}</p>
            </details>
          </div>
        </section>

        <!-- Billing History -->
        <section class="max-w-6xl mx-auto">
          <h2 class="text-xl md:text-2xl font-black mb-5">{{ t('subscription_page.transaction_history_title') }}</h2>
          <div class="bg-white border border-slate-100 rounded-3xl overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full min-w-[600px] border-collapse" :class="isRTL ? 'text-right' : 'text-left'">
                <thead>
                  <tr class="bg-slate-50 text-slate-700 text-sm font-black border-b border-slate-100">
                    <th class="px-5 py-4">{{ t('common.date') }}</th>
                    <th class="px-5 py-4">{{ t('subscription_page.type') }}</th>
                    <th class="px-5 py-4">{{ t('subscription_page.plan') }}</th>
                    <th class="px-5 py-4">{{ t('common.status') }}</th>
                    <th class="px-5 py-4" :class="isRTL ? 'text-left' : 'text-right'">{{ t('subscription_page.amount') }}</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm font-medium">
                  <tr v-if="transactionsLoading" class="animate-pulse">
                    <td colspan="5" class="px-5 py-8 text-center text-slate-500 font-bold">{{ t('common.loading') }}</td>
                  </tr>
                  <tr v-else-if="transactions.length === 0">
                    <td colspan="5" class="px-5 py-12 text-center text-slate-500 font-bold">{{ t('subscription_page.no_transactions') }}</td>
                  </tr>
                  <tr v-for="transaction in transactions" :key="transaction.id" class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-4 text-slate-700">{{ formatDate(transaction.created_at) }}</td>
                    <td class="px-5 py-4 text-slate-700 capitalize">{{ transaction.type }}</td>
                    <td class="px-5 py-4 text-emerald-600 font-bold">{{ transaction.plan?.name || '-' }}</td>
                    <td class="px-5 py-4">
                      <span :class="['px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-tighter', transactionStatusClass(transaction.status)]">
                        {{ transaction.status }}
                      </span>
                    </td>
                    <td class="px-5 py-4 font-black text-slate-900" :class="isRTL ? 'text-left' : 'text-right'">{{ formatAmount(transaction.amount, transaction.currency) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </template>
    </div>
  </DashboardLayout>
</template>

<script>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/services/api';
import auth from '@/services/auth';
import { t, isRTL } from '@/i18n';
import PayPalButton from '@/components/PayPalButton.vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';

export default {
  name: 'Subscription',
  components: {
    DashboardLayout,
    PayPalButton,
  },
  setup() {
    const route = useRoute();
    const user = ref(auth.getUser());
    const subscription = ref({});
    const plans = ref([]);
    const transactions = ref([]);
    const loading = ref(true);
    const transactionsLoading = ref(false);
    const error = ref('');
    const isYearly = ref(true);
    const selectedPlanSlug = ref(null);

    const baseSlugs = ['starter', 'pro', 'business', 'partners'];
    const yearlySuffix = '-yearly';

    const planMap = computed(() => {
      const map = {};
      plans.value.forEach(plan => {
        map[plan.slug] = plan;
      });
      return map;
    });

    const currentPlanSlug = computed(() => subscription.value?.plan?.slug);

    const planGroups = computed(() => {
      return baseSlugs.map(base => {
        const monthly = planMap.value[base];
        const yearly = planMap.value[base + yearlySuffix];
        const active = isYearly.value ? (yearly || monthly) : (monthly || yearly);

        const displayName = t(`subscription_page.plan_${base}`);

        const isMessageCredit = ['starter', 'pro'].includes(base);
        const isContact = ['business', 'partners'].includes(base);
        const isPopular = base === 'pro';
        const isCurrent = currentPlanSlug.value === active?.slug;

        const monthlyTotal = (monthly?.price || 0) * 12;
        const savings = monthlyTotal - (yearly?.price || 0);

        const limitText = buildLimitText(active, base);

        const descKey = `subscription_page.plan_desc_${base}${isYearly.value ? '_yearly' : ''}`;
        const description = t(descKey, active?.description || '');

        const features = getLocalizedFeatures(base, isYearly.value);

        return {
          baseSlug: base,
          displayName,
          description,
          monthly,
          yearly,
          activePlan: active,
          monthlyTotal,
          savings,
          isContact,
          isPopular,
          isCurrent,
          isMessageCredit,
          limitText,
          features,
        };
      });
    });

    const comparisonRows = computed(() => {
      return [
        {
          label: isRTL.value ? 'عدد الرسائل' : 'Message Limit',
          values: planGroups.value.map(g => g.limitText),
        },
        {
          label: isRTL.value ? 'أجهزة WhatsApp' : 'WhatsApp Sessions',
          values: planGroups.value.map(g => `${(g.activePlan?.sessions_limit || 1)} ${isRTL.value ? 'جهاز' : 'Session'}`),
        },
        {
          label: isRTL.value ? 'وصول API' : 'API Access',
          values: planGroups.value.map(() => isRTL.value ? 'كامل' : 'Full'),
        },
        {
          label: 'Webhooks',
          values: planGroups.value.map(g => g.baseSlug === 'starter' ? (isRTL.value ? 'أساسي' : 'Basic') : (isRTL.value ? 'متقدمة' : 'Advanced')),
        },
        {
          label: isRTL.value ? 'لوحة التحليلات' : 'Analytics',
          values: planGroups.value.map(g => g.baseSlug === 'starter' ? '—' : (isRTL.value ? 'متوفر' : 'Included')),
        },
        {
          label: isRTL.value ? 'الدعم الفني' : 'Support',
          values: planGroups.value.map(g =>
            g.baseSlug === 'starter' ? (isRTL.value ? 'بريد إلكتروني' : 'Email') :
            g.baseSlug === 'pro' ? '24/7' : (isRTL.value ? 'مخصص VIP' : 'Dedicated VIP')
          ),
        },
        {
          label: isRTL.value ? 'نوع الباقة' : 'Plan Type',
          values: planGroups.value.map(g => g.isMessageCredit ? (isRTL.value ? 'رصيد رسائل' : 'Message Credit') : (isRTL.value ? 'اشتراك' : 'Subscription')),
        },
      ];
    });

    const buildLimitText = (plan, base) => {
      if (!plan) return '—';
      const UNLIMITED_THRESHOLD = 1000000000;
      const messages = plan.messages_limit || 0;
      if (messages >= UNLIMITED_THRESHOLD) {
        if (base === 'partners') return isRTL.value ? 'حتى 100 جهاز WhatsApp' : 'Up to 100 WhatsApp Sessions';
        return isRTL.value ? 'رسائل غير محدودة' : 'Unlimited Messages';
      }
      const formatted = messages.toLocaleString();
      return isYearly.value && ['starter', 'pro'].includes(base)
        ? (isRTL.value ? `${formatted} رسالة للسنة` : `${formatted} messages / year`)
        : (isRTL.value ? `${formatted} رسالة` : `${formatted} messages`);
    };

    const getLocalizedFeatures = (base, isYearlyValue) => {
      if (!isRTL.value) {
        const mapEn = {
          starter: isYearlyValue
            ? ['36,000 messages / year', '1 WhatsApp session', 'Full API access', 'Webhook callbacks', 'Priority email support']
            : ['3,000 message credits (never expire)', '1 WhatsApp session', 'Full API access', 'Webhook callbacks', 'Priority email support'],
          pro: isYearlyValue
            ? ['180,000 messages / year', '1 WhatsApp session', 'Full API access', 'Advanced webhooks', 'Analytics dashboard', '24/7 Priority support']
            : ['15,000 message credits (never expire)', '1 WhatsApp session', 'Full API access', 'Advanced webhooks', 'Analytics dashboard', '24/7 Priority support'],
          business: isYearlyValue
            ? ['Unlimited annual messages', '1 WhatsApp session', 'Full API access', 'Advanced webhooks', 'Detailed analytics', 'Dedicated VIP support']
            : ['Unlimited monthly messages', '1 WhatsApp session', 'Full API access', 'Advanced webhooks', 'Detailed analytics', 'Dedicated VIP support'],
          partners: [
            'All Wavixa features',
            'Up to 100 WhatsApp sessions',
            'Full API access',
            'Advanced webhooks',
            'Multi-tenant analytics',
            'Dedicated partner support',
          ],
        };
        return mapEn[base] || [];
      }
      const map = {
        starter: isYearlyValue
          ? ['36,000 رسالة للسنة', 'جهاز WhatsApp واحد', 'وصول كامل إلى API', 'دعم Webhooks', 'دعم عبر البريد الإلكتروني بأولوية']
          : ['3,000 رسالة حتى نفاد الرصيد', 'جهاز WhatsApp واحد', 'وصول كامل إلى API', 'دعم Webhooks', 'دعم عبر البريد الإلكتروني بأولوية'],
        pro: isYearlyValue
          ? ['180,000 رسالة للسنة', 'جهاز WhatsApp واحد', 'وصول كامل إلى API', 'Webhooks متقدمة', 'لوحة تحليلات', 'دعم 24/7']
          : ['15,000 رسالة حتى نفاد الرصيد', 'جهاز WhatsApp واحد', 'وصول كامل إلى API', 'Webhooks متقدمة', 'لوحة تحليلات', 'دعم 24/7'],
        business: isYearlyValue
          ? ['رسائل غير محدودة طوال فترة الاشتراك', 'جهاز WhatsApp واحد', 'وصول كامل إلى API', 'Webhooks متقدمة', 'تحليلات تفصيلية', 'دعم مخصص']
          : ['رسائل شهرية غير محدودة', 'جهاز WhatsApp واحد', 'وصول كامل إلى API', 'Webhooks متقدمة', 'تحليلات تفصيلية', 'دعم مخصص'],
        partners: [
          'جميع مميزات Wavixa',
          'حتى 100 جهاز WhatsApp',
          'وصول كامل إلى API',
          'Webhooks متقدمة',
          'تحليلات متقدمة',
          'دعم مخصص للشركاء',
        ],
      };
      return map[base] || [];
    };

    const faqList = computed(() => isRTL.value ? [
      {
        question: 'هل رسائل Starter وPro تنتهي بنهاية الشهر؟',
        answer: 'لا. الرسائل لا تنتهي بنهاية الشهر. تستمر الباقة حتى يتم استهلاك رصيد الرسائل.',
      },
      {
        question: 'هل الرصيد السنوي يتجدد كل شهر؟',
        answer: 'لا. السنوي يمنحك رصيد 12 شهرًا دفعة واحدة، ويظل الرصيد متاحًا حتى استهلاكه بالكامل.',
      },
      {
        question: 'كم عدد الرسائل في Starter السنوي؟',
        answer: '36,000 رسالة للسنة، كرصيد واحد قابل للاستخدام خلال السنة.',
      },
      {
        question: 'كم عدد الرسائل في Pro السنوي؟',
        answer: '180,000 رسالة للسنة، كرصيد واحد قابل للاستخدام خلال السنة.',
      },
      {
        question: 'هل Business توفر رسائل غير محدودة؟',
        answer: 'نعم، Business توفر رسائل غير محدودة طوال مدة الاشتراك.',
      },
      {
        question: 'كم جهاز WhatsApp تدعم Partners؟',
        answer: 'Partners تدعم حتى 100 جهاز WhatsApp.',
      },
      {
        question: 'كيف يمكنني تفعيل Business أو Partners؟',
        answer: 'تواصل معنا على +20 101 167 9726 لتفعيل الخطة فوراً.',
      },
    ] : [
      {
        question: 'Do Starter and Pro message credits expire at month end?',
        answer: 'No. Message credits carry over and remain valid until completely consumed.',
      },
      {
        question: 'How do yearly bundles work?',
        answer: 'Yearly plans deliver the full 12-month message allocation upfront with a 2-month discount included.',
      },
      {
        question: 'How many messages are in the Starter Yearly bundle?',
        answer: '36,000 messages for the year, available for use as a single pool of credits.',
      },
      {
        question: 'How many messages are in the Pro Yearly bundle?',
        answer: '180,000 messages for the year, available for use as a single pool of credits.',
      },
      {
        question: 'Does the Business tier offer unlimited messages?',
        answer: 'Yes, Business provides unlimited message dispatch throughout your active subscription period.',
      },
      {
        question: 'How many devices does Partners support?',
        answer: 'Partners tier supports up to 100 concurrent connected WhatsApp devices.',
      },
    ]);

    const loadSubscription = async () => {
      try {
        loading.value = true;
        error.value = '';
        const [subRes, plansRes, txRes] = await Promise.all([
          api.get('/subscription'),
          api.get('/plans'),
          api.get('/billing/transactions'),
        ]);

        if (subRes.success) {
          subscription.value = subRes.data;
        } else {
          error.value = subRes.message || (isRTL.value ? 'فشل في تحميل بيانات الاشتراك' : 'Failed to load subscription details');
        }

        if (plansRes.success) {
          plans.value = plansRes.data || [];
        }

        if (txRes.success) {
          transactions.value = txRes.data?.data || [];
        }
      } catch (err) {
        error.value = err.data?.message || (isRTL.value ? 'فشل في تحميل البيانات' : 'Failed to load data');
      } finally {
        loading.value = false;
      }
    };

    const loadTransactions = async () => {
      try {
        transactionsLoading.value = true;
        const response = await api.get('/billing/transactions');
        if (response.success) {
          transactions.value = response.data?.data || [];
        }
      } catch (err) {
        console.error('Failed to load transactions:', err);
      } finally {
        transactionsLoading.value = false;
      }
    };

    const handlePaymentSuccess = async (data) => {
      error.value = '';
      selectedPlanSlug.value = null;
      await loadSubscription();
      await loadTransactions();
      alert(isRTL.value ? 'تمت عملية الدفع بنجاح! رصيدك الحالي: ' + formatMessages(data?.messages_remaining) + ' رسالة.' : 'Payment successful! Your balance is now: ' + formatMessages(data?.messages_remaining) + ' messages.');
    };

    const handlePaymentError = (err) => {
      error.value = err?.data?.message || err?.message || (isRTL.value ? 'فشلت عملية الدفع. يرجى المحاولة مرة أخرى.' : 'Payment failed. Please try again.');
    };

    const UNLIMITED_THRESHOLD = 1000000000;
    const isUnlimited = (n) => (n || 0) >= UNLIMITED_THRESHOLD;
    const formatMessages = (n) => isUnlimited(n) ? (isRTL.value ? 'غير محدود' : 'Unlimited') : (n || 0).toLocaleString();

    const formatDate = (date) => {
      if (!date) return '-';
      return new Date(date).toLocaleDateString(isRTL.value ? 'ar-EG' : 'en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
    };

    const formatPrice = (price) => {
      if (!price || price <= 0) return isRTL.value ? 'مجاناً' : 'Free';
      return `$${price}`;
    };

    const intervalLabel = (plan) => {
      if (!plan) return '';
      if (plan.is_free) return '';
      if (plan.interval === 'month') return isRTL.value ? '/ شهر' : '/ mo';
      if (plan.interval === 'year') return isRTL.value ? '/ سنة' : '/ yr';
      return isRTL.value ? '/ دفعة واحدة' : '/ one-time';
    };

    const formatAmount = (amount, currency) => {
      if (amount <= 0) return isRTL.value ? 'مجاني' : 'Free';
      return `$${amount}`;
    };

    const transactionStatusClass = (status) => {
      if (status === 'completed') return 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20';
      if (status === 'failed') return 'bg-red-500/10 text-red-400 border border-red-500/20';
      if (status === 'refunded') return 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
      return 'bg-slate-500/10 text-slate-500 border border-slate-500/20';
    };

    const statusBadgeClass = computed(() => {
      const status = subscription.value?.status;
      if (status === 'active') return 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20';
      if (status === 'cancelled') return 'bg-red-500/10 text-red-400 border border-red-500/20';
      if (status === 'expired') return 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
      return 'bg-slate-500/10 text-slate-500 border border-slate-500/20';
    });

    const scrollToTop = () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    onMounted(() => {
      loadSubscription();
    });

    watch(() => route.query.upgrade, (slug) => {
      if (slug) {
        selectedPlanSlug.value = String(slug);
        isYearly.value = selectedPlanSlug.value.endsWith('-yearly');
        nextTick(() => {
          document.getElementById('plans')?.scrollIntoView({ behavior: 'smooth' });
        });
      }
    });

    return {
      user,
      subscription,
      plans,
      transactions,
      loading,
      transactionsLoading,
      error,
      isYearly,
      selectedPlanSlug,
      planGroups,
      comparisonRows,
      faqList,
      loadSubscription,
      loadTransactions,
      handlePaymentSuccess,
      handlePaymentError,
      formatMessages,
      formatDate,
      formatPrice,
      intervalLabel,
      formatAmount,
      transactionStatusClass,
      statusBadgeClass,
      scrollToTop,
      t,
      isRTL,
    };
  },
};
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap');

.font-sans {
  font-family: 'Cairo', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
</style>
