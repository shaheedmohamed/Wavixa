<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessengerPage extends Model
{
    protected $table = 'messenger_pages';

    protected $fillable = [
        'user_id',
        'page_id',
        'page_name',
        'access_token',
        'status',
        'connected_at',
        'last_sync_at',
        'last_activity_at',
        'webhook_set',
        'messages_sent_count',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'webhook_set' => 'boolean',
        'connected_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'messages_sent_count' => 'integer',
    ];

    protected $hidden = [
        'access_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(MessengerConversation::class, 'page_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessengerMessage::class, 'page_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(MessengerContact::class, 'page_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(MessengerCampaign::class, 'page_id');
    }

    public function autoReply(): HasMany
    {
        return $this->hasMany(MessengerAutoReply::class, 'page_id');
    }

    /**
     * Decrypt the page access token on demand.
     */
    public function getAccessToken(): ?string
    {
        $raw = $this->getRawOriginal('access_token');
        if (empty($raw)) {
            return null;
        }

        try {
            return decrypt($raw);
        } catch (\Throwable $e) {
            return $raw;
        }
    }

    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = !is_null($value) ? encrypt($value) : null;
    }

    public function scopeConnected($query)
    {
        return $query->where('status', 'connected');
    }
}
