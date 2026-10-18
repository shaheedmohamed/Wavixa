<?php

namespace App\Services;

use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppSession;
use App\Models\FraudAlert;
use Illuminate\Support\Facades\Log;

class UserReputationService
{
    public const LEVEL_LOW = 'Low Risk';
    public const LEVEL_NORMAL = 'Normal';
    public const LEVEL_WARNING = 'Warning';
    public const LEVEL_LIMITED = 'Limited';
    public const LEVEL_CAMPAIGN_LOCKED = 'Campaign Locked';
    public const LEVEL_SUSPENDED = 'Account Suspended';

    public static function calculate(User $user): array
    {
        $factors = [];
        $score = 0;

        // 1. Account Age
        $accountAgeDays = (int) $user->created_at->diffInDays(now());
        $factors['account_age_days'] = $accountAgeDays;
        if ($accountAgeDays < 1) {
            $factors['account_age_points'] = 10;
        } elseif ($accountAgeDays < 7) {
            $factors['account_age_points'] = 7;
        } elseif ($accountAgeDays < 30) {
            $factors['account_age_points'] = 4;
        } else {
            $factors['account_age_points'] = 0;
        }
        $score += $factors['account_age_points'];

        // 2. Message Volume
        $volume24h = WhatsAppMessage::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();
        $volume7d = WhatsAppMessage::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $factors['message_volume_24h'] = $volume24h;
        $factors['message_volume_7d'] = $volume7d;

        if ($volume24h > 1000) {
            $factors['message_volume_points'] = 20;
        } elseif ($volume24h > 500) {
            $factors['message_volume_points'] = 15;
        } elseif ($volume24h > 200) {
            $factors['message_volume_points'] = 10;
        } elseif ($volume24h > 50) {
            $factors['message_volume_points'] = 5;
        } else {
            $factors['message_volume_points'] = 0;
        }
        $score += $factors['message_volume_points'];

        // 3. Failed Messages
        $total7d = max(1, $volume7d);
        $failed7d = WhatsAppMessage::where('user_id', $user->id)
            ->where('status', WhatsAppMessage::STATUS_FAILED)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $failRatio = $failed7d / $total7d;
        $factors['failed_messages_7d'] = $failed7d;
        $factors['failed_messages_ratio'] = round($failRatio, 4);

        if ($failRatio >= 0.50) {
            $factors['failed_messages_points'] = 20;
        } elseif ($failRatio >= 0.30) {
            $factors['failed_messages_points'] = 12;
        } elseif ($failRatio >= 0.15) {
            $factors['failed_messages_points'] = 8;
        } elseif ($failRatio >= 0.05) {
            $factors['failed_messages_points'] = 3;
        } else {
            $factors['failed_messages_points'] = 0;
        }
        $score += $factors['failed_messages_points'];

        // 4. Reply Rate
        $outgoing7d = max(1, WhatsAppMessage::where('user_id', $user->id)
            ->where('direction', 'outgoing')
            ->where('created_at', '>=', now()->subDays(7))
            ->count());
        $incoming7d = WhatsAppMessage::where('user_id', $user->id)
            ->where('direction', 'incoming')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $replyRate = $incoming7d / $outgoing7d;
        $factors['reply_rate'] = round($replyRate, 4);

        if ($replyRate < 0.05) {
            $factors['reply_rate_points'] = 20;
        } elseif ($replyRate < 0.15) {
            $factors['reply_rate_points'] = 12;
        } elseif ($replyRate < 0.30) {
            $factors['reply_rate_points'] = 8;
        } elseif ($replyRate < 0.50) {
            $factors['reply_rate_points'] = 3;
        } else {
            $factors['reply_rate_points'] = 0;
        }
        $score += $factors['reply_rate_points'];

        // 5. New Contacts Ratio
        $uniqueTo7d = WhatsAppMessage::where('user_id', $user->id)
            ->where('direction', 'outgoing')
            ->where('created_at', '>=', now()->subDays(7))
            ->distinct('to')
            ->count('to');
        $newContactsRatio = $outgoing7d > 0 ? $uniqueTo7d / $outgoing7d : 0;
        $factors['new_contacts_7d'] = $uniqueTo7d;
        $factors['new_contacts_ratio'] = round($newContactsRatio, 4);

        if ($newContactsRatio >= 0.80) {
            $factors['new_contacts_points'] = 15;
        } elseif ($newContactsRatio >= 0.60) {
            $factors['new_contacts_points'] = 10;
        } elseif ($newContactsRatio >= 0.40) {
            $factors['new_contacts_points'] = 5;
        } else {
            $factors['new_contacts_points'] = 0;
        }
        $score += $factors['new_contacts_points'];

        // 6. Campaign Frequency
        $campaigns7d = WhatsAppCampaign::where('user_id', $user->id)
            ->where(function ($q) {
                $q->where('created_at', '>=', now()->subDays(7))
                  ->orWhere('started_at', '>=', now()->subDays(7));
            })
            ->count();
        $factors['campaigns_7d'] = $campaigns7d;

        if ($campaigns7d > 10) {
            $factors['campaigns_points'] = 15;
        } elseif ($campaigns7d > 5) {
            $factors['campaigns_points'] = 10;
        } elseif ($campaigns7d > 2) {
            $factors['campaigns_points'] = 5;
        } else {
            $factors['campaigns_points'] = 0;
        }
        $score += $factors['campaigns_points'];

        // 7. Device History
        $sessions30d = WhatsAppSession::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $disconnected30d = WhatsAppSession::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->where('status', 'disconnected')
            ->count();
        $factors['sessions_30d'] = $sessions30d;
        $factors['disconnected_sessions_30d'] = $disconnected30d;

        if ($sessions30d > 10) {
            $factors['sessions_points'] = 15;
        } elseif ($sessions30d > 5) {
            $factors['sessions_points'] = 10;
        } elseif ($sessions30d > 3) {
            $factors['sessions_points'] = 5;
        } else {
            $factors['sessions_points'] = 0;
        }
        $score += $factors['sessions_points'];

        $disconnectRatio = $sessions30d > 0 ? $disconnected30d / $sessions30d : 0;
        $factors['disconnect_ratio'] = round($disconnectRatio, 4);
        if ($disconnectRatio > 0.50) {
            $score += 10;
            $factors['disconnect_points'] = 10;
        } elseif ($disconnectRatio > 0.30) {
            $score += 5;
            $factors['disconnect_points'] = 5;
        } else {
            $factors['disconnect_points'] = 0;
        }

        // 8. Fraud Alerts
        $unresolvedAlerts = FraudAlert::where('user_id', $user->id)
            ->where('is_resolved', false)
            ->count();
        $factors['unresolved_fraud_alerts'] = $unresolvedAlerts;
        $factors['fraud_points'] = min(15, $unresolvedAlerts * 5);
        $score += $factors['fraud_points'];

        $score = min(100, max(0, (int) round($score)));
        $level = self::levelForScore($score);

        $result = [
            'score' => $score,
            'max_score' => 100,
            'level' => $level,
            'factors' => $factors,
        ];

        if (!$user->is_admin) {
            $user->update([
                'reputation_score' => $score,
                'risk_level' => $level,
                'reputation_factors' => $factors,
                'reputation_calculated_at' => now(),
            ]);

            if ($level === self::LEVEL_SUSPENDED && is_null($user->banned_at)) {
                $user->update([
                    'banned_at' => now(),
                    'status' => 'suspended',
                ]);
            } elseif ($level !== self::LEVEL_SUSPENDED && !is_null($user->banned_at)) {
                $user->update([
                    'banned_at' => null,
                    'status' => 'active',
                ]);
            }
        }

        return $result;
    }

    public static function levelForScore(int $score): string
    {
        return match (true) {
            $score >= 96 => self::LEVEL_SUSPENDED,
            $score >= 81 => self::LEVEL_CAMPAIGN_LOCKED,
            $score >= 61 => self::LEVEL_LIMITED,
            $score >= 41 => self::LEVEL_WARNING,
            $score >= 21 => self::LEVEL_NORMAL,
            default => self::LEVEL_LOW,
        };
    }

    public static function recalculateIfStale(User $user, int $minutes = 5): array
    {
        if ($user->reputation_calculated_at && $user->reputation_calculated_at->diffInMinutes(now()) < $minutes) {
            return [
                'score' => (int) $user->reputation_score,
                'max_score' => 100,
                'level' => $user->risk_level,
                'factors' => $user->reputation_factors ?? [],
                'calculated_at' => $user->reputation_calculated_at,
            ];
        }

        return self::calculate($user);
    }

    public static function canSendMessages(User $user): bool
    {
        return !in_array($user->risk_level, [self::LEVEL_SUSPENDED], true);
    }

    public static function canRunCampaigns(User $user): bool
    {
        return !in_array($user->risk_level, [self::LEVEL_SUSPENDED, self::LEVEL_CAMPAIGN_LOCKED], true);
    }
}
