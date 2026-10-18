<?php

namespace Tests\Unit;

use App\Models\MetaAdAccount;
use App\Models\MetaConnection;
use App\Services\MetaMarketingService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaMarketingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MetaMarketingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.meta_marketing.access_token' => 'TEST_MOCK_ACCESS_TOKEN_12345',
            'services.meta_marketing.ad_account_id' => 'act_123456789',
            'services.meta_marketing.page_id' => '987654321',
            'services.meta_marketing.api_version' => 'v21.0',
            'services.meta_marketing.graph_url' => 'https://graph.facebook.com',
        ]);

        $this->service = new MetaMarketingService();
    }

    public function test_get_ad_accounts_successful(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/me/adaccounts*' => Http::response([
                'data' => [
                    [
                        'id' => 'act_1001',
                        'account_id' => '1001',
                        'name' => 'Main Business Account',
                        'currency' => 'USD',
                        'account_status' => 1,
                    ],
                ],
            ], 200),
        ]);

        $accounts = $this->service->getAdAccounts();

        $this->assertCount(1, $accounts);
        $this->assertEquals('act_1001', $accounts[0]['id']);
        $this->assertEquals('Main Business Account', $accounts[0]['name']);
    }

    public function test_create_campaign_successful(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/act_1001/campaigns' => Http::response([
                'id' => 'camp_998877',
            ], 200),
        ]);

        $result = $this->service->createCampaign('act_1001', [
            'name' => 'Summer Sale 2026',
            'objective' => 'OUTCOME_TRAFFIC',
            'status' => 'PAUSED',
            'daily_budget' => 25.50,
        ]);

        $this->assertEquals('camp_998877', $result['id']);
    }

    public function test_create_adset_successful(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/act_1001/adsets' => Http::response([
                'id' => 'adset_554433',
            ], 200),
        ]);

        $result = $this->service->createAdSet('act_1001', [
            'name' => 'EG 18-65 All',
            'campaign_id' => 'camp_998877',
            'status' => 'PAUSED',
            'targeting' => [
                'countries' => ['EG'],
                'age_min' => 18,
                'age_max' => 65,
            ],
        ]);

        $this->assertEquals('adset_554433', $result['id']);
    }

    public function test_create_creative_successful(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/act_1001/adcreatives' => Http::response([
                'id' => 'creative_112233',
            ], 200),
        ]);

        $result = $this->service->createCreative('act_1001', [
            'name' => 'Main Creative',
            'page_id' => '987654321',
            'title' => 'Best WhatsApp API',
            'body' => 'Boost your marketing with Wavixa',
            'destination_url' => 'https://wavixa.net',
            'call_to_action_type' => 'LEARN_MORE',
        ]);

        $this->assertEquals('creative_112233', $result['id']);
    }

    public function test_create_ad_successful(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/act_1001/ads' => Http::response([
                'id' => 'ad_776655',
            ], 200),
        ]);

        $result = $this->service->createAd('act_1001', [
            'name' => 'Main Ad',
            'adset_id' => 'adset_554433',
            'creative_id' => 'creative_112233',
            'status' => 'PAUSED',
        ]);

        $this->assertEquals('ad_776655', $result['id']);
    }

    public function test_error_handling_for_expired_token(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/*' => Http::response([
                'error' => [
                    'message' => 'Error validating access token: Session has expired',
                    'type' => 'OAuthException',
                    'code' => 190,
                ],
            ], 400),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Meta Access Token is invalid or has expired');

        $this->service->getAdAccounts();
    }

    public function test_parse_insight_metrics_calculation(): void
    {
        $rawInsight = [
            'spend' => '150.00',
            'impressions' => '10000',
            'reach' => '8500',
            'clicks' => '500',
            'actions' => [
                ['action_type' => 'purchase', 'value' => '15'],
                ['action_type' => 'link_click', 'value' => '500'],
            ],
            'action_values' => [
                ['action_type' => 'purchase', 'value' => '600.00'],
            ],
            'purchase_roas' => [
                ['action_type' => 'omni_purchase', 'value' => '4.00'],
            ],
        ];

        $parsed = $this->service->parseInsightMetrics($rawInsight);

        $this->assertEquals(150.00, $parsed['spend']);
        $this->assertEquals(10000, $parsed['impressions']);
        $this->assertEquals(500, $parsed['clicks']);
        $this->assertEquals(0.3, $parsed['cpc']); // 150 / 500
        $this->assertEquals(15.0, $parsed['cpm']); // (150 / 10000) * 1000
        $this->assertEquals(5.0, $parsed['ctr']); // (500 / 10000) * 100
        $this->assertEquals(15, $parsed['conversions']);
        $this->assertEquals(10.0, $parsed['cpa']); // 150 / 15
        $this->assertEquals(4.0, $parsed['roas']);
        $this->assertEquals(600.0, $parsed['purchase_value']);
    }
}
