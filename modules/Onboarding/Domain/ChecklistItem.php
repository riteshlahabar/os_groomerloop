<?php

namespace Modules\Onboarding\Domain;

/**
 * One row of the §7 checklist as the client sees it.
 *
 * A readonly value rather than an array so the shape is declared once and the resource, the
 * status contract and the tests all agree on it.
 */
final readonly class ChecklistItem
{
    public function __construct(
        public OnboardingStep $step,
        public bool $completed,
        public bool $skipped,
        public bool $skippable,

        /**
         * True when completion is derived from real records rather than the owner saying so.
         * The client uses it to decide whether to render a "mark as done" control at all.
         */
        public bool $verified,

        /**
         * Set when a step is verified but no module has registered a verifier for it yet —
         * the capability is not built. Distinct from "the owner has work to do", and the
         * checklist says so rather than nagging about something impossible.
         */
        public bool $unavailable = false,
    ) {}

    public function outstanding(): bool
    {
        return ! $this->completed && ! $this->skipped;
    }
}
