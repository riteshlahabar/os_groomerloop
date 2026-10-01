<?php

namespace Modules\Team\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Team\Models\StaffMember;

/**
 * Edit a member of staff (spec §23).
 */
final class UpdateStaffMember
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly SyncStaffServices $services,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>|null  $serviceIds  null leaves eligibility alone; [] clears every restriction
     */
    public function execute(StaffMember $staff, array $attributes, ?array $serviceIds = null): StaffMember
    {
        return DB::transaction(function () use ($staff, $attributes, $serviceIds): StaffMember {
            $staff->fill($attributes);

            $changed = array_keys($staff->getDirty());

            $staff->save();

            if ($serviceIds !== null) {
                $this->services->execute($staff, $serviceIds);
            }

            if ($changed !== [] || $serviceIds !== null) {
                $this->audit->record('staff.updated', $staff, [
                    'changed' => $changed,
                    'services_changed' => $serviceIds !== null,
                ]);
            }

            return $staff;
        });
    }
}
