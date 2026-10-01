<?php

namespace Modules\Crm\Actions;

use Modules\Crm\Models\Customer;
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
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly UpsertCustomerTag $upsert,
    ) {}

    /**
     * @param  list<string>  $names
     */
    public function execute(Customer $customer, array $names): void
    {
        $ids = [];

        foreach ($names as $name) {
            // Creation goes through the one action that owns it, so a tag typed onto a
            // customer and a tag added on the settings screen cannot drift apart — including
            // the rule that a name with nothing to slug ("!!!") is not a tag at all.
            $tag = $this->upsert->execute($name);

            if ($tag === null) {
                continue;
            }

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
