<?php

namespace App\Modules\MetaTest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MetaTest\Services\MetaTestSetupService;
use Illuminate\Http\JsonResponse;

class MetaTestSetupController extends Controller
{
    public function index(): JsonResponse
    {
        $service = new MetaTestSetupService();

        return response()->json([
            'success' => true,
            'data' => $service->checklist(),
        ]);
    }
}
