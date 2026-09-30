<?php

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Crm\Domain\CustomerSource;
use Modules\Crm\Domain\CustomerStatus;

final class UpdateCustomerRequest extends FormRequest
{
    /**
     * `sometimes` throughout, so a partial update leaves untouched fields alone rather than
     * nulling them — the same reasoning as the business profile in §7.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],

            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],

            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', 'string', 'max:64'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:16'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],

            'status' => ['sometimes', Rule::enum(CustomerStatus::class)],
            'source' => ['sometimes', 'nullable', Rule::enum(CustomerSource::class)],

            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],

            'tags' => ['sometimes', 'nullable', 'array', 'max:25'],
            'tags.*' => ['string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('country') && is_string($this->input('country'))) {
            $this->merge(['country' => strtoupper($this->string('country')->toString())]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function customerAttributes(): array
    {
        return $this->safe()->except('tags');
    }

    /**
     * Null means "leave tags alone"; an empty array means "remove them all". Collapsing the
     * two would make it impossible to clear a customer's tags.
     *
     * @return list<string>|null
     */
    public function tagNames(): ?array
    {
        return $this->has('tags') ? array_values((array) $this->input('tags', [])) : null;
    }
}
