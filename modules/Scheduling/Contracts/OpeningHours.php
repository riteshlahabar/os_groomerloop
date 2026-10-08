<?php

namespace Modules\Scheduling\Contracts;

/**
 * The business's published weekly opening hours, read-only (spec §11, §14).
 *
 * Exists because the §14 tenant-site templates display opening hours in their hero and footer
 * chrome, and the alternative was printing the design bundle's stock "MON - FRI 9:30 AM - 7:30 PM"
 * on a real business's public website — a fabricated fact about a real company, which invariant #7
 * forbids for a dashboard number and is no more acceptable on a marketing page.
 *
 * Website must not reach `Scheduling\Models\BusinessHour` itself (D-007), and
 * `AppointmentScheduler`/`AppointmentMetrics` answer "can I book this slot" and "what happened" —
 * neither answers "what does the door say". Hence a third, deliberately tiny contract.
 *
 * A day with no window is closed; a day may carry more than one window (closing for lunch is
 * real), so every day maps to a list, never a single pair.
 */
interface OpeningHours
{
    /**
     * Opening windows for the current tenant, keyed by ISO day number (Monday = 1 .. Sunday = 7).
     *
     * Days the business is closed are present with an empty list, so a caller can render a full
     * week without re-deriving which days are missing. Times are `HH:MM`, tenant-local wall clock
     * — the same convention `BusinessHour` stores and the booking engine compares against.
     *
     * @return array<int, list<array{starts_at: string, ends_at: string}>>
     */
    public function weekly(): array;

    /**
     * True when the business has no opening window on any day at all.
     *
     * Separate from an all-empty `weekly()` because the two mean different things to a caller: an
     * unconfigured business should render no hours block rather than seven "Closed" rows, which
     * would read as a business that never opens. See CLAUDE.md's "An unconfigured table answers
     * 'nothing', not 'unconfigured'" trap — this is that distinction, made at the contract.
     */
    public function isUnset(): bool;
}
