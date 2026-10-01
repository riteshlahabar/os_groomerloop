<?php

namespace Modules\Team\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Team\Domain\StaffStatus;

final class StoreStaffMemberRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The only requirement. A salon adds "Maria" to the rota and fills the rest in later;
            // demanding an email would have owners typing fake ones for a Saturday junior.
            'display_name' => ['required', 'string', 'max:255'],

            'job_title' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],

            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],

            'status' => ['nullable', Rule::enum(StaffStatus::class)],
            'is_bookable_online' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],

            // Linking to an existing account. Checked with a tenant-scoped rule, never
            // `exists:users,id` — that would search every business and confirm a stranger's user id.
            'user_id' => ['nullable', 'integer', 'min:1', new UserIsUnlinkedMemberOfTenant],

            'service_ids' => ['nullable', 'array', 'max:100'],
            'service_ids.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function staffAttributes(): array
    {
        return $this->safe()->except(['user_id', 'service_ids']);
    }

    public function userId(): ?int
    {
        $userId = $this->input('user_id');

        return $userId === null || $userId === '' ? null : (int) $userId;
    }

    /**
     * Null leaves eligibility unrestricted — which for a new staff member means "can do everything".
     *
     * @return list<int>|null
     */
    public function serviceIds(): ?array
    {
        return $this->has('service_ids')
            ? array_map('intval', (array) $this->input('service_ids', []))
            : null;
    }
}
