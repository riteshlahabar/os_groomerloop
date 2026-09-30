<?php

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Crm\Domain\CustomerSource;
use Modules\Crm\Domain\CustomerStatus;

final class StoreCustomerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The only required field. A salon takes a name and a phone number over the
            // counter and fills the rest in later; demanding an email would have staff
            // typing fake ones, which is worse than having none.
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],

            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],

            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:64'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'country' => ['nullable', 'string', 'size:2'],

            'status' => ['nullable', Rule::enum(CustomerStatus::class)],
            'source' => ['nullable', Rule::enum(CustomerSource::class)],

            'notes' => ['nullable', 'string', 'max:5000'],

            'tags' => ['nullable', 'array', 'max:25'],
            'tags.*' => ['string', 'max:64'],

            // Consent is deliberately absent. It is not fillable on the model and moves only
            // through RecordConsent (invariant #9), so accepting it here would either be
            // ignored or would quietly become a second, unaudited way to change it.
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
     * @return list<string>|null
     */
    public function tagNames(): ?array
    {
        return $this->has('tags') ? array_values((array) $this->input('tags', [])) : null;
    }
}
