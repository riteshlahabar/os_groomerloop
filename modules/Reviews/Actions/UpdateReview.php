<?php

namespace Modules\Reviews\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Reviews\Models\Review;

final class UpdateReview
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Review $review, array $attributes): Review
    {
        $review->fill($attributes);
        $changed = array_keys($review->getDirty());
        $review->save();

        if ($changed !== []) {
            $this->audit->record('review.updated', $review, ['changed' => $changed]);
        }

        return $review;
    }
}
