<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'meta_ad_account_id',
        'meta_campaign_id',
        'meta_ad_set_id',
        'meta_ad_id',
        'date',
        'spend',
        'impressions',
        'reach',
        'clicks',
        'cpc',
        'cpm',
        'ctr',
        'conversions',
        'cpa',
        'roas',
        'purchase_value',
        'raw_data',
    ];

    protected $casts = [
        'date' => 'date',
        'spend' => 'decimal:2',
        'impressions' => 'integer',
        'reach' => 'integer',
        'clicks' => 'integer',
        'cpc' => 'decimal:4',
        'cpm' => 'decimal:4',
        'ctr' => 'decimal:4',
        'conversions' => 'integer',
        'cpa' => 'decimal:4',
        'roas' => 'decimal:4',
        'purchase_value' => 'decimal:2',
        'raw_data' => 'array',
    ];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class, 'meta_ad_account_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MetaCampaign::class, 'meta_campaign_id');
    }

    public function adSet(): BelongsTo
    {
        return $this->belongsTo(MetaAdSet::class, 'meta_ad_set_id');
    }

    public function ad(): BelongsTo
    {
        return $this->belongsTo(MetaAd::class, 'meta_ad_id');
    }
}
