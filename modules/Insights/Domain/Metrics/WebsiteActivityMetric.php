<?php

namespace Modules\Insights\Domain\Metrics;

use DateTimeImmutable;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Insights\Contracts\MetricDefinition;
use Modules\Insights\Domain\MetricResult;

/**
 * Spec §16 "Booking/website activity where analytics exist" — §14's public site and §12's
 * booking page capture no visit/analytics data today, so there is nothing to report. Kept as its
 * own metric, always insufficient, for the same reason as {@see ReviewTrendMetric}: the gap is
 * visible rather than the row simply missing.
 */
final readonly class WebsiteActivityMetric implements MetricDefinition
{
    public function key(): string
    {
        return 'website_activity';
    }

    public function label(): string
    {
        return 'Booking / website activity';
    }

    public function minimumGrade(): FeatureGrade
    {
        return FeatureGrade::Standard;
    }

    public function compute(DateTimeImmutable $from, DateTimeImmutable $to): MetricResult
    {
        return MetricResult::insufficientData();
    }
}
