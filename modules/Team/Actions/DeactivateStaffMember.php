<?php

namespace Modules\Team\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Team\Domain\StaffStatus;
use Modules\Team\Models\StaffMember;

/**
 * Someone has left (spec §23 "invite/deactivate staff").
 *
 * Nothing is deleted. A groomer who leaves has months of appointments behind them, and §11 history has
 * to keep naming who did the work (invariant #4). They stop being assignable, disappear from the
 * booking page, and their rota is left exactly as it was — because if they come back, the salon should
 * not have to type it in again, and because their shifts are part of the record of when they worked.
 *
 * What this deliberately does **not** do is revoke their login. That is a separate act on a separate
 * object, audited separately, and Identity owns it: a groomer moving to the front desk stops being
 * bookable and keeps their account, while someone leaving the business needs both.
 */
final class DeactivateStaffMember
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(StaffMember $staff): StaffMember
    {
        if ($staff->status === StaffStatus::Inactive) {
            return $staff;
        }

        $staff->status = StaffStatus::Inactive;
        $staff->save();

        $this->audit->record('staff.deactivated', $staff, [
            'display_name' => $staff->display_name,

            // Recorded so an owner reading the log can see whether the account still needs dealing
            // with — the most commonly forgotten half of someone leaving.
            'still_has_login' => $staff->user_id !== null,
        ]);

        return $staff;
    }

    public function reactivate(StaffMember $staff): StaffMember
    {
        if ($staff->status === StaffStatus::Active) {
            return $staff;
        }

        $staff->status = StaffStatus::Active;
        $staff->save();

        $this->audit->record('staff.reactivated', $staff, [
            'display_name' => $staff->display_name,
        ]);

        return $staff;
    }
}
