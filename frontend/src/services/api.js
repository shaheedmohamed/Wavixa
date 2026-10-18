/**
 * API Service
 * Centralized API client for all HTTP requests
 */

import { startLoading, finishLoading } from '@/stores/loader';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';

class ApiService {
  constructor() {
    this.baseURL = API_BASE_URL;
    this.token = localStorage.getItem('auth_token');
  }

  /**
   * Set authentication token
   */
  setToken(token) {
    this.token = token;
    localStorage.setItem('auth_token', token);
  }

  /**
   * Remove authentication token
   */
  removeToken() {
    this.token = null;
    localStorage.removeItem('auth_token');
  }

  /**
   * Get default headers
   */
  getHeaders(body) {
    const headers = {
      'Accept': 'application/json',
    };

    if (body && !(body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
    }

    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }

    return headers;
  }

  /**
   * Make HTTP request
   */
  async request(endpoint, options = {}) {
    const url = `${this.baseURL}${endpoint}`;
    const config = {
      headers: this.getHeaders(options.body),
      credentials: 'include',
      ...options,
    };

    if (options.responseType === 'blob') {
      config.headers['Accept'] = 'text/csv';
      delete config.headers['Content-Type'];
    }

    startLoading();
    try {
      const response = await fetch(url, config);

      if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        if (response.status === 401) {
          this.removeToken();
          window.location.href = '/login';
        }
        const error = new Error(data.message || `HTTP Error: ${response.status}`);
        error.response = response;
        error.data = data;
        throw error;
      }

      if (options.responseType === 'blob') {
        return await response.blob();
      }

      return await response.json();
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    } finally {
      finishLoading();
    }
  }

  /**
   * GET request
   */
  get(endpoint, options = {}) {
    const { params, ...rest } = options;
    let url = endpoint;

    if (params) {
      const filteredParams = Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined)
      );
      if (Object.keys(filteredParams).length > 0) {
        const queryString = new URLSearchParams(filteredParams).toString();
        url += (url.includes('?') ? '&' : '?') + queryString;
      }
    }

    return this.request(url, { method: 'GET', ...rest });
  }

  /**
   * POST request
   */
  post(endpoint, data) {
    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  /**
   * PUT request
   */
  put(endpoint, data) {
    return this.request(endpoint, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  /**
   * DELETE request
   */
  delete(endpoint) {
    return this.request(endpoint, { method: 'DELETE' });
  }

  /**
   * PATCH request
   */
  patch(endpoint, data) {
    return this.request(endpoint, {
      method: 'PATCH',
      body: JSON.stringify(data),
    });
  }

  /**
   * POST multipart form data (for file uploads)
   */
  upload(endpoint, formData) {
    return this.request(endpoint, {
      method: 'POST',
      body: formData,
    });
  }
}

export default new ApiService();
