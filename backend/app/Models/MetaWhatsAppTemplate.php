<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaWhatsAppTemplate extends Model
{
    protected $table = 'meta_whatsapp_templates';

    protected $fillable = [
        'user_id',
        'waba_id',
        'meta_template_id',
        'name',
        'category',
        'language',
        'status',
        'components',
        'raw_data',
    ];

    protected $casts = [
        'components' => 'array',
        'raw_data' => 'array',
    ];

    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_PAUSED = 'PAUSED';

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
        return $this->hasMany(MetaWhatsAppCampaign::class, 'template_id');
    }
}
