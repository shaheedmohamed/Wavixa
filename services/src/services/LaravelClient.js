/**
 * Laravel API Client
 * Handles communication with the Laravel backend
 */

import axios from 'axios';

class LaravelClient {
  constructor() {
    this.baseURL = process.env.LARAVEL_API_URL || 'http://localhost:8000/api';
    this.apiKey = process.env.LARAVEL_API_KEY || 'your-secret-api-key';
    this.client = axios.create({
      baseURL: this.baseURL,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${this.apiKey}`,
      },
      timeout: 10000,
    });
  }

  /**
   * Validate API key with Laravel
   */
  async validateApiKey(apiKey) {
    try {
      const response = await this.client.post('/whatsapp/validate-key', {
        apiKey,
      });
      return response.data;
    } catch (error) {
      console.error('Laravel API Key Validation Error:', error.message);
      throw error;
    }
  }

  /**
   * Get user by API key
   */
  async getUserByApiKey(apiKey) {
    try {
      const response = await this.client.get('/users/by-key', {
        headers: {
          'Authorization': `Bearer ${apiKey}`,
        },
      });
      return response.data;
    } catch (error) {
      console.error('Laravel Get User Error:', error.message);
      throw error;
    }
  }

  /**
   * Create WhatsApp session record in Laravel
   */
  async createSessionRecord(userId, sessionData) {
    try {
      const response = await this.client.post('/whatsapp/sessions', {
        user_id: userId,
        ...sessionData,
      });
      return response.data;
    } catch (error) {
      console.error('Laravel Create Session Record Error:', error.message);
      throw error;
    }
  }

  /**
   * Update WhatsApp session status
   */
  async updateSessionStatus(sessionId, status, metadata = {}) {
    try {
      const response = await this.client.put(`/whatsapp/sessions/${sessionId}`, {
        status,
        metadata,
      });
      return response.data;
    } catch (error) {
      console.error('Laravel Update Session Status Error:', error.message);
      throw error;
    }
  }

  /**
   * Log message in Laravel
   */
  async logMessage(sessionId, messageData) {
    try {
      const response = await this.client.post('/whatsapp/messages', {
        session_id: sessionId,
        ...messageData,
      });
      return response.data;
    } catch (error) {
      console.error('Laravel Log Message Error:', error.message);
      throw error;
    }
  }

  /**
   * Update message status
   */
  async updateMessageStatus(messageId, status, metadata = {}) {
    try {
      const response = await this.client.put(`/whatsapp/messages/${messageId}`, {
        status,
        metadata,
      });
      return response.data;
    } catch (error) {
      console.error('Laravel Update Message Status Error:', error.message);
      throw error;
    }
  }

  /**
   * Send webhook notification to Laravel
   */
  async sendWebhook(event, data) {
    try {
      const response = await this.client.post('/webhooks/whatsapp', {
        event,
        data,
        timestamp: new Date().toISOString(),
      });
      return response.data;
    } catch (error) {
      console.error('Laravel Webhook Error:', error.message);
      throw error;
    }
  }

  /**
   * Health check
   */
  async healthCheck() {
    try {
      const response = await this.client.get('/health');
      return response.data;
    } catch (error) {
      console.error('Laravel Health Check Error:', error.message);
      return { success: false, message: 'Laravel service is unavailable' };
    }
  }
}

export default new LaravelClient();
