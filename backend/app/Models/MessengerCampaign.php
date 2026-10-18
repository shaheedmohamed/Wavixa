<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessengerCampaign extends Model
{
    protected $table = 'messenger_campaigns';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'page_id',
        'name',
        'message',
        'status',
        'total_recipients',
        'valid_recipients',
        'invalid_recipients',
        'duplicate_recipients',
        'sent_count',
        'success_count',
        'failed_count',
        'pending_count',
        'excluded_count',
        'delay_min',
        'delay_max',
        'scheduled_at',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(MessengerPage::class, 'page_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MessengerCampaignRecipient::class, 'campaign_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessengerMessage::class, 'campaign_id');
    }

    public function recomputeStats(): void
    {
        $totals = $this->recipients()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = "excluded" THEN 1 ELSE 0 END) as excluded
            ')
            ->first();

        $this->update([
            'sent_count' => (int) $totals->success + (int) $totals->failed,
            'success_count' => (int) $totals->success,
            'failed_count' => (int) $totals->failed,
            'pending_count' => (int) $totals->pending,
            'excluded_count' => (int) $totals->excluded,
        ]);
    }
}
