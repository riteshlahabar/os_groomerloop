<?php

namespace Modules\Onboarding\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Onboarding\Domain\ChecklistItem;

/**
 * @property-read ChecklistItem $resource
 */
final class ChecklistItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'step' => $this->resource->step->value,
            'position' => $this->resource->step->position(),
            'label' => $this->resource->step->label(),
            'description' => $this->resource->step->description(),

            'completed' => $this->resource->completed,
            'skipped' => $this->resource->skipped,
            'outstanding' => $this->resource->outstanding(),

            // Whether the client should render a "mark as done" control. A verified step
            // completes itself when the work is done, and offering a button that always
            // returns 422 would be worse than offering none.
            'skippable' => $this->resource->skippable,
            'verified' => $this->resource->verified,

            // The owning module is not built yet, so this step cannot be satisfied by
            // anyone. Distinct from "you have work to do" — the client should say so rather
            // than nag about something impossible.
            'unavailable' => $this->resource->unavailable,
        ];
    }
}
