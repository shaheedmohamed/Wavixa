<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WebhookTestReceiver extends Controller
{
    public function receive(Request $request)
    {
        $payload = $request->all();
        $signature = $request->header('X-Webhook-Signature');

        $received = Cache::get('webhook_test_received', []);
        $received[] = [
            'payload' => $payload,
            'signature' => $signature,
            'received_at' => now()->toIso8601String(),
        ];

        Cache::put('webhook_test_received', array_slice($received, -50), 3600);

        return response()->json(['success' => true, 'received' => true]);
    }

    public function getReceived()
    {
        return response()->json([
            'success' => true,
            'data' => Cache::get('webhook_test_received', []),
        ]);
    }

    public function clear()
    {
        Cache::forget('webhook_test_received');
        return response()->json(['success' => true]);
    }
}
