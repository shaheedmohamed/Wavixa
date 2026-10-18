<?php

namespace App\Modules\MetaTest\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaTestMessage extends Model
{
    use HasFactory;

    protected $table = 'meta_test_messages';

    protected $fillable = [
        'user_id',
        'api_key_id',
        'phone_number_id',
        'meta_message_id',
        'conversation_id',
        'pricing_category',
        'pricing_type',
        'pricing_model',
        'pricing_billable',
        'direction',
        'from_phone',
        'to_phone',
        'body',
        'message_type',
        'raw_payload',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'error_message',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public const DIRECTION_INCOMING = 'incoming';
    public const DIRECTION_OUTGOING = 'outgoing';

    public const STATUS_RECEIVED = 'received';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(MetaTestPhoneNumber::class, 'phone_number_id');
    }
}
