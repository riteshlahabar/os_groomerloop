<?php

namespace Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape-only validation (required, integer, date format). Whether the ids actually exist, belong
 * to each other, and are eligible/available is `BookAppointment`'s job — checked through each
 * owning module's own contract, the same split every other module in this codebase uses.
 */
final class BookAppointmentRequest extends FormRequest
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
            'starts_at' => ['required', 'date'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'add_on_service_ids' => ['nullable', 'array', 'max:25'],
            'add_on_service_ids.*' => ['integer', 'min:1'],

            // Recurrence is optional — present only when booking a series.
            'recurrence' => ['nullable', 'array'],
            'recurrence.interval_weeks' => ['required_with:recurrence', 'integer', 'min:1', 'max:26'],
            'recurrence.occurrences' => ['required_with:recurrence', 'integer', 'min:2', 'max:52'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function appointmentAttributes(): array
    {
        return $this->safe()->except(['recurrence', 'add_on_service_ids']);
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

    public function isRecurring(): bool
    {
        return $this->has('recurrence');
    }

    public function recurrenceIntervalWeeks(): int
    {
        return (int) $this->input('recurrence.interval_weeks');
    }

    public function recurrenceOccurrences(): int
    {
        return (int) $this->input('recurrence.occurrences');
    }
}
