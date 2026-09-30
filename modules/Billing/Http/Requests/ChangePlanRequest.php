<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ChangePlanRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', 'exists:plans,key'],
        ];
    }

    public function planKey(): string
    {
        return $this->string('plan')->toString();
    }
}
