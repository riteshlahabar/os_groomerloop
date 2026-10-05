<?php

namespace Modules\Reviews\Contracts;

use DateTimeImmutable;

/**
 * What Insights (spec §16) reads to compute `review_trend`, without touching the `reviews`
 * table itself (D-007).
 */
interface ReviewMetrics
{
    /**
     * @return array{count: int, average_rating: float|null}
     */
    public function summaryBetween(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
