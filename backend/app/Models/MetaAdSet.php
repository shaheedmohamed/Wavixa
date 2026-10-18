<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaAdSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'meta_campaign_id',
        'meta_ad_account_id',
        'meta_ad_set_id',
        'name',
        'status',
        'daily_budget',
        'lifetime_budget',
        'optimization_goal',
        'billing_event',
        'bid_amount',
        'start_time',
        'end_time',
        'targeting',
        'promoted_object',
        'raw_data',
    ];

    protected $casts = [
        'daily_budget' => 'decimal:2',
        'lifetime_budget' => 'decimal:2',
        'bid_amount' => 'decimal:2',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'targeting' => 'array',
        'promoted_object' => 'array',
        'raw_data' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MetaCampaign::class, 'meta_campaign_id');
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class, 'meta_ad_account_id');
    }

    public function ads(): HasMany
    {
        return $this->hasMany(MetaAd::class);
    }

    public function insights(): HasMany
    {
        return $this->hasMany(MetaInsight::class);
    }
}
