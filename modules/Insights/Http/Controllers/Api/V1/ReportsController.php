<?php

namespace Modules\Insights\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Insights\Http\Requests\ReportDateRangeRequest;
use Modules\Insights\Services\DashboardReportBuilder;

/**
 * The spec §16 dashboard, read-only. One endpoint for the whole page: every metric a metric
 * class exists for comes back in one response, each carrying its own status, so the screen
 * renders every row from the same shape rather than one fetch per card.
 */
final class ReportsController
{
    public function __invoke(ReportDateRangeRequest $request, DashboardReportBuilder $builder): JsonResponse
    {
        [$from, $to] = $request->range();

        return response()->json([
            'data' => $builder->build($from, $to),
            'meta' => [
                'from' => $from->format('Y-m-d'),
                // The exclusive upper bound stated back as the inclusive date the viewer picked.
                'to' => $to->modify('-1 day')->format('Y-m-d'),
            ],
        ]);
    }
}
