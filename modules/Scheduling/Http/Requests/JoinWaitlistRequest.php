<?php

namespace Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape-only validation. Whether the ids actually exist, belong to each other and are eligible is
 * `JoinWaitlist`'s job, checked through each owning module's own contract — the same split
 * `BookAppointmentRequest` uses.
 */
final class JoinWaitlistRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'min:1'],
            'pet_id' => ['required', 'integer', 'min:1'],
            'service_id' => ['required', 'integer', 'min:1'],
            'staff_member_id' => ['nullable', 'integer', 'min:1'],
            'requested_date' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function waitlistAttributes(): array
    {
        return $this->safe()->all();
    }
}
