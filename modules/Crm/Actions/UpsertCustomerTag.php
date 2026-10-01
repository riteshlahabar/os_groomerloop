<?php

namespace Modules\Crm\Actions;

use Illuminate\Support\Str;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Crm\Models\CustomerTag;

/**
 * Add a tag to the business's vocabulary, or hand back the one that already covers it
 * (spec §8).
 *
 * The single place a tag is created. "The same tag asked for twice is one tag" is a domain
 * rule shared by two very different callers — a receptionist typing "Nervous" onto a customer
 * and an owner curating the list on a settings screen — and both have to land on the same row
 * rather than on a unique-index violation.
 */
final class UpsertCustomerTag
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * Returns null when the name carries nothing to slug — "!!!" and "???" would both become
     * the empty slug and collide with each other on the unique index.
     */
    public function execute(string $name, ?string $colour = null): ?CustomerTag
    {
        $name = trim($name);
        $slug = Str::slug($name);

        if ($slug === '') {
            return null;
        }

        // Looked up by slug, created by name. Not firstOrCreate: that mass-assigns the lookup
        // key, and `slug` is deliberately not fillable — it is derived from the name by the
        // model, so a client must never be able to set it to something the name does not
        // produce. The model's saving hook then writes the same slug we searched on.
        $tag = CustomerTag::query()->where('slug', $slug)->first();

        if ($tag !== null) {
            return $tag;
        }

        $tag = CustomerTag::query()->create(array_filter([
            'name' => $name,
            'colour' => $colour,
        ], static fn (mixed $value): bool => $value !== null));

        $this->audit->record('customer_tag.created', $tag, ['name' => $tag->name]);

        return $tag;
    }
}
