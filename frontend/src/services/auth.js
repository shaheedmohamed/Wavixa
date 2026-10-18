/**
 * Authentication Service
 * Handles user authentication and authorization
 */

import api from './api';

class AuthService {
  constructor() {
    this.user = this.loadUser();
  }

  /**
   * Load user from localStorage
   */
  loadUser() {
    const userJson = localStorage.getItem('auth_user');
    return userJson ? JSON.parse(userJson) : null;
  }

  /**
   * Set user in localStorage
   */
  setUser(user) {
    this.user = user;
    localStorage.setItem('auth_user', JSON.stringify(user));
  }

  /**
   * Clear user from localStorage
   */
  clearUser() {
    this.user = null;
    localStorage.removeItem('auth_user');
  }

  /**
   * Register a new user
   */
  async register(userData) {
    const response = await api.post('/auth/register', userData);
    return response;
  }

  /**
   * Login user
   */
  async login(credentials) {
    const response = await api.post('/auth/login', credentials);
    if (response.success && response.data.token) {
      api.setToken(response.data.token);
      this.setUser(response.data.user);
    }
    return response;
  }

  /**
   * Logout user
   */
  async logout() {
    try {
      await api.post('/auth/logout', {});
    } finally {
      api.removeToken();
      this.clearUser();
    }
  }

  /**
   * Get current user
   */
  async getCurrentUser() {
    return api.get('/auth/me');
  }

  /**
   * Update user profile
   */
  async updateProfile(profileData) {
    const response = await api.put('/auth/profile', profileData);
    if (response.success) {
      this.setUser(response.data);
    }
    return response;
  }

  /**
   * Refresh token
   */
  async refreshToken() {
    const response = await api.post('/auth/refresh-token', {});
    if (response.success && response.data.token) {
      api.setToken(response.data.token);
    }
    return response;
  }

  /**
   * Send password reset email
   */
  async forgotPassword(email) {
    return api.post('/auth/forgot-password', { email });
  }

  /**
   * Reset password
   */
  async resetPassword(resetData) {
    return api.post('/auth/reset-password', resetData);
  }

  /**
   * Verify email
   */
  async verifyEmail(id, hash) {
    return api.get(`/auth/verify-email/${id}/${hash}`);
  }

  /**
   * Resend verification email
   */
  async resendVerificationEmail(email) {
    return api.post('/auth/resend-verification-email', { email });
  }

  /**
   * Check if user is authenticated
   */
  isAuthenticated() {
    return !!localStorage.getItem('auth_token') && !!this.user;
  }

  /**
   * Get stored token
   */
  getToken() {
    return localStorage.getItem('auth_token');
  }

  /**
   * Get current user
   */
  getUser() {
    return this.user;
  }
}

export default new AuthService();
