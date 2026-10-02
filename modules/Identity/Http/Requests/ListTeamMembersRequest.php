<?php

namespace Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Identity\Domain\Role;
use Modules\Identity\Services\TeamMemberIndex;

final class ListTeamMembersRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],

            // Validated against the enum rather than a free string, so an unknown role filters
            // to nothing visibly instead of silently returning the unfiltered list.
            'role' => ['nullable', Rule::enum(Role::class)],

            'sort' => ['nullable', Rule::in(TeamMemberIndex::sortableFields())],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->all();
    }
}
