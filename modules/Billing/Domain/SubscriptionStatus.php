<?php

namespace Modules\Billing\Domain;

/**
 * The SaaS lifecycle of spec §24, as an explicit state machine.
 *
 * Written as states with declared transitions rather than a handful of booleans
 * (is_trialing, is_cancelled, has_failed_payment) because the interesting bugs in billing are
 * combinations that should be impossible: cancelled and trialing at once, active with no paid
 * invoice, a grace period on a subscription that was never past due. A closed transition map
 * makes those unrepresentable instead of merely unlikely.
 *
 * The flow:
 *
 *   Trialing ──pays──────────► Active ◄──────pays──── PastDue
 *      │                        │  ▲                    │
 *      │                        │  │                    │ grace period configured
 *      │ trial ends unpaid      │  │ reactivates        ▼
 *      └──────────────► PastDue │  └──────────────── Grace
 *                         │     │                       │
 *                         │     └─ cancel ──┐           │ grace expires
 *                         └── dunning ends ─┴─────► Cancelled ◄┘
 *
 * Cancelled is terminal for that subscription record; reactivation opens a new one or
 * resumes this one before the period ends, which is why Cancelled → Active is allowed.
 */
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Grace = 'grace';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Trialing',
            self::Active => 'Active',
            self::PastDue => 'Payment failed',
            self::Grace => 'Grace period',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Does this state entitle the business to its paid plan?
     *
     * Trialing, Active, PastDue and Grace all do. Only Cancelled drops the business back to
     * the default tier — and even then it keeps every record it owns (invariant #4).
     *
     * PastDue deliberately still entitles. A card that expired overnight must not lock a
     * groomer out of the appointments they are about to work; that is what the dunning window
     * and the grace period are for.
     */
    public function entitlesToPlan(): bool
    {
        return $this !== self::Cancelled;
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    /**
     * Is the business behind on payment — past due or in its grace period?
     */
    public function isDelinquent(): bool
    {
        return $this === self::PastDue || $this === self::Grace;
    }

    /**
     * States reachable from this one.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Trialing => [self::Active, self::PastDue, self::Cancelled],
            self::Active => [self::PastDue, self::Cancelled],
            self::PastDue => [self::Active, self::Grace, self::Cancelled],
            self::Grace => [self::Active, self::Cancelled],

            // Reactivation (spec §24). A cancelled subscription can be brought back, which is
            // a different act from opening a new one and is audited as such.
            self::Cancelled => [self::Active],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        // Re-asserting the current state is always allowed: gateway webhooks are replayed, and
        // "still active" must be a no-op rather than an error.
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
