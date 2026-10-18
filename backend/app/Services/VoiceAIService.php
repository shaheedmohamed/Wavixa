<?php

namespace App\Services;

use App\Models\User;
use App\Models\VoiceMessage;
use App\Models\VoiceSession;
use App\Models\WhatsAppAutoReply;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;

class VoiceAIService
{
    public function __construct(
        private AIReplyService $aiReplyService,
    ) {
    }

    /**
     * Generate an AI voice response from a user transcript.
     *
     * @return array{text: string, model: string, tokens_input: int|null, tokens_output: int|null, raw: mixed}
     */
    public function respond(VoiceSession $session, string $transcript, ?string $detectedLanguage = null): array
    {
        $user = $session->user;
        if (!$user) {
            throw new \RuntimeException('Voice session has no associated user');
        }

        $voiceSystemPrompt = $this->buildVoiceSystemPrompt($user);
        $autoReply = $this->resolveAutoReply($user, $voiceSystemPrompt);

        $conversation = $this->makeConversation($autoReply);
        $incoming = $this->makeIncomingMessage($autoReply, $transcript, $detectedLanguage);

        $contextMessages = $this->buildContextMessages($session);

        $userMessage = VoiceMessage::create([
            'voice_session_id' => $session->id,
            'user_id' => $user->id,
            'speaker' => VoiceMessage::SPEAKER_USER,
            'text' => $transcript,
            'status' => 'complete',
        ]);

        try {
            $result = $this->aiReplyService->generate($autoReply, $conversation, $incoming, $contextMessages);

            $aiMessage = VoiceMessage::create([
                'voice_session_id' => $session->id,
                'user_id' => $user->id,
                'speaker' => VoiceMessage::SPEAKER_AI,
                'text' => $result['reply'],
                'provider' => $this->detectProvider($result['model'] ?? ''),
                'provider_model' => $result['model'] ?? null,
                'tokens_input' => $result['tokens_input'] ?? null,
                'tokens_output' => $result['tokens_output'] ?? null,
                'status' => 'complete',
                'metadata' => [
                    'model' => $result['model'] ?? null,
                    'detected_language' => $detectedLanguage,
                ],
            ]);

            return [
                'text' => $result['reply'],
                'model' => $result['model'],
                'tokens_input' => $result['tokens_input'] ?? null,
                'tokens_output' => $result['tokens_output'] ?? null,
                'message' => $aiMessage,
                'raw' => $result['raw'] ?? null,
            ];
        } catch (\Throwable $e) {
            $userMessage->update(['status' => 'failed']);
            Log::error('Voice AI generation failed for session ' . $session->voice_session_id . ': ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate an AI response from an OpenAI-style message array.
     *
     * @param array<int, array{role: string, content: string}> $messages
     */
    public function respondFromMessages(User $user, array $messages): string
    {
        $voiceSystemPrompt = $this->buildVoiceSystemPrompt($user);
        $autoReply = $this->resolveAutoReply($user, $voiceSystemPrompt);

        $contextMessages = [];
        $transcript = '';

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? '';
            $content = $msg['content'] ?? '';

            if ($role === 'user') {
                $contextMessages[] = ['direction' => 'incoming', 'message' => $content];
                $transcript = $content;
            } elseif ($role === 'assistant' || $role === 'model') {
                $contextMessages[] = ['direction' => 'outgoing', 'message' => $content];
            }
        }

        // Keep only the last 10 messages for context
        $contextMessages = array_slice($contextMessages, -10);

        $conversation = $this->makeConversation($autoReply);
        $incoming = $this->makeIncomingMessage($autoReply, $transcript, null);

        $result = $this->aiReplyService->generate($autoReply, $conversation, $incoming, $contextMessages);

        return $result['reply'];
    }

    /**
     * Build voice-first AI system prompt that understands Egyptian Arabic and switches dialect automatically.
     */
    public function buildVoiceSystemPrompt(User $user): string
    {
        $parts = [];

        $parts[] = 'You are Wavixa Voice AI, a helpful, natural-sounding voice assistant for Wavixa users.';
        $parts[] = 'You are speaking with the user through a real-time voice conversation, so keep responses concise, warm, and conversational.';

        $parts[] = "Language and dialect rules:
- Detect the user's language automatically from their speech. Do not ask them to choose a dialect.
- If they speak Egyptian Arabic (عامية مصرية), reply naturally in Egyptian Arabic. Use expressions like 'أيوه', 'طبعًا', 'عندنا', 'خليني أوضحلك', 'مش', 'عايز', 'محتاج', 'يعني', 'كده', 'تمام', etc. when appropriate. Do not overuse them.
- If they speak Modern Standard Arabic (فصحى), reply in formal Arabic.
- If they speak English, reply in English.
- If they mix Arabic and English, understand the meaning and reply in the dominant language naturally.
- Do not treat slang, typos, or informal phrasing as errors. Do not correct the user.
- Do not switch to another language unless the user clearly does.
- Maintain conversation context across multiple turns. Refer back to previous topics when the user says 'طب', 'كمان', 'ده', 'دي', 'هو', 'هي', etc.
- Numbers and product names like Wavixa, WhatsApp, API, QR Code, Webhook, Starter, Pro, Business, OTP, Auto Reply, Campaigns should be understood and pronounced clearly in the chosen language.";

        $business = trim($user->autoReply?->ai_business_info ?? '');
        if ($business !== '') {
            $parts[] = "Business context:\n{$business}";
        }

        return implode("\n\n", $parts);
    }

    private function resolveAutoReply(User $user, string $voiceSystemPrompt): WhatsAppAutoReply
    {
        $existing = $user->autoReply;

        $attributes = [
            'user_id' => $user->id,
            'ai_model' => $existing?->ai_model,
            'ai_system_prompt' => $voiceSystemPrompt,
            'ai_business_info' => $existing?->ai_business_info,
            'ai_tone' => $existing?->ai_tone,
            'ai_language' => 'auto',
        ];

        $autoReply = new WhatsAppAutoReply($attributes);

        if ($existing?->session_id) {
            $autoReply->setRelation('session', $existing->session);
        }

        return $autoReply;
    }

    private function makeConversation(WhatsAppAutoReply $autoReply): WhatsAppConversation
    {
        return new WhatsAppConversation([
            'user_id' => $autoReply->user_id,
            'session_id' => $autoReply->session?->id,
            'contact_jid' => 'voice@wavixa',
            'contact_phone' => 'voice',
        ]);
    }

    private function makeIncomingMessage(WhatsAppAutoReply $autoReply, string $transcript, ?string $detectedLanguage): WhatsAppMessage
    {
        $message = new WhatsAppMessage([
            'user_id' => $autoReply->user_id,
            'session_id' => $autoReply->session?->id,
            'from' => 'voice',
            'to' => $autoReply->session?->phone_number ?? 'voice',
            'message' => $transcript,
            'direction' => 'incoming',
        ]);

        $message->forceFill(['detected_voice_language' => $detectedLanguage]);

        return $message;
    }

    private function buildContextMessages(VoiceSession $session): array
    {
        return $session->messages()
            ->whereIn('speaker', [VoiceMessage::SPEAKER_USER, VoiceMessage::SPEAKER_AI])
            ->where('status', 'complete')
            ->get()
            ->map(fn (VoiceMessage $m) => [
                'direction' => $m->speaker === VoiceMessage::SPEAKER_USER ? 'incoming' : 'outgoing',
                'message' => $m->text,
            ])
            ->toArray();
    }

    private function detectProvider(string $model): string
    {
        if (str_starts_with($model, 'gemini-')) {
            return 'gemini';
        }

        return 'openrouter';
    }
}
