<?php

namespace Modules\Insights\Domain\Metrics;

use DateTimeImmutable;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Insights\Contracts\MetricDefinition;
use Modules\Insights\Domain\MetricResult;
use Modules\Reviews\Contracts\ReviewMetrics;

/**
 * Spec §16 "Review trend where integrated" — reads Reviews' (§20) manual review log, added
 * 2026-10-05. Before that, §20 had no code at all and this always answered `insufficientData()`;
 * it still does for a tenant whose log is empty, never a fabricated number (invariant #7).
 */
final readonly class ReviewTrendMetric implements MetricDefinition
{
    public function __construct(private ReviewMetrics $reviews) {}

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
        $summary = $this->reviews->summaryBetween($from, $to);

        if ($summary['count'] === 0) {
            return MetricResult::insufficientData();
        }

        return MetricResult::ok([
            'count' => $summary['count'],
            'average_rating' => $summary['average_rating'],
        ]);
    }
}
