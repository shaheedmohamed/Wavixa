<?php

namespace Tests\Feature\MetaTest;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaTestOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_callback_requires_code(): void
    {
        config()->set('meta.enabled', true);

        $this->get('/api/meta-test/callback?state=' . base64_encode(json_encode(['user_id' => 1])))
            ->assertRedirect('/meta/connect?status=error&message=missing_code');
    }

    public function test_oauth_callback_exchanges_code_and_redirects(): void
    {
        config()->set('meta.enabled', true);
        config()->set('meta.app_id', 'app-id');
        config()->set('meta.app_secret', 'app-secret');
        config()->set('meta.redirect_uri', 'https://wavixa.net/api/meta-test/callback');

        $graphUrl = config('meta.graph_url');

        Http::fake(function ($request) use ($graphUrl) {
            if (str_contains($request->url(), '/oauth/access_token')) {
                if (str_contains($request->url(), 'fb_exchange_token')) {
                    return Http::response(['access_token' => 'long-lived', 'expires_in' => 5184000], 200);
                }

                return Http::response(['access_token' => 'short-lived', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/me')) {
                return Http::response(['id' => 'meta-user-123', 'name' => 'Test User'], 200);
            }

            if (str_contains($request->url(), '/meta-user-123/assigned_whatsapp_business_accounts')) {
                return Http::response(['data' => [['id' => 'waba-test-123', 'name' => 'Test WABA', 'status' => 'ACTIVE']]], 200);
            }

            if (str_contains($request->url(), '/waba-test-123/phone_numbers') || str_contains($request->url(), '/waba-test-123/message_templates')) {
                return Http::response(['data' => []], 200);
            }

            return Http::response(['data' => []], 200);
        });

        $user = User::factory()->create();

        $state = base64_encode(json_encode(['user_id' => $user->id]));

        $this->get("/api/meta-test/callback?code=test-code&state={$state}")
            ->assertRedirectContains('status=connected');

        $this->assertDatabaseHas('meta_test_connections', [
            'user_id' => $user->id,
            'status' => 'connected',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        config()->set('meta.enabled', true);

        $this->getJson('/api/meta-test/dashboard')->assertUnauthorized();
    }

    public function test_authenticated_user_can_access_dashboard_when_enabled(): void
    {
        config()->set('meta.enabled', true);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/meta-test/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
