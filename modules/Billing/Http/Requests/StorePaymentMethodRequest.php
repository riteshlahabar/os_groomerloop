<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StorePaymentMethodRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // A single-use token from the gateway's client SDK, never a card number. There is
            // deliberately no `number`, `cvc` or `exp` rule here: accepting those fields at
            // all would put a PAN in the request log (spec §28).
            'token' => ['required', 'string', 'max:255'],
            'make_default' => ['sometimes', 'boolean'],
        ];
    }

    public function token(): string
    {
        return $this->string('token')->toString();
    }

    public function makeDefault(): bool
    {
        return ! $this->has('make_default') || $this->boolean('make_default');
    }
}
