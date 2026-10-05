<?php

namespace Modules\Reviews\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Reviews\Models\Review;

/**
 * Log a review staff saw on an external platform (spec §20). Never a review this product
 * generates or submits — the caller's own validated input is the only source of the rating,
 * platform and comment (invariant #6, §20's two "never" bullets).
 */
final class RecordReview
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes, int $recordedBy): Review
    {
        $review = Review::query()->create([...$attributes, 'recorded_by' => $recordedBy]);

        $this->audit->record('review.recorded', $review, ['platform' => $review->platform]);

        return $review;
    }
}
