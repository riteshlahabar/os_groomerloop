<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Models\Appointment;

/**
 * Book a series (spec §11 "recurring appointments"), kept deliberately simple: a fixed cadence
 * and a fixed number of occurrences, generated as real, independently-editable rows up front —
 * no recurrence-rule table, no background regeneration.
 *
 * Partial success, not all-or-nothing: a distant occurrence colliding with something nine weeks
 * out must not block the customer's next two months of appointments over one far-future clash.
 * Each occurrence is booked independently through `BookAppointment`, which still re-validates
 * and locks exactly as a one-off booking would.
 */
final class BookRecurringAppointments
{
    private const MAX_OCCURRENCES = 52;

    public function __construct(private readonly BookAppointment $bookAppointment) {}

    /**
     * @param  array<string, mixed>  $attributes  same shape BookAppointment::execute() takes,
     *     minus starts_at which is read from $firstStart instead
     * @return array{booked: list<Appointment>, skipped: list<array{starts_at: string, reason: string}>}
     */
    public function execute(array $attributes, Carbon $firstStart, int $intervalWeeks, int $occurrences): array
    {
        if ($occurrences < 1 || $occurrences > self::MAX_OCCURRENCES) {
            throw ValidationException::withMessages([
                'occurrences' => 'Occurrences must be between 1 and '.self::MAX_OCCURRENCES.'.',
            ]);
        }

        $booked = [];
        $skipped = [];
        $recurrenceGroupId = null;

        for ($i = 0; $i < $occurrences; $i++) {
            $start = $firstStart->copy()->addWeeks($i * $intervalWeeks);

            try {
                $appointment = $this->bookAppointment->execute(
                    [...$attributes, 'starts_at' => $start],
                    $recurrenceGroupId,
                );

                // The first occurrence can't know the series' group id until it has one — it
                // is its own group id, written back once, immediately after it is created.
                if ($recurrenceGroupId === null && $occurrences > 1) {
                    $recurrenceGroupId = $appointment->getKey();
                    $appointment->update(['recurrence_group_id' => $recurrenceGroupId]);
                }

                $booked[] = $appointment;
            } catch (ValidationException $e) {
                $skipped[] = [
                    'starts_at' => $start->toIso8601String(),
                    'reason' => implode(' ', $e->validator->errors()->all()),
                ];
            }
        }

        return ['booked' => $booked, 'skipped' => $skipped];
    }
}
