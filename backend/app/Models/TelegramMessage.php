<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramMessage extends Model
{
    protected $table = 'telegram_messages';

    protected $fillable = [
        'user_id',
        'telegram_session_id',
        'chat_id',
        'telegram_message_id',
        'direction',
        'type',
        'message',
        'media_url',
        'parse_mode',
        'button_text',
        'button_url',
        'status',
        'error_message',
        'campaign_id',
        'scheduled_at',
        'sent_at',
        'cost',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'cost' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TelegramSession::class, 'telegram_session_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(TelegramCampaign::class, 'campaign_id');
    }
}
