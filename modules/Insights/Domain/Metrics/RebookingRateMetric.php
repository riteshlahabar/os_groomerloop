<?php

namespace Modules\Insights\Domain\Metrics;

use DateTimeImmutable;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Insights\Contracts\MetricDefinition;
use Modules\Insights\Domain\MetricResult;
use Modules\Scheduling\Contracts\AppointmentMetrics;

/**
 * Spec §16 "Rebooking/retention indicators" — of the appointments completed in the range, what
 * share of those customers booked again within 60 days of that visit ending. 60 is stated here,
 * not configurable, so the percentage on screen always matches the window that produced it.
 */
final readonly class RebookingRateMetric implements MetricDefinition
{
    private const REBOOKING_WINDOW_DAYS = 60;

    public function __construct(private AppointmentMetrics $appointments) {}

    public function key(): string
    {
        return 'rebooking_rate';
    }

    public function label(): string
    {
        return 'Rebooking rate';
    }

    public function minimumGrade(): FeatureGrade
    {
        return FeatureGrade::Advanced;
    }

    public function compute(DateTimeImmutable $from, DateTimeImmutable $to): MetricResult
    {
        $stats = $this->appointments->rebookingRate($from, $to, self::REBOOKING_WINDOW_DAYS);

        if ($stats['completed'] === 0) {
            return MetricResult::insufficientData();
        }

        return MetricResult::ok([
            'completed_count' => $stats['completed'],
            'rebooked_count' => $stats['rebooked'],
            'rebooking_percent' => (int) round(($stats['rebooked'] / $stats['completed']) * 100),
            'window_days' => self::REBOOKING_WINDOW_DAYS,
        ]);
    }
}
