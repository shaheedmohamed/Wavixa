import crypto from 'crypto';

export interface PairingCodeState {
  code: string;
  phoneNumber: string;
  expiresAt: number;
}

export class PairingCodeManager {
  private currentPairing: PairingCodeState | null = null;
  private ttlMs: number;

  constructor(ttlMs: number = 120000) {
    this.ttlMs = ttlMs;
  }

  /**
   * Generate an 8-character pairing code formatted as ABCD-1234
   */
  public generateCode(phoneNumber: string): PairingCodeState {
    const chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // exclude ambiguous characters
    let raw = '';
    const bytes = crypto.randomBytes(8);
    for (let i = 0; i < 8; i++) {
      raw += chars[bytes[i]! % chars.length];
    }

    const formatted = `${raw.slice(0, 4)}-${raw.slice(4)}`;
    this.currentPairing = {
      code: formatted,
      phoneNumber,
      expiresAt: Date.now() + this.ttlMs,
    };

    return this.currentPairing;
  }

  public getCurrentCode(): PairingCodeState | null {
    if (this.isExpired()) {
      this.currentPairing = null;
      return null;
    }
    return this.currentPairing;
  }

  public isExpired(): boolean {
    if (!this.currentPairing) return true;
    return Date.now() >= this.currentPairing.expiresAt;
  }

  public clear(): void {
    this.currentPairing = null;
  }
}
