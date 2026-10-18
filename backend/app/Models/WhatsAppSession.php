<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Http;

class WhatsAppSession extends Model
{
    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'user_id',
        'api_key_id',
        'session_token',
        'status',
        'phone_number',
        'device_name',
        'browser',
        'os',
        'connected_at',
        'last_activity_at',
        'qr_generated_at',
    ];

    protected $casts = [
        'connected_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'qr_generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiKeys(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function autoReply(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(WhatsAppAutoReply::class, 'session_id');
    }

    public function scopeForToken($query, string $token)
    {
        return $query->where('session_token', $token);
    }

    /**
     * Determine the WhatsApp node service user id that is currently live.
     *
     * Dashboard/legacy sessions live under the user_id; remote pairing sessions
     * live under the session_token. Probe both and return the connected one.
     */
    public function nodeUserId(): string
    {
        $candidates = array_values(array_filter([$this->session_token, (string) $this->user_id]));

        if (count($candidates) === 0) {
            return (string) $this->user_id;
        }

        $serviceUrl = rtrim(config('services.whatsapp.url', env('WHATSAPP_SERVICE_URL', 'http://127.0.0.1:3002')), '/');
        $apiKey = config('services.whatsapp.api_key');

        foreach ($candidates as $id) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                    ->get("{$serviceUrl}/api/whatsapp/status/{$id}");

                if ($response->successful() && ($response->json()['status'] ?? 'disconnected') === 'connected') {
                    return $id;
                }
            } catch (\Throwable $e) {
                // Continue to the next candidate
            }
        }

        return $candidates[0];
    }
}
