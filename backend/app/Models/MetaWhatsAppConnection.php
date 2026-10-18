<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaWhatsAppConnection extends Model
{
    protected $table = 'meta_whatsapp_connections';

    protected $fillable = [
        'user_id',
        'waba_id',
        'business_portfolio_id',
        'business_portfolio_name',
        'access_token',
        'token_expires_at',
        'scopes',
        'status',
        'error_message',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'scopes' => 'array',
        'token_expires_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_RESTRICTED = 'restricted';
    public const STATUS_STOPPED = 'stopped';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wabas(): HasMany
    {
        return $this->hasMany(MetaWhatsAppWaba::class, 'connection_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(MetaWhatsAppCampaign::class, 'connection_id');
    }
}
