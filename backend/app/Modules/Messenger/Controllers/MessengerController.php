<?php

namespace App\Modules\Messenger\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\SendMessengerMessageJob;
use App\Models\Contact;
use App\Models\MessengerAutoReply;
use App\Models\MessengerContact;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Models\MessengerPage;
use App\Services\MessengerService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MessengerController extends Controller
{
    private MessengerService $messenger;
    private SubscriptionService $subscriptionService;

    public function __construct(MessengerService $messenger, SubscriptionService $subscriptionService)
    {
        $this->messenger = $messenger;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Get connected Messenger pages and overall status.
     */
    public function status()
    {
        $pages = MessengerPage::where('user_id', Auth::id())
            ->where('status', 'connected')
            ->orderByDesc('connected_at')
            ->get(['id', 'page_id', 'page_name', 'status', 'connected_at', 'last_activity_at', 'webhook_set']);

        return response()->json([
            'success' => true,
            'data' => [
                'connected' => $pages->isNotEmpty(),
                'pages' => $pages,
            ],
        ]);
    }

    /**
     * Exchange a short-lived user access token and list manageable pages.
     */
    public function pagesFromToken(Request $request)
    {
        $data = $request->validate([
            'access_token' => 'required|string|min:20',
        ]);

        try {
            $longToken = $this->messenger->exchangeUserToken($data['access_token'])['access_token'] ?? null;
            if (!$longToken) {
                return response()->json(['success' => false, 'message' => 'Failed to exchange access token.'], 400);
            }

            $pages = $this->messenger->listPages($longToken);

            // Do not store anything yet; let the user select pages first.
            return response()->json([
                'success' => true,
                'data' => [
                    'access_token' => $longToken,
                    'pages' => $pages,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Messenger pagesFromToken error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load Facebook pages: ' . $e->getMessage()], 400);
        }
    }

    /**
     * Generate a server-side Facebook OAuth URL for the current user.
     */
    public function oauthAuth()
    {
        $userId = Auth::id();
        $state = Str::random(40);
        $redirectUri = rtrim(config('app.url'), '/') . '/api/messenger/callback';
        $scope = implode(',', [
            'pages_messaging',
            'pages_read_engagement',
            'pages_manage_metadata',
            'pages_show_list',
        ]);

        Cache::put('messenger_oauth:' . $state, ['user_id' => $userId], now()->addMinutes(10));

        $apiVersion = config('services.messenger.api_version', 'v21.0');
        $url = sprintf(
            'https://www.facebook.com/%s/dialog/oauth?client_id=%s&redirect_uri=%s&state=%s&scope=%s&response_type=code',
            $apiVersion,
            urlencode(config('services.messenger.app_id') ?? config('services.meta_marketing.app_id')),
            urlencode($redirectUri),
            urlencode($state),
            urlencode($scope)
        );

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $url,
                'state' => $state,
                'redirect_uri' => $redirectUri,
                'scope' => $scope,
            ],
        ]);
    }

    /**
     * Facebook OAuth callback: exchange code, fetch pages, and return a popup post-message view.
     */
    public function oauthCallback(Request $request)
    {
        $code = $request->input('code');
        $state = $request->input('state');

        if (empty($code) || empty($state)) {
            return $this->callbackView(['success' => false, 'message' => 'Missing authorization code or state.']);
        }

        $cacheKey = 'messenger_oauth:' . $state;
        $stored = Cache::get($cacheKey);
        Cache::forget($cacheKey);

        if (empty($stored) || empty($stored['user_id'])) {
            return $this->callbackView(['success' => false, 'message' => 'Invalid or expired state.']);
        }

        $redirectUri = rtrim(config('app.url'), '/') . '/api/messenger/callback';

        try {
            Auth::loginUsingId($stored['user_id']);

            $tokenData = $this->messenger->exchangeCode($code, $redirectUri);
            $shortToken = $tokenData['access_token'] ?? null;
            if (!$shortToken) {
                return $this->callbackView(['success' => false, 'message' => 'Failed to obtain access token from Facebook.']);
            }

            $longTokenData = $this->messenger->exchangeUserToken($shortToken);
            $longToken = $longTokenData['access_token'] ?? null;
            if (!$longToken) {
                return $this->callbackView(['success' => false, 'message' => 'Failed to exchange token.']);
            }

            $pages = $this->messenger->listPages($longToken);

            return $this->callbackView([
                'success' => true,
                'data' => [
                    'access_token' => $longToken,
                    'pages' => $pages,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Messenger OAuth callback error: ' . $e->getMessage());
            return $this->callbackView(['success' => false, 'message' => 'Facebook login failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Render the OAuth callback HTML view for popup postMessage.
     */
    private function callbackView(array $payload)
    {
        $json = json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        return response()->view('messenger.callback', ['payload' => $json], 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    /**
     * Connect one or more selected Facebook pages.
     */
    public function connect(Request $request)
    {
        $data = $request->validate([
            'access_token' => 'required|string|min:20',
            'pages' => 'required|array|min:1',
            'pages.*.id' => 'required|string',
            'pages.*.name' => 'nullable|string',
            'pages.*.access_token' => 'required|string|min:20',
        ]);

        $user = Auth::user();
        $connected = [];

        DB::beginTransaction();
        try {
            foreach ($data['pages'] as $pageData) {
                $pageId = (string) $pageData['id'];
                $pageName = $pageData['name'] ?? null;
                $pageToken = $pageData['access_token'];

                $existing = MessengerPage::where('user_id', $user->id)
                    ->where('page_id', $pageId)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'page_name' => $pageName,
                        'access_token' => $pageToken,
                        'status' => 'connected',
                        'connected_at' => now(),
                        'last_sync_at' => now(),
                    ]);
                    $page = $existing;
                } else {
                    $page = MessengerPage::create([
                        'user_id' => $user->id,
                        'page_id' => $pageId,
                        'page_name' => $pageName,
                        'access_token' => $pageToken,
                        'status' => 'connected',
                        'connected_at' => now(),
                        'last_sync_at' => now(),
                    ]);
                }

                // Subscribe the app to the page's webhook events.
                $webhookSet = $this->messenger->subscribePageToApp($pageId, $pageToken);
                $page->update(['webhook_set' => $webhookSet]);

                // Ensure an auto reply row exists (disabled by default).
                MessengerAutoReply::firstOrCreate(
                    ['page_id' => $page->id, 'user_id' => $user->id],
                    [
                        'is_enabled' => false,
                        'mode' => 'ai',
                        'ai_tone' => 'friendly',
                        'ai_language' => 'ar',
                        'ai_system_prompt' => 'أنت مساعد ذكي ومحترف لخدمة العملاء على Messenger. رد بأدب واختصار.',
                    ]
                );

                $connected[] = $page->only(['id', 'page_id', 'page_name', 'status', 'webhook_set', 'connected_at']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Messenger page(s) connected successfully.',
                'data' => $connected,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Messenger connect error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to connect page: ' . $e->getMessage()], 400);
        }
    }

    /**
     * Disconnect a page and optionally unsubscribe webhooks.
     */
    public function disconnect($id)
    {
        $page = MessengerPage::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        try {
            $token = $page->getAccessToken();
            if ($token) {
                $this->messenger->unsubscribePageFromApp($page->page_id, $token);
            }
        } catch (\Throwable $e) {
            Log::warning('Messenger disconnect unsubscribe failed: ' . $e->getMessage());
        }

        $page->update([
            'status' => 'disconnected',
            'access_token' => '',
            'webhook_set' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Messenger page disconnected.',
        ]);
    }

    /**
     * Reconnect a previously connected page using a fresh access token.
     */
    public function reconnect(Request $request, $id)
    {
        $data = $request->validate([
            'access_token' => 'required|string|min:20',
            'page_access_token' => 'required|string|min:20',
        ]);

        $page = MessengerPage::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        try {
            $page->update([
                'access_token' => $data['page_access_token'],
                'status' => 'connected',
                'connected_at' => now(),
                'last_sync_at' => now(),
            ]);

            $webhookSet = $this->messenger->subscribePageToApp($page->page_id, $data['page_access_token']);
            $page->update(['webhook_set' => $webhookSet]);

            return response()->json([
                'success' => true,
                'message' => 'Messenger page reconnected.',
                'data' => $page->only(['id', 'page_id', 'page_name', 'status', 'webhook_set', 'connected_at']),
            ]);
        } catch (\Throwable $e) {
            Log::error('Messenger reconnect error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to reconnect: ' . $e->getMessage()], 400);
        }
    }

    /**
     * Dashboard stats for Messenger.
     */
    public function dashboardStats()
    {
        $userId = Auth::id();

        $pageIds = MessengerPage::where('user_id', $userId)->pluck('id');
        $totalPages = $pageIds->count();
        $connectedPages = MessengerPage::where('user_id', $userId)->where('status', 'connected')->count();

        $totalMessages = MessengerMessage::where('user_id', $userId)->count();
        $incoming = MessengerMessage::where('user_id', $userId)->where('direction', 'incoming')->count();
        $outgoing = MessengerMessage::where('user_id', $userId)->where('direction', 'outgoing')->count();
        $sent = MessengerMessage::where('user_id', $userId)->where('status', MessengerMessage::STATUS_SENT)->count();
        $failed = MessengerMessage::where('user_id', $userId)->where('status', MessengerMessage::STATUS_FAILED)->count();

        $conversations = MessengerConversation::where('user_id', $userId)->count();
        $contacts = MessengerContact::where('user_id', $userId)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_pages' => $totalPages,
                'connected_pages' => $connectedPages,
                'total_messages' => $totalMessages,
                'incoming' => $incoming,
                'outgoing' => $outgoing,
                'sent' => $sent,
                'failed' => $failed,
                'total_conversations' => $conversations,
                'total_contacts' => $contacts,
            ],
        ]);
    }

    /**
     * List conversations (Messenger inbox).
     */
    public function inbox(Request $request)
    {
        $userId = Auth::id();
        $search = $request->input('search');

        $query = MessengerConversation::where('user_id', $userId)
            ->with(['page:id,page_id,page_name', 'contact:id,name,profile_pic'])
            ->orderByDesc('last_message_at');

        if ($search) {
            $query->whereHas('contact', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('psid', 'like', "%{$search}%");
            });
        }

        $conversations = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ]);
    }

    /**
     * Get messages for a conversation.
     */
    public function conversationMessages($id)
    {
        $conversation = MessengerConversation::where('id', $id)
            ->where('user_id', Auth::id())
            ->with(['page:id,page_id,page_name', 'contact:id,name,profile_pic,psid'])
            ->firstOrFail();

        $messages = MessengerMessage::where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => $conversation,
                'messages' => $messages,
            ],
        ]);
    }

    /**
     * Send a message from Wavixa to a Messenger user.
     */
    public function sendMessage(Request $request)
    {
        $data = $request->validate([
            'conversation_id' => 'required|integer|exists:messenger_conversations,id',
            'message' => 'required|string|max:2000',
            'message_type' => 'nullable|string|in:text,image,audio,video,file',
            'media_url' => 'nullable|string|url',
        ]);

        $user = Auth::user();
        $conversation = MessengerConversation::where('id', $data['conversation_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        $page = $conversation->page;
        if (!$page || $page->status !== 'connected') {
            return response()->json(['success' => false, 'message' => 'Messenger page is not connected.'], 400);
        }

        if (!$this->subscriptionService->hasRemainingMessages($user)) {
            return response()->json(['success' => false, 'message' => 'No remaining message credits.'], 403);
        }

        $messageType = $data['message_type'] ?? 'text';
        $text = $data['message'];

        $message = MessengerMessage::create([
            'user_id' => $user->id,
            'page_id' => $page->id,
            'conversation_id' => $conversation->id,
            'psid' => $conversation->psid,
            'direction' => 'outgoing',
            'message_type' => $messageType,
            'message' => $text,
            'media_url' => $data['media_url'] ?? null,
            'status' => MessengerMessage::STATUS_PENDING,
        ]);

        SendMessengerMessageJob::dispatch($message->id);

        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Message queued for sending.',
            'data' => ['message_id' => $message->id, 'status' => 'pending'],
        ]);
    }

    /**
     * Toggle AI auto reply for a single conversation.
     */
    public function toggleAi(Request $request, $id)
    {
        $conversation = MessengerConversation::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $disabledUntil = $conversation->isAiDisabled() ? null : now()->addYears(10);
        $conversation->update(['ai_disabled_until' => $disabledUntil]);

        return response()->json([
            'success' => true,
            'data' => [
                'ai_disabled' => !is_null($disabledUntil),
            ],
        ]);
    }

    /**
     * List Messenger contacts and merge with global contacts.
     */
    public function contacts(Request $request)
    {
        $userId = Auth::id();
        $search = $request->input('search');
        $pageId = $request->input('page_id');

        $messengerContacts = MessengerContact::where('user_id', $userId)
            ->with('page:id,page_id,page_name')
            ->when($pageId, function ($q) use ($pageId) {
                $q->where('page_id', $pageId);
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                        ->orWhere('psid', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('last_message_at')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $messengerContacts,
        ]);
    }

    /**
     * Messenger settings/status for a specific page.
     */
    public function config()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'app_id' => config('services.messenger.app_id') ?: config('services.meta_marketing.app_id'),
                'api_version' => config('services.messenger.api_version', 'v21.0'),
                'scopes' => [
                    'pages_messaging',
                    'pages_read_engagement',
                    'pages_manage_metadata',
                    'pages_show_list',
                ],
                'webhook_url' => rtrim(config('app.url'), '/') . '/api/messenger/webhook',
            ],
        ]);
    }

    public function settings($id)
    {
        $page = MessengerPage::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'page' => $page->only(['id', 'page_id', 'page_name', 'status', 'connected_at', 'last_sync_at', 'webhook_set']),
                'stats' => [
                    'total_messages' => MessengerMessage::where('page_id', $page->id)->count(),
                    'messages_sent' => MessengerMessage::where('page_id', $page->id)->where('direction', 'outgoing')->count(),
                    'messages_received' => MessengerMessage::where('page_id', $page->id)->where('direction', 'incoming')->count(),
                    'total_conversations' => MessengerConversation::where('page_id', $page->id)->count(),
                    'total_contacts' => MessengerContact::where('page_id', $page->id)->count(),
                ],
            ],
        ]);
    }
}
