<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserReputationService;
use Illuminate\Http\Request;

class ReputationController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $result = UserReputationService::recalculateIfStale($user);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
