<?php

namespace Modules\Insights\Domain\Metrics;

use DateTimeImmutable;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Insights\Contracts\MetricDefinition;
use Modules\Insights\Domain\MetricResult;
use Modules\Scheduling\Contracts\AppointmentMetrics;

/**
 * Spec §16 "Returning customers" — of the customers who booked in the range, how many already
 * had an appointment before it started.
 */
final readonly class ReturningCustomersMetric implements MetricDefinition
{
    public function __construct(private AppointmentMetrics $appointments) {}

    public function key(): string
    {
        return 'returning_customers';
    }

    public function label(): string
    {
        return 'Returning customers';
    }

    public function minimumGrade(): FeatureGrade
    {
        return FeatureGrade::Standard;
    }

    public function compute(DateTimeImmutable $from, DateTimeImmutable $to): MetricResult
    {
        return MetricResult::ok(['count' => $this->appointments->returningCustomerCount($from, $to)]);
    }
}
