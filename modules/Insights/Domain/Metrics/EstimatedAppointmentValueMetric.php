<?php

namespace Modules\Insights\Domain\Metrics;

use DateTimeImmutable;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Insights\Contracts\MetricDefinition;
use Modules\Insights\Domain\MetricResult;
use Modules\Scheduling\Contracts\AppointmentMetrics;

/**
 * Spec §16 "Revenue trend when reliable revenue/payment data exists" and "Average appointment
 * value when calculable" — folded into one metric, because this product has no payment capture
 * for a tenant's own grooming jobs (Billing's invoices are the tenant's SaaS subscription *to*
 * GroomerLoop, not money the tenant took from its customers — see `D-038`). What *can* be stated
 * honestly is each completed appointment's own listed service price, summed and averaged. That is
 * reported as "Estimated appointment value", never "Revenue" — a real, defined formula, but an
 * estimate of list price charged, not a captured payment.
 */
final readonly class EstimatedAppointmentValueMetric implements MetricDefinition
{
    public function __construct(
        private AppointmentMetrics $appointments,
        private ServiceCatalog $services,
    ) {}

    public function key(): string
    {
        return 'estimated_appointment_value';
    }

    public function label(): string
    {
        return 'Estimated appointment value';
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

        $services = $this->services->findMany(array_keys($countsByService));

        $totalCents = 0;
        $completedCount = 0;

        foreach ($countsByService as $serviceId => $count) {
            $price = $services[$serviceId]?->priceCents ?? 0;
            $totalCents += $price * $count;
            $completedCount += $count;
        }

        return MetricResult::ok([
            'estimated_total_cents' => $totalCents,
            'estimated_average_cents' => $completedCount > 0 ? intdiv($totalCents, $completedCount) : 0,
            'completed_count' => $completedCount,
        ]);
    }
}
