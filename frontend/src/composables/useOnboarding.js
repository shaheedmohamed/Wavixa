import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import api from '@/services/api';
import auth from '@/services/auth';
import { locale, isRTL } from '@/i18n';

const TOUR_STORAGE_KEY = 'wavixa_onboarding_completed';
const TOUR_STEP_KEY = 'wavixa_onboarding_step';

const onboarding = ref(null);
const loading = ref(true);
let driverObj = null;

const TEXTS = {
  ar: {
    next: 'التالي',
    prev: 'السابق',
    done: 'إنهاء الجولة',
    close: 'تخطي الجولة',
    skip: 'تخطي',
    welcomeTitle: 'أهلاً بك في Wavixa',
    welcomeDesc: 'خلينا نأخذ جولة سريعة ونعرّفك على أهم أجزاء المنصة.',
    welcomeStart: 'ابدأ الجولة',
    welcomeSkip: 'تخطي',
    welcomeDontShow: 'لا تظهر مرة أخرى',
    stepProgress: 'خطوة {{current}} من {{total}}',
  },
  en: {
    next: 'Next',
    prev: 'Previous',
    done: 'Finish Tour',
    close: 'Skip Tour',
    skip: 'Skip',
    welcomeTitle: 'Welcome to Wavixa',
    welcomeDesc: 'Let us take a quick tour and show you the most important parts of the platform.',
    welcomeStart: 'Start Tour',
    welcomeSkip: 'Skip',
    welcomeDontShow: 'Do not show again',
    stepProgress: 'Step {{current}} of {{total}}',
  },
};

const MAIN_STEPS = {
  ar: [
    { element: '[data-tour="sidebar-brand"]', title: 'Wavixa بوابة واتساب', description: 'ده الـSidebar الرئيسي. من هنا تقدر تتنقل بين كل أقسام المنصة بسهولة.', preferredSide: 'right', isSidebar: true },
    { element: '[data-tour="nav-section-main"]', title: 'نظرة عامة', description: 'تحت قسم "نظرة عامة" هتلاقي الصفحات الأساسية: الرئيسية، السجلات، وجهات الاتصال.', preferredSide: 'right', isSidebar: true },
    { element: '[data-tour="/dashboard"]', title: 'نظرة عامة', description: 'دي الصفحة الرئيسية للوحة التحكم، ومنها تقدر تتابع أهم الإحصائيات وحالة حسابك واستخدامك للخدمة.', preferredSide: 'right', isSidebar: true },
    { element: '[data-tour="/messages"]', title: 'السجلات والرسائل', description: 'هنا تقدر تتابع الرسائل التي تم إرسالها وحالاتها وتفاصيل عمليات الإرسال.', preferredSide: 'right', isSidebar: true },
    { element: '[data-tour="/contacts"]', title: 'جهات الاتصال', description: 'هنا تقدر إدارة جهات الاتصال والأرقام التي تتعامل معها داخل المنصة.', preferredSide: 'right', isSidebar: true },
    { element: '[data-tour="nav-section-whatsapp"]', title: 'خدمات WhatsApp', description: 'من هنا هتلاقي كل الأدوات الخاصة باستخدام WhatsApp داخل Wavixa.', preferredSide: 'right', isSidebar: true },
    { element: '[data-tour="/devices"]', title: 'الأجهزة والجلسات', description: 'من هنا تقدر تربط أجهزة WhatsApp الخاصة بك عن طريق QR Code وتتابع حالة كل جهاز.', preferredSide: 'right', isSidebar: true },
    { element: '[data-tour="/send-message"]', title: 'إرسال رسالة', description: 'عايز تبعت رسالة بسرعة؟ من هنا تقدر تختار جهاز WhatsApp وتكتب الرسالة وتقوم بإرسالها مباشرة.', preferredSide: 'right', isSidebar: true },
    { element: '[data-tour="/auto-reply"]', title: 'AI مكانك', description: 'هنا تقدر تفعيل الرد الذكي باستخدام AI، بحيث يفهم رسائل العملاء ويرد عليهم تلقائيًا.', preferredSide: 'right', isSidebar: true },
    { element: '[data-tour="nav-section-developers"]', title: 'المطورين والربط', description: 'لو أنت مطور، من هنا هتلاقي الأدوات اللي تساعدك على ربط Wavixa بموقعك أو نظامك.', preferredSide: 'right', isSidebar: true },
    { element: '[data-tour="/api-keys"]', title: 'مفاتيح API', description: 'من هنا تحصل على API Keys الخاصة بحسابك لاستخدام Wavixa داخل الأنظمة والتطبيقات.', preferredSide: 'right', isSidebar: true },
    { element: '[data-tour="/webhooks"]', title: 'الويب هوكس', description: 'استخدم Webhooks لاستقبال الأحداث والبيانات من Wavixa داخل نظامك.', preferredSide: 'right', isSidebar: true },
    { element: '[data-tour="nav-section-account"]', title: 'الحساب', description: 'من هنا تقدر تدير اشتراكك وفوترتك وإعدادات حسابك.', preferredSide: 'right', isSidebar: true },
    { element: '[data-tour="/subscription"]', title: 'الاشتراك والفوترة', description: 'من هنا تقدر تتابع باقتك الحالية، الرسائل المتاحة، الاشتراك والمدفوعات.', preferredSide: 'right', isSidebar: true },
    { element: '[data-tour="/settings"]', title: 'الإعدادات', description: 'هنا تقدر إدارة بيانات حسابك وإعدادات المنصة.', preferredSide: 'right', isSidebar: true },
    { element: '[data-tour="sidebar-plan-card"]', title: 'الباقة والرصيد', description: 'من هنا تقدر تشوف باقتك الحالية وتتابع عدد الرسائل المتاحة لك وتختار الباقة المناسبة لاحتياجاتك.', preferredSide: 'right', isSidebar: true },
    { element: '[data-tour="search"]', title: 'البحث السريع', description: 'تقدر تستخدم البحث السريع للوصول لأي جزء داخل المنصة.', preferredSide: 'bottom' },
    { element: '[data-tour="language"]', title: 'تغيير اللغة', description: 'تقدر تغيير لغة المنصة في أي وقت.', preferredSide: 'bottom' },
    { element: '[data-tour="help"]', title: 'مساعدة', description: 'لو محتاج مساعدة، تقدر التواصل معنا مباشرة.', preferredSide: 'bottom' },
    { element: '[data-tour="api-docs"]', title: 'دليل API', description: 'لو أنت مطور، من هنا تقدر الوصول إلى دليل API.', preferredSide: 'bottom' },
    { element: '[data-tour="new-message"]', title: 'رسالة جديدة', description: 'استخدم الزر ده للوصول السريع إلى إرسال رسالة جديدة.', preferredSide: 'bottom' },
    { element: '[data-tour="dashboard-header"]', title: 'لوحة التحكم', description: 'ده ملخص حسابك اليومي. من هنا تقدر تشوف أدائك وحالة رسائلك بشكل سريع.', preferredSide: 'bottom' },
    { element: '[data-tour="dashboard-stats-grid"]', title: 'إجمالي الرسائل والإحصائيات', description: 'هنا تتابع أهم الأرقام: إجمالي الرسائل، الرسائل المستلمة، المقروءة، وجهات الاتصال الجديدة.', preferredSide: 'top' },
    { element: '[data-tour="dashboard-chart"]', title: 'أداء الرسائل', description: 'الرسم البياني ده بيوريك أداء الرسائل المرسلة والمستلمة والمقروءة خلال آخر 7 أيام.', preferredSide: 'top' },
    { element: '[data-tour="dashboard-devices-card"]', title: 'الأجهزة المتصلة', description: 'من هنا تشوف الأجهزة المتصلة حالياً وحالة كل جهاز وإحصائيات الإرسال.', preferredSide: 'left' },
    { element: '[data-tour="dashboard-message-types"]', title: 'أنواع الرسائل', description: 'هنا تعرف توزيع الرسائل المرسلة حسب النوع، سواء نصية أو وسائط أو روابط.', preferredSide: 'top' },
    { element: '[data-tour="dashboard-activity"]', title: 'آخر النشاطات', description: 'هنا تتابع آخر الحركات على حسابك من إرسال واستقبال واتصال أجهزة.', preferredSide: 'top' },
    { element: '[data-tour="dashboard-heatmap"]', title: 'خريطة النشاط', description: 'الـHeatmap بيوّرلك توقيتات النشاط الأعلى على مدار الأسبوع.', preferredSide: 'top' },
  ],
  en: [
    { element: '[data-tour="sidebar-brand"]', title: 'Wavixa WhatsApp Gateway', description: 'This is the main sidebar. From here you can navigate between all platform sections easily.', preferredSide: 'left', isSidebar: true },
    { element: '[data-tour="nav-section-main"]', title: 'Overview', description: 'Under "Overview" you will find the main pages: Home, Logs, and Contacts.', preferredSide: 'left', isSidebar: true },
    { element: '[data-tour="/dashboard"]', title: 'Overview', description: 'This is the main dashboard page where you can track key statistics, your account status, and service usage.', preferredSide: 'left', isSidebar: true },
    { element: '[data-tour="/messages"]', title: 'Logs & Messages', description: 'Here you can track sent messages, their statuses, and sending details.', preferredSide: 'left', isSidebar: true },
    { element: '[data-tour="/contacts"]', title: 'Contacts', description: 'Manage the contacts and numbers you interact with on the platform.', preferredSide: 'left', isSidebar: true },
    { element: '[data-tour="nav-section-whatsapp"]', title: 'WhatsApp Services', description: 'Here you will find all the tools for using WhatsApp inside Wavixa.', preferredSide: 'left', isSidebar: true },
    { element: '[data-tour="/devices"]', title: 'Devices & Sessions', description: 'Connect your WhatsApp devices using a QR code and monitor each device status.', preferredSide: 'left', isSidebar: true },
    { element: '[data-tour="/send-message"]', title: 'Send Message', description: 'Want to send a message quickly? Choose a WhatsApp device, type the message, and send it directly.', preferredSide: 'left', isSidebar: true },
    { element: '[data-tour="/auto-reply"]', title: 'AI Auto Reply', description: 'Enable the AI auto-reply here so it can understand customer messages and respond automatically.', preferredSide: 'left', isSidebar: true },
    { element: '[data-tour="nav-section-developers"]', title: 'Developers & Integrations', description: 'If you are a developer, here are the tools to connect Wavixa to your website or system.', preferredSide: 'left', isSidebar: true },
    { element: '[data-tour="/api-keys"]', title: 'API Keys', description: 'Get your account API keys to use Wavixa inside your own systems and apps.', preferredSide: 'left', isSidebar: true },
    { element: '[data-tour="/webhooks"]', title: 'Webhooks', description: 'Use Webhooks to receive events and data from Wavixa inside your own system.', preferredSide: 'left', isSidebar: true },
    { element: '[data-tour="nav-section-account"]', title: 'Account', description: 'From here you can manage your subscription, billing, and account settings.', preferredSide: 'left', isSidebar: true },
    { element: '[data-tour="/subscription"]', title: 'Subscription & Billing', description: 'Track your current plan, available messages, subscription, and payments here.', preferredSide: 'left', isSidebar: true },
    { element: '[data-tour="/settings"]', title: 'Settings', description: 'Manage your account details and platform settings here.', preferredSide: 'left', isSidebar: true },
    { element: '[data-tour="sidebar-plan-card"]', title: 'Plan & Balance', description: 'From here you can see your current plan, track your available messages, and choose the plan that fits your needs.', preferredSide: 'left', isSidebar: true },
    { element: '[data-tour="search"]', title: 'Quick Search', description: 'Use the quick search to reach any part of the platform.', preferredSide: 'bottom' },
    { element: '[data-tour="language"]', title: 'Change Language', description: 'You can change the platform language at any time.', preferredSide: 'bottom' },
    { element: '[data-tour="help"]', title: 'Help', description: 'If you need help, you can contact us directly.', preferredSide: 'bottom' },
    { element: '[data-tour="api-docs"]', title: 'API Guide', description: 'If you are a developer, access the API guide here.', preferredSide: 'bottom' },
    { element: '[data-tour="new-message"]', title: 'New Message', description: 'Use this button for quick access to sending a new message.', preferredSide: 'bottom' },
    { element: '[data-tour="dashboard-header"]', title: 'Dashboard', description: 'This is your daily account summary. From here you can quickly see your performance and message status.', preferredSide: 'bottom' },
    { element: '[data-tour="dashboard-stats-grid"]', title: 'Messages & Statistics', description: 'Here you track the most important numbers: total messages, delivered, read, and new contacts.', preferredSide: 'top' },
    { element: '[data-tour="dashboard-chart"]', title: 'Message Performance', description: 'This chart shows the performance of sent, delivered, and read messages over the last 7 days.', preferredSide: 'top' },
    { element: '[data-tour="dashboard-devices-card"]', title: 'Connected Devices', description: 'From here you see currently connected devices and each device status and sending statistics.', preferredSide: 'right' },
    { element: '[data-tour="dashboard-message-types"]', title: 'Message Types', description: 'Here you know the distribution of sent messages by type, whether text, media, or links.', preferredSide: 'top' },
    { element: '[data-tour="dashboard-activity"]', title: 'Recent Activity', description: 'Here you track the latest actions on your account including sending, receiving, and device connections.', preferredSide: 'top' },
    { element: '[data-tour="dashboard-heatmap"]', title: 'Activity Heatmap', description: 'The heatmap shows you the peak activity timings throughout the week.', preferredSide: 'top' },
  ],
};

function getLang() {
  return locale.value === 'ar' ? 'ar' : 'en';
}

function getSide(preferred, selector) {
  try {
    const el = typeof selector === 'string' ? document.querySelector(selector) : null;
    if (!el || typeof window === 'undefined') return preferred;
    const rect = el.getBoundingClientRect();
    const spaceRight = window.innerWidth - rect.right;
    const spaceLeft = rect.left;
    const spaceTop = rect.top;
    const spaceBottom = window.innerHeight - rect.bottom;
    const minSpace = 240;

    if (preferred === 'top') return spaceTop >= minSpace ? 'top' : 'bottom';
    if (preferred === 'bottom') return spaceBottom >= minSpace ? 'bottom' : 'top';

    if (isRTL.value) {
      return spaceLeft >= minSpace ? 'left' : 'right';
    }
    return spaceRight >= minSpace ? 'right' : 'left';
  } catch (e) {
    return preferred;
  }
}

function getSteps() {
  const lang = getLang();
  const steps = MAIN_STEPS[lang];
  return steps.map((step) => ({
    element: step.element,
    data: { isSidebar: step.isSidebar, preferredSide: step.preferredSide },
    skipMissingElement: step.skipMissingElement ?? true,
    waitForElement: step.waitForElement ?? 500,
    popover: {
      title: step.title,
      description: step.description,
      side: getSide(step.preferredSide, step.element),
      align: 'start',
      showButtons: ['next', 'previous', 'close'],
    },
  }));
}

function emitSidebar(open) {
  if (typeof window === 'undefined') return;
  window.dispatchEvent(new CustomEvent('wavixa:tour-sidebar', { detail: { open } }));
}

function isSidebarStep(step) {
  return step?.data?.isSidebar;
}

async function loadStatus() {
  try {
    const user = auth.getUser();
    if (!user) {
      loading.value = false;
      return;
    }
    const localFlag = localStorage.getItem(TOUR_STORAGE_KEY);
    const localStep = localStorage.getItem(TOUR_STEP_KEY);
    const response = await api.get('/auth/onboarding');
    if (response.success && response.data) {
      onboarding.value = {
        completed: response.data.completed,
        step: response.data.step,
        completedAt: response.data.completed_at,
      };
    } else if (localFlag === 'true' || localFlag === '1') {
      onboarding.value = { completed: true, step: localStep || 'finished', completedAt: null };
    } else {
      onboarding.value = { completed: false, step: localStep || null, completedAt: null };
    }
  } catch (err) {
    const localFlag = localStorage.getItem(TOUR_STORAGE_KEY);
    const localStep = localStorage.getItem(TOUR_STEP_KEY);
    onboarding.value = { completed: localFlag === 'true' || localFlag === '1', step: localStep || null, completedAt: null };
  } finally {
    loading.value = false;
  }
}

async function updateStatus(payload) {
  try {
    const response = await api.put('/auth/onboarding', payload);
    if (response.success && response.data) {
      onboarding.value = {
        completed: response.data.completed,
        step: response.data.step,
        completedAt: null,
      };
    }
  } catch (err) {
    console.error('Failed to update onboarding status:', err);
  }
}

function saveProgress(stepIndex, completed = false) {
  localStorage.setItem(TOUR_STEP_KEY, String(stepIndex));
  if (completed) {
    localStorage.setItem(TOUR_STORAGE_KEY, 'true');
  }
  updateStatus({ completed, step: String(stepIndex) });
}

function destroyDriver() {
  if (driverObj) {
    driverObj.destroy();
    driverObj = null;
  }
  emitSidebar(false);
}

function buildDriver(startIndex = 0) {
  const lang = getLang();
  const texts = TEXTS[lang];

  driverObj = driver({
    animate: true,
    duration: 320,
    overlayColor: '#0f172a',
    overlayOpacity: 0.68,
    smoothScroll: true,
    allowScroll: true,
    stagePadding: 8,
    stageRadius: 16,
    allowClose: true,
    overlayClickBehavior: 'close',
    allowKeyboardControl: true,
    popoverClass: 'wavixa-onboarding-popover',
    popoverOffset: 14,
    showProgress: true,
    progressText: texts.stepProgress,
    nextBtnText: texts.next,
    prevBtnText: texts.prev,
    doneBtnText: texts.done,
    closeBtnText: texts.close,
    showButtons: ['next', 'previous', 'close'],
    disableActiveInteraction: false,
    skipMissingElement: true,
    waitForElement: 500,
    onHighlightStarted: (element, step) => {
      if (isSidebarStep(step)) {
        emitSidebar(true);
      } else {
        emitSidebar(false);
      }
    },
    onPopoverRender: (popover) => {
      if (popover?.wrapper) {
        popover.wrapper.setAttribute('dir', isRTL.value ? 'rtl' : 'ltr');
        popover.wrapper.classList.add('wavixa-onboarding-popover');
      }
    },
    onNextClick: (element, step, opts) => {
      const index = opts?.state?.activeIndex ?? 0;
      const steps = getSteps();
      if (index >= steps.length - 1) {
        saveProgress(index, true);
        emitSidebar(false);
        driverObj?.destroy();
      } else {
        saveProgress(index);
        driverObj?.moveNext();
      }
    },
    onPrevClick: (element, step, opts) => {
      const index = opts?.state?.activeIndex ?? 0;
      saveProgress(index);
      driverObj?.movePrevious();
    },
    onCloseClick: (element, step, opts) => {
      const index = opts?.state?.activeIndex ?? 0;
      saveProgress(index);
      emitSidebar(false);
      driverObj?.destroy();
    },
    onDoneClick: (element, step, opts) => {
      const index = opts?.state?.activeIndex ?? 0;
      saveProgress(index, true);
      emitSidebar(false);
      driverObj?.destroy();
    },
    onDestroyed: () => {
      emitSidebar(false);
      driverObj = null;
    },
    steps: getSteps(),
  });

  driverObj.drive(startIndex);
}

function startTour() {
  const saved = onboarding.value?.step;
  const startIndex = saved && /^\d+$/.test(saved) ? parseInt(saved, 10) : 0;
  buildDriver(startIndex);
}

async function endTour() {
  destroyDriver();
  localStorage.setItem(TOUR_STORAGE_KEY, 'true');
  localStorage.removeItem(TOUR_STEP_KEY);
  await updateStatus({ completed: true, step: 'finished' });
  onboarding.value = { completed: true, step: 'finished', completedAt: null };
}

export { TEXTS };

export function useOnboarding() {
  const router = useRouter();

  const welcomeVisible = computed(() => !loading.value && !onboarding.value?.completed);

  const start = async () => {
    startTour();
  };

  const skip = async (dontShowAgain = true) => {
    destroyDriver();
    if (dontShowAgain) {
      localStorage.setItem(TOUR_STORAGE_KEY, 'true');
      localStorage.removeItem(TOUR_STEP_KEY);
      await updateStatus({ completed: true, step: 'skipped' });
      onboarding.value = { completed: true, step: 'skipped', completedAt: null };
    } else {
      localStorage.setItem(TOUR_STEP_KEY, '0');
      await updateStatus({ completed: false, step: '0' });
      onboarding.value = { completed: false, step: '0', completedAt: null };
    }
  };

  const restart = async () => {
    localStorage.removeItem(TOUR_STORAGE_KEY);
    localStorage.removeItem(TOUR_STEP_KEY);
    await updateStatus({ completed: false, step: null });
    onboarding.value = { completed: false, step: null, completedAt: null };
    startTour();
  };

  const load = () => {
    loadStatus();
  };

  return {
    loading,
    welcomeVisible,
    start,
    skip,
    restart,
    load,
    TEXTS,
  };
}
