<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\ApiKey;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSession;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    private SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    private function getUserId(Request $request): ?int
    {
        return Auth::id() ?? $request->attributes->get('api_key_user')?->id;
    }

    /**
     * List messages with search, filter, sort, and pagination.
     */
    public function index(Request $request)
    {
        try {
            $userId = $this->getUserId($request);

            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $query = WhatsAppMessage::with(['apiKey', 'session', 'user'])
                ->where('user_id', $userId)
                ->where('status', '!=', 'delivered');

            // Search by phone or message content
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('to', 'like', "%{$search}%")
                        ->orWhere('from', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            }

            // Filter by status
            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }

            // Filter by direction
            if ($direction = $request->input('direction')) {
                $query->where('direction', $direction);
            }

            // Filter by message type
            if ($type = $request->input('message_type')) {
                $query->where('message_type', $type);
            }

            // Filter by date range
            if ($from = $request->input('from')) {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to = $request->input('to')) {
                $query->whereDate('created_at', '<=', $to);
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $allowedSort = ['created_at', 'sent_at', 'status', 'message_type', 'to'];
            if (in_array($sortBy, $allowedSort)) {
                $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
            }

            $perPage = $request->input('per_page', 15);
            $perPage = min((int) $perPage, 100);

            $messages = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $messages,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading messages: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single message details.
     */
    public function show(Request $request, int $id)
    {
        try {
            $userId = $this->getUserId($request);

            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $message = WhatsAppMessage::with(['apiKey', 'session', 'user'])
                ->where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $message,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading message: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resend a message (creates a new queued copy).
     */
    public function resend(Request $request, int $id)
    {
        try {
            $userId = $this->getUserId($request);

            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $user = User::find($userId);

            // Check subscription has remaining messages
            if (!$this->subscriptionService->hasRemainingMessages($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No remaining messages in your subscription. Please upgrade your plan.',
                ], 403);
            }

            $original = WhatsAppMessage::where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if (!$original) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found',
                ], 404);
            }

            $session = WhatsAppSession::where('user_id', $userId)
                ->where('status', 'connected')
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp not connected',
                ], 400);
            }

            $message = WhatsAppMessage::create([
                'user_id' => $userId,
                'api_key_id' => $original->api_key_id,
                'session_id' => $session->id,
                'to' => $original->to,
                'message' => $original->message,
                'message_type' => $original->message_type,
                'direction' => 'outgoing',
                'status' => WhatsAppMessage::STATUS_QUEUED,
                'queued_at' => now(),
                'timestamp' => now(),
            ]);

            SendWhatsAppMessageJob::dispatch($message->id);

            return response()->json([
                'success' => true,
                'message' => 'Message resend queued',
                'data' => [
                    'message_id' => $message->id,
                    'status' => 'queued',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error resending message: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export messages as CSV.
     */
    public function export(Request $request)
    {
        try {
            $userId = $this->getUserId($request);

            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $query = WhatsAppMessage::where('user_id', $userId)
                ->where('status', '!=', 'delivered');

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('to', 'like', "%{$search}%")
                        ->orWhere('from', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            }

            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }
            if ($direction = $request->input('direction')) {
                $query->where('direction', $direction);
            }
            if ($type = $request->input('message_type')) {
                $query->where('message_type', $type);
            }
            if ($from = $request->input('from')) {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to = $request->input('to')) {
                $query->whereDate('created_at', '<=', $to);
            }

            $messages = $query->orderBy('created_at', 'desc')->get();

            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="messages-export.csv"',
            ];

            $callback = function () use ($messages) {
                $file = fopen('php://output', 'w');
                fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

                fputcsv($file, [
                    'ID', 'Direction', 'To', 'From', 'Message Type', 'Message', 'Status',
                    'API Key', 'Session', 'Sent At', 'Created At', 'Error Message',
                ]);

                foreach ($messages as $message) {
                    fputcsv($file, [
                        $message->id,
                        $message->direction,
                        $message->to,
                        $message->from,
                        $message->message_type,
                        $message->message,
                        $message->status,
                        $message->apiKey?->name ?? 'N/A',
                        $message->session?->phone_number ?? 'N/A',
                        $message->sent_at,
                        $message->created_at,
                        $message->error_message,
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting messages: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available filter options (statuses, types, directions).
     */
    public function filters(Request $request)
    {
        try {
            $userId = $this->getUserId($request);

            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'statuses' => WhatsAppMessage::select('status')->distinct()->where('user_id', $userId)->where('status', '!=', 'delivered')->pluck('status')->filter()->values(),
                    'types' => WhatsAppMessage::select('message_type')->distinct()->where('user_id', $userId)->whereNotIn('message_type', ['notification_template', 'e2e_notification'])->pluck('message_type')->filter()->values(),
                    'directions' => ['incoming', 'outgoing'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading filters: ' . $e->getMessage(),
            ], 500);
        }
    }
}
