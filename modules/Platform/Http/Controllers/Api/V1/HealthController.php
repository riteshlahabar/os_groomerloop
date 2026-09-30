<?php

namespace Modules\Platform\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Platform\Actions\CheckSystemHealth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reports whether the API and its dependencies are alive.
 *
 * A single-action controller: it resolves an Action, shapes the response, and returns.
 * No business logic, well inside the 200-line ceiling, and one controller for exactly one
 * piece of functionality.
 */
final class HealthController
{
    public function __invoke(CheckSystemHealth $check): JsonResponse
    {
        $report = $check->execute();

        return response()->json([
            'data' => [
                'status' => $report->status(),
                'checks' => $report->checkResults(),
                'time' => now()->toIso8601String(),
            ],
        ], $report->isHealthy()
            ? Response::HTTP_OK
            : Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
