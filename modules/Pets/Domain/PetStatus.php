<?php

namespace Modules\Pets\Domain;

/**
 * Where a pet stands with the business (spec §9).
 *
 * `Deceased` is a separate state from `Archived` and that distinction is the reason this enum
 * exists at all. §22 retention sends rebooking prompts and win-back campaigns for pets that have
 * not been seen; sending one about a dog that has died is the single worst message this product
 * could generate. It has to be expressible in the data, not left as a note someone has to read.
 *
 * Nothing is ever deleted (invariant #4): a pet carries grooming and appointment history that
 * the business needs after the animal is gone.
 */
enum PetStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
    case Deceased = 'deceased';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Archived => 'Archived',
            self::Deceased => 'Deceased',
        };
    }

    /**
     * Does this pet appear in the ordinary working lists?
     */
    public function isCurrent(): bool
    {
        return $this === self::Active;
    }

    /**
     * May the business be prompted to contact anyone about this pet?
     *
     * False for both non-active states, and for different reasons: an archived pet is no longer
     * a customer, and a deceased pet must never generate a rebooking reminder.
     */
    public function allowsOutreach(): bool
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
