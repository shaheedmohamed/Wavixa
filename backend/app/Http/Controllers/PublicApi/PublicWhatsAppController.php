<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendWhatsAppMessageRequest;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\ApiKey;
use App\Models\OtpCode;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSession;
use App\Services\FraudDetectionService;
use App\Services\PhoneNormalizer;
use App\Services\SubscriptionService;
use App\Services\UserReputationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicWhatsAppController extends Controller
{
    private string $whatsappServiceUrl = 'http://localhost:3001/api/whatsapp';
    private SubscriptionService $subscriptionService;

    private const MESSAGE_TYPES = ['text', 'image', 'video', 'audio', 'voice', 'document', 'contact', 'location'];

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
        $this->whatsappServiceUrl = config('services.whatsapp.url', 'http://127.0.0.1:3002') . '/api/whatsapp';
    }

    /**
     * Get current authenticated user from API key
     */
    private function getUser(Request $request)
    {
        return $request->attributes->get('api_key_user');
    }

    /**
     * Get current API key record
     */
    private function getApiKey(Request $request): ?ApiKey
    {
        return $request->attributes->get('api_key');
    }

    /**
     * Helper: normalize phone number
     */
    private function normalizePhone(string $phone): string
    {
        return PhoneNormalizer::normalize($phone);
    }

    /**
     * Helper: resolve the WhatsApp session to use for this request.
     * Accepts a device identifier, an API-key linked session, or a paired session.
     */
    private function resolveSession(Request $request): ?WhatsAppSession
    {
        $user = $this->getUser($request);
        $apiKey = $this->getApiKey($request);

        $device = $request->input('device');

        if ($device) {
            $query = WhatsAppSession::where('user_id', $user->id)->where('status', 'connected');

            if (is_numeric($device)) {
                $session = $query->where('id', $device)->first();
            } else {
                $session = $query->where(function ($q) use ($device) {
                    $q->where('session_token', $device)
                      ->orWhere('phone_number', $device);
                })->first();
            }

            if ($session) {
                return $session;
            }
        }

        if ($apiKey?->whatsapp_session_id) {
            $session = WhatsAppSession::where('id', $apiKey->whatsapp_session_id)
                ->where('user_id', $user->id)
                ->where('status', 'connected')
                ->first();

            if ($session) {
                return $session;
            }
        }

        if ($apiKey?->allow_remote_pairing) {
            $session = $apiKey->pairedSessions()
                ->where('status', 'connected')
                ->latest('connected_at')
                ->first();

            if ($session) {
                return $session;
            }
        }

        return WhatsAppSession::where('user_id', $user->id)
            ->where('status', 'connected')
            ->latest('connected_at')
            ->first();
    }

    /**
     * Helper: validate WhatsApp connection for the resolved session
     */
    private function validateConnection(Request $request): array
    {
        $session = $this->resolveSession($request);

        if (!$session) {
            return [
                'success' => false,
                'status' => 400,
                'message' => 'WhatsApp not connected. Please connect your WhatsApp account first or provide a valid device.',
            ];
        }

        $nodeUserId = $session->nodeUserId();
        $statusResponse = Http::timeout(10)
            ->withHeaders(['Authorization' => 'Bearer ' . config('services.whatsapp.api_key')])
            ->get("{$this->whatsappServiceUrl}/status/{$nodeUserId}");

        if (!$statusResponse->successful() || ($statusResponse->json()['status'] ?? 'disconnected') !== 'connected') {
            return [
                'success' => false,
                'status' => 400,
                'message' => 'WhatsApp connection is not active. Please reconnect.',
            ];
        }

        return ['success' => true, 'session' => $session];
    }

    /**
     * Helper: queue a message for sending
     */
    private function queueMessage(Request $request, string $to, string $type, string $content, ?WhatsAppSession $session = null): WhatsAppMessage
    {
        $user = $this->getUser($request);
        $apiKey = $this->getApiKey($request);

        if (!$session) {
            $session = $this->resolveSession($request);
        }

        $scheduledAt = null;

        if ($request->filled('scheduled_at')) {
            $scheduledAt = \Illuminate\Support\Carbon::parse($request->input('scheduled_at'));
        } elseif ($request->filled('delay_minutes')) {
            $scheduledAt = now()->addMinutes((int) $request->input('delay_minutes'));
        }

        $isScheduled = $scheduledAt && $scheduledAt->isFuture();

        $message = WhatsAppMessage::create([
            'user_id' => $user->id,
            'api_key_id' => $apiKey?->id,
            'session_id' => $session?->id,
            'from' => $session?->phone_number,
            'to' => $this->normalizePhone($to),
            'message' => $content,
            'message_type' => $type,
            'direction' => 'outgoing',
            'status' => $isScheduled ? WhatsAppMessage::STATUS_SCHEDULED : WhatsAppMessage::STATUS_QUEUED,
            'queued_at' => now(),
            'scheduled_at' => $isScheduled ? $scheduledAt : null,
            'timestamp' => now(),
        ]);

        $dispatch = SendWhatsAppMessageJob::dispatch($message->id);
        if ($isScheduled) {
            $dispatch->delay($scheduledAt);
        }

        return $message;
    }

    /**
     * Helper: handle common try/catch wrapper
     */
    private function handleSend(Request $request, string $type, callable $callback)
    {
        try {
            $user = $this->getUser($request);

            // Check subscription has remaining messages
            if (!$this->subscriptionService->hasRemainingMessages($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No remaining messages in your subscription. Please upgrade your plan.',
                ], 403);
            }

            if (!$user->is_admin && !UserReputationService::canSendMessages($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is restricted due to high risk behavior. Please contact support.',
                ], 403);
            }

            $connection = $this->validateConnection($request);

            if (!$connection['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $connection['message'],
                ], $connection['status']);
            }

            return $callback($user->id, $connection['session']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error("Public WhatsApp {$type} error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error sending ' . $type . ': ' . 'An error occurred',
            ], 500);
        }
    }

    /**
     * 1. Send a plain text message
     */
    public function sendMessage(SendWhatsAppMessageRequest $request)
    {
        return $this->handleSend($request, 'text', function (int $userId, ?WhatsAppSession $session) use ($request) {
            $to = $this->normalizePhone($request->to);
            $messageText = $request->message;

            $message = $this->queueMessage($request, $to, 'text', $messageText);

            return response()->json([
                'success' => true,
                'message' => 'Message queued successfully',
                'data' => [
                    'message_id' => $message->id,
                    'to' => $to,
                    'message_type' => 'text',
                    'status' => $message->status,
                    'queued_at' => $message->queued_at,
                    'scheduled_at' => $message->scheduled_at,
                ],
            ]);
        });
    }

    /**
     * 2. Send an image message
     */
    public function sendImage(SendWhatsAppMessageRequest $request)
    {
        return $this->handleSend($request, 'image', function (int $userId, ?WhatsAppSession $session) use ($request) {
            $to = $this->normalizePhone($request->to);
            $caption = $request->caption ?? '';
            $content = $request->content ?? $request->image_url ?? '';
            $fileName = $request->file_name ?? $request->filename ?? null;

            $payload = json_encode([
                'content' => $content,
                'caption' => $caption,
                'file_name' => $fileName,
            ]);

            $message = $this->queueMessage($request, $to, 'image', $payload);

            return response()->json([
                'success' => true,
                'message' => 'Image queued successfully',
                'data' => [
                    'message_id' => $message->id,
                    'to' => $to,
                    'message_type' => 'image',
                    'status' => $message->status,
                    'queued_at' => $message->queued_at,
                    'scheduled_at' => $message->scheduled_at,
                ],
            ]);
        });
    }

    /**
     * 3. Send a file / document message
     */
    public function sendFile(SendWhatsAppMessageRequest $request)
    {
        return $this->handleSend($request, 'document', function (int $userId, ?WhatsAppSession $session) use ($request) {
            $to = $this->normalizePhone($request->to);
            $fileName = $request->file_name ?? $request->filename ?? 'document';
            $content = $request->content ?? $request->file_url ?? '';

            // Store a structured JSON so the job can extract file_name and content
            $payload = json_encode([
                'file_name' => $fileName,
                'content' => $content,
                'caption' => $request->caption ?? '',
            ]);

            $message = $this->queueMessage($request, $to, 'document', $payload);

            return response()->json([
                'success' => true,
                'message' => 'File queued successfully',
                'data' => [
                    'message_id' => $message->id,
                    'to' => $to,
                    'message_type' => 'document',
                    'status' => $message->status,
                    'queued_at' => $message->queued_at,
                    'scheduled_at' => $message->scheduled_at,
                ],
            ]);
        });
    }

    /**
     * 4. Send a video message
     */
    public function sendVideo(SendWhatsAppMessageRequest $request)
    {
        return $this->handleSend($request, 'video', function (int $userId, ?WhatsAppSession $session) use ($request) {
            $to = $this->normalizePhone($request->to);
            $caption = $request->caption ?? '';
            $content = $request->content ?? $request->video_url ?? '';
            $fileName = $request->file_name ?? $request->filename ?? null;

            $payload = json_encode([
                'content' => $content,
                'caption' => $caption,
                'file_name' => $fileName,
            ]);

            $message = $this->queueMessage($request, $to, 'video', $payload);

            return response()->json([
                'success' => true,
                'message' => 'Video queued successfully',
                'data' => [
                    'message_id' => $message->id,
                    'to' => $to,
                    'message_type' => 'video',
                    'status' => $message->status,
                    'queued_at' => $message->queued_at,
                    'scheduled_at' => $message->scheduled_at,
                ],
            ]);
        });
    }

    /**
     * 5. Send a voice / audio message
     */
    public function sendVoice(SendWhatsAppMessageRequest $request)
    {
        return $this->handleSend($request, 'voice', function (int $userId, ?WhatsAppSession $session) use ($request) {
            $to = $this->normalizePhone($request->to);
            $content = $request->content ?? $request->audio_url ?? '';

            $message = $this->queueMessage($request, $to, 'voice', $content);

            return response()->json([
                'success' => true,
                'message' => 'Voice message queued successfully',
                'data' => [
                    'message_id' => $message->id,
                    'to' => $to,
                    'message_type' => 'voice',
                    'status' => $message->status,
                    'queued_at' => $message->queued_at,
                    'scheduled_at' => $message->scheduled_at,
                ],
            ]);
        });
    }

    /**
     * 6. Send a contact message
     */
    public function sendContact(SendWhatsAppMessageRequest $request)
    {
        return $this->handleSend($request, 'contact', function (int $userId, ?WhatsAppSession $session) use ($request) {
            $to = $this->normalizePhone($request->to);
            $content = $request->content;

            $displayText = $content['display_name'] . ' (' . $content['phone'] . ')';
            $message = $this->queueMessage($request, $to, 'contact', $displayText, $session);

            return response()->json([
                'success' => true,
                'message' => 'Contact queued successfully',
                'data' => [
                    'message_id' => $message->id,
                    'to' => $to,
                    'message_type' => 'contact',
                    'status' => $message->status,
                    'queued_at' => $message->queued_at,
                    'scheduled_at' => $message->scheduled_at,
                ],
            ]);
        });
    }

    /**
     * 7. Send a location message
     */
    public function sendLocation(SendWhatsAppMessageRequest $request)
    {
        return $this->handleSend($request, 'location', function (int $userId, ?WhatsAppSession $session) use ($request) {
            $to = $this->normalizePhone($request->to);
            $content = $request->content;

            $locationText = ($content['name'] ?? '') . ' (' . $content['latitude'] . ', ' . $content['longitude'] . ')';
            $message = $this->queueMessage($request, $to, 'location', $locationText, $session);

            return response()->json([
                'success' => true,
                'message' => 'Location queued successfully',
                'data' => [
                    'message_id' => $message->id,
                    'to' => $to,
                    'message_type' => 'location',
                    'status' => $message->status,
                    'queued_at' => $message->queued_at,
                    'scheduled_at' => $message->scheduled_at,
                ],
            ]);
        });
    }

    /**
     * Generate and send an OTP code
     */
    public function sendOtp(Request $request)
    {
        try {
            $request->validate([
                'to' => 'required|string|min:8|max:20',
                'purpose' => 'nullable|string|max:100',
                'length' => 'nullable|integer|min:4|max:8',
                'expires_in_minutes' => 'nullable|integer|min:1|max:60',
                'template' => 'nullable|string|max:4000',
            ]);

            $user = $this->getUser($request);
            $userId = $user->id;

            if (!$this->subscriptionService->hasRemainingMessages($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No remaining messages in your subscription. Please upgrade your plan.',
                ], 403);
            }

            $connection = $this->validateConnection($request);
            if (!$connection['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $connection['message'],
                ], $connection['status']);
            }

            $length = $request->input('length', 6);
            $expiresIn = $request->input('expires_in_minutes', 10);
            $purpose = $request->input('purpose', 'verification');
            $to = $this->normalizePhone($request->to);

            $code = (string) random_int(10 ** ($length - 1), (10 ** $length) - 1);

            $otp = OtpCode::create([
                'user_id' => $userId,
                'phone_number' => $to,
                'code' => $code,
                'purpose' => $purpose,
                'expires_at' => now()->addMinutes($expiresIn),
            ]);

            $template = $request->input('template', 'Your verification code is: {code}. It expires in {expires_in_minutes} minutes.');
            $messageText = str_replace(
                ['{code}', '{expires_in_minutes}', '{purpose}'],
                [$code, $expiresIn, $purpose],
                $template
            );

            $message = $this->queueMessage($request, $to, 'text', $messageText);

            return response()->json([
                'success' => true,
                'message' => 'OTP queued successfully',
                'data' => [
                    'otp_id' => $otp->id,
                    'message_id' => $message->id,
                    'to' => $to,
                    'purpose' => $purpose,
                    'expires_at' => $otp->expires_at,
                    'expires_in_minutes' => $expiresIn,
                    'status' => $message->status,
                    'scheduled_at' => $message->scheduled_at,
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
                'message' => 'Error sending OTP.',
            ], 500);
        }
    }

    /**
     * Verify an OTP code
     */
    public function verifyOtp(Request $request)
    {
        try {
            $request->validate([
                'phone' => 'required|string|min:8|max:20',
                'code' => 'required|string|min:3|max:10',
                'purpose' => 'nullable|string|max:100',
            ]);

            $user = $this->getUser($request);
            $userId = $user->id;
            $phone = $this->normalizePhone($request->phone);
            $code = $request->code;
            $purpose = $request->input('purpose');

            $attemptKey = "otp_attempts:public:{$userId}:{$phone}";
            $attempts = Cache::get($attemptKey, 0);
            if ($attempts >= 5) {
                FraudDetectionService::report('otp_brute_force', 'critical', 'OTP brute-force limit reached via public WhatsApp API', [
                    'phone' => $phone,
                    'user_id' => $userId,
                    'attempts' => $attempts,
                    'purpose' => $purpose,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Too many attempts. Please request a new OTP.',
                ], 429);
            }

            $otp = OtpCode::verify($userId, $phone, $code, $purpose);

            if (!$otp) {
                Cache::put($attemptKey, $attempts + 1, now()->addMinutes(15));
                FraudDetectionService::report('otp_failed', 'high', 'Invalid OTP code entered via public WhatsApp API', [
                    'phone' => $phone,
                    'user_id' => $userId,
                    'attempts' => $attempts + 1,
                    'purpose' => $purpose,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP code',
                ], 400);
            }

            Cache::forget($attemptKey);
            $otp->update(['verified_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
                'data' => [
                    'otp_id' => $otp->id,
                    'phone' => $phone,
                    'purpose' => $otp->purpose,
                    'verified_at' => $otp->verified_at,
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
                'message' => 'Error verifying OTP.',
            ], 500);
        }
    }

    /**
     * Send a templated message
     */
    public function sendTemplateMessage(Request $request)
    {
        try {
            $request->validate([
                'to' => 'required|string|min:8|max:20',
                'template' => 'required|string|in:welcome,order_confirmation,appointment_reminder,payment_received,custom',
                'variables' => 'nullable|array',
                'variables.*' => 'string|max:500',
            ]);

            $user = $this->getUser($request);

            if (!$this->subscriptionService->hasRemainingMessages($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No remaining messages in your subscription. Please upgrade your plan.',
                ], 403);
            }

            $connection = $this->validateConnection($request);
            if (!$connection['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $connection['message'],
                ], $connection['status']);
            }

            $to = $this->normalizePhone($request->to);
            $variables = $request->input('variables', []);

            $templates = [
                'welcome' => "Welcome {name}! Thanks for joining us. If you need any help, reply to this message.",
                'order_confirmation' => "Hi {name}, your order #{order_id} has been confirmed. Total: {total}. Thank you for shopping with us!",
                'appointment_reminder' => "Hi {name}, this is a reminder for your appointment on {date} at {time}. Reply CONFIRM to confirm or CANCEL to cancel.",
                'payment_received' => "Hi {name}, we received your payment of {amount}. Thank you!",
                'custom' => $variables['message'] ?? 'Hello!',
            ];

            $messageText = $templates[$request->template];
            foreach ($variables as $key => $value) {
                $messageText = str_replace("{{$key}}", $value, $messageText);
            }

            $message = $this->queueMessage($request, $to, 'text', $messageText);

            return response()->json([
                'success' => true,
                'message' => 'Template message queued successfully',
                'data' => [
                    'message_id' => $message->id,
                    'to' => $to,
                    'message_type' => 'text',
                    'template' => $request->template,
                    'status' => $message->status,
                    'queued_at' => $message->queued_at,
                    'scheduled_at' => $message->scheduled_at,
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
                'message' => 'Error sending template message: ' . 'An error occurred',
            ], 500);
        }
    }

    /**
     * Authenticated service client for the WhatsApp Node service.
     */
    private function serviceClient(int $timeout = 10)
    {
        return Http::timeout($timeout)->withHeaders([
            'Authorization' => 'Bearer ' . config('services.whatsapp.api_key'),
        ]);
    }

    /**
     * Create a remote WhatsApp pairing session.
     */
    public function createPairingSession(Request $request)
    {
        try {
            $apiKey = $this->getApiKey($request);

            if (!$apiKey || !$apiKey->allow_remote_pairing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Remote pairing is not enabled for this API key.',
                ], 403);
            }

            $activePaired = $apiKey->pairedSessions()
                ->whereIn('status', ['pending', 'connected'])
                ->count();

            if ($activePaired >= $apiKey->remote_pairing_limit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Remote pairing limit reached for this API key.',
                ], 429);
            }

            $user = $this->getUser($request);
            $sessionToken = (string) Str::uuid();

            $session = WhatsAppSession::create([
                'user_id' => $user->id,
                'api_key_id' => $apiKey->id,
                'session_token' => $sessionToken,
                'status' => 'pending',
                'device_name' => $request->input('device_name', 'Remote pairing'),
            ]);

            $response = $this->serviceClient()->post("{$this->whatsappServiceUrl}/connect/{$sessionToken}");

            if (!$response->successful()) {
                $session->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to initialize WhatsApp pairing.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pairing session initialized. Display the QR code and poll the status URL.',
                'data' => [
                    'pairing_id' => $session->id,
                    'session_token' => $sessionToken,
                    'status_url' => url("/api/public/whatsapp/pairing-sessions/{$session->id}"),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Create pairing session error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating pairing session: ' . 'An error occurred',
            ], 500);
        }
    }

    /**
     * Get the status of a remote pairing session (including QR code while pending).
     */
    public function pairingStatus(Request $request, $id)
    {
        try {
            $user = $this->getUser($request);
            $apiKey = $this->getApiKey($request);

            $session = WhatsAppSession::where('id', $id)
                ->where('user_id', $user->id)
                ->where('api_key_id', $apiKey?->id)
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pairing session not found.',
                ], 404);
            }

            $nodeUserId = $session->nodeUserId();
            $serviceResponse = $this->serviceClient()->get("{$this->whatsappServiceUrl}/status/{$nodeUserId}");

            if (!$serviceResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to reach WhatsApp service.',
                ], 500);
            }

            $data = $serviceResponse->json();

            if (($data['status'] ?? 'disconnected') === 'connected' && !empty($data['phoneNumber'])) {
                $session->update([
                    'status' => 'connected',
                    'phone_number' => $data['phoneNumber'],
                    'device_name' => $data['name'] ?? $session->device_name,
                    'connected_at' => now(),
                    'last_activity_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'status' => 'connected',
                        'phone_number' => $data['phoneNumber'],
                        'name' => $data['name'] ?? null,
                        'connected_at' => $session->connected_at,
                    ],
                ]);
            }

            if (($data['status'] ?? 'disconnected') === 'pending' && !empty($data['qr'])) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'status' => 'pending',
                        'qr' => $data['qr'],
                        'expires_at' => $session->created_at->addMinutes(5),
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $data['status'] ?? 'disconnected',
                    'qr' => $data['qr'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Pairing status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error getting pairing status: ' . 'An error occurred',
            ], 500);
        }
    }

    /**
     * Delete a remote pairing session.
     */
    public function deletePairingSession(Request $request, $id)
    {
        try {
            $user = $this->getUser($request);
            $apiKey = $this->getApiKey($request);

            $session = WhatsAppSession::where('id', $id)
                ->where('user_id', $user->id)
                ->where('api_key_id', $apiKey?->id)
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pairing session not found.',
                ], 404);
            }

            $session->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pairing session deleted.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Delete pairing session error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting pairing session: ' . 'An error occurred',
            ], 500);
        }
    }

    /**
     * Get public queue status for the authenticated API user.
     */
    public function queueStatus(Request $request)
    {
        try {
            $user = $this->getUser($request);
            $userId = $user->id;

            $queueStatus = [
                'scheduled' => WhatsAppMessage::where('user_id', $userId)->where('status', 'scheduled')->count(),
                'queued' => WhatsAppMessage::where('user_id', $userId)->where('status', 'queued')->count(),
                'processing' => WhatsAppMessage::where('user_id', $userId)->where('status', 'processing')->count(),
                'sent' => WhatsAppMessage::where('user_id', $userId)->where('status', 'sent')->count(),
                'failed' => WhatsAppMessage::where('user_id', $userId)->where('status', 'failed')->count(),
                'pending' => WhatsAppMessage::where('user_id', $userId)->where('status', 'pending')->count(),
                'retrying' => WhatsAppMessage::where('user_id', $userId)->where('status', 'retrying')->count(),
            ];

            $totalJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();

            $recentMessages = WhatsAppMessage::where('user_id', $userId)
                ->whereIn('status', ['scheduled', 'queued', 'processing', 'failed', 'retrying'])
                ->latest()
                ->limit(10)
                ->get(['id', 'to', 'message_type', 'status', 'attempts', 'error_message', 'queued_at', 'scheduled_at', 'processed_at', 'completed_at', 'failed_at']);

            return response()->json([
                'success' => true,
                'message' => 'Queue status retrieved',
                'data' => [
                    'queue_status' => $queueStatus,
                    'total_jobs' => $totalJobs,
                    'failed_jobs' => $failedJobs,
                    'recent_messages' => $recentMessages,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting queue status: ' . 'An error occurred',
            ], 500);
        }
    }

    /**
     * Query message delivery status by internal message ID or external WhatsApp message_id.
     */
    public function messageStatus(Request $request, $id)
    {
        try {
            $user = $this->getUser($request);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $query = WhatsAppMessage::where('user_id', $user->id);

            if (is_numeric($id)) {
                $message = $query->where(function ($q) use ($id) {
                    $q->where('id', $id)
                      ->orWhere('message_id', (string) $id);
                })->first();
            } else {
                $message = $query->where('message_id', $id)->first();
            }

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'message_id' => $message->id,
                    'external_id' => $message->message_id,
                    'status' => $message->status, // queued | processing | sent | delivered | read | failed
                    'to' => $message->to,
                    'from' => $message->from,
                    'message_type' => $message->message_type,
                    'message' => $message->message,
                    'error_message' => $message->error_message,
                    'attempts' => $message->attempts,
                    'queued_at' => $message->queued_at,
                    'sent_at' => $message->sent_at,
                    'delivered_at' => $message->delivered_at,
                    'read_at' => $message->read_at,
                    'failed_at' => $message->failed_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving message status: ' . 'An error occurred',
            ], 500);
        }
    }
}
