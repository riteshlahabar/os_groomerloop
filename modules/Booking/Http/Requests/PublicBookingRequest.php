<?php

namespace Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Pets\Domain\PetSex;

/**
 * Shape-only validation — whether the service/staff ids exist, are sellable/assignable, and the
 * slot is actually available is `SubmitPublicBooking`'s job, checked through each owning module's
 * contract, the same split every authenticated action in this codebase already uses.
 *
 * No add-ons, no recurrence: spec §12's own 7-step flow (select service, date/time, groomer,
 * customer/pet info, review policies, confirm, receive confirmation) names neither, so this stays
 * narrower than the authenticated `BookAppointmentRequest` on purpose.
 *
 * **Two shapes, one endpoint.** The same POST serves a stranger who types their details and a
 * Customer Portal customer (`D-043`) who is already signed in, because spec §12 is one flow and
 * the owner asked for one screen. Who is booking is read from the `customer` guard's session, never
 * from the body — see `signedInCustomerId()`.
 */
final class PublicBookingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Contact fields identify a stranger. For a signed-in customer the identity is the
        // session's, so the page sends none of them and requiring them would reject the very
        // request it is meant to simplify. They stay `nullable` rather than `prohibited` so an
        // older cached copy of the page, which still posts them, is not broken by this change —
        // the action ignores them for a signed-in customer either way.
        $identity = $this->signedInCustomerId() === null ? 'required' : 'nullable';

        return [
            'service_id' => ['required', 'integer', 'min:1'],
            'staff_member_id' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],

            'first_name' => [$identity, 'string', 'max:255'],
            'last_name' => [$identity, 'string', 'max:255'],
            'email' => [$identity, 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],

            // Optional: lets a stranger booking for the first time set their Customer Portal
            // password (D-043) in the same step, rather than waiting on a separate claim-link
            // email. Omitted entirely, both rules are skipped — a returning customer who leaves
            // these blank keeps whatever password they already set.
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'password_confirmation' => ['nullable', 'string'],

            // Either pick one of your own pets or describe a new one. `pet_id` is shape-only
            // here, like `service_id`: that it exists AND belongs to the booking customer is
            // checked in `SubmitPublicBooking`, which is also where it is refused outright for a
            // caller with no customer session — knowing an email is not permission to book
            // against that household's pets.
            'pet_id' => ['nullable', 'integer', 'min:1'],
            'pet_name' => ['required_without:pet_id', 'string', 'max:255'],
            'pet_species_id' => ['required_without:pet_id', 'integer', 'min:1'],
            'pet_breed' => ['nullable', 'string', 'max:255'],
            'pet_sex' => ['nullable', Rule::enum(PetSex::class)],

            // Not stored as its own column this phase (see D-024) — required here so a booking
            // cannot be submitted without the step the spec names existing in the flow at all.
            'policies_accepted' => ['required', 'accepted'],
        ];
    }

    /**
     * The signed-in Customer Portal customer, or null for the anonymous public flow.
     *
     * Read through the `customer` guard, whose provider is Crm's tenant-scoped `Customer` model,
     * and resolved only after this route's `ResolvePublicTenant` has run (route middleware
     * precedes controller/FormRequest resolution). That ordering is what makes this safe across
     * tenants without a check of its own: a customer signed in to tenant A hitting tenant B's
     * booking URL is looked up under B's tenant scope, is not found, and is therefore treated as
     * an anonymous stranger rather than as themselves.
     */
    public function signedInCustomerId(): ?int
    {
        $customer = $this->user('customer');

        return $customer === null ? null : (int) $customer->getAuthIdentifier();
    }

    /**
     * @return array<string, mixed>
     */
    public function bookingAttributes(): array
    {
        return $this->safe()->except(['policies_accepted', 'password_confirmation']);
    }
}
