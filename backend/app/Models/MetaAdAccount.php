<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaAdAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'meta_connection_id',
        'meta_account_id',
        'name',
        'currency',
        'timezone_name',
        'account_status',
        'is_active',
        'min_daily_budget',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'min_daily_budget' => 'decimal:2',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MetaConnection::class, 'meta_connection_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(MetaCampaign::class);
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(MetaCreative::class);
    }

    public function pixels(): HasMany
    {
        return $this->hasMany(MetaPixel::class);
    }

    public function insights(): HasMany
    {
        return $this->hasMany(MetaInsight::class);
    }
}
