<?php

namespace App\Modules\MetaTest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MetaTest\Models\MetaTestConnection;
use App\Modules\MetaTest\Models\MetaTestPhoneNumber;
use App\Modules\MetaTest\Services\MetaTestSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MetaTestReportController extends Controller
{
    public function index(): JsonResponse
    {
        $setupService = new MetaTestSetupService();
        $setup = $setupService->checklist();

        $connection = MetaTestConnection::where('user_id', Auth::id())
            ->where('status', MetaTestConnection::STATUS_CONNECTED)
            ->first();

        $phoneCount = MetaTestPhoneNumber::where('user_id', Auth::id())->where('is_active', true)->count();

        $ready = [
            'Meta Test module created and isolated',
            'Database tables created',
            'Config loaded from environment',
            'Graph API connectivity test',
            'Webhook verification endpoint',
            'API logging with secret masking',
        ];

        $missing = [];
        $metaApproval = [];

        if (!$setup['all_configured']) {
            foreach ($setup['requires_action'] as $item) {
                $missing[] = $item['name'] . ': ' . $item['message'];
            }
        }

        if (empty($connection)) {
            $missing[] = 'No active Meta WhatsApp connection. Complete Embedded Signup.';
        } else {
            if ($connection->wabas()->count() === 0) {
                $missing[] = 'No WABA linked. Run Sync from Meta or re-connect.';
            }
            if ($phoneCount === 0) {
                $missing[] = 'No phone number linked. Add a number in Meta Business Manager.';
            }
        }

        if (!$this->hasScope($setup, 'business_management')) {
            $metaApproval[] = 'business_management scope may require Business Verification / Advanced Access.';
        }

        if (!$this->hasScope($setup, 'whatsapp_business_management')) {
            $metaApproval[] = 'whatsapp_business_management scope may require App Review or Tech Provider onboarding.';
        }

        if (!$this->hasScope($setup, 'whatsapp_business_messaging')) {
            $metaApproval[] = 'whatsapp_business_messaging scope may require App Review or Tech Provider onboarding.';
        }

        $technicalIssues = [];
        if (empty(config('meta.webhook_verify_token'))) {
            $technicalIssues[] = 'Webhook verify token not configured.';
        }
        if (empty(config('meta.app_secret'))) {
            $technicalIssues[] = 'App Secret missing — webhook signature validation disabled.';
        }
        if (empty(config('meta.redirect_uri'))) {
            $technicalIssues[] = 'OAuth redirect URI not configured.';
        }

        $nextSteps = [
            'Register this app as a Meta Tech Provider / Business Solution Provider if reselling to customers.',
            'Complete Business Verification for the Facebook Business Manager used by the app.',
            'Add the webhook URL to the Meta app dashboard and subscribe to WhatsApp messages + message statuses.',
            'Request Advanced Access for required permissions if standard access is insufficient.',
            'Test send/receive end-to-end with a real WhatsApp number.',
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'ready' => $ready,
                'missing' => $missing,
                'meta_approval_required' => $metaApproval,
                'technical_issues' => $technicalIssues,
                'next_steps' => $nextSteps,
            ],
        ]);
    }

    protected function hasScope(array $setup, string $scope): bool
    {
        $tokenItem = collect($setup['items'])->first(fn ($i) => $i['name'] === 'Access Token Valid');

        if (!$tokenItem || empty($tokenItem['details']['scopes'])) {
            return false;
        }

        return in_array($scope, $tokenItem['details']['scopes'], true);
    }
}
