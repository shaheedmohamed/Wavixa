<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppAutoReplyLog extends Model
{
    protected $table = 'whatsapp_auto_reply_logs';

    protected $fillable = [
        'user_id',
        'session_id',
        'conversation_id',
        'incoming_message_id',
        'sender_phone',
        'sender_name',
        'incoming_message',
        'reply_message',
        'status',
        'status_reason',
        'outgoing_message_id',
        'ai_tokens_input',
        'ai_tokens_output',
        'ai_model',
    ];

    protected $casts = [
        'ai_tokens_input' => 'integer',
        'ai_tokens_output' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WhatsAppSession::class, 'session_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function outgoingMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessage::class, 'outgoing_message_id');
    }

    public function incomingMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessage::class, 'incoming_message_id');
    }
}
