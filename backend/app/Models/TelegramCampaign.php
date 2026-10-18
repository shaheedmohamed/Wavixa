<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramCampaign extends Model
{
    protected $table = 'telegram_campaigns';

    const STATUS_DRAFT = 'draft';
    const STATUS_RUNNING = 'running';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'telegram_session_id',
        'name',
        'message',
        'type',
        'media_url',
        'button_text',
        'button_url',
        'parse_mode',
        'status',
        'total_recipients',
        'success_count',
        'failed_count',
        'pending_count',
        'delay_seconds',
        'scheduled_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'total_recipients' => 'integer',
        'success_count' => 'integer',
        'failed_count' => 'integer',
        'pending_count' => 'integer',
        'delay_seconds' => 'integer',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TelegramSession::class, 'telegram_session_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(TelegramCampaignRecipient::class, 'campaign_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class, 'campaign_id');
    }
}
