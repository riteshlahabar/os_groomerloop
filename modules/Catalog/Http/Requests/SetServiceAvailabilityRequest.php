<?php

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Domain\DayOfWeek;

/**
 * The per-service availability rules of spec §10.
 *
 * The whole set is sent every time, because the action replaces rather than merges: the windows are
 * one statement about when a service is sold. An empty array is valid and means "no restriction".
 */
final class SetServiceAvailabilityRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'windows' => ['present', 'array', 'max:7'],
            'windows.*.day_of_week' => ['required', Rule::in(DayOfWeek::values())],

            // H:i, so "09:00" is accepted and "9am" is not. The times are wall-clock in the
            // business's own timezone — "Saturdays until noon" is a statement about the shop's
            // clock and must not move when the clocks change.
            'windows.*.starts_at' => ['required', 'date_format:H:i'],
            'windows.*.ends_at' => ['required', 'date_format:H:i', 'after:windows.*.starts_at'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $days = [];

                foreach ((array) $this->input('windows', []) as $index => $window) {
                    $day = $window['day_of_week'] ?? null;

                    // One window per day. A salon meaning "mornings and late afternoon but not
                    // lunchtime" is describing staff availability, which is §23's problem — letting
                    // the catalogue express it would grow a second, competing scheduling system.
                    if ($day !== null && in_array($day, $days, strict: false)) {
                        $validator->errors()->add(
                            "windows.{$index}.day_of_week",
                            'Give one window per day.'
                        );
                    }

                    $days[] = $day;
                }
            },
        ];
    }

    /**
     * @return list<array{day_of_week: int, starts_at: string, ends_at: string}>
     */
    public function windows(): array
    {
        return array_values(array_map(
            static fn (array $window): array => [
                'day_of_week' => (int) $window['day_of_week'],
                'starts_at' => (string) $window['starts_at'],
                'ends_at' => (string) $window['ends_at'],
            ],
            (array) $this->validated()['windows'],
        ));
    }
}
