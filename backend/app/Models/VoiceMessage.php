<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceMessage extends Model
{
    public const SPEAKER_USER = 'user';
    public const SPEAKER_AI = 'ai';

    protected $fillable = [
        'voice_session_id',
        'user_id',
        'speaker',
        'text',
        'status',
        'provider',
        'provider_model',
        'tokens_input',
        'tokens_output',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'tokens_input' => 'integer',
        'tokens_output' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(VoiceSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUser(): bool
    {
        return $this->speaker === self::SPEAKER_USER;
    }

    public function isAi(): bool
    {
        return $this->speaker === self::SPEAKER_AI;
    }
}
