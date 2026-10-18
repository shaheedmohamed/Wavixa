<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'whatsapp_session_id',
        'meta_phone_number_id',
        'allow_remote_pairing',
        'remote_pairing_limit',
        'name',
        'key',
        'prefix',
        'permissions',
        'last_used_at',
        'requests_count',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'requests_count' => 'integer',
        'permissions' => 'array',
    ];

    protected $hidden = [
        'key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function whatsappSession(): BelongsTo
    {
        return $this->belongsTo(WhatsAppSession::class);
    }

    public function metaPhoneNumber(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\MetaTest\Models\MetaTestPhoneNumber::class, 'meta_phone_number_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApiLog::class);
    }

    public function pairedSessions(): HasMany
    {
        return $this->hasMany(WhatsAppSession::class, 'api_key_id');
    }

    /**
     * Generate a new secure API key
     */
    public static function generateKey(): string
    {
        return 'wav_' . Str::random(48);
    }

    /**
     * Generate key prefix for display
     */
    public static function generatePrefix(string $key): string
    {
        return substr($key, 0, 12);
    }

    /**
     * Check if the API key is active and not expired
     */
    public function isUsable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Increment usage count and update last used timestamp
     */
    public function markUsed(): void
    {
        $this->increment('requests_count');
        $this->update(['last_used_at' => now()]);
    }
}
