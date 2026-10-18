<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSession;
use App\Models\User;
use App\Jobs\SendWhatsAppMessageJob;
use Illuminate\Support\Facades\Log;

class SystemWhatsAppService
{
    /**
     * Send a system WhatsApp message using the admin's connected device.
     */
    public static function sendMessage(string $to, string $text): bool
    {
        try {
            $senderPhone = self::configuredSenderPhone();
            if (!$senderPhone) {
                Log::error('SystemWhatsAppService: no system sender number configured');
                return false;
            }

            $sessions = self::senderSessions($senderPhone);

            if ($sessions->isEmpty()) {
                Log::error("SystemWhatsAppService: sender {$senderPhone} is not connected, refusing to send from another device");
                return false;
            }

            // Try each session row for that number until one succeeds
            foreach ($sessions as $session) {
                if (self::sendFromSession($session, $to, $text)) {
                    return true;
                }

                Log::warning("SystemWhatsAppService: session {$session->id} ({$senderPhone}) could not send to {$to}");
            }

            Log::error("SystemWhatsAppService: sender {$senderPhone} failed to send to {$to}");
            return false;
        } catch (\Exception $e) {
            Log::error("SystemWhatsAppService Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Whether the admin device can actually send right now. Callers use this to
     * fail fast before doing work they would have to undo, such as creating a
     * user account that can never receive its verification code.
     */
    public static function isAvailable(): bool
    {
        $senderPhone = self::configuredSenderPhone();

        return $senderPhone !== null && self::senderSessions($senderPhone)->isNotEmpty();
    }

    /**
     * Connected sessions for the sender number, most recently active first.
     *
     * Matching on the number rather than only the stored session IDs keeps
     * sending alive after a re-pair, which creates a brand new session row whose
     * ID is absent from `system_whatsapp_session_ids`.
     */
    private static function senderSessions(string $senderPhone)
    {
        return WhatsAppSession::where('status', 'connected')
            ->orderBy('last_activity_at', 'desc')
            ->get()
            ->filter(fn ($session) => self::normalizePhone($session->phone_number) === $senderPhone)
            ->values();
    }

    /**
     * The only number system messages may be sent from. Configured explicitly via
     * the `system_whatsapp_phone_number` setting, otherwise derived from the
     * devices the admin marked as system senders.
     */
    private static function configuredSenderPhone(): ?string
    {
        $systemIds = SystemSetting::get('system_whatsapp_session_ids', []);
        if (!is_array($systemIds)) {
            $systemIds = [];
        }

        $legacyId = SystemSetting::get('system_whatsapp_session_id');
        if ($legacyId && !in_array($legacyId, $systemIds)) {
            $systemIds[] = $legacyId;
        }

        return self::senderPhone($systemIds);
    }

    /**
     * Resolve the sender number from the setting, falling back to the devices the
     * admin marked as system senders.
     */
    private static function senderPhone(array $systemIds): ?string
    {
        $configured = self::normalizePhone(SystemSetting::get('system_whatsapp_phone_number'));
        if ($configured !== '') {
            return $configured;
        }

        if (empty($systemIds)) {
            return null;
        }

        $phone = WhatsAppSession::whereIn('id', $systemIds)
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->orderBy('last_activity_at', 'desc')
            ->value('phone_number');

        return self::normalizePhone($phone) ?: null;
    }

    /**
     * Reduce a number to bare digits in international form so that stored values
     * like "+20 101 167 9726", "01011679726" and "201011679726" all match.
     */
    private static function normalizePhone(?string $phone): string
    {
        return PhoneNormalizer::digits($phone);
    }

    /**
     * Send a message using a specific connected session.
     */
    private static function sendFromSession(WhatsAppSession $session, string $to, string $text): bool
    {
        Log::info("sendFromSession debug to={$to} text=" . ($text ?: '[EMPTY]'));
        $message = WhatsAppMessage::create([
            'user_id' => $session->user_id,
            'session_id' => $session->id,
            'from' => $session->phone_number,
            'to' => $to,
            'message' => $text,
            'message_type' => 'text',
            'direction' => 'outgoing',
            'status' => WhatsAppMessage::STATUS_PENDING,
        ]);

        Log::info('created message:', $message->toArray());

        try {
            dispatch_sync(new SendWhatsAppMessageJob($message->id, true));
            return true;
        } catch (\Throwable $e) {
            $message->update([
                'status' => WhatsAppMessage::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'failed_at' => now(),
            ]);
            Log::warning("SystemWhatsAppService session {$session->id} failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send OTP to a user.
     */
    public static function sendOTP(string $phone, string $code): bool
    {
        $text = "كود التحقق الخاص بك في Wavixa هو: {$code}\nلا تشارك هذا الكود مع أحد.";
        return self::sendMessage($phone, $text);
    }
}
