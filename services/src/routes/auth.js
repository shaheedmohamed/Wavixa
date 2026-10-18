/**
 * Authentication Routes
 * Handles API key validation and authentication
 */

import express from 'express';
import { v4 as uuidv4 } from 'uuid';

const router = express.Router();

/**
 * POST /api/auth/validate
 * Validate API key
 */
router.post('/validate', (req, res) => {
  const { apiKey } = req.body;

  if (!apiKey) {
    return res.status(400).json({
      success: false,
      message: 'API key is required',
    });
  }

  // TODO: Validate API key against Laravel backend
  const isValid = apiKey === process.env.LARAVEL_API_KEY;

  if (!isValid) {
    return res.status(401).json({
      success: false,
      message: 'Invalid API key',
    });
  }

  res.json({
    success: true,
    message: 'API key is valid',
    token: uuidv4(),
  });
});

/**
 * POST /api/auth/refresh
 * Refresh authentication token
 */
router.post('/refresh', (req, res) => {
  res.json({
    success: true,
    message: 'Token refreshed',
    token: uuidv4(),
  });
});

export default router;
