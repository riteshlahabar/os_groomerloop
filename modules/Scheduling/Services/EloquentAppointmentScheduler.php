<?php

namespace Modules\Scheduling\Services;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Modules\Scheduling\Actions\BookAppointment;
use Modules\Scheduling\Actions\RescheduleAppointment;
use Modules\Scheduling\Actions\UpdateAppointmentStatus;
use Modules\Scheduling\Contracts\AppointmentScheduler;
use Modules\Scheduling\Domain\AppointmentStatus;
use Modules\Scheduling\Domain\AppointmentSummary;
use Modules\Scheduling\Models\Appointment;

final class EloquentAppointmentScheduler implements AppointmentScheduler
{
    public function __construct(
        private readonly AvailabilityEngine $availability,
        private readonly BookAppointment $booker,
        private readonly RescheduleAppointment $rescheduler,
        private readonly UpdateAppointmentStatus $statusUpdater,
    ) {}

    public function exists(int $appointmentId): bool
    {
        return Appointment::query()->whereKey($appointmentId)->exists();
    }

    public function find(int $appointmentId): ?AppointmentSummary
    {
        $appointment = Appointment::query()->find($appointmentId);

        return $appointment === null ? null : $this->summarise($appointment);
    }

    public function appointmentsFor(int $staffMemberId, DateTimeInterface $from, DateTimeInterface $to): array
    {
        return Appointment::query()
            ->forStaff($staffMemberId)
            ->overlapping($from, $to)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $a): AppointmentSummary => $this->summarise($a))
            ->all();
    }

    public function startingBetween(DateTimeInterface $from, DateTimeInterface $to): array
    {
        return Appointment::query()
            ->overlapping($from, $to)
            ->where('starts_at', '>=', $from)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $a): AppointmentSummary => $this->summarise($a))
            ->all();
    }

    public function isSlotAvailable(int $serviceId, ?int $staffMemberId, DateTimeInterface $start): bool
    {
        return $this->availability->isAvailable($serviceId, $staffMemberId, $start);
    }

    public function openSlotsFor(int $serviceId, ?int $staffMemberId, DateTimeInterface $date): array
    {
        return $this->availability->openSlotsOn($serviceId, $staffMemberId, $date);
    }

    public function book(array $attributes): AppointmentSummary
    {
        return $this->summarise($this->booker->execute($attributes));
    }

    public function reschedule(int $appointmentId, DateTimeInterface $start): AppointmentSummary
    {
        $appointment = Appointment::query()->findOrFail($appointmentId);

        return $this->summarise($this->rescheduler->execute($appointment, Carbon::parse($start)));
    }

    public function cancel(int $appointmentId): AppointmentSummary
    {
        return $this->updateStatus($appointmentId, AppointmentStatus::Cancelled);
    }

    public function updateStatus(int $appointmentId, AppointmentStatus $status, ?string $note = null): AppointmentSummary
    {
        $appointment = Appointment::query()->findOrFail($appointmentId);

        return $this->summarise($this->statusUpdater->execute($appointment, $status, $note));
    }

    public function appointmentsForCustomer(int $customerId): array
    {
        return Appointment::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (Appointment $a): AppointmentSummary => $this->summarise($a))
            ->all();
    }

    private function summarise(Appointment $appointment): AppointmentSummary
    {
        return $appointment->toSummary();
    }
}
