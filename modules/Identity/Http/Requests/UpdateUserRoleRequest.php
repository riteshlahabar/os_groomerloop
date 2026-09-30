<?php

namespace Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Identity\Domain\Role;

final class UpdateUserRoleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(Role::assignableValues())],
        ];
    }

    public function role(): Role
    {
        return Role::from($this->string('role')->toString());
    }
}
