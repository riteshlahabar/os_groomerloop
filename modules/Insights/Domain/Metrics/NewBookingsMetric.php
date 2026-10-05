<?php

namespace Modules\Insights\Domain\Metrics;

use DateTimeImmutable;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Insights\Contracts\MetricDefinition;
use Modules\Insights\Domain\MetricResult;
use Modules\Scheduling\Contracts\AppointmentMetrics;

/**
 * Spec §16 "New bookings" — appointments created in the range, regardless of when they are
 * for. Distinct from {@see AppointmentsByStatusMetric}, which counts by when the appointment
 * happens rather than when it was booked.
 */
final readonly class NewBookingsMetric implements MetricDefinition
{
    public function __construct(private AppointmentMetrics $appointments) {}

    public function key(): string
    {
        return 'new_bookings';
    }

    public function label(): string
    {
        return 'New bookings';
    }

    public function minimumGrade(): FeatureGrade
    {
        return FeatureGrade::Basic;
    }

    public function compute(DateTimeImmutable $from, DateTimeImmutable $to): MetricResult
    {
        return MetricResult::ok(['count' => $this->appointments->newBookingsBetween($from, $to)]);
    }
}
