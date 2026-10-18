<?php

namespace App\Services;

use App\Models\VoiceSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AgoraAgentService
{
    private const AGENT_UID = 1001;
    private const REST_BASE = 'https://api.agora.io/api/conversational-ai-agent/v2/projects';

    public function __construct(
        private AgoraTokenService $tokenService,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->tokenService->isConfigured();
    }

    public function start(VoiceSession $session): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Agora is not configured.');
        }

        $appId = $this->tokenService->getAppId();
        $channel = $session->channel_name;
        $userUid = (int) $session->agora_uid;

        $agentToken = $this->tokenService->buildRtcToken($channel, self::AGENT_UID);

        $payload = $this->buildJoinPayload($session, $agentToken);

        $url = self::REST_BASE . '/' . $appId . '/join';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'agora token=' . $agentToken,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->post($url, $payload);

            if (!$response->successful()) {
                throw new \RuntimeException('Agora join failed: ' . $response->body());
            }

            $data = $response->json();
            $agentId = $data['agent_id'] ?? null;

            if (empty($agentId)) {
                throw new \RuntimeException('Agora did not return an agent_id');
            }

            $session->update([
                'agora_agent_uid' => (string) self::AGENT_UID,
                'agora_agent_id' => (string) $agentId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Agora agent start failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function stop(VoiceSession $session): void
    {
        if (!$this->isConfigured() || empty($session->agora_agent_id)) {
            return;
        }

        $appId = $this->tokenService->getAppId();
        $agentToken = $this->tokenService->buildRtcToken($session->channel_name, self::AGENT_UID);

        $url = self::REST_BASE . '/' . $appId . '/agents/' . $session->agora_agent_id . '/leave';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'agora token=' . $agentToken,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->post($url, []);

            if (!$response->successful()) {
                Log::warning('Agora agent leave failed: ' . $response->body());
            }

            $session->update(['agora_agent_id' => null]);
        } catch (\Throwable $e) {
            Log::warning('Agora agent stop failed: ' . $e->getMessage());
        }
    }

    private function buildJoinPayload(VoiceSession $session, string $agentToken): array
    {
        $appUrl = rtrim(config('app.url', 'https://wavixa.net'), '/');
        $llmUrl = $appUrl . '/api/voice/chat?channel=' . urlencode($session->channel_name);
        $apiKey = (string) config('services.agora.llm_api_key', '');

        $business = trim($session->user->autoReply?->ai_business_info ?? '');

        $systemMessage = "You are Wavixa Voice AI. ";
        $systemMessage .= "Detect the user's language automatically. If they speak Egyptian Arabic, reply naturally in Egyptian Arabic. ";
        $systemMessage .= "If they speak Modern Standard Arabic, reply in formal Arabic. If they speak English, reply in English. ";
        $systemMessage .= "Keep answers short, warm, and conversational, as in a voice call. ";

        if ($business !== '') {
            $systemMessage .= "Business context: {$business}. ";
        }

        return [
            'name' => 'wavixa-voice-' . $session->voice_session_id,
            'properties' => [
                'channel' => $session->channel_name,
                'token' => $agentToken,
                'agent_rtc_uid' => (string) self::AGENT_UID,
                'remote_rtc_uids' => [(string) $session->agora_uid],
                'enable_string_uid' => false,
                'idle_timeout' => 0,
                'asr' => [
                    'credential_mode' => 'managed',
                    'vendor' => 'deepgram',
                    'language' => 'ar-EG',
                    'params' => [
                        'url' => 'wss://api.deepgram.com/v1/listen',
                        'model' => 'nova-3',
                        'language' => 'ar-EG',
                        'keyterm' => 'Wavixa,WhatsApp,API,QR Code,Webhook,Starter,Pro,Business,OTP,Auto Reply,Campaigns,Devices,Voice AI',
                    ],
                ],
                'llm' => [
                    'url' => $llmUrl,
                    'api_key' => $apiKey,
                    'system_messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemMessage,
                        ],
                    ],
                    'params' => [
                        'model' => 'wavixa-voice-ai',
                    ],
                    'max_history' => 20,
                    'greeting_message' => 'أهلاً بيك في Wavixa! أنا مساعدك الصوتي. قول لي عايز إيه؟',
                ],
                'tts' => [
                    'credential_mode' => 'managed',
                    'vendor' => 'openai',
                    'params' => [
                        'url' => 'https://api.openai.com/v1/audio/speech',
                        'model' => 'tts-1',
                        'voice' => 'alloy',
                        'instructions' => 'Speak naturally in the same language and dialect the user is using. For Arabic, use clear Modern Standard or Egyptian colloquial pronunciation as appropriate.',
                        'speed' => 1,
                    ],
                ],
                'interruption' => [
                    'enable' => true,
                    'mode' => 'start_of_speech',
                ],
                'turn_detection' => [
                    'mode' => 'default',
                ],
            ],
        ];
    }
}
