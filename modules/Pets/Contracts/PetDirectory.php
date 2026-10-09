<?php

namespace Modules\Pets\Contracts;

use Modules\Pets\Domain\PetSummary;

/**
 * How other modules look a pet up (D-007).
 *
 * Scheduling (§11) needs to know a pet exists and belongs to the customer being booked;
 * Booking (§12) needs the same check on a public, unauthenticated request; Notifications (§13)
 * and Retention (§22) need to know whether contacting anyone about this pet is appropriate at
 * all. None of them may load the Pet model.
 *
 * `belongsTo` is the important one. A booking request carries a customer id and a pet id, both
 * from the client, and nothing else stops someone booking their own appointment against another
 * family's dog — inside the same business, where the tenant scope offers no protection.
 */
interface PetDirectory
{
    public function exists(int $petId): bool;

    /**
     * Is this pet owned by this customer, in this tenant?
     *
     * False for an unknown pet or an unknown customer, so a caller that forgets to check
     * existence separately still cannot act on a mismatch.
     */
    public function belongsTo(int $petId, int $customerId): bool;

    /**
     * Display name for a calendar entry or a message ("Bella"), or null if the pet is not in the
     * current tenant.
     */
    public function nameOf(int $petId): ?string;

    /**
     * Names for many pets at once, keyed by pet id, with null for any the current tenant cannot
     * see — the same batch shape `CustomerDirectory::namesOf()`/`StaffDirectory::namesOf()`
     * already use. Exists for the same reason theirs do: a calendar page renders many
     * appointments, each naming a pet, and resolving that one row at a time is an N+1
     * `shouldBeStrict()` cannot see because it is not an Eloquent relationship.
     *
     * @param  list<int>  $petIds
     * @return array<int, string|null>
     */
    public function namesOf(array $petIds): array;

    /**
     * May the business be prompted to contact anyone about this pet?
     *
     * False for an archived pet and — the case this exists for — a deceased one. §22 sends
     * rebooking and win-back messages off the back of quiet periods, and a pet that has died is
     * permanently quiet.
     */
    public function allowsOutreach(int $petId): bool;

    /**
     * The ids of a customer's current pets, for a booking form or a calendar entry.
     *
     * @return list<int>
     */
    public function idsForCustomer(int $customerId): array;

    /**
     * Does this business have any pets on file? Used by the §7 onboarding checklist (`D-015`).
     */
    public function hasAny(): bool;

    /**
     * Add a pet to a customer created moments ago by a public booking (spec §12 step 4).
     *
     * Always creates: unlike the customer half, there is no identifying field a repeat online
     * booker types consistently enough to match a pet by (a name alone is not unique even within
     * one family), so a second booking for "Bella" makes a second `Bella` record rather than
     * guessing. §8's existing merge tooling is the place to reconcile that later, the same as any
     * other duplicate.
     *
     * @param  array<string, mixed>  $attributes  name, species_id (required); breed, sex optional
     */
    public function createForPublicBooking(int $customerId, array $attributes): int;

    /**
     * This tenant's species list, for the public booking page's picker — a tenant-owned list
     * (`Models\Species`, added 2026-10-05) rather than the fixed enum it replaced, so a public,
     * unauthenticated caller needs this to build the dropdown at all rather than hard-coding
     * Dog/Cat/Other.
     *
     * @return list<array{id: int, name: string}>
     */
    public function listSpecies(): array;

    /**
     * Does this species id belong to this tenant? `SubmitPublicBooking`'s existence check for
     * `pet_species_id`, the same split `PublicBookingRequest`'s own docblock already describes
     * for `service_id`/`staff_member_id` — shape only in the request, existence here.
     */
    public function speciesExists(int $speciesId): bool;

    /**
     * This customer's current pets, in the shape the Customer Portal's own "my pets" view needs
     * (`D-043`) — richer than `idsForCustomer()`/`namesOf()`, and deliberately built from a DTO
     * that has no `internal_notes` field at all rather than one a caller must remember to omit.
     *
     * @return list<PetSummary>
     */
    public function summariesForCustomer(int $customerId): array;

    /**
     * A customer adding one of their own pets from the Customer Portal (`D-043`).
     *
     * Distinct from `createForPublicBooking()` above, which exists for a stranger mid-booking and
     * takes only the three fields that flow asks for. This takes the §9 field set a pet profile
     * actually has — minus the two a customer may never write, which is the whole reason it is its
     * own method rather than a wider signature on that one:
     *
     *   * `internal_notes` — §9 gives staff-only notes their own permission, and `PetSummary` does
     *     not even carry the field, so a customer cannot read one either.
     *   * `status` — archiving a pet or recording it as deceased is the business's record-keeping
     *     decision, and `UpdatePet` raises a distinct audit event for the latter.
     *
     * `photo_path` is absent for a different reason: nothing writes it yet (§28 has no upload path
     * for pets — see `D-016`'s scope).
     *
     * @param  array<string, mixed>  $attributes  name, species_id (required); breed, sex,
     *                                            date_of_birth, approximate_age_years, weight_lb,
     *                                            coat_type, coat_notes, customer_notes,
     *                                            temperament_notes, special_instructions,
     *                                            medical_notes all optional
     */
    public function createForCustomer(int $customerId, array $attributes): int;

    /**
     * A customer editing one of their own pets from the portal (`D-043`).
     *
     * Takes the customer id as well as the pet id and **returns false rather than throwing** when
     * the pet is not theirs, so the caller cannot forget the ownership question: there is no way
     * to invoke this without naming whose pet it is meant to be. Same field set, and the same two
     * exclusions, as `createForCustomer()`.
     *
     * @param  array<string, mixed>  $attributes  any of the fields listed on `createForCustomer()`
     */
    public function updateForCustomer(int $petId, int $customerId, array $attributes): bool;
}
