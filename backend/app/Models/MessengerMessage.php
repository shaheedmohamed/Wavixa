<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessengerMessage extends Model
{
    protected $table = 'messenger_messages';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'page_id',
        'conversation_id',
        'psid',
        'direction',
        'message_type',
        'message',
        'media_url',
        'external_message_id',
        'status',
        'error_message',
        'campaign_id',
        'campaign_recipient_id',
        'cost',
        'metadata',
        'sent_at',
        'delivered_at',
        'failed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'cost' => 'integer',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(MessengerPage::class, 'page_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(MessengerConversation::class, 'conversation_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MessengerCampaign::class, 'campaign_id');
    }

    public function campaignRecipient(): BelongsTo
    {
        return $this->belongsTo(MessengerCampaignRecipient::class, 'campaign_recipient_id');
    }
}
