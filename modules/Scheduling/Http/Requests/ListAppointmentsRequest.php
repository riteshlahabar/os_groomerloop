<?php

namespace Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Scheduling\Domain\AppointmentStatus;
use Modules\Scheduling\Services\AppointmentIndex;

final class ListAppointmentsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after:from'],

            'staff_member_id' => ['nullable', 'integer', 'min:1'],
            'customer_id' => ['nullable', 'integer', 'min:1'],

            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(AppointmentStatus::class)],

            'sort' => ['nullable', Rule::in(AppointmentIndex::sortableFields())],
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
