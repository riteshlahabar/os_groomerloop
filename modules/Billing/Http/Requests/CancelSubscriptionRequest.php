<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CancelSubscriptionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Defaults to cancelling at period end: the business has paid for this month and
            // should keep it. Immediate cancellation is opt-in.
            'immediately' => ['sometimes', 'boolean'],
            'reason' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function immediately(): bool
    {
        return $this->boolean('immediately');
    }

    public function reason(): string
    {
        return $this->string('reason')->toString() ?: 'requested';
    }
}
