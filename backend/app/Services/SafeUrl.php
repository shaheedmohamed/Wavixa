<?php

namespace App\Services;

class SafeUrl
{
    /**
     * Determine whether an outbound URL is safe to request from the backend.
     *
     * Only http(s) schemes are allowed, and the host must not resolve to a
     * private, loopback, link-local, or otherwise reserved IP address.
     */
    public static function isSafe(string $url): bool
    {
        $parts = parse_url($url);

        if (!$parts || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host']);

        // Block obvious internal / meta-data hostnames without DNS lookup.
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1', '169.254.169.254'], true)) {
            return false;
        }

        if (str_ends_with($host, '.local') || str_starts_with($host, 'metadata.') || str_starts_with($host, 'metadata.google.')) {
            return false;
        }

        // Literal IP addresses.
        $ip = filter_var($host, FILTER_VALIDATE_IP);
        if ($ip !== false) {
            return !self::isPrivateOrReserved($ip);
        }

        // Resolve the hostname and ensure no returned address is internal.
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false || empty($records)) {
            return false;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (!$ip || self::isPrivateOrReserved($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check whether an IP address is private, loopback, link-local, or reserved.
     */
    public static function isPrivateOrReserved(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
