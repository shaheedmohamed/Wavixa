<?php

namespace App\Modules\MetaTest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MetaTest\Models\MetaTestConnection;
use App\Modules\MetaTest\Models\MetaTestWaba;
use App\Modules\MetaTest\Services\MetaTestConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MetaTestAccountController extends Controller
{
    public function index(): JsonResponse
    {
        $wabas = MetaTestWaba::where('user_id', Auth::id())
            ->with(['phoneNumbers', 'connection'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $wabas,
        ]);
    }

    public function sync(): JsonResponse
    {
        $connection = MetaTestConnection::where('user_id', Auth::id())
            ->where('status', MetaTestConnection::STATUS_CONNECTED)
            ->first();

        if (!$connection) {
            return response()->json([
                'success' => false,
                'message' => 'No connected Meta account.',
            ], 404);
        }

        try {
            $manager = new MetaTestConnectionManager(Auth::id());
            $connection = $manager->syncWABAsAndNumbers($connection);

            return response()->json([
                'success' => true,
                'data' => $connection->wabas()->with('phoneNumbers')->get(),
                'message' => 'Accounts synced from Meta.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
