<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppCampaignRecipient extends Model
{
    protected $table = 'whatsapp_campaign_recipients';

    protected $fillable = [
        'campaign_id',
        'name',
        'phone',
        'is_valid',
        'is_duplicate',
        'error_message',
        'status',
        'message_id',
        'sent_at',
        'failed_at',
        'delivered_at',
        'read_at',
        'attempts',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'is_duplicate' => 'boolean',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class);
    }
}
