<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StartSubscriptionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Validated against the plans table rather than an in-code list, because the
            // catalog is data (invariant #3) and a Rule::in of plan names here would be
            // exactly the hard-coding the guard test bans.
            'plan' => ['required', 'string', 'exists:plans,key'],
        ];
    }

    public function planKey(): string
    {
        return $this->string('plan')->toString();
    }
}
