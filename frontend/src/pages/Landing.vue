<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import {
  MessageCircle,
  Smartphone,
  Zap,
  Globe,
  ShieldCheck,
  BarChart3,
  ArrowLeft,
  ArrowRight,
  Menu,
  X,
  Check,
  ChevronDown,
  Clock,
  CreditCard,
  Code2,
  Sparkles,
  TrendingUp,
  Users,
  Send,
  Layers,
  Bot,
  Lock,
  Headphones,
  FileSpreadsheet,
  CheckCheck,
  Building2,
  ShoppingCart,
  Megaphone,
  Radio,
  ExternalLink,
  ChevronRight,
  CheckCircle2,
  ShieldAlert,
} from 'lucide-vue-next';
import logo from '@/assets/logo.png';
import auth from '@/services/auth';
import api from '@/services/api';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { isRTL, t, locale } from '@/i18n';

const router = useRouter();
const isMenuOpen = ref(false);
const isLoggedIn = ref(false);
const scrolled = ref(false);
const plans = ref([]);
const loadingPlans = ref(true);
const expandedFaq = ref(0);
const activeTab = ref('whatsapp');

// Plan Category Toggle: 'quota' (Starter & Pro) vs 'unlimited' (Business & Partners)
const planCategory = ref('quota');

// Per-card billing toggle ('monthly' | 'yearly')
const cardBilling = ref({
  starter: 'yearly',
  pro: 'yearly',
  business: 'yearly',
  partners: 'yearly',
});

// Interactive Demo State
const demoMessages = computed(() => {
  if (locale.value === 'ar') {
    return [
      { id: 1, from: 'client', text: 'السلام عليكم، محتاج تفاصيل الاشتراك عندكم', time: '10:42 ص' },
      { id: 2, from: 'bot', text: 'أهلاً بك! 🌟 في Wavixa بنقدملك أسرع بوابة WhatsApp و AI مكانك للرد على عملائك تلقائياً 24/7. تحب تشوف تجربة مجانية؟', time: '10:42 ص' }
    ];
  }
  return [
    { id: 1, from: 'client', text: 'Hi, I need details about your plans and features', time: '10:42 AM' },
    { id: 2, from: 'bot', text: 'Welcome! 🌟 Wavixa offers the fastest WhatsApp Gateway & Smart AI bot to reply to your clients 24/7. Want a free trial?', time: '10:42 AM' }
  ];
});
const activeDemoList = ref([]);
const demoInput = ref('');
const isBotTyping = ref(false);

watch(demoMessages, (msgs) => {
  activeDemoList.value = [...msgs];
}, { immediate: true });

function sendDemoMessage(quickText = null) {
  const text = quickText || demoInput.value.trim();
  if (!text) return;
  
  const now = new Date().toLocaleTimeString(locale.value === 'ar' ? 'ar-EG' : 'en-US', { hour: '2-digit', minute: '2-digit' });
  activeDemoList.value.push({
    id: Date.now(),
    from: 'client',
    text,
    time: now
  });
  
  if (!quickText) demoInput.value = '';
  
  isBotTyping.value = true;
  setTimeout(() => {
    isBotTyping.value = false;
    let reply = locale.value === 'ar'
      ? 'تم استلام طلبك بنجاح! 🚀 تم تحويل بياناتك لنظام الـ CRM وفتح جلسة متابعة تلقائية.'
      : 'Your request has been received successfully! 🚀 Data synced to CRM and automated follow-up started.';

    if (text.toLowerCase().includes('price') || text.toLowerCase().includes('cost') || text.includes('سعر') || text.includes('باقة')) {
      reply = locale.value === 'ar'
        ? 'باقاتنا تبدأ من 0$ للتجربة المجانية، مع باقات احترافية للمؤسسات تشمل أجهزة غير محدودة و API متكامل 💎'
        : 'Plans start at $0 for Free Trial, with Pro & Unlimited options including full API access & multi-devices 💎';
    } else if (text.toLowerCase().includes('ban') || text.includes('حظر') || text.includes('أمان')) {
      reply = locale.value === 'ar'
        ? 'Wavixa لا تضمن عدم تعرض الرقم للحظر، لكن النظام مصمم لتقليل السلوكيات التي قد تؤدي إلى تقييد الرقم، ويجب استخدامه وفق سياسات WhatsApp.'
        : 'Wavixa does not guarantee that your number will not be banned. The system is designed to reduce behaviors that may lead to restriction, and must be used in accordance with WhatsApp policies.';
    }
    
    activeDemoList.value.push({
      id: Date.now() + 1,
      from: 'bot',
      text: reply,
      time: new Date().toLocaleTimeString(locale.value === 'ar' ? 'ar-EG' : 'en-US', { hour: '2-digit', minute: '2-digit' })
    });
  }, 800);
}

function onScroll() {
  scrolled.value = window.scrollY > 15;
}

function toggleFaq(index) {
  expandedFaq.value = expandedFaq.value === index ? null : index;
}

async function loadPlans() {
  try {
    loadingPlans.value = true;
    const response = await api.get('/plans');
    if (response?.success && Array.isArray(response.data) && response.data.length > 0) {
      plans.value = response.data;
    } else {
      plans.value = fallbackPlans;
    }
  } catch (err) {
    console.warn('Using fallback plans:', err);
    plans.value = fallbackPlans;
  } finally {
    loadingPlans.value = false;
  }
}

const fallbackPlans = [
  {
    name: 'الباقة المجانية',
    slug: 'free',
    price: '0',
    description: 'لتجربة المنصة واستكشاف الإمكانيات والربط البرمجي',
    features: ['100 رسالة تجريبية', 'ربط جهاز واتساب واحد', 'لوحة تحكم أساسية', 'مفاتيح API & Webhooks', 'دعم فني عبر الشات'],
    is_free: true,
  },
  {
    name: 'باقة المحترفين (Pro)',
    slug: 'pro',
    price: '19',
    description: 'الأفضل للمتاجر والشركات الناشئة لزيادة المبيعات والأتمتة',
    features: ['15,000 رسالة شهرياً', 'حتى 5 أجهزة وجلسات متزامنة', 'رد تلقائي بالذكاء الاصطناعي (AI مكانك)', 'حملات تسويقية مجدولة وذكية', 'تكامل n8n و Webhooks كامل', 'تقليل ذكي لمخاطر الحظر'],
    is_popular: true,
  },
  {
    name: 'باقة الشركات (Business)',
    slug: 'business',
    price: '49',
    description: 'حلول غير محدودة للشركات الكبرى والوكالات التسويقية',
    features: ['رسائل غير محدودة بدون قيود', 'أجهزة وجلسات متعددة غير محدودة', 'تكامل شامل: WhatsApp + Telegram + Messenger', 'دعم إعلانات Meta Ads المباشرة', 'أولوية قصوى في طابور الإرسال', 'مدير حساب ودعم فني مخصص 24/7'],
  },
];

const channelsData = computed(() => ({
  whatsapp: {
    title: t('landing.whatsapp_card_title'),
    badge: t('landing.whatsapp_card_badge'),
    desc: t('landing.whatsapp_card_desc'),
    image: 'https://images.unsplash.com/photo-1512428559087-560fa5ceab42?auto=format&fit=crop&w=800&q=80',
    bullets: [
      t('landing.whatsapp_bullet_1'),
      t('landing.whatsapp_bullet_2'),
      t('landing.whatsapp_bullet_3'),
      t('landing.whatsapp_bullet_4')
    ]
  },
  telegram: {
    title: t('landing.telegram_card_title'),
    badge: t('landing.telegram_card_badge'),
    desc: t('landing.telegram_card_desc'),
    image: 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=800&q=80',
    bullets: [
      t('landing.telegram_bullet_1'),
      t('landing.telegram_bullet_2'),
      t('landing.telegram_bullet_3'),
      t('landing.telegram_bullet_4')
    ]
  },
  messenger: {
    title: t('landing.messenger_card_title'),
    badge: t('landing.messenger_card_badge'),
    desc: t('landing.messenger_card_desc'),
    image: 'https://i.ytimg.com/vi/lT4PYvSMtcQ/hq720.jpg?sqp=-oaymwEhCK4FEIIDSFryq4qpAxMIARUAAAAAGAElAADIQj0AgKJD&rs=AOn4CLBNSy8DaXMi7yoDSJDFLBEoQH2kvw',
    bullets: [
      t('landing.messenger_bullet_1'),
      t('landing.messenger_bullet_2'),
      t('landing.messenger_bullet_3'),
      t('landing.messenger_bullet_4')
    ]
  },
  api: {
    title: t('landing.api_card_title'),
    badge: t('landing.api_card_badge'),
    desc: t('landing.api_card_desc'),
    image: 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&w=800&q=80',
    bullets: [
      t('landing.api_bullet_1'),
      t('landing.api_bullet_2'),
      t('landing.api_bullet_3'),
      t('landing.api_bullet_4')
    ]
  }
}));

onMounted(async () => {
  isLoggedIn.value = auth.isAuthenticated();
  await loadPlans();
  window.addEventListener('scroll', onScroll, { passive: true });
});

onUnmounted(() => {
  window.removeEventListener('scroll', onScroll);
});
</script>

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
            <a href="#channels" class="px-3 py-1 text-xs font-bold text-slate-300 hover:text-white rounded-full hover:bg-slate-800/60 transition-colors">{{ t('landing.nav_channels') }}</a>
            <a href="#features" class="px-3 py-1 text-xs font-bold text-slate-300 hover:text-white rounded-full hover:bg-slate-800/60 transition-colors">{{ t('landing.nav_features') }}</a>
            <a href="#interactive-demo" class="px-3 py-1 text-xs font-bold text-slate-300 hover:text-white rounded-full hover:bg-slate-800/60 transition-colors">{{ t('landing.nav_demo') }}</a>
            <a href="#pricing" class="px-3 py-1 text-xs font-bold text-slate-300 hover:text-white rounded-full hover:bg-slate-800/60 transition-colors">{{ t('landing.nav_pricing') }}</a>
            <router-link to="/api-docs" class="px-3 py-1 text-xs font-bold text-slate-300 hover:text-white rounded-full hover:bg-slate-800/60 transition-colors">{{ t('landing.nav_api_docs') }}</router-link>
          </nav>

          <!-- Auth CTA & Language -->
          <div class="flex items-center gap-1.5 sm:gap-2">
            <div class="hidden md:block"><LanguageSwitcher class="scale-90" /></div>
            
            <template v-if="isLoggedIn">
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
        <a @click="isMenuOpen = false" href="#channels" class="block py-1.5 text-slate-300 hover:text-white">{{ t('landing.nav_channels') }}</a>
        <a @click="isMenuOpen = false" href="#features" class="block py-1.5 text-slate-300 hover:text-white">{{ t('landing.nav_features') }}</a>
        <a @click="isMenuOpen = false" href="#interactive-demo" class="block py-1.5 text-slate-300 hover:text-white">{{ t('landing.nav_demo') }}</a>
        <a @click="isMenuOpen = false" href="#pricing" class="block py-1.5 text-slate-300 hover:text-white">{{ t('landing.nav_pricing') }}</a>
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

    <main>
      
      <!-- HERO SECTION: Ultra-Dense & Mobile Optimized -->
      <section class="relative pt-20 pb-8 sm:pt-28 sm:pb-16 overflow-hidden">
        
        <!-- Subtle Ambient Lighting -->
        <div class="absolute inset-0 pointer-events-none">
          <div class="absolute top-10 right-1/2 translate-x-1/2 w-[340px] h-[340px] sm:w-[600px] sm:h-[500px] bg-emerald-500/10 rounded-full blur-[90px] sm:blur-[140px]"></div>
          <div class="absolute top-40 left-10 w-[200px] h-[200px] bg-teal-500/10 rounded-full blur-[80px]"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
          
          <!-- Top Tag & Trust Pill -->
          <div class="flex flex-wrap items-center justify-center gap-1.5 mb-3 sm:mb-4">
            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-900/90 border border-slate-800 text-[11px] font-bold text-slate-300 shadow-2xs">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
              <span class="text-emerald-400 font-black">{{ t('landing.meta_partner') }}</span>
              <span class="text-slate-600">|</span>
              <span class="text-slate-400">WhatsApp Engine</span>
            </div>
          </div>

          <!-- Main Headlines -->
          <div class="text-center max-w-4xl mx-auto">
            <h1 class="text-2xl xs:text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white leading-tight mb-2.5 sm:mb-4">
              {{ t('landing.hero_title_1') }}
              <span class="block mt-1 bg-gradient-to-r from-emerald-400 via-teal-300 to-cyan-400 bg-clip-text text-transparent">
                {{ t('landing.hero_title_2') }}
              </span>
            </h1>

            <p class="text-xs sm:text-base lg:text-lg text-slate-300/90 max-w-2xl mx-auto leading-relaxed mb-4 sm:mb-7 font-medium">
              {{ t('landing.hero_desc') }}
            </p>

            <!-- Compact CTA Row (Single Line) -->
            <div class="flex flex-row items-center justify-center gap-2 sm:gap-3 max-w-lg mx-auto mb-5 sm:mb-8 px-1">
              <router-link 
                to="/register" 
                class="flex-1 min-w-0 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 px-3 sm:px-5 py-2.5 sm:py-3 rounded-xl font-black text-xs sm:text-sm transition-all shadow-lg shadow-emerald-500/25 flex items-center justify-center gap-1.5 sm:gap-2 active:scale-98 whitespace-nowrap truncate"
              >
                <span>{{ t('landing.hero_cta_start') }}</span>
                <ArrowLeft v-if="isRTL" class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0" />
                <ArrowRight v-else class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0" />
              </router-link>

              <a 
                href="#interactive-demo" 
                class="flex-1 min-w-0 bg-slate-900/90 hover:bg-slate-800 border border-slate-800 text-slate-200 px-3 sm:px-5 py-2.5 sm:py-3 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-1.5 sm:gap-2 whitespace-nowrap truncate"
              >
                <Zap class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-amber-400 shrink-0" />
                <span>{{ t('landing.hero_cta_demo') }}</span>
              </a>
            </div>

            <!-- Compact Metrics Grid: 4 items in single tight view on mobile -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 max-w-3xl mx-auto">
              <div class="p-2.5 rounded-xl bg-slate-900/70 border border-slate-800/80 text-center">
                <div class="text-base sm:text-xl font-black text-emerald-400">{{ t('landing.metric_delivery') }}</div>
                <div class="text-[10px] sm:text-xs text-slate-400 font-semibold">{{ t('landing.metric_delivery_label') }}</div>
              </div>
              <div class="p-2.5 rounded-xl bg-slate-900/70 border border-slate-800/80 text-center">
                <div class="text-base sm:text-xl font-black text-cyan-400">{{ t('landing.metric_ban') }}</div>
                <div class="text-[10px] sm:text-xs text-slate-400 font-semibold">{{ t('landing.metric_ban_label') }}</div>
              </div>
              <div class="p-2.5 rounded-xl bg-slate-900/70 border border-slate-800/80 text-center">
                <div class="text-base sm:text-xl font-black text-amber-400">{{ t('landing.metric_api') }}</div>
                <div class="text-[10px] sm:text-xs text-slate-400 font-semibold">{{ t('landing.metric_api_label') }}</div>
              </div>
              <div class="p-2.5 rounded-xl bg-slate-900/70 border border-slate-800/80 text-center">
                <div class="text-base sm:text-xl font-black text-teal-400">{{ t('landing.metric_support') }}</div>
                <div class="text-[10px] sm:text-xs text-slate-400 font-semibold">{{ t('landing.metric_support_label') }}</div>
              </div>
            </div>

          </div>

        </div>
      </section>

      <!-- TRUST & META ACCREDITATION BAR -->
      <section class="py-4 border-y border-slate-800/80 bg-slate-900/40">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
          <div class="flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2">
              <div class="w-6 h-6 rounded-md bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                <ShieldCheck class="w-4 h-4" />
              </div>
              <span class="font-black text-white">{{ t('landing.trust_certified') }}</span>
            </div>
            
            <div class="flex flex-wrap items-center gap-2 sm:gap-6 text-[11px] font-bold text-slate-400">
              <span class="flex items-center gap-1"><Check class="w-3.5 h-3.5 text-emerald-400" /> {{ t('landing.trust_item_1') }}</span>
              <span class="flex items-center gap-1"><Check class="w-3.5 h-3.5 text-emerald-400" /> {{ t('landing.trust_item_2') }}</span>
              <span class="flex items-center gap-1"><Check class="w-3.5 h-3.5 text-emerald-400" /> {{ t('landing.trust_item_3') }}</span>
              <span class="flex items-center gap-1"><Check class="w-3.5 h-3.5 text-emerald-400" /> {{ t('landing.trust_item_4') }}</span>
            </div>
          </div>
        </div>
      </section>

      <!-- MULTI-CHANNEL HUB (Interactive Tabs) -->
      <section id="channels" class="py-8 sm:py-14 relative">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
          
          <div class="text-center max-w-2xl mx-auto mb-5 sm:mb-8">
            <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[11px] font-black text-emerald-400 mb-2">
              {{ t('landing.channels_badge') }}
            </div>
            <h2 class="text-xl sm:text-3xl font-black text-white">{{ t('landing.channels_title') }}</h2>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">{{ t('landing.channels_subtitle') }}</p>
          </div>

          <!-- Channel Selector Pills (Compact 4-tab bar) -->
          <div class="flex items-center justify-center gap-1.5 p-1 bg-slate-900/90 border border-slate-800 rounded-2xl max-w-xl mx-auto mb-6">
            <button
              @click="activeTab = 'whatsapp'"
              :class="[
                'flex-1 py-2 px-2 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 cursor-pointer',
                activeTab === 'whatsapp' ? 'bg-emerald-500 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white'
              ]"
            >
              <MessageCircle class="w-4 h-4" />
              <span>{{ t('landing.tab_whatsapp') }}</span>
            </button>
            <button
              @click="activeTab = 'telegram'"
              :class="[
                'flex-1 py-2 px-2 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 cursor-pointer',
                activeTab === 'telegram' ? 'bg-sky-500 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white'
              ]"
            >
              <Send class="w-4 h-4" />
              <span>{{ t('landing.tab_telegram') }}</span>
            </button>
            <button
              @click="activeTab = 'messenger'"
              :class="[
                'flex-1 py-2 px-2 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 cursor-pointer',
                activeTab === 'messenger' ? 'bg-blue-500 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white'
              ]"
            >
              <Users class="w-4 h-4" />
              <span>{{ t('landing.tab_messenger') }}</span>
            </button>
            <button
              @click="activeTab = 'api'"
              :class="[
                'flex-1 py-2 px-2 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 cursor-pointer',
                activeTab === 'api' ? 'bg-amber-500 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white'
              ]"
            >
              <Code2 class="w-4 h-4" />
              <span>{{ t('landing.tab_api') }}</span>
            </button>
          </div>

          <!-- Active Channel Content Card -->
          <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 sm:p-7 transition-all">
            <div class="grid md:grid-cols-2 gap-5 sm:gap-8 items-center">
              <div>
                <div class="inline-block px-2.5 py-0.5 rounded-md bg-slate-800 text-emerald-400 text-[10px] font-black uppercase mb-2">
                  {{ channelsData[activeTab].badge }}
                </div>
                <h3 class="text-lg sm:text-2xl font-black text-white mb-2">{{ channelsData[activeTab].title }}</h3>
                <p class="text-xs sm:text-sm text-slate-300 leading-relaxed mb-4 font-medium">{{ channelsData[activeTab].desc }}</p>

                <!-- Bullets -->
                <div class="space-y-2 mb-5">
                  <div v-for="(bullet, bIdx) in channelsData[activeTab].bullets" :key="bIdx" class="flex items-start gap-2 text-xs text-slate-300 font-semibold">
                    <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5" />
                    <span>{{ bullet }}</span>
                  </div>
                </div>

                <router-link 
                  to="/register" 
                  class="inline-flex items-center gap-1.5 text-xs font-black text-emerald-400 hover:text-emerald-300 underline"
                >
                  <span>{{ t('landing.cta_channel_try') }}</span>
                  <ArrowLeft v-if="isRTL" class="w-3.5 h-3.5" />
                  <ArrowRight v-else class="w-3.5 h-3.5" />
                </router-link>
              </div>

              <!-- Channel Real Business Image -->
              <div class="relative rounded-2xl overflow-hidden border border-slate-800 shadow-xl group">
                <img 
                  :src="channelsData[activeTab].image" 
                  :alt="channelsData[activeTab].title" 
                  class="w-full h-48 sm:h-64 object-cover group-hover:scale-105 transition-transform duration-500"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-[#070b14] via-transparent to-transparent opacity-80"></div>
                <div class="absolute bottom-3 right-3 left-3 flex items-center justify-between text-xs bg-slate-950/80 backdrop-blur-md p-2 rounded-xl border border-white/10">
                  <span class="font-bold text-white text-[11px] truncate">{{ channelsData[activeTab].title }}</span>
                  <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 text-[9px] font-black">Online Live</span>
                </div>
              </div>
            </div>
          </div>

        </div>
      </section>

      <!-- INTERACTIVE LIVE SIMULATOR (Try it directly on page) -->
      <section id="interactive-demo" class="py-8 sm:py-14 bg-slate-900/50 border-y border-slate-800/80">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
          
          <div class="text-center max-w-2xl mx-auto mb-6">
            <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-[11px] font-black text-cyan-400 mb-2">
              {{ t('landing.demo_badge') }}
            </div>
            <h2 class="text-xl sm:text-3xl font-black text-white">{{ t('landing.demo_title') }}</h2>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">{{ t('landing.demo_subtitle') }}</p>
          </div>

          <!-- Phone Simulator Box -->
          <div class="max-w-md mx-auto bg-[#0c1322] border-2 border-slate-700/80 rounded-3xl overflow-hidden shadow-2xl">
            <!-- Simulated Phone Header -->
            <div class="bg-[#111a2e] p-3 flex items-center justify-between border-b border-slate-800">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-emerald-600 text-slate-950 flex items-center justify-center font-black text-xs">
                  <Bot class="w-4 h-4 text-white" />
                </div>
                <div>
                  <div class="flex items-center gap-1.5">
                    <span class="text-xs font-black text-white">{{ t('landing.demo_bot_name') }}</span>
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                  </div>
                  <span class="text-[10px] text-emerald-400 font-bold">{{ t('landing.demo_bot_status') }}</span>
                </div>
              </div>
              <span class="text-[10px] bg-emerald-950 text-emerald-400 border border-emerald-500/30 px-2 py-0.5 rounded-full font-extrabold">AI</span>
            </div>

            <!-- Chat Message Thread -->
            <div class="p-3.5 space-y-3 min-h-[220px] max-h-[280px] overflow-y-auto bg-[radial-gradient(#1e293b_1px,transparent_1px)] [background-size:16px_16px]">
              <div 
                v-for="msg in activeDemoList" 
                :key="msg.id" 
                class="flex flex-col"
                :class="msg.from === 'client' ? 'items-end' : 'items-start'"
              >
                <div 
                  class="max-w-[85%] p-2.5 rounded-2xl text-xs leading-relaxed font-medium shadow-xs"
                  :class="msg.from === 'client' ? 'bg-emerald-600 text-white rounded-br-none' : 'bg-slate-800 text-slate-100 rounded-bl-none border border-slate-700'"
                >
                  {{ msg.text }}
                  <div class="text-[9px] opacity-60 text-left mt-1 flex items-center justify-end gap-1">
                    <span>{{ msg.time }}</span>
                    <CheckCheck v-if="msg.from === 'client'" class="w-3 h-3 text-cyan-300" />
                  </div>
                </div>
              </div>

              <div v-if="isBotTyping" class="flex items-center gap-1.5 bg-slate-800 px-3 py-1.5 rounded-xl border border-slate-700 w-fit text-slate-300 text-[11px] font-bold">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-bounce"></span>
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-bounce [animation-delay:150ms]"></span>
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-bounce [animation-delay:300ms]"></span>
                <span class="mr-1">{{ t('landing.demo_typing') }}</span>
              </div>
            </div>

            <!-- Quick Action Chips -->
            <div class="px-3 pt-2 pb-1 bg-[#0f1728] border-t border-slate-800 flex items-center gap-1.5 overflow-x-auto text-[10px]">
              <button @click="sendDemoMessage(t('landing.demo_quick_1'))" class="whitespace-nowrap px-2.5 py-1 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 cursor-pointer">
                💰 {{ t('landing.demo_quick_1') }}
              </button>
              <button @click="sendDemoMessage(t('landing.demo_quick_2'))" class="whitespace-nowrap px-2.5 py-1 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 cursor-pointer">
                🛡️ {{ t('landing.demo_quick_2') }}
              </button>
            </div>

            <!-- Chat Input Box -->
            <div class="p-2.5 bg-[#0f1728] border-t border-slate-800 flex items-center gap-2">
              <input 
                v-model="demoInput" 
                @keydown.enter="sendDemoMessage()"
                type="text" 
                :placeholder="t('landing.demo_input_placeholder')" 
                class="flex-1 bg-slate-900 border border-slate-700 rounded-xl px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
              <button 
                @click="sendDemoMessage()" 
                class="w-8 h-8 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 flex items-center justify-center cursor-pointer transition-transform active:scale-90"
              >
                <Send class="w-4 h-4" />
              </button>
            </div>
          </div>

        </div>
      </section>

      <!-- DENSE FEATURES MATRIX (2 Columns Mobile / 4 Columns Desktop) -->
      <section id="features" class="py-8 sm:py-14">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
          
          <div class="text-center max-w-2xl mx-auto mb-6 sm:mb-10">
            <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-teal-500/10 border border-teal-500/20 text-[11px] font-black text-teal-400 mb-2">
              {{ t('landing.features_badge') }}
            </div>
            <h2 class="text-xl sm:text-3xl font-black text-white">{{ t('landing.features_title') }}</h2>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">{{ t('landing.features_subtitle') }}</p>
          </div>

          <!-- 8 Compact Dense Cards (2x4 on mobile, 4x2 on desktop) -->
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-4">
            
            <div class="p-3 sm:p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-emerald-500/50 transition-all group">
              <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-2.5 group-hover:scale-110 transition-transform">
                <Smartphone class="w-4 h-4" />
              </div>
              <h3 class="text-xs sm:text-sm font-black text-white mb-1">{{ t('landing.feat_1_title') }}</h3>
              <p class="text-[11px] text-slate-400 leading-relaxed font-medium">{{ t('landing.feat_1_desc') }}</p>
            </div>

            <div class="p-3 sm:p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-emerald-500/50 transition-all group">
              <div class="w-8 h-8 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center mb-2.5 group-hover:scale-110 transition-transform">
                <Megaphone class="w-4 h-4" />
              </div>
              <h3 class="text-xs sm:text-sm font-black text-white mb-1">{{ t('landing.feat_2_title') }}</h3>
              <p class="text-[11px] text-slate-400 leading-relaxed font-medium">{{ t('landing.feat_2_desc') }}</p>
            </div>

            <div class="p-3 sm:p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-emerald-500/50 transition-all group">
              <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-400 flex items-center justify-center mb-2.5 group-hover:scale-110 transition-transform">
                <Bot class="w-4 h-4" />
              </div>
              <h3 class="text-xs sm:text-sm font-black text-white mb-1">{{ t('landing.feat_3_title') }}</h3>
              <p class="text-[11px] text-slate-400 leading-relaxed font-medium">{{ t('landing.feat_3_desc') }}</p>
            </div>

            <div class="p-3 sm:p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-emerald-500/50 transition-all group">
              <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center mb-2.5 group-hover:scale-110 transition-transform">
                <ShieldCheck class="w-4 h-4" />
              </div>
              <h3 class="text-xs sm:text-sm font-black text-white mb-1">{{ t('landing.feat_4_title') }}</h3>
              <p class="text-[11px] text-slate-400 leading-relaxed font-medium">{{ t('landing.feat_4_desc') }}</p>
            </div>

            <div class="p-3 sm:p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-emerald-500/50 transition-all group">
              <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center mb-2.5 group-hover:scale-110 transition-transform">
                <Code2 class="w-4 h-4" />
              </div>
              <h3 class="text-xs sm:text-sm font-black text-white mb-1">{{ t('landing.feat_5_title') }}</h3>
              <p class="text-[11px] text-slate-400 leading-relaxed font-medium">{{ t('landing.feat_5_desc') }}</p>
            </div>

            <div class="p-3 sm:p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-emerald-500/50 transition-all group">
              <div class="w-8 h-8 rounded-lg bg-teal-500/10 text-teal-400 flex items-center justify-center mb-2.5 group-hover:scale-110 transition-transform">
                <Zap class="w-4 h-4" />
              </div>
              <h3 class="text-xs sm:text-sm font-black text-white mb-1">{{ t('landing.feat_6_title') }}</h3>
              <p class="text-[11px] text-slate-400 leading-relaxed font-medium">{{ t('landing.feat_6_desc') }}</p>
            </div>

            <div class="p-3 sm:p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-emerald-500/50 transition-all group">
              <div class="w-8 h-8 rounded-lg bg-rose-500/10 text-rose-400 flex items-center justify-center mb-2.5 group-hover:scale-110 transition-transform">
                <FileSpreadsheet class="w-4 h-4" />
              </div>
              <h3 class="text-xs sm:text-sm font-black text-white mb-1">{{ t('landing.feat_7_title') }}</h3>
              <p class="text-[11px] text-slate-400 leading-relaxed font-medium">{{ t('landing.feat_7_desc') }}</p>
            </div>

            <div class="p-3 sm:p-4 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-emerald-500/50 transition-all group">
              <div class="w-8 h-8 rounded-lg bg-indigo-500/10 text-indigo-400 flex items-center justify-center mb-2.5 group-hover:scale-110 transition-transform">
                <BarChart3 class="w-4 h-4" />
              </div>
              <h3 class="text-xs sm:text-sm font-black text-white mb-1">{{ t('landing.feat_8_title') }}</h3>
              <p class="text-[11px] text-slate-400 leading-relaxed font-medium">{{ t('landing.feat_8_desc') }}</p>
            </div>

          </div>

        </div>
      </section>

      <!-- USE CASES / WHO IS IT FOR -->
      <section class="py-8 sm:py-14 bg-slate-900/30 border-y border-slate-800/60">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
          
          <div class="text-center max-w-2xl mx-auto mb-6 sm:mb-8">
            <h2 class="text-xl sm:text-3xl font-black text-white">{{ t('landing.use_cases_title') }}</h2>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">{{ t('landing.use_cases_subtitle') }}</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-5">
            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800">
              <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-3">
                <ShoppingCart class="w-5 h-5" />
              </div>
              <h3 class="text-sm sm:text-base font-black text-white mb-1.5">{{ t('landing.case_1_title') }}</h3>
              <p class="text-xs text-slate-400 leading-relaxed font-medium">{{ t('landing.case_1_desc') }}</p>
            </div>

            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800">
              <div class="w-9 h-9 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center mb-3">
                <Building2 class="w-5 h-5" />
              </div>
              <h3 class="text-sm sm:text-base font-black text-white mb-1.5">{{ t('landing.case_2_title') }}</h3>
              <p class="text-xs text-slate-400 leading-relaxed font-medium">{{ t('landing.case_2_desc') }}</p>
            </div>

            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800">
              <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center mb-3">
                <Code2 class="w-5 h-5" />
              </div>
              <h3 class="text-sm sm:text-base font-black text-white mb-1.5">{{ t('landing.case_3_title') }}</h3>
              <p class="text-xs text-slate-400 leading-relaxed font-medium">{{ t('landing.case_3_desc') }}</p>
            </div>
          </div>

        </div>
      </section>

      <!-- PRICING SECTION -->
      <section id="pricing" class="py-8 sm:py-16">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
          
          <div class="text-center max-w-2xl mx-auto mb-5 sm:mb-8">
            <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[11px] font-black text-emerald-400 mb-2">
              {{ t('landing.pricing_badge') }}
            </div>
            <h2 class="text-xl sm:text-3xl font-black text-white">{{ t('landing.pricing_title') }}</h2>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">{{ t('landing.pricing_subtitle') }}</p>
          </div>

          <!-- Main Plan Category Filter TOGGLE (Quota vs Unlimited) -->
          <div class="flex items-center justify-center p-1 bg-slate-900/90 border border-slate-800 rounded-2xl max-w-md mx-auto mb-6 shadow-lg">
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
                <router-link
                  :to="'/register?plan=' + (cardBilling.starter === 'yearly' ? 'starter-yearly' : 'starter')"
                  class="w-full text-center py-1.5 sm:py-2.5 rounded-lg sm:rounded-xl text-[10px] sm:text-xs font-black transition-all bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 block cursor-pointer"
                >
                  {{ t('landing.subscribe') }}
                </router-link>
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
                <router-link
                  :to="'/register?plan=' + (cardBilling.pro === 'yearly' ? 'pro-yearly' : 'pro')"
                  class="w-full text-center py-1.5 sm:py-2.5 rounded-lg sm:rounded-xl text-[10px] sm:text-xs font-black transition-all bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 shadow-md shadow-emerald-500/20 active:scale-98 block cursor-pointer"
                >
                  {{ t('landing.subscribe') }}
                </router-link>
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
                    <div class="flex items-center gap-1 sm:gap-1.5">
                      <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-cyan-400 shrink-0" />
                      <span class="truncate">{{ locale === 'ar' ? 'WhatsApp + Telegram + Messenger' : 'Omnichannel Hub' }}</span>
                    </div>
                    <div class="flex items-center gap-1 sm:gap-1.5">
                      <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-cyan-400 shrink-0" />
                      <span class="truncate">{{ locale === 'ar' ? 'AI مكانك بدون سقف' : 'Uncapped AI Auto-Reply' }}</span>
                    </div>
                    <div class="flex items-center gap-1 sm:gap-1.5">
                      <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-cyan-400 shrink-0" />
                      <span class="truncate">{{ locale === 'ar' ? 'API و Webhooks كامل' : 'Full REST API & Webhooks' }}</span>
                    </div>
                    <div class="flex items-center gap-1 sm:gap-1.5">
                      <Check class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-cyan-400 shrink-0" />
                      <span class="truncate">{{ locale === 'ar' ? 'مدير حساب مخصص 24/7' : 'Account Manager 24/7' }}</span>
                    </div>
                  </div>
                </div>

                <!-- Action Button -->
                <router-link
                  :to="'/register?plan=' + (cardBilling.business === 'yearly' ? 'business-yearly' : 'business')"
                  class="w-full text-center py-1.5 sm:py-2.5 rounded-lg sm:rounded-xl text-[10px] sm:text-xs font-black transition-all bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-400 hover:to-blue-400 text-slate-950 shadow-md shadow-cyan-500/20 active:scale-98 block cursor-pointer"
                >
                  {{ t('landing.subscribe') }}
                </router-link>
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
                <router-link
                  :to="'/register?plan=' + (cardBilling.partners === 'yearly' ? 'partners-yearly' : 'partners')"
                  class="w-full text-center py-1.5 sm:py-2.5 rounded-lg sm:rounded-xl text-[10px] sm:text-xs font-black transition-all bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 block cursor-pointer"
                >
                  {{ t('landing.subscribe') }}
                </router-link>
              </div>

            </div>
          </div>

          <!-- FULL-WIDTH FREE PLAN CARD UNDERNEATH -->
          <div class="max-w-4xl mx-auto">
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

        </div>
      </section>

      <!-- FAQ SECTION (Compact Accordion) -->
      <section class="py-8 sm:py-14 bg-slate-900/40 border-t border-slate-800/80">
        <div class="max-w-3xl mx-auto px-3 sm:px-6">
          
          <div class="text-center mb-6 sm:mb-8">
            <h2 class="text-xl sm:text-3xl font-black text-white">{{ t('landing.faq_title') }}</h2>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">{{ t('landing.faq_subtitle') }}</p>
          </div>

          <div class="space-y-2.5">
            <div 
              v-for="(faq, fIndex) in [
                { q: t('landing.faq_1_q'), a: t('landing.faq_1_a') },
                { q: t('landing.faq_2_q'), a: t('landing.faq_2_a') },
                { q: t('landing.faq_3_q'), a: t('landing.faq_3_a') },
                { q: t('landing.faq_4_q'), a: t('landing.faq_4_a') },
                { q: t('landing.faq_5_q'), a: t('landing.faq_5_a') },
              ]" 
              :key="fIndex"
              class="rounded-xl border border-slate-800 bg-slate-900/80 overflow-hidden"
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
      </section>

      <!-- FINAL HIGH-CONVERTING CTA BANNER -->
      <section class="py-10 sm:py-16 relative overflow-hidden bg-gradient-to-b from-slate-950 to-[#070b14]">
        <div class="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8 text-center relative z-10">
          <div class="p-6 sm:p-12 rounded-3xl bg-gradient-to-r from-emerald-950/70 via-slate-900 to-teal-950/70 border border-emerald-500/30 shadow-2xl">
            <h2 class="text-xl sm:text-4xl font-black text-white mb-2 sm:mb-3">
              {{ t('landing.cta_banner_title') }}
            </h2>
            <p class="text-xs sm:text-base text-slate-300 max-w-xl mx-auto mb-5 sm:mb-8 font-medium">
              {{ t('landing.cta_banner_subtitle') }}
            </p>
            
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 max-w-sm mx-auto">
              <router-link 
                to="/register" 
                class="w-full sm:w-auto flex-1 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 px-6 py-3.5 rounded-xl font-black text-xs sm:text-sm transition-all shadow-lg shadow-emerald-500/25 active:scale-98 flex items-center justify-center gap-2"
              >
                <span>{{ t('landing.cta_banner_button') }}</span>
                <Rocket class="w-4 h-4" />
              </router-link>

              <a 
                href="https://wa.me/201024520482" 
                target="_blank" 
                class="w-full sm:w-auto bg-slate-900 hover:bg-slate-800 border border-slate-700 text-white px-5 py-3.5 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-2"
              >
                <Headphones class="w-4 h-4 text-emerald-400" />
                <span>{{ locale === 'ar' ? 'تحدث مع المبيعات' : 'Talk to Sales' }}</span>
              </a>
            </div>

            <div class="mt-4 text-[11px] text-slate-400 flex flex-wrap items-center justify-center gap-3">
              <span>✓ {{ locale === 'ar' ? 'إعداد فوري في دقيقتين' : '2-Min Instant Setup' }}</span>
              <span>✓ {{ locale === 'ar' ? 'لا حاجة لبطاقة ائتمان' : 'No Credit Card Required' }}</span>
              <span>✓ {{ locale === 'ar' ? 'دعم فني مباشر 24/7' : '24/7 Live Support' }}</span>
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
              <li><a href="#channels" class="hover:text-emerald-400 transition-colors">{{ t('landing.nav_channels') }}</a></li>
              <li><a href="#features" class="hover:text-emerald-400 transition-colors">{{ t('landing.nav_features') }}</a></li>
              <li><a href="#pricing" class="hover:text-emerald-400 transition-colors">{{ t('landing.nav_pricing') }}</a></li>
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
            <h4 class="text-xs font-black text-white mb-2.5">{{ locale === 'ar' ? 'دعم النظام' : 'System Support' }}</h4>
            <div class="p-2.5 rounded-xl bg-slate-900 border border-slate-800">
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
