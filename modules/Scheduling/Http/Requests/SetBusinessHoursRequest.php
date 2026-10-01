<?php

namespace Modules\Scheduling\Http\Requests;

use App\Domain\DayOfWeek;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The whole week is sent every time; the action replaces rather than merges. An empty array is
 * valid and means "closed every day" — the same `SetWorkingHoursRequest` shape, same reasoning.
 */
final class SetBusinessHoursRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'windows' => ['present', 'array', 'max:21'],
            'windows.*.day_of_week' => ['required', Rule::in(DayOfWeek::values())],
            'windows.*.starts_at' => ['required', 'date_format:H:i'],
            'windows.*.ends_at' => ['required', 'date_format:H:i', 'after:windows.*.starts_at'],
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
