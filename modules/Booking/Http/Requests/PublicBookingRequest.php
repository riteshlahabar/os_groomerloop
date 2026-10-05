<?php

namespace Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Pets\Domain\PetSex;

/**
 * Shape-only validation — whether the service/staff ids exist, are sellable/assignable, and the
 * slot is actually available is `SubmitPublicBooking`'s job, checked through each owning module's
 * contract, the same split every authenticated action in this codebase already uses.
 *
 * No add-ons, no recurrence: spec §12's own 7-step flow (select service, date/time, groomer,
 * customer/pet info, review policies, confirm, receive confirmation) names neither, so this stays
 * narrower than the authenticated `BookAppointmentRequest` on purpose.
 */
final class PublicBookingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'min:1'],
            'staff_member_id' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],

            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],

            'pet_name' => ['required', 'string', 'max:255'],
            'pet_species_id' => ['required', 'integer', 'min:1'],
            'pet_breed' => ['nullable', 'string', 'max:255'],
            'pet_sex' => ['nullable', Rule::enum(PetSex::class)],

            // Not stored as its own column this phase (see D-024) — required here so a booking
            // cannot be submitted without the step the spec names existing in the flow at all.
            'policies_accepted' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bookingAttributes(): array
    {
        return $this->safe()->except('policies_accepted');
    }
}
