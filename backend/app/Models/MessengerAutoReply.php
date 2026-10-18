<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessengerAutoReply extends Model
{
    protected $table = 'messenger_auto_replies';

    public const MODE_AI = 'ai';
    public const MODE_RULES = 'rules';

    protected $fillable = [
        'user_id',
        'page_id',
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
        'rules' => 'array',
        'delay_seconds' => 'integer',
        'total_replies_sent' => 'integer',
        'last_reply_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(MessengerPage::class, 'page_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MessengerAutoReplyLog::class, 'messenger_auto_reply_id');
    }
}
