<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppAutoReply extends Model
{
    protected $table = 'whatsapp_auto_replies';

    protected $fillable = [
        'user_id',
        'session_id',
        'is_enabled',
        'mode',
        'reply_message',
        'reply_once',
        'delay_seconds',
        'total_replies_sent',
        'last_reply_at',
        'ai_system_prompt',
        'ai_business_info',
        'ai_tone',
        'ai_language',
        'ai_context_limit',
        'ai_fallback_enabled',
        'ai_fallback_message',
        'ai_max_daily_replies',
        'ai_total_replies_sent',
        'ai_daily_replies_sent',
        'ai_replies_today_date',
        'ai_last_reply_at',
        'ai_business_hours_enabled',
        'ai_business_hours_from',
        'ai_business_hours_to',
        'ai_model',
        'ai_handoff_minutes',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'reply_once' => 'boolean',
        'delay_seconds' => 'integer',
        'total_replies_sent' => 'integer',
        'last_reply_at' => 'datetime',
        'ai_context_limit' => 'integer',
        'ai_fallback_enabled' => 'boolean',
        'ai_max_daily_replies' => 'integer',
        'ai_total_replies_sent' => 'integer',
        'ai_daily_replies_sent' => 'integer',
        'ai_replies_today_date' => 'date',
        'ai_last_reply_at' => 'datetime',
        'ai_business_hours_enabled' => 'boolean',
        'ai_business_hours_from' => 'string',
        'ai_business_hours_to' => 'string',
        'ai_handoff_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WhatsAppSession::class, 'session_id');
    }

    public function isAi(): bool
    {
        return $this->mode === 'ai';
    }

    public function isStatic(): bool
    {
        return $this->mode !== 'ai';
    }

    /**
     * Reset the daily AI counter if the stored date is not today.
     */
    public function refreshDailyCounter(): void
    {
        if ($this->ai_replies_today_date?->toDateString() !== now()->toDateString()) {
            $this->ai_daily_replies_sent = 0;
            $this->ai_replies_today_date = now()->toDateString();
            $this->save();
        }
    }
}
