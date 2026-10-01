<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Scheduling\Domain\WaitlistStatus;
use Modules\Scheduling\Models\Appointment;
use Modules\Scheduling\Models\WaitlistEntry;

/**
 * Turn a waiting entry into a real appointment once a slot opens (spec §11).
 *
 * Deliberately calls `BookAppointment` rather than writing the appointment itself — `D-023`'s
 * rule is "one appointment engine", and that applies just as much to this entry point as it does
 * to the future public booking page. `BookAppointment` re-validates and re-checks availability on
 * its own, which is correct: an entry sitting on the waitlist for days must not skip the same
 * concurrency-safe check every other booking goes through.
 */
final class ConvertWaitlistEntryToAppointment
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly BookAppointment $book,
    ) {}

    public function execute(WaitlistEntry $entry, Carbon $start, ?int $staffMemberId): Appointment
    {
        if ($entry->status->isTerminal()) {
            throw ValidationException::withMessages([
                'waitlist_entry' => 'This waitlist entry has already been booked or cancelled.',
            ]);
        }

        $appointment = $this->book->execute([
            'customer_id' => $entry->customer_id,
            'pet_id' => $entry->pet_id,
            'service_id' => $entry->service_id,
            'staff_member_id' => $staffMemberId ?? $entry->staff_member_id,
            'starts_at' => $start,
            'customer_notes' => $entry->notes,
        ]);

        $entry->status = WaitlistStatus::Booked;
        $entry->appointment_id = $appointment->getKey();
        $entry->save();

        $this->audit->record('waitlist.converted', $entry, [
            'appointment_id' => $appointment->getKey(),
            'starts_at' => $appointment->starts_at->toIso8601String(),
        ]);

        return $appointment;
    }
}
