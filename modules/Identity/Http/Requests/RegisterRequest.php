<?php

namespace Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],

            // Checked across all tenants: users.email is globally unique, so a duplicate would
            // otherwise fail on the index after the tenant row had already been created.
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],

            // Password::defaults() is set once in AppServiceProvider, so every entry point in the
            // product enforces the same policy rather than each form inventing its own.
            'password' => ['required', 'confirmed', Password::defaults()],

            'timezone' => ['sometimes', 'string', 'timezone:all', 'max:64'],
        ];
    }
}
