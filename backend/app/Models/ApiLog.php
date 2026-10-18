<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'api_key_id',
        'user_id',
        'method',
        'endpoint',
        'ip_address',
        'user_agent',
        'status_code',
        'duration_ms',
        'request_size',
        'response_size',
        'country',
        'city',
        'response_message',
        'request_data',
    ];

    protected $casts = [
        'request_data' => 'array',
    ];

    public $timestamps = false;

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
