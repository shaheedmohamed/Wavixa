<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaWhatsAppWaba extends Model
{
    protected $table = 'meta_whatsapp_wabas';

    protected $fillable = [
        'user_id',
        'connection_id',
        'meta_waba_id',
        'name',
        'timezone',
        'currency',
        'status',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MetaWhatsAppConnection::class, 'connection_id');
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(MetaWhatsAppPhoneNumber::class, 'waba_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(MetaWhatsAppTemplate::class, 'waba_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(MetaWhatsAppCampaign::class, 'waba_id');
    }
}
