<?php

namespace Tests\Feature\MetaTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaTestWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_verification_challenge(): void
    {
        config()->set('meta.enabled', true);
        config()->set('meta.webhook_verify_token', 'test-token');

        $this->get('/api/meta-test/webhook?hub.mode=subscribe&hub.verify_token=test-token&hub.challenge=12345')
            ->assertOk()
            ->assertSee('12345');
    }

    public function test_webhook_verification_rejects_invalid_token(): void
    {
        config()->set('meta.enabled', true);
        config()->set('meta.webhook_verify_token', 'test-token');

        $this->get('/api/meta-test/webhook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=12345')
            ->assertForbidden();
    }

    public function test_webhook_receives_message_event(): void
    {
        config()->set('meta.enabled', true);
        config()->set('meta.app_secret', 'test-secret');

        $payload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'WABA_ID',
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => [
                                    'phone_number_id' => 'PHONE_ID',
                                    'display_phone_number' => '+1234',
                                ],
                                'messages' => [
                                    [
                                        'id' => 'wamid.123',
                                        'from' => '201012345678',
                                        'type' => 'text',
                                        'text' => ['body' => 'Hello from test'],
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ]);

        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'test-secret');

        $this->withHeader('X-Hub-Signature-256', $signature)
            ->postJson('/api/meta-test/webhook', json_decode($payload, true))
            ->assertSee('EVENT_RECEIVED');

        $this->assertDatabaseHas('meta_test_events', [
            'event_type' => 'messages',
            'waba_id' => 'WABA_ID',
        ]);
    }

    public function test_webhook_does_not_break_on_invalid_signature(): void
    {
        config()->set('meta.enabled', true);
        config()->set('meta.app_secret', 'test-secret');

        $payload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'WABA_ID',
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => ['phone_number_id' => 'PHONE_ID'],
                                'messages' => [
                                    ['id' => 'wamid.123', 'from' => '201012345678', 'type' => 'text', 'text' => ['body' => 'test']],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ]);

        $this->withHeader('X-Hub-Signature-256', 'sha256=invalid')
            ->postJson('/api/meta-test/webhook', json_decode($payload, true))
            ->assertSee('EVENT_RECEIVED');
    }
}
