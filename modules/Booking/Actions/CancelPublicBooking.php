<?php

namespace Modules\Booking\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Booking\Models\BookingSettings;
use Modules\Scheduling\Contracts\AppointmentScheduler;
use Modules\Scheduling\Domain\AppointmentStatus;
use Modules\Scheduling\Domain\AppointmentSummary;

/**
 * The customer-facing half of §12's cancellation window — the setting
 * `BookingSettings.cancellation_window_hours` has existed since the booking-rules screen shipped,
 * stored and displayed but never enforced anywhere (no self-service endpoint existed to enforce
 * it against). This is that endpoint's business rule.
 *
 * Deliberately not a second state machine: `eligibility()` only decides whether *this* caller, a
 * stranger with a link, may ask for a cancellation right now. The actual transition is still
 * `Scheduling\Actions\UpdateAppointmentStatus`'s own state machine (reached through
 * `AppointmentScheduler::updateStatus()`), which still refuses a checked-in/in-service/completed
 * appointment on its own terms — this class's window check only narrows *when*, never *whether*,
 * same split `SubmitPublicBooking` already draws for lead time.
 */
final class CancelPublicBooking
{
    public function __construct(private readonly AppointmentScheduler $scheduler) {}

    public function execute(int $appointmentId): AppointmentSummary
    {
        $appointment = $this->scheduler->find($appointmentId);

        if ($appointment === null) {
            throw ValidationException::withMessages([
                'appointment' => 'This appointment could not be found.',
            ]);
        }

        $eligibility = $this->eligibility($appointment);

        if (! $eligibility['eligible']) {
            throw ValidationException::withMessages([
                'appointment' => $eligibility['reason'],
            ]);
        }

        return $this->scheduler->updateStatus(
            $appointmentId,
            AppointmentStatus::Cancelled,
            'Cancelled by the customer via the self-service link.',
        );
    }

    /**
     * @return array{eligible: bool, reason: ?string}
     */
    public function eligibility(AppointmentSummary $appointment): array
    {
        if ($appointment->status === AppointmentStatus::Cancelled) {
            return ['eligible' => false, 'reason' => 'This appointment has already been cancelled.'];
        }

        if ($appointment->status->isTerminal()) {
            return [
                'eligible' => false,
                'reason' => "This appointment is already {$appointment->status->label()} and can no longer be cancelled online.",
            ];
        }

        if (! $appointment->status->canTransitionTo(AppointmentStatus::Cancelled)) {
            // Checked in / in service: physically underway, the same reasoning
            // AppointmentStatus's own docblock gives for excluding these from cancellation.
            return [
                'eligible' => false,
                'reason' => 'This appointment is already underway and can no longer be cancelled online. Please contact the business directly.',
            ];
        }

        $windowHours = BookingSettings::query()->first()?->cancellation_window_hours ?? 24;
        $cutoff = Carbon::instance($appointment->startsAt)->subHours($windowHours);

        if (now()->greaterThanOrEqualTo($cutoff)) {
            return [
                'eligible' => false,
                'reason' => $windowHours > 0
                    ? "This appointment starts too soon to cancel online (within {$windowHours} hours of the start time). Please contact the business directly."
                    : 'This appointment has already started. Please contact the business directly.',
            ];
        }

        return ['eligible' => true, 'reason' => null];
    }
}
