<?php

namespace Modules\Insights\Domain\Metrics;

use DateTimeImmutable;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Insights\Contracts\MetricDefinition;
use Modules\Insights\Domain\MetricResult;
use Modules\Scheduling\Contracts\AppointmentMetrics;

/**
 * Spec §16 "Service popularity" — completed appointments in the range, grouped by service.
 * Counts *completed* work rather than every booking, so a service that is requested a lot but
 * frequently cancelled does not read as popular.
 */
final readonly class ServicePopularityMetric implements MetricDefinition
{
    public function __construct(
        private AppointmentMetrics $appointments,
        private ServiceCatalog $services,
    ) {}

    public function key(): string
    {
        return 'service_popularity';
    }

    public function label(): string
    {
        return 'Service popularity';
    }

    public function minimumGrade(): FeatureGrade
    {
        return FeatureGrade::Standard;
    }

    public function compute(DateTimeImmutable $from, DateTimeImmutable $to): MetricResult
    {
        $countsByService = $this->appointments->completedCountByService($from, $to);

        if ($countsByService === []) {
            return MetricResult::insufficientData();
        }

        $names = $this->services->findMany(array_keys($countsByService));

        $rows = [];

        foreach ($countsByService as $serviceId => $count) {
            $rows[] = [
                'service_id' => $serviceId,
                'service_name' => $names[$serviceId]?->name ?? 'Unknown service',
                'completed_count' => $count,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['completed_count'] <=> $a['completed_count']);

        return MetricResult::ok(['services' => $rows]);
    }
}
