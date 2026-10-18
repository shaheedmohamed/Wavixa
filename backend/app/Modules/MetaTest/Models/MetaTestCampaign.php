<?php

namespace App\Modules\MetaTest\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class MetaTestCampaign extends Model
{
    protected $table = 'meta_test_campaigns';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const TYPE_MARKETING = 'marketing';
    public const TYPE_UTILITY = 'utility';
    public const TYPE_AUTHENTICATION = 'authentication';
    public const TYPE_TEXT = 'text';

    protected $fillable = [
        'user_id',
        'api_key_id',
        'connection_id',
        'waba_id',
        'phone_number_id',
        'template_id',
        'name',
        'message_type',
        'text',
        'status',
        'total_recipients',
        'valid_recipients',
        'invalid_recipients',
        'queued_count',
        'pending_count',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
        'delay_seconds',
        'opt_in_confirmed',
        'error_message',
        'started_at',
        'completed_at',
        'paused_at',
    ];

    protected $casts = [
        'opt_in_confirmed' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'paused_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MetaTestConnection::class, 'connection_id');
    }

    public function waba(): BelongsTo
    {
        return $this->belongsTo(MetaTestWaba::class, 'waba_id');
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(MetaTestPhoneNumber::class, 'phone_number_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MetaTestTemplate::class, 'template_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MetaTestCampaignRecipient::class, 'campaign_id');
    }

    /**
     * Recalculate counters from the recipients table. Marks the campaign
     * completed when nothing is left pending while running/paused.
     */
    public function recomputeStats(): void
    {
        DB::transaction(function () {
            $campaign = static::where('id', $this->id)->lockForUpdate()->firstOrFail();

            $counts = $campaign->recipients()
                ->selectRaw("status, COUNT(*) as c")
                ->groupBy('status')
                ->pluck('c', 'status');

            $pending = (int) ($counts[MetaTestCampaignRecipient::STATUS_PENDING] ?? 0);
            $sent = (int) ($counts[MetaTestCampaignRecipient::STATUS_SENT] ?? 0);
            $delivered = (int) ($counts[MetaTestCampaignRecipient::STATUS_DELIVERED] ?? 0);
            $read = (int) ($counts[MetaTestCampaignRecipient::STATUS_READ] ?? 0);
            $failed = (int) ($counts[MetaTestCampaignRecipient::STATUS_FAILED] ?? 0);
            $total = $pending + $sent + $delivered + $read + $failed;

            $data = [
                'total_recipients' => $total,
                'pending_count' => $pending,
                'sent_count' => $sent,
                'delivered_count' => $delivered,
                'read_count' => $read,
                'failed_count' => $failed,
            ];

            if ($pending === 0
                && in_array($campaign->status, [self::STATUS_RUNNING, self::STATUS_PAUSED], true)
                && $total > 0
            ) {
                $data['status'] = self::STATUS_COMPLETED;
                $data['completed_at'] = now();
            }

            $campaign->update($data);
        });

        $this->refresh();
    }
}
