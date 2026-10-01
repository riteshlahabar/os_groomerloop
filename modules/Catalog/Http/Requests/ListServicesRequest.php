<?php

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Domain\ServiceStatus;
use Modules\Catalog\Services\ServiceIndex;

/**
 * Validates the §10 service list parameters.
 *
 * A Form Request for a GET, because this is where `sort` is constrained to the whitelist — an
 * unvalidated sort column reaching orderBy is the one place Eloquent will interpolate user input
 * into SQL.
 */
final class ListServicesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],

            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(ServiceStatus::class)],

            'category_id' => ['nullable', 'integer', 'min:1'],

            'is_add_on' => ['nullable', 'boolean'],
            'bookable_online' => ['nullable', 'boolean'],
            'include_inactive' => ['nullable', 'boolean'],

            'sort' => ['nullable', Rule::in(ServiceIndex::sortableFields())],
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

        foreach (['bookable_online', 'include_inactive'] as $flag) {
            if (array_key_exists($flag, $filters)) {
                $filters[$flag] = $this->boolean($flag);
            }
        }

        // Three states rather than two: absent leaves add-ons and services mixed, which is what a
        // search across the whole menu wants.
        if ($this->has('is_add_on')) {
            $filters['is_add_on'] = $this->boolean('is_add_on');
        }

        return $filters;
    }
}
