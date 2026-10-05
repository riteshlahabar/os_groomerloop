<?php

namespace Modules\Pets\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Pets\Models\Species;

/**
 * The species must be one of this business's own — mirrors Catalog's `CategoryBelongsToTenant`
 * exactly, for the same reason: `exists:pet_species,id` searches every tenant, and a probing
 * caller could use that to confirm another business's species id exists (invariant #1). The
 * query here is tenant-scoped by the global scope, so an id from elsewhere simply is not found.
 */
final class SpeciesBelongsToTenant implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! Species::query()->whereKey((int) $value)->exists()) {
            $fail('The selected species could not be found.');
        }
    }
}
