<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramAutoReplyLog extends Model
{
    protected $table = 'telegram_auto_reply_logs';

    protected $fillable = [
        'telegram_auto_reply_id',
        'user_id',
        'telegram_session_id',
        'chat_id',
        'sender_name',
        'incoming_text',
        'reply_text',
        'mode',
        'model',
        'tokens_used',
        'status',
    ];

    protected $casts = [
        'tokens_used' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TelegramSession::class, 'telegram_session_id');
    }

    public function autoReply(): BelongsTo
    {
        return $this->belongsTo(TelegramAutoReply::class, 'telegram_auto_reply_id');
    }
}
