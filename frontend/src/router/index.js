import { createRouter, createWebHistory } from 'vue-router';
import api from '@/services/api';
import auth from '@/services/auth';

// Pages
import Landing from '@/pages/Landing.vue';
import Login from '@/pages/Login.vue';
import Register from '@/pages/Register.vue';
import ForgotPassword from '@/pages/ForgotPassword.vue';
import VerifyEmail from '@/pages/VerifyEmail.vue';
import Dashboard from '@/pages/Dashboard.vue';
import WhatsAppDashboard from '@/pages/WhatsAppDashboard.vue';
import WhatsAppConnect from '@/pages/WhatsAppConnect.vue';
import SendMessage from '@/pages/SendMessage.vue';
import Campaigns from '@/pages/Campaigns.vue';
import CampaignCreate from '@/pages/CampaignCreate.vue';
import CampaignShow from '@/pages/CampaignShow.vue';
import AutoReply from '@/pages/AutoReply.vue';
import Devices from '@/pages/Devices.vue';
import Messages from '@/pages/Messages.vue';
import Contacts from '@/pages/Contacts.vue';
import ApiKeys from '@/pages/ApiKeys.vue';
import ApiDocs from '@/pages/ApiDocs.vue';
import Webhooks from '@/pages/Webhooks.vue';
import N8n from '@/pages/N8n.vue';
import N8nDocs from '@/pages/N8nDocs.vue';
import Pricing from '@/pages/Pricing.vue';
import PrivacyPolicy from '@/pages/PrivacyPolicy.vue';
import Subscription from '@/pages/Subscription.vue';
import Settings from '@/pages/Settings.vue';

// Telegram Pages
import TelegramDevices from '@/pages/telegram/TelegramDevices.vue';
import TelegramConnect from '@/pages/telegram/TelegramConnect.vue';
import TelegramSendMessage from '@/pages/telegram/TelegramSendMessage.vue';
import TelegramAutoReply from '@/pages/telegram/TelegramAutoReply.vue';
import TelegramCampaigns from '@/pages/telegram/TelegramCampaigns.vue';
import TelegramCampaignCreate from '@/pages/telegram/TelegramCampaignCreate.vue';
import TelegramCampaignShow from '@/pages/telegram/TelegramCampaignShow.vue';

// Messenger Pages
import MessengerDashboard from '@/pages/messenger/MessengerDashboard.vue';
import MessengerConnect from '@/pages/messenger/MessengerConnect.vue';
import MessengerInbox from '@/pages/messenger/MessengerInbox.vue';
import MessengerContacts from '@/pages/messenger/MessengerContacts.vue';
import MessengerAutoReply from '@/pages/messenger/MessengerAutoReply.vue';
import MessengerCampaigns from '@/pages/messenger/MessengerCampaigns.vue';
import MessengerCampaignCreate from '@/pages/messenger/MessengerCampaignCreate.vue';
import MessengerCampaignShow from '@/pages/messenger/MessengerCampaignShow.vue';
import MessengerSettings from '@/pages/messenger/MessengerSettings.vue';
import MessengerAds from '@/pages/messenger/MessengerAds.vue';

// Admin Pages
import AdminDashboard from '@/pages/admin/AdminDashboard.vue';
import AdminUsers from '@/pages/admin/AdminUsers.vue';
import AdminUserDetail from '@/pages/admin/AdminUserDetail.vue';
import AdminSessions from '@/pages/admin/AdminSessions.vue';
import AdminApiKeys from '@/pages/admin/AdminApiKeys.vue';
import AdminMessages from '@/pages/admin/AdminMessages.vue';
import AdminSubscriptions from '@/pages/admin/AdminSubscriptions.vue';
import AdminPlans from '@/pages/admin/AdminPlans.vue';
import AdminWebhooks from '@/pages/admin/AdminWebhooks.vue';
import AdminQueue from '@/pages/admin/AdminQueue.vue';
import AdminDevices from '@/pages/admin/AdminDevices.vue';
import AdminSystemStats from '@/pages/admin/AdminSystemStats.vue';
import AdminAnalytics from '@/pages/admin/AdminAnalytics.vue';
import AdminActivityLogs from '@/pages/admin/AdminActivityLogs.vue';
import AdminAuditLogs from '@/pages/admin/AdminAuditLogs.vue';
import AdminApiMonitoring from '@/pages/admin/AdminApiMonitoring.vue';
import AdminLiveEvents from '@/pages/admin/AdminLiveEvents.vue';
import AdminSecurityCenter from '@/pages/admin/AdminSecurityCenter.vue';
import AdminPayments from '@/pages/admin/AdminPayments.vue';
import AdminSettings from '@/pages/admin/AdminSettings.vue';
import AdminNotifications from '@/pages/admin/AdminNotifications.vue';
import AdminMetaAds from '@/pages/admin/AdminMetaAds.vue';
import AdminTelegramSessions from '@/pages/admin/AdminTelegramSessions.vue';
import AdminTelegramMessages from '@/pages/admin/AdminTelegramMessages.vue';
import AdminTelegramCampaigns from '@/pages/admin/AdminTelegramCampaigns.vue';

// Meta Official Test pages
import MetaTest from '@/pages/meta-test/MetaTest.vue';
import MetaTestDashboard from '@/pages/meta-test/MetaTestDashboard.vue';
import MetaTestSetup from '@/pages/meta-test/MetaTestSetup.vue';
import MetaTestConnect from '@/pages/meta-test/MetaTestConnect.vue';
import MetaTestAccounts from '@/pages/meta-test/MetaTestAccounts.vue';
import MetaTestPhoneNumbers from '@/pages/meta-test/MetaTestPhoneNumbers.vue';
import MetaTestMessages from '@/pages/meta-test/MetaTestMessages.vue';
import MetaTestChats from '@/pages/meta-test/MetaTestChats.vue';
import MetaTestTemplates from '@/pages/meta-test/MetaTestTemplates.vue';
import MetaTestCampaigns from '@/pages/meta-test/MetaTestCampaigns.vue';
import MetaTestCampaignCreate from '@/pages/meta-test/MetaTestCampaignCreate.vue';
import MetaTestCampaignShow from '@/pages/meta-test/MetaTestCampaignShow.vue';
import MetaTestLogs from '@/pages/meta-test/MetaTestLogs.vue';
import MetaTestStatus from '@/pages/meta-test/MetaTestStatus.vue';
import MetaTestReport from '@/pages/meta-test/MetaTestReport.vue';

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: Login,
    meta: { requiresAuth: false, layout: 'auth' },
  },
  {
    path: '/register',
    name: 'Register',
    component: Register,
    meta: { requiresAuth: false, layout: 'auth' },
  },
  {
    path: '/forgot-password',
    name: 'ForgotPassword',
    component: ForgotPassword,
    meta: { requiresAuth: false, layout: 'auth' },
  },
  {
    path: '/verify-email',
    name: 'VerifyEmail',
    component: VerifyEmail,
    meta: { requiresAuth: false, layout: 'auth' },
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: Dashboard,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/whatsapp-dashboard',
    name: 'WhatsAppDashboard',
    component: WhatsAppDashboard,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/whatsapp-connect',
    name: 'WhatsAppConnect',
    component: WhatsAppConnect,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/send-message',
    name: 'SendMessage',
    component: SendMessage,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/campaigns',
    name: 'Campaigns',
    component: Campaigns,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/campaigns/new',
    name: 'CampaignCreate',
    component: CampaignCreate,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/campaigns/:id',
    name: 'CampaignShow',
    component: CampaignShow,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/auto-reply',
    name: 'AutoReply',
    component: AutoReply,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/devices',
    name: 'Devices',
    component: Devices,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/messages',
    name: 'Messages',
    component: Messages,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/contacts',
    name: 'Contacts',
    component: Contacts,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/api-keys',
    name: 'ApiKeys',
    component: ApiKeys,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/api-docs',
    name: 'ApiDocs',
    component: ApiDocs,
    meta: { requiresAuth: false, layout: 'default' },
  },
  {
    path: '/webhooks',
    name: 'Webhooks',
    component: Webhooks,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/n8n',
    name: 'N8n',
    component: N8n,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/n8n-docs',
    name: 'N8nDocs',
    component: N8nDocs,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/pricing',
    name: 'Pricing',
    component: Pricing,
    meta: { requiresAuth: false, layout: 'default' },
  },
  {
    path: '/privacy',
    name: 'PrivacyPolicy',
    component: PrivacyPolicy,
    meta: { requiresAuth: false, layout: 'default' },
  },
  {
    path: '/privacy-policy',
    redirect: '/privacy',
  },
  {
    path: '/subscription',
    name: 'Subscription',
    component: Subscription,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/settings',
    name: 'Settings',
    component: Settings,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  // Telegram Client Routes
  {
    path: '/telegram/devices',
    name: 'TelegramDevices',
    component: TelegramDevices,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/telegram/connect',
    name: 'TelegramConnect',
    component: TelegramConnect,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/telegram-connect',
    redirect: '/telegram/connect',
  },
  {
    path: '/telegram/send-message',
    name: 'TelegramSendMessage',
    component: TelegramSendMessage,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/telegram/auto-reply',
    name: 'TelegramAutoReply',
    component: TelegramAutoReply,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/telegram/campaigns',
    name: 'TelegramCampaigns',
    component: TelegramCampaigns,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/telegram/campaigns/new',
    name: 'TelegramCampaignCreate',
    component: TelegramCampaignCreate,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/telegram/campaigns/:id',
    name: 'TelegramCampaignShow',
    component: TelegramCampaignShow,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  // Messenger routes
  {
    path: '/messenger',
    name: 'MessengerDashboard',
    component: MessengerDashboard,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/messenger/connect',
    name: 'MessengerConnect',
    component: MessengerConnect,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/messenger/inbox',
    name: 'MessengerInbox',
    component: MessengerInbox,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/messenger/contacts',
    name: 'MessengerContacts',
    component: MessengerContacts,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/messenger/auto-reply',
    name: 'MessengerAutoReply',
    component: MessengerAutoReply,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/messenger/campaigns',
    name: 'MessengerCampaigns',
    component: MessengerCampaigns,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/messenger/campaigns/new',
    name: 'MessengerCampaignCreate',
    component: MessengerCampaignCreate,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/messenger/campaigns/:id',
    name: 'MessengerCampaignShow',
    component: MessengerCampaignShow,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/messenger/settings',
    name: 'MessengerSettings',
    component: MessengerSettings,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  {
    path: '/messenger/ads',
    name: 'MessengerAds',
    component: MessengerAds,
    meta: { requiresAuth: true, layout: 'dashboard' },
  },
  // Admin routes
  {
    path: '/admin',
    name: 'AdminDashboard',
    component: AdminDashboard,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/users',
    name: 'AdminUsers',
    component: AdminUsers,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/users/:id',
    name: 'AdminUserDetail',
    component: AdminUserDetail,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/sessions',
    name: 'AdminSessions',
    component: AdminSessions,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/api-keys',
    name: 'AdminApiKeys',
    component: AdminApiKeys,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/messages',
    name: 'AdminMessages',
    component: AdminMessages,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/subscriptions',
    name: 'AdminSubscriptions',
    component: AdminSubscriptions,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/plans',
    name: 'AdminPlans',
    component: AdminPlans,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/webhooks',
    name: 'AdminWebhooks',
    component: AdminWebhooks,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/queue',
    name: 'AdminQueue',
    component: AdminQueue,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/devices',
    name: 'AdminDevices',
    component: AdminDevices,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/api-monitoring',
    name: 'AdminApiMonitoring',
    component: AdminApiMonitoring,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/live-events',
    name: 'AdminLiveEvents',
    component: AdminLiveEvents,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/security',
    name: 'AdminSecurityCenter',
    component: AdminSecurityCenter,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/payments',
    name: 'AdminPayments',
    component: AdminPayments,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/system-stats',
    name: 'AdminSystemStats',
    component: AdminSystemStats,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/analytics',
    name: 'AdminAnalytics',
    component: AdminAnalytics,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/activity-logs',
    name: 'AdminActivityLogs',
    component: AdminActivityLogs,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/audit-logs',
    name: 'AdminAuditLogs',
    component: AdminAuditLogs,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/settings',
    name: 'AdminSettings',
    component: AdminSettings,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/meta-ads',
    name: 'AdminMetaAds',
    component: AdminMetaAds,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/notifications',
    name: 'AdminNotifications',
    component: AdminNotifications,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/telegram-sessions',
    name: 'AdminTelegramSessions',
    component: AdminTelegramSessions,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/telegram-messages',
    name: 'AdminTelegramMessages',
    component: AdminTelegramMessages,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  {
    path: '/admin/telegram-campaigns',
    name: 'AdminTelegramCampaigns',
    component: AdminTelegramCampaigns,
    meta: { requiresAuth: true, requiresAdmin: true, layout: 'dashboard' },
  },
  // Meta Official WhatsApp (Cloud API) routes — live product
  {
    path: '/meta-test',
    redirect: '/meta/dashboard',
  },
  {
    path: '/meta-test/:pathMatch(.*)*',
    redirect: (to) => ({
      path: `/meta/${to.params.pathMatch || 'dashboard'}`,
      query: to.query,
    }),
  },
  {
    path: '/meta',
    component: MetaTest,
    meta: { requiresAuth: true, layout: 'dashboard' },
    redirect: '/meta/dashboard',
    children: [
      { path: 'dashboard', name: 'MetaDashboard', component: MetaTestDashboard },
      { path: 'setup', name: 'MetaSetup', component: MetaTestSetup },
      { path: 'connect', name: 'MetaConnect', component: MetaTestConnect },
      { path: 'accounts', name: 'MetaAccounts', component: MetaTestAccounts },
      { path: 'phone-numbers', name: 'MetaPhoneNumbers', component: MetaTestPhoneNumbers },
      { path: 'messages', name: 'MetaMessages', component: MetaTestMessages },
      { path: 'chats', name: 'MetaChats', component: MetaTestChats },
      { path: 'templates', name: 'MetaTemplates', component: MetaTestTemplates },
      { path: 'campaigns', name: 'MetaCampaigns', component: MetaTestCampaigns },
      { path: 'campaigns/new', name: 'MetaCampaignCreate', component: MetaTestCampaignCreate },
      { path: 'campaigns/:id', name: 'MetaCampaignShow', component: MetaTestCampaignShow },
      { path: 'logs', name: 'MetaLogs', component: MetaTestLogs },
      { path: 'status', name: 'MetaStatus', component: MetaTestStatus },
      { path: 'report', name: 'MetaReport', component: MetaTestReport },
    ],
  },
  {
    path: '/',
    name: 'Landing',
    component: Landing,
    meta: { requiresAuth: false, layout: 'default' },
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
});

/**
 * Navigation guard to check authentication
 */
router.beforeEach((to, from, next) => {
  const isAuthenticated = auth.isAuthenticated();
  const requiresAuth = to.meta.requiresAuth;
  const requiresAdmin = to.meta.requiresAdmin;
  const user = auth.getUser();
  const isAdmin = user?.is_admin === true;

  if (requiresAuth && !isAuthenticated) {
    // Redirect to login if route requires auth and user is not authenticated
    next('/login');
  } else if (requiresAdmin && !isAdmin) {
    // Redirect to dashboard if route requires admin and user is not an admin
    next('/dashboard');
  } else if (!requiresAuth && isAuthenticated && (to.path === '/login' || to.path === '/register')) {
    // Redirect to dashboard if user is already authenticated and trying to access auth pages
    next('/dashboard');
  } else {
    next();
  }
});

/**
 * Track a PageView on every route change for the Facebook/Meta Pixel.
 */
router.afterEach((to) => {
  if (typeof window !== 'undefined' && window.fbq) {
    window.fbq('track', 'PageView');
  }

  // Also send the PageView event through the backend Conversions API.
  if (typeof window !== 'undefined' && auth.isAuthenticated()) {
    const pageUrl = window.location.origin + to.fullPath;
    api.post('/meta-pixel/track', {
      events: [
        { event_name: 'PageView', event_source_url: pageUrl },
      ],
    }).catch(() => {});
  }
});

export default router;
