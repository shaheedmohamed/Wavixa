<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessengerCampaignRecipient extends Model
{
    protected $table = 'messenger_campaign_recipients';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXCLUDED = 'excluded';

    protected $fillable = [
        'campaign_id',
        'psid',
        'name',
        'status',
        'error_message',
        'sent_at',
        'external_message_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MessengerCampaign::class, 'campaign_id');
    }
}
