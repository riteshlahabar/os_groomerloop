<?php

namespace Modules\Team\Http\Requests;

use App\Domain\DayOfWeek;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A groomer's normal week (spec §23 "working hours").
 *
 * The whole rota is sent every time, because the action replaces rather than merges. An empty array is
 * valid and means "no shifts" — which is how a salon takes someone off the rota without their leaving,
 * and which makes them available at no time at all.
 *
 * Up to 21 shifts: three per day is already a generous reading of a split shift, and a cap stops a
 * malformed client writing thousands of rows the scheduler then has to read per slot.
 */
final class SetWorkingHoursRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shifts' => ['present', 'array', 'max:21'],
            'shifts.*.day_of_week' => ['required', Rule::in(DayOfWeek::values())],

            // H:i, so "09:00" is accepted and "9am" is not. Wall-clock in the business's own timezone.
            'shifts.*.starts_at' => ['required', 'date_format:H:i'],
            'shifts.*.ends_at' => ['required', 'date_format:H:i', 'after:shifts.*.starts_at'],
        ];
    }

    /**
     * Overlap between two shifts on the same day is checked in the action rather than here, because it
     * is a domain rule about the set as a whole and the action is also the path any future import
     * would take.
     *
     * @return list<array{day_of_week: int, starts_at: string, ends_at: string}>
     */
    public function shifts(): array
    {
        return array_values(array_map(
            static fn (array $shift): array => [
                'day_of_week' => (int) $shift['day_of_week'],
                'starts_at' => (string) $shift['starts_at'],
                'ends_at' => (string) $shift['ends_at'],
            ],
            (array) $this->validated()['shifts'],
        ));
    }
}
