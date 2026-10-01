<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Pets\Contracts\PetDirectory;
use Modules\Scheduling\Models\Appointment;
use Modules\Scheduling\Models\AppointmentStatusHistory;
use Modules\Scheduling\Services\AvailabilityEngine;
use Modules\Team\Contracts\StaffDirectory;
use Modules\Team\Models\StaffMember;

/**
 * Book one appointment (spec §11), concurrency-safe (invariant #2, spec §35).
 *
 * Every id in `$attributes` comes from the client, so each is checked through its owning
 * module's own contract — never a bare `exists:table,id`, which would let a probing caller
 * confirm another business's row the same way every other action in this codebase refuses to.
 *
 * Concurrency: the only existing precedent in this codebase is
 * `Billing\Services\InvoiceNumbers::next()` — lock, then check, inside one transaction; the
 * query is the real guarantee, the lock is what stops two concurrent callers from both passing
 * the check before either has written. That pattern locks rows that already exist; this one
 * can't, because the two concurrent callers are racing to create a row that exists in neither
 * of their transactions yet. So the lock is taken on `StaffMember` itself — a stable row both
 * transactions must touch — which serialises every booking attempt for *that groomer* behind
 * one gate. A side effect: two bookings for the same groomer at two unrelated times also
 * serialise, not just genuinely conflicting ones. Acceptable at a salon's real booking volume,
 * and simpler and more obviously correct than a narrower lock would be.
 */
final class BookAppointment
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly AvailabilityEngine $availability,
        private readonly CustomerDirectory $customers,
        private readonly PetDirectory $pets,
        private readonly ServiceCatalog $catalog,
        private readonly StaffDirectory $staff,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes, ?int $recurrenceGroupId = null): Appointment
    {
        $this->assertReferencesAreValid($attributes);

        $service = $this->catalog->find((int) $attributes['service_id']);
        $start = Carbon::parse($attributes['starts_at']);
        $staffMemberId = $attributes['staff_member_id'] ?? null;

        return DB::transaction(function () use ($attributes, $recurrenceGroupId, $service, $start, $staffMemberId): Appointment {
            // The serialisation point — see the class docblock. A null staff member has nothing
            // to lock: an unassigned "requested" appointment can't conflict with anything yet.
            if ($staffMemberId !== null) {
                StaffMember::query()->whereKey($staffMemberId)->lockForUpdate()->first();
            }

            if (! $this->availability->isAvailable((int) $attributes['service_id'], $staffMemberId, $start)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'That slot is no longer available.',
                ]);
            }

            $appointment = new Appointment;
            $appointment->fill([
                'customer_id' => $attributes['customer_id'],
                'pet_id' => $attributes['pet_id'],
                'service_id' => $attributes['service_id'],
                'staff_member_id' => $staffMemberId,
                'starts_at' => $start,
                'ends_at' => $start->copy()->addMinutes($service->occupiesMinutes),
                'customer_notes' => $attributes['customer_notes'] ?? null,
                'internal_notes' => $attributes['internal_notes'] ?? null,
                'recurrence_group_id' => $recurrenceGroupId,
            ]);
            $appointment->save();

            if (! empty($attributes['add_on_service_ids'])) {
                foreach (array_unique($attributes['add_on_service_ids']) as $addOnId) {
                    $appointment->addOns()->create(['service_id' => (int) $addOnId]);
                }
            }

            AppointmentStatusHistory::query()->create([
                'appointment_id' => $appointment->getKey(),
                'from_status' => null,
                'to_status' => $appointment->status->value,
                'changed_by' => auth()->id(),
            ]);

            $this->audit->record('appointment.booked', $appointment, [
                'customer_id' => $appointment->customer_id,
                'pet_id' => $appointment->pet_id,
                'service_id' => $appointment->service_id,
                'staff_member_id' => $appointment->staff_member_id,
                'starts_at' => $appointment->starts_at->toIso8601String(),
                'recurrence_group_id' => $recurrenceGroupId,
            ]);

            return $appointment;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertReferencesAreValid(array $attributes): void
    {
        $customerId = (int) $attributes['customer_id'];
        $petId = (int) $attributes['pet_id'];
        $serviceId = (int) $attributes['service_id'];
        $staffMemberId = $attributes['staff_member_id'] ?? null;

        $errors = [];

        if (! $this->customers->exists($customerId)) {
            $errors['customer_id'] = 'The selected customer could not be found.';
        } elseif (! $this->pets->belongsTo($petId, $customerId)) {
            $errors['pet_id'] = 'The selected pet does not belong to this customer.';
        }

        if (! $this->catalog->isSellable($serviceId)) {
            $errors['service_id'] = 'The selected service could not be found.';
        }

        if ($staffMemberId !== null && ! $this->staff->isAssignable((int) $staffMemberId)) {
            $errors['staff_member_id'] = 'The selected staff member could not be found.';
        }

        if ($staffMemberId !== null && ! $this->staff->canPerform((int) $staffMemberId, $serviceId)) {
            $errors['staff_member_id'] = 'This staff member is not eligible to perform the selected service.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
