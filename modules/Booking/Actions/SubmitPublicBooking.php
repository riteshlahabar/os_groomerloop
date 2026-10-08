<?php

namespace Modules\Booking\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Booking\Domain\ConfirmationMode;
use Modules\Booking\Models\BookingSettings;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Pets\Contracts\PetDirectory;
use Modules\Scheduling\Contracts\AppointmentScheduler;
use Modules\Scheduling\Domain\AppointmentStatus;
use Modules\Scheduling\Domain\AppointmentSummary;

/**
 * The spec §12 flow's last three steps — "reviews policies", "confirms booking", "receives
 * confirmation" — landing on the exact same engine the authenticated calendar uses (`D-023`).
 * Nothing here re-implements availability, locking or conflict detection; it only does what an
 * anonymous caller cannot: establish who the customer and pet are, and apply the two settings
 * (lead time, confirmation mode) that only make sense in a channel where nobody is logged in.
 */
final class SubmitPublicBooking
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly AppointmentScheduler $scheduler,
        private readonly CustomerDirectory $customers,
        private readonly PetDirectory $pets,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  customer: first_name, last_name, email, phone,
     *                                            password (optional, Customer Portal login); pet: pet_name,
     *                                            pet_species_id, pet_breed (optional), pet_sex (optional); booking:
     *                                            service_id, staff_member_id (optional), starts_at, customer_notes (optional)
     */
    public function execute(array $attributes): AppointmentSummary
    {
        $settings = BookingSettings::query()->first();
        $leadTimeMinutes = $settings?->lead_time_minutes ?? 60;
        $confirmationMode = $settings?->confirmation_mode ?? ConfirmationMode::Manual;

        $start = Carbon::parse($attributes['starts_at']);

        if ($start->lt(now()->addMinutes($leadTimeMinutes))) {
            throw ValidationException::withMessages([
                'starts_at' => "This business needs at least {$leadTimeMinutes} minutes' notice for a booking.",
            ]);
        }

        // species_id is shape-only in PublicBookingRequest (an integer) — this is where its
        // existence is actually checked, the same split that request's own docblock already
        // describes for service_id/staff_member_id. Nothing downstream would catch an unknown
        // one otherwise: unlike service_id, species is not part of the scheduling engine
        // `$this->scheduler->book()` validates.
        if (! $this->pets->speciesExists((int) $attributes['pet_species_id'])) {
            throw ValidationException::withMessages([
                'pet_species_id' => 'The selected species could not be found.',
            ]);
        }

        return DB::transaction(function () use ($attributes, $start, $confirmationMode): AppointmentSummary {
            $customerId = $this->customers->findOrCreateForPublicBooking([
                'first_name' => $attributes['first_name'],
                'last_name' => $attributes['last_name'],
                'email' => $attributes['email'],
                'phone' => $attributes['phone'] ?? null,
            ]);

            // Self-service, same as a claim-link password set — not "reset by staff" (see
            // `AdminResetCustomerPassword`'s docblock on keeping those audit events distinct).
            if (! empty($attributes['password'])) {
                $this->customers->setPassword($customerId, $attributes['password']);

                $this->audit->record('customer.password_set_at_booking', null, [
                    'customer_id' => $customerId,
                ]);
            }

            $petAttributes = [
                'name' => $attributes['pet_name'],
                'species_id' => $attributes['pet_species_id'],
                'breed' => $attributes['pet_breed'] ?? null,
            ];

            // Omitted entirely rather than passed as an explicit null when the stranger didn't
            // pick one: unlike `breed`, `sex` has a NOT NULL column with a database-level default
            // (PetSex::Unknown) — Eloquent's fill()+save() sends an explicit null straight through
            // an INSERT, bypassing that default and violating the column.
            if (! empty($attributes['pet_sex'])) {
                $petAttributes['sex'] = $attributes['pet_sex'];
            }

            $petId = $this->pets->createForPublicBooking($customerId, $petAttributes);

            $appointment = $this->scheduler->book([
                'customer_id' => $customerId,
                'pet_id' => $petId,
                'service_id' => $attributes['service_id'],
                'staff_member_id' => $attributes['staff_member_id'] ?? null,
                'starts_at' => $start,
                'customer_notes' => $attributes['customer_notes'] ?? null,
            ]);

            if ($confirmationMode === ConfirmationMode::Automatic) {
                $appointment = $this->scheduler->updateStatus($appointment->id, AppointmentStatus::Confirmed);
            }

            $this->audit->record('public_booking.submitted', null, [
                'appointment_id' => $appointment->id,
                'customer_id' => $customerId,
                'confirmation_mode' => $confirmationMode->value,
            ]);

            return $appointment;
        });
    }
}
