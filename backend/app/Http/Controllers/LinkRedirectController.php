<?php

namespace App\Http\Controllers;

use App\Models\ShortLink;
use Illuminate\Http\Request;

class LinkRedirectController extends Controller
{
    /**
     * Handle link redirection.
     */
    public function redirect(Request $request, string $code)
    {
        $shortLink = ShortLink::where('short_code', $code)->first();

        if (!$shortLink) {
            return redirect()->away('https://wavixa.net');
        }

        $shortLink->increment('clicks_count');
        $shortLink->update(['last_clicked_at' => now()]);

        return redirect()->away($shortLink->original_url, 302);
    }
}
