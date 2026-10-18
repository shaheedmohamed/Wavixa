<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramCampaignRecipient extends Model
{
    protected $table = 'telegram_campaign_recipients';

    protected $fillable = [
        'campaign_id',
        'chat_id',
        'name',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(TelegramCampaign::class, 'campaign_id');
    }
}
