<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private const BASE_URL = 'https://api.telegram.org/bot';

    /**
     * Call Telegram Bot API
     */
    private function call(string $botToken, string $method, array $params = [], int $timeout = 20): array
    {
        $url = self::BASE_URL . $botToken . '/' . $method;

        try {
            $response = Http::timeout($timeout)->post($url, $params);
            $data = $response->json();

            if ($response->successful() && ($data['ok'] ?? false)) {
                return [
                    'success' => true,
                    'result' => $data['result'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $data['description'] ?? 'Telegram API error (' . $response->status() . ')',
                'error_code' => $data['error_code'] ?? $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error('Telegram API Exception: ' . $e->getMessage(), [
                'method' => $method,
                'params' => $params,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Bot details
     */
    public function getMe(string $botToken): array
    {
        return $this->call($botToken, 'getMe');
    }

    /**
     * Set Webhook
     */
    public function setWebhook(string $botToken, string $webhookUrl): array
    {
        return $this->call($botToken, 'setWebhook', [
            'url' => $webhookUrl,
            'allowed_updates' => json_encode(['message', 'callback_query']),
        ]);
    }

    /**
     * Delete Webhook
     */
    public function deleteWebhook(string $botToken): array
    {
        return $this->call($botToken, 'deleteWebhook', [
            'drop_pending_updates' => false,
        ]);
    }

    /**
     * Get Webhook Info
     */
    public function getWebhookInfo(string $botToken): array
    {
        return $this->call($botToken, 'getWebhookInfo');
    }

    /**
     * Send Text Message
     */
    public function sendMessage(string $botToken, string $chatId, string $text, array $options = []): array
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $options['parse_mode'] ?? 'HTML',
            'disable_web_page_preview' => $options['disable_web_page_preview'] ?? false,
        ];

        if (!empty($options['buttons']) && is_array($options['buttons'])) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $options['buttons'],
            ]);
        } elseif (!empty($options['button_text']) && !empty($options['button_url'])) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $options['button_text'], 'url' => $options['button_url']]
                    ]
                ]
            ]);
        }

        return $this->call($botToken, 'sendMessage', $params);
    }

    /**
     * Send Photo
     */
    public function sendPhoto(string $botToken, string $chatId, string $photo, string $caption = '', array $options = []): array
    {
        $params = [
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => $options['parse_mode'] ?? 'HTML',
        ];

        if (!empty($options['button_text']) && !empty($options['button_url'])) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $options['button_text'], 'url' => $options['button_url']]
                    ]
                ]
            ]);
        }

        return $this->call($botToken, 'sendPhoto', $params);
    }

    /**
     * Send Document
     */
    public function sendDocument(string $botToken, string $chatId, string $document, string $caption = '', array $options = []): array
    {
        $params = [
            'chat_id' => $chatId,
            'document' => $document,
            'caption' => $caption,
            'parse_mode' => $options['parse_mode'] ?? 'HTML',
        ];

        if (!empty($options['button_text']) && !empty($options['button_url'])) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $options['button_text'], 'url' => $options['button_url']]
                    ]
                ]
            ]);
        }

        return $this->call($botToken, 'sendDocument', $params);
    }

    /**
     * Send Chat Action (e.g. typing)
     */
    public function sendChatAction(string $botToken, string $chatId, string $action = 'typing'): array
    {
        return $this->call($botToken, 'sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }
}
