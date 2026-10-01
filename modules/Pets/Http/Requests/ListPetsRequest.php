<?php

namespace Modules\Pets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Pets\Domain\CoatType;
use Modules\Pets\Domain\PetSpecies;
use Modules\Pets\Domain\PetStatus;
use Modules\Pets\Services\PetIndex;

/**
 * Validates the §9 pet list parameters.
 *
 * A Form Request for a GET is unusual, but this is where `sort` is constrained to the whitelist —
 * an unvalidated sort column reaching orderBy is the one place Eloquent will interpolate user
 * input into SQL.
 */
final class ListPetsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],

            // Not validated for existence: an unknown or another business's id simply matches no
            // pets, which is the right answer and tells the caller nothing.
            'customer_id' => ['nullable', 'integer', 'min:1'],

            'species' => ['nullable', 'array'],
            'species.*' => [Rule::enum(PetSpecies::class)],

            'coat_type' => ['nullable', 'array'],
            'coat_type.*' => [Rule::enum(CoatType::class)],

            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(PetStatus::class)],

            'include_inactive' => ['nullable', 'boolean'],
            'needs_handling_care' => ['nullable', 'boolean'],

            'sort' => ['nullable', Rule::in(PetIndex::sortableFields())],
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

        foreach (['include_inactive', 'needs_handling_care'] as $flag) {
            if (array_key_exists($flag, $filters)) {
                $filters[$flag] = $this->boolean($flag);
            }
        }

        return $filters;
    }
}
