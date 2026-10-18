<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class MetaWhatsAppCampaign extends Model
{
    protected $table = 'meta_whatsapp_campaigns';

    protected $fillable = [
        'user_id',
        'connection_id',
        'waba_id',
        'phone_number_id',
        'template_id',
        'name',
        'message_type',
        'status',
        'total_recipients',
        'valid_recipients',
        'invalid_recipients',
        'queued_count',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
        'error_message',
        'started_at',
        'completed_at',
        'paused_at',
        'opt_in_confirmed',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'paused_at' => 'datetime',
        'opt_in_confirmed' => 'boolean',
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

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MetaWhatsAppConnection::class, 'connection_id');
    }

    public function waba(): BelongsTo
    {
        return $this->belongsTo(MetaWhatsAppWaba::class, 'waba_id');
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(MetaWhatsAppPhoneNumber::class, 'phone_number_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MetaWhatsAppTemplate::class, 'template_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MetaWhatsAppCampaignRecipient::class, 'campaign_id');
    }

    public function recomputeStats(): void
    {
        DB::transaction(function () {
            $campaign = static::where('id', $this->id)->lockForUpdate()->firstOrFail();

            $total = $campaign->recipients()->count();
            $queued = $campaign->recipients()->where('status', MetaWhatsAppCampaignRecipient::STATUS_QUEUED)->count();
            $sent = $campaign->recipients()->where('status', MetaWhatsAppCampaignRecipient::STATUS_SENT)->count();
            $delivered = $campaign->recipients()->where('status', MetaWhatsAppCampaignRecipient::STATUS_DELIVERED)->count();
            $read = $campaign->recipients()->where('status', MetaWhatsAppCampaignRecipient::STATUS_READ)->count();
            $failed = $campaign->recipients()->where('status', MetaWhatsAppCampaignRecipient::STATUS_FAILED)->count();
            $pending = $campaign->recipients()->where('status', MetaWhatsAppCampaignRecipient::STATUS_PENDING)->count();

            $data = [
                'total_recipients' => $total,
                'queued_count' => $queued,
                'sent_count' => $sent,
                'delivered_count' => $delivered,
                'read_count' => $read,
                'failed_count' => $failed,
                'pending_count' => $pending,
                'valid_recipients' => $total,
            ];

            if (in_array($campaign->status, [self::STATUS_RUNNING, self::STATUS_PAUSED]) && $pending === 0 && $queued === 0) {
                $data['status'] = self::STATUS_COMPLETED;
                $data['completed_at'] = now();
            }

            $campaign->update($data);
        });

        $this->refresh();
    }
}
