/**
 * Health Check Routes
 */

import express from 'express';

const router = express.Router();

/**
 * GET /api/health
 * Health check endpoint
 */
router.get('/', (req, res) => {
  res.json({
    success: true,
    message: 'WhatsApp Service is running',
    timestamp: new Date().toISOString(),
    service: 'wavixa-whatsapp',
    version: '1.0.0',
  });
});

export default router;
