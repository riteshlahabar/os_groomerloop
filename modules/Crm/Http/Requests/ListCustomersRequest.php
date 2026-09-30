<?php

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Crm\Domain\CustomerSource;
use Modules\Crm\Domain\CustomerStatus;
use Modules\Crm\Services\CustomerIndex;

/**
 * Validates the §8 search/filter/sort parameters.
 *
 * A Form Request for a GET is unusual, but this is where `sort` is constrained to the
 * whitelist — an unvalidated sort column reaching orderBy is the one place Eloquent will
 * happily interpolate user input into SQL.
 */
final class ListCustomersRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],

            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(CustomerStatus::class)],

            'source' => ['nullable', 'array'],
            'source.*' => [Rule::enum(CustomerSource::class)],

            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:64'],

            'opted_out' => ['nullable', 'boolean'],
            'include_archived' => ['nullable', 'boolean'],

            'sort' => ['nullable', Rule::in(CustomerIndex::sortableFields())],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],

            // Capped again in CustomerIndex. Validating it here gives a clear 422 instead of
            // silently ignoring an out-of-range value the caller believed was applied.
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = $this->safe()->all();

        foreach (['opted_out', 'include_archived'] as $flag) {
            if (array_key_exists($flag, $filters)) {
                $filters[$flag] = $this->boolean($flag);
            }
        }

        return $filters;
    }
}
