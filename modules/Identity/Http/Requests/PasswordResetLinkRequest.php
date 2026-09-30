<?php

namespace Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PasswordResetLinkRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Deliberately not validated as `exists:users`. Telling an unauthenticated caller
            // whether an address has an account turns this endpoint into an account-enumeration
            // oracle, so an unknown address is accepted and simply produces no email.
            'email' => ['required', 'string', 'email'],
        ];
    }
}
