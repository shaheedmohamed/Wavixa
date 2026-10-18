<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\WhatsAppSession;

class WhatsAppWarmupService
{
    /**
     * Calculate the daily message limit for a session based on its connection age.
     *
     * Newly connected sessions start at the configured base limit. Every N days
     * (default 2) the limit grows by the configured increment, up to the max cap.
     */
    public static function dailyLimit(WhatsAppSession $session): int
    {
        $connectedAt = $session->connected_at ?? $session->created_at;

        if (!$connectedAt) {
            return 0;
        }

        $ageInDays = (int) now()->diffInDays($connectedAt, true);

        $base = (int) SystemSetting::get('whatsapp_warmup_base_daily', 100);
        $increment = (int) SystemSetting::get('whatsapp_warmup_increment_amount', 50);
        $everyDays = max(1, (int) SystemSetting::get('whatsapp_warmup_increment_every_days', 2));
        $max = (int) SystemSetting::get('whatsapp_warmup_max_daily', 1000);

        $multiplier = (int) floor($ageInDays / $everyDays);

        return min($base + ($increment * $multiplier), $max);
    }

    /**
     * Calculate the hourly message limit for a session.
     *
     * Distributes the daily limit over the configured number of active hours
     * so messages are spread throughout the day.
     */
    public static function hourlyLimit(WhatsAppSession $session): int
    {
        $daily = self::dailyLimit($session);

        if ($daily <= 0) {
            return 0;
        }

        $hoursPerDay = max(1, (int) SystemSetting::get('whatsapp_warmup_hours_per_day', 12));

        return (int) ceil($daily / $hoursPerDay);
    }

    /**
     * Get both daily and hourly limits for a session.
     */
    public static function limits(WhatsAppSession $session): array
    {
        return [
            'per_day' => self::dailyLimit($session),
            'per_hour' => self::hourlyLimit($session),
        ];
    }
}
