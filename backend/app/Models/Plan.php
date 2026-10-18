<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'messages_limit',
        'sessions_limit',
        'price',
        'currency',
        'interval',
        'is_active',
        'is_free',
        'is_popular',
        'sort_order',
        'description',
        'features',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_free' => 'boolean',
        'is_popular' => 'boolean',
        'features' => 'array',
        'metadata' => 'array',
    ];

    public const INTERVAL_MONTH = 'month';
    public const INTERVAL_YEAR = 'year';
    public const INTERVAL_ONE_TIME = 'one_time';

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function getFormattedPriceAttribute(): string
    {
        if ($this->is_free || $this->price <= 0) {
            return 'مجانًا';
        }

        return $this->price . ' ' . ($this->currency === 'USD' ? '$' : $this->currency);
    }
}
