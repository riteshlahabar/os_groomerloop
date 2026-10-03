<?php

namespace Modules\SuperAdmin\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Identity\Domain\Role;

/**
 * Editing a GroomerLoop staff account (`D-034`).
 *
 * `role` and `tenant_id` are absent on purpose and are not fillable on the model either — this
 * screen edits who someone is, never what they are.
 */
final class UpdatePlatformAdminRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $admin = $this->route('admin');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                // Unique across every user, excluding this one — otherwise saving the form
                // without touching the email would collide with itself.
                Rule::unique('users', 'email')->ignore($admin instanceof User ? $admin->getKey() : null),
            ],
            // Blank or omitted keeps the current password; when given it must still clear
            // Password::defaults().
            'password' => ['nullable', 'string', Password::defaults()],

            // Only GroomerLoop's own two tiers (`D-035`) — never a tenant role, which would leave
            // a user with no tenant and a role that expects one. Omitted keeps the current tier.
            'role' => ['nullable', Rule::in(Role::platformValues())],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adminAttributes(): array
    {
        return $this->safe()->all();
    }
}
