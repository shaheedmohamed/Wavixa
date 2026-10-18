<?php

namespace App\Services;

class DeviceInfoService
{
    /**
     * Extract device info from user agent string.
     */
    public static function parse(?string $userAgent): array
    {
        if (!$userAgent) {
            return [
                'device_type' => 'unknown',
                'browser' => 'unknown',
                'os' => 'unknown',
                'name' => 'Unknown Device',
            ];
        }

        $ua = strtolower($userAgent);

        // Device type
        $deviceType = 'desktop';
        if (preg_match('/tablet|ipad|android(?!.*mobile)/i', $userAgent)) {
            $deviceType = 'tablet';
        } elseif (preg_match('/mobile|iphone|ipod|android|windows phone/i', $userAgent)) {
            $deviceType = 'mobile';
        }

        // Browser
        $browser = 'unknown';
        if (preg_match('/edg/i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/safari/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/firefox/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/opera|opr/i', $userAgent)) {
            $browser = 'Opera';
        } elseif (preg_match('/msie|trident/i', $userAgent)) {
            $browser = 'Internet Explorer';
        }

        // OS
        $os = 'unknown';
        if (preg_match('/windows nt/i', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/macintosh|mac os/i', $userAgent)) {
            $os = 'macOS';
        } elseif (preg_match('/linux/i', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/android/i', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/iphone|ios/i', $userAgent)) {
            $os = 'iOS';
        }

        $name = $browser . ' on ' . $os;

        return [
            'device_type' => $deviceType,
            'browser' => $browser,
            'os' => $os,
            'name' => $name,
        ];
    }

    /**
     * Get a friendly device name from user agent.
     */
    public static function getName(?string $userAgent): string
    {
        return self::parse($userAgent)['name'];
    }
}
