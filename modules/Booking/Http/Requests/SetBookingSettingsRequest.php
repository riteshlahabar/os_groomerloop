<?php

namespace Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Booking\Domain\ConfirmationMode;

final class SetBookingSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lead_time_minutes' => ['sometimes', 'integer', 'min:0', 'max:10080'],
            'cancellation_window_hours' => ['sometimes', 'integer', 'min:0', 'max:720'],
            'confirmation_mode' => ['sometimes', Rule::enum(ConfirmationMode::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsAttributes(): array
    {
        return $this->safe()->all();
    }
}
