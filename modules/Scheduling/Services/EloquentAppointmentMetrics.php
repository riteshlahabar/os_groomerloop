<?php

namespace Modules\Scheduling\Services;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Scheduling\Contracts\AppointmentMetrics;
use Modules\Scheduling\Domain\AppointmentStatus;
use Modules\Scheduling\Models\Appointment;

final class EloquentAppointmentMetrics implements AppointmentMetrics
{
    public function countsForWindow(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $counts = $this->inStartWindow($from, $to)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $byStatus = [];
        $total = 0;

        foreach (AppointmentStatus::cases() as $status) {
            $count = (int) ($counts[$status->value] ?? 0);
            $byStatus[$status->value] = $count;
            $total += $count;
        }

        return ['total' => $total, 'by_status' => $byStatus];
    }

    public function newBookingsBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return Appointment::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->count();
    }

    public function cancellationsAndNoShowsBetween(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $counts = $this->inStartWindow($from, $to)
            ->whereIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        return [
            'cancelled' => (int) ($counts[AppointmentStatus::Cancelled->value] ?? 0),
            'no_show' => (int) ($counts[AppointmentStatus::NoShow->value] ?? 0),
        ];
    }

    public function volumeByDay(DateTimeInterface $from, DateTimeInterface $to): array
    {
        return $this->inStartWindow($from, $to)
            ->whereIn('status', $this->occupyingStatusValues())
            ->selectRaw('DATE(starts_at) as d, count(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd')
            ->mapWithKeys(fn (mixed $count, string $day): array => [$day => (int) $count])
            ->all();
    }

    public function completedCountByService(DateTimeInterface $from, DateTimeInterface $to): array
    {
        return $this->inStartWindow($from, $to)
            ->where('status', AppointmentStatus::Completed->value)
            ->selectRaw('service_id, count(*) as c')
            ->groupBy('service_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->service_id => (int) $row->c])
            ->all();
    }

    public function occupiedMinutesByStaff(DateTimeInterface $from, DateTimeInterface $to): array
    {
        return $this->inStartWindow($from, $to)
            ->whereIn('status', $this->occupyingStatusValues())
            ->whereNotNull('staff_member_id')
            ->selectRaw('staff_member_id, SUM(TIMESTAMPDIFF(MINUTE, starts_at, ends_at)) as minutes')
            ->groupBy('staff_member_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->staff_member_id => (int) $row->minutes])
            ->all();
    }

    public function returningCustomerCount(DateTimeInterface $from, DateTimeInterface $to): int
    {
        $occupying = $this->occupyingStatusValues();

        $customerIdsInRange = $this->inStartWindow($from, $to)
            ->whereIn('status', $occupying)
            ->distinct()
            ->pluck('customer_id');

        if ($customerIdsInRange->isEmpty()) {
            return 0;
        }

        return Appointment::query()
            ->whereIn('customer_id', $customerIdsInRange)
            ->whereIn('status', $occupying)
            ->where('starts_at', '<', $from)
            ->distinct('customer_id')
            ->count('customer_id');
    }

    /**
     * Deliberately one query per completed appointment rather than a single self-join: this
     * reporting endpoint runs on demand over a bounded window (a month at most for a small
     * grooming business, not a continuously-queried hot path), and the per-row question — "did
     * this customer rebook within N days of this specific visit ending?" — reads far more clearly
     * this way than as SQL.
     */
    public function rebookingRate(DateTimeInterface $from, DateTimeInterface $to, int $withinDays): array
    {
        $completed = $this->inStartWindow($from, $to)
            ->where('status', AppointmentStatus::Completed->value)
            ->get(['id', 'customer_id', 'ends_at']);

        if ($completed->isEmpty()) {
            return ['completed' => 0, 'rebooked' => 0];
        }

        $rebooked = 0;

        foreach ($completed as $appointment) {
            $windowEnd = Carbon::parse($appointment->ends_at)->addDays($withinDays);

            $hasRebooking = Appointment::query()
                ->where('customer_id', $appointment->customer_id)
                ->where('id', '!=', $appointment->id)
                ->where('starts_at', '>', $appointment->ends_at)
                ->where('starts_at', '<=', $windowEnd)
                ->exists();

            if ($hasRebooking) {
                $rebooked++;
            }
        }

        return ['completed' => $completed->count(), 'rebooked' => $rebooked];
    }

    public function staleCustomerCount(DateTimeInterface $asOf, int $inactivityDays): int
    {
        $cutoff = Carbon::parse($asOf)->subDays($inactivityDays);
        $occupying = $this->occupyingStatusValues();

        // Last slot-occupying appointment per customer, then kept only if that last one ended
        // before the cutoff and nothing is booked after it — a customer who has an upcoming
        // appointment is not lapsed just because their last *past* visit was a while ago.
        $lastEndByCustomer = Appointment::query()
            ->whereIn('status', $occupying)
            ->selectRaw('customer_id, MAX(ends_at) as last_end')
            ->groupBy('customer_id')
            ->get();

        $staleCustomerIds = $lastEndByCustomer
            ->filter(fn (object $row): bool => Carbon::parse($row->last_end)->lt($cutoff))
            ->pluck('customer_id');

        if ($staleCustomerIds->isEmpty()) {
            return 0;
        }

        $withUpcoming = Appointment::query()
            ->whereIn('customer_id', $staleCustomerIds)
            ->whereIn('status', $occupying)
            ->where('starts_at', '>=', $asOf)
            ->distinct()
            ->pluck('customer_id');

        return $staleCustomerIds->diff($withUpcoming)->count();
    }

    /**
     * @return Builder<Appointment>
     */
    private function inStartWindow(DateTimeInterface $from, DateTimeInterface $to)
    {
        return Appointment::query()
            ->where('starts_at', '>=', $from)
            ->where('starts_at', '<', $to);
    }

    /**
     * @return list<string>
     */
    private function occupyingStatusValues(): array
    {
        return array_values(array_map(
            static fn (AppointmentStatus $s): string => $s->value,
            array_filter(AppointmentStatus::cases(), static fn (AppointmentStatus $s): bool => $s->occupiesSlot())
        ));
    }
}
