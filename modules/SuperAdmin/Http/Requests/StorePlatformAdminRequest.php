<?php

namespace Modules\SuperAdmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * A new GroomerLoop staff account (`D-034`).
 *
 * The password rules are `Password::defaults()`, the same ones the console command enforces —
 * moving this to a screen must not quietly lower the bar on the most privileged role in the
 * product.
 */
final class StorePlatformAdminRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Unique across ALL users, not just platform admins: one email is one login, and a
            // business owner's address must never also be able to reach /platform.
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
        ];
    }
}
