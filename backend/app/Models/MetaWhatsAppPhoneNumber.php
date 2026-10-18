<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaWhatsAppPhoneNumber extends Model
{
    protected $table = 'meta_whatsapp_phone_numbers';

    protected $fillable = [
        'user_id',
        'waba_id',
        'meta_phone_number_id',
        'phone_number',
        'display_name',
        'quality_rating',
        'status',
        'is_active',
        'raw_data',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'raw_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function waba(): BelongsTo
    {
        return $this->belongsTo(MetaWhatsAppWaba::class, 'waba_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(MetaWhatsAppCampaign::class, 'phone_number_id');
    }
}
