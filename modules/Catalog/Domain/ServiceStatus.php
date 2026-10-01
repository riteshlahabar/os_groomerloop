<?php

namespace Modules\Catalog\Domain;

/**
 * Spec §10: "Active/inactive status".
 *
 * Two states, exactly as the spec words it, and no delete anywhere in the module. A service is
 * referenced by every appointment that ever used it (§11), so removing one would detach history
 * from the work it describes — invariant #4 applied to the catalogue rather than to customers.
 *
 * Inactive means "we no longer sell this": it disappears from the booking page and from the
 * new-appointment picker, while every past appointment still resolves its name, price and duration.
 */
enum ServiceStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    /**
     * May this service be put on a new appointment?
     *
     * Deliberately not the same question as whether it may be shown online — a salon books plenty
     * of things over the counter that it does not publish (§10 lists booking visibility separately).
     */
    public function isSellable(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
