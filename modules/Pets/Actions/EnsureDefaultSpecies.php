<?php

namespace Modules\Pets\Actions;

use Modules\Pets\Models\Species;

/**
 * Every tenant starts with Dog/Cat/Other (spec §9) — the same three options the fixed enum this
 * table replaced always offered, so nobody opens the pet form to find species gone. Seeded
 * lazily, on first read, the same shape `modules/Website` provisions a tenant's site (`D-030`)
 * — a brand-new tenant that registered after this module shipped gets these without a migration
 * or a registration-flow hook needing to know this module exists.
 */
final class EnsureDefaultSpecies
{
    /**
     * @var list<string>
     */
    private const DEFAULTS = ['Dog', 'Cat', 'Other'];

    public function execute(): void
    {
        if (Species::query()->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $position => $name) {
            Species::query()->create(['name' => $name, 'position' => $position]);
        }
    }
}
