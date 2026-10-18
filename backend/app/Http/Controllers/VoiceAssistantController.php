<?php

namespace App\Http\Controllers;

use App\Models\VoiceSession;
use App\Services\AgoraAgentService;
use App\Services\AgoraTokenService;
use App\Services\FraudDetectionService;
use App\Services\VoiceAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VoiceAssistantController extends Controller
{
    public function __construct(
        private AgoraAgentService $agoraAgentService,
        private AgoraTokenService $agoraTokenService,
        private VoiceAIService $voiceAIService,
    ) {
    }

    public function start(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $session = VoiceSession::create([
            'user_id' => $user->id,
            'channel_name' => 'wavixa-voice-' . Str::random(16),
            'agora_uid' => (string) random_int(100000, 999999),
            'status' => 'active',
            'started_at' => now(),
            'browser' => $request->header('User-Agent'),
            'platform' => $request->input('platform'),
            'ip_address' => $request->ip(),
        ]);

        $token = null;
        $agoraConfigured = $this->agoraTokenService->isConfigured();

        if ($agoraConfigured) {
            try {
                $token = $this->agoraTokenService->buildRtcToken(
                    $session->channel_name,
                    (int) $session->agora_uid,
                );
            } catch (\Throwable $e) {
                Log::warning('Agora token generation failed for voice session ' . $session->voice_session_id . ': ' . $e->getMessage());
            }

            if ($token) {
                try {
                    $this->agoraAgentService->start($session);
                } catch (\Throwable $e) {
                    Log::error('Agora agent start failed for session ' . $session->voice_session_id . ': ' . $e->getMessage());
                    $session->delete();

                    return $this->error('تعذر بدء وكيل Agora. يرجى التحقق من إعدادات Agora.', 503);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->voice_session_id,
                'channel_name' => $session->channel_name,
                'agora_uid' => $session->agora_uid,
                'agora_agent_uid' => $session->agora_agent_uid,
                'agora_app_id' => $this->agoraTokenService->getAppId(),
                'agora_token' => $token,
                'agora_configured' => $agoraConfigured,
                'agent_started' => !empty($session->agora_agent_id),
                'messages' => [],
            ],
        ]);
    }

    public function transcript(Request $request, string $sessionId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $session = VoiceSession::where('voice_session_id', $sessionId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$session) {
            return $this->error('Voice session not found or has ended.', 404);
        }

        $transcript = trim($request->input('transcript') ?? '');
        $language = $request->input('detected_language');

        if ($transcript === '') {
            return $this->error('Transcript is required.', 422);
        }

        try {
            $result = $this->voiceAIService->respond($session, $transcript, $language);

            return response()->json([
                'success' => true,
                'data' => [
                    'ai_text' => $result['text'],
                    'model' => $result['model'],
                    'message_id' => $result['message']?->id,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Voice AI transcript failed for session ' . $sessionId . ': ' . $e->getMessage());

            return $this->error('حدث خطأ مؤقت، حاول مرة أخرى.', 503);
        }
    }

    public function end(Request $request, string $sessionId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $session = VoiceSession::where('voice_session_id', $sessionId)
            ->where('user_id', $user->id)
            ->first();

        if (!$session) {
            return $this->error('Voice session not found.', 404);
        }

        try {
            $this->agoraAgentService->stop($session);
        } catch (\Throwable $e) {
            Log::warning('Agora agent stop failed for session ' . $sessionId . ': ' . $e->getMessage());
        }

        $session->end();

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->voice_session_id,
                'status' => $session->status,
            ],
        ]);
    }

    /**
     * OpenAI-compatible chat completions endpoint used by Agora Conversational AI Engine.
     */
    public function chat(Request $request): JsonResponse
    {
        $channel = $request->query('channel') ?? $request->input('channel');
        $apiKey = config('services.agora.llm_api_key');

        if (!$apiKey) {
            return $this->error('Unauthorized', 401);
        }

        $provided = $request->bearerToken() ?? $request->header('X-Api-Key') ?? '';
        if (!hash_equals($apiKey, $provided)) {
            FraudDetectionService::report('voice_api_key_invalid', 'high', 'Invalid or missing Agora LLM API key on voice chat endpoint', [
                'channel' => $channel,
                'key_prefix' => substr($provided, 0, 8) . '***',
            ]);
            return $this->error('Unauthorized', 401);
        }

        $session = VoiceSession::where('channel_name', $channel)
            ->where('status', 'active')
            ->first();

        if (!$session) {
            return $this->error('Session not found', 404);
        }

        $messages = $request->input('messages', []);
        if (empty($messages) || !is_array($messages)) {
            return $this->error('Messages are required', 422);
        }

        try {
            $text = $this->voiceAIService->respondFromMessages($session->user, $messages);

            return response()->json([
                'id' => 'wavixa-voice-' . $session->voice_session_id,
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'wavixa-voice-ai',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => $text,
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Voice LLM endpoint failed for channel ' . $channel . ': ' . $e->getMessage());

            return $this->error('حدث خطأ مؤقت، حاول مرة أخرى.', 503);
        }
    }

    public function history(Request $request, string $sessionId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $session = VoiceSession::where('voice_session_id', $sessionId)
            ->where('user_id', $user->id)
            ->with('messages')
            ->first();

        if (!$session) {
            return $this->error('Voice session not found.', 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->voice_session_id,
                'status' => $session->status,
                'messages' => $session->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'speaker' => $m->speaker,
                    'text' => $m->text,
                    'created_at' => $m->created_at->toIso8601String(),
                ]),
            ],
        ]);
    }

    private function error(string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
