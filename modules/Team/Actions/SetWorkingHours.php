<?php

namespace Modules\Team\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Team\Models\StaffMember;
use Modules\Team\Models\StaffWorkingHour;

/**
 * Replace a groomer's normal week (spec §23 "working hours").
 *
 * Replace rather than merge: a rota is one statement about when someone works, and a partial update
 * would leave a salon unable to drop a shift without knowing which row it was.
 *
 * Overlapping shifts on the same day are refused. Two shifts that overlap are not a split shift, they
 * are a mistake — and once §11 lays them on a calendar the same minute would be offered twice, which is
 * exactly the double-booking invariant #2 exists to prevent.
 */
final class SetWorkingHours
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  list<array{day_of_week: int, starts_at: string, ends_at: string}>  $shifts
     */
    public function execute(StaffMember $staff, array $shifts): StaffMember
    {
        $this->refuseOverlaps($shifts);

        DB::transaction(function () use ($staff, $shifts): void {
            $staff->workingHours()->delete();

            foreach ($shifts as $shift) {
                // No tenant_id passed: it is not fillable, and BelongsToTenant stamps it on create.
                $staff->workingHours()->create($shift);
            }
        });

        $this->audit->record('staff.working_hours_changed', $staff, [
            'shifts' => count($shifts),

            // A distinct, meaningful state rather than an empty one: nobody with no shifts is
            // available, so this is how a salon takes a groomer off the rota without them leaving.
            'no_hours' => $shifts === [],
        ]);

        return $staff->refresh();
    }

    /**
     * @param  list<array{day_of_week: int, starts_at: string, ends_at: string}>  $shifts
     */
    private function refuseOverlaps(array $shifts): void
    {
        $byDay = [];

        foreach ($shifts as $shift) {
            $byDay[(int) $shift['day_of_week']][] = $shift;
        }

        foreach ($byDay as $day => $daysShifts) {
            $count = count($daysShifts);

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($this->overlap($daysShifts[$i], $daysShifts[$j])) {
                        throw ValidationException::withMessages([
                            'shifts' => 'Two shifts on the same day cannot overlap.',
                        ]);
                    }
                }
            }
        }
    }

    /**
     * @param  array{starts_at: string, ends_at: string}  $a
     * @param  array{starts_at: string, ends_at: string}  $b
     */
    private function overlap(array $a, array $b): bool
    {
        $aStart = StaffWorkingHour::minutesFromMidnight($a['starts_at']);
        $aEnd = StaffWorkingHour::minutesFromMidnight($a['ends_at']);
        $bStart = StaffWorkingHour::minutesFromMidnight($b['starts_at']);
        $bEnd = StaffWorkingHour::minutesFromMidnight($b['ends_at']);

        if ($aStart === null || $aEnd === null || $bStart === null || $bEnd === null) {
            return false;
        }

        // Touching is not overlapping: a shift ending at 13:00 and another starting at 13:00 is a
        // perfectly ordinary lunch boundary.
        return $aStart < $bEnd && $bStart < $aEnd;
    }
}
