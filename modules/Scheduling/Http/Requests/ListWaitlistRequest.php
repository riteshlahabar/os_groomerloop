<?php

namespace Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Scheduling\Domain\WaitlistStatus;

final class ListWaitlistRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(WaitlistStatus::class)],

            'service_id' => ['nullable', 'integer', 'min:1'],
            'staff_member_id' => ['nullable', 'integer', 'min:1'],
            'requested_date' => ['nullable', 'date'],

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
