<?php

namespace App\Modules\MetaTest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MetaTest\Models\MetaTestConnection;
use App\Modules\MetaTest\Models\MetaTestTemplate;
use App\Modules\MetaTest\Models\MetaTestWaba;
use App\Modules\MetaTest\Services\MetaTestConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MetaTestTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = MetaTestTemplate::where('user_id', Auth::id())
            ->with('waba')
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $templates,
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
                'data' => MetaTestTemplate::where('user_id', Auth::id())->with('waba')->get(),
                'message' => 'Templates synced from Meta.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'waba_id' => 'required|integer|exists:meta_test_wabas,id',
            'name' => 'required|string|max:512',
            'category' => 'required|string|in:MARKETING,UTILITY,AUTHENTICATION',
            'language' => 'required|string|max:10',
            'components' => 'required|array',
        ]);

        $connection = MetaTestConnection::where('user_id', Auth::id())
            ->where('status', MetaTestConnection::STATUS_CONNECTED)
            ->first();

        if (!$connection) {
            return response()->json([
                'success' => false,
                'message' => 'No connected Meta account.',
            ], 404);
        }

        $waba = MetaTestWaba::where('id', $data['waba_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        try {
            $manager = new MetaTestConnectionManager(Auth::id());
            $template = $manager->createTemplate($connection, $waba, $data);

            return response()->json([
                'success' => true,
                'data' => $template,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $template = MetaTestTemplate::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $waba = $template->waba;
        $connection = $waba?->connection;

        if ($connection) {
            try {
                $manager = new MetaTestConnectionManager(Auth::id());
                $manager->deleteTemplate($connection, $waba, $template);
            } catch (\Throwable $e) {
                // Local delete proceeds even if Meta rejects the remote delete.
            }
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted.',
        ]);
    }
}
