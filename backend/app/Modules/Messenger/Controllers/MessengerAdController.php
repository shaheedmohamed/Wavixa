<?php

namespace App\Modules\Messenger\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MessengerPage;
use App\Services\MetaMarketingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MessengerAdController extends Controller
{
    protected MetaMarketingService $metaService;

    public function __construct(MetaMarketingService $metaService)
    {
        $this->metaService = $metaService;
    }

    /**
     * Test whether a given Meta access token can be used to create ads
     * for one of the user's connected Messenger pages.
     */
    public function test(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string|min:20',
            'page_id' => 'required|integer|exists:messenger_pages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $page = MessengerPage::where('id', $request->input('page_id'))
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $token = $request->input('access_token');

        try {
            // 1. Check accessible ad accounts (also validates ads_management permission)
            $adAccounts = $this->metaService->getAdAccounts($token);

            // 2. Check accessible pages and find the selected page
            $pages = $this->metaService->getPages($token);
            $pageData = null;
            foreach ($pages as $p) {
                if (($p['id'] ?? '') === $page->page_id) {
                    $pageData = $p;
                    break;
                }
            }
            $pageOk = !empty($pageData['id']);

            return response()->json([
                'success' => true,
                'data' => [
                    'ad_accounts_count' => count($adAccounts),
                    'ad_accounts' => array_slice($adAccounts, 0, 5),
                    'page_accessible' => $pageOk,
                    'page_name' => $page->page_name,
                    'page_facebook_id' => $page->page_id,
                    'ready' => count($adAccounts) > 0 && $pageOk,
                ],
            ]);
        } catch (Exception $e) {
            Log::warning('Messenger ad test failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Token test failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Create a Meta ad campaign for a connected Messenger page.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string|min:20',
            'ad_account_id' => 'required|string',
            'page_id' => 'required|integer|exists:messenger_pages,id',

            'campaign_name' => 'required|string|max:200',
            'objective' => 'required|string|in:OUTCOME_TRAFFIC,OUTCOME_SALES,OUTCOME_LEADS,OUTCOME_ENGAGEMENT,OUTCOME_AWARENESS,OUTCOME_APP_PROMOTION',
            'budget_type' => 'required|string|in:daily,lifetime',
            'budget' => 'required|numeric|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',

            'country' => 'required|string|size:2',
            'age_min' => 'required|integer|min:18|max:65',
            'age_max' => 'required|integer|min:18|max:65|gte:age_min',
            'gender' => 'required|string|in:ALL,MALE,FEMALE',

            'title' => 'required|string|max:255',
            'body' => 'required|string|max:2000',
            'destination_url' => 'required|url|max:1000',
            'call_to_action_type' => 'required|string|in:LEARN_MORE,SHOP_NOW,SIGN_UP,CONTACT_US,APPLY_NOW,ORDER_NOW,GET_OFFER,SEND_MESSAGE',
            'image_url' => 'nullable|url',

            'publish_immediately' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $page = MessengerPage::where('id', $data['page_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $token = $data['access_token'];
        $accountId = $data['ad_account_id'];
        $status = !empty($data['publish_immediately']) ? 'ACTIVE' : 'PAUSED';

        try {
            // 1. Campaign
            $campaignPayload = [
                'name' => $data['campaign_name'],
                'objective' => $data['objective'],
                'status' => $status,
                'special_ad_categories' => [],
                'buying_type' => 'AUCTION',
            ];

            if ($data['budget_type'] === 'daily') {
                $campaignPayload['daily_budget'] = $data['budget'];
            } else {
                $campaignPayload['lifetime_budget'] = $data['budget'];
            }

            if (!empty($data['start_date'])) {
                $campaignPayload['start_time'] = date('Y-m-d\T00:00:00O', strtotime($data['start_date']));
            }
            if (!empty($data['end_date'])) {
                $campaignPayload['stop_time'] = date('Y-m-d\T23:59:59O', strtotime($data['end_date']));
            }

            $campaignRes = $this->metaService->createCampaign($accountId, $campaignPayload, $token);
            $campaignId = $campaignRes['id'] ?? throw new Exception('Meta did not return a campaign ID.');

            // 2. Ad Set
            $genders = match ($data['gender']) {
                'MALE' => [1],
                'FEMALE' => [2],
                default => [],
            };

            $targeting = [
                'countries' => [strtoupper($data['country'])],
                'age_min' => (int) $data['age_min'],
                'age_max' => (int) $data['age_max'],
            ];
            if (!empty($genders)) {
                $targeting['genders'] = $genders;
            }

            $adSetPayload = [
                'name' => $data['campaign_name'] . ' - Ad Set',
                'campaign_id' => $campaignId,
                'status' => $status,
                'targeting' => $targeting,
                'optimization_goal' => $data['objective'] === 'OUTCOME_SALES' ? 'OFFSITE_CONVERSIONS' : 'LINK_CLICKS',
                'billing_event' => 'IMPRESSIONS',
            ];

            $adSetRes = $this->metaService->createAdSet($accountId, $adSetPayload, $token);
            $adSetId = $adSetRes['id'] ?? throw new Exception('Meta did not return an Ad Set ID.');

            // 3. Creative
            $creativePayload = [
                'name' => $data['campaign_name'] . ' - Creative',
                'page_id' => $page->page_id,
                'title' => $data['title'],
                'body' => $data['body'],
                'destination_url' => $data['destination_url'],
                'call_to_action_type' => $data['call_to_action_type'],
                'image_url' => $data['image_url'] ?? null,
            ];

            $creativeRes = $this->metaService->createCreative($accountId, $creativePayload, $token);
            $creativeId = $creativeRes['id'] ?? throw new Exception('Meta did not return a Creative ID.');

            // 4. Ad
            $adPayload = [
                'name' => $data['campaign_name'] . ' - Ad',
                'adset_id' => $adSetId,
                'creative_id' => $creativeId,
                'status' => $status,
            ];

            $adRes = $this->metaService->createAd($accountId, $adPayload, $token);
            $adId = $adRes['id'] ?? throw new Exception('Meta did not return an Ad ID.');

            return response()->json([
                'success' => true,
                'message' => $status === 'ACTIVE'
                    ? 'تم إنشاء ونشر الإعلان بنجاح.'
                    : 'تم إنشاء الإعلان (متوقف). يمكنك نشره لاحقاً.',
                'data' => [
                    'campaign_id' => $campaignId,
                    'adset_id' => $adSetId,
                    'creative_id' => $creativeId,
                    'ad_id' => $adId,
                    'status' => $status,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Messenger ad creation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create ad on Meta: ' . $e->getMessage(),
            ], 400);
        }
    }
}
