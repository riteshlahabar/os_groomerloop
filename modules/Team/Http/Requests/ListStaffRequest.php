<?php

namespace Modules\Team\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Team\Domain\StaffStatus;
use Modules\Team\Services\StaffIndex;

/**
 * Validates the §23 team list parameters.
 *
 * A Form Request for a GET, because this is where `sort` is constrained to the whitelist — an
 * unvalidated sort column reaching orderBy is the one place Eloquent will interpolate user input
 * into SQL.
 */
final class ListStaffRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],

            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(StaffStatus::class)],

            'bookable_online' => ['nullable', 'boolean'],
            'without_working_hours' => ['nullable', 'boolean'],
            'has_login' => ['nullable', 'boolean'],
            'include_inactive' => ['nullable', 'boolean'],

            'sort' => ['nullable', Rule::in(StaffIndex::sortableFields())],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],

            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = $this->safe()->all();

        foreach (['bookable_online', 'without_working_hours', 'include_inactive'] as $flag) {
            if (array_key_exists($flag, $filters)) {
                $filters[$flag] = $this->boolean($flag);
            }
        }

        // Three states rather than two: absent leaves every staff member in the list regardless
        // of whether they have a login, which is what the default team view wants.
        if ($this->has('has_login')) {
            $filters['has_login'] = $this->boolean('has_login');
        }

        return $filters;
    }
}
