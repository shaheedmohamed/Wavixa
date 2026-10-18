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
use Illuminate\Support\Facades\Log;

class PayPalController extends Controller
{
    private PayPalService $payPalService;
    private SubscriptionService $subscriptionService;

    public function __construct(PayPalService $payPalService, SubscriptionService $subscriptionService)
    {
        $this->payPalService = $payPalService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Create a PayPal order for a selected plan.
     */
    public function createOrder(Request $request)
    {
        try {
            $request->validate([
                'plan_id' => 'required|exists:plans,id',
            ]);

            $user = Auth::user();
            $plan = Plan::findOrFail($request->plan_id);

            if (!$plan->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected plan is not available.',
                ], 400);
            }

            $order = $this->payPalService->createOrder($plan, $user->id, [
                'plan_id' => $plan->id,
                'user_id' => $user->id,
            ]);

            if (!$order['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $order['message'] ?? 'Failed to create PayPal order.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order['order_id'],
                    'approval_url' => $order['approval_url'],
                    'status' => $order['status'],
                    'plan' => [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'price' => $plan->price,
                        'currency' => $plan->currency,
                        'messages_limit' => $plan->messages_limit,
                    ],
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PayPal create order controller error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to create PayPal order.',
            ], 500);
        }
    }

    /**
     * Capture an approved PayPal order and add message credits.
     */
    public function captureOrder(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|string',
                'plan_id' => 'required|exists:plans,id',
            ]);

            $user = Auth::user();
            $plan = Plan::findOrFail($request->plan_id);

            // Prevent zero-price plans from being processed through PayPal.
            if ($plan->price <= 0 || $plan->is_free) {
                FraudDetectionService::report('paypal_free_plan_payment_attempt', 'high', 'User attempted to activate a free/zero-price plan through PayPal capture', [
                    'plan_id' => $plan->id,
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'This plan does not require payment.',
                ], 400);
            }

            // Verify the order details before capture to ensure the order was
            // created for this exact user/plan/amount and has not been replayed.
            $orderDetails = $this->payPalService->getOrderDetails($request->order_id);
            if (!$orderDetails['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to verify PayPal order.',
                ], 400);
            }

            $orderData = $orderDetails['data'] ?? [];
            $unit = $orderData['purchase_units'][0] ?? [];
            $expectedReference = 'plan_' . $plan->id . '_user_' . $user->id;
            $expectedCurrency = strtoupper($plan->currency ?: config('paypal.currency', 'USD'));

            if (($unit['reference_id'] ?? null) !== $expectedReference) {
                FraudDetectionService::report('paypal_plan_mismatch', 'critical', 'PayPal order reference_id does not match selected plan/user', [
                    'order_id' => $request->order_id,
                    'expected_reference' => $expectedReference,
                    'actual_reference' => $unit['reference_id'] ?? null,
                    'plan_id' => $plan->id,
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'PayPal order does not match the selected plan.',
                ], 400);
            }

            if (($unit['custom_id'] ?? null) !== (string) $user->id) {
                FraudDetectionService::report('paypal_user_mismatch', 'critical', 'PayPal order custom_id does not match authenticated user (possible account takeover)', [
                    'order_id' => $request->order_id,
                    'expected_user_id' => $user->id,
                    'actual_custom_id' => $unit['custom_id'] ?? null,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'PayPal order does not match the authenticated user.',
                ], 400);
            }

            if (($unit['amount']['currency_code'] ?? null) !== $expectedCurrency) {
                FraudDetectionService::report('paypal_currency_mismatch', 'critical', 'PayPal order currency mismatch', [
                    'order_id' => $request->order_id,
                    'expected_currency' => $expectedCurrency,
                    'actual_currency' => $unit['amount']['currency_code'] ?? null,
                    'plan_id' => $plan->id,
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'PayPal order currency mismatch.',
                ], 400);
            }

            if ((float) ($unit['amount']['value'] ?? 0) !== (float) $plan->price) {
                FraudDetectionService::report('paypal_amount_mismatch', 'critical', 'PayPal order amount mismatch (possible price tampering)', [
                    'order_id' => $request->order_id,
                    'expected_amount' => $plan->price,
                    'actual_amount' => $unit['amount']['value'] ?? null,
                    'plan_id' => $plan->id,
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'PayPal order amount mismatch.',
                ], 400);
            }

            if (!in_array($orderData['status'] ?? null, ['APPROVED', 'PAYER_ACTION_REQUIRED'], true)) {
                FraudDetectionService::report('paypal_status_mismatch', 'high', 'PayPal order status is not ready for capture', [
                    'order_id' => $request->order_id,
                    'order_status' => $orderData['status'] ?? null,
                    'plan_id' => $plan->id,
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'PayPal order is not ready for capture.',
                ], 400);
            }

            $capture = $this->payPalService->captureOrder($request->order_id);

            if (!$capture['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $capture['message'] ?? 'Payment capture failed.',
                ], 500);
            }

            $captureCurrency = strtoupper($capture['currency'] ?? '');
            $captureAmount = (float) ($capture['amount'] ?? 0);

            if ($captureCurrency !== $expectedCurrency || $captureAmount !== (float) $plan->price) {
                FraudDetectionService::report('paypal_capture_amount_mismatch', 'critical', 'PayPal captured amount/currency does not match plan', [
                    'order_id' => $request->order_id,
                    'expected_amount' => $plan->price,
                    'expected_currency' => $expectedCurrency,
                    'capture_amount' => $captureAmount,
                    'capture_currency' => $captureCurrency,
                    'plan_id' => $plan->id,
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Captured amount/currency does not match the plan.',
                ], 400);
            }

            $captureId = $capture['capture_id'] ?? $capture['order_id'];

            if (Transaction::where('gateway_transaction_id', $captureId)
                ->where('payment_method', 'paypal')
                ->exists()) {
                FraudDetectionService::report('paypal_capture_replay', 'critical', 'Attempted replay of already-used PayPal capture ID', [
                    'order_id' => $request->order_id,
                    'capture_id' => $captureId,
                    'plan_id' => $plan->id,
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'This PayPal capture has already been used.',
                ], 400);
            }

            $subscription = $this->subscriptionService->upgrade($user, $plan, [
                'payment_method' => 'paypal',
                'gateway_transaction_id' => $captureId,
                'gateway_response' => $capture['raw'],
            ]);

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
                "purchase-{$request->order_id}-{$user->id}",
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
                "subscribe-{$request->order_id}-{$user->id}",
                'https://wavixa.net/subscription'
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment successful. ' . number_format($plan->messages_limit) . ' message credits added.',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'plan' => $subscription->plan->name,
                    'messages_limit' => $subscription->messages_limit,
                    'messages_remaining' => $subscription->messages_remaining,
                    'order_id' => $capture['order_id'],
                    'capture_id' => $capture['capture_id'],
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PayPal capture order controller error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to capture payment.',
            ], 500);
        }
    }

    /**
     * Get PayPal SDK configuration (public endpoint for frontend).
     */
    public function config()
    {
        $mode = config('paypal.mode', 'sandbox');
        $clientId = $mode === 'live'
            ? config('paypal.live_client_id')
            : config('paypal.sandbox_client_id');

        return response()->json([
            'success' => true,
            'data' => [
                'mode' => $mode,
                'client_id' => $clientId,
                'currency' => config('paypal.currency', 'USD'),
            ],
        ]);
    }

    /**
     * Get PayPal order details.
     */
    public function orderDetails(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|string',
            ]);

            $user = Auth::user();
            $details = $this->payPalService->getOrderDetails($request->order_id);

            $orderData = $details['data'] ?? [];
            $unit = $orderData['purchase_units'][0] ?? [];
            $orderUserId = $unit['custom_id'] ?? null;

            if ($orderUserId !== (string) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order does not belong to the authenticated user.',
                ], 403);
            }

            // Return only the fields the frontend needs; avoid leaking payer PII.
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $orderData['id'] ?? $request->order_id,
                    'status' => $orderData['status'] ?? null,
                    'amount' => $unit['amount']['value'] ?? null,
                    'currency' => $unit['amount']['currency_code'] ?? null,
                    'reference_id' => $unit['reference_id'] ?? null,
                    'custom_id' => $orderUserId,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PayPal order details controller error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve order details.',
            ], 500);
        }
    }
}
