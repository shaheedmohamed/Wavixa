<?php

namespace App\Modules\Billing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Transaction;
use App\Services\FraudDetectionService;
use App\Services\PayPalService;
use App\Services\SubscriptionService;
use App\Services\MetaPixelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    private SubscriptionService $subscriptionService;
    private PayPalService $payPalService;

    public function __construct(SubscriptionService $subscriptionService, PayPalService $payPalService)
    {
        $this->subscriptionService = $subscriptionService;
        $this->payPalService = $payPalService;
    }

    /**
     * Get current user's subscription status.
     */
    public function status()
    {
        try {
            $user = Auth::user();
            $stats = $this->subscriptionService->getUsageStats($user);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading subscription status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upgrade or subscribe to a plan.
     *
     * Paid plans require a verified PayPal capture; mock/free payments are
     * only allowed for zero-price (free) plans.
     */
    public function upgrade(Request $request)
    {
        try {
            $request->validate([
                'plan_id' => 'required|exists:plans,id',
                'payment_method' => 'nullable|string|in:mock,free,paypal',
                'paypal_order_id' => 'nullable|string',
            ]);

            $user = Auth::user();
            $plan = Plan::findOrFail($request->plan_id);

            if (!$plan->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected plan is not available.',
                ], 400);
            }

            $paymentMethod = $request->input('payment_method', 'free');
            $paymentData = ['payment_method' => $paymentMethod];

            if ($plan->price > 0 && !$plan->is_free) {
                if ($paymentMethod !== 'paypal' || !$request->filled('paypal_order_id')) {
                    FraudDetectionService::report('subscription_bypass_attempt', 'critical', 'User attempted to activate paid plan without a valid PayPal order', [
                        'plan_id' => $plan->id,
                        'user_id' => $user->id,
                        'payment_method' => $paymentMethod,
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'A valid PayPal order is required for paid plans.',
                    ], 400);
                }

                $capture = $this->captureAndVerifyPayPal($request->paypal_order_id, $user, $plan);
                if (!$capture['success']) {
                    return $capture['response'];
                }

                $paymentData = [
                    'payment_method' => 'paypal',
                    'gateway_transaction_id' => $capture['capture_id'],
                    'gateway_response' => $capture['raw'],
                ];
            } else {
                // Only free/mock payments are accepted for zero-price plans.
                if (!in_array($paymentMethod, ['free', 'mock'], true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment is not required for this plan.',
                    ], 400);
                }
                $paymentData['payment_method'] = 'free';
            }

            $subscription = $this->subscriptionService->upgrade($user, $plan, $paymentData);

            MetaPixelService::track(
                'Purchase',
                $user,
                [
                    'value' => (float) $plan->price,
                    'currency' => $plan->currency ?? 'USD',
                    'content_name' => $plan->name,
                    'content_ids' => [(string) $plan->id],
                    'content_type' => 'product',
                    'num_items' => 1,
                ],
                "purchase-{$plan->id}-{$user->id}-" . time(),
                'https://wavixa.net/subscription'
            );

            MetaPixelService::track(
                'Subscribe',
                $user,
                [
                    'value' => (float) $plan->price,
                    'currency' => $plan->currency ?? 'USD',
                    'content_name' => $plan->name,
                    'content_ids' => [(string) $plan->id],
                    'content_type' => 'product',
                    'num_items' => 1,
                ],
                "subscribe-{$plan->id}-{$user->id}-" . time(),
                'https://wavixa.net/subscription'
            );

            return response()->json([
                'success' => true,
                'message' => 'Successfully added ' . $plan->name . ' credits (' . number_format($plan->messages_limit) . ' messages).',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'plan' => $subscription->plan->name,
                    'messages_limit' => $subscription->messages_limit,
                    'messages_remaining' => $subscription->messages_remaining,
                    'ends_at' => $subscription->ends_at,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing subscription.',
            ], 500);
        }
    }

    /**
     * Renew current subscription.
     *
     * Paid renewals require a verified PayPal capture; free plans do not.
     */
    public function renew(Request $request)
    {
        try {
            $request->validate([
                'payment_method' => 'nullable|string|in:mock,free,paypal',
                'paypal_order_id' => 'nullable|string',
            ]);

            $user = Auth::user();
            $subscription = $this->subscriptionService->getOrCreateActiveSubscription($user);
            $plan = $subscription->plan;

            $paymentMethod = $request->input('payment_method', 'free');
            $paymentData = ['payment_method' => $paymentMethod];

            if ($plan && ($plan->price > 0 && !$plan->is_free)) {
                if ($paymentMethod !== 'paypal' || !$request->filled('paypal_order_id')) {
                    FraudDetectionService::report('renewal_bypass_attempt', 'critical', 'User attempted to renew paid subscription without a valid PayPal order', [
                        'plan_id' => $plan->id,
                        'user_id' => $user->id,
                        'payment_method' => $paymentMethod,
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'A valid PayPal order is required for paid renewals.',
                    ], 400);
                }

                $capture = $this->captureAndVerifyPayPal($request->paypal_order_id, $user, $plan);
                if (!$capture['success']) {
                    return $capture['response'];
                }

                $paymentData = [
                    'payment_method' => 'paypal',
                    'gateway_transaction_id' => $capture['capture_id'],
                    'gateway_response' => $capture['raw'],
                ];
            } else {
                if (!in_array($paymentMethod, ['free', 'mock'], true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment is not required for this plan.',
                    ], 400);
                }
                $paymentData['payment_method'] = 'free';
            }

            $newSubscription = $this->subscriptionService->renew($user, $subscription, $paymentData);

            return response()->json([
                'success' => true,
                'message' => 'Credits renewed successfully.',
                'data' => [
                    'subscription_id' => $newSubscription->id,
                    'plan' => $newSubscription->plan->name,
                    'messages_limit' => $newSubscription->messages_limit,
                    'messages_remaining' => $newSubscription->messages_remaining,
                    'ends_at' => $newSubscription->ends_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error renewing subscription.',
            ], 500);
        }
    }

    /**
     * Capture and verify a PayPal order for the given user/plan.
     */
    private function captureAndVerifyPayPal(string $orderId, $user, Plan $plan): array
    {
        $orderDetails = $this->payPalService->getOrderDetails($orderId);
        if (!$orderDetails['success']) {
            FraudDetectionService::report('paypal_order_lookup_failed', 'high', 'PayPal order details could not be retrieved during capture', [
                'order_id' => $orderId,
                'plan_id' => $plan->id,
                'user_id' => $user->id,
            ]);
            return [
                'success' => false,
                'response' => response()->json([
                    'success' => false,
                    'message' => 'Unable to verify PayPal order.',
                ], 400),
            ];
        }

        $orderData = $orderDetails['data'] ?? [];
        $unit = $orderData['purchase_units'][0] ?? [];
        $expectedReference = 'plan_' . $plan->id . '_user_' . $user->id;
        $expectedCurrency = strtoupper($plan->currency ?: config('paypal.currency', 'USD'));

        if (($unit['reference_id'] ?? null) !== $expectedReference) {
            FraudDetectionService::report('paypal_plan_mismatch', 'critical', 'PayPal order reference_id does not match selected plan/user', [
                'order_id' => $orderId,
                'expected_reference' => $expectedReference,
                'actual_reference' => $unit['reference_id'] ?? null,
                'plan_id' => $plan->id,
                'user_id' => $user->id,
            ]);
            return [
                'success' => false,
                'response' => response()->json([
                    'success' => false,
                    'message' => 'PayPal order does not match the selected plan.',
                ], 400),
            ];
        }

        if (($unit['custom_id'] ?? null) !== (string) $user->id) {
            FraudDetectionService::report('paypal_user_mismatch', 'critical', 'PayPal order custom_id does not match authenticated user (possible account takeover)', [
                'order_id' => $orderId,
                'expected_user_id' => $user->id,
                'actual_custom_id' => $unit['custom_id'] ?? null,
            ]);
            return [
                'success' => false,
                'response' => response()->json([
                    'success' => false,
                    'message' => 'PayPal order does not match the authenticated user.',
                ], 400),
            ];
        }

        if (($unit['amount']['currency_code'] ?? null) !== $expectedCurrency) {
            FraudDetectionService::report('paypal_currency_mismatch', 'critical', 'PayPal order currency mismatch', [
                'order_id' => $orderId,
                'expected_currency' => $expectedCurrency,
                'actual_currency' => $unit['amount']['currency_code'] ?? null,
                'plan_id' => $plan->id,
                'user_id' => $user->id,
            ]);
            return [
                'success' => false,
                'response' => response()->json([
                    'success' => false,
                    'message' => 'PayPal order currency mismatch.',
                ], 400),
            ];
        }

        if ((float) ($unit['amount']['value'] ?? 0) !== (float) $plan->price) {
            FraudDetectionService::report('paypal_amount_mismatch', 'critical', 'PayPal order amount mismatch (possible price tampering)', [
                'order_id' => $orderId,
                'expected_amount' => $plan->price,
                'actual_amount' => $unit['amount']['value'] ?? null,
                'plan_id' => $plan->id,
                'user_id' => $user->id,
            ]);
            return [
                'success' => false,
                'response' => response()->json([
                    'success' => false,
                    'message' => 'PayPal order amount mismatch.',
                ], 400),
            ];
        }

        if (!in_array($orderData['status'] ?? null, ['APPROVED', 'PAYER_ACTION_REQUIRED'], true)) {
            FraudDetectionService::report('paypal_status_mismatch', 'high', 'PayPal order status is not ready for capture', [
                'order_id' => $orderId,
                'order_status' => $orderData['status'] ?? null,
                'plan_id' => $plan->id,
                'user_id' => $user->id,
            ]);
            return [
                'success' => false,
                'response' => response()->json([
                    'success' => false,
                    'message' => 'PayPal order is not ready for capture.',
                ], 400),
            ];
        }

        $capture = $this->payPalService->captureOrder($orderId);
        if (!$capture['success']) {
            return [
                'success' => false,
                'response' => response()->json([
                    'success' => false,
                    'message' => $capture['message'] ?? 'Payment capture failed.',
                ], 500),
            ];
        }

        $captureCurrency = strtoupper($capture['currency'] ?? '');
        $captureAmount = (float) ($capture['amount'] ?? 0);

        if ($captureCurrency !== $expectedCurrency || $captureAmount !== (float) $plan->price) {
            FraudDetectionService::report('paypal_capture_amount_mismatch', 'critical', 'PayPal captured amount/currency does not match plan', [
                'order_id' => $orderId,
                'expected_amount' => $plan->price,
                'expected_currency' => $expectedCurrency,
                'capture_amount' => $captureAmount,
                'capture_currency' => $captureCurrency,
                'plan_id' => $plan->id,
                'user_id' => $user->id,
            ]);
            return [
                'success' => false,
                'response' => response()->json([
                    'success' => false,
                    'message' => 'Captured amount/currency does not match the plan.',
                ], 400),
            ];
        }

        $captureId = $capture['capture_id'] ?? $capture['order_id'];

        if (Transaction::where('gateway_transaction_id', $captureId)
            ->where('payment_method', 'paypal')
            ->exists()) {
            FraudDetectionService::report('paypal_capture_replay', 'critical', 'Attempted replay of already-used PayPal capture ID', [
                'order_id' => $orderId,
                'capture_id' => $captureId,
                'plan_id' => $plan->id,
                'user_id' => $user->id,
            ]);
            return [
                'success' => false,
                'response' => response()->json([
                    'success' => false,
                    'message' => 'This PayPal capture has already been used.',
                ], 400),
            ];
        }

        return [
            'success' => true,
            'capture_id' => $captureId,
            'raw' => $capture['raw'],
        ];
    }

    /**
     * Cancel current subscription.
     */
    public function cancel()
    {
        try {
            $user = Auth::user();
            $subscription = $this->subscriptionService->getOrCreateActiveSubscription($user);

            $this->subscriptionService->cancel($user, $subscription);

            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to cancel subscription.',
            ], 500);
        }
    }
}
