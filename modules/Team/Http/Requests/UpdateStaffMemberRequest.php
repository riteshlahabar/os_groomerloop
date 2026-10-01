<?php

namespace Modules\Team\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateStaffMemberRequest extends FormRequest
{
    /**
     * `sometimes` throughout, so a partial update leaves untouched fields alone.
     *
     * `user_id` is absent: linking or unlinking an account changes who can see a groomer's calendar, so
     * it is its own endpoint with its own audit event rather than a field on an edit form.
     *
     * `status` is absent too, the same way Catalog's `UpdateServiceRequest` omits `is_add_on`: status
     * transitions go through `DeactivateStaffMember::execute()`/`reactivate()` only, so the dedicated
     * `staff.deactivated`/`staff.reactivated` audit events and the `still_has_login` note are never
     * bypassed by a plain edit.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],

            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],

            'is_bookable_online' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:9999'],

            'service_ids' => ['sometimes', 'nullable', 'array', 'max:100'],
            'service_ids.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function staffAttributes(): array
    {
        return $this->safe()->except('service_ids');
    }

    /**
     * Null leaves eligibility alone; an empty array clears every restriction, which means this groomer
     * can do everything. Collapsing the two would make it impossible to lift a restriction.
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
