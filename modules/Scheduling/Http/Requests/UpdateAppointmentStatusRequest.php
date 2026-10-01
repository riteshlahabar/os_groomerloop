<?php

namespace Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Scheduling\Domain\AppointmentStatus;

final class UpdateAppointmentStatusRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function status(): AppointmentStatus
    {
        return AppointmentStatus::from($this->string('status')->toString());
    }

    public function note(): ?string
    {
        return $this->string('note')->toString() ?: null;
    }
}
