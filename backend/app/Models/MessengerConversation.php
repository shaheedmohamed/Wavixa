<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessengerConversation extends Model
{
    protected $table = 'messenger_conversations';

    protected $fillable = [
        'user_id',
        'page_id',
        'contact_id',
        'psid',
        'ai_disabled_until',
        'last_message_at',
        'messaging_window_until',
    ];

    protected $casts = [
        'ai_disabled_until' => 'datetime',
        'last_message_at' => 'datetime',
        'messaging_window_until' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(MessengerPage::class, 'page_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(MessengerContact::class, 'contact_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessengerMessage::class, 'conversation_id');
    }

    public function isAiDisabled(): bool
    {
        return $this->ai_disabled_until && $this->ai_disabled_until->isFuture();
    }

    public function isMessagingWindowOpen(): bool
    {
        return !$this->messaging_window_until || $this->messaging_window_until->isFuture();
    }
}
