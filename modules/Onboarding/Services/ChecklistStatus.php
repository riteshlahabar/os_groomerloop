<?php

namespace Modules\Onboarding\Services;

use Modules\Onboarding\Contracts\OnboardingStatus;
use Modules\Onboarding\Domain\ChecklistItem;
use Modules\Onboarding\Domain\OnboardingStep;
use Modules\Onboarding\Models\OnboardingProgress;

/**
 * Assembles the spec §7 checklist from two sources that are kept deliberately separate.
 *
 * A step is done either because the work is visibly done (services exist, hours are set) or
 * because the owner said so. Verified steps take the first route and *ignore* stored
 * completion entirely — if a business deletes all its services, the services step goes back
 * to outstanding, which is the honest answer and the one the §16 dashboard alert needs.
 *
 * Skipping still works on a verified step. Skipping is a statement about intent ("not now"),
 * not a claim that the work is done, so it is recorded rather than derived.
 */
final class ChecklistStatus implements OnboardingStatus
{
    public function __construct(private readonly StepVerifiers $verifiers) {}

    /**
     * @return list<ChecklistItem>
     */
    public function checklist(): array
    {
        $progress = $this->progress();

        return array_map(
            fn (OnboardingStep $step): ChecklistItem => $this->item($step, $progress),
            OnboardingStep::all()
        );
    }

    public function isReady(): bool
    {
        $progress = $this->progress();

        foreach (OnboardingStep::required() as $step) {
            if (! $this->item($step, $progress)->completed) {
                return false;
            }
        }

        return true;
    }

    public function isFinished(): bool
    {
        return $this->progress()?->isFinished() ?? false;
    }

    public function percentComplete(): int
    {
        $items = $this->checklist();

        if ($items === []) {
            return 0;
        }

        // Skipped counts as dealt with. A progress bar stuck at 80% because the owner does
        // not want a website is a progress bar nobody trusts.
        $done = count(array_filter(
            $items,
            static fn (ChecklistItem $item): bool => $item->completed || $item->skipped
        ));

        return (int) round(($done / count($items)) * 100);
    }

    private function item(OnboardingStep $step, ?OnboardingProgress $progress): ChecklistItem
    {
        $skipped = $progress?->hasSkipped($step) ?? false;

        if (! $step->isVerified()) {
            return new ChecklistItem(
                step: $step,
                completed: $progress?->hasCompleted($step) ?? false,
                skipped: $skipped,
                skippable: $step->isSkippable(),
                verified: false,
            );
        }

        $satisfied = $this->verifiers->satisfied($step);

        return new ChecklistItem(
            step: $step,
            completed: $satisfied === true,
            skipped: $skipped,
            skippable: $step->isSkippable(),
            verified: true,

            // No verifier registered means the owning module is not built yet. Reported as
            // its own state so the client can say "coming soon" rather than nagging the
            // owner to do something the product cannot yet accept.
            unavailable: $satisfied === null,
        );
    }

    private function progress(): ?OnboardingProgress
    {
        return OnboardingProgress::query()->first();
    }
}
