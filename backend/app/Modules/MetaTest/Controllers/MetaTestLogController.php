<?php

namespace App\Modules\MetaTest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MetaTest\Models\MetaTestApiLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MetaTestLogController extends Controller
{
    public function index(): JsonResponse
    {
        $logs = MetaTestApiLog::where(function ($q) {
            $q->where('user_id', Auth::id())->orWhereNull('user_id');
        })
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }
}
