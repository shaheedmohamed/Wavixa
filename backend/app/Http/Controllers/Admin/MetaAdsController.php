<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetaAd;
use App\Models\MetaAdAccount;
use App\Models\MetaAdSet;
use App\Models\MetaCampaign;
use App\Models\MetaConnection;
use App\Models\MetaCreative;
use App\Models\MetaInsight;
use App\Models\MetaPage;
use App\Models\MetaPixel;
use App\Services\MetaMarketingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MetaAdsController extends Controller
{
    protected MetaMarketingService $metaService;

    public function __construct(MetaMarketingService $metaService)
    {
        $this->metaService = $metaService;
    }

    /**
     * Get integration status & connected assets.
     */
    public function status(): JsonResponse
    {
        try {
            $connection = MetaConnection::where('is_active', true)->latest()->first();
            $hasEnvToken = !empty(config('services.meta_marketing.access_token'));
            $isConfigured = $connection !== null || $hasEnvToken;

            $adAccounts = MetaAdAccount::withCount('campaigns')->get();
            $pages = MetaPage::all();
            $pixels = MetaPixel::all();

            return response()->json([
                'success' => true,
                'is_configured' => $isConfigured,
                'connection' => $connection ? [
                    'id' => $connection->id,
                    'name' => $connection->name,
                    'app_id' => $connection->app_id,
                    'status' => $connection->status,
                    'created_at' => $connection->created_at,
                ] : null,
                'ad_accounts' => $adAccounts,
                'pages' => $pages,
                'pixels' => $pixels,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Connect or update Meta credentials securely.
     */
    public function connect(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string|min:20',
            'app_id' => 'nullable|string',
            'name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $token = $request->input('access_token');
            $appId = $request->input('app_id');
            $name = $request->input('name', 'Meta Business Account');

            // Test token by requesting ad accounts
            $accountsData = $this->metaService->getAdAccounts($token);

            $connection = MetaConnection::updateOrCreate(
                ['user_id' => $request->user()?->id],
                [
                    'name' => $name,
                    'app_id' => $appId,
                    'access_token' => $token,
                    'is_active' => true,
                    'status' => 'connected',
                    'error_message' => null,
                ]
            );

            // Sync ad accounts
            $this->syncAssetsInternal($connection, $token);

            return response()->json([
                'success' => true,
                'message' => 'Meta Business connected successfully.',
                'accounts_found' => count($accountsData),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to connect Meta account: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Sync accounts, pages, and pixels from Meta.
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            $connection = MetaConnection::where('is_active', true)->latest()->first();
            $token = $connection?->access_token ?? config('services.meta_marketing.access_token');

            if (empty($token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Meta access token configured.',
                ], 400);
            }

            if (!$connection) {
                $connection = MetaConnection::create([
                    'user_id' => $request->user()?->id,
                    'name' => 'Default Meta Account',
                    'access_token' => $token,
                    'is_active' => true,
                    'status' => 'connected',
                ]);
            }

            $stats = $this->syncAssetsInternal($connection, $token);

            // Also sync campaigns for all active ad accounts
            $accounts = MetaAdAccount::where('meta_connection_id', $connection->id)->get();
            $syncedCampaigns = 0;
            foreach ($accounts as $account) {
                try {
                    $campSync = $this->metaService->syncAccountData($account);
                    $syncedCampaigns += $campSync['synced_campaigns'] ?? 0;
                } catch (Exception $e) {
                    Log::warning("Campaign sync failed for account {$account->meta_account_id}: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Assets synced successfully from Meta.',
                'stats' => array_merge($stats, ['synced_campaigns' => $syncedCampaigns]),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Internal helper to sync accounts, pages, and pixels.
     */
    protected function syncAssetsInternal(MetaConnection $connection, string $token): array
    {
        $accounts = $this->metaService->getAdAccounts($token);
        $pages = $this->metaService->getPages($token);

        $savedAccounts = 0;
        $savedPages = 0;
        $savedPixels = 0;

        foreach ($accounts as $acc) {
            $adAcc = MetaAdAccount::updateOrCreate(
                [
                    'meta_connection_id' => $connection->id,
                    'meta_account_id' => $acc['id'],
                ],
                [
                    'name' => $acc['name'] ?? 'Ad Account ' . $acc['account_id'],
                    'currency' => $acc['currency'] ?? 'USD',
                    'timezone_name' => $acc['timezone_name'] ?? null,
                    'account_status' => (string) ($acc['account_status'] ?? '1'),
                    'min_daily_budget' => !empty($acc['min_daily_budget']) ? ((float) $acc['min_daily_budget']) / 100 : null,
                ]
            );
            $savedAccounts++;

            // Fetch pixels for this ad account
            try {
                $pixels = $this->metaService->getPixels($acc['id'], $token);
                foreach ($pixels as $px) {
                    MetaPixel::updateOrCreate(
                        [
                            'meta_connection_id' => $connection->id,
                            'meta_pixel_id' => $px['id'],
                        ],
                        [
                            'meta_ad_account_id' => $adAcc->id,
                            'name' => $px['name'] ?? 'Pixel ' . $px['id'],
                            'is_active' => true,
                        ]
                    );
                    $savedPixels++;
                }
            } catch (Exception $e) {
                Log::warning("Pixel sync failed for {$acc['id']}: " . $e->getMessage());
            }
        }

        foreach ($pages as $p) {
            MetaPage::updateOrCreate(
                [
                    'meta_connection_id' => $connection->id,
                    'meta_page_id' => $p['id'],
                ],
                [
                    'name' => $p['name'] ?? 'Facebook Page ' . $p['id'],
                    'access_token' => $p['access_token'] ?? null,
                    'category' => $p['category'] ?? null,
                    'instagram_actor_id' => $p['instagram_business_account']['id'] ?? null,
                ]
            );
            $savedPages++;
        }

        return [
            'accounts' => $savedAccounts,
            'pages' => $savedPages,
            'pixels' => $savedPixels,
        ];
    }

    /**
     * Get aggregate overview metrics.
     */
    public function overview(Request $request): JsonResponse
    {
        try {
            $accountId = $request->query('ad_account_id');

            $campaignQuery = MetaCampaign::query();
            $insightQuery = MetaInsight::query();

            if (!empty($accountId)) {
                $campaignQuery->where('meta_ad_account_id', $accountId);
                $insightQuery->where('meta_ad_account_id', $accountId);
            }

            $totalCampaigns = $campaignQuery->count();
            $activeCampaigns = (clone $campaignQuery)->where('status', 'ACTIVE')->count();
            $pausedCampaigns = (clone $campaignQuery)->where('status', 'PAUSED')->count();

            $totalSpend = (float) $insightQuery->sum('spend');
            $totalImpressions = (int) $insightQuery->sum('impressions');
            $totalReach = (int) $insightQuery->sum('reach');
            $totalClicks = (int) $insightQuery->sum('clicks');
            $totalConversions = (int) $insightQuery->sum('conversions');
            $totalPurchaseValue = (float) $insightQuery->sum('purchase_value');

            $ctr = $totalImpressions > 0 ? ($totalClicks / $totalImpressions) * 100 : 0;
            $cpc = $totalClicks > 0 ? $totalSpend / $totalClicks : 0;
            $cpm = $totalImpressions > 0 ? ($totalSpend / $totalImpressions) * 1000 : 0;
            $cpa = $totalConversions > 0 ? $totalSpend / $totalConversions : 0;
            $roas = $totalSpend > 0 ? $totalPurchaseValue / $totalSpend : 0;

            // Daily trend for charts (last 14 days)
            $recentInsights = MetaInsight::select(
                'date',
                DB::raw('SUM(spend) as spend'),
                DB::raw('SUM(impressions) as impressions'),
                DB::raw('SUM(clicks) as clicks'),
                DB::raw('SUM(conversions) as conversions')
            )
            ->when(!empty($accountId), fn($q) => $q->where('meta_ad_account_id', $accountId))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->limit(14)
            ->get();

            return response()->json([
                'success' => true,
                'overview' => [
                    'total_spend' => round($totalSpend, 2),
                    'active_campaigns' => $activeCampaigns,
                    'paused_campaigns' => $pausedCampaigns,
                    'total_campaigns' => $totalCampaigns,
                    'total_impressions' => $totalImpressions,
                    'total_reach' => $totalReach,
                    'total_clicks' => $totalClicks,
                    'ctr' => round($ctr, 2),
                    'cpc' => round($cpc, 2),
                    'cpm' => round($cpm, 2),
                    'conversions' => $totalConversions,
                    'cpa' => round($cpa, 2),
                    'roas' => round($roas, 2),
                    'purchase_value' => round($totalPurchaseValue, 2),
                ],
                'trend' => $recentInsights,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List campaigns with combined metrics.
     */
    public function campaigns(Request $request): JsonResponse
    {
        try {
            $query = MetaCampaign::with(['adAccount', 'adSets.ads', 'insights' => function ($q) {
                $q->latest('date');
            }]);

            if ($request->filled('ad_account_id')) {
                $query->where('meta_ad_account_id', $request->input('ad_account_id'));
            }

            if ($request->filled('status')) {
                $query->where('status', strtoupper($request->input('status')));
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', "%{$search}%");
            }

            $campaigns = $query->latest()->paginate($request->input('per_page', 20));

            // Transform with computed metrics
            $campaigns->getCollection()->transform(function ($camp) {
                $latestInsight = $camp->insights->first();
                return [
                    'id' => $camp->id,
                    'meta_campaign_id' => $camp->meta_campaign_id,
                    'name' => $camp->name,
                    'objective' => $camp->objective,
                    'status' => $camp->status,
                    'daily_budget' => $camp->daily_budget,
                    'lifetime_budget' => $camp->lifetime_budget,
                    'start_time' => $camp->start_time,
                    'stop_time' => $camp->stop_time,
                    'ad_account' => $camp->adAccount ? [
                        'id' => $camp->adAccount->id,
                        'name' => $camp->adAccount->name,
                        'currency' => $camp->adAccount->currency,
                    ] : null,
                    'metrics' => [
                        'spend' => (float) ($latestInsight?->spend ?? 0),
                        'impressions' => (int) ($latestInsight?->impressions ?? 0),
                        'clicks' => (int) ($latestInsight?->clicks ?? 0),
                        'ctr' => (float) ($latestInsight?->ctr ?? 0),
                        'cpc' => (float) ($latestInsight?->cpc ?? 0),
                        'cpm' => (float) ($latestInsight?->cpm ?? 0),
                        'conversions' => (int) ($latestInsight?->conversions ?? 0),
                        'roas' => (float) ($latestInsight?->roas ?? 0),
                    ],
                    'created_at' => $camp->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'campaigns' => $campaigns,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single campaign details.
     */
    public function showCampaign(int $id): JsonResponse
    {
        try {
            $campaign = MetaCampaign::with(['adAccount', 'adSets.ads.creative', 'insights' => function ($q) {
                $q->orderBy('date', 'desc')->limit(30);
            }])->findOrFail($id);

            return response()->json([
                'success' => true,
                'campaign' => $campaign,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Multi-step Campaign creation wizard.
     * Note: Creates campaign with status PAUSED initially for safety until Review & Publish is confirmed.
     */
    public function createCampaignWizard(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // Account & Assets
            'ad_account_id' => 'required|exists:meta_ad_accounts,id',
            'page_id' => 'required|string',
            'pixel_id' => 'nullable|string',
            'conversion_event' => 'nullable|string',

            // Campaign details
            'campaign_name' => 'required|string|max:200',
            'objective' => 'required|string|in:OUTCOME_TRAFFIC,OUTCOME_SALES,OUTCOME_LEADS,OUTCOME_ENGAGEMENT,OUTCOME_AWARENESS,OUTCOME_APP_PROMOTION',
            'special_ad_categories' => 'nullable|array',

            // Budget & Schedule
            'budget_type' => 'required|string|in:daily,lifetime',
            'budget' => 'required|numeric|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',

            // Ad Set Targeting
            'adset_name' => 'nullable|string|max:200',
            'country' => 'required|string|size:2',
            'age_min' => 'required|integer|min:18|max:65',
            'age_max' => 'required|integer|min:18|max:65|gte:age_min',
            'gender' => 'required|string|in:ALL,MALE,FEMALE',
            'placements' => 'nullable|array',

            // Creative
            'creative_name' => 'nullable|string|max:200',
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:2000',
            'description' => 'nullable|string|max:500',
            'destination_url' => 'required|url|max:1000',
            'call_to_action_type' => 'required|string|in:LEARN_MORE,SHOP_NOW,SIGN_UP,CONTACT_US,APPLY_NOW,ORDER_NOW,GET_OFFER,SEND_MESSAGE',
            'image_url' => 'nullable|url',
            'image_hash' => 'nullable|string',

            // Explicit auto-publish flag (defaults to false for safety)
            'publish_immediately' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $account = MetaAdAccount::findOrFail($validated['ad_account_id']);

        DB::beginTransaction();
        try {
            // Determine initial status based on safety rule
            $initialStatus = !empty($validated['publish_immediately']) ? 'ACTIVE' : 'PAUSED';

            // 1. Create Campaign on Meta
            $campaignPayload = [
                'name' => $validated['campaign_name'],
                'objective' => $validated['objective'],
                'status' => $initialStatus,
                'special_ad_categories' => $validated['special_ad_categories'] ?? [],
            ];

            if ($validated['budget_type'] === 'daily') {
                $campaignPayload['daily_budget'] = $validated['budget'];
            } else {
                $campaignPayload['lifetime_budget'] = $validated['budget'];
            }

            if (!empty($validated['start_date'])) {
                $campaignPayload['start_time'] = date('Y-m-d\TH:i:sO', strtotime($validated['start_date']));
            }
            if (!empty($validated['end_date'])) {
                $campaignPayload['stop_time'] = date('Y-m-d\TH:i:sO', strtotime($validated['end_date']));
            }

            $metaCampRes = $this->metaService->createCampaign($account->meta_account_id, $campaignPayload);
            $metaCampId = $metaCampRes['id'] ?? throw new Exception('Meta did not return a campaign ID.');

            $localCampaign = MetaCampaign::create([
                'meta_ad_account_id' => $account->id,
                'meta_campaign_id' => $metaCampId,
                'name' => $validated['campaign_name'],
                'objective' => $validated['objective'],
                'status' => $initialStatus,
                'daily_budget' => $validated['budget_type'] === 'daily' ? $validated['budget'] : null,
                'lifetime_budget' => $validated['budget_type'] === 'lifetime' ? $validated['budget'] : null,
                'start_time' => $validated['start_date'] ?? null,
                'stop_time' => $validated['end_date'] ?? null,
                'special_ad_categories' => $validated['special_ad_categories'] ?? [],
                'raw_data' => $metaCampRes,
            ]);

            // 2. Format Targeting & Create Ad Set on Meta
            $genders = match ($validated['gender']) {
                'MALE' => [1],
                'FEMALE' => [2],
                default => [],
            };

            $targeting = [
                'countries' => [$validated['country']],
                'age_min' => (int) $validated['age_min'],
                'age_max' => (int) $validated['age_max'],
            ];
            if (!empty($genders)) {
                $targeting['genders'] = $genders;
            }
            if (!empty($validated['placements'])) {
                $targeting['publisher_platforms'] = $validated['placements'];
            }

            $adSetPayload = [
                'name' => $validated['adset_name'] ?? ($validated['campaign_name'] . ' - Ad Set'),
                'campaign_id' => $metaCampId,
                'status' => $initialStatus,
                'targeting' => $targeting,
                'optimization_goal' => $validated['objective'] === 'OUTCOME_SALES' ? 'OFFSITE_CONVERSIONS' : 'LINK_CLICKS',
                'billing_event' => 'IMPRESSIONS',
            ];

            if (!empty($validated['pixel_id'])) {
                $adSetPayload['promoted_object'] = [
                    'pixel_id' => $validated['pixel_id'],
                    'custom_event_type' => $validated['conversion_event'] ?? 'PURCHASE',
                ];
            }

            $metaAdSetRes = $this->metaService->createAdSet($account->meta_account_id, $adSetPayload);
            $metaAdSetId = $metaAdSetRes['id'] ?? throw new Exception('Meta did not return an Ad Set ID.');

            $localAdSet = MetaAdSet::create([
                'meta_campaign_id' => $localCampaign->id,
                'meta_ad_account_id' => $account->id,
                'meta_ad_set_id' => $metaAdSetId,
                'name' => $adSetPayload['name'],
                'status' => $initialStatus,
                'optimization_goal' => $adSetPayload['optimization_goal'],
                'billing_event' => $adSetPayload['billing_event'],
                'targeting' => $targeting,
                'promoted_object' => $adSetPayload['promoted_object'] ?? null,
                'raw_data' => $metaAdSetRes,
            ]);

            // 3. Create Ad Creative on Meta
            $creativePayload = [
                'name' => $validated['creative_name'] ?? ($validated['campaign_name'] . ' - Creative'),
                'page_id' => $validated['page_id'],
                'title' => $validated['title'],
                'body' => $validated['body'],
                'description' => $validated['description'] ?? '',
                'destination_url' => $validated['destination_url'],
                'call_to_action_type' => $validated['call_to_action_type'],
                'image_hash' => $validated['image_hash'] ?? null,
                'image_url' => $validated['image_url'] ?? null,
            ];

            $metaCreativeRes = $this->metaService->createCreative($account->meta_account_id, $creativePayload);
            $metaCreativeId = $metaCreativeRes['id'] ?? throw new Exception('Meta did not return a Creative ID.');

            $localCreative = MetaCreative::create([
                'meta_ad_account_id' => $account->id,
                'meta_creative_id' => $metaCreativeId,
                'name' => $creativePayload['name'],
                'page_id' => $validated['page_id'],
                'title' => $validated['title'],
                'body' => $validated['body'],
                'description' => $validated['description'] ?? null,
                'destination_url' => $validated['destination_url'],
                'call_to_action_type' => $validated['call_to_action_type'],
                'image_hash' => $validated['image_hash'] ?? null,
                'image_url' => $validated['image_url'] ?? null,
                'raw_data' => $metaCreativeRes,
            ]);

            // 4. Create Ad on Meta
            $adPayload = [
                'name' => $validated['campaign_name'] . ' - Ad',
                'adset_id' => $metaAdSetId,
                'creative_id' => $metaCreativeId,
                'status' => $initialStatus,
            ];

            $metaAdRes = $this->metaService->createAd($account->meta_account_id, $adPayload);
            $metaAdId = $metaAdRes['id'] ?? throw new Exception('Meta did not return an Ad ID.');

            $localAd = MetaAd::create([
                'meta_ad_set_id' => $localAdSet->id,
                'meta_creative_id' => $localCreative->id,
                'meta_ad_account_id' => $account->id,
                'meta_ad_id' => $metaAdId,
                'name' => $adPayload['name'],
                'status' => $initialStatus,
                'raw_data' => $metaAdRes,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $initialStatus === 'ACTIVE'
                    ? 'Campaign created and published successfully!'
                    : 'Campaign created successfully in PAUSED mode. You can review and publish it whenever you are ready.',
                'campaign' => [
                    'id' => $localCampaign->id,
                    'meta_campaign_id' => $metaCampId,
                    'status' => $initialStatus,
                    'meta_ad_set_id' => $metaAdSetId,
                    'meta_creative_id' => $metaCreativeId,
                    'meta_ad_id' => $metaAdId,
                ],
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Meta Campaign Wizard Failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create campaign on Meta: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Explicit Publish confirmation action.
     */
    public function publish(int $id): JsonResponse
    {
        try {
            $campaign = MetaCampaign::with(['adSets.ads'])->findOrFail($id);

            // Activate on Meta
            $this->metaService->activateCampaign($campaign->meta_campaign_id);

            // Activate Ad Sets & Ads on Meta as well
            foreach ($campaign->adSets as $adSet) {
                try {
                    $this->metaService->updateAdSet($adSet->meta_ad_set_id, ['status' => 'ACTIVE']);
                    $adSet->update(['status' => 'ACTIVE']);
                } catch (Exception $e) {
                    Log::warning("Could not activate ad set {$adSet->meta_ad_set_id}: " . $e->getMessage());
                }

                foreach ($adSet->ads as $ad) {
                    try {
                        $this->metaService->updateAd($ad->meta_ad_id, ['status' => 'ACTIVE']);
                        $ad->update(['status' => 'ACTIVE']);
                    } catch (Exception $e) {
                        Log::warning("Could not activate ad {$ad->meta_ad_id}: " . $e->getMessage());
                    }
                }
            }

            $campaign->update(['status' => 'ACTIVE']);

            return response()->json([
                'success' => true,
                'message' => 'Campaign and associated ads have been published and activated on Meta!',
                'status' => 'ACTIVE',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Publish failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Pause a Campaign.
     */
    public function pause(int $id): JsonResponse
    {
        try {
            $campaign = MetaCampaign::findOrFail($id);
            $this->metaService->pauseCampaign($campaign->meta_campaign_id);
            $campaign->update(['status' => 'PAUSED']);

            return response()->json([
                'success' => true,
                'message' => 'Campaign paused successfully.',
                'status' => 'PAUSED',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pause failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Activate a Campaign.
     */
    public function activate(int $id): JsonResponse
    {
        return $this->publish($id);
    }

    /**
     * Update Campaign Budget.
     */
    public function updateBudget(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'budget_type' => 'required|in:daily,lifetime',
            'budget' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $campaign = MetaCampaign::findOrFail($id);
            $budgetType = $request->input('budget_type');
            $budget = (float) $request->input('budget');

            $updateData = [];
            if ($budgetType === 'daily') {
                $updateData['daily_budget'] = $budget;
            } else {
                $updateData['lifetime_budget'] = $budget;
            }

            $this->metaService->updateCampaign($campaign->meta_campaign_id, $updateData);

            $campaign->update([
                'daily_budget' => $budgetType === 'daily' ? $budget : null,
                'lifetime_budget' => $budgetType === 'lifetime' ? $budget : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Campaign budget updated successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update budget: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete Campaign.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $campaign = MetaCampaign::findOrFail($id);

            // Try to delete from Meta
            try {
                $this->metaService->deleteCampaign($campaign->meta_campaign_id);
            } catch (Exception $e) {
                Log::warning("Meta campaign delete API failed, proceeding with local delete: " . $e->getMessage());
            }

            $campaign->delete();

            return response()->json([
                'success' => true,
                'message' => 'Campaign deleted successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delete failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get live Insights for a Campaign.
     */
    public function insights(Request $request, int $id): JsonResponse
    {
        try {
            $campaign = MetaCampaign::findOrFail($id);
            $datePreset = $request->query('date_preset', 'last_30d');

            $liveInsights = $this->metaService->getInsights($campaign->meta_campaign_id, [
                'date_preset' => $datePreset,
            ]);

            $metrics = !empty($liveInsights[0])
                ? $this->metaService->parseInsightMetrics($liveInsights[0])
                : [
                    'spend' => 0,
                    'impressions' => 0,
                    'reach' => 0,
                    'clicks' => 0,
                    'cpc' => 0,
                    'cpm' => 0,
                    'ctr' => 0,
                    'conversions' => 0,
                    'cpa' => 0,
                    'roas' => 0,
                    'purchase_value' => 0,
                ];

            return response()->json([
                'success' => true,
                'metrics' => $metrics,
                'raw_insights' => $liveInsights,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch insights: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Upload Image Media for Ad Creatives.
     */
    public function uploadMedia(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ad_account_id' => 'required|exists:meta_ad_accounts,id',
            'file' => 'required|file|mimes:jpeg,png,jpg,webp,mp4,mov|max:51200', // max 50MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $account = MetaAdAccount::findOrFail($request->input('ad_account_id'));
            $file = $request->file('file');
            $mime = $file->getMimeType();

            $tempPath = $file->getRealPath();

            if (str_starts_with($mime, 'image/')) {
                $uploadRes = $this->metaService->uploadAdImage($account->meta_account_id, $tempPath);
                $images = $uploadRes['images'] ?? [];
                $firstImage = reset($images);
                $hash = $firstImage['hash'] ?? null;
                $url = $firstImage['url'] ?? null;

                return response()->json([
                    'success' => true,
                    'type' => 'image',
                    'hash' => $hash,
                    'url' => $url,
                ]);
            } else {
                $uploadRes = $this->metaService->uploadAdVideo($account->meta_account_id, $tempPath);
                $videoId = $uploadRes['id'] ?? null;

                return response()->json([
                    'success' => true,
                    'type' => 'video',
                    'video_id' => $videoId,
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Media upload failed: ' . $e->getMessage(),
            ], 400);
        }
    }
}
