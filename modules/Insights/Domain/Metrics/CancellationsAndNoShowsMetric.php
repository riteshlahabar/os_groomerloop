<?php

namespace Modules\Insights\Domain\Metrics;

use DateTimeImmutable;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Insights\Contracts\MetricDefinition;
use Modules\Insights\Domain\MetricResult;
use Modules\Scheduling\Contracts\AppointmentMetrics;

/**
 * Spec §16 "Cancellations/no-shows" — appointments that were due in the range and ended up
 * cancelled or marked a no-show.
 */
final readonly class CancellationsAndNoShowsMetric implements MetricDefinition
{
    public function __construct(private AppointmentMetrics $appointments) {}

    public function key(): string
    {
        return 'cancellations_no_shows';
    }

    public function label(): string
    {
        return 'Cancellations / no-shows';
    }

    public function minimumGrade(): FeatureGrade
    {
        return FeatureGrade::Basic;
    }

    public function compute(DateTimeImmutable $from, DateTimeImmutable $to): MetricResult
    {
        return MetricResult::ok($this->appointments->cancellationsAndNoShowsBetween($from, $to));
    }
}
