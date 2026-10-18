<?php

namespace App\Modules\WhatsApp\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MetaWhatsAppConnection;
use App\Models\MetaWhatsAppPhoneNumber;
use App\Models\MetaWhatsAppTemplate;
use App\Models\MetaWhatsAppWaba;
use App\Services\MetaWhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class MetaWhatsAppController extends Controller
{
    protected MetaWhatsAppService $service;

    public function __construct(MetaWhatsAppService $service)
    {
        $this->service = $service;
    }

    /**
     * Get the Facebook OAuth/Embedded Signup URL.
     */
    public function authUrl(Request $request)
    {
        $user = Auth::user();
        $redirectUri = config('services.meta_whatsapp.redirect_uri', url('/api/whatsapp/cloud/callback'));
        $state = base64_encode(json_encode([
            'user_id' => $user->id,
            'nonce' => bin2hex(random_bytes(8)),
        ]));

        try {
            $url = $this->service->getAuthUrl($redirectUri, $state);
            return response()->json(['success' => true, 'data' => ['url' => $url, 'state' => $state]]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Handle OAuth callback from Meta.
     */
    public function callback(Request $request)
    {
        $code = $request->input('code');
        $state = $request->input('state');
        $error = $request->input('error');
        $errorReason = $request->input('error_reason');

        if ($error) {
            Log::warning('Meta WhatsApp OAuth denied', ['error' => $error, 'reason' => $errorReason]);
            return redirect('/dashboard/devices?meta_whatsapp=denied');
        }

        if (!$code) {
            return redirect('/dashboard/devices?meta_whatsapp=error&message=missing_code');
        }

        $stateData = json_decode(base64_decode($state), true);
        if (empty($stateData['user_id'])) {
            return redirect('/dashboard/devices?meta_whatsapp=error&message=invalid_state');
        }

        $userId = (int) $stateData['user_id'];
        $redirectUri = config('services.meta_whatsapp.redirect_uri', url('/api/whatsapp/cloud/callback'));

        try {
            $tokenData = $this->service->exchangeCode($code, $redirectUri);
            $accessToken = $tokenData['access_token'];
            $expiresIn = $tokenData['expires_in'] ?? 3600;

            // Optionally exchange for long-lived token
            $longLived = $this->service->exchangeLongLivedToken($accessToken);
            if (!empty($longLived['access_token'])) {
                $accessToken = $longLived['access_token'];
                $expiresIn = $longLived['expires_in'] ?? 5184000;
            }

            $connection = MetaWhatsAppConnection::updateOrCreate(
                ['user_id' => $userId],
                [
                    'access_token' => $accessToken,
                    'token_expires_at' => now()->addSeconds($expiresIn),
                    'status' => MetaWhatsAppConnection::STATUS_CONNECTED,
                    'scopes' => $longLived['scopes'] ?? $tokenData['scopes'] ?? ['business_management', 'whatsapp_business_management', 'whatsapp_business_messaging'],
                ]
            );

            $connection->user_id = $userId;

            $this->syncWABAsAndNumbers($connection, $accessToken);

            return redirect('/dashboard/devices?meta_whatsapp=connected');
        } catch (Exception $e) {
            Log::error('Meta WhatsApp OAuth callback failed: ' . $e->getMessage());
            return redirect('/dashboard/devices?meta_whatsapp=error&message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * List the authenticated user's Meta WhatsApp connections and phone numbers.
     */
    public function index()
    {
        $numbers = MetaWhatsAppPhoneNumber::where('user_id', Auth::id())
            ->with('waba:id,connection_id,name,meta_waba_id')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $numbers]);
    }

    /**
     * Fetch WABAs and phone numbers from Meta and sync them locally.
     */
    public function sync(Request $request)
    {
        $connection = MetaWhatsAppConnection::where('user_id', Auth::id())
            ->where('status', MetaWhatsAppConnection::STATUS_CONNECTED)
            ->first();

        if (!$connection) {
            return response()->json(['success' => false, 'message' => 'No connected Meta WhatsApp account.'], 404);
        }

        try {
            $this->syncWABAsAndNumbers($connection, $connection->access_token);
            return response()->json(['success' => true, 'message' => 'Sync complete.']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get available WABAs and numbers from Meta for the picker UI.
     */
    public function accounts(Request $request)
    {
        $connection = MetaWhatsAppConnection::where('user_id', Auth::id())
            ->where('status', MetaWhatsAppConnection::STATUS_CONNECTED)
            ->first();

        if (!$connection) {
            return response()->json(['success' => false, 'message' => 'No connected Meta WhatsApp account.'], 404);
        }

        try {
            $wabas = $this->service->listWABAs($connection->access_token);
            $result = [];

            foreach ($wabas as $waba) {
                $numbers = $this->service->listPhoneNumbers($waba['id'], $connection->access_token);
                $result[] = [
                    'waba_id' => $waba['id'],
                    'name' => $waba['name'] ?? null,
                    'timezone' => $waba['timezone'] ?? null,
                    'currency' => $waba['currency'] ?? null,
                    'status' => $waba['status'] ?? null,
                    'phone_numbers' => $numbers,
                ];
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Activate a selected phone number.
     */
    public function connect(Request $request)
    {
        $data = $request->validate([
            'waba_id' => 'required|string',
            'phone_number_id' => 'required|string',
            'phone_number' => 'required|string',
        ]);

        $connection = MetaWhatsAppConnection::where('user_id', Auth::id())
            ->where('status', MetaWhatsAppConnection::STATUS_CONNECTED)
            ->first();

        if (!$connection) {
            return response()->json(['success' => false, 'message' => 'No connected Meta WhatsApp account.'], 404);
        }

        $waba = MetaWhatsAppWaba::firstOrCreate(
            ['user_id' => Auth::id(), 'meta_waba_id' => $data['waba_id']],
            [
                'connection_id' => $connection->id,
                'name' => $data['waba_id'],
                'status' => 'ACTIVE',
            ]
        );

        $number = MetaWhatsAppPhoneNumber::updateOrCreate(
            ['user_id' => Auth::id(), 'meta_phone_number_id' => $data['phone_number_id']],
            [
                'waba_id' => $waba->id,
                'phone_number' => $data['phone_number'],
                'is_active' => true,
                'status' => 'CONNECTED',
            ]
        );

        try {
            $this->service->registerPhoneNumber($data['phone_number_id'], $connection->access_token);
        } catch (Exception $e) {
            Log::warning('Phone number registration skipped: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'data' => $number]);
    }

    /**
     * Disconnect a Meta WhatsApp number.
     */
    public function destroy($id)
    {
        $number = MetaWhatsAppPhoneNumber::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $number->delete();

        return response()->json(['success' => true, 'message' => 'Number disconnected.']);
    }

    /**
     * Send a free-form or template message from a connected number.
     */
    public function send(Request $request)
    {
        $data = $request->validate([
            'phone_number_id' => 'required|integer|exists:meta_whatsapp_phone_numbers,id',
            'to' => 'required|string',
            'type' => 'required|string|in:text,template',
            'text' => 'required_without:template_name|string|nullable',
            'template_name' => 'required_without:text|string|nullable',
            'language' => 'sometimes|string',
            'components' => 'sometimes|array',
        ]);

        $number = MetaWhatsAppPhoneNumber::where('id', $data['phone_number_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $connection = $number->waba->connection;

        if (!$connection) {
            return response()->json(['success' => false, 'message' => 'Connection not found.'], 404);
        }

        try {
            if ($data['type'] === 'template') {
                $result = $this->service->sendTemplateMessage(
                    $number->meta_phone_number_id,
                    $data['to'],
                    $data['template_name'],
                    $data['language'] ?? 'ar',
                    $connection->access_token,
                    $data['components'] ?? []
                );
            } else {
                $result = $this->service->sendTextMessage(
                    $number->meta_phone_number_id,
                    $data['to'],
                    $data['text'],
                    $connection->access_token
                );
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Sync WABAs and phone numbers from Meta Graph API.
     */
    protected function syncWABAsAndNumbers(MetaWhatsAppConnection $connection, string $accessToken): void
    {
        $wabas = $this->service->listWABAs($accessToken);

        DB::transaction(function () use ($connection, $wabas, $accessToken) {
            foreach ($wabas as $wabaData) {
                $waba = MetaWhatsAppWaba::updateOrCreate(
                    ['user_id' => $connection->user_id, 'meta_waba_id' => $wabaData['id']],
                    [
                        'connection_id' => $connection->id,
                        'name' => $wabaData['name'] ?? $wabaData['id'],
                        'timezone' => $wabaData['timezone'] ?? null,
                        'currency' => $wabaData['currency'] ?? null,
                        'status' => $wabaData['status'] ?? 'ACTIVE',
                        'raw_data' => $wabaData,
                    ]
                );

                $numbers = $this->service->listPhoneNumbers($wabaData['id'], $accessToken);
                foreach ($numbers as $phone) {
                    MetaWhatsAppPhoneNumber::updateOrCreate(
                        ['user_id' => $connection->user_id, 'meta_phone_number_id' => $phone['id']],
                        [
                            'waba_id' => $waba->id,
                            'phone_number' => $phone['display_phone_number'] ?? $phone['phone_number'] ?? $phone['id'],
                            'display_name' => $phone['verified_name'] ?? null,
                            'quality_rating' => $phone['quality_rating'] ?? null,
                            'status' => $phone['status'] ?? 'CONNECTED',
                            'raw_data' => $phone,
                        ]
                    );
                }

                $templates = $this->service->listTemplates($wabaData['id'], $accessToken);
                foreach ($templates as $template) {
                    MetaWhatsAppTemplate::updateOrCreate(
                        ['user_id' => $connection->user_id, 'waba_id' => $waba->id, 'name' => $template['name']],
                        [
                            'meta_template_id' => $template['id'] ?? null,
                            'category' => $template['category'] ?? 'MARKETING',
                            'language' => $template['language'] ?? 'ar',
                            'status' => $template['status'] ?? MetaWhatsAppTemplate::STATUS_PENDING,
                            'components' => $template['components'] ?? null,
                            'raw_data' => $template,
                        ]
                    );
                }
            }
        });
    }

    /**
     * List templates for a connected WABA.
     */
    public function templates(Request $request)
    {
        $wabaId = $request->input('waba_id');

        $query = MetaWhatsAppTemplate::where('user_id', Auth::id());
        if ($wabaId) {
            $query->where('waba_id', $wabaId);
        }

        $templates = $query->orderBy('name')->paginate(50);

        return response()->json(['success' => true, 'data' => $templates]);
    }

    /**
     * Create a new template and submit to Meta.
     */
    public function storeTemplate(Request $request)
    {
        $data = $request->validate([
            'waba_id' => 'required|integer|exists:meta_whatsapp_wabas,id',
            'name' => 'required|string|max:512',
            'category' => 'required|string|in:MARKETING,UTILITY,AUTHENTICATION',
            'language' => 'required|string|max:10',
            'components' => 'required|array',
        ]);

        $waba = MetaWhatsAppWaba::where('id', $data['waba_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $connection = $waba->connection;
        if (!$connection) {
            return response()->json(['success' => false, 'message' => 'Connection not found.'], 404);
        }

        try {
            $result = $this->service->createTemplate($waba->meta_waba_id, $connection->access_token, [
                'name' => strtolower(preg_replace('/[^a-z0-9_]/', '_', $data['name'])),
                'category' => $data['category'],
                'language' => $data['language'],
                'components' => $data['components'],
            ]);

            $template = MetaWhatsAppTemplate::create([
                'user_id' => Auth::id(),
                'waba_id' => $waba->id,
                'meta_template_id' => $result['id'] ?? null,
                'name' => $data['name'],
                'category' => $data['category'],
                'language' => $data['language'],
                'status' => $result['status'] ?? MetaWhatsAppTemplate::STATUS_PENDING,
                'components' => $data['components'],
                'raw_data' => $result,
            ]);

            return response()->json(['success' => true, 'data' => $template], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a template by name.
     */
    public function destroyTemplate($id)
    {
        $template = MetaWhatsAppTemplate::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $waba = $template->waba;
        $connection = $waba->connection;

        if ($connection) {
            try {
                $this->service->deleteTemplate($waba->meta_waba_id, $template->name, $connection->access_token);
            } catch (Exception $e) {
                Log::warning('Meta deleteTemplate failed: ' . $e->getMessage());
            }
        }

        $template->delete();

        return response()->json(['success' => true, 'message' => 'Template deleted.']);
    }
}
