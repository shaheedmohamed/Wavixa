<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Models\WhatsAppSession;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ApiKeysController extends Controller
{
    /**
     * List all API keys for the authenticated user
     */
    public function index(Request $request)
    {
        $query = ApiKey::where('user_id', Auth::id())
            ->with(['whatsappSession', 'metaPhoneNumber'])
            ->withCount('logs');

        // Search by name or prefix
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('prefix', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', $request->input('is_active'));
        }

        $apiKeys = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $apiKeys,
        ]);
    }

    /**
     * Create a new API key
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'expires_at' => 'nullable|date|after:now',
            'permissions' => 'nullable|array',
            'permissions.*' => 'in:send_messages,read_message_status,read_sessions,read_balance,use_webhooks',
            'whatsapp_session_id' => [
                'nullable',
                'integer',
                Rule::exists('whatsapp_sessions', 'id')->where(function ($query) {
                    $query->where('user_id', Auth::id())->where('status', 'connected');
                }),
            ],
            'meta_phone_number_id' => [
                'nullable',
                'integer',
                Rule::exists('meta_test_phone_numbers', 'id')->where(function ($query) {
                    $query->where('user_id', Auth::id())->where('is_active', true);
                }),
            ],
            'allow_remote_pairing' => 'sometimes|boolean',
            'remote_pairing_limit' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->boolean('allow_remote_pairing')) {
            $planSlug = Auth::user()->activeSubscription?->plan?->slug ?? '';
            $hasPartnersPlan = str_contains($planSlug, 'partners');
            if (!$hasPartnersPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'يتطلب اتصال API خطة Partners.',
                ], 403);
            }
        }

        $key = ApiKey::generateKey();
        $prefix = ApiKey::generatePrefix($key);

        $apiKey = ApiKey::create([
            'user_id' => Auth::id(),
            'whatsapp_session_id' => $request->input('whatsapp_session_id'),
            'meta_phone_number_id' => $request->input('meta_phone_number_id'),
            'allow_remote_pairing' => $request->boolean('allow_remote_pairing', false),
            'remote_pairing_limit' => $request->input('remote_pairing_limit', 1),
            'name' => $request->input('name'),
            'key' => $key,
            'prefix' => $prefix,
            'permissions' => $request->input('permissions'),
            'expires_at' => $request->input('expires_at'),
        ]);

        AuditLogger::log('api_key_created', 'api_key', $apiKey->id, 'API key created', ['name' => $apiKey->name, 'whatsapp_session_id' => $apiKey->whatsapp_session_id]);

        return response()->json([
            'success' => true,
            'message' => 'API key created successfully',
            'data' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'prefix' => $apiKey->prefix,
                'key' => $key, // Show full key only once
                'is_active' => $apiKey->is_active,
                'requests_count' => $apiKey->requests_count,
                'last_used_at' => $apiKey->last_used_at,
                'expires_at' => $apiKey->expires_at,
                'created_at' => $apiKey->created_at,
                'whatsapp_session' => $apiKey->whatsappSession,
                'meta_phone_number' => $apiKey->metaPhoneNumber,
            ],
        ], 201);
    }

    /**
     * Regenerate an API key
     */
    public function regenerate($id)
    {
        $apiKey = ApiKey::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $key = ApiKey::generateKey();
        $prefix = ApiKey::generatePrefix($key);

        $apiKey->update([
            'key' => $key,
            'prefix' => $prefix,
            'requests_count' => 0,
            'last_used_at' => null,
        ]);

        AuditLogger::log('api_key_regenerated', 'api_key', $apiKey->id, 'API key regenerated', ['name' => $apiKey->name]);

        return response()->json([
            'success' => true,
            'message' => 'API key regenerated successfully',
            'data' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'prefix' => $apiKey->prefix,
                'key' => $key, // Show full key only once
                'is_active' => $apiKey->is_active,
                'requests_count' => $apiKey->requests_count,
                'last_used_at' => $apiKey->last_used_at,
                'expires_at' => $apiKey->expires_at,
                'created_at' => $apiKey->created_at,
                'whatsapp_session' => $apiKey->whatsappSession,
            ],
        ]);
    }

    /**
     * Update API key
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'expires_at' => 'nullable|date|after:now',
            'permissions' => 'nullable|array',
            'permissions.*' => 'in:send_messages,read_message_status,read_sessions,read_balance,use_webhooks',
            'whatsapp_session_id' => [
                'nullable',
                'integer',
                Rule::exists('whatsapp_sessions', 'id')->where(function ($query) {
                    $query->where('user_id', Auth::id())->where('status', 'connected');
                }),
            ],
            'meta_phone_number_id' => [
                'nullable',
                'integer',
                Rule::exists('meta_test_phone_numbers', 'id')->where(function ($query) {
                    $query->where('user_id', Auth::id())->where('is_active', true);
                }),
            ],
            'allow_remote_pairing' => 'sometimes|boolean',
            'remote_pairing_limit' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('allow_remote_pairing') && $request->boolean('allow_remote_pairing')) {
            $planSlug = Auth::user()->activeSubscription?->plan?->slug ?? '';
            $hasPartnersPlan = str_contains($planSlug, 'partners');
            if (!$hasPartnersPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'يتطلب اتصال API خطة Partners.',
                ], 403);
            }
        }

        $apiKey = ApiKey::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $updateData = [];
        if ($request->has('name')) {
            $updateData['name'] = $request->input('name');
        }
        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->input('is_active');
        }
        if ($request->has('expires_at')) {
            $updateData['expires_at'] = $request->input('expires_at');
        }
        if ($request->has('whatsapp_session_id')) {
            $updateData['whatsapp_session_id'] = $request->input('whatsapp_session_id');
        }
        if ($request->has('meta_phone_number_id')) {
            $updateData['meta_phone_number_id'] = $request->input('meta_phone_number_id');
        }
        if ($request->has('allow_remote_pairing')) {
            $updateData['allow_remote_pairing'] = $request->boolean('allow_remote_pairing');
        }
        if ($request->has('remote_pairing_limit')) {
            $updateData['remote_pairing_limit'] = $request->input('remote_pairing_limit');
        }
        if ($request->has('permissions')) {
            $updateData['permissions'] = $request->input('permissions');
        }

        $apiKey->update($updateData);

        AuditLogger::log('api_key_updated', 'api_key', $apiKey->id, 'API key updated', $updateData);

        return response()->json([
            'success' => true,
            'message' => 'API key updated successfully',
            'data' => $apiKey->load(['whatsappSession', 'metaPhoneNumber']),
        ]);
    }

    /**
     * Delete an API key
     */
    public function destroy($id)
    {
        $apiKey = ApiKey::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $apiKey->delete();

        AuditLogger::log('api_key_deleted', 'api_key', $apiKey->id, 'API key deleted', ['name' => $apiKey->name]);

        return response()->json([
            'success' => true,
            'message' => 'API key deleted successfully',
        ]);
    }

    /**
     * Get API key logs
     */
    public function logs(Request $request, $id)
    {
        $apiKey = ApiKey::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $logs = ApiLog::where('api_key_id', $apiKey->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Test an API key
     */
    public function test($id)
    {
        $apiKey = ApiKey::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!$apiKey->isUsable()) {
            return response()->json([
                'success' => false,
                'message' => 'API key is inactive or expired',
            ], 400);
        }

        // Update usage stats
        $apiKey->markUsed();

        // Log the test request
        ApiLog::create([
            'api_key_id' => $apiKey->id,
            'user_id' => $apiKey->user_id,
            'method' => 'GET',
            'endpoint' => 'api/api-keys/{id}/test',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'status_code' => 200,
            'response_message' => 'Test successful',
            'request_data' => null,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API key is valid',
            'data' => [
                'user' => [
                    'id' => $apiKey->user->id,
                    'name' => $apiKey->user->name,
                    'email' => $apiKey->user->email,
                ],
                'key_name' => $apiKey->name,
                'requests_count' => $apiKey->requests_count,
                'last_used_at' => $apiKey->last_used_at,
                'whatsapp_session' => $apiKey->whatsappSession,
            ],
            'status_code' => 200,
        ]);
    }
}
