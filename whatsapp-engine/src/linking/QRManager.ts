import qrcode from 'qrcode';

export interface QRState {
  raw: string;
  dataUrl?: string;
  expiresAt: number;
}

export class QRManager {
  private currentQR: QRState | null = null;
  private ttlMs: number;

  constructor(ttlMs: number = 25000) {
    this.ttlMs = ttlMs;
  }

  /**
   * Format multi-device pairing QR string from handshake references
   */
  public async generateQR(
    ref: string,
    noisePublicKey: Buffer,
    identityPublicKey: Buffer,
    clientId: Buffer,
  ): Promise<QRState> {
    const raw = `${ref},${noisePublicKey.toString('base64')},${identityPublicKey.toString('base64')},${clientId.toString('base64')}`;
    const expiresAt = Date.now() + this.ttlMs;

    let dataUrl: string | undefined;
    try {
      dataUrl = await qrcode.toDataURL(raw, {
        margin: 2,
        width: 300,
        errorCorrectionLevel: 'M',
      });
    } catch {
      // Optional fallback
    }

    this.currentQR = {
      raw,
      dataUrl,
      expiresAt,
    };

    return this.currentQR;
  }

  public getCurrentQR(): QRState | null {
    if (this.isExpired()) {
      this.currentQR = null;
      return null;
    }
    return this.currentQR;
  }

  public isExpired(): boolean {
    if (!this.currentQR) return true;
    return Date.now() >= this.currentQR.expiresAt;
  }

  public clear(): void {
    this.currentQR = null;
  }
}
