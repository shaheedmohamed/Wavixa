<?php

namespace App\Services;

use App\Models\FraudAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FraudDetectionService
{
    /** Admin WhatsApp number where fraud alerts are sent. */
    public const ADMIN_PHONE = '+201011679726';

    /** Minimum seconds between two identical alert notifications. */
    public const NOTIFY_COOLDOWN_SECONDS = 300;

    /**
     * Record a suspicious/tampering attempt and notify the admin.
     */
    public static function report(string $type, string $severity, string $description, array $metadata = []): ?FraudAlert
    {
        $request = request();

        if (!$request instanceof Request) {
            $request = new Request();
        }

        $ip = $metadata['ip_address'] ?? self::clientIp($request);
        $userAgent = $metadata['user_agent'] ?? $request->header('User-Agent') ?? 'unknown';
        unset($metadata['ip_address'], $metadata['user_agent']);

        $fingerprint = self::fingerprint($ip, $userAgent);

        // Throttle DB alert creation: one alert per type+fingerprint per minute.
        // Admin WhatsApp notifications have their own separate 5-minute cooldown.
        $throttleKey = 'fraud_alert_throttle:' . $type . ':' . $fingerprint;
        if (!Cache::add($throttleKey, true, now()->addSeconds(60))) {
            return null;
        }

        $user = $request->user() ?? $request->attributes->get('api_key_user');

        $device = DeviceInfoService::parse($userAgent);

        $ownerName = $user?->name ?? ($metadata['owner_name'] ?? 'Guest');
        $ownerEmail = $user?->email ?? ($metadata['owner_email'] ?? null);
        $ownerPhone = $user?->phone_number ?? ($metadata['owner_phone'] ?? null);

        $metadata = array_merge($metadata, [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'headers' => self::redactHeaders($request->headers->all()),
        ]);

        try {
            $alert = FraudAlert::create([
                'user_id' => $user?->id,
                'type' => $type,
                'severity' => $severity,
                'description' => $description,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'device_type' => $device['device_type'] ?? 'unknown',
                'device_name' => $device['name'] ?? 'Unknown Device',
                'device_fingerprint' => $fingerprint,
                'owner_name' => $ownerName,
                'owner_email' => $ownerEmail,
                'owner_phone' => $ownerPhone,
                'metadata' => $metadata,
                'is_resolved' => false,
            ]);

            self::notifyAdmin($alert);

            return $alert;
        } catch (\Throwable $e) {
            Log::error('FraudDetectionService failed to record alert: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve the real client IP, respecting Cloudflare / proxy headers.
     */
    private static function clientIp(Request $request): string
    {
        foreach (['CF-Connecting-IP', 'X-Forwarded-For', 'True-Client-IP'] as $header) {
            $value = $request->header($header);
            if ($value) {
                $ips = explode(',', $value);
                $candidate = trim($ips[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $candidate;
                }
            }
        }

        $ip = $request->ip();

        return $ip ?: '0.0.0.0';
    }

    /**
     * Create a simple fingerprint for the request source.
     */
    private static function fingerprint(string $ip, string $userAgent): string
    {
        return hash('sha256', $ip . '|' . $userAgent . '|');
    }

    /**
     * Remove sensitive header values before storing metadata.
     */
    private static function redactHeaders(array $headers): array
    {
        $sensitive = [
            'authorization',
            'x-api-key',
            'x-service-signature',
            'x-n8n-api-key',
            'cookie',
            'x-csrf-token',
        ];

        $redacted = [];
        foreach ($headers as $key => $value) {
            $lower = strtolower($key);
            $redacted[$key] = in_array($lower, $sensitive, true) ? '***' : $value;
        }

        return $redacted;
    }

    /**
     * Send a WhatsApp alert to the admin device, with cooldown to avoid spam.
     */
    private static function notifyAdmin(FraudAlert $alert): void
    {
        $key = 'fraud_alert_notified:' . $alert->type . ':' . ($alert->ip_address ?? '') . ':' . ($alert->user_id ?? 'guest');

        if (!Cache::add($key, true, now()->addSeconds(self::NOTIFY_COOLDOWN_SECONDS))) {
            return;
        }

        try {
            SystemWhatsAppService::sendMessage(self::ADMIN_PHONE, self::formatMessage($alert));
        } catch (\Throwable $e) {
            Log::error('Failed to send fraud alert WhatsApp: ' . $e->getMessage());
        }
    }

    /**
     * Format the admin notification message.
     */
    private static function formatMessage(FraudAlert $alert): string
    {
        $type = $alert->type;
        $severity = $alert->severity;
        $desc = $alert->description;
        $ip = $alert->ip_address ?: 'unknown';
        $device = $alert->device_name ?: 'Unknown';
        $deviceType = $alert->device_type ?: 'unknown';
        $owner = $alert->owner_name ?: 'غير معروف';
        $time = $alert->created_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s');
        $fp = substr($alert->device_fingerprint ?: '', 0, 16);

        return "🚨 تنبيه احتيال على Wavixa\n\n"
            . "النوع: {$type}\n"
            . "الخطورة: {$severity}\n"
            . "الوصف: {$desc}\n"
            . "IP: {$ip}\n"
            . "الجهاز: {$device} ({$deviceType})\n"
            . "البصمة: {$fp}...\n"
            . "الاسم: {$owner}\n"
            . "الوقت: {$time}\n\n"
            . "افحص مركز الأمان للتفاصيل.";
    }
}
