<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessengerAutoReplyLog extends Model
{
    protected $table = 'messenger_auto_reply_logs';

    protected $fillable = [
        'messenger_auto_reply_id',
        'user_id',
        'page_id',
        'conversation_id',
        'psid',
        'incoming_text',
        'reply_text',
        'mode',
        'model',
        'tokens_used',
        'status',
        'error_message',
    ];

    protected $casts = [
        'tokens_used' => 'integer',
    ];

    public function autoReply(): BelongsTo
    {
        return $this->belongsTo(MessengerAutoReply::class, 'messenger_auto_reply_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(MessengerPage::class, 'page_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(MessengerConversation::class, 'conversation_id');
    }
}
