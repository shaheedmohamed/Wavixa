<?php

namespace App\Modules\MetaTest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MetaTest\Models\MetaTestConnection;
use App\Modules\MetaTest\Models\MetaTestPhoneNumber;
use App\Modules\MetaTest\Services\MetaTestSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MetaTestStatusController extends Controller
{
    public function index(): JsonResponse
    {
        $setupService = new MetaTestSetupService();
        $setup = $setupService->checklist();

        $connection = MetaTestConnection::where('user_id', Auth::id())
            ->where('status', MetaTestConnection::STATUS_CONNECTED)
            ->first();

        $phoneCount = MetaTestPhoneNumber::where('user_id', Auth::id())->where('is_active', true)->count();

        $checks = [
            ['name' => 'Meta App', 'ok' => !empty(config('meta.app_id'))],
            ['name' => 'Graph API', 'ok' => $this->isOk($setup, 'Graph API Reachable')],
            ['name' => 'Access Token', 'ok' => $this->isOk($setup, 'Access Token Valid')],
            ['name' => 'WhatsApp API', 'ok' => $this->isOk($setup, 'WhatsApp API Accessible')],
            ['name' => 'Embedded Signup', 'ok' => !empty(config('meta.redirect_uri')) && !empty(config('meta.app_id'))],
            ['name' => 'Webhook', 'ok' => !empty(config('meta.webhook_verify_token'))],
            ['name' => 'WABA', 'ok' => $connection !== null && $connection->wabas()->count() > 0],
            ['name' => 'Phone Number', 'ok' => $phoneCount > 0],
            ['name' => 'Send Message', 'ok' => $connection !== null && $phoneCount > 0],
            ['name' => 'Receive Message', 'ok' => !empty(config('meta.webhook_verify_token')) && !empty(config('meta.app_secret'))],
            ['name' => 'Templates', 'ok' => $this->isOk($setup, 'WhatsApp API Accessible')],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'all_ok' => collect($checks)->every('ok'),
                'checks' => $checks,
            ],
        ]);
    }

    protected function isOk(array $setup, string $name): bool
    {
        $item = collect($setup['items'])->first(fn ($i) => $i['name'] === $name);

        return $item && in_array($item['status'], ['ok', 'valid', 'configured'], true);
    }
}
