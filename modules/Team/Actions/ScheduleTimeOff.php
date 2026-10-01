<?php

namespace Modules\Team\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Team\Models\StaffMember;
use Modules\Team\Models\StaffTimeOff;

/**
 * Record an absence (spec §23 "availability").
 *
 * Added one at a time rather than replacing a set, unlike working hours: a holiday is an event, and a
 * salon booking August off should not have to resend March's sick day along with it.
 *
 * What this does not do is cancel appointments that fall inside the absence. It cannot — §11 does not
 * exist yet — and it should not do it silently even then: a groomer taking a day off with four dogs
 * booked is a conversation with four customers, not a cascade delete. Phase 7 will surface the clash.
 */
final class ScheduleTimeOff
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(StaffMember $staff, array $attributes): StaffTimeOff
    {
        /** @var StaffTimeOff $absence */
        $absence = $staff->timeOff()->create($attributes);

        $this->audit->record('staff.time_off_scheduled', $staff, [
            'display_name' => $staff->display_name,
            'starts_at' => $absence->starts_at->toIso8601String(),
            'ends_at' => $absence->ends_at->toIso8601String(),

            // The reason is recorded only when the business chose to give one. Sickness detail is the
            // employee's business, and an audit log readable by anyone holding `audit.view` is not
            // where it belongs.
            'reason_given' => filled($absence->reason),
        ]);

        return $absence;
    }

    public function cancel(StaffMember $staff, StaffTimeOff $absence): void
    {
        $this->audit->record('staff.time_off_cancelled', $staff, [
            'display_name' => $staff->display_name,
            'starts_at' => $absence->starts_at->toIso8601String(),
        ]);

        $absence->delete();
    }
}
