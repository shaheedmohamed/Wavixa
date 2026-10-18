<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppConversation extends Model
{
    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'user_id',
        'session_id',
        'contact_jid',
        'contact_phone',
        'contact_name',
        'contact_username',
        'contact_avatar',
        'ai_disabled_until',
        'last_message_at',
        'unread_count',
        'last_message',
        'last_message_type',
        'last_message_direction',
        'is_pinned',
        'assigned_agent_id',
        'assigned_at',
        'resolved_at',
        'resolved_by',
        'rating',
        'rating_note',
    ];

    protected $casts = [
        'ai_disabled_until' => 'datetime',
        'last_message_at' => 'datetime',
        'unread_count' => 'integer',
        'is_pinned' => 'boolean',
        'assigned_agent_id' => 'integer',
        'assigned_at' => 'datetime',
        'resolved_at' => 'datetime',
        'resolved_by' => 'integer',
        'rating' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WhatsAppSession::class, 'session_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WhatsAppAutoReplyLog::class, 'conversation_id');
    }
}
