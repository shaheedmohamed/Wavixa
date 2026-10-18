<template>
  <div class="relative">
    <button
      @click="open = !open"
      class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl text-xs font-bold border transition-all"
      :class="badgeClass"
    >
      <span class="w-2 h-2 rounded-full" :class="dotClass"></span>
      <span>{{ isRTL ? 'السمعة' : 'Rep' }} {{ reputation.score }}/{{ reputation.max_score }}</span>
      <span class="text-[10px] opacity-80">{{ levelLabel }}</span>
    </button>

    <div
      v-if="open"
      class="absolute top-full mt-2 w-72 sm:w-80 bg-white border border-slate-200 rounded-2xl shadow-xl p-4 z-50"
      :class="isRTL ? 'left-0' : 'right-0'"
    >
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">
          {{ isRTL ? 'تفاصيل سلوك الحساب' : 'Account Behavior' }}
        </h3>
        <span :class="levelClass" class="text-[10px] font-black px-2 py-0.5 rounded-full border">
          {{ levelLabel }}
        </span>
      </div>

      <div class="space-y-2 max-h-72 overflow-y-auto custom-scrollbar pr-1">
        <div
          v-for="(value, key) in displayFactors"
          :key="key"
          class="flex items-center justify-between text-[10px] sm:text-xs py-1 border-b border-slate-100 last:border-0"
        >
          <span class="text-slate-500 font-medium">{{ key }}</span>
          <span class="font-mono font-bold text-slate-800">{{ value }}</span>
        </div>
      </div>

      <p class="text-[9px] text-slate-400 mt-3 leading-relaxed">
        {{ isRTL
          ? 'Wavixa لا تضمن عدم الحظر. هذه النتيجة تعكس سلوك الإرسال فقط.'
          : 'Wavixa does not guarantee no ban. This score reflects sending behavior only.' }}
      </p>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import api from '@/services/api';
import { isRTL } from '@/i18n';

const levelOrder = {
  'Low Risk': 0,
  'Normal': 1,
  'Warning': 2,
  'Limited': 3,
  'Campaign Locked': 4,
  'Account Suspended': 5,
};

export default {
  name: 'ReputationBadge',
  setup() {
    const open = ref(false);
    const loading = ref(false);
    const reputation = ref({
      score: 0,
      max_score: 100,
      level: 'Low Risk',
      factors: {},
    });

    const factorLabels = {
      ar: {
        account_age_days: 'عمر الحساب (أيام)',
        account_age_points: 'نقاط عمر الحساب',
        message_volume_24h: 'رسائل 24 ساعة',
        message_volume_7d: 'رسائل 7 أيام',
        message_volume_points: 'نقاط حجم الرسائل',
        failed_messages_7d: 'رسائل فاشلة 7 أيام',
        failed_messages_ratio: 'نسبة الرسائل الفاشلة',
        failed_messages_points: 'نقاط الرسائل الفاشلة',
        reply_rate: 'نسبة الرد',
        reply_rate_points: 'نقاط نسبة الرد',
        new_contacts_7d: 'جهات اتصال جديدة',
        new_contacts_ratio: 'نسبة جهات الاتصال الجديدة',
        new_contacts_points: 'نقاط جهات جديدة',
        campaigns_7d: 'حملات 7 أيام',
        campaigns_points: 'نقاط الحملات',
        sessions_30d: 'جلسات 30 يوم',
        disconnected_sessions_30d: 'جلسات منفصلة',
        disconnect_ratio: 'نسبة الانفصال',
        disconnect_points: 'نقاط الانفصال',
        sessions_points: 'نقاط الجلسات',
        unresolved_fraud_alerts: 'تنبيهات الاحتيال',
        fraud_points: 'نقاط الاحتيال',
      },
      en: {
        account_age_days: 'Account Age (days)',
        account_age_points: 'Account Age Points',
        message_volume_24h: 'Messages 24h',
        message_volume_7d: 'Messages 7d',
        message_volume_points: 'Volume Points',
        failed_messages_7d: 'Failed 7d',
        failed_messages_ratio: 'Failed Ratio',
        failed_messages_points: 'Failed Points',
        reply_rate: 'Reply Rate',
        reply_rate_points: 'Reply Rate Points',
        new_contacts_7d: 'New Contacts 7d',
        new_contacts_ratio: 'New Contacts Ratio',
        new_contacts_points: 'New Contacts Points',
        campaigns_7d: 'Campaigns 7d',
        campaigns_points: 'Campaigns Points',
        sessions_30d: 'Sessions 30d',
        disconnected_sessions_30d: 'Disconnected Sessions',
        disconnect_ratio: 'Disconnect Ratio',
        disconnect_points: 'Disconnect Points',
        sessions_points: 'Sessions Points',
        unresolved_fraud_alerts: 'Fraud Alerts',
        fraud_points: 'Fraud Points',
      },
    };

    const load = async () => {
      try {
        loading.value = true;
        const res = await api.get('/reputation');
        if (res?.success && res.data) {
          reputation.value = { ...reputation.value, ...res.data };
        }
      } catch (err) {
        console.warn('Reputation load failed', err);
      } finally {
        loading.value = false;
      }
    };

    onMounted(() => {
      load();
      const interval = setInterval(load, 120000);
      onUnmounted(() => clearInterval(interval));
    });

    const displayFactors = computed(() => {
      const labels = factorLabels[isRTL.value ? 'ar' : 'en'];
      const out = {};
      for (const [key, value] of Object.entries(reputation.value.factors || {})) {
        const label = labels[key] || key;
        out[label] = typeof value === 'number' ? Number(value.toFixed(2)) : value;
      }
      return out;
    });

    const levelIndex = computed(() => levelOrder[reputation.value.level] ?? 0);

    const levelLabel = computed(() => {
      const map = {
        ar: {
          'Low Risk': 'منخفض',
          'Normal': 'عادي',
          'Warning': 'تحذير',
          'Limited': 'مقيّد',
          'Campaign Locked': 'الحملات مقفلة',
          'Account Suspended': 'الحساب معلّق',
        },
        en: {
          'Low Risk': 'Low',
          'Normal': 'Normal',
          'Warning': 'Warning',
          'Limited': 'Limited',
          'Campaign Locked': 'Campaign Locked',
          'Account Suspended': 'Suspended',
        },
      };
      return map[isRTL.value ? 'ar' : 'en'][reputation.value.level] || reputation.value.level;
    });

    const badgeClass = computed(() => {
      const colors = [
        'bg-emerald-50 text-emerald-700 border-emerald-200',
        'bg-sky-50 text-sky-700 border-sky-200',
        'bg-amber-50 text-amber-700 border-amber-200',
        'bg-orange-50 text-orange-700 border-orange-200',
        'bg-rose-50 text-rose-700 border-rose-200',
        'bg-slate-900 text-white border-slate-900',
      ];
      return colors[levelIndex.value] || colors[0];
    });

    const dotClass = computed(() => {
      const colors = [
        'bg-emerald-500',
        'bg-sky-500',
        'bg-amber-500',
        'bg-orange-500',
        'bg-rose-500',
        'bg-red-500',
      ];
      return colors[levelIndex.value] || colors[0];
    });

    const levelClass = computed(() => {
      const colors = [
        'bg-emerald-100 text-emerald-700 border-emerald-200',
        'bg-sky-100 text-sky-700 border-sky-200',
        'bg-amber-100 text-amber-700 border-amber-200',
        'bg-orange-100 text-orange-700 border-orange-200',
        'bg-rose-100 text-rose-700 border-rose-200',
        'bg-slate-900 text-white border-slate-900',
      ];
      return colors[levelIndex.value] || colors[0];
    });

    return {
      open,
      reputation,
      loading,
      isRTL,
      displayFactors,
      levelLabel,
      levelClass,
      badgeClass,
      dotClass,
    };
  },
};
</script>
