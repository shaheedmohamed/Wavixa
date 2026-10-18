<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class N8nConnection extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'url',
        'api_key',
        'prefix',
        'status',
        'last_error',
        'last_tested_at',
        'last_used_at',
        'workflows_count',
        'requests_count',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'workflows_count' => 'integer',
        'requests_count' => 'integer',
        'last_tested_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generateApiKey(): string
    {
        return 'n8n_' . Str::random(48);
    }

    public static function generatePrefix(string $key): string
    {
        return substr($key, 0, 12);
    }

    public function maskedApiKey(): string
    {
        return $this->prefix . '...';
    }

    public function markUsed(): void
    {
        $this->increment('requests_count');
        $this->update(['last_used_at' => now()]);
    }
}
