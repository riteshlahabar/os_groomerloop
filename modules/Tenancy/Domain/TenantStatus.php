<?php

namespace Modules\Tenancy\Domain;

/**
 * Lifecycle of a tenant account.
 *
 * Distinct from subscription state, which Phase 3 owns: a tenant can be active here while
 * their subscription is past_due. This enum answers "may this business use the OS at all",
 * not "have they paid".
 */
enum TenantStatus: string
{
    case Active = 'active';

    /**
     * Access is blocked but nothing is deleted — invariant #4 applies to suspension just as
     * it applies to a plan downgrade.
     */
    case Suspended = 'suspended';

    case Cancelled = 'cancelled';

    public function allowsAccess(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
