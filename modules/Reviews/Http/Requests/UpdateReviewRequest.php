<?php

namespace Modules\Reviews\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Crm\Contracts\CustomerDirectory;

/**
 * Same rules as {@see StoreReviewRequest} — editing a logged review corrects the same fields
 * it was recorded with. A separate class, not a subclass: `StoreReviewRequest` is `final`, the
 * same shape `StorePetRequest`/`UpdatePetRequest` already use for an identical reason.
 */
final class UpdateReviewRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', 'max:64'],
            'customer_id' => ['nullable', 'integer', 'min:1', $this->customerExists()],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'reviewed_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

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
}
