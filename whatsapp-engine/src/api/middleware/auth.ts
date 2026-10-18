import { Request, Response, NextFunction } from 'express';

export function requireInternalAuth(req: Request, res: Response, next: NextFunction): void {
  const secret = process.env.ENGINE_INTERNAL_SECRET || process.env.WHATSAPP_SERVICE_API_KEY || 'wavixa-local-api-key-2026';
  const authHeader = req.headers.authorization || '';

  if (!authHeader) {
    res.status(401).json({ success: false, message: 'Missing Authorization header' });
    return;
  }

  const token = authHeader.replace(/^Bearer\s+/i, '').trim();
  if (token !== secret) {
    res.status(401).json({ success: false, message: 'Invalid service authentication secret' });
    return;
  }

  next();
}
