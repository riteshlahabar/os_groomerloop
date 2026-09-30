<?php

namespace Modules\Onboarding\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Onboarding\Actions\RecordStepDecision;
use Modules\Onboarding\Contracts\OnboardingStatus;
use Modules\Onboarding\Domain\ChecklistItem;
use Modules\Onboarding\Domain\OnboardingStep;
use Modules\Onboarding\Http\Resources\ChecklistItemResource;

/**
 * Marking a §7 step done, or setting it aside for later.
 *
 * The {step} route parameter is typed as the enum, so Laravel resolves it and answers 404
 * for anything that is not a real step — no validation rule needed, and no way to reach the
 * action with a string the domain does not recognise.
 */
final class OnboardingStepController
{
    public function complete(OnboardingStep $step, RecordStepDecision $decide, OnboardingStatus $status): JsonResponse
    {
        $decide->complete($step);

        return $this->stateOf($step, $status);
    }

    public function skip(OnboardingStep $step, RecordStepDecision $decide, OnboardingStatus $status): JsonResponse
    {
        $decide->skip($step);

        return $this->stateOf($step, $status);
    }

    /**
     * Returns the step's new state rather than a bare 204, so the client can re-render the
     * row — and the overall progress — without a second request.
     */
    private function stateOf(OnboardingStep $step, OnboardingStatus $status): JsonResponse
    {
        $item = collect($status->checklist())
            ->first(static fn (ChecklistItem $item): bool => $item->step === $step);

        return response()->json([
            'data' => ChecklistItemResource::make($item)->resolve(),
            'meta' => [
                'is_ready' => $status->isReady(),
                'percent_complete' => $status->percentComplete(),
            ],
        ]);
    }
}
