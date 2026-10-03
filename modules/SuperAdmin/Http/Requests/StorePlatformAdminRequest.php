<?php

namespace Modules\SuperAdmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Identity\Domain\Role;

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

            // Only GroomerLoop's own two tiers (`D-035`). `Rule::in` over `Role::platformValues()`
            // rather than `Rule::enum(Role::class)`, which would also accept `owner` and create a
            // tenant role with no tenant.
            'role' => ['required', Rule::in(Role::platformValues())],
        ];
    }

    public function platformRole(): Role
    {
        return Role::from($this->string('role')->toString());
    }
}
