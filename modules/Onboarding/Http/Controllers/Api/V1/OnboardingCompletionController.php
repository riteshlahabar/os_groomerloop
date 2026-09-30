<?php

namespace Modules\Onboarding\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Onboarding\Actions\RecordStepDecision;
use Modules\Onboarding\Contracts\OnboardingStatus;

/**
 * Spec §7 step 12: complete the checklist and enter the dashboard.
 *
 * Refuses while a required step is undone — "finished" would otherwise claim the business
 * can take bookings when it has no hours or no services. Skipped steps are fine; that is
 * exactly what skippable means.
 */
final class OnboardingCompletionController
{
    public function __invoke(RecordStepDecision $decide, OnboardingStatus $status): JsonResponse
    {
        $progress = $decide->finish($status->isReady());

        return response()->json([
            'data' => [
                'is_finished' => true,
                'finished_at' => $progress->finished_at?->toIso8601String(),
                'skipped_steps' => $progress->skipped_steps,
                'percent_complete' => $status->percentComplete(),
            ],
        ]);
    }
}
