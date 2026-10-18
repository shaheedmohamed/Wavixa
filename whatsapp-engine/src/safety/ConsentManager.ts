/**
 * Wavixa WhatsApp Engine - Opt-In Consent Manager
 * Enforces recipient opt-in verification and blocks messages to opted-out contacts.
 */

export type ConsentStatus = 'UNKNOWN' | 'OPTED_IN' | 'OPTED_OUT' | 'BLOCKED';

export class ConsentManager {
  private consentStore: Map<string, ConsentStatus> = new Map();

  public setConsent(recipient: string, status: ConsentStatus): void {
    const key = this.normalize(recipient);
    this.consentStore.set(key, status);
  }

  public getConsent(recipient: string): ConsentStatus {
    const key = this.normalize(recipient);
    return this.consentStore.get(key) || 'UNKNOWN';
  }

  public canSend(recipient: string): { allowed: boolean; reason?: string } {
    const status = this.getConsent(recipient);

    if (status === 'OPTED_OUT') {
      return { allowed: false, reason: 'Recipient has opted out of messaging' };
    }

    if (status === 'BLOCKED') {
      return { allowed: false, reason: 'Recipient is permanently blocked' };
    }

    return { allowed: true };
  }

  private normalize(recipient: string): string {
    return recipient.replace(/\D/g, '');
  }
}
