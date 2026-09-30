<?php

namespace Modules\Onboarding\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Onboarding\Domain\OnboardingStep;
use Modules\Onboarding\Models\OnboardingProgress;

/**
 * Mark a §7 step done, or set it aside for later.
 *
 * Completing and skipping are one action because they are one decision — "what is happening
 * with this step" — and they share every guard: the step must exist, a required step cannot
 * be skipped, and a verified step cannot be hand-ticked.
 */
final class RecordStepDecision
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function complete(OnboardingStep $step): OnboardingProgress
    {
        // A verified step is done when the work is done. Letting the client mark it complete
        // would let a business declare itself ready to take bookings with no services.
        if ($step->isVerified()) {
            throw ValidationException::withMessages([
                'step' => sprintf(
                    'The "%s" step completes itself once the work is done.',
                    $step->label()
                ),
            ]);
        }

        $progress = $this->progress();
        $progress->markCompleted($step);
        $progress->save();

        $this->audit->record('onboarding.step_completed', $progress, ['step' => $step->value]);

        return $progress;
    }

    public function skip(OnboardingStep $step): OnboardingProgress
    {
        if (! $step->isSkippable()) {
            throw ValidationException::withMessages([
                'step' => sprintf(
                    'The "%s" step cannot be skipped — the business cannot operate without it.',
                    $step->label()
                ),
            ]);
        }

        $progress = $this->progress();
        $progress->markSkipped($step);
        $progress->save();

        $this->audit->record('onboarding.step_skipped', $progress, ['step' => $step->value]);

        return $progress;
    }

    /**
     * Spec §7 step 12: leave the checklist and enter the dashboard.
     *
     * Allowed with skipped steps outstanding — that is what skippable means — but not with a
     * required step undone, because "finished" would then be a claim the business can take
     * bookings when it cannot.
     */
    public function finish(bool $ready): OnboardingProgress
    {
        if (! $ready) {
            throw ValidationException::withMessages([
                'onboarding' => 'Finish the required setup steps before entering the dashboard.',
            ]);
        }

        $progress = $this->progress();

        if (! $progress->isFinished()) {
            $progress->finished_at = now();
            $progress->save();

            $this->audit->record('onboarding.finished', $progress, [
                'skipped' => $progress->skipped_steps,
            ]);
        }

        return $progress;
    }

    /**
     * Onboarding is resumable, so the progress row is created on first use rather than at
     * registration — a business that never opens the checklist has no row, and that is fine.
     */
    private function progress(): OnboardingProgress
    {
        return OnboardingProgress::query()->first()
            ?? OnboardingProgress::create(['completed_steps' => [], 'skipped_steps' => []]);
    }
}
