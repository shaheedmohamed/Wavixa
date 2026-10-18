import api from './api';

class VoiceService {
  async startSession() {
    return await api.post('/voice/sessions');
  }

  async sendTranscript(sessionId, transcript, detectedLanguage) {
    return await api.post(`/voice/sessions/${sessionId}/transcript`, {
      transcript,
      detected_language: detectedLanguage,
    });
  }

  async endSession(sessionId) {
    return await api.post(`/voice/sessions/${sessionId}/end`);
  }

  async getHistory(sessionId) {
    return await api.get(`/voice/sessions/${sessionId}/history`);
  }
}

export default new VoiceService();
