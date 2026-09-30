<?php

namespace Modules\Onboarding\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateBusinessProfileRequest extends FormRequest
{
    /**
     * Every field is optional, deliberately.
     *
     * Spec §7 requires onboarding to be resumable, which means the owner must be able to
     * save half a profile and come back. Marking fields required here would make the step
     * all-or-nothing and contradict the one thing §7 is explicit about.
     *
     * Whether there is *enough* to count the step done is a separate question, answered by
     * BusinessProfile::isSufficient() rather than by validation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:32'],

            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', 'string', 'max:64'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:16'],

            // Two letters, uppercase. Defaults to US at the database level (spec §1 is a
            // US-market product) but is not fixed to it.
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],

            'service_area' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('country') && is_string($this->input('country'))) {
            $this->merge(['country' => strtoupper($this->string('country')->toString())]);
        }
    }

    /**
     * Only the keys actually submitted, so a partial save does not blank the rest of the
     * profile by filling absent fields with null.
     *
     * @return array<string, mixed>
     */
    public function profileAttributes(): array
    {
        return $this->safe()->only(array_keys($this->rules()));
    }
}
