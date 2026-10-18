<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\N8nConnection;
use App\Models\N8nTemplate;
use App\Services\ActivityLogger;
use App\Services\FraudDetectionService;
use App\Services\SafeUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class N8nController extends Controller
{
    /**
     * List n8n connections for authenticated user
     */
    public function index(Request $request)
    {
        $query = N8nConnection::where('user_id', Auth::id())->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        if ($request->has('status') && $request->input('status') !== '') {
            $query->where('status', $request->input('status'));
        }

        $connections = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $connections,
        ]);
    }

    /**
     * Show single n8n connection
     */
    public function show($id)
    {
        $connection = N8nConnection::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $connection,
        ]);
    }

    /**
     * Create a new n8n connection
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:1000',
            'api_key' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $url = rtrim($request->input('url'), '/');
        if (!SafeUrl::isSafe($url)) {
            FraudDetectionService::report('ssrf_attempt', 'critical', 'User attempted to create an n8n connection to an unsafe/internal URL', [
                'url' => $url,
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'n8n URL is not safe. It must be a public http(s) address.',
            ], 400);
        }

        $key = $request->input('api_key');
        $prefix = N8nConnection::generatePrefix($key);

        $connection = N8nConnection::create([
            'user_id' => Auth::id(),
            'name' => $request->input('name'),
            'url' => $url,
            'api_key' => $key,
            'prefix' => $prefix,
            'status' => 'not_connected',
            'is_active' => true,
        ]);

        ActivityLogger::log('n8n_connection_created', 'n8n_connection', $connection->id, Auth::id(), 'n8n connection created', ['name' => $connection->name]);

        return response()->json([
            'success' => true,
            'message' => 'n8n connection created',
            'data' => [
                'id' => $connection->id,
                'name' => $connection->name,
                'url' => $connection->url,
                'prefix' => $connection->prefix,
                'status' => $connection->status,
                'is_active' => $connection->is_active,
                'created_at' => $connection->created_at,
            ],
        ], 201);
    }

    /**
     * Update an n8n connection
     */
    public function update(Request $request, $id)
    {
        $connection = N8nConnection::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'url' => 'sometimes|url|max:1000',
            'api_key' => 'sometimes|string|max:1000',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = [];
        if ($request->has('name')) {
            $updateData['name'] = $request->input('name');
        }
        if ($request->has('url')) {
            $url = rtrim($request->input('url'), '/');
            if (!SafeUrl::isSafe($url)) {
                FraudDetectionService::report('ssrf_attempt', 'critical', 'User attempted to update an n8n connection to an unsafe/internal URL', [
                    'url' => $url,
                    'connection_id' => $id,
                    'user_id' => Auth::id(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'n8n URL is not safe. It must be a public http(s) address.',
                ], 400);
            }
            $updateData['url'] = $url;
        }
        if ($request->has('api_key')) {
            $updateData['api_key'] = $request->input('api_key');
            $updateData['prefix'] = N8nConnection::generatePrefix($request->input('api_key'));
        }
        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->input('is_active');
        }

        $connection->update($updateData);

        ActivityLogger::log('n8n_connection_updated', 'n8n_connection', $connection->id, Auth::id(), 'n8n connection updated', $updateData);

        return response()->json([
            'success' => true,
            'message' => 'n8n connection updated',
            'data' => $connection,
        ]);
    }

    /**
     * Delete an n8n connection
     */
    public function destroy($id)
    {
        $connection = N8nConnection::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $name = $connection->name;
        $connection->delete();

        ActivityLogger::log('n8n_connection_deleted', 'n8n_connection', $id, Auth::id(), 'n8n connection deleted', ['name' => $name]);

        return response()->json([
            'success' => true,
            'message' => 'n8n connection deleted',
        ]);
    }

    /**
     * Test an n8n connection
     */
    public function test($id)
    {
        $connection = N8nConnection::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!$connection->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Connection is disabled',
            ], 400);
        }

        if (!SafeUrl::isSafe($connection->url)) {
            FraudDetectionService::report('ssrf_attempt_executed', 'critical', 'n8n connection test attempted to call an unsafe/internal URL', [
                'url' => $connection->url,
                'connection_id' => $id,
                'user_id' => Auth::id(),
            ]);
            $connection->update(['status' => 'error', 'last_error' => 'Unsafe n8n URL']);
            return response()->json([
                'success' => false,
                'message' => 'n8n URL is not safe. It must be a public http(s) address.',
            ], 400);
        }

        try {
            $response = Http::timeout(15)
                ->withoutRedirecting()
                ->withHeaders([
                    'X-N8N-API-KEY' => $connection->api_key,
                    'Accept' => 'application/json',
                ])
                ->get($connection->url . '/api/v1/workflows');

            if ($response->successful()) {
                $connection->update([
                    'status' => 'connected',
                    'last_error' => null,
                    'last_tested_at' => now(),
                    'workflows_count' => count($response->json('data') ?? []),
                ]);

                ActivityLogger::log('n8n_connection_tested', 'n8n_connection', $connection->id, Auth::id(), 'n8n connection test succeeded');

                return response()->json([
                    'success' => true,
                    'message' => 'تم الاتصال بنجاح',
                    'data' => [
                        'status' => 'connected',
                        'workflows_count' => $connection->workflows_count,
                    ],
                ]);
            }

            $error = 'HTTP ' . $response->status();
            $connection->update([
                'status' => 'error',
                'last_error' => $error,
                'last_tested_at' => now(),
            ]);

            ActivityLogger::log('n8n_connection_test_failed', 'n8n_connection', $connection->id, Auth::id(), 'n8n connection test failed', ['error' => $error, 'http_status' => $response->status()]);

            return response()->json([
                'success' => false,
                'message' => 'فشل الاتصال: ' . $error,
                'error' => $error,
            ], 400);
        } catch (\Exception $e) {
            $connection->update([
                'status' => 'error',
                'last_error' => 'Connection test failed',
                'last_tested_at' => now(),
            ]);

            ActivityLogger::log('n8n_connection_test_failed', 'n8n_connection', $connection->id, Auth::id(), 'n8n connection test failed');

            return response()->json([
                'success' => false,
                'message' => 'فشل الاتصال: تعذر الوصول إلى خادم n8n.',
            ], 500);
        }
    }

    /**
     * Get statistics for n8n integration
     */
    public function stats(Request $request)
    {
        $userId = Auth::id();

        $connectionCount = N8nConnection::where('user_id', $userId)->count();
        $workflowCount = N8nConnection::where('user_id', $userId)->sum('workflows_count');
        $requestCount = N8nConnection::where('user_id', $userId)->sum('requests_count');

        $messageCount = \App\Models\WhatsAppMessage::where('user_id', $userId)
            ->whereNotNull('api_key_id')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'connections_count' => $connectionCount,
                'workflows_count' => (int) $workflowCount,
                'requests_count' => (int) $requestCount,
                'messages_count' => (int) $messageCount,
            ],
        ]);
    }

    /**
     * Get integration status summary
     */
    public function status()
    {
        $connection = N8nConnection::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('last_used_at', 'desc')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'is_connected' => $connection && $connection->status === 'connected',
                'status' => $connection?->status ?? 'not_connected',
                'last_error' => $connection?->last_error,
                'connection' => $connection ? [
                    'id' => $connection->id,
                    'name' => $connection->name,
                    'url' => $connection->url,
                ] : null,
            ],
        ]);
    }

    /**
     * List n8n templates
     */
    public function templates()
    {
        $templates = N8nTemplate::where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Get user's available sessions for n8n node
     */
    public function sessions(Request $request)
    {
        $sessions = \App\Models\WhatsAppSession::where('user_id', Auth::id())
            ->where('status', 'connected')
            ->get(['id', 'phone_number', 'device_name', 'status', 'last_activity_at']);

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }
}
