<?php

namespace Modules\CustomerPortal\Http\Requests;

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

            // Deliberately no Password::defaults() here, the same reasoning as Identity's own
            // LoginRequest: validating shape on login would reject a legitimate older password
            // and would tell an attacker the policy before they have authenticated.
            'password' => ['required', 'string'],

            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
