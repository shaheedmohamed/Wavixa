<?php

namespace App\Services;

use App\Models\ShortLink;
use Illuminate\Support\Str;

class LinkMaskerService
{
    /**
     * Automatically detect and replace all URLs in the given text with a unique masked short link.
     */
    public static function maskUrlsInText(?string $text, ?int $userId = null): string
    {
        if (empty($text)) {
            return '';
        }

        $appUrl = rtrim(config('app.url', 'https://wavixa.net'), '/');
        // Prefer https://wavixa.net for cleaner outbound links
        if (str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1')) {
            $baseUrl = 'https://wavixa.net';
        } else {
            $baseUrl = $appUrl;
        }

        // Regex matching http/https URLs and standalone domain URLs
        $pattern = '/\b(?:https?:\/\/)[^\s<>"\'{}|\\^`]+[^\s<>"\'{}|\\^`,.;:!?)\]]/i';

        return preg_replace_callback($pattern, function ($matches) use ($userId, $baseUrl) {
            $originalUrl = $matches[0];

            // If it's already a short link from our platform, don't re-mask it
            if (str_contains($originalUrl, '/r/') || str_contains($originalUrl, '/l/')) {
                return $originalUrl;
            }

            // Generate unique short code
            $code = self::generateUniqueCode();

            ShortLink::create([
                'user_id' => $userId,
                'original_url' => $originalUrl,
                'short_code' => $code,
                'clicks_count' => 0,
            ]);

            return "{$baseUrl}/r/{$code}";
        }, $text);
    }

    /**
     * Generate a unique alphanumeric short code.
     */
    protected static function generateUniqueCode(int $length = 7): string
    {
        do {
            $code = Str::random($length);
        } while (ShortLink::where('short_code', $code)->exists());

        return $code;
    }
}
