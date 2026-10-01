<?php

namespace Modules\Team\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Team\Models\StaffMember;

/**
 * Add someone to the team (spec §23).
 *
 * Creating a staff record does not create a login and does not send an invitation — Identity owns
 * both (`POST /invitations`). A salon adds a Saturday junior to the rota now and decides about an
 * account later, or never.
 */
final class CreateStaffMember
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly SyncStaffServices $services,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>|null  $serviceIds
     */
    public function execute(array $attributes, ?array $serviceIds = null, ?int $userId = null): StaffMember
    {
        return DB::transaction(function () use ($attributes, $serviceIds, $userId): StaffMember {
            $staff = new StaffMember;
            $staff->fill($attributes);

            // Set outside fill() because user_id is deliberately not fillable: linking a staff record
            // to a login grants that person a groomer's calendar and a public profile.
            if ($userId !== null) {
                $staff->user_id = $userId;
            }

            $staff->save();

            if ($serviceIds !== null) {
                $this->services->execute($staff, $serviceIds);
            }

            $this->audit->record('staff.created', $staff, [
                'display_name' => $staff->display_name,
                'linked_to_user' => $userId !== null,
            ]);

            return $staff;
        });
    }
}
