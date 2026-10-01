<?php

namespace Modules\Booking\Domain;

/**
 * How a public booking's `requested` appointment becomes `confirmed` (spec §12 "automatic or
 * manual confirmation mode").
 */
enum ConfirmationMode: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automatic',
            self::Manual => 'Manual review',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $m): string => $m->value, self::cases());
    }
}
