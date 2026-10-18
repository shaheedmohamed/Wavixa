<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TelegramSession extends Model
{
    protected $table = 'telegram_sessions';

    protected $fillable = [
        'user_id',
        'phone_number',
        'bot_token',
        'bot_username',
        'bot_id',
        'first_name',
        'device_name',
        'session_token',
        'status',
        'webhook_url',
        'webhook_set',
        'can_join_groups',
        'can_read_all_group_messages',
        'supports_inline_queries',
        'messages_sent_count',
        'connected_at',
        'last_activity_at',
    ];

    protected $casts = [
        'webhook_set' => 'boolean',
        'can_join_groups' => 'boolean',
        'can_read_all_group_messages' => 'boolean',
        'supports_inline_queries' => 'boolean',
        'messages_sent_count' => 'integer',
        'connected_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    protected $hidden = [
        'bot_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class, 'telegram_session_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(TelegramCampaign::class, 'telegram_session_id');
    }

    public function autoReply(): HasOne
    {
        return $this->hasOne(TelegramAutoReply::class, 'telegram_session_id');
    }

    public function autoReplyLogs(): HasMany
    {
        return $this->hasMany(TelegramAutoReplyLog::class, 'telegram_session_id');
    }
}
