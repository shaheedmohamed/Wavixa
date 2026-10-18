<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaAd extends Model
{
    use HasFactory;

    protected $fillable = [
        'meta_ad_set_id',
        'meta_creative_id',
        'meta_ad_account_id',
        'meta_ad_id',
        'name',
        'status',
        'effective_status',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function adSet(): BelongsTo
    {
        return $this->belongsTo(MetaAdSet::class, 'meta_ad_set_id');
    }

    public function creative(): BelongsTo
    {
        return $this->belongsTo(MetaCreative::class, 'meta_creative_id');
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class, 'meta_ad_account_id');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(MetaInsight::class);
    }
}
