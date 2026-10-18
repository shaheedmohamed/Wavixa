<?php

namespace App\Modules\MetaTest\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaTestApiLog extends Model
{
    use HasFactory;

    protected $table = 'meta_test_api_logs';

    protected $fillable = [
        'user_id',
        'method',
        'endpoint',
        'request_payload',
        'response_payload',
        'http_status',
        'meta_error_code',
        'meta_error_message',
        'duration_ms',
        'success',
        'requested_at',
        'responded_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'success' => 'boolean',
        'requested_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at')->limit(100);
    }
}
