<?php

namespace App\Modules\WhatsApp\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * WhatsApp Service Client
 * Handles communication with the Node.js WhatsApp service
 */
class WhatsAppServiceClient
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.whatsapp.url', env('WHATSAPP_SERVICE_URL', 'http://localhost:3000'));
        $this->apiKey = config('services.whatsapp.api_key', env('WHATSAPP_SERVICE_API_KEY'));
    }

    /**
     * Check if WhatsApp service is healthy
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/health");
            return $response->successful();
        } catch (Exception $e) {
            Log::error('WhatsApp Service Health Check Failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Create a new WhatsApp session
     */
    public function createSession(string $userId, string $phoneNumber): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/api/whatsapp/sessions", [
                    'userId' => $userId,
                    'phoneNumber' => $phoneNumber,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception("Failed to create session: {$response->status()}");
        } catch (Exception $e) {
            Log::error('WhatsApp Session Creation Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get session status
     */
    public function getSessionStatus(string $sessionId): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/api/whatsapp/sessions/{$sessionId}");

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception("Failed to get session status: {$response->status()}");
        } catch (Exception $e) {
            Log::error('WhatsApp Get Session Status Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Send a WhatsApp message
     */
    public function sendMessage(string $sessionId, string $phoneNumber, string $message): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/api/whatsapp/messages", [
                    'sessionId' => $sessionId,
                    'phoneNumber' => $phoneNumber,
                    'message' => $message,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception("Failed to send message: {$response->status()}");
        } catch (Exception $e) {
            Log::error('WhatsApp Send Message Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get message status
     */
    public function getMessageStatus(string $messageId): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/api/whatsapp/messages/{$messageId}");

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception("Failed to get message status: {$response->status()}");
        } catch (Exception $e) {
            Log::error('WhatsApp Get Message Status Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Disconnect a WhatsApp session
     */
    public function disconnectSession(string $sessionId): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->delete("{$this->baseUrl}/api/whatsapp/sessions/{$sessionId}");

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception("Failed to disconnect session: {$response->status()}");
        } catch (Exception $e) {
            Log::error('WhatsApp Disconnect Session Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get request headers
     */
    protected function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->apiKey}",
        ];
    }
}
