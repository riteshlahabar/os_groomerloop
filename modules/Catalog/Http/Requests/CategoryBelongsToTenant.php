<?php

namespace Modules\Catalog\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Catalog\Models\ServiceCategory;

/**
 * The category must be one of this business's own.
 *
 * Its own rule class rather than `exists:service_categories,id`, which searches every tenant: a
 * probing caller could otherwise confirm that some other business holds a given category id, and a
 * mis-typed id would attach a service to a stranger's category (invariant #1). The query here is
 * tenant-scoped by the global scope, so an id from elsewhere simply is not found.
 */
final class CategoryBelongsToTenant implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! ServiceCategory::query()->whereKey((int) $value)->exists()) {
            $fail('The selected category could not be found.');
        }
    }
}
