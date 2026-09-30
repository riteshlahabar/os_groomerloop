<?php

namespace Modules\Crm\Actions;

use Illuminate\Support\Str;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\CustomerTag;
use Modules\Tenancy\Support\TenantContext;

/**
 * Put a set of tags on a customer, creating any that do not exist yet (spec §8).
 *
 * Tags are created on demand rather than having to be defined first, because that is how
 * people actually tag things — they type "nervous" while looking at the customer, not by
 * going to a settings screen beforehand.
 */
final class SyncCustomerTags
{
    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * @param  list<string>  $names
     */
    public function execute(Customer $customer, array $names): void
    {
        $ids = [];

        foreach ($names as $name) {
            $name = trim($name);
            $slug = Str::slug($name);

            // An empty name, or one that is nothing but punctuation, would slug to "" and
            // collide with every other such tag on the unique index.
            if ($name === '' || $slug === '') {
                continue;
            }

            // firstOrCreate on the slug, not the name: "Nervous" and "nervous" are one tag,
            // and the unique index on (tenant_id, slug) would reject the second anyway.
            $tag = CustomerTag::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name],
            );

            $ids[] = $tag->getKey();
        }

        // The pivot carries tenant_id of its own (see the migration), and sync() does not
        // know about it — so it is supplied explicitly rather than left null.
        $tenantId = $this->tenants->id();

        $customer->tags()->sync(
            array_fill_keys(array_unique($ids), ['tenant_id' => $tenantId])
        );
    }
}
