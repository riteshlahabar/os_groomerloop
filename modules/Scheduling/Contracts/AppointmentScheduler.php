<?php

namespace Modules\Scheduling\Contracts;

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
     * Business hours ∩ the service's own rules ∩ staff availability ∩ no conflicting
     * appointment — the composed answer, server-side, the way invariant #2 requires.
     *
     * $staffMemberId is nullable: "is this service bookable at all at this time, by anyone" is
     * also a real question (the future public booking page's "no preference" option).
     */
    public function isSlotAvailable(int $serviceId, ?int $staffMemberId, DateTimeInterface $start): bool;

    /**
     * @param  array<string, mixed>  $attributes  customer_id, pet_id, service_id, staff_member_id
     *                                            (nullable), starts_at, customer_notes, internal_notes, add_on_service_ids (optional)
     */
    public function book(array $attributes): AppointmentSummary;

    public function reschedule(int $appointmentId, DateTimeInterface $start): AppointmentSummary;

    public function cancel(int $appointmentId): AppointmentSummary;

    public function updateStatus(int $appointmentId, AppointmentStatus $status, ?string $note = null): AppointmentSummary;
}
