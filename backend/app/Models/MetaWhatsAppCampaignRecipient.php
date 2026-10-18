<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaWhatsAppCampaignRecipient extends Model
{
    protected $table = 'meta_whatsapp_campaign_recipients';

    protected $fillable = [
        'campaign_id',
        'name',
        'phone',
        'is_valid',
        'status',
        'meta_message_id',
        'error_message',
        'error_code',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'attempts',
        'variables',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'variables' => 'array',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MetaWhatsAppCampaign::class, 'campaign_id');
    }
}
