<?php

namespace Modules\Insights\Domain\Metrics;

use DateTimeImmutable;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Insights\Contracts\MetricDefinition;
use Modules\Insights\Domain\MetricResult;

/**
 * Spec §16 "Review trend where integrated" — the "where integrated" is doing the work: §20
 * Reviews & Reputation has no code yet (CLAUDE.md pending module #18), so there is no review data
 * to trend. Kept as its own metric, always insufficient, rather than left off the dashboard
 * entirely — invariant #7 wants the gap shown, not hidden, and the moment §20 exists this class
 * is the one place that changes.
 */
final readonly class ReviewTrendMetric implements MetricDefinition
{
    public function key(): string
    {
        return 'review_trend';
    }

    public function label(): string
    {
        return 'Review trend';
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
