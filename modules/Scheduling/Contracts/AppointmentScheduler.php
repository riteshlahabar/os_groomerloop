<?php

namespace Modules\Scheduling\Contracts;

use DateTimeImmutable;
use DateTimeInterface;
use Modules\Scheduling\Domain\AppointmentStatus;
use Modules\Scheduling\Domain\AppointmentSummary;

/**
 * The seam the future public booking phase (spec §12) will build on — Scheduling owns the
 * appointment engine; Booking is a later, public-facing entry point onto the same one, never a
 * parallel implementation (see the §11/§12 boundary note in `D-023`).
 *
 * Unlike `ServiceCatalog`/`StaffDirectory` (pure read directories, because no other module
 * creates a service or a staff member on another module's behalf), this contract also exposes
 * the write operations a future caller needs — creating and managing an appointment is the
 * entire reason Scheduling exists for a consumer to reach it at all.
 */
interface AppointmentScheduler
{
    public function exists(int $appointmentId): bool;

    public function find(int $appointmentId): ?AppointmentSummary;

    /**
     * Every slot-occupying appointment for one staff member in a window — the query the
     * calendar's day/week/month views and the future booking widget both need.
     *
     * @return list<AppointmentSummary>
     */
    public function appointmentsFor(int $staffMemberId, DateTimeInterface $from, DateTimeInterface $to): array;

    /**
     * Every slot-occupying appointment in the current tenant starting in a window, regardless of
     * staff member — the query a tenant-wide sweep (a reminder cron, spec §13) needs and a single
     * groomer's calendar does not. Reads the ambient `TenantContext`, so a caller sweeping every
     * business runs this once per tenant inside `TenantContext::runFor()`, the same pattern
     * `ExpireLapsedSubscriptions` already established for Billing's own clock-driven sweep.
     *
     * @return list<AppointmentSummary>
     */
    public function startingBetween(DateTimeInterface $from, DateTimeInterface $to): array;

    /**
     * Business hours ∩ the service's own rules ∩ staff availability ∩ no conflicting
     * appointment — the composed answer, server-side, the way invariant #2 requires.
     *
     * $staffMemberId is nullable: "is this service bookable at all at this time, by anyone" is
     * also a real question (the future public booking page's "no preference" option).
     */
    public function isSlotAvailable(int $serviceId, ?int $staffMemberId, DateTimeInterface $start): bool;

    /**
     * Every bookable start time on one calendar day for a service (optionally narrowed to one
     * staff member) — the same composed check `isSlotAvailable` answers one candidate at a time,
     * asked across a whole day so a caller never has to probe it slot by slot to draw one. Spec
     * §12's booking page needs this to show a day of open times; the authenticated calendar's own
     * "pick a time" step has the identical need and can reuse it through this same contract.
     *
     * Business-hours and service/staff-availability only (invariant #2's composed answer) — a
     * caller-specific notion of "too soon to book" (§12's lead time) is not this contract's
     * concern and is applied by whoever asks, the same split `SubmitPublicBooking` already uses
     * for lead time against a single slot.
     *
     * @return list<DateTimeImmutable>
     */
    public function openSlotsFor(int $serviceId, ?int $staffMemberId, DateTimeInterface $date): array;

    /**
     * @param  array<string, mixed>  $attributes  customer_id, pet_id, service_id, staff_member_id
     *                                            (nullable), starts_at, customer_notes, internal_notes, add_on_service_ids (optional)
     */
    public function book(array $attributes): AppointmentSummary;

    public function reschedule(int $appointmentId, DateTimeInterface $start): AppointmentSummary;

    public function cancel(int $appointmentId): AppointmentSummary;

    public function updateStatus(int $appointmentId, AppointmentStatus $status, ?string $note = null): AppointmentSummary;

    /**
     * Every appointment ever booked for one customer, newest first — the Customer Portal's own
     * history view (`D-043`), built the same contracts-only way the public booking page and the
     * admin calendar already are: Customer Portal never reaches this module's `Appointment`
     * model directly.
     *
     * @return list<AppointmentSummary>
     */
    public function appointmentsForCustomer(int $customerId): array;
}
