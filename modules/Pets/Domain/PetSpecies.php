<?php

namespace Modules\Pets\Domain;

/**
 * What kind of animal this is (spec §9).
 *
 * A closed set rather than free text, because §10 will price and time services by species and
 * §16 reports on the mix. Free text would give "dog", "Dog", "canine" and "dg" as four species
 * and make every one of those numbers a guess.
 *
 * Only three cases, and that is the judgement: a US grooming business grooms dogs and cats.
 * Adding rabbits, guinea pigs and birds up front would be inventing a market — `Other` carries
 * them, with the breed field holding what it actually was, until real demand says otherwise.
 */
enum PetSpecies: string
{
    case Dog = 'dog';
    case Cat = 'cat';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Dog => 'Dog',
            self::Cat => 'Cat',
            self::Other => 'Other',
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
