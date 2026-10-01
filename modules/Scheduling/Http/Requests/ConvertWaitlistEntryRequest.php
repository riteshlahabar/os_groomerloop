<?php

namespace Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

final class ConvertWaitlistEntryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],

            // Overrides the entry's own staff member — staff may find an opening with someone
            // other than who the customer originally asked for.
            'staff_member_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function start(): Carbon
    {
        return Carbon::parse($this->string('starts_at')->toString());
    }

    public function staffMemberId(): ?int
    {
        return $this->filled('staff_member_id') ? (int) $this->input('staff_member_id') : null;
    }
}
