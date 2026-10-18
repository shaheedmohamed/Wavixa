<?php

namespace App\Modules\MetaTest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaTestCampaignRecipient extends Model
{
    protected $table = 'meta_test_campaign_recipients';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'campaign_id',
        'name',
        'phone',
        'is_valid',
        'status',
        'meta_message_id',
        'message_id',
        'error_message',
        'error_code',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'attempts',
        'variables',
        'custom_text',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'variables' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MetaTestCampaign::class, 'campaign_id');
    }
}
