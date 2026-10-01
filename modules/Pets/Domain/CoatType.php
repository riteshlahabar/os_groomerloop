<?php

namespace Modules\Pets\Domain;

/**
 * Spec §9: "Coat/grooming characteristics".
 *
 * Structured rather than left to the notes field because coat is the single biggest driver of
 * how long a groom takes, and §10 needs to reason about service duration per pet. A double coat
 * on a large dog is an afternoon; the same dog clipped short is an hour.
 *
 * The free-text `coat_notes` column sits alongside this for everything a category cannot carry
 * ("matted behind the ears", "clipped short in June").
 */
enum CoatType: string
{
    case Short = 'short';
    case Medium = 'medium';
    case Long = 'long';
    case Double = 'double';
    case Curly = 'curly';
    case Wire = 'wire';
    case Hairless = 'hairless';

    public function label(): string
    {
        return match ($this) {
            self::Short => 'Short',
            self::Medium => 'Medium',
            self::Long => 'Long',
            self::Double => 'Double coat',
            self::Curly => 'Curly',
            self::Wire => 'Wire',
            self::Hairless => 'Hairless',
        };
    }

    /**
     * Coats that mat if they are not maintained, which is what makes an overdue appointment a
     * welfare question rather than a scheduling preference. §22 retention will want this.
     */
    public function mats(): bool
    {
        return in_array($this, [self::Long, self::Double, self::Curly], strict: true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
