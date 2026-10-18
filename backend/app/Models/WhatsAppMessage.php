<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'user_id',
        'api_key_id',
        'session_id',
        'conversation_id',
        'campaign_id',
        'campaign_recipient_id',
        'from',
        'to',
        'message',
        'media_url',
        'media_meta',
        'media_caption',
        'media_mime_type',
        'media_size',
        'reactions',
        'reply_to_id',
        'message_id',
        'job_id',
        'message_type',
        'direction',
        'status',
        'attempts',
        'error_message',
        'timestamp',
        'queued_at',
        'processed_at',
        'completed_at',
        'failed_at',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'revoked_at',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'queued_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'revoked_at' => 'datetime',
        'reactions' => 'array',
        'media_meta' => 'array',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RETRYING = 'retrying';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_SCHEDULED = 'scheduled';

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WhatsAppSession::class, 'session_id');
    }
}
