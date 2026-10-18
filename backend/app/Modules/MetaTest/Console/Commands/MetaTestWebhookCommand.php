<?php

namespace App\Modules\MetaTest\Console\Commands;

use App\Modules\MetaTest\Services\MetaTestWebhookService;
use Illuminate\Console\Command;

class MetaTestWebhookCommand extends Command
{
    protected $signature = 'meta:test-webhook {--payload=} {--signature=}';
    protected $description = 'Test the Meta webhook verification and parsing logic';

    public function handle(): int
    {
        if (!config('meta.enabled')) {
            $this->error('Meta Test module is disabled.');
            return self::FAILURE;
        }

        $service = new MetaTestWebhookService();

        // Test verification challenge
        $this->info('Testing verification challenge...');
        $verifyToken = config('meta.webhook_verify_token');
        $challenge = $service->verifyChallenge('subscribe', $verifyToken, '123456789');

        if ($challenge === null) {
            $this->error('Verification challenge failed. Check META_TEST_WEBHOOK_VERIFY_TOKEN.');
            return self::FAILURE;
        }

        $this->info('Verification challenge accepted: ' . $challenge);

        // Test signature if payload provided
        $payload = $this->option('payload');
        $signature = $this->option('signature');

        if ($payload) {
            $this->info('Testing webhook signature...');
            $valid = $service->verifySignature($payload, $signature);
            $this->info($valid ? 'Signature valid' : 'Signature invalid');

            if ($valid) {
                $result = $service->handlePayload($payload, $signature);
                $this->info('Events processed: ' . count($result['events']));
            }
        }

        $this->info('Webhook URL (configure in Meta app): ' . url(config('meta.webhook_path')));

        return self::SUCCESS;
    }
}
