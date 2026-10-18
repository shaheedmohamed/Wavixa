<?php

namespace App\Modules\MetaTest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MetaTest\Models\MetaTestMessage;
use App\Modules\MetaTest\Models\MetaTestPhoneNumber;
use App\Modules\MetaTest\Models\MetaTestTemplate;
use App\Modules\MetaTest\Services\MetaTestMessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MetaTestMessageController extends Controller
{
    protected MetaTestMessagingService $messaging;

    public function __construct()
    {
        $this->messaging = new MetaTestMessagingService(Auth::id());
    }

    public function index(Request $request): JsonResponse
    {
        $phoneNumberId = $request->input('phone_number_id');

        return response()->json([
            'success' => true,
            'data' => $this->messaging->getInbox(Auth::id(), $phoneNumberId ? (int) $phoneNumberId : null),
        ]);
    }

    public function conversation(Request $request, string $phone): JsonResponse
    {
        $phoneNumberId = $request->input('phone_number_id');

        return response()->json([
            'success' => true,
            'data' => $this->messaging->getConversation(Auth::id(), $phone, $phoneNumberId ? (int) $phoneNumberId : null),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_number_id' => 'required|integer|exists:meta_test_phone_numbers,id',
            'to' => 'required|string',
            'message' => 'required_without:template_id|string|nullable',
            'template_id' => 'required_without:message|integer|exists:meta_test_templates,id',
            'components' => 'sometimes|array',
        ]);

        $phone = MetaTestPhoneNumber::where('id', $data['phone_number_id'])
            ->where('user_id', Auth::id())
            ->with('waba')
            ->firstOrFail();

        try {
            if (!empty($data['template_id'])) {
                $template = MetaTestTemplate::where('id', $data['template_id'])
                    ->where('user_id', Auth::id())
                    ->firstOrFail();

                $message = $this->messaging->sendTemplateMessage(
                    $phone,
                    $data['to'],
                    $template,
                    $data['components'] ?? []
                );
            } else {
                $message = $this->messaging->sendTextMessage($phone, $data['to'], $data['message']);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => $message,
                    'meta_message_id' => $message->meta_message_id,
                    'status' => $message->status,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string',
        ]);

        $incoming = MetaTestMessage::where('id', $id)
            ->where('user_id', Auth::id())
            ->where('direction', MetaTestMessage::DIRECTION_INCOMING)
            ->with('phoneNumber')
            ->firstOrFail();

        try {
            $message = $this->messaging->replyTo($incoming, $data['message']);

            return response()->json([
                'success' => true,
                'data' => $message,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
