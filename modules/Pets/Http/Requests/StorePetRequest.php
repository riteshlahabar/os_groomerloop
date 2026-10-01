<?php

namespace Modules\Pets\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Pets\Domain\CoatType;
use Modules\Pets\Domain\PetSex;
use Modules\Pets\Domain\PetSpecies;
use Modules\Pets\Domain\PetStatus;

final class StorePetRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'min:1', $this->customerExists()],

            // Name and species are the only requirements. A front desk taking a booking over the
            // phone has those two and often nothing else; demanding a breed or a birthday would
            // have staff typing guesses, which is worse than holding nothing.
            'name' => ['required', 'string', 'max:255'],
            'species' => ['required', Rule::enum(PetSpecies::class)],

            'breed' => ['nullable', 'string', 'max:255'],
            'sex' => ['nullable', Rule::enum(PetSex::class)],

            // Both accepted, and at most one used — see the after() rule.
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today', 'after:1980-01-01'],
            'approximate_age_years' => ['nullable', 'integer', 'min:0', 'max:40'],

            'weight_lb' => ['nullable', 'numeric', 'min:0.1', 'max:400'],

            'coat_type' => ['nullable', Rule::enum(CoatType::class)],
            'coat_notes' => ['nullable', 'string', 'max:2000'],

            'customer_notes' => ['nullable', 'string', 'max:5000'],
            'temperament_notes' => ['nullable', 'string', 'max:2000'],
            'special_instructions' => ['nullable', 'string', 'max:2000'],
            'medical_notes' => ['nullable', 'string', 'max:5000'],

            'status' => ['nullable', Rule::enum(PetStatus::class)],

            // internal_notes is deliberately absent. Spec §9 gives it its own permission, so it
            // has its own endpoint; accepting it here would either be ignored or would quietly
            // become a way for a role without that permission to write it.
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function after(): array
    {
        return [
            // Recording both is a contradiction rather than extra detail, and whichever the UI
            // then showed would be a coin toss. Date of birth is the better fact, so the caller
            // is told to pick.
            function (Validator $validator): void {
                if ($this->filled('date_of_birth') && $this->filled('approximate_age_years')) {
                    $validator->errors()->add(
                        'approximate_age_years',
                        'Give a date of birth or an approximate age, not both.'
                    );
                }
            },
        ];
    }

    /**
     * Existence is checked through Crm's contract, never with `exists:customers,id`.
     *
     * Two reasons, and both matter. The rule would search every tenant's customers, so a
     * probing caller could confirm that some other business holds a given id (invariant #1) —
     * the contract is tenant-scoped and answers false. And naming another module's table in a
     * validation rule is the same coupling `ModuleBoundaryGuardTest` exists to prevent; it simply
     * spells the table rather than the model class.
     */
    private function customerExists(): ValidationRule
    {
        return new class implements ValidationRule
        {
            public function validate(string $attribute, mixed $value, \Closure $fail): void
            {
                if (! app(CustomerDirectory::class)->exists((int) $value)) {
                    $fail('The selected customer could not be found.');
                }
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function petAttributes(): array
    {
        return $this->safe()->except('customer_id');
    }

    public function customerId(): int
    {
        return (int) $this->input('customer_id');
    }
}
