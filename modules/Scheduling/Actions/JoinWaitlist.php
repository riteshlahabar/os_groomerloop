<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Pets\Contracts\PetDirectory;
use Modules\Scheduling\Models\WaitlistEntry;
use Modules\Team\Contracts\StaffDirectory;

/**
 * Hold a request for a service that has no open slot today (spec §11). Validates every id
 * through its owning module's contract — the same reasoning `BookAppointment::
 * assertReferencesAreValid()` already established, repeated here rather than shared because the
 * two checks are not identical (this one has no add-ons to validate).
 */
final class JoinWaitlist
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly CustomerDirectory $customers,
        private readonly PetDirectory $pets,
        private readonly ServiceCatalog $catalog,
        private readonly StaffDirectory $staff,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): WaitlistEntry
    {
        $this->assertReferencesAreValid($attributes);

        $entry = new WaitlistEntry;
        $entry->fill([
            'customer_id' => $attributes['customer_id'],
            'pet_id' => $attributes['pet_id'],
            'service_id' => $attributes['service_id'],
            'staff_member_id' => $attributes['staff_member_id'] ?? null,
            'requested_date' => $attributes['requested_date'],
            'notes' => $attributes['notes'] ?? null,
        ]);
        $entry->save();

        $this->audit->record('waitlist.joined', $entry, [
            'customer_id' => $entry->customer_id,
            'pet_id' => $entry->pet_id,
            'service_id' => $entry->service_id,
            'staff_member_id' => $entry->staff_member_id,
            'requested_date' => $entry->requested_date->toDateString(),
        ]);

        return $entry;
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
