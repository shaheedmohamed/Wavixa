/**
 * Admin Service
 * Handles admin dashboard API requests
 */

import api from './api';

class AdminService {
  // Overview
  getOverview() {
    return api.get('/admin/overview');
  }

  // Users
  getUsers(params = {}) {
    return api.get('/admin/users', { params });
  }

  getUser(id) {
    return api.get(`/admin/users/${id}`);
  }

  updateUser(id, data) {
    return api.put(`/admin/users/${id}`, data);
  }

  deleteUser(id) {
    return api.delete(`/admin/users/${id}`);
  }

  suspendUser(id) {
    return api.post(`/admin/users/${id}/suspend`);
  }

  banUser(id) {
    return api.post(`/admin/users/${id}/ban`);
  }

  reactivateUser(id) {
    return api.post(`/admin/users/${id}/reactivate`);
  }

  loginAsUser(id) {
    return api.post(`/admin/users/${id}/login-as`);
  }

  adjustUserBalance(id, data) {
    return api.post(`/admin/users/${id}/balance`, data);
  }

  changeUserPlan(id, data) {
    return api.post(`/admin/users/${id}/change-plan`, data);
  }

  getUserLoginHistory(id, params = {}) {
    return api.get(`/admin/users/${id}/login-history`, { params });
  }

  getUserDevices(id, params = {}) {
    return api.get(`/admin/users/${id}/devices`, { params });
  }

  getUserApiKeys(id, params = {}) {
    return api.get(`/admin/users/${id}/api-keys`, { params });
  }

  getUserMessages(id, params = {}) {
    return api.get(`/admin/users/${id}/messages`, { params });
  }

  // Sessions
  getSessions(params = {}) {
    return api.get('/admin/sessions', { params });
  }

  deleteSession(id) {
    return api.delete(`/admin/sessions/${id}`);
  }

  setSystemSession(id) {
    return api.post(`/admin/sessions/${id}/set-system`);
  }

  sendMessageFromSession(id, data) {
    return api.post(`/admin/sessions/${id}/send-message`, data);
  }

  // API Keys
  getApiKeys(params = {}) {
    return api.get('/admin/api-keys', { params });
  }

  deleteApiKey(id) {
    return api.delete(`/admin/api-keys/${id}`);
  }

  getApiKeyMetaUsage(id) {
    return api.get(`/admin/api-keys/${id}/meta-usage`);
  }

  // Messages
  getMessages(params = {}) {
    return api.get('/admin/messages', { params });
  }

  getMessage(id) {
    return api.get(`/admin/messages/${id}`);
  }

  deleteMessage(id) {
    return api.delete(`/admin/messages/${id}`);
  }

  // Subscriptions
  getSubscriptions(params = {}) {
    return api.get('/admin/subscriptions', { params });
  }

  updateSubscription(id, data) {
    return api.put(`/admin/subscriptions/${id}`, data);
  }

  // Plans
  getPlans() {
    return api.get('/admin/plans');
  }

  createPlan(data) {
    return api.post('/admin/plans', data);
  }

  updatePlan(id, data) {
    return api.put(`/admin/plans/${id}`, data);
  }

  deletePlan(id) {
    return api.delete(`/admin/plans/${id}`);
  }

  // Webhooks
  getWebhooks(params = {}) {
    return api.get('/admin/webhooks', { params });
  }

  getWebhookLogs(id, params = {}) {
    return api.get(`/admin/webhooks/${id}/logs`, { params });
  }

  deleteWebhook(id) {
    return api.delete(`/admin/webhooks/${id}`);
  }

  // Queue
  getQueue(params = {}) {
    return api.get('/admin/queue', { params });
  }

  retryFailedJob(id) {
    return api.post(`/admin/queue/failed/${id}/retry`);
  }

  deleteFailedJob(id) {
    return api.delete(`/admin/queue/failed/${id}`);
  }

  // Devices
  getDevices(params = {}) {
    return api.get('/admin/devices', { params });
  }

  deleteDevice(id) {
    return api.delete(`/admin/devices/${id}`);
  }

  // System Stats
  getSystemStats() {
    return api.get('/admin/system-stats');
  }

  // Analytics
  getAnalytics(params = {}) {
    return api.get('/admin/analytics', { params });
  }

  // Activity Logs
  getActivityLogs(params = {}) {
    return api.get('/admin/activity-logs', { params });
  }

  // Audit Logs
  getAuditLogs(params = {}) {
    return api.get('/admin/audit-logs', { params });
  }

  // API Monitoring
  getApiMonitoring(params = {}) {
    return api.get('/admin/api-monitoring', { params });
  }

  // Security Center
  getLoginHistory(params = {}) {
    return api.get('/admin/security/login-history', { params });
  }

  getFailedLogins(params = {}) {
    return api.get('/admin/security/failed-logins', { params });
  }

  getFraudAlerts(params = {}) {
    return api.get('/admin/security/fraud-alerts', { params });
  }

  resolveFraudAlert(id) {
    return api.post(`/admin/security/fraud-alerts/${id}/resolve`);
  }

  // Live Events
  getLiveEvents(params = {}) {
    return api.get('/admin/live-events', { params });
  }

  // Payments
  getTransactions(params = {}) {
    return api.get('/admin/payments/transactions', { params });
  }

  refundTransaction(id) {
    return api.post(`/admin/payments/transactions/${id}/refund`);
  }

  // System Settings
  getSettings(group = null) {
    const params = group ? { group } : {};
    return api.get('/admin/settings', { params });
  }

  updateSetting(data) {
    return api.post('/admin/settings', data);
  }

  // Notifications
  getNotifications(params = {}) {
    return api.get('/admin/notifications', { params });
  }

  sendNotification(data) {
    return api.post('/admin/notifications', data);
  }

  deleteNotification(id) {
    return api.delete(`/admin/notifications/${id}`);
  }

  // Meta Ads Marketing
  getMetaAdsStatus() {
    return api.get('/admin/meta-ads/status');
  }

  connectMetaAds(data) {
    return api.post('/admin/meta-ads/connect', data);
  }

  syncMetaAds() {
    return api.post('/admin/meta-ads/sync');
  }

  getMetaAdsOverview(params = {}) {
    return api.get('/admin/meta-ads/overview', { params });
  }

  getMetaAdsCampaigns(params = {}) {
    return api.get('/admin/meta-ads/campaigns', { params });
  }

  getMetaAdsCampaign(id) {
    return api.get(`/admin/meta-ads/campaigns/${id}`);
  }

  createMetaAdsCampaign(data) {
    return api.post('/admin/meta-ads/campaigns', data);
  }

  publishMetaAdsCampaign(id) {
    return api.post(`/admin/meta-ads/campaigns/${id}/publish`);
  }

  pauseMetaAdsCampaign(id) {
    return api.post(`/admin/meta-ads/campaigns/${id}/pause`);
  }

  activateMetaAdsCampaign(id) {
    return api.post(`/admin/meta-ads/campaigns/${id}/activate`);
  }

  updateMetaAdsCampaignBudget(id, data) {
    return api.put(`/admin/meta-ads/campaigns/${id}/budget`, data);
  }

  deleteMetaAdsCampaign(id) {
    return api.delete(`/admin/meta-ads/campaigns/${id}`);
  }

  getMetaAdsInsights(id, params = {}) {
    return api.get(`/admin/meta-ads/campaigns/${id}/insights`, { params });
  }

  uploadMetaAdsMedia(formData) {
    return api.post('/admin/meta-ads/media/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  }
}

export default new AdminService();
