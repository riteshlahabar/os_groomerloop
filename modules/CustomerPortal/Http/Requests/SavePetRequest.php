<?php

namespace Modules\CustomerPortal\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Pets\Contracts\PetDirectory;
use Modules\Pets\Domain\CoatType;
use Modules\Pets\Domain\PetSex;

/**
 * A customer adding or editing one of their own pets from the portal (`D-043`).
 *
 * One request for both verbs, because §9's field set does not change between them and two classes
 * would be two places to forget something. `POST` and `PUT` differ only in whether a pet id is in
 * the URL, which is the route's business, not this class's.
 *
 * The field list is §9's, minus the two a customer may never write and the one nothing can:
 *
 *   * `internal_notes` — staff-only, with its own §9 permission, and `PetSummary` does not even
 *     carry the value, so a customer cannot read one either.
 *   * `status` — archiving a pet, or recording that it has died, is the business's record-keeping
 *     act; `UpdatePet` raises a separate audit event for the latter.
 *   * `photo` — §9 lists one and the product has no upload path for it (§28, `D-016`'s scope was
 *     the website's two branding images only).
 *
 * §9's "service preferences" has no column anywhere yet, so it is not here either.
 *
 * `PetDirectory` filters to the same list again on its own side. Deliberate duplication: this says
 * what a well-behaved client sends, the contract guarantees what reaches the database.
 */
final class SavePetRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            // Shape here, existence through the owning module's contract below — never
            // `exists:pet_species,id`, which searches every tenant and would let a probing caller
            // confirm another business's species id (invariant #1).
            'species_id' => ['required', 'integer', 'min:1', $this->speciesExists()],

            'breed' => ['nullable', 'string', 'max:255'],
            'sex' => ['nullable', Rule::enum(PetSex::class)],

            // The same either/or the staff-side form enforces: a real date of birth, or an
            // approximate age when the customer genuinely does not know one (a rescue), never
            // both — `Pet::ageYears()` would otherwise have two sources of truth.
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today', 'after:1980-01-01'],
            'approximate_age_years' => ['nullable', 'integer', 'min:0', 'max:40'],

            'weight_lb' => ['nullable', 'numeric', 'min:0.1', 'max:400'],
            'coat_type' => ['nullable', Rule::enum(CoatType::class)],
            'coat_notes' => ['nullable', 'string', 'max:2000'],

            'customer_notes' => ['nullable', 'string', 'max:5000'],
            'temperament_notes' => ['nullable', 'string', 'max:2000'],
            'special_instructions' => ['nullable', 'string', 'max:2000'],

            // §9: "Health-related information must not be presented as veterinary diagnosis." The
            // customer is the right author for their own pet's health notes, and the portal labels
            // them as not veterinary advice wherever they are shown.
            'medical_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('date_of_birth') && $this->filled('approximate_age_years')) {
                $validator->errors()->add(
                    'approximate_age_years',
                    'Give a date of birth or an approximate age, not both.'
                );
            }
        });
    }

    private function speciesExists(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! app(PetDirectory::class)->speciesExists((int) $value)) {
                $fail('The selected species could not be found.');
            }
        };
    }
}
