<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VoiceSession extends Model
{
    protected $fillable = [
        'user_id',
        'voice_session_id',
        'channel_name',
        'agora_uid',
        'agora_agent_uid',
        'agora_agent_id',
        'status',
        'started_at',
        'ended_at',
        'duration_seconds',
        'browser',
        'platform',
        'ip_address',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $session) {
            if (empty($session->voice_session_id)) {
                $session->voice_session_id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(VoiceMessage::class)->orderBy('created_at', 'asc');
    }

    public function end(?int $duration = null): void
    {
        $this->update([
            'status' => 'ended',
            'ended_at' => now(),
            'duration_seconds' => $duration ?? (int) ($this->started_at?->diffInSeconds(now()) ?? 0),
        ]);
    }
}
