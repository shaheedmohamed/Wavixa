<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class WhatsAppCampaign extends Model
{
    protected $table = 'whatsapp_campaigns';

    protected $fillable = [
        'user_id',
        'session_id',
        'name',
        'message',
        'message_variations',
        'status',
        'total_recipients',
        'valid_recipients',
        'invalid_recipients',
        'duplicate_recipients',
        'sent_count',
        'success_count',
        'failed_count',
        'pending_count',
        'progress',
        'delay_min',
        'delay_max',
        'concurrent',
        'recipients_data',
        'started_at',
        'completed_at',
        'paused_at',
        'error_message',
        'consent_checkbox',
    ];

    protected $casts = [
        'recipients_data' => 'array',
        'message_variations' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'paused_at' => 'datetime',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WhatsAppSession::class, 'session_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignRecipient::class, 'campaign_id');
    }

    public function recomputeStats(): void
    {
        DB::transaction(function () {
            $campaign = static::where('id', $this->id)->lockForUpdate()->firstOrFail();

            $total = $campaign->recipients()->count();
            $sent = $campaign->recipients()->whereIn('status', ['sent', 'delivered', 'read'])->count();
            $success = $campaign->recipients()->whereIn('status', ['sent', 'delivered', 'read'])->count();
            $failed = $campaign->recipients()->where('status', 'failed')->count();
            $pending = $campaign->recipients()->whereIn('status', ['pending', 'queued'])->count();

            $data = [
                'total_recipients' => $total,
                'sent_count' => $sent,
                'success_count' => $success,
                'failed_count' => $failed,
                'pending_count' => $pending,
                'progress' => $total > 0 ? (int) round(($sent + $failed) / $total * 100) : 0,
            ];

            if (in_array($campaign->status, [self::STATUS_RUNNING, self::STATUS_PAUSED]) && $pending === 0) {
                $data['status'] = self::STATUS_COMPLETED;
                $data['completed_at'] = now();
            }

            $campaign->update($data);
        });

        $this->refresh();
    }
}
