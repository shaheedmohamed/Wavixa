<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramAutoReply extends Model
{
    protected $table = 'telegram_auto_replies';

    protected $fillable = [
        'user_id',
        'telegram_session_id',
        'is_enabled',
        'mode',
        'welcome_message',
        'reply_once',
        'delay_seconds',
        'rules',
        'ai_system_prompt',
        'ai_business_info',
        'ai_tone',
        'ai_language',
        'ai_model',
        'ai_fallback_message',
        'total_replies_sent',
        'last_reply_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'reply_once' => 'boolean',
        'delay_seconds' => 'integer',
        'rules' => 'array',
        'total_replies_sent' => 'integer',
        'last_reply_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TelegramSession::class, 'telegram_session_id');
    }
}
