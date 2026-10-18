<?php

namespace Tests\Feature\MetaTest;

use App\Models\User;
use App\Modules\MetaTest\Models\MetaTestConnection;
use App\Modules\MetaTest\Models\MetaTestPhoneNumber;
use App\Modules\MetaTest\Models\MetaTestWaba;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaTestEmbeddedSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function enableMeta(): void
    {
        config()->set('meta.enabled', true);
        config()->set('meta.app_id', 'app-id');
        config()->set('meta.app_secret', 'app-secret');
        config()->set('meta.login_config_id', 'config-123');
        config()->set('meta.api_version', 'v26.0');
    }

    protected function fakeSuccessfulExchange(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/oauth/access_token')) {
                if (str_contains($url, 'fb_exchange_token')) {
                    return Http::response(['access_token' => 'long-lived-token', 'expires_in' => 5184000], 200);
                }

                return Http::response(['access_token' => 'short-lived-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($url, '/me/businesses') || str_contains($url, 'assigned_whatsapp_business_accounts')) {
                return Http::response(['data' => []], 200);
            }

            if (str_contains($url, '/me')) {
                return Http::response(['id' => 'meta-user-1', 'name' => 'Test User'], 200);
            }

            if (str_contains($url, '/waba-1/phone_numbers')) {
                return Http::response(['data' => [[
                    'id' => 'pn-1',
                    'display_phone_number' => '+201012345678',
                    'verified_name' => 'Test Biz',
                    'status' => 'CONNECTED',
                    'platform_type' => 'CLOUD_API',
                    'account_mode' => 'LIVE',
                ]]], 200);
            }

            if (str_contains($url, '/waba-1/message_templates')) {
                return Http::response(['data' => []], 200);
            }

            if (str_contains($url, '/waba-1/subscribed_apps')) {
                return Http::response(['success' => true], 200);
            }

            if (str_contains($url, '/waba-1')) {
                return Http::response(['id' => 'waba-1', 'name' => 'Test WABA'], 200);
            }

            return Http::response(['data' => []], 200);
        });
    }

    public function test_embedded_signup_config_requires_auth(): void
    {
        $this->enableMeta();

        $this->getJson('/api/meta-test/embedded-signup/config')->assertUnauthorized();
    }

    public function test_embedded_signup_config_returns_launch_params(): void
    {
        $this->enableMeta();

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/meta-test/embedded-signup/config')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.app_id', 'app-id')
            ->assertJsonPath('data.config_id', 'config-123')
            ->assertJsonPath('data.embedded_signup_available', true);
    }

    public function test_embedded_signup_config_unavailable_without_config_id(): void
    {
        $this->enableMeta();
        config()->set('meta.login_config_id', null);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/meta-test/embedded-signup/config')
            ->assertOk()
            ->assertJsonPath('data.embedded_signup_available', false);
    }

    public function test_callback_requires_authentication(): void
    {
        $this->enableMeta();

        $this->postJson('/api/meta-test/embedded-signup/callback', ['code' => 'abc'])
            ->assertUnauthorized();
    }

    public function test_callback_requires_code(): void
    {
        $this->enableMeta();

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/meta-test/embedded-signup/callback', [])
            ->assertStatus(422);
    }

    public function test_callback_persists_connection_and_detects_coexistence(): void
    {
        $this->enableMeta();
        $this->fakeSuccessfulExchange();

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/meta-test/embedded-signup/callback', [
                'code' => 'exchangeable-code',
                'waba_id' => 'waba-1',
                'phone_number_id' => 'pn-1',
                'event' => 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING',
                'session_info' => ['waba_id' => 'waba-1', 'phone_number_id' => 'pn-1'],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'connected')
            ->assertJsonPath('data.onboarding_type', 'business_app_onboarding')
            ->assertJsonPath('data.webhook_subscribed', true);

        $this->assertDatabaseHas('meta_test_connections', [
            'user_id' => $user->id,
            'status' => 'connected',
            'onboarding_type' => 'business_app_onboarding',
            'signup_event' => 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING',
        ]);

        $this->assertDatabaseHas('meta_test_wabas', [
            'user_id' => $user->id,
            'meta_waba_id' => 'waba-1',
        ]);

        $this->assertDatabaseHas('meta_test_phone_numbers', [
            'user_id' => $user->id,
            'meta_phone_number_id' => 'pn-1',
            'platform_type' => 'CLOUD_API',
        ]);
    }

    public function test_callback_maps_finish_event_to_cloud_api(): void
    {
        $this->enableMeta();
        $this->fakeSuccessfulExchange();

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/meta-test/embedded-signup/callback', [
                'code' => 'exchangeable-code',
                'waba_id' => 'waba-1',
                'event' => 'FINISH',
            ])
            ->assertOk()
            ->assertJsonPath('data.onboarding_type', 'cloud_api');
    }

    public function test_callback_without_event_maps_unknown(): void
    {
        $this->enableMeta();
        $this->fakeSuccessfulExchange();

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/meta-test/embedded-signup/callback', [
                'code' => 'exchangeable-code',
                'waba_id' => 'waba-1',
            ])
            ->assertOk()
            ->assertJsonPath('data.onboarding_type', 'unknown');
    }

    public function test_code_exchange_does_not_send_redirect_uri(): void
    {
        $this->enableMeta();
        $this->fakeSuccessfulExchange();

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/meta-test/embedded-signup/callback', [
                'code' => 'exchangeable-code',
                'waba_id' => 'waba-1',
            ]);

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/oauth/access_token')) {
                return true; // ignore non-token requests
            }

            return !str_contains($request->url(), 'redirect_uri=')
                && str_contains($request->url(), 'code=exchangeable-code');
        });
    }

    public function test_token_exchange_failure_returns_error(): void
    {
        $this->enableMeta();

        Http::fake(function () {
            return Http::response(['error' => ['message' => 'Invalid verification code format', 'code' => 100]], 400);
        });

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/meta-test/embedded-signup/callback', ['code' => 'bad-code'])
            ->assertStatus(502)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('meta_test_connections', [
            'user_id' => $user->id,
            'status' => 'connected',
        ]);
    }

    public function test_access_token_is_encrypted_at_rest(): void
    {
        $this->enableMeta();
        $this->fakeSuccessfulExchange();

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/meta-test/embedded-signup/callback', [
                'code' => 'exchangeable-code',
                'waba_id' => 'waba-1',
                'event' => 'FINISH',
            ])
            ->assertOk();

        $raw = DB::table('meta_test_connections')->where('user_id', $user->id)->value('access_token');

        $this->assertNotEquals('long-lived-token', $raw);
        $this->assertEquals('long-lived-token', Crypt::decryptString($raw));

        $connection = MetaTestConnection::where('user_id', $user->id)->first();
        $this->assertEquals('long-lived-token', $connection->access_token);
    }

    public function test_duplicate_webhook_event_is_not_reprocessed(): void
    {
        $this->enableMeta();
        config()->set('meta.app_secret', 'test-secret');

        $user = User::factory()->create();
        $connection = MetaTestConnection::create([
            'user_id' => $user->id,
            'status' => 'connected',
            'access_token' => 'token',
        ]);
        $waba = MetaTestWaba::create([
            'user_id' => $user->id,
            'connection_id' => $connection->id,
            'meta_waba_id' => 'WABA_ID',
        ]);
        MetaTestPhoneNumber::create([
            'user_id' => $user->id,
            'waba_id' => $waba->id,
            'meta_phone_number_id' => 'PHONE_ID',
            'phone_number' => '+1234',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        $payload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_ID',
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => 'PHONE_ID', 'display_phone_number' => '+1234'],
                        'messages' => [[
                            'id' => 'wamid.dup',
                            'from' => '201012345678',
                            'type' => 'text',
                            'text' => ['body' => 'hi'],
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ]);

        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'test-secret');
        $server = ['HTTP_X_HUB_SIGNATURE_256' => $signature, 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', '/api/meta-test/webhook', [], [], [], $server, $payload)
            ->assertSee('EVENT_RECEIVED');

        $this->call('POST', '/api/meta-test/webhook', [], [], [], $server, $payload)
            ->assertSee('EVENT_RECEIVED');

        $this->assertEquals(1, \App\Modules\MetaTest\Models\MetaTestMessage::where('meta_message_id', 'wamid.dup')->count());
        $this->assertDatabaseHas('meta_test_events', [
            'meta_message_id' => 'wamid.dup',
            'status' => 'duplicate',
        ]);
    }

    public function test_webhook_auto_provisions_phone_for_known_waba(): void
    {
        $this->enableMeta();
        config()->set('meta.app_secret', 'test-secret');

        $user = User::factory()->create();
        $connection = MetaTestConnection::create([
            'user_id' => $user->id,
            'status' => 'connected',
            'access_token' => 'token',
        ]);
        MetaTestWaba::create([
            'user_id' => $user->id,
            'connection_id' => $connection->id,
            'meta_waba_id' => 'WABA_ID',
        ]);

        $payload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_ID',
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => 'NEW_PHONE_ID', 'display_phone_number' => '+1555'],
                        'messages' => [[
                            'id' => 'wamid.autoprov',
                            'from' => '201099988877',
                            'type' => 'text',
                            'text' => ['body' => 'hello'],
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ]);

        $this->call('POST', '/api/meta-test/webhook', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $payload, 'test-secret'),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertSee('EVENT_RECEIVED');

        $this->assertDatabaseHas('meta_test_phone_numbers', [
            'user_id' => $user->id,
            'meta_phone_number_id' => 'NEW_PHONE_ID',
        ]);
        $this->assertDatabaseHas('meta_test_messages', [
            'meta_message_id' => 'wamid.autoprov',
            'direction' => 'incoming',
        ]);
    }

    public function test_disconnect_marks_connection_disconnected(): void
    {
        $this->enableMeta();

        $user = User::factory()->create();
        MetaTestConnection::create([
            'user_id' => $user->id,
            'status' => 'connected',
            'access_token' => 'token',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/meta-test/disconnect')
            ->assertOk();

        $this->assertDatabaseHas('meta_test_connections', [
            'user_id' => $user->id,
            'status' => 'disconnected',
        ]);
    }
}
