<?php

namespace App\Services;

use App\Models\MetaAd;
use App\Models\MetaAdAccount;
use App\Models\MetaAdSet;
use App\Models\MetaCampaign;
use App\Models\MetaConnection;
use App\Models\MetaCreative;
use App\Models\MetaInsight;
use App\Models\MetaPage;
use App\Models\MetaPixel;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaMarketingService
{
    protected string $graphUrl;
    protected string $apiVersion;
    protected ?string $defaultAccessToken;
    protected ?string $defaultAdAccountId;
    protected ?string $defaultPageId;
    protected ?string $defaultPixelId;

    public function __construct()
    {
        $this->graphUrl = rtrim(config('services.meta_marketing.graph_url', 'https://graph.facebook.com'), '/');
        $this->apiVersion = config('services.meta_marketing.api_version', 'v21.0');
        $this->defaultAccessToken = config('services.meta_marketing.access_token');
        $this->defaultAdAccountId = config('services.meta_marketing.ad_account_id');
        $this->defaultPageId = config('services.meta_marketing.page_id');
        $this->defaultPixelId = config('services.meta_marketing.pixel_id');
    }

    /**
     * Resolve effective access token from active connection or config.
     */
    public function getAccessToken(?string $providedToken = null): string
    {
        if (!empty($providedToken)) {
            return $providedToken;
        }

        $connection = MetaConnection::where('is_active', true)->latest()->first();
        if ($connection && !empty($connection->access_token)) {
            return $connection->access_token;
        }

        if (!empty($this->defaultAccessToken)) {
            return $this->defaultAccessToken;
        }

        throw new Exception('No Meta Access Token configured. Please connect your Meta account.');
    }

    /**
     * Helper to perform GET request to Meta Graph API.
     */
    protected function get(string $endpoint, array $query = [], ?string $token = null): array
    {
        $accessToken = $this->getAccessToken($token);
        $url = "{$this->graphUrl}/{$this->apiVersion}/" . ltrim($endpoint, '/');

        $query['access_token'] = $accessToken;

        try {
            $response = Http::timeout(45)->get($url, $query);
            return $this->handleResponse($response, $endpoint);
        } catch (Exception $e) {
            $this->logError("GET {$endpoint}", $e->getMessage());
            throw $e;
        }
    }

    /**
     * Helper to perform POST request to Meta Graph API.
     */
    protected function post(string $endpoint, array $data = [], ?string $token = null): array
    {
        $accessToken = $this->getAccessToken($token);
        $url = "{$this->graphUrl}/{$this->apiVersion}/" . ltrim($endpoint, '/');

        $data['access_token'] = $accessToken;

        try {
            $response = Http::timeout(60)->asJson()->post($url, $data);
            return $this->handleResponse($response, $endpoint);
        } catch (Exception $e) {
            $this->logError("POST {$endpoint}", $e->getMessage());
            throw $e;
        }
    }

    /**
     * Helper to perform multipart POST request to Meta Graph API (for media uploads).
     */
    protected function postMultipart(string $endpoint, string $fieldName, string $filePath, array $additionalData = [], ?string $token = null): array
    {
        $accessToken = $this->getAccessToken($token);
        $url = "{$this->graphUrl}/{$this->apiVersion}/" . ltrim($endpoint, '/');

        try {
            $fileContents = file_get_contents($filePath);
            $fileName = basename($filePath);

            $req = Http::timeout(120)->attach($fieldName, $fileContents, $fileName);
            $additionalData['access_token'] = $accessToken;

            $response = $req->post($url, $additionalData);
            return $this->handleResponse($response, $endpoint);
        } catch (Exception $e) {
            $this->logError("POST MULTIPART {$endpoint}", $e->getMessage());
            throw $e;
        }
    }

    /**
     * Helper to perform DELETE request to Meta Graph API.
     */
    protected function delete(string $endpoint, array $data = [], ?string $token = null): array
    {
        $accessToken = $this->getAccessToken($token);
        $url = "{$this->graphUrl}/{$this->apiVersion}/" . ltrim($endpoint, '/');

        $data['access_token'] = $accessToken;

        try {
            $response = Http::timeout(45)->delete($url, $data);
            return $this->handleResponse($response, $endpoint);
        } catch (Exception $e) {
            $this->logError("DELETE {$endpoint}", $e->getMessage());
            throw $e;
        }
    }

    /**
     * Format and sanitize Meta API error responses.
     */
    protected function handleResponse(Response $response, string $endpoint): array
    {
        $json = $response->json();

        if ($response->successful()) {
            return $json ?? [];
        }

        $error = $json['error'] ?? [];
        $message = $error['message'] ?? 'Unknown Meta API error';
        $code = $error['code'] ?? $response->status();
        $errorUserTitle = $error['error_user_title'] ?? null;
        $errorUserMsg = $error['error_user_msg'] ?? null;
        $errorSubcode = $error['error_subcode'] ?? null;

        $detailedMsg = $errorUserMsg ?? $message;
        if ($errorUserTitle) {
            $detailedMsg = "{$errorUserTitle}: {$detailedMsg}";
        }

        // Detect specific common Meta errors
        if ($code === 190 || str_contains($message, 'Session has expired') || str_contains($message, 'Error validating access token')) {
            $detailedMsg = 'Meta Access Token is invalid or has expired. Please refresh your Meta connection.';
        } elseif ($code === 200 || $code === 10 || str_contains($message, 'Permissions error')) {
            $detailedMsg = "Missing required Meta permissions ({$message}). Please ensure ads_management and pages_read_engagement permissions are granted.";
        } elseif ($code === 100 && str_contains($message, 'rate limit')) {
            $detailedMsg = 'Meta API rate limit reached. Please wait a moment before trying again.';
        }

        $this->logError($endpoint, $detailedMsg, [
            'status' => $response->status(),
            'code' => $code,
            'subcode' => $errorSubcode,
        ]);

        throw new Exception($detailedMsg, (int) $code);
    }

    /**
     * Sanitize and log error without tokens.
     */
    protected function logError(string $endpoint, string $message, array $context = []): void
    {
        Log::error("Meta Marketing API [{$endpoint}] failed: {$message}", $context);
    }

    /**
     * Normalize Meta Ad Account ID (ensures 'act_' prefix).
     */
    public function formatAdAccountId(string $accountId): string
    {
        $accountId = trim($accountId);
        if (!str_starts_with($accountId, 'act_')) {
            return 'act_' . $accountId;
        }
        return $accountId;
    }

    /**
     * Strip 'act_' prefix if needed.
     */
    public function rawAdAccountId(string $accountId): string
    {
        return preg_replace('/^act_/', '', trim($accountId));
    }

    // -------------------------------------------------------------
    // Account, Pages & Pixels Management
    // -------------------------------------------------------------

    /**
     * Get accessible Ad Accounts for the user / token.
     */
    public function getAdAccounts(?string $token = null): array
    {
        $res = $this->get('me/adaccounts', [
            'fields' => 'id,account_id,name,currency,timezone_name,account_status,min_daily_budget,amount_spent,balance',
            'limit' => 50,
        ], $token);

        return $res['data'] ?? [];
    }

    /**
     * Get Facebook Pages managed by the user / token.
     */
    public function getPages(?string $token = null): array
    {
        $res = $this->get('me/accounts', [
            'fields' => 'id,name,access_token,category,instagram_business_account{id,username}',
            'limit' => 50,
        ], $token);

        return $res['data'] ?? [];
    }

    /**
     * Get Pixels / Datasets for an Ad Account.
     */
    public function getPixels(string $adAccountId, ?string $token = null): array
    {
        $formattedId = $this->formatAdAccountId($adAccountId);
        
        $pixels = [];
        try {
            $res = $this->get("{$formattedId}/adspixels", [
                'fields' => 'id,name,is_unavailable,creation_time,last_fired_time',
                'limit' => 50,
            ], $token);
            $pixels = $res['data'] ?? [];
        } catch (Exception $e) {
            Log::warning("Could not fetch adspixels for {$formattedId}: " . $e->getMessage());
        }

        // Also check custom conversions / datasets
        try {
            $datasetRes = $this->get("{$formattedId}/customconversions", [
                'fields' => 'id,name,event_source_type',
                'limit' => 50,
            ], $token);
            if (!empty($datasetRes['data'])) {
                foreach ($datasetRes['data'] as $item) {
                    $pixels[] = [
                        'id' => $item['id'],
                        'name' => $item['name'] . ' (Custom Conversion)',
                        'type' => 'custom_conversion',
                    ];
                }
            }
        } catch (Exception $e) {
            // Ignore if customconversions fail
        }

        return $pixels;
    }

    // -------------------------------------------------------------
    // Campaign Management
    // -------------------------------------------------------------

    /**
     * Get Campaigns for an Ad Account.
     */
    public function getCampaigns(string $adAccountId, array $params = [], ?string $token = null): array
    {
        $formattedId = $this->formatAdAccountId($adAccountId);

        $defaultParams = [
            'fields' => 'id,name,objective,status,effective_status,buying_type,daily_budget,lifetime_budget,start_time,stop_time,special_ad_categories,created_time,updated_time',
            'limit' => 50,
        ];

        $res = $this->get("{$formattedId}/campaigns", array_merge($defaultParams, $params), $token);
        return $res['data'] ?? [];
    }

    /**
     * Get a specific Campaign by Meta ID.
     */
    public function getCampaign(string $campaignId, ?string $token = null): array
    {
        return $this->get($campaignId, [
            'fields' => 'id,name,objective,status,effective_status,buying_type,daily_budget,lifetime_budget,start_time,stop_time,special_ad_categories,created_time,updated_time',
        ], $token);
    }

    /**
     * Create a new Campaign on Meta.
     */
    public function createCampaign(string $adAccountId, array $data, ?string $token = null): array
    {
        $formattedId = $this->formatAdAccountId($adAccountId);

        $payload = [
            'name' => $data['name'],
            'objective' => $data['objective'] ?? 'OUTCOME_TRAFFIC',
            'status' => $data['status'] ?? 'PAUSED',
            'special_ad_categories' => $data['special_ad_categories'] ?? [],
            'buying_type' => $data['buying_type'] ?? 'AUCTION',
        ];

        if (!empty($data['daily_budget'])) {
            // Meta takes budget in cents / minor currency units (e.g. $10 = 1000)
            $payload['daily_budget'] = (int) round(((float) $data['daily_budget']) * 100);
        } elseif (!empty($data['lifetime_budget'])) {
            $payload['lifetime_budget'] = (int) round(((float) $data['lifetime_budget']) * 100);
        }

        if (!empty($data['start_time'])) {
            $payload['start_time'] = $data['start_time'];
        }
        if (!empty($data['stop_time'])) {
            $payload['stop_time'] = $data['stop_time'];
        }

        return $this->post("{$formattedId}/campaigns", $payload, $token);
    }

    /**
     * Update an existing Campaign on Meta.
     */
    public function updateCampaign(string $campaignId, array $data, ?string $token = null): array
    {
        $payload = [];

        if (isset($data['name'])) {
            $payload['name'] = $data['name'];
        }
        if (isset($data['status'])) {
            $payload['status'] = $data['status'];
        }
        if (isset($data['daily_budget'])) {
            $payload['daily_budget'] = (int) round(((float) $data['daily_budget']) * 100);
        }
        if (isset($data['lifetime_budget'])) {
            $payload['lifetime_budget'] = (int) round(((float) $data['lifetime_budget']) * 100);
        }
        if (isset($data['stop_time'])) {
            $payload['stop_time'] = $data['stop_time'];
        }

        return $this->post($campaignId, $payload, $token);
    }

    /**
     * Pause a Campaign.
     */
    public function pauseCampaign(string $campaignId, ?string $token = null): array
    {
        return $this->updateCampaign($campaignId, ['status' => 'PAUSED'], $token);
    }

    /**
     * Activate / Publish a Campaign.
     */
    public function activateCampaign(string $campaignId, ?string $token = null): array
    {
        return $this->updateCampaign($campaignId, ['status' => 'ACTIVE'], $token);
    }

    /**
     * Delete a Campaign.
     */
    public function deleteCampaign(string $campaignId, ?string $token = null): array
    {
        return $this->delete($campaignId, [], $token);
    }

    // -------------------------------------------------------------
    // Ad Set Management
    // -------------------------------------------------------------

    /**
     * Get Ad Sets for an Ad Account or Campaign.
     */
    public function getAdSets(string $adAccountId, array $params = [], ?string $token = null): array
    {
        $formattedId = $this->formatAdAccountId($adAccountId);

        $defaultParams = [
            'fields' => 'id,name,campaign_id,status,effective_status,daily_budget,lifetime_budget,optimization_goal,billing_event,bid_amount,start_time,end_time,targeting,promoted_object,created_time,updated_time',
            'limit' => 50,
        ];

        $res = $this->get("{$formattedId}/adsets", array_merge($defaultParams, $params), $token);
        return $res['data'] ?? [];
    }

    /**
     * Create an Ad Set on Meta.
     */
    public function createAdSet(string $adAccountId, array $data, ?string $token = null): array
    {
        $formattedId = $this->formatAdAccountId($adAccountId);

        $payload = [
            'name' => $data['name'],
            'campaign_id' => $data['campaign_id'],
            'status' => $data['status'] ?? 'PAUSED',
            'optimization_goal' => $data['optimization_goal'] ?? 'LINK_CLICKS',
            'billing_event' => $data['billing_event'] ?? 'IMPRESSIONS',
            'targeting' => $this->formatTargeting($data['targeting'] ?? []),
        ];

        if (!empty($data['daily_budget'])) {
            $payload['daily_budget'] = (int) round(((float) $data['daily_budget']) * 100);
        } elseif (!empty($data['lifetime_budget'])) {
            $payload['lifetime_budget'] = (int) round(((float) $data['lifetime_budget']) * 100);
        }

        if (!empty($data['bid_amount'])) {
            $payload['bid_amount'] = (int) round(((float) $data['bid_amount']) * 100);
        }

        if (!empty($data['start_time'])) {
            $payload['start_time'] = $data['start_time'];
        }
        if (!empty($data['end_time'])) {
            $payload['end_time'] = $data['end_time'];
        }

        if (!empty($data['promoted_object'])) {
            $payload['promoted_object'] = $data['promoted_object'];
        }

        return $this->post("{$formattedId}/adsets", $payload, $token);
    }

    /**
     * Update an Ad Set on Meta.
     */
    public function updateAdSet(string $adSetId, array $data, ?string $token = null): array
    {
        $payload = [];

        if (isset($data['name'])) {
            $payload['name'] = $data['name'];
        }
        if (isset($data['status'])) {
            $payload['status'] = $data['status'];
        }
        if (isset($data['daily_budget'])) {
            $payload['daily_budget'] = (int) round(((float) $data['daily_budget']) * 100);
        }
        if (isset($data['targeting'])) {
            $payload['targeting'] = $this->formatTargeting($data['targeting']);
        }

        return $this->post($adSetId, $payload, $token);
    }

    /**
     * Format targeting payload for Meta API.
     */
    protected function formatTargeting(array $targeting): array
    {
        $formatted = [
            'geo_locations' => [
                'countries' => $targeting['countries'] ?? ['EG'],
            ],
            'age_min' => (int) ($targeting['age_min'] ?? 18),
            'age_max' => (int) ($targeting['age_max'] ?? 65),
        ];

        if (isset($targeting['genders']) && !empty($targeting['genders'])) {
            $formatted['genders'] = (array) $targeting['genders']; // 1 = Male, 2 = Female
        }

        if (isset($targeting['publisher_platforms']) && !empty($targeting['publisher_platforms'])) {
            $formatted['publisher_platforms'] = (array) $targeting['publisher_platforms'];
        }

        if (isset($targeting['facebook_positions']) && !empty($targeting['facebook_positions'])) {
            $formatted['facebook_positions'] = (array) $targeting['facebook_positions'];
        }

        if (isset($targeting['instagram_positions']) && !empty($targeting['instagram_positions'])) {
            $formatted['instagram_positions'] = (array) $targeting['instagram_positions'];
        }

        return $formatted;
    }

    // -------------------------------------------------------------
    // Media & Creative Management
    // -------------------------------------------------------------

    /**
     * Upload an image to Meta Ad Account.
     */
    public function uploadAdImage(string $adAccountId, string $filePath, ?string $token = null): array
    {
        $formattedId = $this->formatAdAccountId($adAccountId);
        return $this->postMultipart("{$formattedId}/adimages", 'filename', $filePath, [], $token);
    }

    /**
     * Upload a video to Meta Ad Account.
     */
    public function uploadAdVideo(string $adAccountId, string $filePath, ?string $token = null): array
    {
        $formattedId = $this->formatAdAccountId($adAccountId);
        return $this->postMultipart("{$formattedId}/advideos", 'source', $filePath, [], $token);
    }

    /**
     * Create an Ad Creative on Meta.
     */
    public function createCreative(string $adAccountId, array $data, ?string $token = null): array
    {
        $formattedId = $this->formatAdAccountId($adAccountId);

        $pageId = $data['page_id'] ?? $this->defaultPageId;
        if (empty($pageId)) {
            throw new Exception('Page ID is required to create an Ad Creative.');
        }

        $linkData = [
            'message' => $data['body'] ?? '',
            'link' => $data['destination_url'] ?? config('app.url'),
            'name' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'call_to_action' => [
                'type' => $data['call_to_action_type'] ?? 'LEARN_MORE',
                'value' => [
                    'link' => $data['destination_url'] ?? config('app.url'),
                ],
            ],
        ];

        if (!empty($data['image_hash'])) {
            $linkData['image_hash'] = $data['image_hash'];
        } elseif (!empty($data['image_url'])) {
            $linkData['picture'] = $data['image_url'];
        }

        $objectStorySpec = [
            'page_id' => $pageId,
            'link_data' => $linkData,
        ];

        if (!empty($data['instagram_actor_id'])) {
            $objectStorySpec['instagram_actor_id'] = $data['instagram_actor_id'];
        }

        $payload = [
            'name' => $data['name'] ?? 'Creative ' . now()->toDateTimeString(),
            'object_story_spec' => $objectStorySpec,
        ];

        return $this->post("{$formattedId}/adcreatives", $payload, $token);
    }

    // -------------------------------------------------------------
    // Ad Management
    // -------------------------------------------------------------

    /**
     * Get Ads for an Ad Account.
     */
    public function getAds(string $adAccountId, array $params = [], ?string $token = null): array
    {
        $formattedId = $this->formatAdAccountId($adAccountId);

        $defaultParams = [
            'fields' => 'id,name,adset_id,creative{id,name,title,body,image_url},status,effective_status,created_time,updated_time',
            'limit' => 50,
        ];

        $res = $this->get("{$formattedId}/ads", array_merge($defaultParams, $params), $token);
        return $res['data'] ?? [];
    }

    /**
     * Create an Ad on Meta.
     */
    public function createAd(string $adAccountId, array $data, ?string $token = null): array
    {
        $formattedId = $this->formatAdAccountId($adAccountId);

        $payload = [
            'name' => $data['name'],
            'adset_id' => $data['adset_id'],
            'creative' => ['creative_id' => $data['creative_id']],
            'status' => $data['status'] ?? 'PAUSED',
        ];

        return $this->post("{$formattedId}/ads", $payload, $token);
    }

    /**
     * Update an Ad on Meta.
     */
    public function updateAd(string $adId, array $data, ?string $token = null): array
    {
        $payload = [];
        if (isset($data['name'])) {
            $payload['name'] = $data['name'];
        }
        if (isset($data['status'])) {
            $payload['status'] = $data['status'];
        }

        return $this->post($adId, $payload, $token);
    }

    // -------------------------------------------------------------
    // Insights & Analytics
    // -------------------------------------------------------------

    /**
     * Get Insights for an Ad Account, Campaign, Ad Set, or Ad.
     */
    public function getInsights(string $objectId, array $params = [], ?string $token = null): array
    {
        $defaultParams = [
            'fields' => 'spend,impressions,reach,clicks,cpc,cpm,ctr,actions,action_values,cost_per_action_type,purchase_roas',
            'date_preset' => $params['date_preset'] ?? 'last_30d',
        ];

        $res = $this->get("{$objectId}/insights", array_merge($defaultParams, $params), $token);
        return $res['data'] ?? [];
    }

    /**
     * Calculate summary metrics from insights data.
     */
    public function parseInsightMetrics(array $rawInsight): array
    {
        $spend = (float) ($rawInsight['spend'] ?? 0);
        $impressions = (int) ($rawInsight['impressions'] ?? 0);
        $reach = (int) ($rawInsight['reach'] ?? 0);
        $clicks = (int) ($rawInsight['clicks'] ?? 0);
        $cpc = (float) ($rawInsight['cpc'] ?? ($clicks > 0 ? $spend / $clicks : 0));
        $cpm = (float) ($rawInsight['cpm'] ?? ($impressions > 0 ? ($spend / $impressions) * 1000 : 0));
        $ctr = (float) ($rawInsight['ctr'] ?? ($impressions > 0 ? ($clicks / $impressions) * 100 : 0));

        // Calculate conversions from actions
        $conversions = 0;
        $purchaseValue = 0.0;
        if (!empty($rawInsight['actions']) && is_array($rawInsight['actions'])) {
            foreach ($rawInsight['actions'] as $action) {
                if (in_array($action['action_type'] ?? '', ['purchase', 'omni_purchase', 'lead', 'complete_registration', 'contact'])) {
                    $conversions += (int) ($action['value'] ?? 0);
                }
            }
        }

        if (!empty($rawInsight['action_values']) && is_array($rawInsight['action_values'])) {
            foreach ($rawInsight['action_values'] as $actionValue) {
                if (in_array($actionValue['action_type'] ?? '', ['purchase', 'omni_purchase'])) {
                    $purchaseValue += (float) ($actionValue['value'] ?? 0);
                }
            }
        }

        $cpa = $conversions > 0 ? $spend / $conversions : 0;

        $roas = 0.0;
        if (!empty($rawInsight['purchase_roas']) && is_array($rawInsight['purchase_roas'])) {
            $roas = (float) ($rawInsight['purchase_roas'][0]['value'] ?? 0);
        } elseif ($spend > 0 && $purchaseValue > 0) {
            $roas = $purchaseValue / $spend;
        }

        return [
            'spend' => $spend,
            'impressions' => $impressions,
            'reach' => $reach,
            'clicks' => $clicks,
            'cpc' => round($cpc, 4),
            'cpm' => round($cpm, 4),
            'ctr' => round($ctr, 4),
            'conversions' => $conversions,
            'cpa' => round($cpa, 4),
            'roas' => round($roas, 4),
            'purchase_value' => round($purchaseValue, 2),
        ];
    }

    // -------------------------------------------------------------
    // Sync to Database
    // -------------------------------------------------------------

    /**
     * Synchronize remote Meta data into local database for fast dashboard queries.
     */
    public function syncAccountData(MetaAdAccount $account): array
    {
        $campaigns = $this->getCampaigns($account->meta_account_id);
        $syncedCount = 0;

        foreach ($campaigns as $campData) {
            $budget = null;
            if (!empty($campData['daily_budget'])) {
                $budget = ((float) $campData['daily_budget']) / 100;
            }

            $lifetimeBudget = null;
            if (!empty($campData['lifetime_budget'])) {
                $lifetimeBudget = ((float) $campData['lifetime_budget']) / 100;
            }

            $campaign = MetaCampaign::updateOrCreate(
                [
                    'meta_ad_account_id' => $account->id,
                    'meta_campaign_id' => $campData['id'],
                ],
                [
                    'name' => $campData['name'],
                    'objective' => $campData['objective'] ?? 'OUTCOME_TRAFFIC',
                    'status' => $campData['status'] ?? 'PAUSED',
                    'buying_type' => $campData['buying_type'] ?? 'AUCTION',
                    'daily_budget' => $budget,
                    'lifetime_budget' => $lifetimeBudget,
                    'start_time' => $campData['start_time'] ?? null,
                    'stop_time' => $campData['stop_time'] ?? null,
                    'special_ad_categories' => $campData['special_ad_categories'] ?? [],
                    'raw_data' => $campData,
                ]
            );

            // Sync insights for this campaign
            try {
                $insights = $this->getInsights($campData['id'], ['date_preset' => 'last_30d']);
                if (!empty($insights[0])) {
                    $metrics = $this->parseInsightMetrics($insights[0]);
                    MetaInsight::updateOrCreate(
                        [
                            'meta_campaign_id' => $campaign->id,
                            'date' => now()->toDateString(),
                        ],
                        array_merge($metrics, [
                            'meta_ad_account_id' => $account->id,
                            'raw_data' => $insights[0],
                        ])
                    );
                }
            } catch (Exception $e) {
                Log::warning("Could not sync insights for campaign {$campData['id']}: " . $e->getMessage());
            }

            $syncedCount++;
        }

        return ['synced_campaigns' => $syncedCount];
    }
}
