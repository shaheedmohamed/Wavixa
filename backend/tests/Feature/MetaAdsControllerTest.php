<?php

namespace Tests\Feature;

use App\Models\MetaAdAccount;
use App\Models\MetaCampaign;
use App\Models\MetaConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MetaAdsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@wavixa.net',
            'password' => bcrypt('password123'),
            'is_admin' => true,
            'status' => 'active',
        ]);

        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@wavixa.net',
            'password' => bcrypt('password123'),
            'is_admin' => false,
            'status' => 'active',
        ]);

        config([
            'services.meta_marketing.access_token' => 'MOCK_TEST_TOKEN_XYZ',
            'services.meta_marketing.ad_account_id' => 'act_999888777',
            'services.meta_marketing.page_id' => '12345678',
        ]);
    }

    public function test_non_admin_cannot_access_meta_ads_api(): void
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/admin/meta-ads/status');
        $response->assertStatus(403);
    }

    public function test_admin_can_view_meta_status(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/meta-ads/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_configured' => true,
            ]);
    }

    public function test_campaign_creation_wizard_creates_paused_campaign_for_safety(): void
    {
        Sanctum::actingAs($this->admin);

        $connection = MetaConnection::create([
            'user_id' => $this->admin->id,
            'name' => 'Test Connection',
            'access_token' => 'MOCK_TOKEN_VAL',
            'is_active' => true,
        ]);

        $account = MetaAdAccount::create([
            'meta_connection_id' => $connection->id,
            'meta_account_id' => 'act_999888777',
            'name' => 'My Ads Account',
            'currency' => 'USD',
        ]);

        Http::fake([
            'https://graph.facebook.com/' . config('services.meta_marketing.api_version') . '/act_999888777/campaigns' => Http::response(['id' => 'meta_camp_101'], 200),
            'https://graph.facebook.com/' . config('services.meta_marketing.api_version') . '/act_999888777/adsets' => Http::response(['id' => 'meta_adset_202'], 200),
            'https://graph.facebook.com/' . config('services.meta_marketing.api_version') . '/act_999888777/adcreatives' => Http::response(['id' => 'meta_creative_303'], 200),
            'https://graph.facebook.com/' . config('services.meta_marketing.api_version') . '/act_999888777/ads' => Http::response(['id' => 'meta_ad_404'], 200),
        ]);

        $payload = [
            'ad_account_id' => $account->id,
            'page_id' => '12345678',
            'campaign_name' => 'Super Summer Campaign',
            'objective' => 'OUTCOME_TRAFFIC',
            'budget_type' => 'daily',
            'budget' => 50,
            'country' => 'EG',
            'age_min' => 20,
            'age_max' => 55,
            'gender' => 'ALL',
            'placements' => ['facebook', 'instagram'],
            'title' => 'Special Promo 2026',
            'body' => 'Get the best WhatsApp API service now',
            'destination_url' => 'https://wavixa.net/promo',
            'call_to_action_type' => 'LEARN_MORE',
        ];

        $response = $this->postJson('/api/admin/meta-ads/campaigns', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'campaign' => [
                    'meta_campaign_id' => 'meta_camp_101',
                    'status' => 'PAUSED', // Guaranteed initial PAUSED status for safety
                ],
            ]);

        $this->assertDatabaseHas('meta_campaigns', [
            'meta_campaign_id' => 'meta_camp_101',
            'name' => 'Super Summer Campaign',
            'status' => 'PAUSED',
        ]);
    }

    public function test_explicit_publish_activates_campaign_on_meta(): void
    {
        Sanctum::actingAs($this->admin);

        $connection = MetaConnection::create([
            'user_id' => $this->admin->id,
            'name' => 'Test Connection',
            'access_token' => 'MOCK_TOKEN_VAL',
            'is_active' => true,
        ]);

        $account = MetaAdAccount::create([
            'meta_connection_id' => $connection->id,
            'meta_account_id' => 'act_999888777',
            'name' => 'My Ads Account',
            'currency' => 'USD',
        ]);

        $campaign = MetaCampaign::create([
            'meta_ad_account_id' => $account->id,
            'meta_campaign_id' => 'meta_camp_101',
            'name' => 'Super Summer Campaign',
            'objective' => 'OUTCOME_TRAFFIC',
            'status' => 'PAUSED',
            'daily_budget' => 50,
        ]);

        Http::fake([
            'https://graph.facebook.com/' . config('services.meta_marketing.api_version') . '/meta_camp_101' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson("/api/admin/meta-ads/campaigns/{$campaign->id}/publish");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'ACTIVE',
            ]);

        $this->assertDatabaseHas('meta_campaigns', [
            'id' => $campaign->id,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_pause_and_budget_update(): void
    {
        Sanctum::actingAs($this->admin);

        $connection = MetaConnection::create([
            'user_id' => $this->admin->id,
            'name' => 'Test Connection',
            'access_token' => 'MOCK_TOKEN_VAL',
            'is_active' => true,
        ]);

        $account = MetaAdAccount::create([
            'meta_connection_id' => $connection->id,
            'meta_account_id' => 'act_999888777',
            'name' => 'My Ads Account',
            'currency' => 'USD',
        ]);

        $campaign = MetaCampaign::create([
            'meta_ad_account_id' => $account->id,
            'meta_campaign_id' => 'meta_camp_101',
            'name' => 'Super Summer Campaign',
            'objective' => 'OUTCOME_TRAFFIC',
            'status' => 'ACTIVE',
            'daily_budget' => 50,
        ]);

        Http::fake([
            'https://graph.facebook.com/' . config('services.meta_marketing.api_version') . '/meta_camp_101' => Http::response(['success' => true], 200),
        ]);

        // Pause
        $pauseRes = $this->postJson("/api/admin/meta-ads/campaigns/{$campaign->id}/pause");
        $pauseRes->assertStatus(200)->assertJson(['success' => true, 'status' => 'PAUSED']);

        // Update Budget
        $budgetRes = $this->putJson("/api/admin/meta-ads/campaigns/{$campaign->id}/budget", [
            'budget_type' => 'daily',
            'budget' => 75.0,
        ]);
        $budgetRes->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('meta_campaigns', [
            'id' => $campaign->id,
            'daily_budget' => 75.0,
        ]);
    }
}
