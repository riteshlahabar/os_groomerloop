<?php

namespace Modules\Pets\Domain;

/**
 * Spec §9 lists sex among the pet's details.
 *
 * `Unknown` is a real case and the default, not a placeholder: a rescue arrives without papers,
 * and a front desk should never have to guess to save the record. Storing a guess would put
 * wrong information on a record other staff then trust.
 */
enum PetSex: string
{
    case Male = 'male';
    case Female = 'female';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
            self::Unknown => 'Unknown',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
