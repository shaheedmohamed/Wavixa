<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetaPixelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaPixelController extends Controller
{
    /**
     * Track one or more Meta/Facebook Pixel events.
     *
     * This endpoint is called by the frontend to send website events through
     * the backend via the Conversions API, alongside the browser pixel.
     */
    public function track(Request $request, MetaPixelService $service): JsonResponse
    {
        $data = $request->validate([
            'events' => 'required|array',
            'events.*.event_name' => 'required|string',
            'events.*.event_source_url' => 'nullable|string',
            'events.*.custom_data' => 'nullable|array',
            'events.*.action_source' => 'nullable|string',
        ]);

        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $events = [];

        foreach ($data['events'] as $event) {
            $events[] = [
                'event_name' => $event['event_name'],
                'event_time' => now()->timestamp,
                'event_source_url' => $event['event_source_url'] ?? $request->header('referer'),
                'action_source' => $event['action_source'] ?? 'website',
                'user_data' => [
                    'client_ip_address' => $ip,
                    'client_user_agent' => $userAgent,
                ],
                'custom_data' => $event['custom_data'] ?? [],
            ];
        }

        $result = $service->sendEvents($events);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Send a single test PageView event from the backend.
     *
     * Useful for verifying that the Conversions API token and pixel are
     * configured correctly without waiting for live frontend traffic.
     */
    public function test(Request $request, MetaPixelService $service): JsonResponse
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent() ?: 'WavixaBackend/1.0';

        $result = $service->sendEvents([
            [
                'event_name' => 'PageView',
                'event_time' => now()->timestamp,
                'event_source_url' => $request->header('referer') ?: 'https://wavixa.net/',
                'action_source' => 'website',
                'user_data' => [
                    'client_ip_address' => $ip,
                    'client_user_agent' => $userAgent,
                ],
            ],
        ]);

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
