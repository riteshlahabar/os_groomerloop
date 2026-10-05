<?php

namespace Modules\Reviews\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Crm\Contracts\CustomerDirectory;

final class StoreReviewRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', 'max:64'],

            // Not required: not every external review can be matched to a system customer.
            // Existence is checked through Crm's contract rather than `exists:customers,id`,
            // the same reasoning `StorePetRequest::customerExists()` documents — that rule
            // would search every tenant's customers (invariant #1).
            'customer_id' => ['nullable', 'integer', 'min:1', $this->customerExists()],

            // Not every platform uses stars.
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],

            'comment' => ['nullable', 'string', 'max:2000'],

            // A review cannot have happened in the future.
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
