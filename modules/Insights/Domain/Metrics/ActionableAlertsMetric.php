<?php

namespace Modules\Insights\Domain\Metrics;

use DateTimeImmutable;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Insights\Contracts\MetricDefinition;
use Modules\Insights\Domain\MetricResult;
use Modules\Scheduling\Contracts\AppointmentMetrics;

/**
 * Spec §16 "Actionable alerts" — plain-language flags derived from metrics this module already
 * computes, not a second data source. Two rules today, both thresholds stated here rather than
 * configurable, so the number on screen always matches the rule that produced it:
 *
 *   - 3 or more no-shows in the range ("no-shows are adding up").
 *   - Any customer whose last visit was 45+ days ago with nothing booked next ("lapsing").
 *
 * `$to` stands in for "as of now" for the lapsed-customer check — the end of whatever range the
 * viewer selected, which for the default "today" range is today.
 */
final readonly class ActionableAlertsMetric implements MetricDefinition
{
    private const NO_SHOW_ALERT_THRESHOLD = 3;

    private const LAPSED_AFTER_DAYS = 45;

    public function __construct(private AppointmentMetrics $appointments) {}

    public function key(): string
    {
        return 'actionable_alerts';
    }

    public function label(): string
    {
        return 'Actionable alerts';
    }

    public function minimumGrade(): FeatureGrade
    {
        return FeatureGrade::Standard;
    }

    public function compute(DateTimeImmutable $from, DateTimeImmutable $to): MetricResult
    {
        $alerts = [];

        $noShows = $this->appointments->cancellationsAndNoShowsBetween($from, $to)['no_show'];

        if ($noShows >= self::NO_SHOW_ALERT_THRESHOLD) {
            $alerts[] = [
                'type' => 'no_shows',
                'count' => $noShows,
                'message' => "{$noShows} no-shows in this range",
            ];
        }

        $lapsed = $this->appointments->staleCustomerCount($to, self::LAPSED_AFTER_DAYS);

        if ($lapsed > 0) {
            $alerts[] = [
                'type' => 'lapsed_customers',
                'count' => $lapsed,
                'message' => "{$lapsed} customers haven't booked in ".self::LAPSED_AFTER_DAYS.'+ days',
            ];
        }

        return MetricResult::ok(['alerts' => $alerts]);
    }
}
