<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AgoraTokenService
{
    private ?string $appId;
    private ?string $appCertificate;

    public function __construct()
    {
        $this->appId = config('services.agora.app_id') ?: null;
        $this->appCertificate = config('services.agora.app_certificate') ?: null;
    }

    public function isConfigured(): bool
    {
        return !empty($this->appId) && !empty($this->appCertificate);
    }

    /**
     * Build an RTC token for a given channel and user id.
     */
    public function buildRtcToken(string $channelName, int|string $uid, int $expiresInSeconds = 3600): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Agora App ID and App Certificate are not configured.');
        }

        if (!class_exists(\Peterujah\Agora\Builders\RtcToken::class)) {
            throw new \RuntimeException('Agora token builder package is not installed. Run: composer require peterujah/php-agora-tokens');
        }

        $uid = (int) $uid;

        try {
            $client = new \Peterujah\Agora\Agora($this->appId, $this->appCertificate);
            $client->setExpiration($expiresInSeconds);

            $user = new \Peterujah\Agora\User($uid);
            $user->setChannel($channelName)
                ->setRole(\Peterujah\Agora\Roles::RTC_PUBLISHER)
                ->setPrivilegeExpire(time() + $expiresInSeconds);

            $token = \Peterujah\Agora\Builders\RtcToken::buildTokenWithUid($client, $user);

            if (empty($token)) {
                throw new \RuntimeException('Agora returned an empty token');
            }

            return $token;
        } catch (\Throwable $e) {
            Log::error('Agora token build failed: ' . $e->getMessage());
            throw new \RuntimeException('Failed to build Agora token: ' . $e->getMessage());
        }
    }

    public function getAppId(): ?string
    {
        return $this->appId;
    }
}
