<?php

namespace App\Modules\MetaTest\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Modules\MetaTest\Models\MetaTestMessage;
use App\Modules\MetaTest\Models\MetaTestPhoneNumber;
use App\Modules\MetaTest\Services\MetaTestMessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class MetaTestChatController extends Controller
{
    /**
     * List conversations: one row per customer number, newest activity first.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MetaTestMessage::where('user_id', Auth::id())
            ->where('is_hidden', false)
            ->where('message_type', '!=', 'reaction')
            ->selectRaw("CASE WHEN direction = 'outgoing' THEN to_phone ELSE from_phone END as peer")
            ->selectRaw('MAX(id) as last_id')
            ->selectRaw('MAX(created_at) as last_at')
            ->selectRaw('COUNT(*) as msg_count')
            ->selectRaw("SUM(CASE WHEN direction = 'incoming' AND status = 'received' THEN 1 ELSE 0 END) as unread")
            ->groupBy('peer')
            ->orderByDesc('last_id');

        if ($request->filled('search')) {
            $term = preg_replace('/\D/', '', $request->input('search'));
            $names = Contact::where('user_id', Auth::id())
                ->where('name', 'like', '%' . $request->input('search') . '%')
                ->pluck('phone')
                ->map(fn ($p) => ltrim($p, '+'))
                ->all();

            // The peer is always one of from/to, so filter rows pre-grouping.
            $query->where(function ($q) use ($term, $names) {
                $q->where('to_phone', 'like', '%' . $term . '%')
                    ->orWhere('from_phone', 'like', '%' . $term . '%');
                if ($names) {
                    $q->orWhereIn('to_phone', $names)->orWhereIn('from_phone', $names);
                }
            });
        }

        $conversations = $query->paginate(40);

        $lastIds = collect($conversations->items())->pluck('last_id')->all();
        $lastMsgs = MetaTestMessage::whereIn('id', $lastIds)
            ->with('phoneNumber:id,phone_number,display_name')
            ->get()->keyBy('id');

        // Contact names: stored as +phone, peers stored without +.
        $peers = collect($conversations->items())->pluck('peer')->all();
        $contactNames = Contact::where('user_id', Auth::id())
            ->whereIn('phone', array_map(fn ($p) => '+' . $p, $peers))
            ->pluck('name', 'phone');

        $items = collect($conversations->items())->map(function ($c) use ($lastMsgs, $contactNames) {
            $last = $lastMsgs->get($c->last_id);
            $name = $contactNames->get('+' . $c->peer);

            return [
                'peer' => $c->peer,
                'contact_name' => $name,
                'msg_count' => (int) $c->msg_count,
                'unread' => (int) $c->unread,
                'last_at' => $c->last_at,
                'last_body' => $last?->body,
                'last_direction' => $last?->direction,
                'last_status' => $last?->status,
                'last_type' => $last?->message_type,
                'phone_number_id' => $last?->phone_number_id,
                'our_number' => $last?->phoneNumber?->phone_number,
                'our_display' => $last?->phoneNumber?->display_name,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
            ],
            'total_unread' => (int) MetaTestMessage::where('user_id', Auth::id())
                ->where('direction', 'incoming')->where('status', 'received')->count(),
        ]);
    }

    /**
     * Full thread with one customer number (newest last), plus since-id polling.
     * Marks incoming messages as read — locally AND on Meta (blue ticks).
     */
    public function messages(Request $request): JsonResponse
    {
        $request->validate(['peer' => 'required|string']);

        $peer = $request->input('peer');

        $query = MetaTestMessage::where('user_id', Auth::id())
            ->where('is_hidden', false)
            ->where(function ($q) use ($peer) {
                $q->where('to_phone', $peer)->orWhere('from_phone', $peer);
            })
            ->orderBy('id');

        if ($request->filled('since')) {
            $query->where('id', '>', (int) $request->input('since'));
        }

        $messages = $query->get(['id', 'direction', 'body', 'message_type', 'status', 'meta_message_id', 'reply_to_id', 'created_at', 'phone_number_id', 'from_phone', 'to_phone', 'raw_payload', 'sent_at', 'delivered_at', 'read_at', 'failed_at', 'error_message']);

        // Collect unread incoming to mark read + send Meta read receipts.
        $unread = MetaTestMessage::where('user_id', Auth::id())
            ->where('from_phone', $peer)
            ->where('direction', 'incoming')
            ->where('status', MetaTestMessage::STATUS_RECEIVED)
            ->get();

        if ($unread->isNotEmpty()) {
            $messaging = new MetaTestMessagingService(Auth::id());
            foreach ($unread as $m) {
                if ($m->meta_message_id && $m->phone_number_id) {
                    $phone = MetaTestPhoneNumber::find($m->phone_number_id);
                    if ($phone) {
                        $messaging->markReadOnMeta($phone, $m->meta_message_id);
                    }
                }
            }

            MetaTestMessage::whereIn('id', $unread->pluck('id'))
                ->update(['status' => MetaTestMessage::STATUS_READ, 'read_at' => now()]);
        }

        $contact = Contact::where('user_id', Auth::id())->where('phone', '+' . $peer)->first();

        return response()->json([
            'success' => true,
            'data' => $messages,
            'contact_name' => $contact?->name,
        ]);
    }

    /**
     * Rename / label the peer as a Wavixa contact.
     */
    public function setName(Request $request): JsonResponse
    {
        $data = $request->validate([
            'peer' => 'required|string',
            'name' => 'required|string|max:100',
        ]);

        $contact = Contact::updateOrCreate(
            ['user_id' => Auth::id(), 'phone' => '+' . ltrim($data['peer'], '+')],
            ['name' => $data['name'], 'source' => 'manual', 'channel' => 'meta']
        );

        return response()->json(['success' => true, 'data' => $contact]);
    }

    /**
     * Send a free-form text message inside the chat.
     */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'peer' => 'required|string',
            'phone_number_id' => 'nullable|integer',
            'message' => 'required|string|max:4096',
            'reply_to' => 'nullable|integer',
        ]);

        $phone = $this->resolvePhone($data['phone_number_id'] ?? null);
        if (!$phone) {
            return response()->json(['success' => false, 'message' => 'No active phone number.'], 404);
        }

        try {
            $message = (new MetaTestMessagingService(Auth::id()))
                ->sendTextMessage($phone, $data['peer'], $data['message'], $this->resolveReplyTarget($data['reply_to'] ?? null));

            return response()->json(['success' => true, 'data' => $message]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $this->metaErrorMessage($e)], 422);
        }
    }

    /**
     * Send image / video / audio / document / voice-note in the chat.
     * kind=voice uploads webm from MediaRecorder, converts to ogg/opus via
     * ffmpeg so Meta renders it as a real voice note.
     */
    public function sendMedia(Request $request): JsonResponse
    {
        $data = $request->validate([
            'peer' => 'required|string',
            'phone_number_id' => 'nullable|integer',
            'caption' => 'nullable|string|max:1024',
            'kind' => 'required|in:image,video,audio,document,voice',
            'file' => 'required|file|max:16000',
            'reply_to' => 'nullable|integer',
        ]);

        $phone = $this->resolvePhone($data['phone_number_id'] ?? null);
        if (!$phone) {
            return response()->json(['success' => false, 'message' => 'No active phone number.'], 404);
        }

        $file = $request->file('file');
        $kind = $data['kind'];
        $path = $file->getRealPath();
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $sendType = $kind === 'voice' ? 'audio' : $kind;
        $filename = $file->getClientOriginalName();

        try {
            // Voice notes: Meta requires ogg/opus to render the waveform UI.
            if ($kind === 'voice') {
                $ogg = tempnam(sys_get_temp_dir(), 'wa_voice') . '.ogg';
                exec(sprintf(
                    'ffmpeg -y -i %s -c:a libopus -b:a 32k %s 2>/dev/null',
                    escapeshellarg($path),
                    escapeshellarg($ogg)
                ));
                if (is_file($ogg) && filesize($ogg) > 0) {
                    $path = $ogg;
                    $mime = 'audio/ogg';
                    $filename = 'voice.ogg';
                }
            }

            $messaging = new MetaTestMessagingService(Auth::id());
            $mediaId = $messaging->uploadMedia($phone, $path, $mime);
            $message = $messaging->sendMediaMessage(
                $phone,
                $data['peer'],
                $sendType,
                $mediaId,
                $data['caption'] ?? null,
                $sendType === 'document' ? $filename : null,
                null,
                $this->resolveReplyTarget($data['reply_to'] ?? null)
            );

            if (isset($ogg) && is_file($ogg)) {
                @unlink($ogg);
            }

            return response()->json(['success' => true, 'data' => $message]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $this->metaErrorMessage($e)], 422);
        }
    }

    /**
     * Proxy-download media attached to a message (incoming images/audio/etc.)
     * so the chat UI can display/play them.
     */
    public function media(int $id)
    {
        $message = MetaTestMessage::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $info = (new MetaTestMessagingService(Auth::id()))->getMediaInfo($message);
        if (!$info || empty($info['url'])) {
            abort(404);
        }

        $response = Http::withToken($info['token'])->timeout(60)->get($info['url']);
        if (!$response->successful()) {
            abort(502);
        }

        return response($response->body(), 200, [
            'Content-Type' => $info['mime_type'],
            'Content-Disposition' => 'inline; filename="' . ($info['filename'] ?: 'media') . '"',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Emoji reaction on a message (sent to Meta + aggregated locally).
     */
    public function react(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message_id' => 'required|integer',
            'emoji' => 'required|string|max:8',
        ]);

        $target = MetaTestMessage::where('id', $data['message_id'])
            ->where('user_id', Auth::id())
            ->first();
        if (!$target || !$target->phoneNumber) {
            return response()->json(['success' => false, 'message' => 'Message not found.'], 404);
        }

        try {
            $reaction = (new MetaTestMessagingService(Auth::id()))
                ->sendReaction($target->phoneNumber, $target, $data['emoji']);

            return response()->json(['success' => true, 'data' => $reaction]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $this->metaErrorMessage($e)], 422);
        }
    }

    /**
     * Forward messages to another chat. Text resends directly; media is
     * downloaded from Meta and re-uploaded under a new media id.
     */
    public function forward(Request $request): JsonResponse
    {
        $data = $request->validate([
            'peer' => 'required|string',
            'phone_number_id' => 'nullable|integer',
            'message_ids' => 'required|array|min:1|max:20',
            'message_ids.*' => 'integer',
        ]);

        $phone = $this->resolvePhone($data['phone_number_id'] ?? null);
        if (!$phone) {
            return response()->json(['success' => false, 'message' => 'No active phone number.'], 404);
        }

        $messages = MetaTestMessage::where('user_id', Auth::id())
            ->whereIn('id', $data['message_ids'])
            ->where('message_type', '!=', 'reaction')
            ->orderBy('id')
            ->get();

        $messaging = new MetaTestMessagingService(Auth::id());
        $sent = [];
        $errors = [];

        foreach ($messages as $msg) {
            try {
                $type = $msg->message_type;
                if ($type === 'text' || !$type) {
                    $sent[] = $messaging->sendTextMessage($phone, $data['peer'], $msg->body ?? '');
                } elseif (in_array($type, ['image', 'video', 'audio', 'document'])) {
                    $info = $messaging->getMediaInfo($msg);
                    if (empty($info['url'])) {
                        $errors[] = "#{$msg->id}: media unavailable";
                        continue;
                    }
                    $dl = Http::withToken($info['token'])->timeout(90)->get($info['url']);
                    if (!$dl->successful()) {
                        $errors[] = "#{$msg->id}: media download failed";
                        continue;
                    }
                    $tmp = tempnam(sys_get_temp_dir(), 'wa_fwd');
                    file_put_contents($tmp, $dl->body());
                    $mediaId = $messaging->uploadMedia($phone, $tmp, $info['mime_type']);
                    @unlink($tmp);
                    $sent[] = $messaging->sendMediaMessage(
                        $phone,
                        $data['peer'],
                        $type === 'voice' ? 'audio' : $type,
                        $mediaId,
                        $msg->body,
                        $info['filename']
                    );
                }
            } catch (\Throwable $e) {
                $errors[] = "#{$msg->id}: " . $this->metaErrorMessage($e);
            }
        }

        return response()->json([
            'success' => count($sent) > 0 || empty($errors),
            'data' => $sent,
            'errors' => $errors,
        ]);
    }

    /**
     * Local delete — Meta has no remote delete, so hidden rows stay in the
     * DB but disappear from the thread and conversation list.
     */
    public function hide(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message_ids' => 'required|array|min:1|max:100',
            'message_ids.*' => 'integer',
        ]);

        MetaTestMessage::where('user_id', Auth::id())
            ->whereIn('id', $data['message_ids'])
            ->update(['is_hidden' => true]);

        return response()->json(['success' => true]);
    }

    /**
     * Typing indicator — Meta anchors it to the customer's last message.
     */
    public function typing(Request $request): JsonResponse
    {
        $data = $request->validate([
            'peer' => 'required|string',
            'phone_number_id' => 'nullable|integer',
        ]);

        $phone = $this->resolvePhone($data['phone_number_id'] ?? null);
        if ($phone) {
            (new MetaTestMessagingService(Auth::id()))->sendTypingIndicator($phone, $data['peer']);
        }

        return response()->json(['success' => true]);
    }

    private function resolveReplyTarget(?int $id): ?MetaTestMessage
    {
        if (!$id) {
            return null;
        }

        return MetaTestMessage::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();
    }

    private function resolvePhone(?int $id): ?MetaTestPhoneNumber
    {
        if ($id) {
            $phone = MetaTestPhoneNumber::where('id', $id)
                ->where('user_id', Auth::id())->where('is_active', true)->first();
            if ($phone) {
                return $phone;
            }
        }

        return MetaTestPhoneNumber::where('user_id', Auth::id())
            ->where('is_active', true)->orderBy('id')->first();
    }

    private function metaErrorMessage(\Throwable $e): string
    {
        if (method_exists($e, 'getMetaError')) {
            $meta = $e->getMetaError() ?? [];
            return trim($meta['error_user_msg'] ?? $meta['message'] ?? $e->getMessage());
        }

        return $e->getMessage();
    }
}
