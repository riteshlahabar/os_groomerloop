<?php

namespace Modules\Catalog\Actions;

use Illuminate\Support\Str;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Catalog\Models\ServiceCategory;

/**
 * Add a category to the menu, or hand back the one that already covers it (spec §10).
 *
 * Looked up by slug and created by name — not `firstOrCreate`, which mass-assigns its lookup key.
 * `slug` is derived from the name by the model and deliberately not fillable, and that exact
 * combination was a 500 in the CRM's customer tags before a test caught it.
 */
final class UpsertServiceCategory
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * Null when the name carries nothing to slug — two such names would collide on the unique index.
     */
    public function execute(string $name, int $position = 0): ?ServiceCategory
    {
        $name = trim($name);
        $slug = Str::slug($name);

        if ($slug === '') {
            return null;
        }

        $category = ServiceCategory::query()->where('slug', $slug)->first();

        if ($category !== null) {
            return $category;
        }

        $category = ServiceCategory::query()->create([
            'name' => $name,
            'position' => $position,
        ]);

        $this->audit->record('service_category.created', $category, ['name' => $category->name]);

        return $category;
    }
}
