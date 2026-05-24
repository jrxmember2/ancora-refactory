<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Services\Hub\HubDashboardService;
use App\Support\Hub\HubApiContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends HubApiController
{
    public function __construct(
        private readonly HubDashboardService $dashboardService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = HubApiContext::user($request);

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        return response()->json(
            $this->dashboardService->build($user)
        );
    }
}
