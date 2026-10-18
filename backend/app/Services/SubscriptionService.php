<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionService
{
    /**
     * Get the user's active subscription or create a free plan subscription.
     */
    public function getOrCreateActiveSubscription(User $user): Subscription
    {
        $subscription = Subscription::forUser($user->id)
            ->active()
            ->latest('starts_at')
            ->first();

        if ($subscription) {
            return $subscription;
        }

        return $this->assignFreePlan($user);
    }

    /**
     * Assign the free plan to a user.
     */
    public function assignFreePlan(User $user): Subscription
    {
        $plan = Plan::where('slug', 'free')->active()->first();

        if (!$plan) {
            $plan = Plan::active()->where('is_free', true)->first();
        }

        if (!$plan) {
            throw new \Exception('No free plan configured.');
        }

        return DB::transaction(function () use ($user, $plan) {
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => Subscription::STATUS_ACTIVE,
                'messages_limit' => $plan->messages_limit,
                'sessions_limit' => $plan->sessions_limit,
                'messages_used' => 0,
                'messages_remaining' => $plan->messages_limit,
                'starts_at' => now(),
                'ends_at' => null,
                'renews_at' => null,
            ]);

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'type' => Transaction::TYPE_SUBSCRIPTION,
                'amount' => 0,
                'currency' => $plan->currency,
                'status' => Transaction::STATUS_COMPLETED,
                'payment_method' => 'free',
                'description' => 'Free plan subscription',
            ]);

            Invoice::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'transaction_id' => $transaction->id,
                'invoice_number' => Invoice::generateNumber(),
                'amount' => 0,
                'currency' => $plan->currency,
                'status' => Invoice::STATUS_PAID,
                'due_date' => now(),
                'paid_at' => now(),
                'items' => [
                    [
                        'description' => $plan->name . ' Plan',
                        'quantity' => 1,
                        'amount' => 0,
                    ],
                ],
                'notes' => 'Free plan subscription invoice',
            ]);

            return $subscription;
        });
    }

    /**
     * Check if the user has an active subscription with remaining messages.
     */
    public function hasRemainingMessages(User $user): bool
    {
        $subscription = $this->getOrCreateActiveSubscription($user);

        return $subscription->isActive() && $subscription->hasRemainingMessages();
    }

    /**
     * Get remaining message count for a user.
     */
    public function getRemainingMessages(User $user): int
    {
        $subscription = $this->getOrCreateActiveSubscription($user);

        return $subscription->isActive() ? $subscription->messages_remaining : 0;
    }

    /**
     * Get usage stats for a user.
     */
    public function getUsageStats(User $user): array
    {
        $subscription = $this->getOrCreateActiveSubscription($user);

        return [
            'subscription_id' => $subscription->id,
            'plan' => [
                'id' => $subscription->plan->id,
                'name' => $subscription->plan->name,
                'slug' => $subscription->plan->slug,
                'messages_limit' => $subscription->plan->messages_limit,
            ],
            'status' => $subscription->status,
            'messages_limit' => $subscription->messages_limit,
            'sessions_limit' => $subscription->sessions_limit,
            'messages_used' => $subscription->messages_used,
            'messages_remaining' => $subscription->messages_remaining,
            'usage_percentage' => $subscription->usage_percentage,
            'starts_at' => $subscription->starts_at,
            'ends_at' => $subscription->ends_at,
            'days_remaining' => $subscription->days_remaining,
        ];
    }

    /**
     * Consume one message from the user's active subscription.
     * Returns the remaining messages count, or null on failure.
     */
    public function consumeMessage(User $user): ?int
    {
        return DB::transaction(function () use ($user) {
            $subscription = Subscription::forUser($user->id)
                ->active()
                ->lockForUpdate()
                ->latest('starts_at')
                ->first();

            if (!$subscription) {
                $subscription = $this->getOrCreateActiveSubscription($user);
                $subscription = Subscription::lockForUpdate()->find($subscription->id);
            }

            if (!$subscription || !$subscription->isActive() || $subscription->messages_remaining <= 0) {
                return null;
            }

            $subscription->increment('messages_used');
            $subscription->decrement('messages_remaining');
            $subscription->refresh();

            return (int) $subscription->messages_remaining;
        });
    }

    /**
     * Refund a message credit when a message fails to send.
     */
    public function refundMessage(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            $subscription = Subscription::forUser($user->id)
                ->active()
                ->lockForUpdate()
                ->latest('starts_at')
                ->first();

            if (!$subscription || !$subscription->isActive()) {
                return false;
            }

            if ($subscription->messages_used <= 0 || $subscription->messages_remaining >= $subscription->messages_limit) {
                return false;
            }

            $subscription->decrement('messages_used');
            $subscription->increment('messages_remaining');
            $subscription->refresh();

            return true;
        });
    }

    /**
     * Send a WhatsApp notification when the user's balance is depleted.
     */
    public function sendLowBalanceNotification(User $user): void
    {
        if (empty($user->phone_number)) {
            return;
        }

        $rechargeUrl = config('app.url') . '/subscription';
        $message = "عزيزي المستخدم،\nرصيد رسائلك في Wavixa قد نفذ.\nيرجى شحن رصيد للاستمرار في الإرسال.\n🔗 رابط الشحن: {$rechargeUrl}";

        SystemWhatsAppService::sendMessage($user->phone_number, $message);
    }

    /**
     * Subscribe a user to a plan (add message credits to active subscription).
     */
    public function subscribeToPlan(User $user, Plan $plan, array $paymentData = []): Subscription
    {
        return DB::transaction(function () use ($user, $plan, $paymentData) {
            $currentSubscription = Subscription::forUser($user->id)
                ->active()
                ->latest('starts_at')
                ->first();

            $isRecurring = in_array($plan->interval, [Plan::INTERVAL_MONTH, Plan::INTERVAL_YEAR], true);

            $nextEndsAt = match ($plan->interval) {
                Plan::INTERVAL_MONTH => now()->addMonth(),
                Plan::INTERVAL_YEAR => now()->addYear(),
                default => null,
            };

            $renewsAt = $nextEndsAt;

            $type = Transaction::TYPE_SUBSCRIPTION;

            if ($currentSubscription) {
                if ($isRecurring && $currentSubscription->plan_id === $plan->id) {
                    // Same recurring plan: extend the period and reset the monthly/yearly quota
                    $baseEndDate = $currentSubscription->ends_at && $currentSubscription->ends_at > now()
                        ? $currentSubscription->ends_at
                        : now();

                    $newEndsAt = $plan->interval === Plan::INTERVAL_YEAR
                        ? $baseEndDate->copy()->addYear()
                        : $baseEndDate->copy()->addMonth();

                    $currentSubscription->update([
                        'plan_id' => $plan->id,
                        'messages_limit' => $plan->messages_limit,
                        'sessions_limit' => $plan->sessions_limit,
                        'messages_used' => 0,
                        'messages_remaining' => $plan->messages_limit,
                        'ends_at' => $newEndsAt,
                        'renews_at' => $newEndsAt,
                    ]);

                    $type = Transaction::TYPE_RENEWAL;
                    $subscription = $currentSubscription;
                } else {
                    // Different plan or one-time package: expire the old subscription and start a new one
                    $this->expireSubscription($currentSubscription);

                    $subscription = Subscription::create([
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'status' => Subscription::STATUS_ACTIVE,
                        'messages_limit' => $plan->messages_limit,
                        'sessions_limit' => $plan->sessions_limit,
                        'messages_used' => 0,
                        'messages_remaining' => $plan->messages_limit,
                        'starts_at' => now(),
                        'ends_at' => $nextEndsAt,
                        'renews_at' => $renewsAt,
                        'payment_method' => $paymentData['payment_method'] ?? 'mock',
                        'transaction_id' => $paymentData['transaction_id'] ?? null,
                    ]);

                    $type = $currentSubscription->plan_id === $plan->id
                        ? Transaction::TYPE_RENEWAL
                        : Transaction::TYPE_UPGRADE;
                }
            } else {
                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'status' => Subscription::STATUS_ACTIVE,
                    'messages_limit' => $plan->messages_limit,
                    'sessions_limit' => $plan->sessions_limit,
                    'messages_used' => 0,
                    'messages_remaining' => $plan->messages_limit,
                    'starts_at' => now(),
                    'ends_at' => $nextEndsAt,
                    'renews_at' => $renewsAt,
                    'payment_method' => $paymentData['payment_method'] ?? 'mock',
                    'transaction_id' => $paymentData['transaction_id'] ?? null,
                ]);
            }

            $transaction = $this->createTransaction(
                $user,
                $plan,
                $subscription,
                $type,
                $plan->price,
                $paymentData
            );

            $this->createInvoice($user, $plan, $subscription, $transaction, $plan->price);

            $user->update([
                'subscription_plan' => $plan->slug,
                'subscription_status' => $subscription->status,
            ]);

            return $subscription;
        });
    }

    /**
     * Add message credits to the user's active subscription (same as purchase).
     */
    public function upgrade(User $user, Plan $newPlan, array $paymentData = []): Subscription
    {
        return $this->subscribeToPlan($user, $newPlan, array_merge($paymentData, [
            'type' => Transaction::TYPE_UPGRADE,
        ]));
    }

    /**
     * Add more message credits to the current subscription (same plan).
     */
    public function renew(User $user, Subscription $subscription, array $paymentData = []): Subscription
    {
        $plan = $subscription->plan;

        return $this->subscribeToPlan($user, $plan, array_merge($paymentData, [
            'type' => Transaction::TYPE_RENEWAL,
        ]));
    }

    /**
     * Cancel a subscription (mark as cancelled, credits remain usable).
     */
    public function cancel(User $user, Subscription $subscription): Subscription
    {
        if ($subscription->user_id !== $user->id) {
            throw new \Exception('Unauthorized subscription.');
        }

        $subscription->update([
            'status' => Subscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $user->update([
            'subscription_status' => Subscription::STATUS_CANCELLED,
        ]);

        return $subscription;
    }

    /**
     * Expire a subscription (used internally when replacing it).
     */
    public function expireSubscription(Subscription $subscription): void
    {
        $subscription->update([
            'status' => Subscription::STATUS_EXPIRED,
            'ends_at' => $subscription->ends_at ? min($subscription->ends_at, now()) : now(),
        ]);
    }

    /**
     * Create a transaction record.
     */
    private function createTransaction(
        User $user,
        Plan $plan,
        Subscription $subscription,
        string $type,
        float $amount,
        array $paymentData = []
    ): Transaction {
        $paymentMethod = $paymentData['payment_method'] ?? 'mock';
        $gatewayTransactionId = $paymentData['gateway_transaction_id'] ?? 'txn_' . Str::random(12);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'type' => $type,
            'amount' => $amount,
            'currency' => $plan->currency,
            'status' => Transaction::STATUS_COMPLETED,
            'payment_method' => $paymentMethod,
            'gateway_transaction_id' => $gatewayTransactionId,
            'gateway_response' => $paymentData['gateway_response'] ?? null,
            'description' => ucfirst($type) . ' to ' . $plan->name,
        ]);

        $subscription->update(['transaction_id' => $gatewayTransactionId]);

        return $transaction;
    }

    /**
     * Create an invoice record.
     */
    private function createInvoice(
        User $user,
        Plan $plan,
        Subscription $subscription,
        Transaction $transaction,
        float $amount
    ): Invoice {
        return Invoice::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'transaction_id' => $transaction->id,
            'invoice_number' => Invoice::generateNumber(),
            'amount' => $amount,
            'currency' => $plan->currency,
            'status' => Invoice::STATUS_PAID,
            'due_date' => $subscription->starts_at,
            'paid_at' => now(),
            'items' => [
                [
                    'description' => $plan->name . ' Plan (' . $plan->messages_limit . ' messages)',
                    'quantity' => 1,
                    'amount' => $amount,
                ],
            ],
            'notes' => 'Invoice for ' . $transaction->description,
        ]);
    }
}
