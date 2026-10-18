<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;
    private string $currency;
    private ?string $accessToken = null;

    public function __construct()
    {
        $mode = config('paypal.mode', env('PAYPAL_MODE', 'sandbox'));

        $this->clientId = $mode === 'live'
            ? config('paypal.live_client_id', env('PAYPAL_LIVE_CLIENT_ID', ''))
            : config('paypal.sandbox_client_id', env('PAYPAL_SANDBOX_CLIENT_ID', ''));

        $this->clientSecret = $mode === 'live'
            ? config('paypal.live_client_secret', env('PAYPAL_LIVE_CLIENT_SECRET', ''))
            : config('paypal.sandbox_client_secret', env('PAYPAL_SANDBOX_CLIENT_SECRET', ''));

        $this->baseUrl = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $this->currency = config('paypal.currency', env('PAYPAL_CURRENCY', 'USD'));
    }

    /**
     * Get PayPal access token using client credentials.
     */
    public function getAccessToken(): ?string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post($this->baseUrl . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->successful()) {
                $this->accessToken = $response->json('access_token');
                return $this->accessToken;
            }

            Log::error('PayPal access token failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('PayPal access token exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Create a PayPal order for a plan purchase.
     */
    public function createOrder(Plan $plan, int $userId, array $metadata = []): array
    {
        $token = $this->getAccessToken();

        if (!$token) {
            return [
                'success' => false,
                'message' => 'Unable to connect to PayPal.',
            ];
        }

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'plan_' . $plan->id . '_user_' . $userId,
                    'description' => $plan->name . ' - ' . number_format($plan->messages_limit) . ' messages',
                    'amount' => [
                        'currency_code' => $this->currency,
                        'value' => number_format($plan->price, 2, '.', ''),
                        'breakdown' => [
                            'item_total' => [
                                'currency_code' => $this->currency,
                                'value' => number_format($plan->price, 2, '.', ''),
                            ],
                        ],
                    ],
                    'custom_id' => (string) $userId,
                    'invoice_id' => 'wavixa_' . uniqid(),
                    'items' => [
                        [
                            'name' => $plan->name . ' Message Credits',
                            'quantity' => '1',
                            'unit_amount' => [
                                'currency_code' => $this->currency,
                                'value' => number_format($plan->price, 2, '.', ''),
                            ],
                            'category' => 'DIGITAL_GOODS',
                        ],
                    ],
                ],
            ],
            'application_context' => [
                'brand_name' => config('app.name', 'Wavixa'),
                'landing_page' => 'BILLING',
                'user_action' => 'PAY_NOW',
                'return_url' => config('paypal.return_url', env('PAYPAL_RETURN_URL', config('app.url') . '/subscription')),
                'cancel_url' => config('paypal.cancel_url', env('PAYPAL_CANCEL_URL', config('app.url') . '/subscription')),
            ],
        ];

        try {
            $response = Http::withToken($token)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'PayPal-Request-Source' => 'laravel',
                ])
                ->post($this->baseUrl . '/v2/checkout/orders', $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'order_id' => $data['id'],
                    'status' => $data['status'],
                    'approval_url' => $this->getApprovalUrl($data),
                    'raw' => $data,
                ];
            }

            Log::error('PayPal create order failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'PayPal order creation failed: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('PayPal create order exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'PayPal order creation error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Capture an approved PayPal order.
     */
    public function captureOrder(string $orderId): array
    {
        $token = $this->getAccessToken();

        if (!$token) {
            return [
                'success' => false,
                'message' => 'Unable to connect to PayPal.',
            ];
        }

        try {
            $response = Http::withToken($token)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'PayPal-Request-Source' => 'laravel',
                ])
                ->post($this->baseUrl . '/v2/checkout/orders/' . $orderId . '/capture');

            $data = $response->json();

            if ($response->successful() && isset($data['status']) && $data['status'] === 'COMPLETED') {
                return [
                    'success' => true,
                    'order_id' => $data['id'],
                    'status' => $data['status'],
                    'capture_id' => $data['purchase_units'][0]['payments']['captures'][0]['id'] ?? null,
                    'amount' => $data['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? null,
                    'currency' => $data['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] ?? null,
                    'payer' => $data['payer'] ?? null,
                    'raw' => $data,
                ];
            }

            Log::error('PayPal capture order failed', [
                'order_id' => $orderId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'PayPal capture failed: ' . ($data['message'] ?? $response->body()),
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal capture order exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'PayPal capture error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get order details from PayPal.
     */
    public function getOrderDetails(string $orderId): array
    {
        $token = $this->getAccessToken();

        if (!$token) {
            return [
                'success' => false,
                'message' => 'Unable to connect to PayPal.',
            ];
        }

        try {
            $response = Http::withToken($token)
                ->get($this->baseUrl . '/v2/checkout/orders/' . $orderId);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to get order details: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('PayPal get order details exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Extract the approval URL from PayPal order response.
     */
    private function getApprovalUrl(array $orderData): ?string
    {
        foreach ($orderData['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        }

        return null;
    }
}
