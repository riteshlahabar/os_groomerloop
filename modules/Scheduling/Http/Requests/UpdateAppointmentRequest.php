<?php

namespace Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Edits notes, add-ons and the assigned groomer only. `customer_id`, `pet_id` and `service_id`
 * are absent deliberately — changing who or what an appointment is for is a new booking, not an
 * edit. `starts_at`/`ends_at` and `status` are absent too: reschedule and status-update are their
 * own endpoints with their own audit trail, the exact bug Team's `UpdateStaffMemberRequest` was
 * fixed to stop repeating.
 */
final class UpdateAppointmentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'staff_member_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'customer_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'add_on_service_ids' => ['sometimes', 'nullable', 'array', 'max:25'],
            'add_on_service_ids.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function appointmentAttributes(): array
    {
        return $this->safe()->except('add_on_service_ids');
    }

    /**
     * @return list<int>|null
     */
    public function addOnServiceIds(): ?array
    {
        return $this->has('add_on_service_ids')
            ? array_map('intval', (array) $this->input('add_on_service_ids', []))
            : null;
    }
}
