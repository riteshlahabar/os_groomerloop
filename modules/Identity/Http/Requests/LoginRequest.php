<?php

namespace Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],

            // Deliberately no Password::defaults() here. Validating the shape of a submitted
            // password on login would reject a legitimate older password and, worse, tell an
            // attacker what the policy is before they have authenticated.
            'password' => ['required', 'string'],

            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
