<?php

namespace Modules\Scheduling\Services;

use App\Domain\DayOfWeek;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Scheduling\Models\Appointment;
use Modules\Scheduling\Models\BusinessHour;
use Modules\Team\Contracts\StaffDirectory;

/**
 * The composed answer to "can this appointment happen" (spec §11, invariant #2).
 *
 * Four questions, cheapest first, short-circuiting on the first "no": business hours (this
 * module's own table), the service's own rules (Catalog), staff availability (Team), then
 * whether an existing appointment already occupies the slot. Each half already exists and is
 * already tested in its own module — this only composes them, the way `D-017`'s docblock
 * predicted: "Scheduling (§11) must consult two contracts to validate an appointment... which
 * is the honest shape of the question anyway."
 *
 * Read-only. The booking action is what locks and writes; this class answers the same question
 * whether or not a lock is held, so it can be asked speculatively (an availability-query
 * endpoint) as well as inside the locked, about-to-write path.
 */
final class AvailabilityEngine
{
    public function __construct(
        private readonly ServiceCatalog $catalog,
        private readonly StaffDirectory $staff,
    ) {}

    public function isAvailable(
        int $serviceId,
        ?int $staffMemberId,
        DateTimeInterface $start,
        ?int $excludingAppointmentId = null,
    ): bool {
        $service = $this->catalog->find($serviceId);

        if ($service === null) {
            return false;
        }

        if (! $this->businessIsOpen($start, $service->occupiesMinutes)) {
            return false;
        }

        if (! $this->catalog->isAvailableAt($serviceId, $start)) {
            return false;
        }

        if ($staffMemberId === null) {
            return true;
        }

        if (! $this->staff->isAvailableAt($staffMemberId, $start, $service->occupiesMinutes)) {
            return false;
        }

        return ! $this->hasConflictingAppointment(
            $staffMemberId,
            $start,
            Carbon::parse($start)->addMinutes($service->occupiesMinutes),
            $excludingAppointmentId,
        );
    }

    private function businessIsOpen(DateTimeInterface $start, int $minutes): bool
    {
        $windows = BusinessHour::query()
            ->where('day_of_week', DayOfWeek::fromDate($start)->value)
            ->get();

        if ($windows->isEmpty()) {
            return false;
        }

        return $windows->contains(
            fn (BusinessHour $window): bool => $window->accommodates($start->format('H:i'), $minutes)
        );
    }

    private function hasConflictingAppointment(
        int $staffMemberId,
        DateTimeInterface $start,
        DateTimeInterface $end,
        ?int $excludingAppointmentId,
    ): bool {
        return Appointment::query()
            ->forStaff($staffMemberId)
            ->overlapping($start, $end)
            ->when(
                $excludingAppointmentId !== null,
                fn ($query) => $query->whereKeyNot($excludingAppointmentId)
            )
            ->exists();
    }
}
