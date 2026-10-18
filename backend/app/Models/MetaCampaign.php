<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'meta_ad_account_id',
        'meta_campaign_id',
        'name',
        'objective',
        'status',
        'buying_type',
        'daily_budget',
        'lifetime_budget',
        'start_time',
        'stop_time',
        'special_ad_categories',
        'raw_data',
    ];

    protected $casts = [
        'daily_budget' => 'decimal:2',
        'lifetime_budget' => 'decimal:2',
        'start_time' => 'datetime',
        'stop_time' => 'datetime',
        'special_ad_categories' => 'array',
        'raw_data' => 'array',
    ];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class, 'meta_ad_account_id');
    }

    public function adSets(): HasMany
    {
        return $this->hasMany(MetaAdSet::class);
    }

    public function insights(): HasMany
    {
        return $this->hasMany(MetaInsight::class);
    }
}
