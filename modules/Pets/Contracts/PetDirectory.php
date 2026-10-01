<?php

namespace Modules\Pets\Contracts;

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
     * @param  array<string, mixed>  $attributes  name, species (required); breed, sex optional
     */
    public function createForPublicBooking(int $customerId, array $attributes): int;
}
