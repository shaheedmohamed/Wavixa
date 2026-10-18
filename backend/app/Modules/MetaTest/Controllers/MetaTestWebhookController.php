<?php

namespace App\Modules\MetaTest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MetaTest\Services\MetaTestWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MetaTestWebhookController extends Controller
{
    protected MetaTestWebhookService $service;

    public function __construct()
    {
        $this->service = new MetaTestWebhookService();
    }

    /**
     * Handle the GET verification challenge from Meta.
     */
    public function verify(Request $request): Response
    {
        // PHP's $_GET normalises dots to underscores for these Meta query keys.
        $mode = $request->query->get('hub_mode', $request->query->get('hub.mode', ''));
        $token = $request->query->get('hub_verify_token', $request->query->get('hub.verify_token', ''));
        $challenge = $request->query->get('hub_challenge', $request->query->get('hub.challenge', ''));

        $result = $this->service->verifyChallenge($mode, $token, $challenge);

        if ($result === null) {
            return response('Forbidden', 403);
        }

        return response($result, 200);
    }

    /**
     * Handle incoming POST events from Meta.
     */
    public function receive(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        try {
            $this->service->handlePayload($payload, $signature);

            return response('EVENT_RECEIVED', 200);
        } catch (\Throwable $e) {
            return response('EVENT_RECEIVED', 200);
        }
    }
}
