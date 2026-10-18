<?php

namespace App\Modules\MetaTest\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaTestEvent extends Model
{
    use HasFactory;

    protected $table = 'meta_test_events';

    protected $fillable = [
        'user_id',
        'event_type',
        'payload',
        'phone_number',
        'meta_message_id',
        'waba_id',
        'status',
        'error_message',
        'meta_signature',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DUPLICATE = 'duplicate';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
