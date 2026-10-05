<?php

namespace Modules\Reviews\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Reviews\Models\ReviewDestination;

/**
 * Add a review destination, or hand back the one with the same label (spec §20). Mirrors
 * `Pets\Actions\UpsertSpecies` and `Catalog\Actions\UpsertServiceCategory`: looked up by the
 * column the unique index covers, never `firstOrCreate`.
 */
final class UpsertReviewDestination
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(string $label, string $url, int $position = 0): ReviewDestination
    {
        $label = trim($label);

        $destination = ReviewDestination::query()->where('label', $label)->first();

        if ($destination !== null) {
            return $destination;
        }

        $destination = ReviewDestination::query()->create([
            'label' => $label,
            'url' => trim($url),
            'position' => $position,
        ]);

        $this->audit->record('review_destination.created', $destination, ['label' => $destination->label]);

        return $destination;
    }
}
