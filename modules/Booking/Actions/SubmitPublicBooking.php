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
     *                                            password (all optional for a signed-in customer — see
     *                                            $signedInCustomerId); pet: either pet_id (one of this customer's
     *                                            existing pets) or pet_name + pet_species_id with pet_breed/pet_sex
     *                                            optional; booking: service_id, staff_member_id (optional),
     *                                            starts_at, customer_notes (optional)
     * @param  int|null  $signedInCustomerId  the Customer Portal customer making this booking (`D-043`), from the
     *                                        request's own session — never from the body. Given, it *is* the
     *                                        customer: the posted contact fields are ignored rather than used to
     *                                        look anybody up, so a signed-in customer cannot book as someone else
     *                                        by editing an email, and it is the only thing that unlocks `pet_id`.
     */
    public function execute(array $attributes, ?int $signedInCustomerId = null): AppointmentSummary
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

        $existingPetId = isset($attributes['pet_id']) ? (int) $attributes['pet_id'] : null;

        // Picking an existing pet is a Customer-Portal-only capability, and this is the check
        // that makes it one. Ownership alone would not be enough: the anonymous flow resolves a
        // customer by *email*, so a stranger who knows an address would otherwise reach that
        // household's customer id and pass its ownership test. Requiring a real session means the
        // caller has proved they are that customer, not merely that they know of them.
        if ($existingPetId !== null && $signedInCustomerId === null) {
            throw ValidationException::withMessages([
                'pet_id' => 'Sign in to book for a pet already on file.',
            ]);
        }

        // species_id is shape-only in PublicBookingRequest (an integer) — this is where its
        // existence is actually checked, the same split that request's own docblock already
        // describes for service_id/staff_member_id. Nothing downstream would catch an unknown
        // one otherwise: unlike service_id, species is not part of the scheduling engine
        // `$this->scheduler->book()` validates. Skipped when an existing pet was chosen: it
        // already has a species, and the request does not ask for one.
        if ($existingPetId === null && ! $this->pets->speciesExists((int) $attributes['pet_species_id'])) {
            throw ValidationException::withMessages([
                'pet_species_id' => 'The selected species could not be found.',
            ]);
        }

        return DB::transaction(function () use ($attributes, $start, $confirmationMode, $signedInCustomerId, $existingPetId): AppointmentSummary {
            if ($signedInCustomerId !== null) {
                // Already identified, and already has a password — so neither the email lookup
                // nor the password set below applies. Both are the anonymous flow's way of
                // establishing an identity that does not exist yet.
                $customerId = $signedInCustomerId;
            } else {
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
            }

            if ($existingPetId !== null) {
                // The fix for a real defect, not just a convenience: `createForPublicBooking()`
                // always creates, so before this every repeat online booking for the same animal
                // added another `Bella` row — the duplicate that contract's own docblock accepts
                // for a stranger, because a typed name is not an identity. A signed-in customer
                // picking from their own list *is* an identity, so reuse is correct here and only
                // here.
                // `idsForCustomer()` rather than `belongsTo()`, deliberately: it is scoped to the
                // customer's **current** pets, so an archived or deceased pet is refused as well
                // as another household's. That matters because it is exactly the list the picker
                // offers — anything else arriving here did not come from the screen — and because
                // `belongsTo()` alone would accept a pet that has died.
                if (! in_array($existingPetId, $this->pets->idsForCustomer($customerId), true)) {
                    throw ValidationException::withMessages([
                        'pet_id' => 'That pet could not be found on your account.',
                    ]);
                }

                $petId = $existingPetId;
            } else {
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
            }

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
                // Which of the two shapes this was. Worth auditing because the signed-in one
                // trusts a session rather than a typed email for the customer's identity, and
                // because it is the only one that can attach an existing pet.
                'signed_in' => $signedInCustomerId !== null,
                'existing_pet' => $existingPetId !== null,
            ]);

            return $appointment;
        });
    }
}
