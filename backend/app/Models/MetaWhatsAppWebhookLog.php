<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaWhatsAppWebhookLog extends Model
{
    protected $table = 'meta_whatsapp_webhook_logs';

    protected $fillable = [
        'user_id',
        'event_type',
        'payload',
        'status',
        'error_message',
        'meta_signature',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
