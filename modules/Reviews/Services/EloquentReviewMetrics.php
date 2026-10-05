<?php

namespace Modules\Reviews\Services;

use DateTimeImmutable;
use Modules\Reviews\Contracts\ReviewMetrics;
use Modules\Reviews\Models\Review;

final class EloquentReviewMetrics implements ReviewMetrics
{
    /**
     * @return array{count: int, average_rating: float|null}
     */
    public function summaryBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $reviews = Review::query()
            ->whereBetween('reviewed_at', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->get(['rating']);

        $rated = $reviews->whereNotNull('rating');

        return [
            'count' => $reviews->count(),
            'average_rating' => $rated->isEmpty() ? null : round($rated->avg('rating'), 1),
        ];
    }
}
