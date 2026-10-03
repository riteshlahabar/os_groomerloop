<?php

namespace Modules\Team\Contracts;

use DateTimeInterface;
use Modules\Team\Domain\StaffSummary;

/**
 * How other modules read the team (D-007).
 *
 * Scheduling (§11) needs to know who may be put on an appointment, whether they can do the service,
 * and whether they are at work at that moment; Booking (§12) needs the same checks on a public,
 * unauthenticated request, plus the narrower list of groomers a customer may choose; Insights (§16)
 * reports by groomer. None of them may load the StaffMember model.
 *
 * `isAvailableAt` answers only the staff half of availability. Business hours belong to §11 and the
 * service's own rules to §10; the booking engine combines all three, and each module answers for
 * what it owns.
 */
interface StaffDirectory
{
    public function exists(int $staffMemberId): bool;

    public function find(int $staffMemberId): ?StaffSummary;

    /**
     * May this person be put on a new appointment at all?
     *
     * False for a leaver, so a stale calendar in an open browser tab cannot assign work to someone
     * who no longer works here.
     */
    public function isAssignable(int $staffMemberId): bool;

    /**
     * Is this groomer able to perform this service (spec §10, §23)?
     *
     * True when the staff member has no service restrictions recorded at all — the common case, and
     * the reason a solo groomer does not have to tick every service against their own name before the
     * business can take a booking. False for an unknown or inactive staff member.
     */
    public function canPerform(int $staffMemberId, int $serviceId): bool;

    /**
     * Is this groomer at work, and not on holiday, for a booking starting at $start and running
     * $minutes — buffer included?
     *
     * False when they have no rota at all. That default is the opposite of a service's, deliberately:
     * a person with no shifts is not at work, and defaulting the other way would offer a groomer who
     * has never been given one.
     */
    public function isAvailableAt(int $staffMemberId, DateTimeInterface $start, int $minutes): bool;

    /**
     * Everyone who may be assigned work, for a calendar's groomer picker.
     *
     * @return list<StaffSummary>
     */
    public function assignable(): array;

    /**
     * The narrower list a customer may choose from on the §12 booking page: active, published, on a
     * rota, and — when a service is named — able to perform it.
     *
     * "On a rota" is part of the definition rather than the caller's problem: a staff member with no
     * working hours fails {@see self::isAvailableAt()} for every slot on every day, so offering them
     * as a choice can only ever end in the booking page reporting no availability — and reporting it
     * against the business rather than against the person.
     *
     * @return list<StaffSummary>
     */
    public function bookableOnline(?int $serviceId = null): array;

    /**
     * Names for many staff at once, keyed by id, with null for any this tenant cannot see.
     *
     * Exists so a calendar can label a week of appointments without a query per row — the N+1 that
     * `Model::shouldBeStrict()` cannot catch, because it is not a relationship.
     *
     * @param  list<int>  $staffMemberIds
     * @return array<int, string|null>
     */
    public function namesOf(array $staffMemberIds): array;

    /**
     * The staff record belonging to a login, if there is one. Lets §11 show a groomer their own
     * calendar without Scheduling knowing how staff and users are linked.
     */
    public function findByUser(int $userId): ?StaffSummary;

    /**
     * Does this business have anyone on the team? Used by the §7 onboarding checklist.
     */
    public function hasAny(): bool;

    /**
     * Take a row lock on this staff member for the duration of the caller's open transaction.
     *
     * Scheduling's booking/reschedule/reassign actions serialise concurrent attempts against the
     * same groomer by locking their row before re-checking availability (`D-022`) — `SELECT ...
     * FOR UPDATE` only works as a gate when every concurrent writer takes the same lock before it
     * reads, so this must run on the model Team actually owns. A no-op for an unknown staff
     * member: there is nothing to serialise against, and the caller's own existence check is
     * what refuses the request.
     */
    public function lockForBooking(int $staffMemberId): void;
}
