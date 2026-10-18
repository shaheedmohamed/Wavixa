<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaCreative extends Model
{
    use HasFactory;

    protected $fillable = [
        'meta_ad_account_id',
        'meta_creative_id',
        'name',
        'page_id',
        'instagram_actor_id',
        'title',
        'body',
        'description',
        'image_url',
        'image_hash',
        'video_id',
        'call_to_action_type',
        'destination_url',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class, 'meta_ad_account_id');
    }

    public function ads(): HasMany
    {
        return $this->hasMany(MetaAd::class);
    }
}
