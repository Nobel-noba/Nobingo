<?php

namespace App\Http\Controllers;

use App\Domains\Health\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Provide a production liveness & readiness health check probe.
     */
    public function __invoke(HealthCheckService $healthService): JsonResponse
    {
        $report = $healthService->check();

        $statusCode = $report['status'] === HealthCheckService::STATUS_CRITICAL ? 503 : 200;

        return response()
            ->json($report, $statusCode)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache');
    }
}
