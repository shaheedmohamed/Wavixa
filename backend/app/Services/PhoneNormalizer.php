<?php

namespace App\Services;

class PhoneNormalizer
{
    /**
     * Normalize any common phone number format to E.164.
     *
     * Accepted inputs:
     *   +201155505269
     *   00201155505269
     *   01155505269
     *   201155505269
     *   1155505269
     *
     * Output:
     *   +201155505269 (withPlus=true)
     *   201155505269  (withPlus=false)
     */
    public static function normalize(?string $phone, bool $withPlus = true): string
    {
        $phone = (string) $phone;

        if (empty($phone)) {
            return '';
        }

        // Keep WhatsApp JIDs as-is
        if (str_contains($phone, '@')) {
            return $phone;
        }

        // Remove all non-digit characters except leading +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Trim a leading +; we'll add it back at the end if requested
        $hasPlus = str_starts_with($phone, '+');
        $digits = ltrim($phone, '+');

        if (empty($digits)) {
            return '';
        }

        // International exit code 00 -> remove it
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // If a + was present, a leading 0 is just a redundant trunk prefix; strip it.
        if ($hasPlus && str_starts_with($digits, '0')) {
            $digits = ltrim($digits, '0');
        }

        // Local Egyptian mobile starting with 0 -> replace with country code 20
        if (str_starts_with($digits, '0')) {
            $digits = '20' . substr($digits, 1);
        }

        // Final cleanup: remove any remaining leading zeros
        $digits = ltrim($digits, '0');

        // Fallback: 10-digit number starting with 1 is likely an Egyptian mobile
        // entered without country code and without the leading 0.
        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            $digits = '20' . $digits;
        }

        if ($withPlus) {
            return '+' . $digits;
        }

        return $digits;
    }

    /**
     * Return digits only (no +), suitable for APIs like Meta Cloud API.
     */
    public static function digits(?string $phone): string
    {
        return self::normalize($phone, false);
    }
}
