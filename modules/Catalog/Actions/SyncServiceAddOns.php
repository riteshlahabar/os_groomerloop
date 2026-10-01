<?php

namespace Modules\Catalog\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Catalog\Models\Service;
use Modules\Tenancy\Support\TenantContext;

/**
 * Decide which add-ons a service offers (spec §10).
 *
 * Two rules, enforced here rather than in the form request because the action is also reached from
 * a create and from any future import:
 *
 *   - an add-on must actually be flagged as one, so a full groom cannot be offered as an extra on
 *     another full groom;
 *   - a service cannot be its own add-on.
 */
final class SyncServiceAddOns
{
    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * @param  list<int>  $addOnIds
     */
    public function execute(Service $service, array $addOnIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $addOnIds)));

        if (in_array((int) $service->getKey(), $ids, strict: true)) {
            throw ValidationException::withMessages([
                'add_on_ids' => 'A service cannot be its own add-on.',
            ]);
        }

        // Scoped, so an id from another business is simply not found and the count check below
        // refuses the request rather than silently attaching nothing.
        $found = $ids === [] ? [] : Service::query()
            ->addOns()
            ->whereKey($ids)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if (count($found) !== count($ids)) {
            throw ValidationException::withMessages([
                'add_on_ids' => 'Every add-on must be an existing service marked as an add-on.',
            ]);
        }

        // The pivot carries tenant_id of its own (see the migration) and sync() does not know about
        // it, so it is supplied explicitly rather than left null.
        $tenantId = $this->tenants->id();

        $service->addOns()->sync(array_fill_keys($found, ['tenant_id' => $tenantId]));
    }
}
