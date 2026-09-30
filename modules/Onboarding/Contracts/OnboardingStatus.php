<?php

namespace Modules\Onboarding\Contracts;

use Modules\Onboarding\Domain\ChecklistItem;

/**
 * How other modules ask where a business is in its setup (spec §7, §16).
 *
 * The dashboard needs it for the §16 "actionable alerts", and public booking needs to know
 * whether the business is ready to take one. Both ask here rather than reading the
 * onboarding tables (D-007).
 */
interface OnboardingStatus
{
    /**
     * Every §7 step with its current state, in order.
     *
     * @return list<ChecklistItem>
     */
    public function checklist(): array;

    /**
     * Has every step that cannot be skipped been done?
     *
     * This — not `finished_at` — is what "can this business operate" means. An owner may
     * leave the checklist early, and a business can be finished with skipped steps still
     * outstanding.
     */
    public function isReady(): bool;

    /**
     * Has the owner left the checklist and entered the dashboard (§7 step 12)?
     */
    public function isFinished(): bool;

    /**
     * Whole-number percentage, for a progress bar.
     */
    public function percentComplete(): int;
}
