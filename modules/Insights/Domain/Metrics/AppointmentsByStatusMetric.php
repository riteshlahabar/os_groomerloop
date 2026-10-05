<?php

namespace Modules\Insights\Domain\Metrics;

use DateTimeImmutable;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Insights\Contracts\MetricDefinition;
use Modules\Insights\Domain\MetricResult;
use Modules\Scheduling\Contracts\AppointmentMetrics;

/**
 * Spec §16 "Today's/upcoming appointments" — appointments starting in the selected range, by
 * status. When the range is "today", this is literally today's schedule; a wider range reads as
 * "upcoming" instead. One metric, because the formula does not change — only the window does.
 */
final readonly class AppointmentsByStatusMetric implements MetricDefinition
{
    public function __construct(private AppointmentMetrics $appointments) {}

    public function key(): string
    {
        return 'appointments_by_status';
    }

    public function label(): string
    {
        return 'Appointments';
    }

    public function minimumGrade(): FeatureGrade
    {
        return FeatureGrade::Basic;
    }

    public function compute(DateTimeImmutable $from, DateTimeImmutable $to): MetricResult
    {
        return MetricResult::ok($this->appointments->countsForWindow($from, $to));
    }
}
