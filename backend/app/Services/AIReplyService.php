<?php

namespace App\Services;

use App\Models\WhatsAppAutoReply;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIReplyService
{
    private string $baseUrl;
    private string $apiKey;
    private string $defaultModel;
    private int $timeout;
    private int $maxRetries;

    private string $geminiBaseUrl;
    private string $geminiApiKey;
    private string $geminiDefaultModel;
    private int $geminiTimeout;
    private int $geminiMaxRetries;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');
        $this->apiKey = (string) config('services.openrouter.api_key');
        $this->openrouterDefaultModel = (string) config('services.openrouter.default_model', 'google/gemma-4-31b-it:free');
        $this->timeout = (int) config('services.openrouter.timeout', 60);
        $this->maxRetries = (int) config('services.openrouter.max_retries', 2);

        $this->geminiBaseUrl = rtrim(config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $this->geminiApiKey = (string) config('services.gemini.api_key');
        $this->geminiDefaultModel = (string) config('services.gemini.default_model', 'gemini-2.5-flash');
        $this->geminiTimeout = (int) config('services.gemini.timeout', 60);
        $this->geminiMaxRetries = (int) config('services.gemini.max_retries', 2);

        $this->defaultModel = $this->geminiApiKey !== '' ? $this->geminiDefaultModel : $this->openrouterDefaultModel;
    }

    /**
     * Generate an AI reply for a conversation and incoming message.
     *
     * @return array{reply: string, tokens_input: int|null, tokens_output: int|null, model: string, raw: mixed}
     */
    public function generate(WhatsAppAutoReply $autoReply, WhatsAppConversation $conversation, WhatsAppMessage $incomingMessage, array $contextMessages = []): array
    {
        $model = $autoReply->ai_model ?: $this->defaultModel;

        if (str_starts_with($model, 'gemini-')) {
            return $this->generateWithGemini($autoReply, $conversation, $incomingMessage, $contextMessages, $model);
        }

        return $this->generateWithOpenRouter($autoReply, $conversation, $incomingMessage, $contextMessages, $model);
    }

    /**
     * Test AI generation without sending a real message.
     */
    public function test(WhatsAppAutoReply $autoReply, string $sampleMessage, array $contextMessages = []): array
    {
        $conversation = new WhatsAppConversation([
            'user_id' => $autoReply->user_id,
            'session_id' => $autoReply->session_id,
            'contact_jid' => 'test@preview',
            'contact_phone' => '000000000',
        ]);

        $incoming = new WhatsAppMessage([
            'user_id' => $autoReply->user_id,
            'session_id' => $autoReply->session_id,
            'from' => '000000000',
            'to' => $autoReply->session?->phone_number ?? '000000000',
            'message' => $sampleMessage,
            'direction' => 'incoming',
        ]);

        return $this->generate($autoReply, $conversation, $incoming, $contextMessages);
    }

    private function generateWithOpenRouter(WhatsAppAutoReply $autoReply, WhatsAppConversation $conversation, WhatsAppMessage $incomingMessage, array $contextMessages, string $model): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenRouter API key is not configured');
        }

        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $this->buildSystemPrompt($autoReply, $incomingMessage)];

        foreach ($contextMessages as $msg) {
            $role = $msg['direction'] === 'incoming' ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $msg['message'] ?? ''];
        }

        $messages[] = ['role' => 'user', 'content' => $incomingMessage->message ?? ''];

        $lastException = null;
        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'HTTP-Referer' => config('app.url', 'https://wavixa.net'),
                        'X-Title' => 'Wavixa AI Auto Reply',
                        'Content-Type' => 'application/json',
                    ])
                    ->post("{$this->baseUrl}/chat/completions", [
                        'model' => $model,
                        'messages' => $messages,
                        'temperature' => 0.65,
                        'max_tokens' => 500,
                    ]);

                if (!$response->successful()) {
                    $body = $response->json() ?: [];
                    $error = $body['error']['message'] ?? $response->body() ?: 'OpenRouter request failed';
                    throw new \RuntimeException('OpenRouter: ' . $error);
                }

                $json = $response->json();
                $content = $json['choices'][0]['message']['content'] ?? '';

                if (empty(trim($content))) {
                    throw new \RuntimeException('OpenRouter returned an empty reply');
                }

                $content = $this->sanitize($content);

                return [
                    'reply' => $content,
                    'tokens_input' => $json['usage']['prompt_tokens'] ?? null,
                    'tokens_output' => $json['usage']['completion_tokens'] ?? null,
                    'model' => $model,
                    'raw' => $json,
                ];
            } catch (\Throwable $e) {
                $lastException = $e;
                Log::warning("OpenRouter attempt {$attempt} failed for conversation {$conversation->id}: " . $e->getMessage());
                if ($attempt < $this->maxRetries) {
                    usleep(($attempt + 1) * 500000);
                }
            }
        }

        throw $lastException ?: new \RuntimeException('OpenRouter failed after retries');
    }

    private function generateWithGemini(WhatsAppAutoReply $autoReply, WhatsAppConversation $conversation, WhatsAppMessage $incomingMessage, array $contextMessages, string $model): array
    {
        if (empty($this->geminiApiKey)) {
            throw new \RuntimeException('Gemini API key is not configured. Add GEMINI_API_KEY to your .env to use the free Gemini Flash tier.');
        }

        $contents = $this->buildGeminiContents($autoReply, $incomingMessage, $contextMessages);

        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $this->buildSystemPrompt($autoReply, $incomingMessage)],
                ],
            ],
            'contents' => $contents,
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_CIVIC_INTEGRITY', 'threshold' => 'BLOCK_NONE'],
            ],
            'generationConfig' => [
                'temperature' => 0.65,
                'maxOutputTokens' => 500,
            ],
        ];

        $lastException = null;
        for ($attempt = 0; $attempt <= $this->geminiMaxRetries; $attempt++) {
            try {
                $url = "{$this->geminiBaseUrl}/models/{$model}:generateContent?key=" . urlencode($this->geminiApiKey);

                $response = Http::timeout($this->geminiTimeout)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                    ])
                    ->post($url, $payload);

                if (!$response->successful()) {
                    $body = $response->json() ?: [];
                    $error = $body['error']['message'] ?? $response->body() ?: 'Gemini request failed';
                    throw new \RuntimeException('Gemini: ' . $error);
                }

                $json = $response->json();
                $content = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

                if (empty(trim($content))) {
                    throw new \RuntimeException('Gemini returned an empty reply (finish reason: ' . ($json['candidates'][0]['finishReason'] ?? 'unknown') . ')');
                }

                $content = $this->sanitize($content);

                return [
                    'reply' => $content,
                    'tokens_input' => $json['usageMetadata']['promptTokenCount'] ?? null,
                    'tokens_output' => $json['usageMetadata']['candidatesTokenCount'] ?? null,
                    'model' => $model,
                    'raw' => $json,
                ];
            } catch (\Throwable $e) {
                $lastException = $e;
                Log::warning("Gemini attempt {$attempt} failed for conversation {$conversation->id}: " . $e->getMessage());
                if ($attempt < $this->geminiMaxRetries) {
                    usleep(($attempt + 1) * 500000);
                }
            }
        }

        throw $lastException ?: new \RuntimeException('Gemini failed after retries');
    }

    private function buildGeminiContents(WhatsAppAutoReply $autoReply, ?WhatsAppMessage $incomingMessage, array $contextMessages = []): array
    {
        $contents = [];

        foreach ($contextMessages as $msg) {
            $role = $msg['direction'] === 'incoming' ? 'user' : 'model';
            $contents[] = [
                'role' => $role,
                'parts' => [
                    ['text' => $msg['message'] ?? ''],
                ],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => $incomingMessage->message ?? ''],
            ],
        ];

        return $contents;
    }

    private function buildSystemPrompt(WhatsAppAutoReply $autoReply, ?WhatsAppMessage $incomingMessage = null): string
    {
        $parts = [];

        $instructions = trim($autoReply->ai_system_prompt ?? '');
        if ($instructions !== '') {
            $parts[] = $instructions;
        } else {
            $parts[] = 'You are a helpful WhatsApp customer support assistant. Keep replies short, friendly and natural.';
        }

        $business = trim($autoReply->ai_business_info ?? '');
        if ($business !== '') {
            $parts[] = "Business context:\n{$business}";
        }

        $tone = $this->resolveTone($autoReply->ai_tone);

        $customerText = $incomingMessage ? ($incomingMessage->message ?? '') : '';
        $language = $this->resolveLanguage($autoReply->ai_language, $customerText);

        $parts[] = "Tone of the reply: {$tone}.";
        $parts[] = "Language to use: {$language}.";
        $parts[] = 'Rules: 1) Reply ONLY in the requested language. 2) Never mix Arabic with random English or foreign words. 3) Do not use markdown. 4) Do not include internal reasoning, placeholders, or code. 5) Keep the reply under 500 characters. 6) Be concise, warm, and natural. 7) If you cannot answer based on the business context, briefly say so in the requested language. Do not ask the user to contact support unless the business context explicitly says to do so.';

        return implode("\n\n", $parts);
    }

    private function resolveTone(?string $tone): string
    {
        return match ($tone) {
            'friendly' => 'friendly and warm',
            'professional' => 'professional and polite',
            'brief' => 'brief and to the point',
            'formal' => 'formal and respectful',
            default => 'friendly and helpful',
        };
    }

    private function resolveLanguage(?string $language, string $customerText = ''): string
    {
        if ($language === 'auto' && $customerText !== '') {
            if (preg_match('/\p{Arabic}/u', $customerText)) {
                return 'Arabic (العربية)';
            }
            if (preg_match('/[a-zA-Z]/', $customerText)) {
                return 'English';
            }
        }

        return match ($language) {
            'arabic' => 'Arabic (العربية)',
            'egyptian' => 'Egyptian Arabic (العامية المصرية)',
            'english' => 'English',
            default => 'the same language as the customer',
        };
    }

    private function sanitize(string $content): string
    {
        // Remove markdown code blocks and excessive formatting
        $content = preg_replace('/```[\s\S]*?```/', '', $content);
        $content = trim($content);

        // Limit length to keep WhatsApp safe and prevent token abuse
        if (mb_strlen($content) > 1500) {
            $content = mb_substr($content, 0, 1500) . '...';
        }

        return $content;
    }
}
