<?php

namespace Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Identity\Domain\Role;

final class InviteTeamMemberRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],

            // assignableValues() excludes platform_admin, so no business can invite someone as
            // GroomerLoop staff. The action re-checks this rather than trusting validation alone.
            'role' => ['required', 'string', Rule::in(Role::assignableValues())],
        ];
    }

    public function role(): Role
    {
        return Role::from($this->string('role')->toString());
    }
}
