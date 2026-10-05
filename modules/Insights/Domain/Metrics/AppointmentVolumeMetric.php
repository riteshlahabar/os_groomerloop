<?php

namespace Modules\Insights\Domain\Metrics;

use DateTimeImmutable;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Insights\Contracts\MetricDefinition;
use Modules\Insights\Domain\MetricResult;
use Modules\Scheduling\Contracts\AppointmentMetrics;

/**
 * Spec §16 "Appointment volume" — a daily trend of appointments that still occupy a slot
 * (cancelled/no-show excluded, since a slot that was never kept is not volume).
 */
final readonly class AppointmentVolumeMetric implements MetricDefinition
{
    public function __construct(private AppointmentMetrics $appointments) {}

    public function key(): string
    {
        return 'appointment_volume';
    }

    public function label(): string
    {
        return 'Appointment volume';
    }

    public function minimumGrade(): FeatureGrade
    {
        return FeatureGrade::Basic;
    }

    public function compute(DateTimeImmutable $from, DateTimeImmutable $to): MetricResult
    {
        return MetricResult::ok(['by_day' => $this->appointments->volumeByDay($from, $to)]);
    }
}
