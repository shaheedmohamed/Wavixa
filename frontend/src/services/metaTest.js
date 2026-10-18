import api from './api';

const BASE = '/meta-test';

class MetaTestService {
  dashboard() {
    return api.get(`${BASE}/dashboard`);
  }

  setup() {
    return api.get(`${BASE}/setup`);
  }

  status() {
    return api.get(`${BASE}/status`);
  }

  report() {
    return api.get(`${BASE}/report`);
  }

  connectUrl() {
    return api.get(`${BASE}/connect`);
  }

  embeddedSignupConfig() {
    return api.get(`${BASE}/embedded-signup/config`);
  }

  embeddedSignupCallback(payload) {
    return api.post(`${BASE}/embedded-signup/callback`, payload);
  }

  disconnect() {
    return api.post(`${BASE}/disconnect`);
  }

  accounts() {
    return api.get(`${BASE}/accounts`);
  }

  syncAccounts() {
    return api.post(`${BASE}/accounts/sync`);
  }

  phoneNumbers() {
    return api.get(`${BASE}/phone-numbers`);
  }

  syncPhoneNumbers() {
    return api.post(`${BASE}/phone-numbers/sync`);
  }

  registerPhone(id) {
    return api.post(`${BASE}/phone-numbers/${id}/register`);
  }

  addPhoneNumber(payload) {
    return api.post(`${BASE}/phone-numbers`, payload);
  }

  sendPhoneCode(id, method = 'SMS') {
    return api.post(`${BASE}/phone-numbers/${id}/send-code`, { method });
  }

  verifyPhone(id, code) {
    return api.post(`${BASE}/phone-numbers/${id}/verify`, { code });
  }

  deletePhoneNumber(id) {
    return api.delete(`${BASE}/phone-numbers/${id}`);
  }

  chats(params = {}) {
    return api.get(`${BASE}/chats`, { params });
  }

  chatMessages(peer, since = 0) {
    return api.get(`${BASE}/chats/messages`, { params: { peer, since } });
  }

  chatSend(peer, message, phoneNumberId = null) {
    return api.post(`${BASE}/chats/send`, { peer, message, phone_number_id: phoneNumberId });
  }

  chatSendMedia(peer, formData) {
    formData.append('peer', peer);
    return api.post(`${BASE}/chats/send-media`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  }

  chatSetName(peer, name) {
    return api.post(`${BASE}/chats/name`, { peer, name });
  }

  chatMediaUrl(id) {
    return `/api${BASE}/chats/media/${id}`;
  }

  templates() {
    return api.get(`${BASE}/templates`);
  }

  syncTemplates() {
    return api.post(`${BASE}/templates/sync`);
  }

  createTemplate(data) {
    return api.post(`${BASE}/templates`, data);
  }

  deleteTemplate(id) {
    return api.delete(`${BASE}/templates/${id}`);
  }

  campaigns(params = {}) {
    return api.get(`${BASE}/campaigns`, { params });
  }

  campaignStats() {
    return api.get(`${BASE}/campaigns/stats`);
  }

  campaign(id) {
    return api.get(`${BASE}/campaigns/${id}`);
  }

  createCampaign(data) {
    return api.post(`${BASE}/campaigns`, data);
  }

  updateCampaign(id, data) {
    return api.put(`${BASE}/campaigns/${id}`, data);
  }

  deleteCampaign(id) {
    return api.delete(`${BASE}/campaigns/${id}`);
  }

  validateCampaignRecipients(data) {
    // Accepts either {recipients: [...]} or a FormData with a `file` field.
    return api.post(`${BASE}/campaigns/validate-recipients`, data);
  }

  saveCampaignRecipients(id, recipients) {
    return api.post(`${BASE}/campaigns/${id}/recipients`, { recipients });
  }

  campaignRecipients(id, params = {}) {
    return api.get(`${BASE}/campaigns/${id}/recipients`, { params });
  }

  launchCampaign(id) {
    return api.post(`${BASE}/campaigns/${id}/launch`);
  }

  pauseCampaign(id) {
    return api.post(`${BASE}/campaigns/${id}/pause`);
  }

  messages(params = {}) {
    return api.get(`${BASE}/messages`, { params });
  }

  conversation(phone, params = {}) {
    return api.get(`${BASE}/messages/conversation/${encodeURIComponent(phone)}`, { params });
  }

  sendMessage(data) {
    return api.post(`${BASE}/messages/send`, data);
  }

  reply(id, message) {
    return api.post(`${BASE}/messages/${id}/reply`, { message });
  }

  logs() {
    return api.get(`${BASE}/logs`);
  }
}

export default new MetaTestService();
