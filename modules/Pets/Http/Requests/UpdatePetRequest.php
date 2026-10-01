<?php

namespace Modules\Pets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Pets\Domain\CoatType;
use Modules\Pets\Domain\PetSex;
use Modules\Pets\Domain\PetSpecies;
use Modules\Pets\Domain\PetStatus;

final class UpdatePetRequest extends FormRequest
{
    /**
     * `sometimes` throughout, so a partial update leaves untouched fields alone rather than
     * nulling them — the same reasoning as the customer record and the business profile.
     *
     * `customer_id` is absent: re-homing a pet moves its entire grooming history to another
     * family, which is not something an edit form may do as a side effect. Merging customers
     * moves pets, through the participant.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'species' => ['sometimes', 'required', Rule::enum(PetSpecies::class)],

            'breed' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sex' => ['sometimes', 'nullable', Rule::enum(PetSex::class)],

            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before_or_equal:today', 'after:1980-01-01'],
            'approximate_age_years' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:40'],

            'weight_lb' => ['sometimes', 'nullable', 'numeric', 'min:0.1', 'max:400'],

            'coat_type' => ['sometimes', 'nullable', Rule::enum(CoatType::class)],
            'coat_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],

            'customer_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'temperament_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'special_instructions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'medical_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],

            'status' => ['sometimes', Rule::enum(PetStatus::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function after(): array
    {
        return [
            // Setting one clears the other, so an update can switch between them — but sending
            // both in one request is still a contradiction.
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
     * @return array<string, mixed>
     */
    public function petAttributes(): array
    {
        $attributes = $this->safe()->all();

        // The two age fields are one fact stored two ways, so setting either must clear the
        // other. Without this, correcting "about 7" to a real birthday would leave both columns
        // populated and the record would be internally inconsistent.
        if (array_key_exists('date_of_birth', $attributes) && filled($attributes['date_of_birth'])) {
            $attributes['approximate_age_years'] = null;
        }

        if (array_key_exists('approximate_age_years', $attributes) && filled($attributes['approximate_age_years'])) {
            $attributes['date_of_birth'] = null;
        }

        return $attributes;
    }
}
