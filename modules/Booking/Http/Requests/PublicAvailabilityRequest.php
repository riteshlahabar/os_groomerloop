<?php

namespace Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

final class PublicAvailabilityRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'min:1'],
            'staff_member_id' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
        ];
    }

    public function serviceId(): int
    {
        return (int) $this->input('service_id');
    }

    public function staffMemberId(): ?int
    {
        $id = $this->input('staff_member_id');

        return $id === null || $id === '' ? null : (int) $id;
    }

    public function start(): Carbon
    {
        return Carbon::parse($this->string('starts_at')->toString());
    }
}
