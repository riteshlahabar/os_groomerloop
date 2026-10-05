<?php

namespace Modules\Insights\Services;

use DateTimeImmutable;
use Modules\Entitlements\Contracts\Entitlements;
use Modules\Entitlements\Domain\Feature;

/**
 * Assembles the `/admin/reports` response: every registered metric, graded against the
 * tenant's own `business_insights` entitlement before it is ever computed (invariant #3 — the
 * grade check lives here, once, rather than inside each metric class).
 */
final class DashboardReportBuilder
{
    public function __construct(
        private readonly MetricRegistry $registry,
        private readonly Entitlements $entitlements,
    ) {}

    /**
     * @return list<array{key: string, label: string, status: string, data: array<string, mixed>}>
     */
    public function build(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rows = [];

        foreach ($this->registry->all() as $metric) {
            if (! $this->entitlements->atLeast(Feature::BusinessInsights, $metric->minimumGrade())) {
                $rows[] = [
                    'key' => $metric->key(),
                    'label' => $metric->label(),
                    'status' => 'below_grade',
                    'data' => [],
                ];

                continue;
            }

            $result = $metric->compute($from, $to);

            $rows[] = [
                'key' => $metric->key(),
                'label' => $metric->label(),
                'status' => $result->status,
                'data' => $result->data,
            ];
        }

        return $rows;
    }
}
