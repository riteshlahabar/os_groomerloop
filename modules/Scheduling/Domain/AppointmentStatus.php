<?php

namespace Modules\Scheduling\Domain;

/**
 * The seven statuses of spec §11, as an explicit state machine — the same shape
 * `Billing\Domain\SubscriptionStatus` uses, for the same reason: a closed transition map makes
 * impossible combinations unrepresentable (a `no-show` that later turns `completed`, a
 * `cancelled` appointment moved to `checked-in`) instead of merely unlikely.
 *
 * The flow:
 *
 *   Requested ──confirms──► Confirmed ──arrives──► CheckedIn ──starts──► InService ──► Completed
 *       │                       │
 *       │ declines/cancels      │ cancels, or never arrives
 *       ▼                       ▼
 *   Cancelled               Cancelled / NoShow
 *
 * `CheckedIn` and `InService` cannot be cancelled or marked no-show: once someone is physically
 * on site doing the work, those words no longer describe reality.
 */
enum AppointmentStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked-in';
    case InService = 'in-service';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no-show';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Confirmed => 'Confirmed',
            self::CheckedIn => 'Checked in',
            self::InService => 'In service',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No-show',
        };
    }

    /**
     * Does a slot held by an appointment in this status still occupy the calendar?
     *
     * Cancelled and no-show appointments free their slot; every other status — including
     * completed, for the time already spent — does not retroactively unbook the past.
     */
    public function occupiesSlot(): bool
    {
        return $this !== self::Cancelled && $this !== self::NoShow;
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Cancelled, self::NoShow => true,
            default => false,
        };
    }

    /**
     * States reachable from this one.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Requested => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::CheckedIn, self::Cancelled, self::NoShow],
            self::CheckedIn => [self::InService],
            self::InService => [self::Completed],
            self::Completed, self::Cancelled, self::NoShow => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        // Re-asserting the current state is always allowed, the same reasoning
        // SubscriptionStatus uses for replayed webhooks: an idempotent caller must not error.
        if ($next === $this) {
            return true;
        }

        return in_array($next, $this->allowedTransitions(), strict: true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
