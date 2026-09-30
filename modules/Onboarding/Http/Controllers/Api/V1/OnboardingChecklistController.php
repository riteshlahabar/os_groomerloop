<?php

namespace Modules\Onboarding\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Onboarding\Contracts\OnboardingStatus;
use Modules\Onboarding\Http\Resources\ChecklistItemResource;

/**
 * The spec §7 checklist as it stands right now.
 *
 * Read-only. Acting on a step belongs to OnboardingStepController and finishing to
 * OnboardingCompletionController — three use cases, three controllers.
 */
final class OnboardingChecklistController
{
    public function __invoke(OnboardingStatus $status): JsonResponse
    {
        return response()->json([
            'data' => [
                'steps' => ChecklistItemResource::collection($status->checklist())->resolve(),

                // "Ready" and "finished" are different questions and the dashboard needs
                // both: ready means the business can take a booking, finished means the
                // owner has left the checklist. A business can be finished with skipped
                // steps outstanding, and can be ready before it has finished.
                'is_ready' => $status->isReady(),
                'is_finished' => $status->isFinished(),
                'percent_complete' => $status->percentComplete(),
            ],
        ]);
    }
}
