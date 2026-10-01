<?php

namespace Modules\Team\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Team\Models\StaffMember;
use Modules\Tenancy\Support\TenantContext;

/**
 * Which services this groomer is able to perform (spec §10 "eligible groomers/staff", §23 "services
 * assigned to staff").
 *
 * Team owns the link (`D-017`) and validates the other side through Catalog's contract — never by
 * querying Catalog's tables, and never with `exists:services,id`, which would search every tenant
 * and let a probing caller confirm another business's service ids.
 *
 * An empty list is meaningful: it clears every restriction, which means "can do everything". That is
 * the default for a new staff member too, so a solo groomer never has to tick their own name against
 * every service before the business can take a booking.
 */
final class SyncStaffServices
{
    public function __construct(
        private readonly ServiceCatalog $catalog,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @param  list<int>  $serviceIds
     */
    public function execute(StaffMember $staff, array $serviceIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $serviceIds)));

        foreach ($ids as $id) {
            // exists(), not isSellable(): a groomer may remain qualified for a service the salon has
            // retired, and removing the qualification when the price list changes would quietly
            // rewrite who can do what.
            if (! $this->catalog->exists($id)) {
                throw ValidationException::withMessages([
                    'service_ids' => 'Every assigned service must be one of this business\'s services.',
                ]);
            }
        }

        $tenantId = $this->tenants->id();
        $now = now();

        DB::transaction(function () use ($staff, $ids, $tenantId, $now): void {
            // Replaced wholesale rather than diffed: the set is one statement about what this person
            // can do, and the table is a handful of rows per groomer.
            DB::table('staff_member_service')
                ->where('staff_member_id', $staff->getKey())
                ->delete();

            if ($ids === []) {
                return;
            }

            DB::table('staff_member_service')->insert(array_map(
                static fn (int $serviceId): array => [
                    'tenant_id' => $tenantId,
                    'staff_member_id' => $staff->getKey(),
                    'service_id' => $serviceId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $ids,
            ));
        });
    }

    /**
     * The services currently assigned, for a resource or a form.
     *
     * @return list<int>
     */
    public function current(StaffMember $staff): array
    {
        return DB::table('staff_member_service')
            ->where('staff_member_id', $staff->getKey())
            ->pluck('service_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
