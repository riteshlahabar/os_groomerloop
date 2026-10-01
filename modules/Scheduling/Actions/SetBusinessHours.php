<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Scheduling\Models\BusinessHour;
use Modules\Tenancy\Support\TenantContext;

/**
 * Replace the business's whole week (spec §11, §7 "business hours and closed days").
 *
 * Replace rather than merge, and overlap-refused on the same day — the exact shape
 * `Team\Actions\SetWorkingHours` already established for exactly the same kind of data.
 */
final class SetBusinessHours
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @param  list<array{day_of_week: int, starts_at: string, ends_at: string}>  $windows
     */
    public function execute(array $windows): void
    {
        $this->refuseOverlaps($windows);

        $tenant = $this->tenants->tenant();

        DB::transaction(function () use ($windows): void {
            BusinessHour::query()->delete();

            foreach ($windows as $window) {
                BusinessHour::query()->create($window);
            }
        });

        $this->audit->record('business_hours.changed', $tenant, [
            'windows' => count($windows),
            'closed_every_day' => $windows === [],
        ]);
    }

    /**
     * @param  list<array{day_of_week: int, starts_at: string, ends_at: string}>  $windows
     */
    private function refuseOverlaps(array $windows): void
    {
        $byDay = [];

        foreach ($windows as $window) {
            $byDay[(int) $window['day_of_week']][] = $window;
        }

        foreach ($byDay as $daysWindows) {
            $count = count($daysWindows);

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($this->overlap($daysWindows[$i], $daysWindows[$j])) {
                        throw ValidationException::withMessages([
                            'windows' => 'Two windows on the same day cannot overlap.',
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
        $aStart = BusinessHour::minutesFromMidnight($a['starts_at']);
        $aEnd = BusinessHour::minutesFromMidnight($a['ends_at']);
        $bStart = BusinessHour::minutesFromMidnight($b['starts_at']);
        $bEnd = BusinessHour::minutesFromMidnight($b['ends_at']);

        if ($aStart === null || $aEnd === null || $bStart === null || $bEnd === null) {
            return false;
        }

        return $aStart < $bEnd && $bStart < $aEnd;
    }
}
