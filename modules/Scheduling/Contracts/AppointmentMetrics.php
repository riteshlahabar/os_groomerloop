<?php

namespace Modules\Scheduling\Contracts;

use DateTimeInterface;

/**
 * Aggregate reads over the appointment book, for Insights (spec §16) and Automation (spec §18) —
 * the two modules that report or act on the book in bulk rather than one slot at a time (D-007).
 *
 * `AppointmentScheduler` answers "what can I book" one slot at a time; this contract answers
 * "what happened", in bulk, over a range — a different shape of question that would otherwise
 * tempt a caller into loading the Appointment model itself. Every method states which column it
 * ranges over (`starts_at` for "when did this happen", `created_at` for "when was this booked")
 * because the two give different and both legitimate answers to "how many appointments in
 * October" — invariant #7 requires the formula be stated, not just the number.
 *
 * Every range is half-open: `$from` inclusive, `$to` exclusive, matching how the rest of the
 * calendar (`Appointment::scopeOverlapping()`) already treats a window.
 */
interface AppointmentMetrics
{
    /**
     * Appointments starting in the window, by status. Powers "today's/upcoming appointments".
     *
     * @return array{total: int, by_status: array<string, int>}
     */
    public function countsForWindow(DateTimeInterface $from, DateTimeInterface $to): array;

    /**
     * Appointments *booked* in the window, regardless of when they are for — `created_at`, not
     * `starts_at`. This is "new bookings", distinct from appointment volume.
     */
    public function newBookingsBetween(DateTimeInterface $from, DateTimeInterface $to): int;

    /**
     * Appointments that were due to start in the window and ended up cancelled or a no-show.
     *
     * @return array{cancelled: int, no_show: int}
     */
    public function cancellationsAndNoShowsBetween(DateTimeInterface $from, DateTimeInterface $to): array;

    /**
     * Appointments that still occupy a slot (status-wise), one count per calendar day they start
     * on. Cancelled/no-show excluded — a slot that was never kept is not volume.
     *
     * @return array<string, int> "Y-m-d" => count
     */
    public function volumeByDay(DateTimeInterface $from, DateTimeInterface $to): array;

    /**
     * Completed appointments in the window, grouped by the service performed. The basis for both
     * "service popularity" and the estimated-value metric, which multiplies this by the service's
     * own listed price rather than Insights holding a second copy of it.
     *
     * @return array<int, int> service_id => completed count
     */
    public function completedCountByService(DateTimeInterface $from, DateTimeInterface $to): array;

    /**
     * Minutes actually occupied by appointments in the window, grouped by assigned staff.
     * Unassigned appointments are omitted — there is no groomer to credit the time to.
     *
     * @return array<int, int> staff_member_id => minutes
     */
    public function occupiedMinutesByStaff(DateTimeInterface $from, DateTimeInterface $to): array;

    /**
     * How many distinct customers booked in the window *and* already had an appointment starting
     * before `$from`. A customer who is new this window cannot be "returning" in it.
     */
    public function returningCustomerCount(DateTimeInterface $from, DateTimeInterface $to): int;

    /**
     * Of the appointments completed in the window, how many of those customers went on to book
     * another appointment (any status) starting within `$withinDays` of the completed one ending?
     *
     * @return array{completed: int, rebooked: int}
     */
    public function rebookingRate(DateTimeInterface $from, DateTimeInterface $to, int $withinDays): array;

    /**
     * Customers whose most recent slot-occupying appointment ended more than `$inactivityDays`
     * before `$asOf`, and who have nothing upcoming. A customer who has never booked at all is not
     * counted — they are not lapsed, they never started.
     */
    public function staleCustomerCount(DateTimeInterface $asOf, int $inactivityDays): int;

    /**
     * The same customers {@see self::staleCustomerCount()} counts, as ids rather than a number —
     * Automation's retention sweep needs to know *who*, not just how many.
     *
     * @return list<int>
     */
    public function staleCustomerIds(DateTimeInterface $asOf, int $inactivityDays): array;

    /**
     * Completed appointments whose `ends_at` falls at least `$minDaysAgo` days before `$asOf` —
     * the candidate pool for Automation's rebooking-reminder and review-request sweeps, which
     * differ only in whether a rebooking since disqualifies a candidate (checked separately via
     * {@see self::hasBookedSince()}, so this one query serves both).
     *
     * @return list<array{appointment_id: int, customer_id: int, service_id: int, ends_at: string}>
     */
    public function completedAppointmentsOlderThan(DateTimeInterface $asOf, int $minDaysAgo): array;

    /**
     * No-show appointments whose `starts_at` falls at least `$minDaysAgo` days before `$asOf` —
     * the candidate pool for Automation's no-show follow-up sweep.
     *
     * @return list<array{appointment_id: int, customer_id: int, service_id: int, starts_at: string}>
     */
    public function noShowAppointmentsOlderThan(DateTimeInterface $asOf, int $minDaysAgo): array;

    /**
     * Has this customer got any appointment (any status) starting after `$since`? Used to decide
     * whether a completed visit still needs a rebooking reminder, or the customer already acted.
     */
    public function hasBookedSince(int $customerId, DateTimeInterface $since): bool;
}
